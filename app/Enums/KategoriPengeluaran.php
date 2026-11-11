<?php

namespace App\Enums;

enum KategoriPengeluaran: string
{
    case BahanBaku = 'bahan_baku';
    case GajiUpah = 'gaji_upah';
    case Operasional = 'operasional';
    case Transportasi = 'transportasi';
    case Marketing = 'marketing';
    case LainLain = 'lain_lain';

    public function label(): string
    {
        return match($this) {
            KategoriPengeluaran::BahanBaku => 'Bahan Baku',
            KategoriPengeluaran::GajiUpah => 'Gaji & Upah',
            KategoriPengeluaran::Operasional => 'Operasional',
            KategoriPengeluaran::Transportasi => 'Transportasi',
            KategoriPengeluaran::Marketing => 'Marketing & Pelanggan',
            KategoriPengeluaran::LainLain => 'Lain-lain',
        };
    }
}
