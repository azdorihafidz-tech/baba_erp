<?php

namespace App\Http\Requests;

use App\Support\CabangAktif;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tahap 2.5 D'mentai — form LENGKAP Master Produk Jual: info dasar + foto +
 * resep (komposisi bahan) + varian + ketersediaan per outlet, semua dalam
 * 1 submit (sesuai spesifikasi Owner).
 */
class ProdukJualRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 4.43: ikut centangan Role & Hak Akses untuk semua role (Owner lolos lewat Gate::before)
        return (bool) $this->user()?->can($this->isMethod('post') ? 'master.produk_jual.create' : 'master.produk_jual.edit');
    }

    /** Cabang pemilik produk (CLAUDE.md 4.39): edit = cabang produk; tambah = cabang aktif / pilihan form. */
    public function cabangPemilik(): ?int
    {
        $item = $this->route('produkJual');
        if ($item) {
            return $item->cabang_id;
        }

        return CabangAktif::pemilikBaru($this->filled('cabang_id') ? (int) $this->input('cabang_id') : null);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'harga_jual' => preg_replace('/\D/', '', $this->harga_jual ?? ''),
        ]);
    }

    public function rules(): array
    {
        $itemId = $this->route('produkJual')?->id;
        $cabangId = $this->cabangPemilik();

        return [
            'cabang_id'        => ['nullable', 'integer'],
            // Kode unik per cabang (4.39)
            'kode_item'        => ['required', 'string', 'max:30', Rule::unique('items', 'kode_item')->ignore($itemId)
                ->where(fn ($q) => $cabangId ? $q->where(fn ($w) => $w->where('cabang_id', $cabangId)->orWhereNull('cabang_id')) : $q)],
            'nama_item'        => ['required', 'string', 'max:150'],
            'item_category_id' => ['nullable', 'exists:item_categories,id'],
            'tipe'             => ['required', Rule::in(['produk_jual', 'produk_tambahan'])],
            'satuan'           => ['required', 'string', 'max:20'],
            'harga_jual'       => ['required', 'numeric', 'min:0'],
            'deskripsi'        => ['nullable', 'string', 'max:500'],
            'is_active'        => ['boolean'],
            'foto'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'hapus_foto'       => ['boolean'],

            // Komposisi resep (opsional — tidak semua produk_jual punya resep).
            // Tahap 7 D'mentai (Fitur Import Bumbu Pusat, 2026-09-17) — 1 baris
            // resep sekarang item_id LANGSUNG *atau* resep_bumbu_ref_id (link
            // ke Master Bumbu Pusat), mutually exclusive -- divalidasi manual
            // di withValidator() di bawah (Laravel tidak punya "exactly one of"
            // built-in utk array wildcard antar-field).
            'resep'                     => ['nullable', 'array'],
            'resep.*.item_id'           => ['nullable', 'exists:items,id'],
            'resep.*.resep_bumbu_ref_id'=> ['nullable', 'exists:resep_bumbu,id'],
            'resep.*.qty_per_unit'      => ['required_with:resep', 'numeric', 'min:0.001'],
            'resep.*.satuan'            => ['nullable', 'string', 'max:20'],
            'resep.*.is_wajib'          => ['nullable', 'boolean'],
            'resep.*.mode_harga'        => ['nullable', Rule::in(['gratis', 'pakai_master'])],

            // Varian (opsional)
            'punya_varian'          => ['boolean'],
            'stok_per_varian'       => ['boolean'],
            'atribut'               => ['nullable', 'array'],
            'atribut.*.nama'        => ['required_with:atribut', 'string', 'max:50'],
            'atribut.*.nilai'       => ['required_with:atribut', 'string', 'max:255'], // comma-separated
            'harga_override'        => ['nullable', 'array'],
            'harga_override.*'      => ['nullable', 'numeric', 'min:0'],

        ];
    }

    /**
     * Validasi "exactly one of item_id/resep_bumbu_ref_id per baris resep" —
     * tidak bisa diekspresikan lewat rule array wildcard biasa.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('resep', []) as $i => $row) {
                $adaItem  = !empty($row['item_id']);
                $adaBumbu = !empty($row['resep_bumbu_ref_id']);

                if (!$adaItem && !$adaBumbu) {
                    $validator->errors()->add("resep.{$i}.item_id", 'Baris resep harus pilih Bahan atau Bumbu Pusat.');
                } elseif ($adaItem && $adaBumbu) {
                    $validator->errors()->add("resep.{$i}.item_id", 'Baris resep tidak boleh pilih Bahan DAN Bumbu Pusat sekaligus.');
                } elseif ($adaItem && empty($row['satuan'])) {
                    $validator->errors()->add("resep.{$i}.satuan", 'Satuan wajib diisi untuk bahan manual.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'kode_item'        => 'Kode Item',
            'nama_item'        => 'Nama Produk',
            'item_category_id' => 'Kategori',
            'harga_jual'       => 'Harga Jual',
        ];
    }
}
