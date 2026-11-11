<?php

namespace App\Enums;

enum StatusPurchaseOrder: string
{
    case Draft = 'draft';
    case Disetujui = 'disetujui';
    case DikirimSupplier = 'dikirim_supplier';
    case Diterima = 'diterima';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match($this) {
            StatusPurchaseOrder::Draft => 'Draft',
            StatusPurchaseOrder::Disetujui => 'Disetujui',
            StatusPurchaseOrder::DikirimSupplier => 'Dikirim Supplier',
            StatusPurchaseOrder::Diterima => 'Diterima',
            StatusPurchaseOrder::Dibatalkan => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            StatusPurchaseOrder::Draft => 'bg-secondary',
            StatusPurchaseOrder::Disetujui => 'bg-info',
            StatusPurchaseOrder::DikirimSupplier => 'bg-primary',
            StatusPurchaseOrder::Diterima => 'bg-success',
            StatusPurchaseOrder::Dibatalkan => 'bg-danger',
        };
    }
}
