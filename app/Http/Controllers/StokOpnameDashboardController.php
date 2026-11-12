<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\DashboardStokOpnameService;
use App\Services\StokOpnameService;
use Illuminate\Http\Request;

/** ERP BABA Tahap 7e — Stok & Gudang › Dashboard Stok Opname (izin stok_opname.laporan). Lokasi sama dengan Laporan Kerugian Stok. */
class StokOpnameDashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStokOpnameService $dash)
    {
        abort_unless(auth()->user()->can('stok_opname.laporan'), 403);
        $u = auth()->user();
        $terlihat = $u->canAccessAllBranches() ? Cabang::orderBy('nama_cabang')->get() : $u->cabangs()->orderBy('nama_cabang')->get();
        $pilih = (string) $request->query('lokasi', '');
        if (ctype_digit($pilih) && $terlihat->contains('id', (int) $pilih)) {
            $ids = [(int) $pilih];
        } elseif ($pilih === 'semua') {
            $ids = $terlihat->pluck('id')->all();
        } else {
            $pilih = '';
            $baba = $terlihat->filter(fn ($c) => ! StokOpnameService::lokasiMitra($c));
            $ids = ($baba->isNotEmpty() ? $baba : $terlihat)->pluck('id')->all();
        }

        return view('stok-opname.dashboard', ['d' => $dash->data($u, $ids), 'terlihat' => $terlihat, 'pilih' => $pilih,
            'masaPengingat' => DashboardStokOpnameService::masaPengingat()]);
    }
}
