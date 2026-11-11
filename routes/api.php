<?php

use App\Http\Controllers\Api\Member\KatalogController;
use App\Http\Controllers\Api\Member\PesananController;
use App\Http\Controllers\Api\Member\SehatController;
use Illuminate\Support\Facades\Route;

/*
| API untuk server aplikasi member BABA (antar-server, ditandatangani). Bukan untuk browser.
| Prefix /api (bootstrap/app.php). Middleware 'aplikasi.member' = VerifikasiAplikasiMember.
*/
Route::prefix('member')->middleware('aplikasi.member')->name('api.member.')->group(function () {
    Route::get('sehat', SehatController::class)->name('sehat');
    Route::get('katalog', KatalogController::class)->name('katalog');
    Route::get('checkout-opsi', [PesananController::class, 'opsi'])->name('checkout-opsi');
    Route::get('pesanan', [PesananController::class, 'index'])->name('pesanan.index');
    Route::post('pesanan', [PesananController::class, 'store'])->name('pesanan.store');
    Route::get('pesanan/{nomor}', [PesananController::class, 'show'])->name('pesanan.show');
    Route::post('pesanan/{nomor}/bukti', [PesananController::class, 'bukti'])->name('pesanan.bukti');
    Route::post('pesanan/{nomor}/batal', [PesananController::class, 'batal'])->name('pesanan.batal');
    Route::post('pesanan/{nomor}/terima', [PesananController::class, 'terima'])->name('pesanan.terima');
});
