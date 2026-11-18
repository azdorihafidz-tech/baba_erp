<?php

namespace App\Http\Middleware;

use App\Support\Paket;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API aplikasi member (/api/member/*) mengikuti Paket & Langganan ERP (keputusan Owner 18 Nov 2026, P1):
 * - modul Toko Online mati  → seperti toko ditutup: katalog & checkout ditolak, simpan/ubah ditolak (403); melihat riwayat tetap boleh.
 * - baca-saja (langganan habis) → melihat & mengunduh boleh; pesan, bayar, retur, kasir & opname member ditolak (423).
 * Kirim RO (ERP → aplikasi member) tidak lewat sini dan tetap berjalan (P2).
 */
class CekPaketMember
{
    public const PESAN_MATI = 'Layanan toko online sedang tidak tersedia. Pesanan yang sudah ada tetap bisa dilihat. Hubungi admin BABA.';

    public const PESAN_BACA_SAJA = 'Layanan toko sedang dibatasi: data tetap bisa dilihat, tetapi belum bisa memesan, membayar, retur, mencatat kasir, atau stok opname. Hubungi admin BABA.';

    /** GET yang tetap ditolak saat modul mati / baca-saja (dipakai untuk belanja baru). */
    private const BELANJA = ['api.member.katalog', 'api.member.checkout-opsi'];

    public function handle(Request $request, Closure $next): Response
    {
        $nama = (string) $request->route()?->getName();
        if (in_array($nama, ['api.member.sehat', 'api.member.status-toko'], true)) {
            return $next($request);
        }
        $baca = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ! in_array($nama, self::BELANJA, true);
        if ($baca) {
            return $next($request);
        }
        if (! Paket::aktif('toko_online')) {
            return response()->json(['ok' => false, 'pesan' => self::PESAN_MATI, 'paket' => 'mati'], 403);
        }
        if (Paket::bacaSaja()) {
            return response()->json(['ok' => false, 'pesan' => self::PESAN_BACA_SAJA, 'paket' => 'baca_saja'], 423);
        }

        return $next($request);
    }
}
