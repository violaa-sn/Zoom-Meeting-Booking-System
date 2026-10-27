<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecommendBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Department;
use App\Models\ZoomAccount;
use App\Services\BookingService;
use App\Services\CalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly BookingService $bookings,
    ) {
    }

    /**
     * Kalender mingguan, 24 jam per hari dengan slot 30 menit.
     *
     * ?account=      kosong -> hanya akun utama (supaya halaman tidak terlalu panjang)
     *                angka  -> akun tertentu
     *                all    -> semua akun aktif (lewat filter)
     * ?week=Y-m-d    tanggal apa saja di pekan yang diinginkan
     */
    public function calendar(Request $request): View
    {
        $weekStart = $this->calendar->weekStart($request->query('week'));
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

        return view('booking.calendar', [
            'weeks' => $shown->map(fn (ZoomAccount $a) => $this->calendar->week($a, $weekStart)),
            'accounts' => $accounts,
            'selectedAccount' => $selected === 'all' ? 'all' : ($shown->count() === 1 ? $shown->first()->id : null),
            'weekStart' => $weekStart,
            'prevWeek' => $weekStart->subWeek()->format('Y-m-d'),
            'nextWeek' => $weekStart->addWeek()->format('Y-m-d'),
        ]);
    }

    /** Form pengajuan (langkah 1: isi jadwal). */
    public function create(): View
    {
        return view('booking.create', [
            'purposes' => Booking::PURPOSES,
            'recommendations' => null,
        ]);
    }

    /**
     * Langkah 2: jadwal sudah valid, tampilkan akun yang tersedia (akun utama dulu)
     * beserta alasan untuk akun yang penuh. GET supaya bisa di-refresh / kembali tanpa kirim ulang form.
     */
    public function recommend(RecommendBookingRequest $request): View
    {
        $intervals = $this->bookings->intervalsFrom($request->validated());

        return view('booking.create', [
            'purposes' => Booking::PURPOSES,
            'recommendations' => $this->bookings->recommend($intervals),
            'occurrenceCount' => count($intervals),
            'firstDate' => $intervals[0]['starts_at'] ?? null,
            'lastDate' => $intervals ? $intervals[array_key_last($intervals)]['starts_at'] : null,
        ]);
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
}
