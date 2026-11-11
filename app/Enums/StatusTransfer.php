<?php

namespace App\Enums;

enum StatusTransfer: string
{
    case Draft = 'draft';
    case Dikirim = 'dikirim';
    case DiterimaSebagian = 'diterima_sebagian';
    case Diterima = 'diterima';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match($this) {
            StatusTransfer::Draft => 'Draft',
            StatusTransfer::Dikirim => 'Dikirim',
            StatusTransfer::DiterimaSebagian => 'Diterima Sebagian',
            StatusTransfer::Diterima => 'Diterima',
            StatusTransfer::Dibatalkan => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            StatusTransfer::Draft => 'bg-secondary',
            StatusTransfer::Dikirim => 'bg-info',
            StatusTransfer::DiterimaSebagian => 'bg-warning',
            StatusTransfer::Diterima => 'bg-success',
            StatusTransfer::Dibatalkan => 'bg-danger',
        };
    }
}
