<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecommendBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\BookingOccurrence;
use App\Models\Department;
use App\Models\ZoomAccount;
use App\Services\BookingCancellationService;
use App\Services\BookingService;
use App\Services\CalendarService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly BookingService $bookings,
        private readonly BookingCancellationService $cancellations,
    ) {
    }

    /**
     * Kalender rentang tanggal bebas (maksimal config('booking.max_range_days') hari), slot 30 menit.
     *
     * ?account=      kosong -> hanya akun utama (supaya halaman tidak terlalu panjang)
     *                angka  -> akun tertentu
     *                all    -> semua akun aktif (lewat filter)
     * ?from=&to=     format Y-m-d. Kosong -> pekan ini (Senin-Minggu)
     * ?days[]=       filter hari tertentu dari rentang (0 = Minggu ... 6 = Sabtu). Kosong -> semua hari
     */
    public function calendar(Request $request): View
    {
        [$from, $to, $clamped, $dayCount] = $this->calendar->resolveRange(
            $request->query('from'),
            $request->query('to'),
        );

        // Filter hari (lingkaran M S S R K J S). Nilai di luar 0-6 dibuang.
        $selectedDays = collect((array) $request->query('days', []))
            ->filter(fn ($d) => is_numeric($d) && $d >= 0 && $d <= 6)
            ->map(fn ($d) => (int) $d)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $accounts = ZoomAccount::active()->ordered()->get();
        $selected = $request->query('account');

        if ($selected === 'all') {
            $shown = $accounts;
        } elseif (is_numeric($selected)) {
            $shown = $accounts->where('id', (int) $selected)->values();
            abort_if($shown->isEmpty(), 404);
        } else {
            $default = $accounts->firstWhere('is_default', true) ?? $accounts->first();
            $shown = $default ? collect([$default]) : collect();
        }

        // Parameter query untuk tombol rentang di view
        $fmt = 'Y-m-d';
        $thisWeekStart = $this->calendar->weekStart();
        $nextWeekStart = $thisWeekStart->addWeek();

        return view('booking.calendar', [
            'grids' => $shown->map(fn (ZoomAccount $a) => $this->calendar->range($a, $from, $dayCount, $selectedDays)),
            'accounts' => $accounts,
            'selectedAccount' => $selected === 'all' ? 'all' : ($shown->count() === 1 ? $shown->first()->id : null),
            'from' => $from,
            'to' => $to,
            'dayCount' => $dayCount,
            'clamped' => $clamped,
            'selectedDays' => $selectedDays,
            'maxDays' => (int) config('booking.max_range_days', 14),
            // Geser mundur/maju selebar rentang yang sedang tampil
            'prev' => [
                'from' => $from->subDays($dayCount)->format($fmt),
                'to' => $from->subDay()->format($fmt),
            ],
            'next' => [
                'from' => $to->addDay()->format($fmt),
                'to' => $to->addDays($dayCount)->format($fmt),
            ],
            'thisWeek' => [
                'from' => $thisWeekStart->format($fmt),
                'to' => $thisWeekStart->addDays(6)->format($fmt),
            ],
            'nextWeek' => [
                'from' => $nextWeekStart->format($fmt),
                'to' => $nextWeekStart->addDays(6)->format($fmt),
            ],
        ]);
    }

    /**
     * Form pengajuan sekarang berupa panel (offcanvas) di semua halaman,
     * jadi URL ini cukup membuka kalender dengan panel yang sudah terbuka.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('booking.calendar', ['book' => 1]);
    }

    /**
     * Langkah 2 (dipanggil lewat fetch dari JS): jadwal sudah valid, kirim daftar akun
     * beserta status tersedia / penuh. Akun utama paling atas.
     * Kalau jadwal tidak valid, RecommendBookingRequest otomatis membalas 422 + daftar error.
     */
    public function recommend(RecommendBookingRequest $request): JsonResponse
    {
        $intervals = $this->bookings->intervalsFrom($request->validated());
        $first = $intervals[0] ?? null;

        $summary = $first
            ? $first['starts_at']->locale('id')->translatedFormat('l, j M Y')
                .', '.$first['starts_at']->format('H.i').'–'.$first['ends_at']->format('H.i')
                .(count($intervals) > 1 ? ' ('.count($intervals).' tanggal)' : '')
            : '';

        $accounts = $this->bookings->recommend($intervals, $request->validated('account_type'), $request->validated('participant_count'))->map(fn (array $row) => [
            'id' => $row['account']->id,
            'name' => $row['account']->name,
            'color' => $row['account']->color,
            'is_default' => $row['account']->is_default,
            'type' => ucfirst($row['account']->account_type),
            'capacity' => max(1, (int) $row['account']->allowed_login_users),
            'participants' => $row['account']->participant_capacity,
            'available' => $row['available'],
            'reason' => $row['reason'],
        ])->values();

        return response()->json(['summary' => $summary, 'accounts' => $accounts]);
    }

    /** Langkah 3: user memilih akun, pengajuan disimpan sebagai pending. */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $this->bookings->submit($request->user(), $request->validated());

        return redirect()
            ->route('booking.index')
            ->with('success', 'Pengajuan booking terkirim. Status akan dikonfirmasi admin.');
    }

    /**
     * Daftar booking dengan filter. Admin melihat semua; user biasa hanya miliknya
     * (user_id dipaksa, filter user_id dari query diabaikan).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = $request->only([
            'status', 'zoom_account_id', 'department_id', 'user_id',
            'purpose', 'recurring', 'date_from', 'date_to', 'search',
        ]);

        if (! $user->isAdmin()) {
            $filters['user_id'] = $user->id;
        }

        $bookings = Booking::query()
            ->with(['user.department', 'zoomAccount', 'days'])
            // has_upcoming dipakai view untuk menampilkan tombol Batalkan / Atur tanggal.
            // Satu subquery EXISTS, bukan satu query per baris.
            ->withExists(['occurrences as has_upcoming' => fn ($q) => $q
                ->approved()
                ->where('ends_at', '>', now())])
            ->filter($filters)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('booking.index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'accounts' => ZoomAccount::ordered()->get(),
            'departments' => Department::orderBy('name')->get(),
            'purposes' => Booking::PURPOSES,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    /**
     * Daftar tanggal nyata sebuah booking (JSON untuk modal "Atur tanggal").
     * Hanya pemilik atau admin yang boleh melihat.
     */
    public function occurrences(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $booking->user_id === $user->id, 403);

        $items = $booking->occurrences()
            ->orderBy('starts_at')
            ->get()
            ->map(function (BookingOccurrence $o) {
                $state = $o->status === BookingOccurrence::STATUS_CANCELLED
                    ? 'cancelled'
                    : ($o->ends_at <= now() ? 'done' : 'upcoming');

                return [
                    'date' => $o->starts_at->locale('id')->translatedFormat('D, j M Y'),
                    'time' => $o->starts_at->format('H:i').' – '.$o->ends_at->format('H:i'),
                    'state' => $state,
                    // Tombol Batalkan hanya untuk tanggal yang belum berlangsung dan belum dibatalkan
                    'cancel_url' => $state === 'upcoming' ? route('booking.occurrences.cancel', $o) : null,
                ];
            })
            ->values();

        return response()->json([
            'account' => $booking->zoomAccount?->name ?? 'Belum ditentukan',
            'items' => $items,
        ]);
    }

    /** Batalkan seluruh booking (pending, atau approved yang masih punya tanggal mendatang). */
    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        try {
            $this->cancellations->cancel($booking, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Booking dibatalkan.');
    }

    /** Batalkan SATU tanggal dari seri berulang. */
    public function cancelOccurrence(Request $request, BookingOccurrence $occurrence): RedirectResponse
    {
        try {
            $this->cancellations->cancelOccurrence($occurrence, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tanggal dibatalkan. Slot kembali kosong.');
    }
}
