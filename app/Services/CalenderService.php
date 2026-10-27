<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingOccurrence;
use App\Models\ZoomAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Kalender mingguan per akun Zoom: 24 jam, dipecah per 30 menit (48 slot per hari).
 *
 * Aturan status slot:
 *  - full      : meeting approved yang berjalan bersamaan >= kapasitas meeting akun
 *  - approved  : ada meeting approved, tapi kapasitas masih tersisa (tampil "sisa N")
 *  - pending   : tidak ada approved, tapi ada pengajuan yang menunggu
 *  - available : kosong
 * Pending TIDAK dihitung untuk status Penuh; hanya ditampilkan sebagai informasi.
 */
class CalendarService
{
    /** Panjang satu slot di kalender (menit). Jam booking juga harus kelipatan angka ini. */
    public const SLOT_MINUTES = 30;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FULL = 'full';

    public function __construct(private readonly RecurrenceExpander $expander)
    {
    }

    /** Senin pada pekan dari tanggal yang diberikan (default: pekan ini). */
    public function weekStart(?string $date = null): CarbonImmutable
    {
        try {
            $base = $date ? CarbonImmutable::createFromFormat('Y-m-d', $date) : CarbonImmutable::now();
        } catch (\Throwable) {
            $base = CarbonImmutable::now();
        }

        return ($base ?: CarbonImmutable::now())->startOfWeek(CarbonInterface::MONDAY);
    }

    /**
     * Data kalender satu pekan untuk satu akun.
     *
     * @return array{account: ZoomAccount, week_start: CarbonImmutable, week_end: CarbonImmutable, days: array<int, array<string, mixed>>}
     */
    public function week(ZoomAccount $account, CarbonImmutable $weekStart): array
    {
        $from = $weekStart->startOfDay();
        $to = $from->addDays(7);

        $approved = BookingOccurrence::query()
            ->approved()
            ->where('zoom_account_id', $account->id)
            ->overlapping($from, $to)
            ->with('booking.user.department')
            ->get()
            ->map(fn (BookingOccurrence $o) => [
                'start' => CarbonImmutable::instance($o->starts_at),
                'end' => CarbonImmutable::instance($o->ends_at),
                'label' => $this->label($o->booking),
            ])
            ->all();

        $pending = Booking::query()
            ->pending()
            ->where('zoom_account_id', $account->id)
            ->whereDate('starts_on', '<', $to->toDateString())
            ->with(['user.department', 'days'])
            ->get()
            ->flatMap(fn (Booking $b) => array_map(
                fn (array $o) => ['start' => $o['starts_at'], 'end' => $o['ends_at'], 'label' => $this->label($b)],
                $this->expander->expand($b),
            ))
            ->filter(fn (array $o) => $o['start'] < $to && $o['end'] > $from)
            ->values()
            ->all();

        $now = CarbonImmutable::now();
        $capacity = max(1, (int) $account->allowed_login_users);
        $expiresOn = $account->subscription_expires_at?->format('Y-m-d');

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $from->addDays($i);

            // Akun nonaktif atau langganan sudah habis: seluruh hari tidak bisa dipilih
            $dayBookable = $account->status === ZoomAccount::STATUS_ACTIVE
                && ($expiresOn === null || $date->format('Y-m-d') <= $expiresOn);

            $days[] = [
                'date' => $date,
                'name' => $date->locale('id')->translatedFormat('l'),
                'slots' => self::buildDay($date, $capacity, $approved, $pending, $now, $dayBookable),
            ];
        }

        return [
            'account' => $account,
            'week_start' => $from,
            'week_end' => $from->addDays(6),
            'days' => $days,
        ];
    }

    /**
     * Susun 48 slot satu hari. Murni perhitungan (tanpa database) supaya mudah diuji.
     *
     * @param  array<int, array{start: CarbonInterface, end: CarbonInterface, label: string}>  $approved
     * @param  array<int, array{start: CarbonInterface, end: CarbonInterface, label: string}>  $pending
     * @return array<int, array<string, mixed>>
     */
    public static function buildDay(
        CarbonImmutable $day,
        int $capacity,
        array $approved,
        array $pending,
        CarbonInterface $now,
        bool $dayBookable = true,
    ): array {
        $slots = [];
        $dayStart = $day->startOfDay();

        for ($minute = 0; $minute < 1440; $minute += self::SLOT_MINUTES) {
            $start = $dayStart->addMinutes($minute);
            $end = $start->addMinutes(self::SLOT_MINUTES);

            $approvedHere = self::overlapping($approved, $start, $end);
            $pendingHere = self::overlapping($pending, $start, $end);
            $used = count($approvedHere);

            $status = match (true) {
                $used >= $capacity => self::STATUS_FULL,
                $used > 0 => self::STATUS_APPROVED,
                $pendingHere !== [] => self::STATUS_PENDING,
                default => self::STATUS_AVAILABLE,
            };

            $isPast = $end <= $now;

            $slots[] = [
                'start' => $start->format('H:i'),
                // Slot terakhir berakhir tengah malam: tampilkan 24:00, bukan 00:00
                'end' => $minute + self::SLOT_MINUTES >= 1440 ? '24:00' : $end->format('H:i'),
                'status' => $status,
                'used' => $used,
                'remaining' => max(0, $capacity - $used),
                'labels' => array_values(array_unique(array_column($approvedHere, 'label'))),
                'pending_labels' => array_values(array_unique(array_column($pendingHere, 'label'))),
                'past' => $isPast,
                'bookable' => $dayBookable && ! $isPast && $status !== self::STATUS_FULL,
            ];
        }

        return $slots;
    }

    /**
     * Cek kapasitas untuk calon booking: tanggal-jam mana saja yang akan Penuh?
     * Hanya meeting APPROVED yang dihitung. Satu query untuk semua tanggal seri.
     *
     * @param  array<int, array{starts_at: CarbonInterface, ends_at: CarbonInterface}>  $intervals
     * @return array<int, array{starts_at: CarbonInterface, ends_at: CarbonInterface, peak: int}>
     */
    public function conflicts(ZoomAccount $account, array $intervals, ?int $ignoreBookingId = null): array
    {
        if ($intervals === []) {
            return [];
        }

        $min = min(array_map(fn ($i) => $i['starts_at']->getTimestamp(), $intervals));
        $max = max(array_map(fn ($i) => $i['ends_at']->getTimestamp(), $intervals));

        $existing = BookingOccurrence::query()
            ->approved()
            ->where('zoom_account_id', $account->id)
            ->when($ignoreBookingId, fn ($q) => $q->where('booking_id', '!=', $ignoreBookingId))
            ->overlapping(CarbonImmutable::createFromTimestamp($min), CarbonImmutable::createFromTimestamp($max))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (BookingOccurrence $o) => ['start' => $o->starts_at, 'end' => $o->ends_at])
            ->all();

        $capacity = max(1, (int) $account->allowed_login_users);
        $conflicts = [];

        foreach ($intervals as $interval) {
            $peak = self::peakConcurrency($existing, $interval['starts_at'], $interval['ends_at']);

            // Booking baru ikut dihitung: kalau yang sudah ada sama dengan kapasitas, tidak muat
            if ($peak + 1 > $capacity) {
                $conflicts[] = $interval + ['peak' => $peak];
            }
        }

        return $conflicts;
    }

    /**
     * Jumlah terbanyak meeting yang berjalan BERSAMAAN (pada satu titik waktu)
     * di dalam rentang $start-$end. Batas menempel (09-10 dan 10-11) tidak bersamaan.
     *
     * @param  array<int, array{start: CarbonInterface, end: CarbonInterface}>  $occurrences
     */
    public static function peakConcurrency(array $occurrences, CarbonInterface $start, CarbonInterface $end): int
    {
        $inside = array_values(array_filter(
            $occurrences,
            fn ($o) => $o['start'] < $end && $o['end'] > $start,
        ));

        $peak = 0;
        foreach ($inside as $candidate) {
            // Jumlah maksimum selalu terjadi tepat saat salah satu meeting dimulai
            $t = $candidate['start'] > $start ? $candidate['start'] : $start;

            $count = 0;
            foreach ($inside as $o) {
                if ($o['start'] <= $t && $o['end'] > $t) {
                    $count++;
                }
            }
            $peak = max($peak, $count);
        }

        return $peak;
    }

    /**
     * @param  array<int, array{start: CarbonInterface, end: CarbonInterface, label: string}>  $items
     * @return array<int, array{start: CarbonInterface, end: CarbonInterface, label: string}>
     */
    private static function overlapping(array $items, CarbonInterface $start, CarbonInterface $end): array
    {
        return array_values(array_filter($items, fn ($o) => $o['start'] < $end && $o['end'] > $start));
    }

    /** Label di kotak kalender: kode departemen, lalu nama peminjam kalau tidak ada departemen. */
    private function label(?Booking $booking): string
    {
        return $booking?->user?->department?->code
            ?? $booking?->user?->name
            ?? '?';
    }
}
