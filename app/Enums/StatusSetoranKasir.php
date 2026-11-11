<?php

namespace App\Enums;

enum StatusSetoranKasir: string
{
    case Menunggu = 'menunggu';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Approval',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Menunggu => 'bg-warning text-dark',
            self::Approved => 'bg-success',
            self::Rejected => 'bg-danger',
        };
    }
}
