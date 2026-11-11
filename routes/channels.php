<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Private channel untuk antrian produksi per cabang.
| Channel name: private-antrian.{cabang_id}
|
| Akses diizinkan untuk:
| - Owner / Admin Pusat (canAccessAllBranches)
| - User yang terdaftar di cabang tersebut (via pivot cabang_user)
*/

// Private channel untuk bell notifikasi per user
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('antrian.{cabangId}', function ($user, $cabangId) {
    if ($user->canAccessAllBranches()) {
        return true;
    }

    return $user->cabangs()->where('cabangs.id', (int) $cabangId)->exists();
});
