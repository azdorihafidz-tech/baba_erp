<?php

namespace App\Enums;

enum TipeCabang: string
{
    case Cabang     = 'cabang';
    case GudangPusat = 'gudang_pusat';
    case HeadOffice = 'head_office';

    public function label(): string
    {
        return match($this) {
            self::Cabang      => 'Cabang',
            self::GudangPusat => 'Gudang Pusat',
            self::HeadOffice  => 'Head Office',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Cabang      => '🏢',
            self::GudangPusat => '📦',
            self::HeadOffice  => '🏛️',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Cabang      => 'bg-primary',
            self::GudangPusat => 'bg-warning text-dark',
            self::HeadOffice  => 'bg-info text-dark',
        };
    }

    public function isOperasional(): bool
    {
        return $this === self::Cabang;
    }
}
