<?php

namespace App\Observers;

use App\Models\Stock;
use App\Models\StockHistory;

class StockObserver
{
    public function updating(Stock $stock): void
    {
        StockHistory::create([
            'stock_id'        => $stock->id,
            'action'          => 'updated',
            'data_lama'       => $stock->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(Stock $stock): void
    {
        StockHistory::create([
            'stock_id'        => $stock->id,
            'action'          => 'deleted',
            'data_lama'       => $stock->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
