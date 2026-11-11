<?php

use App\Http\Controllers\Api\Member\KatalogController;
use App\Http\Controllers\Api\Member\PesananController;
use App\Http\Controllers\Api\Member\SehatController;
use App\Http\Controllers\Api\Member\TokoSayaController;
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
    // Tahap 2c — Toko Saya: pesanan masuk dari downline (upline = penjual), stok saya, rekening saya
    Route::get('masuk', [TokoSayaController::class, 'masukIndex'])->name('masuk.index');
    Route::get('masuk/{nomor}', [TokoSayaController::class, 'masukRinci'])->name('masuk.show');
    Route::post('masuk/{nomor}/bayar/{id}/{aksi}', [TokoSayaController::class, 'masukBukti'])->whereNumber('id')->whereIn('aksi', ['terima', 'tolak'])->name('masuk.bukti');
    Route::get('masuk/{nomor}/bayar/{id}/file', [TokoSayaController::class, 'masukBuktiFile'])->whereNumber('id')->name('masuk.bukti.file');
    Route::post('masuk/{nomor}/{aksi}', [TokoSayaController::class, 'masukAksi'])->whereIn('aksi', ['ongkir', 'keluarkan', 'sudah-diambil', 'tunai', 'batal'])->name('masuk.aksi');
    Route::get('stok', [TokoSayaController::class, 'stok'])->name('stok');
    Route::get('rekening', [TokoSayaController::class, 'rekening'])->name('rekening');
    Route::post('rekening', [TokoSayaController::class, 'rekeningSimpan'])->name('rekening.simpan');
    Route::post('rekening/{id}/aktif', [TokoSayaController::class, 'rekeningAktif'])->whereNumber('id')->name('rekening.aktif');
});
