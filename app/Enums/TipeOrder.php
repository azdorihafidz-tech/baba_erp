<?php

namespace App\Enums;

enum TipeOrder: string
{
    // Warisan Berkah Mulyo — SENGAJA TIDAK dihapus meski tidak dipakai order
    // baru manapun (semua order D'mentai pakai Penjualan), supaya query lama
    // yang masih referensi 'jasa_giling' (BepOtomatisService dkk, gap
    // terdokumentasi CLAUDE.md 4.1, ditunda Tahap 5/6) tidak error.
    case JasaGiling = 'jasa_giling';
    case ProdukJadi = 'produk_jadi';

    // D'mentai (Tahap 3) — satu-satunya value yang dipakai order baru,
    // apapun tipe_transaksi-nya (dine_in/takeaway/frozen).
    case Penjualan = 'penjualan';

    public function label(): string
    {
        return match($this) {
            TipeOrder::JasaGiling => 'Jasa Giling',
            TipeOrder::ProdukJadi => 'Produk Jadi',
            TipeOrder::Penjualan  => 'Penjualan',
        };
    }
}
