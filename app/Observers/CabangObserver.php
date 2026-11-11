<?php

namespace App\Observers;

use App\Models\Cabang;
use App\Models\CabangHistory;

class CabangObserver
{
    public function updating(Cabang $cabang): void
    {
        CabangHistory::create([
            'cabang_id'       => $cabang->id,
            'action'          => 'updated',
            'data_lama'       => $cabang->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(Cabang $cabang): void
    {
        CabangHistory::create([
            'cabang_id'       => $cabang->id,
            'action'          => 'deleted',
            'data_lama'       => $cabang->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
