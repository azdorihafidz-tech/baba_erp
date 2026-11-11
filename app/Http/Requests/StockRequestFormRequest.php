<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'catatan'              => ['nullable','string','max:500'],
            'items'                => ['required','array','min:1'],
            'items.*.item_id'      => ['required','exists:items,id'],
            'items.*.qty_diminta'  => ['required','numeric','min:0.001'],
            'items.*.catatan'      => ['nullable','string','max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items'               => 'Item',
            'items.*.item_id'     => 'Item',
            'items.*.qty_diminta' => 'Qty Diminta',
        ];
    }
}
