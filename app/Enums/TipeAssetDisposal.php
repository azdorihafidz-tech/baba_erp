<?php

namespace App\Enums;

enum TipeAssetDisposal: string
{
    case Dijual = 'dijual';
    case Dibuang = 'dibuang';
    case Dihibahkan = 'dihibahkan';

    public function label(): string
    {
        return match($this) {
            TipeAssetDisposal::Dijual => 'Dijual',
            TipeAssetDisposal::Dibuang => 'Dibuang',
            TipeAssetDisposal::Dihibahkan => 'Dihibahkan',
        };
    }
}
