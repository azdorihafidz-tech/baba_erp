<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserHistory;

class UserObserver
{
    public function updating(User $user): void
    {
        UserHistory::create([
            'user_id'         => $user->id,
            'action'          => 'updated',
            'data_lama'       => $user->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    public function deleting(User $user): void
    {
        UserHistory::create([
            'user_id'         => $user->id,
            'action'          => 'deleted',
            'data_lama'       => $user->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
