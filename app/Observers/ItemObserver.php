<?php

namespace App\Observers;

use App\Models\Item;
use App\Models\ItemHistory;

class ItemObserver
{
    public function updating(Item $item): void
    {
        ItemHistory::create([
            'item_id'         => $item->id,
            'action'          => 'updated',
            'data_lama'       => $item->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(Item $item): void
    {
        ItemHistory::create([
            'item_id'         => $item->id,
            'action'          => 'deleted',
            'data_lama'       => $item->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
