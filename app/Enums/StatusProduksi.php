<?php

namespace App\Enums;

enum StatusProduksi: string
{
    case Menunggu   = 'menunggu';
    case Dikerjakan = 'dikerjakan';
    case Selesai    = 'selesai';
    case Disimpan   = 'disimpan';
    case Diambil    = 'diambil';

    public function label(): string
    {
        return match($this) {
            self::Menunggu   => 'Menunggu',
            self::Dikerjakan => 'Dikerjakan',
            self::Selesai    => 'Selesai',
            self::Disimpan   => 'Di Rak',
            self::Diambil    => 'Sudah Diambil',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Menunggu   => 'bg-secondary',
            self::Dikerjakan => 'bg-warning text-dark',
            self::Selesai    => 'bg-success',
            self::Disimpan   => 'bg-info text-dark',
            self::Diambil    => 'bg-light text-dark border',
        };
    }
}
