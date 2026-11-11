<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KaryawanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gaji_pokok' => preg_replace('/\D/', '', $this->gaji_pokok ?? '0'),
        ]);
    }

    public function rules(): array
    {
        $karyawanId = $this->route('karyawan')?->id;

        return [
            'cabang_id'      => ['required', 'exists:cabangs,id'],
            'nik'            => ['nullable', 'string', 'max:50',
                Rule::unique('karyawans', 'nik')->ignore($karyawanId)
            ],
            'nama_lengkap'   => ['required', 'string', 'max:255'],
            'jenis_kelamin'  => ['required', Rule::in(['L', 'P'])],
            'tanggal_lahir'  => ['nullable', 'date'],
            'alamat'         => ['nullable', 'string'],
            'telepon'        => ['nullable', 'string', 'max:20'],
            'jabatan'        => ['required', 'string', 'max:100'],
            'tipe_karyawan'  => ['required', Rule::in(['tetap', 'kontrak', 'harian'])],
            'tanggal_masuk'  => ['required', 'date'],
            'tanggal_keluar' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],
            'gaji_pokok'     => ['required', 'numeric', 'min:0'],
            'no_rekening'    => ['nullable', 'string', 'max:50'],
            'nama_bank'      => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', Rule::in(['aktif', 'tidak_aktif', 'keluar'])],
            'shift_id'       => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'atasan_id'      => ['nullable', 'exists:karyawans,id'],
            'foto'           => ['nullable', 'image', 'max:2048'],
            'catatan'        => ['nullable', 'string'],
            'user_id'        => ['nullable', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cabang_id'     => 'cabang',
            'nama_lengkap'  => 'nama lengkap',
            'jenis_kelamin' => 'jenis kelamin',
            'tanggal_lahir' => 'tanggal lahir',
            'tipe_karyawan' => 'tipe karyawan',
            'tanggal_masuk' => 'tanggal masuk',
            'gaji_pokok'    => 'gaji pokok',
        ];
    }
}
