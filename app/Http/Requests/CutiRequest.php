<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'karyawan_id'     => ['required', 'exists:karyawans,id'],
            'tipe'            => ['required', Rule::in(['cuti_tahunan', 'izin', 'sakit', 'cuti_khusus'])],
            'tanggal_mulai'   => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan'          => ['required', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'karyawan_id'     => 'karyawan',
            'tanggal_mulai'   => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai',
        ];
    }
}
