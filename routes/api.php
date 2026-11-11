<?php

use App\Http\Controllers\Api\Member\SehatController;
use Illuminate\Support\Facades\Route;

/*
| API untuk server aplikasi member BABA (antar-server, ditandatangani). Bukan untuk browser.
| Prefix /api (bootstrap/app.php). Middleware 'aplikasi.member' = VerifikasiAplikasiMember.
*/
Route::prefix('member')->middleware('aplikasi.member')->name('api.member.')->group(function () {
    Route::get('sehat', SehatController::class)->name('sehat');
});
