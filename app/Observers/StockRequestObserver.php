<?php

namespace App\Observers;

use App\Models\StockRequest;
use App\Models\StockRequestHistory;

class StockRequestObserver
{
    public function updating(StockRequest $stockRequest): void
    {
        StockRequestHistory::create([
            'stock_request_id' => $stockRequest->id,
            'action'           => 'updated',
            'data_lama'        => $stockRequest->getOriginal(),
            'changed_by'       => auth()->id(),
            'changed_by_name'  => auth()->user()?->name,
            'changed_at'       => now(),
        ]);
    }

    public function deleting(StockRequest $stockRequest): void
    {
        StockRequestHistory::create([
            'stock_request_id' => $stockRequest->id,
            'action'           => 'deleted',
            'data_lama'        => $stockRequest->toArray(),
            'changed_by'       => auth()->id(),
            'changed_by_name'  => auth()->user()?->name,
            'changed_at'       => now(),
        ]);
    }
}
