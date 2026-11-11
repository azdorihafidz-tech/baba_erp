<?php

namespace App\Enums;

enum StatusSetoran: string
{
    case MenungguDiterima = 'menunggu_diterima';
    case Diterima         = 'diterima';
    case Ditolak          = 'ditolak';
    case Dibatalkan       = 'dibatalkan';

    public function label(): string
    {
        return match($this) {
            self::MenungguDiterima => 'Menunggu Diterima',
            self::Diterima         => 'Diterima',
            self::Ditolak          => 'Ditolak',
            self::Dibatalkan       => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::MenungguDiterima => 'bg-warning text-dark',
            self::Diterima         => 'bg-success',
            self::Ditolak          => 'bg-danger',
            self::Dibatalkan       => 'bg-secondary',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::MenungguDiterima => 'bi-hourglass-split',
            self::Diterima         => 'bi-check-circle-fill',
            self::Ditolak          => 'bi-x-circle-fill',
            self::Dibatalkan       => 'bi-slash-circle',
        };
    }
}
