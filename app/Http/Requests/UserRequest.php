<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dipakai untuk tambah dan ubah pengguna (khusus admin; route sudah dijaga can:admin). */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user'); // null saat tambah

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($user)],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nama', 'username' => 'username', 'department_id' => 'departemen', 'role' => 'peran', 'status' => 'status'];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Username ini sudah dipakai.',
            'department_id.required' => 'Departemen wajib dipilih.',
        ];
    }
}
