<?php

namespace App\Http\Requests;

/**
 * Pengajuan booking: jadwal yang sama dengan langkah rekomendasi,
 * ditambah akun Zoom yang dipilih user dari hasil rekomendasi.
 */
class StoreBookingRequest extends RecommendBookingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'zoom_account_id' => ['required', 'integer', 'exists:zoom_accounts,id'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['zoom_account_id' => 'akun Zoom'];
    }
}
