<?php

namespace App\Http\Requests;

use App\Support\CabangAktif;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tahap 2.5 D'mentai — form SIMPLE untuk Master Bahan Baku & Kemasan
 * (tanpa foto/resep/varian, beda dari ProdukJualRequest).
 */
class BahanBakuRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 4.43: ikut centangan Role & Hak Akses untuk semua role (Owner lolos lewat Gate::before)
        return (bool) $this->user()?->can($this->isMethod('post') ? 'master.bahan_baku.create' : 'master.bahan_baku.edit');
    }

    /**
     * Cabang pemilik barang (CLAUDE.md 4.39): edit = cabang barang itu;
     * tambah = cabang aktif, atau pilihan cabang di form saat "Semua Cabang".
     */
    public function cabangPemilik(): ?int
    {
        $item = $this->route('bahanBaku');
        if ($item) {
            return $item->cabang_id;
        }

        return CabangAktif::pemilikBaru($this->filled('cabang_id') ? (int) $this->input('cabang_id') : null);
    }

    protected function prepareForValidation(): void
    {
        // Kosong → null (dulu '' masuk ke kolom DECIMAL → error 500 saat harga dikosongkan)
        $harga = preg_replace('/\D/', '', (string) ($this->harga_beli_terakhir ?? ''));
        $merge = ['harga_beli_terakhir' => $harga === '' ? null : $harga];

        // Format lama stok_awal[cabang_id] → ambil nilai untuk cabang pemilik
        if (is_array($this->stok_awal)) {
            $merge['stok_awal'] = $this->stok_awal[$this->cabangPemilik()] ?? null;
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        $item = $this->route('bahanBaku');
        $cabangId = $this->cabangPemilik();

        return [
            'cabang_id'           => ['nullable', 'integer'],
            'kode_item'           => ['required', 'string', 'max:30',
                Rule::unique('items', 'kode_item')->ignore($item?->id)->where(fn ($q) => $cabangId ? $q->where(fn ($w) => $w->where('cabang_id', $cabangId)->orWhereNull('cabang_id')) : $q)],
            'nama_item'           => ['required', 'string', 'max:150'],
            'item_category_id'    => ['nullable', 'exists:item_categories,id'],
            'tipe'                => ['required', Rule::in(['bahan_baku', 'kemasan', 'tambahan_gratis'])],
            'satuan'              => ['required', 'string', 'max:20'],
            'harga_beli_terakhir' => ['nullable', 'numeric', 'min:0'],
            'qty_minimum'         => ['nullable', 'numeric', 'min:0'],
            'is_active'           => ['boolean'],
            'stok_awal'           => ['nullable', 'numeric', 'min:0'],
            // Sprint 4.34 (2026-10-08) — 3 field dipindah dari Master Barang Lengkap.
            'jenis'               => ['nullable', Rule::in(['bahan_baku', 'perlengkapan'])],
            'track_stok'          => ['nullable', 'boolean'],
            'deskripsi'           => ['nullable', 'string', 'max:1000'],
            // Sprint Unit Family (2026-09-27) — pasangan wajib atau keduanya kosong.
            'unit_beli'           => ['nullable', 'required_with:isi_per_unit_beli', 'string', 'max:20'],
            'isi_per_unit_beli'   => ['nullable', 'required_with:unit_beli', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode_item.unique'                => 'Kode Item sudah dipakai barang lain di cabang ini.',
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
            'satuan'           => 'Unit',
        ];
    }
}
