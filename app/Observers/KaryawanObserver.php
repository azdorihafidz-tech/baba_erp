<?php

namespace App\Observers;

use App\Models\Karyawan;
use App\Models\KaryawanHistory;

class KaryawanObserver
{
    public function updating(Karyawan $karyawan): void
    {
        KaryawanHistory::create([
            'karyawan_id'     => $karyawan->id,
            'action'          => 'updated',
            'data_lama'       => $karyawan->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(Karyawan $karyawan): void
    {
        KaryawanHistory::create([
            'karyawan_id'     => $karyawan->id,
            'action'          => 'deleted',
            'data_lama'       => $karyawan->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
