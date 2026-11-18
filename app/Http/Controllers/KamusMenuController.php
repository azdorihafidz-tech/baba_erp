<?php

namespace App\Http\Controllers;

use App\Models\KamusMenu;
use Illuminate\Http\Request;

/**
 * 4.50 Kamus Menu — bisa dibuka semua user login (seperti Panduan), tapi setiap
 * entri hanya tampil kalau user boleh membuka menu yang dijelaskan.
 */
class KamusMenuController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q    = trim((string) $request->input('q', ''));

        $entri = KamusMenu::semuaAktif()->filter(fn (KamusMenu $k) => $k->bolehDilihat($user))->values();

        $hasil = $q !== '' ? KamusMenu::cari($q, $entri) : collect();

        $urutan = array_flip(KamusMenu::KELOMPOK);
        $perKelompok = $entri
            ->sortBy(fn ($k) => sprintf('%03d-%05d', $urutan[$k->kelompok] ?? 999, $k->urutan))
            ->groupBy('kelompok');

        return view('kamus-menu.index', compact('q', 'hasil', 'perKelompok'));
    }

    public function show(Request $request, string $slug)
    {
        $user  = $request->user();
        $kamus = KamusMenu::semuaAktif()->firstWhere('slug', $slug);
        abort_if(! $kamus || ! $kamus->bolehDilihat($user), 404);

        $terkait = collect($kamus->terkait ?? [])
            ->map(fn ($s) => KamusMenu::semuaAktif()->firstWhere('slug', $s))
            ->filter(fn ($k) => $k && $k->bolehDilihat($user))
            ->values();

        // Tombol "Buka menu ini": route pertama yang ada & tidak butuh parameter.
        $urlMenu = null;
        foreach ($kamus->route_names ?? [] as $nama) {
            if (str_contains($nama, '*') || ! \Illuminate\Support\Facades\Route::has($nama)) {
                continue;
            }
            try {
                $urlMenu = route($nama);
                break;
            } catch (\Throwable) {
                // butuh parameter — lewati
            }
        }

        return view('kamus-menu.show', [
            'urlMenu' => $urlMenu,
            'kamus'   => $kamus,
            'terkait' => $terkait,
            'izin'    => $kamus->izinTeks(),
            'q'       => (string) $request->input('q', ''),
        ]);
    }
}
