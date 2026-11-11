<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PemakaianPerlengkapanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pemakaian_perlengkapan.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'item_id'           => ['required', 'exists:items,id'],
            'cabang_id'         => ['required', 'exists:cabangs,id'],
            'qty'               => ['required', 'numeric', 'min:0.001'],
            'tanggal_pemakaian' => ['required', 'date'],
            'keterangan'        => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'item_id'           => 'Item',
            'cabang_id'         => 'Cabang',
            'qty'               => 'Qty',
            'tanggal_pemakaian' => 'Tanggal Pemakaian',
        ];
    }
}
