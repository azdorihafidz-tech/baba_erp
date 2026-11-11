<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\ProdukOnline;
use App\Models\StokMember;
use App\Services\HargaOnlineService;
use App\Services\PesananOnlineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Katalog menu Belanja untuk satu member (dipanggil server aplikasi member, lihat VerifikasiAplikasiMember).
 * Harga sesuai member (khusus akun → jabatan → gudang → tetap); produk tanpa harga yang berlaku tidak ditampilkan.
 * Stok = stok barang di gudang pemiliknya.
 */
class KatalogController extends Controller
{
    public function __invoke(Request $request, HargaOnlineService $harga, PesananOnlineService $pesanan): JsonResponse
    {
        $member = $request->attributes->get('member_akun');
        if (! $member) {
            return response()->json(['ok' => false, 'pesan' => 'Identitas member tidak dikirim.'], 422);
        }
        if (! $member->aktif) {
            return response()->json(['ok' => false, 'pesan' => 'Akun belum aktif.'], 403);
        }
        $sumber = $request->query('sumber') === HargaOnlineService::SUMBER_UPLINE ? HargaOnlineService::SUMBER_UPLINE : HargaOnlineService::SUMBER_GUDANG;
        // Tahap 2c: belanja ke upline = hanya barang yang pernah dipegang upline, stok = stok upline − dipesan
        $upline = null;
        $stokUpline = collect();
        if ($sumber === HargaOnlineService::SUMBER_UPLINE) {
            $upline = $pesanan->upline($member);
            if (! $pesanan->sumberBoleh($member)['upline'] || ! $upline) {
                return response()->json(['ok' => false, 'pesan' => $upline ? 'Akun Anda belum diizinkan belanja ke upline.' : 'Tidak ada upline aktif untuk akun Anda.'], 422);
            }
            $stokUpline = StokMember::where('member_id', $upline->member_id)->pluck('qty', 'item_id');
        }

        $produk = ProdukOnline::with(['item', 'harga'])->where('aktif', true)
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->when($upline, fn ($q) => $q->whereIn('item_id', $stokUpline->keys()))
            ->orderBy('urutan')->orderBy('nama_tampil')->get();

        $data = $produk->map(function (ProdukOnline $p) use ($member, $harga, $pesanan, $sumber, $upline) {
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
                'gudang_id'   => (int) $p->item->cabang_id,
                // stok yang masih bisa dipesan = stok gudang − pesanan yang belum keluar
                'stok'        => $upline ? $pesanan->tersediaUpline($p->item_id, $upline->member_id)
                    : ($p->item->cabang_id ? $pesanan->tersedia($p->item_id, (int) $p->item->cabang_id) : 0),
            ];
        })->filter()->values();

        return response()->json(['ok' => true, 'member' => ['nama' => $member->nama, 'jabatan' => $member->jabatan], 'sumber' => $sumber,
            'upline' => $upline ? ['label' => $upline->label] : null, 'data' => $data]);
    }
}
