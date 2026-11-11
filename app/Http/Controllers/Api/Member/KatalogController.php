<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\ProdukOnline;
use App\Services\HargaOnlineService;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Katalog menu Belanja untuk satu member (dipanggil server aplikasi member, lihat VerifikasiAplikasiMember).
 * Harga sesuai member (khusus akun → jabatan → gudang → tetap); produk tanpa harga yang berlaku tidak ditampilkan.
 * Stok = stok barang di gudang pemiliknya.
 */
class KatalogController extends Controller
{
    public function __invoke(Request $request, HargaOnlineService $harga, StokService $stok): JsonResponse
    {
        $member = $request->attributes->get('member_akun');
        if (! $member) {
            return response()->json(['ok' => false, 'pesan' => 'Identitas member tidak dikirim.'], 422);
        }
        if (! $member->aktif) {
            return response()->json(['ok' => false, 'pesan' => 'Akun belum aktif.'], 403);
        }
        $sumber = $request->query('sumber') === HargaOnlineService::SUMBER_UPLINE ? HargaOnlineService::SUMBER_UPLINE : HargaOnlineService::SUMBER_GUDANG;

        $produk = ProdukOnline::with(['item', 'harga'])->where('aktif', true)
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->orderBy('urutan')->orderBy('nama_tampil')->get();

        $data = $produk->map(function (ProdukOnline $p) use ($member, $harga, $stok, $sumber) {
            $h = $harga->untuk($p, $member, $sumber);
            if (! $h) {
                return null;
            }

            return [
                'id'          => $p->id,
                'nama'        => $p->nama_tampil,
                'kategori'    => $p->kategori,
                'deskripsi'   => $p->deskripsi,
                'foto'        => $p->fotoUrl(),
                'satuan'      => $p->item->satuan,
                'harga'       => $h['harga'],
                'dasar'       => $h['dasar'],
                'label_harga' => HargaOnlineService::LABEL[$h['dasar']],
                'stok'        => $p->item->cabang_id ? max(0, floor($stok->getStok($p->item_id, (int) $p->item->cabang_id))) : 0,
            ];
        })->filter()->values();

        return response()->json(['ok' => true, 'member' => ['nama' => $member->nama, 'jabatan' => $member->jabatan], 'data' => $data]);
    }
}
