<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ZoomAccount;
use App\Services\BookingApprovalService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Menu Persetujuan (khusus admin; route dijaga can:admin). */
class ApprovalController extends Controller
{
    public function __construct(private readonly BookingApprovalService $approvals)
    {
    }

    public function index(Request $request): View
    {
        $bookings = Booking::query()
            ->pending()
            // Pengajuan pending belum punya akun Zoom, jadi tidak ada relasi zoomAccount yang dimuat
            ->with(['user.department', 'days'])
            ->filter($request->only(['account_type', 'search']))
            ->oldest() // yang paling lama menunggu di atas
            ->paginate(10)
            ->withQueryString();

        return view('approvals.index', [
            'bookings' => $bookings,
            'info' => $bookings->getCollection()->mapWithKeys(fn (Booking $b) => [$b->id => $this->approvals->inspect($b)]),
            'accountTypes' => ZoomAccount::TYPES,
        ]);
    }

    /**
     * Daftar rekomendasi akun (JSON) untuk modal "Setujui". Admin memilih satu akun dari daftar ini.
     * Dipanggil hanya saat admin menekan Setujui, jadi pengecekan bentrok tidak berjalan untuk semua baris sekaligus.
     */
    public function recommend(Booking $booking): JsonResponse
    {
        abort_unless($booking->isPending(), 404);

        $accounts = $this->approvals->recommendations($booking)->map(fn (array $row) => [
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

        return response()->json([
            'type' => ucfirst($booking->account_type),
            'accounts' => $accounts,
        ]);
    }

    public function approve(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['zoom_account_id' => ['required', 'integer', 'exists:zoom_accounts,id']], [
            'zoom_account_id.required' => 'Pilih akun Zoom dulu sebelum menyetujui.',
        ]);

        try {
            $count = $this->approvals->approve($booking, $request->user(), (int) $data['zoom_account_id']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('approvals.index')
            ->with('success', $count > 1 ? "Booking disetujui ({$count} tanggal)." : 'Booking disetujui.');
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
        ]);

        try {
            $this->approvals->reject($booking, $request->user(), trim($data['reason']));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('approvals.index')->with('success', 'Pengajuan ditolak.');
    }
}
