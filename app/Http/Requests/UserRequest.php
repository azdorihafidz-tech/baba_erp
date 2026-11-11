<?php

namespace App\Http\Requests;

use App\Enums\RoleUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 4.43: hak tambah/ubah user diatur centangan user.create/user.edit
        // (route middleware); batas cabang & akun Owner dijaga UserController.
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        // 4.43: role tanpa akses semua cabang hanya boleh menugaskan ke cabangnya sendiri
        $cabangRule = ['exists:cabangs,id'];
        if ($this->user() && ! $this->user()->canAccessAllBranches()) {
            $cabangRule[] = Rule::in($this->user()->cabangs()->pluck('cabangs.id')->all());
        }

        return [
            'name'        => ['required', 'string', 'max:100'],
            'email'       => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'telepon'     => ['nullable', 'string', 'max:20'],
            // 4.43: role Owner hanya boleh diberikan oleh Owner
            'role'        => ['required', Rule::in(\App\Support\Roles::values($this->user()?->role === RoleUser::Owner))],
            'is_active'   => ['sometimes', 'boolean'],
            'cabang_ids'  => ['nullable', 'array'],
            'cabang_ids.*'=> $cabangRule,
            'default_cabang_id' => ['nullable', ...$cabangRule],
            'password'    => $isUpdate
                ? ['nullable', 'confirmed', Password::min(8)]
                : ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Nama lengkap wajib diisi.',
            'email.required'    => 'Email wajib diisi.',
            'email.unique'      => 'Email sudah digunakan oleh user lain.',
            'role.required'     => 'Role wajib dipilih.',
            'password.required' => 'Password wajib diisi untuk user baru.',
            'password.confirmed'=> 'Konfirmasi password tidak cocok.',
            'password.min'      => 'Password minimal 8 karakter.',
            'cabang_ids.*.exists' => 'Salah satu cabang yang dipilih tidak valid.',
            'cabang_ids.*.in'     => 'Anda hanya bisa menugaskan user ke cabang Anda sendiri.',
            'default_cabang_id.in' => 'Anda hanya bisa menugaskan user ke cabang Anda sendiri.',
            'role.in'           => 'Role yang dipilih tidak boleh Anda berikan.',
        ];
    }
}
