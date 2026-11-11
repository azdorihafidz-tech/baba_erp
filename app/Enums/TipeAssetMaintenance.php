<?php

namespace App\Enums;

enum TipeAssetMaintenance: string
{
    case PerawatanRutin = 'perawatan_rutin';
    case Perbaikan = 'perbaikan';
    case Overhaul = 'overhaul';

    public function label(): string
    {
        return match($this) {
            TipeAssetMaintenance::PerawatanRutin => 'Perawatan Rutin',
            TipeAssetMaintenance::Perbaikan => 'Perbaikan',
            TipeAssetMaintenance::Overhaul => 'Overhaul',
        };
    }
}
