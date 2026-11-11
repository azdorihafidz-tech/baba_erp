<?php

namespace App\Http\Middleware;

use App\Support\Branding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identitas usaha (4.44 Poin 3) — nama & ikon usaha ke manifest PWA. Paket
 * laravelpwa membaca config statis, jadi nilainya diisi per request dari
 * Pengaturan Umum (sekali baca cache).
 */
class TerapkanBranding
{
    public function handle(Request $request, Closure $next): Response
    {
        app(Branding::class)->terapkanKeConfigPwa();

        return $next($request);
    }
}
