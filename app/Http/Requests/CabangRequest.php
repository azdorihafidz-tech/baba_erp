<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CabangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAllBranches() ?? false;
    }

    public function rules(): array
    {
        $cabangId = $this->route('cabang')?->id;

        return [
            'nama_cabang'      => ['required', 'string', 'max:100'],
            'kode_cabang'      => [
                'required', 'string', 'max:10', 'alpha_num',
                Rule::unique('cabangs', 'kode_cabang')->ignore($cabangId),
            ],
            'tipe'             => ['required', Rule::in(array_column(\App\Enums\TipeCabang::cases(), 'value'))],
            // BABA Tahap 3: jenis toko (hanya untuk tipe cabang)
            'jenis_toko'       => ['nullable', Rule::in(array_keys(\App\Models\Cabang::JENIS_TOKO))],
            'member_id'        => ['nullable', 'integer', 'min:1'],
            'alamat'           => ['nullable', 'string', 'max:500'],
            'telepon'          => ['nullable', 'string', 'max:20'],
            'kepala_cabang_id'    => ['nullable', 'exists:users,id'],
            'is_active'           => ['sometimes', 'boolean'],
            'latitude'            => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'           => ['nullable', 'numeric', 'between:-180,180'],
            'radius_absen_meter'  => ['nullable', 'integer', 'min:10', 'max:5000'],
            'jam_masuk'               => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'antrian_produksi_aktif'  => ['sometimes', 'boolean'],
            'running_text'            => ['nullable', 'string', 'max:500'],
            'running_text_aktif'      => ['sometimes', 'boolean'],
            'footer_struk'            => ['nullable', 'string', 'max:500'],
            'izinkan_sembunyi_harga_struk' => ['sometimes', 'boolean'],
            // Tahap 3 D'mentai — config POS per outlet
            'dine_in_aktif'           => ['sometimes', 'boolean'],
            'takeaway_aktif'          => ['sometimes', 'boolean'],
            'frozen_aktif'            => ['sometimes', 'boolean'],
            'nomor_meja_aktif'        => ['sometimes', 'boolean'],
            // 4.41 — tampil/sembunyikan pilihan tipe transaksi di POS + tipe bawaannya
            'pilih_tipe_transaksi'    => ['sometimes', 'boolean'],
            'tipe_transaksi_default'  => ['sometimes', 'in:dine_in,takeaway,frozen'],
            'wajib_bukti_transfer'    => ['sometimes', 'boolean'],
            'wajib_bukti_qris'        => ['sometimes', 'boolean'],
            'kirim_cabang_aktif'      => ['sometimes', 'boolean'], // 4.49
            // 4.44 Poin 2 — Label Produk (stiker) per cabang
            'label_cup_aktif'         => ['sometimes', 'boolean'],
            'label_cup_kategori'      => ['sometimes', 'array'],
            'label_cup_kategori.*'    => ['string', 'max:20'],
            'label_cup_lebar_mm'      => ['sometimes', 'integer', 'min:25', 'max:120'],
            'label_cup_tinggi_mm'     => ['sometimes', 'integer', 'min:15', 'max:120'],
            // 4.49 Tahap D — geser posisi label (mm); printer/gulungan stiker bisa menggeser isi
            'label_cup_geser_x'       => ['sometimes', 'nullable', 'numeric', 'min:-10', 'max:10'],
            'label_cup_geser_y'       => ['sometimes', 'nullable', 'numeric', 'min:-10', 'max:10'],
            // 4.38-F — khusus tambah cabang: salin harga & pengaturan barang dari cabang lain
            'salin_dari_cabang_id'    => ['nullable', 'integer', 'exists:cabangs,id'],
            'take_away_fee'           => ['nullable', 'numeric', 'min:0'],
            'service_charge_persen'   => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_cabang.required'      => 'Nama cabang wajib diisi.',
            'nama_cabang.max'           => 'Nama cabang maksimal 100 karakter.',
            'kode_cabang.required'      => 'Kode cabang wajib diisi.',
            'kode_cabang.max'           => 'Kode cabang maksimal 10 karakter.',
            'kode_cabang.alpha_num'     => 'Kode cabang hanya boleh huruf dan angka.',
            'kode_cabang.unique'        => 'Kode cabang sudah digunakan.',
            'tipe.required'             => 'Tipe cabang wajib dipilih.',
            'tipe.in'                   => 'Tipe cabang tidak valid.',
            'kepala_cabang_id.exists'   => 'Kepala cabang yang dipilih tidak ditemukan.',
        ];
    }

    public function failedAuthorization()
    {
        abort(403, 'Hanya Owner yang dapat mengelola data cabang.');
    }
}
