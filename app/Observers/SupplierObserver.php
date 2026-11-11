<?php

namespace App\Observers;

use App\Models\Supplier;
use App\Models\SupplierHistory;

class SupplierObserver
{
    public function updating(Supplier $supplier): void
    {
        SupplierHistory::create([
            'supplier_id'     => $supplier->id,
            'action'          => 'updated',
            'data_lama'       => $supplier->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(Supplier $supplier): void
    {
        SupplierHistory::create([
            'supplier_id'     => $supplier->id,
            'action'          => 'deleted',
            'data_lama'       => $supplier->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
