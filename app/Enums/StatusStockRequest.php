<?php

namespace App\Enums;

enum StatusStockRequest: string
{
    case Pending = 'pending';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dikirim = 'dikirim';
    case Diterima = 'diterima';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match($this) {
            StatusStockRequest::Pending => 'Menunggu',
            StatusStockRequest::Disetujui => 'Disetujui',
            StatusStockRequest::Ditolak => 'Ditolak',
            StatusStockRequest::Dikirim => 'Dikirim',
            StatusStockRequest::Diterima => 'Diterima',
            StatusStockRequest::Dibatalkan => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            StatusStockRequest::Pending => 'bg-warning',
            StatusStockRequest::Disetujui => 'bg-info',
            StatusStockRequest::Ditolak => 'bg-danger',
            StatusStockRequest::Dikirim => 'bg-primary',
            StatusStockRequest::Diterima => 'bg-success',
            StatusStockRequest::Dibatalkan => 'bg-secondary',
        };
    }
}
