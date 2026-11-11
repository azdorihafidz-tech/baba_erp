<?php

namespace App\Http\Controllers\Api\Member;

use App\Exceptions\PesananException;
use App\Http\Controllers\Controller;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\ReturOnline;
use App\Services\ReturOnlineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ERP BABA Tahap 2d — retur dari aplikasi member: pembeli (ajukan, daftar, rincian, batal) dan upline penjual
 * (retur masuk, setujui, tolak, foto). Aturan di ReturOnlineService.
 */
class ReturController extends Controller
{
    public function __construct(private ReturOnlineService $svc)
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
            return response()->json(['ok' => false, 'pesan' => $e->getMessage()], 422);
        }
    }

    private function milikSaya(Request $request, string $nomor): ReturOnline
    {
        return ReturOnline::where('nomor', $nomor)->where('member_id', $this->saya($request)->member_id)->first()
            ?? throw new PesananException('Retur tidak ditemukan.');
    }

    private function masukSaya(Request $request, string $nomor): ReturOnline
    {
        return ReturOnline::where('nomor', $nomor)->where('sumber', 'upline')->where('sumber_member_id', $this->saya($request)->member_id)->first()
            ?? throw new PesananException('Retur tidak ditemukan.');
    }

    /** POST pesanan/{nomor}/retur — {alasan, item:[{id, qty}], foto?: base64} */
    public function ajukan(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor) {
            $m = $this->saya($request);
            $p = PesananOnline::where('nomor', $nomor)->where('member_id', $m->member_id)->first() ?? throw new PesananException('Pesanan tidak ditemukan.');
            $foto = null;
            if ($request->filled('foto')) {
                $foto = base64_decode(preg_replace('#^data:[^,]*,#', '', (string) $request->input('foto')), true);
                if ($foto === false) {
                    throw new PesananException('Foto retur tidak terbaca. Pilih ulang fotonya.');
                }
            }
            $r = $this->svc->ajukan($p, $m, ['alasan' => $request->input('alasan'), 'item' => (array) $request->input('item', [])], $foto);

            return response()->json(['ok' => true, 'pesan' => "Retur {$r->nomor} diajukan. Kirim / bawa barangnya ke penjual.", 'data' => $this->svc->untukMember($r)], 201);
        });
    }

    public function index(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $hal = ReturOnline::with(['item', 'pesanan'])->where('member_id', $this->saya($request)->member_id)->orderByDesc('id')->paginate(20);

            return response()->json(['ok' => true, 'data' => collect($hal->items())->map(fn ($r) => $this->svc->untukMember($r))->values(),
                'halaman' => $hal->currentPage(), 'jumlah_halaman' => $hal->lastPage()]);
        });
    }

    public function show(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(fn () => response()->json(['ok' => true, 'data' => $this->svc->untukMember($this->milikSaya($request, $nomor))]));
    }

    public function batal(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor) {
            $r = $this->svc->batalPembeli($this->milikSaya($request, $nomor), $this->saya($request));

            return response()->json(['ok' => true, 'pesan' => "Retur {$r->nomor} dibatalkan.", 'data' => $this->svc->untukMember($r->fresh())]);
        });
    }

    // ===== upline penjual =====

    public function masukIndex(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $m = $this->saya($request);
            $dasar = ReturOnline::where('sumber', 'upline')->where('sumber_member_id', $m->member_id);
            $hal = (clone $dasar)->with(['item', 'pesanan', 'member'])
                ->when($request->query('kelompok') === 'diajukan', fn ($q) => $q->where('status', 'diajukan'))
                ->orderByRaw("status = 'diajukan' DESC")->orderByDesc('id')->paginate(20);

            return response()->json(['ok' => true, 'data' => collect($hal->items())->map(fn ($r) => $this->svc->untukMember($r, 'penjual'))->values(),
                'halaman' => $hal->currentPage(), 'jumlah_halaman' => $hal->lastPage(), 'jumlah' => ['diajukan' => (clone $dasar)->where('status', 'diajukan')->count()]]);
        });
    }

    public function masukShow(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(fn () => response()->json(['ok' => true, 'data' => $this->svc->untukMember($this->masukSaya($request, $nomor), 'penjual')]));
    }

    public function masukPutus(Request $request, string $nomor, string $aksi): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor, $aksi) {
            $r = $this->masukSaya($request, $nomor);
            if ($aksi === 'setujui') {
                $this->svc->setujui($r, $this->saya($request), $request->input('pengembalian'), $request->input('catatan'));
                $pesan = 'Retur disetujui; stok Anda bertambah.';
            } else {
                $this->svc->tolak($r, $this->saya($request), (string) $request->input('alasan', ''));
                $pesan = 'Retur ditolak.';
            }

            return response()->json(['ok' => true, 'pesan' => $pesan, 'data' => $this->svc->untukMember($r->fresh(), 'penjual')]);
        });
    }

    public function masukFoto(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor) {
            $r = $this->masukSaya($request, $nomor);
            if (! $r->foto || ! Storage::disk('local')->exists($r->foto)) {
                throw new PesananException('Retur ini tanpa foto.');
            }
            $isi = Storage::disk('local')->get($r->foto);

            return response()->json(['ok' => true, 'mime' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($isi), 'isi' => base64_encode($isi)]);
        });
    }
}
