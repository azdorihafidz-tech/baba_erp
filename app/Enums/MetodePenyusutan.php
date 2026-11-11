<?php

namespace App\Enums;

enum MetodePenyusutan: string
{
    case GarisLurus = 'garis_lurus';
    case SaldoMenurun = 'saldo_menurun';
    case SatuanProduksi = 'satuan_produksi';

    public function label(): string
    {
        return match($this) {
            MetodePenyusutan::GarisLurus => 'Garis Lurus (Straight Line)',
            MetodePenyusutan::SaldoMenurun => 'Saldo Menurun (Declining Balance)',
            MetodePenyusutan::SatuanProduksi => 'Satuan Hasil Produksi (Units of Production)',
        };
    }
}
