<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\ZoomAccount;
use App\Services\BookingService;
use App\Services\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi jadwal booking. Dipakai untuk langkah "cek akun tersedia" dan untuk mengirim pengajuan
 * (StoreBookingRequest). User tidak memilih akun Zoom; admin yang memilih saat menyetujui.
 */
class RecommendBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isActive();
    }

    /**
     * Form punya pilihan "Pengulangan": Tidak berulang / Setiap hari / Setiap minggu / Setiap bulan / Kustom.
     * Di sini pilihan itu diterjemahkan ke recurrence_frequency + repeat_interval yang dipakai sistem:
     *  - hari / minggu / bulan : setiap 1 (minggu: hari yang sama dengan tanggal mulai)
     *  - kustom                : angka dan satuan (hari/minggu/bulan/tahun) dari form, plus hari tertentu kalau mingguan
     */
    protected function prepareForValidation(): void
    {
        $preset = $this->input('recurrence_preset', 'none');

        $this->merge(match ($preset) {
            'day', 'week', 'month' => [
                'recurrence_frequency' => $preset,
                'repeat_interval' => 1,
                'days' => [],
            ],
            'custom' => [
                'recurrence_frequency' => $this->input('recurrence_unit'),
                'days' => $this->input('recurrence_unit') === Booking::RECURRENCE_WEEK ? (array) $this->input('days', []) : [],
            ],
            default => [
                'recurrence_frequency' => Booking::RECURRENCE_NONE,
                'repeat_interval' => 1,
                'recurrence_end_type' => null,
                'recurrence_ends_on' => null,
                'recurrence_ends_after' => null,
                'days' => [],
            ],
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isRecurring = fn() => $this->input('recurrence_frequency', Booking::RECURRENCE_NONE) !== Booking::RECURRENCE_NONE;
        // Pilihan hari (lingkaran S M T W ...) hanya ada di Kustom dengan satuan minggu
        $isWeekly = fn() => $this->input('recurrence_preset') === 'custom'
            && $this->input('recurrence_frequency') === Booking::RECURRENCE_WEEK;

        return [
            'purpose' => ['required', Rule::in(Booking::PURPOSES)],
            'purpose_other' => ['nullable', 'required_if:purpose,lainnya', 'string', 'max:255'],
            // Wajib dipilih (100 / 300 / 500 / lebih dari 500). Akun dengan kapasitas peserta di bawahnya tidak direkomendasikan
            'participant_count' => ['required', Rule::in(array_keys(Booking::PARTICIPANT_OPTIONS))],
            // Tipe akun yang dicari user. Sistem hanya merekomendasikan akun bertipe ini.
            'account_type' => ['required', Rule::in([ZoomAccount::TYPE_PRO, ZoomAccount::TYPE_BUSINESS])],

            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            // Jam bebas, asal kelipatan 30 menit supaya pas dengan slot kalender
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],

            'recurrence_preset' => ['required', Rule::in(['none', 'day', 'week', 'month', 'custom'])],
            'recurrence_unit' => [Rule::requiredIf(fn () => $this->input('recurrence_preset') === 'custom'), 'nullable', Rule::in([
                Booking::RECURRENCE_DAY,
                Booking::RECURRENCE_WEEK,
                Booking::RECURRENCE_MONTH,
                Booking::RECURRENCE_YEAR,
            ])],
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
            'recurrence_ends_after' => ['nullable', 'required_if:recurrence_end_type,after', 'integer', 'min:1', 'max:' . config('booking.max_occurrences', 3000)],

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
            'account_type' => 'tipe akun',
            'starts_on' => 'tanggal',
            'start_time' => 'jam mulai',
            'end_time' => 'jam selesai',
            'recurrence_preset' => 'pengulangan',
            'recurrence_unit' => 'satuan pengulangan',
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

            // Jam operasional (config/booking.php). Kunci error "operating_hours" memicu popup di JS.
            $toMinutes = fn(string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
            $start = $toMinutes($data['start_time']);
            $end = $data['end_time'] === '00:00' ? 1440 : $toMinutes($data['end_time']); // 00:00 = tengah malam

            if ($start < CalendarService::openMinute() || $end > CalendarService::closeMinute()) {
                $validator->errors()->add('operating_hours', config('booking.out_of_hours_message'));

                return;
            }

            // Meeting tidak melewati tengah malam (jam booking hanya sampai 24:00)
            if ($end <= $start) {
                $validator->errors()->add('end_time', 'Jam selesai harus setelah jam mulai.');

                return;
            }

            // Jam mulai tidak boleh sudah lewat (berlaku untuk kejadian pertama)
            if ($intervals !== [] && $intervals[0]['starts_at'] < CarbonImmutable::now()) {
                $validator->errors()->add('start_time', 'Jam mulai sudah lewat.');

                return;
            }

            // Tidak ada batas tahun. Hanya pengaman: jumlah tanggal tidak boleh melebihi max_occurrences
            if (($data['recurrence_frequency'] ?? 'none') !== Booking::RECURRENCE_NONE
                && count($intervals) > (int) config('booking.max_occurrences', 3000)
            ) {
                $validator->errors()->add(
                    'recurrence_end_type',
                    'Terlalu banyak tanggal (maksimal ' . config('booking.max_occurrences', 3000) . '). Perpendek masa pengulangannya.'
                );
            }
        }];
    }
}
