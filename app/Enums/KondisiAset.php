<?php

namespace App\Enums;

enum KondisiAset: string
{
    case Baik = 'baik';
    case RusakRingan = 'rusak_ringan';
    case RusakBerat = 'rusak_berat';
    case Dihapuskan = 'dihapuskan';

    public function label(): string
    {
        return match($this) {
            KondisiAset::Baik => 'Baik',
            KondisiAset::RusakRingan => 'Rusak Ringan',
            KondisiAset::RusakBerat => 'Rusak Berat',
            KondisiAset::Dihapuskan => 'Dihapuskan',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            KondisiAset::Baik => 'bg-success',
            KondisiAset::RusakRingan => 'bg-warning',
            KondisiAset::RusakBerat => 'bg-danger',
            KondisiAset::Dihapuskan => 'bg-secondary',
        };
    }
}
