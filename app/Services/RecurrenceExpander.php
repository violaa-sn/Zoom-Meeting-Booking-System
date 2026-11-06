<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;

/**
 * Mengubah pola booking (tanggal mulai, jam, pengulangan) menjadi daftar
 * tanggal-jam nyata. Dipakai oleh kalender (untuk booking pending), pengecekan
 * Penuh, dan nanti saat Admin approve (membuat baris booking_occurrences).
 */
class RecurrenceExpander
{
    /**
     * Batas jumlah tanggal per seri (config/booking.php). Sengaja dilebihkan 1 supaya validasi
     * bisa tahu "melewati batas" (jumlah > max_occurrences) tanpa menghitung ribuan tanggal lagi.
     */
    private static function cap(): int
    {
        return (int) config('booking.max_occurrences', 3000) + 1;
    }

    /**
     * @return array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function expand(Booking $booking): array
    {
        return $this->generate(
            startsOn: CarbonImmutable::parse($booking->starts_on->format('Y-m-d')),
            startTime: (string) $booking->start_time,
            endTime: (string) $booking->end_time,
            frequency: $booking->recurrence_frequency,
            interval: (int) $booking->repeat_interval,
            endType: $booking->recurrence_end_type,
            endsOn: $booking->recurrence_ends_on
                ? CarbonImmutable::parse($booking->recurrence_ends_on->format('Y-m-d'))
                : null,
            endsAfter: $booking->recurrence_ends_after,
            weekDays: $booking->days->pluck('day_of_week')->all(),
        );
    }

    /**
     * @param  array<int, int>  $weekDays  0 = Minggu ... 6 = Sabtu (hanya untuk frequency = week)
     * @return array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function generate(
        CarbonImmutable $startsOn,
        string $startTime,
        string $endTime,
        string $frequency = Booking::RECURRENCE_NONE,
        int $interval = 1,
        ?string $endType = null,
        ?CarbonImmutable $endsOn = null,
        ?int $endsAfter = null,
        array $weekDays = [],
    ): array {
        $startsOn = $startsOn->startOfDay();
        $cap = self::cap();
        $interval = max(1, $interval);

        $dates = [];
        foreach ($this->candidateDates($startsOn, $frequency, $interval, $weekDays) as $date) {
            if ($endType === Booking::END_ON && $endsOn !== null && $date->startOfDay() > $endsOn->startOfDay()) {
                break;
            }

            $dates[] = $date;

            if (($endType === Booking::END_AFTER && $endsAfter !== null && count($dates) >= $endsAfter)
                || count($dates) >= $cap) {
                break;
            }
        }

        return array_map(fn (CarbonImmutable $date) => $this->withTimes($date, $startTime, $endTime), $dates);
    }

    /**
     * Tanggal kandidat, selalu urut naik. Pemanggil yang berhenti sendiri
     * (batas akhir / batas jumlah tanggal).
     *
     * @return Generator<int, CarbonImmutable>
     */
    private function candidateDates(CarbonImmutable $startsOn, string $frequency, int $interval, array $weekDays): Generator
    {
        // Pengaman loop: tidak pernah menghasilkan lebih dari batas jumlah tanggal (+ sedikit cadangan)
        $max = self::cap() + 10;

        switch ($frequency) {
            case Booking::RECURRENCE_DAY:
                for ($i = 0; $i < $max; $i++) {
                    yield $startsOn->addDays($i * $interval);
                }
                break;

            case Booking::RECURRENCE_WEEK:
                $days = array_values(array_unique(array_map('intval', $weekDays)));
                sort($days);
                if ($days === []) {
                    $days = [$startsOn->dayOfWeek]; // tanpa pilihan hari: pakai hari dari tanggal mulai
                }

                // Pekan dihitung Minggu-Sabtu, sesuai urutan lingkaran hari "S M T W T F S"
                $firstWeek = $startsOn->startOfWeek(CarbonInterface::SUNDAY);
                for ($w = 0; $w < $max; $w++) {
                    $weekStart = $firstWeek->addWeeks($w * $interval);
                    foreach ($days as $day) {
                        $date = $weekStart->addDays($day);
                        if ($date >= $startsOn) {
                            yield $date;
                        }
                    }
                }
                break;

            case Booking::RECURRENCE_MONTH:
                for ($i = 0; $i < $max; $i++) {
                    yield $startsOn->addMonthsNoOverflow($i * $interval);
                }
                break;

            case Booking::RECURRENCE_YEAR:
                for ($i = 0; $i < $max; $i++) {
                    yield $startsOn->addYearsNoOverflow($i * $interval);
                }
                break;

            default: // none
                yield $startsOn;
        }
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}
     */
    private function withTimes(CarbonImmutable $date, string $startTime, string $endTime): array
    {
        $start = $date->setTimeFromTimeString($startTime);
        $end = $date->setTimeFromTimeString($endTime);

        // end_time <= start_time berarti meeting melewati tengah malam
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return ['starts_at' => $start, 'ends_at' => $end];
    }
}
