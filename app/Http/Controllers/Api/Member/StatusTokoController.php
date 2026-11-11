<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\PesananOnline;
use App\Models\ReturOnline;
use App\Models\TokoOnlinePengaturan;
use App\Services\PesananOnlineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ERP BABA Tahap 2f — status toko online untuk satu akun (saklar Owner: tutup / uji / buka) + angka ringkas menu:
 * perlu_bayar (Pesanan Saya), masuk_perlu & retur_diajukan (Toko Saya). Menu tetap bisa dipakai untuk pesanan
 * yang sudah berjalan walau toko ditutup (punya_riwayat).
 */
class StatusTokoController extends Controller
{
    public function __invoke(Request $request, PesananOnlineService $svc): JsonResponse
    {
        $id = $request->attributes->get('member_id');
        $buka = TokoOnlinePengaturan::ambil()->bukaUntuk($id);
        if (! $id) {
            return response()->json(['ok' => true, 'buka' => $buka]);
        }
        $milik = PesananOnline::where('member_id', $id);
        $perluBayar = (clone $milik)->where('status_bayar', 'belum')->whereRaw('total - nilai_retur > 0')
            ->where(fn ($w) => $w->where('status', 'menunggu_bayar')->orWhere(fn ($t) => $t->where('cara_bayar', 'tagihan')->whereIn('status', ['disiapkan', 'dikirim', 'siap_diambil', 'diterima'])))
            ->count();
        $masuk = PesananOnline::where('sumber', 'upline')->where('sumber_member_id', $id);

        return response()->json(['ok' => true, 'buka' => $buka,
            'punya_riwayat' => (clone $milik)->exists() || (clone $masuk)->exists(),
            'jumlah' => [
                'perlu_bayar' => $perluBayar,
                'masuk_perlu' => $svc->saringPerluTindakan(clone $masuk)->count(),
                'retur_diajukan' => ReturOnline::where('sumber', 'upline')->where('sumber_member_id', $id)->where('status', 'diajukan')->count(),
            ]]);
    }
}
