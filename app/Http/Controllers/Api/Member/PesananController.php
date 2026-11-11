<?php

namespace App\Http\Controllers\Api\Member;

use App\Exceptions\PesananException;
use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\ProdukOnline;
use App\Models\TokoOnlinePengaturan;
use App\Services\PesananOnlineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ERP BABA Tahap 2b — pesanan member (dipanggil server aplikasi member atas nama member yang login).
 * Semua aturan di PesananOnlineService; penolakan aturan → 422 {ok:false, pesan}.
 */
class PesananController extends Controller
{
    public function __construct(private PesananOnlineService $svc)
    {
    }

    private function member(Request $request): MemberAkun
    {
        $m = $request->attributes->get('member_akun');
        if (! $m) {
            throw new PesananException('Identitas member tidak dikirim.');
        }

        return $m;
    }

    private function jalankan(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (PesananException $e) {
            return response()->json(['ok' => false, 'pesan' => $e->getMessage()], 422);
        }
    }

    private function cari(Request $request, string $nomor): PesananOnline
    {
        $p = PesananOnline::where('nomor', $nomor)->where('member_id', $this->member($request)->member_id)->first();
        if (! $p) {
            throw new PesananException('Pesanan tidak ditemukan.');
        }

        return $p;
    }

    /** Pilihan checkout: gudang (alamat ambil + rekening), tagihan (boleh / sisa batas), batas waktu bayar. */
    public function opsi(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $m = $this->member($request);
            $this->svc->pastikanBolehBelanja($m);
            $cabangIds = ProdukOnline::where('aktif', true)->join('items', 'items.id', '=', 'produk_online.item_id')->distinct()->pluck('items.cabang_id');
            $gudang = Cabang::whereIn('id', $cabangIds)->where('is_active', true)->orderBy('nama_cabang')->get()->map(fn (Cabang $c) => [
                'id' => $c->id, 'nama' => $c->nama_cabang, 'alamat' => $c->alamat, 'telepon' => $c->telepon, 'rekening' => $this->svc->rekening($c->id),
            ])->values();

            $up = $this->svc->upline($m);
            $boleh = $this->svc->sumberBoleh($m);

            return response()->json(['ok' => true, 'gudang' => $gudang, 'tagihan' => $this->svc->infoTagihan($m),
                'sumber' => $boleh,
                'upline' => $boleh['upline'] && $up ? ['id' => $up->member_id, 'label' => $up->label, 'jabatan' => $up->jabatan, 'rekening' => $this->svc->rekeningMember($up->member_id)] : null,
                'jam_batas_bayar' => TokoOnlinePengaturan::ambil()->jam_batas_bayar]);
        });
    }

    public function index(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $m = $this->member($request);
            $kelompok = $request->query('kelompok');
            $q = PesananOnline::with(['item', 'cabang'])->where('member_id', $m->member_id)
                ->when($kelompok === 'berjalan', fn ($q) => $q->whereIn('status', PesananOnline::BERJALAN))
                ->when($kelompok === 'selesai', fn ($q) => $q->where('status', 'diterima'))
                ->when($kelompok === 'batal', fn ($q) => $q->where('status', 'batal'))
                ->orderByDesc('id');
            $hal = $q->paginate(min(50, max(5, (int) $request->query('limit', 20))));
            $semua = PesananOnline::where('member_id', $m->member_id);

            return response()->json(['ok' => true,
                'data' => collect($hal->items())->map(fn ($p) => $this->svc->untukMember($p))->values(),
                'halaman' => $hal->currentPage(), 'jumlah_halaman' => $hal->lastPage(), 'total' => $hal->total(),
                'jumlah' => [
                    'berjalan' => (clone $semua)->whereIn('status', PesananOnline::BERJALAN)->count(),
                    'perlu_bayar' => (clone $semua)->where('status_bayar', 'belum')->where(fn ($w) => $w->where('status', 'menunggu_bayar')
                        ->orWhere(fn ($t) => $t->where('cara_bayar', 'tagihan')->whereIn('status', ['disiapkan', 'dikirim', 'siap_diambil', 'diterima'])))->count(),
                ],
                'tagihan' => $this->svc->infoTagihan($m)]);
        });
    }

    public function show(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(fn () => response()->json(['ok' => true, 'data' => $this->svc->untukMember($this->cari($request, $nomor), true)]));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $p = $this->svc->buat($this->member($request), $request->only(['sumber', 'cara_ambil', 'cara_bayar', 'kirim_nama', 'kirim_hp', 'kirim_alamat', 'catatan', 'item']));

            return response()->json(['ok' => true, 'pesan' => "Pesanan {$p->nomor} dibuat.", 'data' => $this->svc->untukMember($p->fresh(), true)], 201);
        });
    }

    /** Body JSON: {file: base64, nama?: string}. */
    public function bukti(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor) {
            $p = $this->cari($request, $nomor);
            $isi = (string) $request->input('file', '');
            $biner = base64_decode(preg_replace('#^data:[^,]*,#', '', $isi), true);
            if ($biner === false) {
                throw new PesananException('File bukti tidak terbaca. Pilih ulang filenya.');
            }
            $this->svc->unggahBukti($p, $this->member($request), $biner);

            return response()->json(['ok' => true, 'pesan' => 'Bukti transfer terkirim. Admin gudang akan mengeceknya.', 'data' => $this->svc->untukMember($p->fresh(), true)]);
        });
    }

    public function batal(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor) {
            $p = $this->svc->batalMember($this->cari($request, $nomor), $this->member($request), $request->input('alasan'));

            return response()->json(['ok' => true, 'pesan' => "Pesanan {$p->nomor} dibatalkan.", 'data' => $this->svc->untukMember($p->fresh(), true)]);
        });
    }

    public function terima(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor) {
            $p = $this->svc->terimaMember($this->cari($request, $nomor), $this->member($request));

            return response()->json(['ok' => true, 'pesan' => 'Terima kasih, pesanan sudah diterima.', 'data' => $this->svc->untukMember($p->fresh(), true)]);
        });
    }
}
