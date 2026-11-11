<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->value, ['owner','admin_pusat','admin_gudang']);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'harga_jual'          => preg_replace('/\D/', '', $this->harga_jual ?? ''),
            'harga_beli_terakhir' => preg_replace('/\D/', '', $this->harga_beli_terakhir ?? ''),
        ]);
    }

    public function rules(): array
    {
        $itemId = $this->route('item')?->id;

        return [
            'kode_item'         => ['required','string','max:30', Rule::unique('items','kode_item')->ignore($itemId)],
            'nama_item'         => ['required','string','max:150'],
            'item_category_id'  => ['nullable','exists:item_categories,id'],
            // Tahap 2.5 D'mentai — extend enum: pisah bahan_baku/kemasan
            // (Master Bahan Baku) dari produk_jual/produk_tambahan (Master
            // Produk Jual) + tambahan_gratis. 'produk_jadi'/'lainnya' TETAP
            // diterima (enum DB masih punya nilainya, backward compat).
            'tipe'              => ['required', Rule::in(['bahan_baku','produk_jadi','kemasan','lainnya','tambahan_gratis','produk_jual','produk_tambahan'])],
            'satuan'            => ['required','string','max:20'],
            'harga_jual'        => ['nullable','numeric','min:0'],
            'harga_beli_terakhir'=> ['nullable','numeric','min:0'],
            'qty_minimum'       => ['nullable','numeric','min:0'],
            'deskripsi'         => ['nullable','string','max:500'],
            'is_active'         => ['boolean'],
            // Fase 5 — Modul Perlengkapan (Rule #66): additive, item existing
            // tidak lewat validasi ini lagi kecuali diedit ulang (default kolom
            // di migration yang menjamin data lama aman).
            'jenis'             => ['required', Rule::in(['bahan_baku','perlengkapan'])],
            'track_stok'        => ['boolean'],
            // Sprint Unit Family (2026-09-27).
            'unit_beli'         => ['nullable', 'required_with:isi_per_unit_beli', 'string', 'max:20'],
            'isi_per_unit_beli' => ['nullable', 'required_with:unit_beli', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_beli.required_with'         => 'Unit Beli wajib diisi kalau Isi per Unit Beli di-isi.',
            'isi_per_unit_beli.required_with' => 'Isi per Unit Beli wajib diisi kalau Unit Beli di-isi.',
            'isi_per_unit_beli.gt'            => 'Isi per Unit Beli harus lebih dari 0.',
        ];
    }

    public function attributes(): array
    {
        return [
            'kode_item'        => 'Kode Item',
            'nama_item'        => 'Nama Item',
            'item_category_id' => 'Kategori',
            'tipe'             => 'Tipe',
            'satuan'           => 'Satuan',
        ];
    }
}
