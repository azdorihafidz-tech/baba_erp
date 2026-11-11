<?php

namespace App\Support;

use Illuminate\Routing\UrlGenerator;

/**
 * Override PATH asset(): 'storage/...' -> 'asset/...' (2026-09-16).
 *
 * KENAPA INI ADA (JANGAN HAPUS TANPA BACA INI DULU):
 * Production (Rumah Web, shared hosting cPanel) punya rule bawaan di
 * public/.htaccess: `RewriteRule ^storage/ - [L,NC]` — rule ini didesain
 * untuk kondisi public/storage adalah SYMLINK/JUNCTION beneran (jalan
 * normal di XAMPP lokal), supaya Apache serve file itu langsung tanpa lewat
 * Laravel (demi performa). TAPI di Rumah Web, PHP symlink() DIBLOKIR provider
 * — public/storage TIDAK PERNAH ADA sebagai symlink — jadi rule itu MALAH
 * bikin Apache coba serve file yang tidak ada, gagal, dan berakhir di 404
 * Laravel branded (via ErrorDocument hosting) SEBELUM request sempat
 * di-rewrite balik ke index.php. Laravel Route::get('/storage/{path}')
 * apapun TIDAK PERNAH ke-hit karena request-nya tidak pernah sampai situ.
 *
 * FIX (disetujui Owner, alih-alih edit .htaccess produksi yang berisiko):
 * override asset() secara GLOBAL di level UrlGenerator supaya SEMUA
 * pemanggilan `asset('storage/xxx')` di manapun (view lama/baru, tanpa
 * perlu diedit satu-satu) otomatis generate URL ke path BARU 'asset/xxx' —
 * path ini TIDAK match rule .htaccess bawaan di atas (yang cuma match
 * `^storage/`), jadi request-nya lolos sampai ke index.php dan ditangani
 * route Laravel biasa (lihat routes/web.php + StorageAssetController).
 *
 * SCOPE SENGAJA SEMPIT: cuma path yang LITERAL diawali 'storage/' yang
 * di-rewrite. URL absolute (http://...) atau path lain (mis. 'css/app.css',
 * 'js/app.js') TIDAK disentuh — asset() bawaan tetap jalan normal untuk itu.
 *
 * REVERSIBLE: kalau pindah hosting yang symlink-nya jalan normal (atau
 * Rumah Web mencabut rule .htaccess itu), cukup hapus blok registrasi di
 * AppServiceProvider::register() — class ini boleh dibiarkan menganggur,
 * tidak ada efek apapun kalau tidak didaftarkan ke container.
 */
class StorageAwareUrlGenerator extends UrlGenerator
{
    public function asset($path, $secure = null)
    {
        if (is_string($path) && str_starts_with($path, 'storage/')) {
            $path = 'asset/' . substr($path, strlen('storage/'));
        }

        return parent::asset($path, $secure);
    }
}
