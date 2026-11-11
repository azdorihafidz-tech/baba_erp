<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** 4.41 — pilihan tipe disembunyikan / hanya 1 tipe aktif → pakai tipe otomatis cabang. */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('tipe_transaksi')) {
            $cabangId = session('active_cabang_id') ?? $this->user()?->defaultCabangId();
            $tipe = $cabangId ? \App\Models\Cabang::find($cabangId)?->tipeTransaksiOtomatis() : null;
            if ($tipe) {
                $this->merge(['tipe_transaksi' => $tipe]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'pelanggan_id'       => 'nullable|exists:pelanggans,id',
            // Tahap 3 D'mentai — walk-in dine-in/takeaway/frozen tidak selalu
            // punya data pelanggan, jadi tidak lagi wajib (beda dari alur
            // jasa giling lama yang mewajibkan nama+telepon).
            'nama_pelanggan'     => 'nullable|string|max:255',
            'telepon_pelanggan'  => 'nullable|string|max:20',
            'tipe_transaksi'     => 'required|in:dine_in,takeaway,frozen',
            'nomor_meja'         => 'nullable|string|max:20',
            'tanggal_expired_frozen' => 'nullable|date',
            // Split Payment — sumber kebenaran, WAJIB minimal 1 baris.
            'payments'              => 'required|array|min:1',
            'payments.*.metode'     => 'required|in:tunai,transfer,qris',
            'payments.*.jumlah'     => 'required|numeric|min:0.01',
            'kas_id'             => 'nullable|exists:kas,id',
            'jumlah_bayar'       => 'required|numeric|min:0',
            'diskon'             => 'nullable|numeric|min:0',
            'catatan'            => 'nullable|string',
            'bukti_pembayaran'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'tampil_di_antrian'  => 'nullable|boolean',
            'parent_order_id'    => 'nullable|exists:orders,id',
            // Tahap C 4.38 — nota gudang yang memproses PO cabang (supplier internal)
            'purchase_order_id'  => 'nullable|exists:purchase_orders,id',
            // 4.49 Tahap B2 — Kirim ke Cabang (hanya POS Gudang Pusat, divalidasi lagi di PenjualanService)
            'customer_type'      => 'nullable|in:retail,internal',
            'outlet_tujuan_id'   => 'nullable|required_if:customer_type,internal|exists:cabangs,id',
            'items'              => 'required|array|min:1',
            // Tahap 3 D'mentai — POS baru cuma jual produk jadi (dari
            // katalog atau custom item bebas), tidak ada lagi baris jasa
            // giling. Field tetap ada (bukan dihapus) supaya tidak breaking
            // kalau ada data/kode lama yang masih mengirim 'jasa_giling'.
            'items.*.tipe'       => 'nullable|in:produk_jadi,jasa_giling',
            'items.*.item_id'    => 'nullable|exists:items,id',
            'items.*.item_variant_id' => 'nullable|exists:item_variants,id',
            // 4.44 Poin 2 — catatan per item, ikut tercetak di Label Produk
            'items.*.catatan'        => 'nullable|string|max:255',
            'items.*.nama_item'  => 'required|string',
            'items.*.qty'        => 'required|numeric|min:0.001',
            // Satuan yang klien klaim dikirim -- server TETAP mengambil
            // keputusan akhir dari master item (lihat PenjualanService::
            // buatOrder()), field ini cuma perlu SURVIVE validated() supaya
            // bisa dipakai sebagai bahan validasi defense-in-depth di sana
            // (kalau tidak ada di rules ini, Laravel FormRequest::validated()
            // otomatis membuang field ini sebelum sampai ke service).
            'items.*.satuan'     => 'nullable|string|max:20',
            'items.*.harga_satuan'   => 'required|numeric|min:0',
            'items.*.berat_daging'   => 'nullable|required_if:items.*.tipe,jasa_giling|numeric|min:0.001',
            'items.*.jenis_olahan'   => ['nullable', 'string', 'exists:jenis_olahans,slug'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_pelanggan'     => 'Nama Pelanggan',
            'telepon_pelanggan'  => 'Telepon Pelanggan',
            'tipe_transaksi'     => 'Tipe Transaksi',
            'jumlah_bayar'       => 'Jumlah Bayar',
            'payments'           => 'Metode Pembayaran',
            'items'              => 'Item Order',
            'items.*.nama_item'  => 'Nama Item',
            'items.*.qty'        => 'Qty',
            'items.*.harga_satuan'   => 'Harga Satuan',
            'items.*.berat_daging'   => 'Berat',
        ];
    }

    public function messages(): array
    {
        return [
            'tipe_transaksi.required'    => 'Pilih tipe transaksi (Dine-in/Takeaway/Frozen).',
            'payments.required'          => 'Pilih minimal 1 metode pembayaran.',
            'payments.min'               => 'Pilih minimal 1 metode pembayaran.',
            'items.required'             => 'Order harus punya minimal 1 item.',
            'items.min'                  => 'Order harus punya minimal 1 item.',
            'items.*.berat_daging.required_if' => 'Berat wajib diisi untuk item jasa giling.',
        ];
    }
}
