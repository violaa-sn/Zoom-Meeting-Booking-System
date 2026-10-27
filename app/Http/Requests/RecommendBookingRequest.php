<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi jadwal booking (tanpa akun). Dipakai untuk langkah "cari rekomendasi akun".
 * StoreBookingRequest menambahkan zoom_account_id di atasnya.
 */
class RecommendBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isActive();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isRecurring = fn () => $this->input('recurrence_frequency', Booking::RECURRENCE_NONE) !== Booking::RECURRENCE_NONE;
        $isWeekly = fn () => $this->input('recurrence_frequency') === Booking::RECURRENCE_WEEK;

        return [
            'purpose' => ['required', Rule::in(Booking::PURPOSES)],
            'purpose_other' => ['nullable', 'required_if:purpose,lainnya', 'string', 'max:255'],
            // Hanya informasi: wajib diisi, tanpa batas atas
            'participant_count' => ['required', 'integer', 'min:1'],

            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            // Jam bebas, asal kelipatan 30 menit supaya pas dengan slot kalender
            'start_time' => ['required', 'date_format:H:i', $this->slotRule()],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time', $this->slotRule()],

            'recurrence_frequency' => ['required', Rule::in([
                Booking::RECURRENCE_NONE,
                Booking::RECURRENCE_DAY,
                Booking::RECURRENCE_WEEK,
                Booking::RECURRENCE_MONTH,
                Booking::RECURRENCE_YEAR,
            ])],
            'repeat_interval' => [Rule::requiredIf($isRecurring), 'nullable', 'integer', 'min:1', 'max:99'],
            'recurrence_end_type' => [Rule::requiredIf($isRecurring), 'nullable', Rule::in([Booking::END_ON, Booking::END_AFTER])],
            'recurrence_ends_on' => ['nullable', 'required_if:recurrence_end_type,on', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'recurrence_ends_after' => ['nullable', 'required_if:recurrence_end_type,after', 'integer', 'min:1', 'max:366'],

            'days' => [Rule::requiredIf($isWeekly), 'nullable', 'array'],
            'days.*' => ['integer', 'between:0,6', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purpose' => 'keperluan',
            'purpose_other' => 'keterangan keperluan',
            'participant_count' => 'jumlah peserta',
            'starts_on' => 'tanggal',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
            'recurrence_frequency' => 'pengulangan',
            'repeat_interval' => 'interval pengulangan',
            'recurrence_end_type' => 'batas akhir',
            'recurrence_ends_on' => 'tanggal berakhir',
            'recurrence_ends_after' => 'jumlah kejadian',
            'days' => 'hari pengulangan',
        ];
    }

    /**
     * Pengecekan yang butuh banyak field sekaligus.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->validated();
            $intervals = app(BookingService::class)->intervalsFrom($data);

            // Jam mulai tidak boleh sudah lewat (berlaku untuk kejadian pertama)
            if ($intervals !== [] && $intervals[0]['starts_at'] < CarbonImmutable::now()) {
                $validator->errors()->add('start_time', 'Jam mulai sudah lewat.');

                return;
            }

            // Seri maksimal 1 tahun sejak tanggal mulai
            if (($data['recurrence_frequency'] ?? 'none') !== Booking::RECURRENCE_NONE) {
                $limit = CarbonImmutable::createFromFormat('Y-m-d', $data['starts_on'])->addYear()->format('Y-m-d');

                if (($data['recurrence_end_type'] ?? null) === Booking::END_ON
                    && ($data['recurrence_ends_on'] ?? '9999-12-31') > $limit) {
                    $validator->errors()->add('recurrence_ends_on', 'Pengulangan maksimal 1 tahun sejak tanggal mulai.');
                } elseif (($data['recurrence_end_type'] ?? null) === Booking::END_AFTER
                    && count($intervals) < (int) $data['recurrence_ends_after']) {
                    // Generator berhenti di batas 1 tahun, jadi jumlahnya kurang dari yang diminta
                    $validator->errors()->add('recurrence_ends_after', 'Jumlah kejadian melewati batas 1 tahun sejak tanggal mulai.');
                }
            }
        }];
    }

    private function slotRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $minutes = (int) substr((string) $value, 3, 2);

            if ($minutes % CalendarService::SLOT_MINUTES !== 0) {
                $fail('Jam harus kelipatan '.CalendarService::SLOT_MINUTES.' menit (contoh 09:00 atau 09:30).');
            }
        };
    }
}
