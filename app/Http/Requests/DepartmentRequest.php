<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** Kode selalu huruf besar tanpa spasi di pinggir, supaya "mia" dan "MIA" dianggap sama. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'code' => is_string($this->code) ? strtoupper(trim($this->code)) : $this->code,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $department = $this->route('department'); // null saat tambah

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department)],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('departments', 'code')->ignore($department)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nama departemen', 'code' => 'kode'];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama departemen ini sudah ada.',
            'code.unique' => 'Kode ini sudah dipakai departemen lain.',
            'code.regex' => 'Kode hanya boleh huruf dan angka, tanpa spasi.',
        ];
    }
}
