<?php

namespace App\Http\Requests;

use App\Models\ZoomAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZoomAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_default' => $this->boolean('is_default'),
            // Spasi di pinggir dibuang dan email dijadikan huruf kecil, supaya "A@x.com" dan "a@x.com" dianggap sama
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $account = $this->route('zoom_account'); // null saat tambah

        return [
            // unique juga memeriksa akun yang sudah di-soft delete, karena indeks database tetap berlaku
            'name' => ['required', 'string', 'max:255', Rule::unique('zoom_accounts', 'name')->ignore($account)],
            // unique juga memeriksa akun yang sudah di-soft delete, karena indeks database tetap berlaku
            'email' => ['required', 'email', 'max:255', Rule::unique('zoom_accounts', 'email')->ignore($account)],
            'account_type' => ['required', Rule::in([ZoomAccount::TYPE_PRO, ZoomAccount::TYPE_BUSINESS])],
            'allowed_login_users' => ['required', 'integer', 'min:1', 'max:50'],
            'participant_capacity' => ['required', 'integer', 'min:1'],
            'subscription_expires_at' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in([ZoomAccount::STATUS_ACTIVE, ZoomAccount::STATUS_INACTIVE])],
            'is_default' => ['boolean'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama akun', 'email' => 'email', 'account_type' => 'tipe akun',
            'allowed_login_users' => 'kapasitas meeting', 'participant_capacity' => 'maks. peserta',
            'subscription_expires_at' => 'tanggal langganan', 'color' => 'warna',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama akun ini sudah dipakai (termasuk akun yang pernah dihapus).',
            'email.unique' => 'Email ini sudah terdaftar (termasuk akun yang pernah dihapus).',
        ];
    }
}
