<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve file dari storage/app/public/{path} lewat Laravel (bukan symlink),
 * dipasang di route '/asset/{path}' (2026-09-16). Lihat penjelasan lengkap
 * kenapa ini perlu di App\Support\StorageAwareUrlGenerator.
 *
 * Dipakai sebagai fallback utk shared hosting yang PHP symlink()-nya
 * diblokir provider (mis. Rumah Web) — di environment yang symlink-nya
 * jalan normal (XAMPP lokal), asset('storage/xxx') dioverride generate URL
 * 'asset/xxx' juga (konsisten 1 skema), jadi controller ini TETAP dipakai
 * di kedua environment, bukan cuma production.
 */
class StorageAssetController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        // Cegah path traversal ("../../.env") -- normalisasi & tolak kalau
        // hasilnya mengandung ".." di manapun.
        $normalized = str_replace('\\', '/', $path);
        abort_if(str_contains($normalized, '..'), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($normalized), 404);

        return $disk->response($normalized, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
