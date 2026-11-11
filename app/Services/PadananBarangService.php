<?php

namespace App\Services;

use App\Models\Item;

/**
 * Padanan barang antar cabang (CLAUDE.md 4.39) — data barang terpisah per
 * cabang; barang yang "sama" di cabang berbeda dipasangkan lewat Kode Item
 * yang sama. Dipakai transfer stok, PO cabang ke Gudang (supplier internal),
 * dan permintaan stok.
 */
class PadananBarangService
{
    /**
     * Barang milik $cabangId yang sepadan dengan $sumber (kode sama).
     * Data lama tanpa cabang / sudah milik cabang itu → barang itu sendiri.
     * $buat = true → kalau belum ada, dibuatkan salinan di cabang tujuan.
     */
    public function untukCabang(Item $sumber, int $cabangId, bool $buat = true): ?Item
    {
        if ($sumber->cabang_id === null || (int) $sumber->cabang_id === $cabangId) {
            return $sumber;
        }

        $ada = Item::withTrashed()->where('cabang_id', $cabangId)->where('kode_item', $sumber->kode_item)->first();
        if ($ada) {
            if ($ada->trashed()) {
                $ada->restore();
            }
            return $ada;
        }
        if (! $buat) {
            return null;
        }

        // Salinan baru di cabang tujuan. Produk jual gudang yang dibeli cabang
        // menjadi bahan baku (stok) di cabang itu — dijual/diolah lewat produk
        // milik cabang sendiri.
        $baru = $sumber->replicate(['cabang_id', 'qty_minimum', 'harga_po_terakhir', 'harga_po_terakhir_at']);
        $baru->cabang_id = $cabangId;
        $baru->qty_minimum = 0;
        $baru->is_active = true;
        if (in_array($sumber->tipe, Item::TIPE_PRODUK_INTERNAL, true)) {
            $baru->tipe = 'bahan_baku';
            $baru->harga_beli_terakhir = $sumber->harga_jual;
            $baru->harga_jual = null;
            $baru->track_stok = true;
            $baru->punya_varian = false;
            $baru->stok_per_varian = false;
        }
        $baru->save();

        return $baru;
    }

    /** Cari padanan tanpa membuat (null kalau cabang tujuan belum punya). */
    public function cari(Item $sumber, int $cabangId): ?Item
    {
        return $this->untukCabang($sumber, $cabangId, false);
    }
}
