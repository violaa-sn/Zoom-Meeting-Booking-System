<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingOccurrence;
use App\Models\ZoomAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Kalender per akun Zoom untuk rentang tanggal bebas (maksimal config('booking.max_range_days') hari).
 * Jam yang tampil: 06:00 sampai 24:00, dipecah per 30 menit (36 slot per hari).
 *
 * Kalender hanya menampilkan status (tanpa nama peminjam).
 * Aturan status slot:
 *  - full      : meeting approved yang berjalan bersamaan >= kapasitas meeting akun
 *  - approved  : ada meeting approved, tapi kapasitas masih tersisa
 *  - pending   : tidak ada approved, tapi ada pengajuan yang menunggu
 *  - available : kosong
 * Pending TIDAK dihitung untuk status Penuh; hanya ditampilkan sebagai informasi.
 */
class CalendarService
{
    /** Panjang satu kotak di kalender (menit). Hanya untuk tampilan: jam booking boleh bebas. */
    public const SLOT_MINUTES = 30;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FULL = 'full';

    public function __construct(private readonly RecurrenceExpander $expander)
    {
    }

    /** Jam operasional (menit sejak 00:00). Nilainya dari config/booking.php. */
    public static function openMinute(): int
    {
        return (int) config('booking.open_minute', 360);
    }

    public static function closeMinute(): int
    {
        return (int) config('booking.close_minute', 1440);
    }

    /**
     * Rentang kalender dari query ?from=&to= (format Y-m-d).
     * Tanpa input: pekan ini (Senin-Minggu). Hanya "from": 7 hari sejak tanggal itu.
     * "to" sebelum "from" dianggap tidak valid dan diganti 7 hari. Dibatasi max_range_days.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool, 3: int}  [from, to, dipersingkat?, jumlah hari]
     */
    public function resolveRange(?string $from, ?string $to): array
    {
        $parse = function (?string $v): ?CarbonImmutable {
            try {
                return $v ? CarbonImmutable::createFromFormat('Y-m-d', $v)->startOfDay() : null;
            } catch (\Throwable) {
                return null;
            }
        };

        $start = $parse($from);
        $end = $parse($to);

        if (! $start) {
            $start = $this->weekStart();
            $end = $start->addDays(6);
        } elseif (! $end || $end < $start) {
            $end = $start->addDays(6);
        }

        $max = (int) config('booking.max_range_days', 14);
        $days = (int) $start->diffInDays($end) + 1;
        $clamped = $days > $max;
        if ($clamped) {
            $end = $start->addDays($max - 1);
            $days = $max;
        }

        return [$start, $end, $clamped, $days];
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
     * Data kalender untuk satu akun pada rentang $dayCount hari sejak $start.
     *
     * @param  array<int, int>  $onlyDays  filter hari (0 = Minggu ... 6 = Sabtu). Kosong = tampilkan semua hari.
     * @return array{account: ZoomAccount, from: CarbonImmutable, to: CarbonImmutable, days: array<int, array<string, mixed>>}
     */
    public function range(ZoomAccount $account, CarbonImmutable $start, int $dayCount, array $onlyDays = []): array
    {
        $from = $start->startOfDay();
        $to = $from->addDays($dayCount); // batas atas (tidak termasuk)

        // Kalender hanya menampilkan status, tanpa nama peminjam, jadi tidak perlu memuat relasi user/departemen
        $approved = BookingOccurrence::query()
            ->approved()
            ->where('zoom_account_id', $account->id)
            ->overlapping($from, $to)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (BookingOccurrence $o) => [
                'start' => CarbonImmutable::instance($o->starts_at),
                'end' => CarbonImmutable::instance($o->ends_at),
            ])
            ->all();

        // Pengajuan pending belum punya akun (admin yang memilih saat menyetujui), jadi ditampilkan
        // "Menunggu" di semua akun bertipe sama dengan yang diminta user.
        $pending = Booking::query()
            ->pending()
            ->where(fn ($q) => $q
                ->where('zoom_account_id', $account->id)
                ->orWhere(fn ($w) => $w->whereNull('zoom_account_id')->where('account_type', $account->account_type)))
            ->whereDate('starts_on', '<', $to->toDateString())
            ->with('days')
            ->get()
            ->flatMap(fn (Booking $b) => array_map(
                fn (array $o) => ['start' => $o['starts_at'], 'end' => $o['ends_at']],
                $this->expander->expand($b),
            ))
            ->filter(fn (array $o) => $o['start'] < $to && $o['end'] > $from)
            ->values()
            ->all();

        $now = CarbonImmutable::now();
        $capacity = max(1, (int) $account->allowed_login_users);
        $expiresOn = $account->subscription_expires_at?->format('Y-m-d');

        $days = [];
        for ($i = 0; $i < $dayCount; $i++) {
            $date = $from->addDays($i);

            // Filter hari tertentu (lingkaran S M T W ... di kalender)
            if ($onlyDays !== [] && ! in_array($date->dayOfWeek, $onlyDays, true)) {
                continue;
            }

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
            'from' => $from,
            'to' => $from->addDays($dayCount - 1),
            'days' => $days,
        ];
    }

    /**
     * Susun slot satu hari (per 30 menit). Murni perhitungan (tanpa database) supaya mudah diuji.
     *
     * Jam booking boleh bebas (mis. 09:12 sampai 10:24); kotak kalender tetap 30 menit.
     * Sebuah kotak dihitung terisi kalau ada meeting yang menyentuh kotak itu, walau hanya sebagian.
     * Status Penuh memakai jumlah meeting yang berjalan BERSAMAAN (bukan jumlah meeting) di dalam kotak,
     * jadi dua meeting pendek yang berurutan di akun berkapasitas 1 tidak dihitung dua kali.
     *
     * @param  array<int, array{start: CarbonInterface, end: CarbonInterface}>  $approved
     * @param  array<int, array{start: CarbonInterface, end: CarbonInterface}>  $pending
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

        for ($minute = self::openMinute(); $minute < self::closeMinute(); $minute += self::SLOT_MINUTES) {
            $start = $dayStart->addMinutes($minute);
            $end = $start->addMinutes(self::SLOT_MINUTES);

            $approvedHere = self::overlapping($approved, $start, $end);
            $hasPending = self::overlapping($pending, $start, $end) !== [];
            $used = self::peakConcurrency($approvedHere, $start, $end);

            $status = match (true) {
                $used >= $capacity => self::STATUS_FULL,
                $used > 0 => self::STATUS_APPROVED,
                $hasPending => self::STATUS_PENDING,
                default => self::STATUS_AVAILABLE,
            };

            $isPast = $end <= $now;

            $slots[] = [
                'start' => $start->format('H:i'),
                // Slot terakhir berakhir tengah malam: tampilkan 24:00, bukan 00:00
                'end' => $minute + self::SLOT_MINUTES >= 1440 ? '24:00' : $end->format('H:i'),
                'status' => $status,
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
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at'])
            ->map(fn (BookingOccurrence $o) => ['start' => $o->starts_at, 'end' => $o->ends_at])
            ->all();

        $capacity = max(1, (int) $account->allowed_login_users);
        $conflicts = [];

        // Urutkan jadwal calon booking, lalu geser "jendela" meeting yang relevan.
        // Dengan begini seri panjang (ribuan tanggal) tidak membandingkan semua pasangan satu per satu.
        usort($intervals, fn ($a, $b) => $a['starts_at'] <=> $b['starts_at']);
        $window = [];
        $next = 0;
        $total = count($existing);

        foreach ($intervals as $interval) {
            // Masukkan meeting yang mulai sebelum interval ini berakhir
            while ($next < $total && $existing[$next]['start'] < $interval['ends_at']) {
                $window[] = $existing[$next++];
            }
            // Buang yang sudah selesai sebelum interval ini mulai (interval berikutnya makin akhir)
            $window = array_values(array_filter($window, fn ($o) => $o['end'] > $interval['starts_at']));

            $peak = self::peakConcurrency($window, $interval['starts_at'], $interval['ends_at']);

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
     * @param  array<int, array{start: CarbonInterface, end: CarbonInterface}>  $items
     * @return array<int, array{start: CarbonInterface, end: CarbonInterface}>
     */
    private static function overlapping(array $items, CarbonInterface $start, CarbonInterface $end): array
    {
        return array_values(array_filter($items, fn ($o) => $o['start'] < $end && $o['end'] > $start));
    }
}
