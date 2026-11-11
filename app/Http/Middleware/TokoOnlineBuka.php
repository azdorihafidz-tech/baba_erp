<?php

namespace App\Http\Middleware;

use App\Models\TokoOnlinePengaturan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ERP BABA Tahap 2f — belanja & retur baru hanya bila toko online dibuka Owner untuk akun ini
 * (Pengaturan Toko Online › Status toko). Pesanan yang sudah berjalan tetap bisa dilihat & diproses.
 * Dipasang setelah aplikasi.member (butuh member_id).
 */
class TokoOnlineBuka
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! TokoOnlinePengaturan::ambil()->bukaUntuk($request->attributes->get('member_id'))) {
            return response()->json(['ok' => false, 'pesan' => 'Toko online belum dibuka.'], 403);
        }

        return $next($request);
    }
}
