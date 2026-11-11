<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 4.43: ikut centangan Transfer Stok (Owner lolos lewat Gate::before)
        return (bool) $this->user()?->can('stok.transfer');
    }

    public function rules(): array
    {
        return [
            'dari_lokasi_id'       => ['required','exists:cabangs,id'],
            'ke_lokasi_id'         => ['required','exists:cabangs,id','different:dari_lokasi_id'],
            'tanggal_kirim'        => ['required','date'],
            'stock_request_id'     => ['nullable','exists:stock_requests,id'],
            'catatan'              => ['nullable','string','max:500'],
            'items'                => ['required','array','min:1'],
            'items.*.item_id'      => ['required','exists:items,id'],
            'items.*.qty_kirim'    => ['required','numeric','min:0.001'],
            'items.*.catatan'      => ['nullable','string','max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'dari_lokasi_id'      => 'Lokasi Asal',
            'ke_lokasi_id'        => 'Lokasi Tujuan',
            'tanggal_kirim'       => 'Tanggal Kirim',
            'items.*.item_id'     => 'Item',
            'items.*.qty_kirim'   => 'Qty Kirim',
        ];
    }
}
