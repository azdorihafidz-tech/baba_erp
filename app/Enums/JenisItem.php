<?php

namespace App\Enums;

enum JenisItem: string
{
    case BahanBaku = 'bahan_baku';
    case Perlengkapan = 'perlengkapan';

    public function label(): string
    {
        return match($this) {
            JenisItem::BahanBaku => 'Bahan Baku',
            JenisItem::Perlengkapan => 'Perlengkapan',
        };
    }
}
