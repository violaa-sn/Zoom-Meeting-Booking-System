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
     * @param  string|null  $accountType  pro|business, null = semua tipe
     * @param  string|null  $participantCount  pilihan jumlah peserta; akun dengan participant_capacity di bawahnya tidak dimasukkan
     * @return Collection<int, array{account: ZoomAccount, available: bool, reason: ?string, conflicts: array}>
     */
    public function recommend(array $intervals, ?string $accountType = null, ?string $participantCount = null): Collection
    {
        $lastDay = collect($intervals)->max(fn ($i) => $i['ends_at']->format('Y-m-d'));

        return ZoomAccount::active()
            // Hanya akun bertipe yang dipilih user (pro / business)
            ->when($accountType, fn ($q) => $q->where('account_type', $accountType))
            // Kapasitas peserta akun harus sama dengan atau lebih besar dari jumlah peserta yang dipilih
            ->when($participantCount, fn ($q) => $q->where('participant_capacity', '>=', Booking::minCapacityFor($participantCount)))
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
     * Simpan pengajuan (status pending). User TIDAK memilih akun Zoom: akun ditentukan admin saat menyetujui.
     * Di sini hanya dipastikan masih ada minimal satu akun (bertipe yang diminta) yang kosong untuk
     * semua tanggal, karena kalender bisa berubah antara saat user melihat rekomendasi dan saat klik Ajukan.
     * Pengecekan final dan pemilihan akun dilakukan saat admin approve.
     *
     * @param  array<string, mixed>  $data  hasil validasi StoreBookingRequest
     *
     * @throws ValidationException
     */
    public function submit(User $user, array $data): Booking
    {
        $intervals = $this->intervalsFrom($data);

        if (! $this->recommend($intervals, $data['account_type'], $data['participant_count'])->contains('available', true)) {
            throw ValidationException::withMessages([
                'account_type' => 'Tidak ada akun '.ucfirst($data['account_type']).' yang kosong pada jadwal ini untuk '.(Booking::PARTICIPANT_OPTIONS[$data['participant_count']] ?? $data['participant_count']).' peserta. Ubah jadwal, tipe akun, atau jumlah peserta.',
            ]);
        }

        $isRecurring = ($data['recurrence_frequency'] ?? Booking::RECURRENCE_NONE) !== Booking::RECURRENCE_NONE;

        return DB::transaction(function () use ($user, $data, $isRecurring) {
            $booking = Booking::create([
                'user_id' => $user->id,
                'zoom_account_id' => null, // dipilih admin saat menyetujui
                'account_type' => $data['account_type'],
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
