<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvaluationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_periode'       => ['required', 'string', 'max:100'],
            'cabang_id'          => ['required', 'exists:cabangs,id'],
            'tanggal_mulai'      => ['required', 'date'],
            'tanggal_selesai'    => ['required', 'date', 'after:tanggal_mulai'],
            'deadline_pengisian' => ['required', 'date', 'before:tanggal_selesai'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_periode'       => 'nama periode',
            'cabang_id'          => 'cabang',
            'tanggal_mulai'      => 'tanggal mulai',
            'tanggal_selesai'    => 'tanggal selesai',
            'deadline_pengisian' => 'deadline pengisian',
        ];
    }
}
