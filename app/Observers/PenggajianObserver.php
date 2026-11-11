<?php

namespace App\Observers;

use App\Models\Penggajian;
use App\Models\PenggajianHistory;

class PenggajianObserver
{
    public function updating(Penggajian $penggajian): void
    {
        PenggajianHistory::create([
            'penggajian_id'   => $penggajian->id,
            'action'          => 'updated',
            'data_lama'       => $penggajian->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(Penggajian $penggajian): void
    {
        PenggajianHistory::create([
            'penggajian_id'   => $penggajian->id,
            'action'          => 'deleted',
            'data_lama'       => $penggajian->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
