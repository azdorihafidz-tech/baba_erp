<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class CabangSwitcherController extends Controller
{
    public function switch(Request $request)
    {
        $request->validate([
            'cabang_id' => 'nullable|exists:cabangs,id',
        ]);

        $user = $request->user();
        $cabangId = $request->input('cabang_id');

        // Validasi akses
        if ($cabangId && !$user->canAccessAllBranches()) {
            $userCabangIds = $user->cabangs()->pluck('cabangs.id')->toArray();
            if (!in_array($cabangId, $userCabangIds)) {
                abort(403, 'Anda tidak memiliki akses ke cabang ini.');
            }
        }

        // null = lihat semua (hanya untuk owner/admin_pusat)
        if ($cabangId === null && !$user->canAccessAllBranches()) {
            abort(403);
        }

        session(['active_cabang_id' => $cabangId]);

        $namaCabang = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? 'cabang terpilih') : 'Semua Cabang';
        [$tujuan, $keDaftar] = $this->halamanSetelahGanti(url()->previous());

        return redirect()->to($tujuan)->with('success', $keDaftar
            ? "Cabang aktif: {$namaCabang}. Ditampilkan data milik cabang ini."
            : "Cabang aktif: {$namaCabang}.");
    }

    /**
     * Halaman tujuan setelah ganti cabang (CLAUDE.md 4.39) — data barang &
     * transaksi terpisah per cabang, jadi halaman yang menampilkan 1 data
     * milik cabang lama tidak boleh tetap terbuka:
     * - halaman edit / detail / tambah (route ber-parameter atau *.create/
     *   *.edit/*.show) → halaman daftar menu itu (fallback Dashboard);
     * - halaman daftar / laporan → halaman yang sama TANPA filter (query
     *   string dibuang) supaya tidak tersisa barang/lokasi cabang lama.
     *
     * @return array{0: string, 1: bool} [url tujuan, true kalau dipindah ke daftar]
     */
    private function halamanSetelahGanti(string $sebelumnya): array
    {
        $dashboard = Route::has('dashboard') ? route('dashboard') : url('/');

        try {
            $route = app('router')->getRoutes()->match(Request::create($sebelumnya, 'GET'));
        } catch (\Throwable $e) {
            return [$dashboard, false];
        }

        $nama = (string) $route->getName();
        // Profil pengguna bukan data cabang → tetap di halaman itu
        $halamanSatuData = ! str_starts_with($nama, 'profile.')
            && ($route->parameterNames() !== [] || preg_match('/\.(create|edit|show)$/', $nama));

        if (! $halamanSatuData) {
            return [strtok($sebelumnya, '?'), false];
        }

        // a.b.edit → a.b.index → a.b → a.index → a (route tanpa parameter wajib)
        $bagian = explode('.', $nama);
        array_pop($bagian);
        while ($bagian) {
            $prefix = implode('.', $bagian);
            foreach ([$prefix . '.index', $prefix] as $kandidat) {
                $r = Route::has($kandidat) ? Route::getRoutes()->getByName($kandidat) : null;
                if ($r && in_array('GET', $r->methods(), true) && $r->parameterNames() === []) {
                    return [route($kandidat), true];
                }
            }
            array_pop($bagian);
        }

        // Cadangan lewat alamat: /evaluasi/5/detail → /evaluasi (halaman daftar tanpa parameter)
        $segmen = array_values(array_filter(explode('/', $route->uri()), fn ($s) => $s !== '' && ! str_contains($s, '{')));
        while ($segmen) {
            $kandidat = '/' . implode('/', $segmen);
            try {
                $r = app('router')->getRoutes()->match(Request::create($kandidat, 'GET'));
                if ($r->parameterNames() === [] && ! preg_match('/\.(create|edit|show)$/', (string) $r->getName())) {
                    return [url($kandidat), true];
                }
            } catch (\Throwable $e) {
                // tidak ada halaman di alamat ini — coba lebih pendek
            }
            array_pop($segmen);
        }

        return [$dashboard, true];
    }
}
