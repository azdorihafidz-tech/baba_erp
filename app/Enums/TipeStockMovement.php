<?php

namespace App\Enums;

enum TipeStockMovement: string
{
    case Masuk = 'masuk';
    case Keluar = 'keluar';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match($this) {
            TipeStockMovement::Masuk => 'Masuk',
            TipeStockMovement::Keluar => 'Keluar',
            TipeStockMovement::Transfer => 'Transfer',
            TipeStockMovement::Adjustment => 'Penyesuaian',
        };
    }
}
