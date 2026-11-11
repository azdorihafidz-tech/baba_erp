<?php

namespace App\Observers;

use App\Models\StockTransfer;
use App\Models\StockTransferHistory;

class StockTransferObserver
{
    public function updating(StockTransfer $stockTransfer): void
    {
        StockTransferHistory::create([
            'stock_transfer_id' => $stockTransfer->id,
            'action'            => 'updated',
            'data_lama'         => $stockTransfer->getOriginal(),
            'changed_by'        => auth()->id(),
            'changed_by_name'   => auth()->user()?->name,
            'changed_at'        => now(),
        ]);
    }

    public function deleting(StockTransfer $stockTransfer): void
    {
        StockTransferHistory::create([
            'stock_transfer_id' => $stockTransfer->id,
            'action'            => 'deleted',
            'data_lama'         => $stockTransfer->toArray(),
            'changed_by'        => auth()->id(),
            'changed_by_name'   => auth()->user()?->name,
            'changed_at'        => now(),
        ]);
    }
}
