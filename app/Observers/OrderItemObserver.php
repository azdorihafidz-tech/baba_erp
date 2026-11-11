<?php

namespace App\Observers;

use App\Models\OrderItem;
use App\Models\OrderItemHistory;

class OrderItemObserver
{
    public function updating(OrderItem $item): void
    {
        OrderItemHistory::create([
            'order_item_id'   => $item->id,
            'action'          => 'updated',
            'data_lama'       => $item->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(OrderItem $item): void
    {
        OrderItemHistory::create([
            'order_item_id'   => $item->id,
            'action'          => 'deleted',
            'data_lama'       => $item->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
