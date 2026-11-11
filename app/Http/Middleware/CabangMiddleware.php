<?php

namespace App\Http\Middleware;

use App\Models\Cabang;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CabangMiddleware
{
    /**
     * Set active_cabang_id di session saat login atau ganti cabang
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $user = $request->user();

            // Jika belum ada cabang aktif di session, set default
            if (!session()->has('active_cabang_id')) {
                $defaultCabangId = $user->defaultCabangId();

                // Owner/Admin Pusat: default ke null (lihat semua)
                if ($user->canAccessAllBranches() && !$defaultCabangId) {
                    session(['active_cabang_id' => null]);
                } elseif ($defaultCabangId) {
                    session(['active_cabang_id' => $defaultCabangId]);
                } else {
                    // Ambil cabang pertama yang dimiliki user
                    $firstCabang = $user->cabangs()->first();
                    session(['active_cabang_id' => $firstCabang?->id]);
                }
            }

            // Share data cabang ke semua view
            $activeCabangId = session('active_cabang_id');
            $activeCabang = $activeCabangId ? Cabang::find($activeCabangId) : null;

            // Tahap 7 D'mentai (Bug 1 fix, 2026-09-14) — root cause "Outlet 3-5
            // tidak muncul di dropdown Pilih Cabang Aktif" BUKAN cuma soal CSS
            // overflow: user dgn canAccessAllBranches() (owner/admin_pusat)
            // TETAP di-filter ke $user->cabangs() (pivot cabang_user eksplisit),
            // padahal mereka SEHARUSNYA bisa switch ke SEMUA cabang aktif tanpa
            // perlu di-assign satu-satu. Ditemukan konkret: user Owner/Admin
            // Pusat dev cuma punya 3/6 pivot cabang_user. Fix: role yang bisa
            // akses semua cabang -> tampilkan SEMUA cabang aktif di switcher,
            // bukan cuma yang di-pivot.
            $userCabangs = $user->canAccessAllBranches()
                ? Cabang::aktif()->orderBy('nama_cabang')->get()
                : $user->cabangs()->aktif()->get();

            view()->share('activeCabang', $activeCabang);
            view()->share('userCabangs', $userCabangs);
            view()->share('authUser', $user);
        }

        return $next($request);
    }
}
