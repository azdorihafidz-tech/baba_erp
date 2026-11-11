<?php

namespace App\Observers;

use App\Models\Cuti;
use App\Models\CutiHistory;

class CutiObserver
{
    public function updating(Cuti $cuti): void
    {
        CutiHistory::create([
            'cuti_id'         => $cuti->id,
            'action'          => 'updated',
            'data_lama'       => $cuti->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(Cuti $cuti): void
    {
        CutiHistory::create([
            'cuti_id'         => $cuti->id,
            'action'          => 'deleted',
            'data_lama'       => $cuti->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
