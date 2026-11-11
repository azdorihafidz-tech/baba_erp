<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustmentStokRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stok.adjustment') ?? false;
    }

    public function rules(): array
    {
        return [
            'item_id'          => 'required|exists:items,id',
            'lokasi_id'        => 'required|exists:cabangs,id',
            'qty_fisik'        => 'required|numeric|min:0',
            'alasan'           => 'required|in:susut,rusak,hilang,salah_hitung,audit,lainnya',
            'catatan'          => 'nullable|string|max:500',
            'mode_distribusi'  => 'required|in:fifo,manual,batch_baru,batch_existing',
            'batch_distribusi'             => 'required_if:mode_distribusi,manual|array',
            'batch_distribusi.*.batch_id'  => 'required_with:batch_distribusi|integer|exists:stock_batches,id',
            'batch_distribusi.*.qty'       => 'required_with:batch_distribusi|numeric|min:0.001',
            'batch_id_target'  => 'required_if:mode_distribusi,batch_existing|nullable|integer|exists:stock_batches,id',
            'harga_custom'     => 'nullable|numeric|min:0',
            'catat_sebagai_biaya' => 'nullable|boolean',
            // Sprint Unit Family (2026-09-27) — audit trail input asli.
            'unit_input'       => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'qty_fisik.required'        => 'Qty stok fisik wajib diisi.',
            'qty_fisik.min'             => 'Qty tidak boleh negatif.',
            'alasan.required'           => 'Pilih alasan adjustment.',
            'alasan.in'                 => 'Alasan tidak valid.',
            'mode_distribusi.required'  => 'Mode distribusi wajib dipilih.',
            'batch_id_target.required_if' => 'Pilih batch yang akan ditambah untuk mode ini.',
            'batch_distribusi.required_if' => 'Detail batch distribusi wajib diisi untuk mode manual.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Strip format titik ribuan dari harga_custom
        if ($this->filled('harga_custom')) {
            $this->merge(['harga_custom' => (float) str_replace('.', '', $this->harga_custom)]);
        }
    }
}
