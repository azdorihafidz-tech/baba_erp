<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PenggajianGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cabang_id'    => ['required', 'exists:cabangs,id'],
            'periode'      => ['required', 'date_format:Y-m'],
            'tunjangan'    => ['nullable', 'numeric', 'min:0'],
            'bonus'        => ['nullable', 'numeric', 'min:0'],
            'potongan_lain' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cabang_id' => 'cabang',
            'periode'   => 'periode',
        ];
    }
}
