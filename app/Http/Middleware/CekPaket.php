<?php

namespace App\Http\Middleware;

use App\Support\Paket;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alamat milik modul yang tidak termasuk paket → halaman "tidak termasuk paket"
 * (berlaku untuk semua user, termasuk Owner & Tim IT). porting Dimsum 4.53 (_rancangan/RANCANGAN-KAMUS-TIMIT-PAKET.md).
 */
class CekPaket
{
    public function handle(Request $request, Closure $next): Response
    {
        $modul = Paket::modulUntukRoute($request->route()?->getName());
        if ($modul && ! Paket::aktif($modul)) {
            $label = Paket::MODUL[$modul][0];
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'pesan' => "Fitur {$label} tidak termasuk paket aplikasi Anda.", 'message' => "Fitur {$label} tidak termasuk paket aplikasi Anda."], 403);
            }

            return response()->view('paket.tidak-termasuk', ['label' => $label, 'modul' => $modul], 403);
        }

        return $next($request);
    }
}
