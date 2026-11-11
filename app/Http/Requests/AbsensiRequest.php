<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbsensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'karyawan_id' => ['required', 'exists:karyawans,id'],
            'tanggal'     => ['required', 'date'],
            'status'      => ['required', Rule::in(['hadir', 'izin', 'sakit', 'alpha', 'libur', 'cuti'])],
            'jam_masuk'   => ['nullable', 'date_format:H:i'],
            'jam_keluar'  => ['nullable', 'date_format:H:i'],
            'jam_lembur'  => ['nullable', 'numeric', 'min:0'],
            'keterangan'  => ['nullable', 'string', 'max:500'],
        ];
    }
}
