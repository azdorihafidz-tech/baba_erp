<?php

namespace App\Observers;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemHistory;

class PurchaseOrderItemObserver
{
    public function updating(PurchaseOrderItem $item): void
    {
        PurchaseOrderItemHistory::create([
            'purchase_order_item_id' => $item->id,
            'action'                 => 'updated',
            'data_lama'              => $item->getOriginal(),
            'changed_by'             => auth()->id(),
            'changed_by_name'        => auth()->user()?->name,
            'changed_at'             => now(),
        ]);
    }

    public function deleting(PurchaseOrderItem $item): void
    {
        PurchaseOrderItemHistory::create([
            'purchase_order_item_id' => $item->id,
            'action'                 => 'deleted',
            'data_lama'              => $item->toArray(),
            'changed_by'             => auth()->id(),
            'changed_by_name'        => auth()->user()?->name,
            'changed_at'             => now(),
        ]);
    }
}
