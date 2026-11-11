<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\AssetHistory;

class AssetObserver
{
    public function updating(Asset $asset): void
    {
        AssetHistory::create([
            'asset_id'        => $asset->id,
            'action'          => 'updated',
            'data_lama'       => $asset->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(Asset $asset): void
    {
        AssetHistory::create([
            'asset_id'        => $asset->id,
            'action'          => 'deleted',
            'data_lama'       => $asset->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
