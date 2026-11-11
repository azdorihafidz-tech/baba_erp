<?php

namespace App\Observers;

use App\Models\StockTransferItem;
use App\Models\StockTransferItemHistory;

class StockTransferItemObserver
{
    public function updating(StockTransferItem $stockTransferItem): void
    {
        StockTransferItemHistory::create([
            'stock_transfer_item_id' => $stockTransferItem->id,
            'action'                 => 'updated',
            'data_lama'              => $stockTransferItem->getOriginal(),
            'changed_by'             => auth()->id(),
            'changed_by_name'        => auth()->user()?->name,
            'changed_at'             => now(),
        ]);
    }

    public function deleting(StockTransferItem $stockTransferItem): void
    {
        StockTransferItemHistory::create([
            'stock_transfer_item_id' => $stockTransferItem->id,
            'action'                 => 'deleted',
            'data_lama'              => $stockTransferItem->toArray(),
            'changed_by'             => auth()->id(),
            'changed_by_name'        => auth()->user()?->name,
            'changed_at'             => now(),
        ]);
    }
}
