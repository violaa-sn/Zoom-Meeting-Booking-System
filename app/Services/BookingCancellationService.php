<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingNotification;
use App\Models\BookingOccurrence;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan booking oleh pemiliknya (atau admin).
 *
 * - Pending: langsung jadi cancelled.
 * - Approved: occurrence yang belum berakhir dibatalkan (slot langsung kosong lagi),
 *   occurrence yang sudah lewat tetap sebagai riwayat.
 * - cancelOccurrence(): hanya satu tanggal dari seri berulang.
 */
class BookingCancellationService
{
    /** @throws DomainException pesan siap tampil ke user */
    public function cancel(Booking $booking, User $actor): void
    {
        DB::transaction(function () use ($booking, $actor) {
            $booking = Booking::with('zoomAccount', 'user')->lockForUpdate()->findOrFail($booking->id);

            if (! $actor->isAdmin() && $booking->user_id !== $actor->id) {
                throw new DomainException('Kamu hanya bisa membatalkan booking milikmu sendiri.');
            }
            if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_APPROVED], true)) {
                throw new DomainException('Booking ini sudah tidak aktif, jadi tidak bisa dibatalkan.');
            }

            $wasApproved = $booking->status === Booking::STATUS_APPROVED;

            if ($wasApproved) {
                $released = BookingOccurrence::where('booking_id', $booking->id)
                    ->approved()
                    ->where('ends_at', '>', now())
                    ->update(['status' => BookingOccurrence::STATUS_CANCELLED]);

                if ($released === 0) {
                    throw new DomainException('Semua jadwal booking ini sudah selesai, tidak ada yang bisa dibatalkan.');
                }
            }

            $booking->update(['status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now()]);

            $this->notify($booking, $actor, $wasApproved);
        });
    }

    /**
     * Batalkan SATU tanggal dari booking yang sudah disetujui (mis. satu pertemuan dari seri mingguan).
     * Seri lainnya tetap berjalan. Kalau tidak ada lagi tanggal aktif sama sekali, booking ikut dibatalkan.
     *
     * @throws DomainException pesan siap tampil ke user
     */
    public function cancelOccurrence(BookingOccurrence $occurrence, User $actor): void
    {
        DB::transaction(function () use ($occurrence, $actor) {
            $occurrence = BookingOccurrence::lockForUpdate()->findOrFail($occurrence->id);
            $booking = Booking::with('zoomAccount', 'user')->lockForUpdate()->findOrFail($occurrence->booking_id);

            if (! $actor->isAdmin() && $booking->user_id !== $actor->id) {
                throw new DomainException('Kamu hanya bisa membatalkan booking milikmu sendiri.');
            }
            if ($occurrence->status !== BookingOccurrence::STATUS_APPROVED) {
                throw new DomainException('Tanggal ini sudah dibatalkan.');
            }
            if ($occurrence->ends_at <= now()) {
                throw new DomainException('Tanggal ini sudah berlangsung, tidak bisa dibatalkan.');
            }

            $occurrence->update(['status' => BookingOccurrence::STATUS_CANCELLED]);

            // Tidak ada tanggal aktif tersisa (termasuk yang sudah lewat): seluruh booking dianggap batal
            $remaining = BookingOccurrence::where('booking_id', $booking->id)->approved()->exists();
            if (! $remaining) {
                $booking->update(['status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now()]);
            }

            $date = $occurrence->starts_at->locale('id')->translatedFormat('j M Y')
                .' ('.$occurrence->starts_at->format('H:i').'–'.$occurrence->ends_at->format('H:i').')';
            $accountName = $booking->zoomAccount?->name ?? 'Zoom';
            $what = "booking {$accountName} pada {$date}";

            if ($actor->isAdmin() && $booking->user_id !== $actor->id) {
                BookingNotification::create([
                    'user_id' => $booking->user_id, 'booking_id' => $booking->id, 'type' => BookingNotification::TYPE_CANCELLED,
                    'message' => "Admin membatalkan {$what}.",
                ]);
            } else {
                foreach (User::admins()->active()->get() as $admin) {
                    BookingNotification::create([
                        'user_id' => $admin->id, 'booking_id' => $booking->id, 'type' => BookingNotification::TYPE_CANCELLED,
                        'message' => "{$booking->user->name} membatalkan {$what}. Slot kembali kosong.",
                    ]);
                }
            }
        });
    }

    private function notify(Booking $booking, User $actor, bool $wasApproved): void
    {
        $when = $booking->starts_on->locale('id')->translatedFormat('j M Y');
        // Booking pending belum punya akun Zoom (dipilih admin saat menyetujui)
        $accountName = $booking->zoomAccount?->name ?? 'Zoom';
        $what = "{$accountName} mulai {$when}";

        if ($actor->isAdmin() && $booking->user_id !== $actor->id) {
            // Admin membatalkan: peminjam diberi tahu
            BookingNotification::create([
                'user_id' => $booking->user_id, 'booking_id' => $booking->id,
                'type' => BookingNotification::TYPE_CANCELLED,
                'message' => "Booking {$what} dibatalkan oleh admin.",
            ]);

            return;
        }

        // Peminjam membatalkan booking yang sudah disetujui: admin perlu tahu bahwa slot kembali kosong.
        // Pembatalan pengajuan pending tidak perlu notifikasi (hitungan Persetujuan otomatis berkurang).
        if ($wasApproved) {
            foreach (User::admins()->active()->get() as $admin) {
                BookingNotification::create([
                    'user_id' => $admin->id, 'booking_id' => $booking->id,
                    'type' => BookingNotification::TYPE_CANCELLED,
                    'message' => "{$booking->user->name} membatalkan booking {$what}. Slot kembali kosong.",
                ]);
            }
        }
    }
}
