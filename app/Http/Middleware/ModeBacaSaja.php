<?php

namespace App\Http\Middleware;

use App\Support\Paket;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langganan habis (lewat masa tenggang) → mode BACA-SAJA: semua simpan/ubah/
 * hapus ditolak untuk user pelanggan; melihat data & mengunduh laporan tetap
 * boleh. Tim IT tidak terkena (supaya bisa memperpanjang/memperbaiki).
 * porting Dimsum 4.53 (_rancangan/RANCANGAN-KAMUS-TIMIT-PAKET.md) bagian 5.
 */
class ModeBacaSaja
{
    /** POST yang tidak mengubah data usaha (export, pratinjau, sesi, notifikasi, kata sandi sendiri). */
    public const DIKECUALIKAN = [
        'logout', 'password.update', 'password.email', 'password.store', 'cabang.switch', 'notifikasi.*',
        '*.export*', 'master.produk-jual.preview-bumbu', 'master.produk-jual.kalkulator-resep', 'penjualan.print-preview', 'admin.panduan.preview',
    ];

    public const PESAN = 'Masa langganan aplikasi sudah berakhir — aplikasi dalam mode baca-saja. Data dan laporan tetap bisa dilihat dan diunduh, tetapi tidak bisa menyimpan atau mengubah data. Hubungi tim IT penyedia aplikasi untuk memperpanjang.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            || (method_exists($user, 'isTeknisi') && $user->isTeknisi()) || ! Paket::bacaSaja()) {
            return $next($request);
        }
        $nama = (string) $request->route()?->getName();
        foreach (self::DIKECUALIKAN as $pola) {
            if ($nama !== '' && Str::is($pola, $nama)) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'pesan' => self::PESAN, 'message' => self::PESAN], 423);
        }

        return back()->withInput()->with('error', self::PESAN);
    }
}
