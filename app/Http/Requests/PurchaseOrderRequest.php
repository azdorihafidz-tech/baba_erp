<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'      => 'required|exists:suppliers,id',
            'tanggal_po'       => 'required|date',
            'pembelian_langsung' => 'boolean',
            'alasan_langsung'  => 'nullable|string',
            'catatan'          => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.item_id'  => 'required|exists:items,id',
            'items.*.qty_pesan'     => 'required|numeric|min:0.001',
            'items.*.harga_satuan'  => 'required|numeric|min:0',
            // Sprint Unit Family (2026-09-27): nullable, kalau NULL berarti mode
            // legacy (qty/harga sudah dalam unit pakai — backward compat).
            'items.*.unit_input'    => 'nullable|string|max:20',
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id'      => 'Supplier',
            'tanggal_po'       => 'Tanggal PO',
            'items'            => 'Item PO',
            'items.*.item_id'  => 'Barang',
            'items.*.qty_pesan'    => 'Qty Pesan',
            'items.*.harga_satuan' => 'Harga Satuan',
        ];
    }
}
