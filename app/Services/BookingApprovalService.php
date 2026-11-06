<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingNotification;
use App\Models\BookingOccurrence;
use App\Models\User;
use App\Models\ZoomAccount;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Persetujuan booking oleh admin.
 *
 * Alur: user mengajukan tanpa memilih akun. Saat admin menyetujui, admin melihat rekomendasi akun
 * (recommendations) lalu MEMILIH satu akun (approve).
 * Approve membuat baris booking_occurrences, dan di sinilah status Penuh dicek untuk terakhir kali.
 * Akun Zoom dikunci (lock) selama transaksi, jadi dua booking pending yang berebut slot terakhir
 * tidak bisa sama-sama lolos: yang di-approve duluan menang, yang kedua ditolak sistem.
 */
class BookingApprovalService
{
    public function __construct(
        private readonly RecurrenceExpander $expander,
        private readonly CalendarService $calendar,
        private readonly BookingService $bookings,
    ) {
    }

    /**
     * Ringkasan untuk halaman persetujuan: jumlah tanggal, rentang, dan apakah sudah lewat semua.
     * Bentrok per akun tidak dihitung di sini: baru dilihat saat admin menekan Setujui (lihat recommendations).
     *
     * @return array{count: int, range: string, expired: bool}
     */
    public function inspect(Booking $booking): array
    {
        $all = $this->expander->expand($booking);

        $first = $all[0]['starts_at'] ?? null;
        $last = $all ? $all[array_key_last($all)]['starts_at'] : null;
        $fmt = fn ($d) => $d->locale('id')->translatedFormat('D, j M Y');

        return [
            'count' => count($all),
            'range' => $first ? ($first->isSameDay($last) ? $fmt($first) : $fmt($first).' sampai '.$fmt($last)) : '-',
            'expired' => $this->upcoming($all) === [],
        ];
    }

    /**
     * Rekomendasi akun untuk admin: akun bertipe yang diminta user, lengkap dengan status
     * tersedia / penuh. Dipanggil lewat fetch saat admin menekan Setujui (tidak untuk semua baris sekaligus,
     * supaya halaman Persetujuan tetap ringan).
     *
     * @return Collection<int, array{account: \App\Models\ZoomAccount, available: bool, reason: ?string, conflicts: array}>
     */
    public function recommendations(Booking $booking): Collection
    {
        $intervals = $this->upcoming($this->expander->expand($booking));

        return $intervals === []
            ? collect()
            : $this->bookings->recommend($intervals, $booking->account_type, $booking->participant_count);
    }

    /**
     * @param  int  $accountId  akun Zoom yang dipilih admin
     * @return int jumlah tanggal meeting yang dibuat
     *
     * @throws DomainException pesan siap tampil ke admin
     */
    public function approve(Booking $booking, User $admin, int $accountId): int
    {
        return DB::transaction(function () use ($booking, $admin, $accountId) {
            $account = ZoomAccount::withTrashed()->lockForUpdate()->findOrFail($accountId);
            $booking = Booking::with('days')->lockForUpdate()->findOrFail($booking->id);

            if (! $booking->isPending()) {
                throw new DomainException('Pengajuan ini sudah diproses.');
            }
            if ($account->trashed() || $account->status !== ZoomAccount::STATUS_ACTIVE) {
                throw new DomainException('Akun Zoom ini tidak aktif.');
            }
            if ($account->account_type !== $booking->account_type) {
                throw new DomainException('User meminta akun '.ucfirst($booking->account_type).'. Pilih akun bertipe itu.');
            }

            if ($account->participant_capacity < Booking::minCapacityFor($booking->participant_count)) {
                throw new DomainException('Akun ini hanya mendukung '.number_format($account->participant_capacity, 0, ',', '.').' peserta, kurang dari yang diminta ('.$booking->participantLabel().'). Pilih akun lain.');
            }

            $intervals = $this->upcoming($this->expander->expand($booking));
            if ($intervals === []) {
                throw new DomainException('Tanggal booking sudah lewat.');
            }

            $lastDay = collect($intervals)->max(fn ($i) => $i['ends_at']->format('Y-m-d'));
            if ($account->subscription_expires_at && $account->subscription_expires_at->format('Y-m-d') < $lastDay) {
                throw new DomainException('Langganan akun berakhir sebelum seri booking selesai.');
            }

            $conflicts = $this->calendar->conflicts($account, $intervals, $booking->id);
            if ($conflicts !== []) {
                $dates = collect($conflicts)->take(3)->map(fn ($c) => $c['starts_at']->locale('id')->translatedFormat('j M'))->implode(', ');
                throw new DomainException("Akun sudah penuh pada {$dates}. Pengajuan tidak bisa disetujui.");
            }

            // Simpan per 500 baris sekaligus (seri panjang tidak perlu ribuan query terpisah)
            $stamp = now()->format('Y-m-d H:i:s');
            $rows = array_map(fn (array $i) => [
                'booking_id' => $booking->id,
                'zoom_account_id' => $account->id,
                'starts_at' => $i['starts_at']->format('Y-m-d H:i:s'),
                'ends_at' => $i['ends_at']->format('Y-m-d H:i:s'),
                'status' => BookingOccurrence::STATUS_APPROVED,
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ], $intervals);

            foreach (array_chunk($rows, 500) as $chunk) {
                BookingOccurrence::insert($chunk);
            }

            $booking->update([
                'zoom_account_id' => $account->id,
                'status' => Booking::STATUS_APPROVED,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            BookingNotification::create([
                'user_id' => $booking->user_id,
                'booking_id' => $booking->id,
                'type' => BookingNotification::TYPE_APPROVED,
                'message' => "Booking {$account->name} mulai ".$booking->starts_on->locale('id')->translatedFormat('j M Y').' disetujui.',
            ]);

            return count($intervals);
        });
    }

    /**
     * Beri tahu peminjam bahwa pengajuannya sudah lewat tanggal tetapi admin belum menyetujui.
     * Hanya untuk booking pending yang SEMUA tanggalnya sudah berakhir; sekali per booking
     * (ditandai expired_notified_at). Aman dipanggil berulang.
     *
     * @return int jumlah notifikasi yang dibuat
     */
    public function notifyExpiredPending(): int
    {
        $sent = 0;

        // Booking yang tanggal mulainya masih hari ini atau mendatang pasti belum lewat semua, tidak perlu di-expand
        Booking::pending()
            ->whereNull('expired_notified_at')
            ->whereDate('starts_on', '<=', now()->toDateString())
            ->with('days')
            ->chunkById(100, function ($bookings) use (&$sent) {
                foreach ($bookings as $booking) {
                    if ($this->upcoming($this->expander->expand($booking)) !== []) {
                        continue;
                    }

                    DB::transaction(function () use ($booking, &$sent) {
                        // Kunci baris supaya dua request bersamaan tidak mengirim notifikasi ganda
                        $fresh = Booking::lockForUpdate()->find($booking->id);
                        if (! $fresh || ! $fresh->isPending() || $fresh->expired_notified_at !== null) {
                            return;
                        }

                        $label = $fresh->purpose === 'lainnya' ? ($fresh->purpose_other ?: 'Lainnya') : ucfirst($fresh->purpose);
                        $date = $fresh->starts_on->locale('id')->translatedFormat('D, j M Y');
                        $time = substr($fresh->start_time, 0, 5).'–'.substr($fresh->end_time, 0, 5);

                        BookingNotification::create([
                            'user_id' => $fresh->user_id,
                            'booking_id' => $fresh->id,
                            'type' => BookingNotification::TYPE_EXPIRED,
                            'message' => "Booking \"{$label}\" ({$date}, {$time}) sudah lewat tanggal dan belum disetujui admin.",
                        ]);
                        $fresh->forceFill(['expired_notified_at' => now()])->save();
                        $sent++;
                    });
                }
            });

        return $sent;
    }

    /** @throws DomainException */
    public function reject(Booking $booking, User $admin, string $reason): void
    {
        DB::transaction(function () use ($booking, $admin, $reason) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);

            if (! $booking->isPending()) {
                throw new DomainException('Pengajuan ini sudah diproses.');
            }
            if ($this->upcoming($this->expander->expand($booking)) === []) {
                throw new DomainException('Tanggal booking sudah lewat.');
            }

            $booking->update([
                'status' => Booking::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'approved_by' => $admin->id,
            ]);

            BookingNotification::create([
                'user_id' => $booking->user_id,
                'booking_id' => $booking->id,
                'type' => BookingNotification::TYPE_REJECTED,
                'message' => 'Booking mulai '.$booking->starts_on->locale('id')->translatedFormat('j M Y')." ditolak: {$reason}",
            ]);
        });
    }

    /** Hanya tanggal yang belum berakhir. Tanggal lampau tidak dibuatkan occurrence. */
    private function upcoming(array $intervals): array
    {
        return array_values(array_filter($intervals, fn ($i) => $i['ends_at'] > now()));
    }
}
