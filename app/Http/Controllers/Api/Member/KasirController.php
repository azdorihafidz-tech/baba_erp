<?php

namespace App\Http\Controllers\Api\Member;

use App\Exceptions\PesananException;
use App\Http\Controllers\Controller;
use App\Models\MemberAkun;
use App\Models\PenjualanMember;
use App\Services\KasirMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** ERP BABA Tahap 3 — kasir & laporan Toko Saya (dipanggil server aplikasi member atas nama member yang login). */
class KasirController extends Controller
{
    public function __construct(private KasirMemberService $svc)
    {
    }

    private function saya(Request $request): MemberAkun
    {
        return $request->attributes->get('member_akun') ?? throw new PesananException('Identitas member tidak dikirim.');
    }

    private function jalankan(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (PesananException $e) {
            return response()->json(['ok' => false, 'pesan' => $e->getMessage()], $e->getCode() === 404 ? 404 : 422);
        }
    }

    private function milik(Request $request, string $nomor): PenjualanMember
    {
        return PenjualanMember::where('nomor', $nomor)->where('member_id', $this->saya($request)->member_id)->first()
            ?? throw new PesananException('Transaksi tidak ditemukan.', 404);
    }

    public function produk(Request $request): JsonResponse
    {
        return $this->jalankan(fn () => response()->json(['ok' => true, 'data' => $this->svc->produk($this->saya($request))]));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $p = $this->svc->jual($this->saya($request), $request->only(['item', 'bayar', 'diterima', 'pelanggan_nama', 'pelanggan_hp', 'catatan', 'simpan_harga']));

            return response()->json(['ok' => true, 'pesan' => "Penjualan {$p->nomor} tersimpan.", 'data' => $this->svc->struk($p)], 201);
        });
    }

    public function index(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $hal = PenjualanMember::with('item')->where('member_id', $this->saya($request)->member_id)->orderByDesc('id')->paginate(20);

            return response()->json(['ok' => true, 'data' => collect($hal->items())->map(fn ($p) => $this->svc->struk($p))->values(),
                'halaman' => $hal->currentPage(), 'jumlah_halaman' => $hal->lastPage()]);
        });
    }

    public function show(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(fn () => response()->json(['ok' => true, 'data' => $this->svc->struk($this->milik($request, $nomor))]));
    }

    public function batal(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor) {
            $p = $this->svc->batal($this->milik($request, $nomor), $this->saya($request), $request->input('alasan'));

            return response()->json(['ok' => true, 'pesan' => "Transaksi {$p->nomor} dibatalkan; stok kembali.", 'data' => $this->svc->struk($p->fresh())]);
        });
    }

    public function laporan(Request $request): JsonResponse
    {
        return $this->jalankan(fn () => response()->json(['ok' => true] + $this->svc->laporan($this->saya($request),
            in_array($request->query('jenis'), ['produk', 'kategori'], true) ? $request->query('jenis') : 'transaksi', $request->query('dari'), $request->query('sampai'))));
    }
}
