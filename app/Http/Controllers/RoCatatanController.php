<?php

namespace App\Http\Controllers;

use App\Models\MemberAkun;
use App\Models\RoCatatan;
use App\Services\RoService;
use Illuminate\Http\Request;

/** ERP BABA Tahap 2e — Toko Online › Catatan RO: pantau RO belanja online yang dikirim ke aplikasi member + kirim ulang. Izin toko_online.kelola. */
class RoCatatanController extends Controller
{
    private function izin(): void
    {
        abort_unless(auth()->user()->can('toko_online.kelola'), 403);
    }

    public function index(Request $request, RoService $ro)
    {
        $this->izin();
        $status = array_key_exists($request->query('status'), RoCatatan::STATUS) ? $request->query('status') : null;
        $cari = trim((string) $request->query('cari', ''));
        $catatan = RoCatatan::with(['member', 'pesanan', 'retur'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('ref', 'like', "%{$cari}%")
                ->orWhereIn('member_id', MemberAkun::where('nama', 'like', "%{$cari}%")->orWhere('no_id', 'like', "{$cari}%")->pluck('member_id'))))
            ->orderByDesc('id')->paginate(30)->withQueryString();
        $jumlah = RoCatatan::selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return view('toko-online.ro.index', ['catatan' => $catatan, 'status' => $status, 'cari' => $cari, 'jumlah' => $jumlah, 'mode' => $ro->mode()]);
    }

    public function kirimUlang(RoService $ro)
    {
        $this->izin();
        $h = $ro->kirimTertunda();

        return back()->with($h['gagal'] ? 'error' : 'success', "RO terkirim: {$h['terkirim']}" . ($h['gagal'] ? ", masih gagal: {$h['gagal']} (cek sambungan ke aplikasi member)." : '.'));
    }
}
