<?php

namespace App\Http\Middleware;

use App\Services\BookingApprovalService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memberi tahu peminjam bila pengajuannya (pending) sudah lewat tanggal dan belum disetujui admin.
 *
 * Dijalankan lazy saat ada request dari user yang login, paling sering sekali per menit
 * (Cache::add atomik), jadi tidak butuh cron/scheduler. Kegagalan di sini tidak boleh
 * mengganggu halaman yang sedang dibuka.
 */
class NotifyExpiredBookings
{
    public function __construct(private readonly BookingApprovalService $approvals)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->isMethod('GET') && ! $request->expectsJson() && Cache::add('expired-bookings-sweep', 1, 60)) {
            try {
                $this->approvals->notifyExpiredPending();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }
}
