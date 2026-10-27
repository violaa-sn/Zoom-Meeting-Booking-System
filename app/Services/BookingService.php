<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\User;
use App\Models\ZoomAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private readonly RecurrenceExpander $expander,
        private readonly CalendarService $calendar,
    ) {
    }

    /**
     * Tanggal-jam nyata dari data form (belum disimpan ke database).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function intervalsFrom(array $data): array
    {
        $frequency = $data['recurrence_frequency'] ?? Booking::RECURRENCE_NONE;

        return $this->expander->generate(
            startsOn: CarbonImmutable::createFromFormat('Y-m-d', $data['starts_on'])->startOfDay(),
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            frequency: $frequency,
            interval: (int) ($data['repeat_interval'] ?? 1),
            endType: $frequency === Booking::RECURRENCE_NONE ? null : ($data['recurrence_end_type'] ?? null),
            endsOn: ! empty($data['recurrence_ends_on'])
                ? CarbonImmutable::createFromFormat('Y-m-d', $data['recurrence_ends_on'])
                : null,
            endsAfter: isset($data['recurrence_ends_after']) ? (int) $data['recurrence_ends_after'] : null,
            weekDays: $frequency === Booking::RECURRENCE_WEEK ? ($data['days'] ?? []) : [],
        );
    }

    /**
     * Rekomendasi akun untuk jadwal yang diminta. Akun utama dicek lebih dulu,
     * lalu akun lain berdasarkan urutan. Akun yang tidak cocok tetap dikembalikan
     * beserta alasannya, supaya UI bisa menjelaskan kenapa akun itu tidak dipilih.
     *
     * @param  array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $intervals
     * @return Collection<int, array{account: ZoomAccount, available: bool, reason: ?string, conflicts: array}>
     */
    public function recommend(array $intervals): Collection
    {
        $lastDay = collect($intervals)->max(fn ($i) => $i['ends_at']->format('Y-m-d'));

        return ZoomAccount::active()
            ->ordered()
            ->get()
            ->sortBy([['is_default', 'desc'], ['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (ZoomAccount $account) use ($intervals, $lastDay) {
                if ($lastDay && $account->subscription_expires_at
                    && $account->subscription_expires_at->format('Y-m-d') < $lastDay) {
                    return [
                        'account' => $account,
                        'available' => false,
                        'reason' => 'Langganan akun berakhir '.$account->subscription_expires_at->format('d-m-Y').', sebelum seri booking selesai.',
                        'conflicts' => [],
                    ];
                }

                $conflicts = $this->calendar->conflicts($account, $intervals);

                return [
                    'account' => $account,
                    'available' => $conflicts === [],
                    'reason' => $conflicts === [] ? null : 'Penuh pada '.count($conflicts).' tanggal.',
                    'conflicts' => $conflicts,
                ];
            });
    }

    /**
     * Simpan pengajuan (status pending). Status Penuh dicek ulang di sini karena
     * kalender bisa berubah antara saat user melihat rekomendasi dan saat klik Ajukan.
     * Pengecekan final tetap dilakukan saat Admin approve.
     *
     * @param  array<string, mixed>  $data  hasil validasi StoreBookingRequest
     *
     * @throws ValidationException
     */
    public function submit(User $user, array $data): Booking
    {
        $account = ZoomAccount::active()->find($data['zoom_account_id']);
        if (! $account) {
            throw ValidationException::withMessages(['zoom_account_id' => 'Akun Zoom tidak ditemukan atau tidak aktif.']);
        }

        $intervals = $this->intervalsFrom($data);
        $lastDay = collect($intervals)->max(fn ($i) => $i['ends_at']->format('Y-m-d'));

        if ($account->subscription_expires_at && $lastDay
            && $account->subscription_expires_at->format('Y-m-d') < $lastDay) {
            throw ValidationException::withMessages([
                'zoom_account_id' => 'Langganan akun ini berakhir sebelum seri booking selesai.',
            ]);
        }

        $conflicts = $this->calendar->conflicts($account, $intervals);
        if ($conflicts !== []) {
            $dates = collect($conflicts)->take(3)
                ->map(fn ($c) => $c['starts_at']->format('d-m-Y'))
                ->implode(', ');

            throw ValidationException::withMessages([
                'zoom_account_id' => "Akun ini sudah penuh pada {$dates}. Pilih akun lain atau ubah jadwal.",
            ]);
        }

        $isRecurring = ($data['recurrence_frequency'] ?? Booking::RECURRENCE_NONE) !== Booking::RECURRENCE_NONE;

        return DB::transaction(function () use ($user, $account, $data, $isRecurring) {
            $booking = Booking::create([
                'user_id' => $user->id,
                'zoom_account_id' => $account->id,
                'purpose' => $data['purpose'],
                'purpose_other' => $data['purpose'] === 'lainnya' ? ($data['purpose_other'] ?? null) : null,
                'participant_count' => $data['participant_count'],
                'starts_on' => $data['starts_on'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'recurrence_frequency' => $data['recurrence_frequency'] ?? Booking::RECURRENCE_NONE,
                'repeat_interval' => $isRecurring ? (int) ($data['repeat_interval'] ?? 1) : 1,
                'recurrence_end_type' => $isRecurring ? ($data['recurrence_end_type'] ?? null) : null,
                'recurrence_ends_on' => $isRecurring && ($data['recurrence_end_type'] ?? null) === Booking::END_ON
                    ? $data['recurrence_ends_on'] : null,
                'recurrence_ends_after' => $isRecurring && ($data['recurrence_end_type'] ?? null) === Booking::END_AFTER
                    ? (int) $data['recurrence_ends_after'] : null,
                'status' => Booking::STATUS_PENDING,
            ]);

            if (($data['recurrence_frequency'] ?? null) === Booking::RECURRENCE_WEEK) {
                foreach (array_unique(array_map('intval', $data['days'] ?? [])) as $day) {
                    BookingDay::create(['booking_id' => $booking->id, 'day_of_week' => $day]);
                }
            }

            return $booking;
        });
    }
}
