<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catat setiap simpan/ubah/hapus yang dilakukan akun Tim IT (Aktivitas Tim IT,
 * porting Dimsum 4.53). Isi formulir TIDAK disimpan (bisa
 * memuat kata sandi) — hanya menu, alamat, metode, IP, dan hasilnya.
 */
class CatatAktivitasTeknisi
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user && method_exists($user, 'isTeknisi') && $user->isTeknisi()
            && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            try {
                $route = $request->route();
                activity('TimIT')->causedBy($user)->withProperties([
                    'route'  => $route?->getName(),
                    'method' => $request->method(),
                    'url'    => $request->path(),
                    'ip'     => $request->ip(),
                    'status' => $response->getStatusCode(),
                ])->log('Tindakan: ' . ($route?->getName() ?? $request->path()));
            } catch (\Throwable $e) {
                \Log::warning('Gagal mencatat aktivitas Tim IT: ' . $e->getMessage());
            }
        }

        return $response;
    }
}
