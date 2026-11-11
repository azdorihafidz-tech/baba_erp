<?php

namespace App\Enums;

enum StatusAset: string
{
    case Aktif = 'aktif';
    case TidakAktif = 'tidak_aktif';
    case Dijual = 'dijual';
    case Dihapuskan = 'dihapuskan';

    public function label(): string
    {
        return match($this) {
            StatusAset::Aktif => 'Aktif',
            StatusAset::TidakAktif => 'Tidak Aktif',
            StatusAset::Dijual => 'Dijual',
            StatusAset::Dihapuskan => 'Dihapuskan',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            StatusAset::Aktif => 'bg-success',
            StatusAset::TidakAktif => 'bg-secondary',
            StatusAset::Dijual => 'bg-info',
            StatusAset::Dihapuskan => 'bg-danger',
        };
    }
}
