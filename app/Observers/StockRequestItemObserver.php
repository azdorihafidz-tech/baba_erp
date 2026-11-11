<?php

namespace App\Observers;

use App\Models\StockRequestItem;
use App\Models\StockRequestItemHistory;

class StockRequestItemObserver
{
    public function updating(StockRequestItem $stockRequestItem): void
    {
        StockRequestItemHistory::create([
            'stock_request_item_id' => $stockRequestItem->id,
            'action'                => 'updated',
            'data_lama'             => $stockRequestItem->getOriginal(),
            'changed_by'            => auth()->id(),
            'changed_by_name'       => auth()->user()?->name,
            'changed_at'            => now(),
        ]);
    }

    public function deleting(StockRequestItem $stockRequestItem): void
    {
        StockRequestItemHistory::create([
            'stock_request_item_id' => $stockRequestItem->id,
            'action'                => 'deleted',
            'data_lama'             => $stockRequestItem->toArray(),
            'changed_by'            => auth()->id(),
            'changed_by_name'       => auth()->user()?->name,
            'changed_at'            => now(),
        ]);
    }
}
