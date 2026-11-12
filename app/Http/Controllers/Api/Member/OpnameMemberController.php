<?php

namespace App\Http\Controllers\Api\Member;

use App\Exceptions\PesananException;
use App\Http\Controllers\Controller;
use App\Models\MemberAkun;
use App\Models\OpnameMember;
use App\Services\OpnameMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** ERP BABA Tahap 7f — Toko Saya › Stok Saya › Stok opname (dipanggil server aplikasi member atas nama member yang login). */
class OpnameMemberController extends Controller
{
    public function __construct(private OpnameMemberService $svc)
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

    private function milik(Request $request, string $nomor): OpnameMember
    {
        return OpnameMember::where('nomor', $nomor)->where('member_id', $this->saya($request)->member_id)->first()
            ?? throw new PesananException('Opname tidak ditemukan.', 404);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $hal = OpnameMember::with(['item', 'member'])->where('member_id', $this->saya($request)->member_id)->orderByDesc('id')->paginate(20);

            return response()->json(['ok' => true, 'data' => collect($hal->items())->map(fn ($o) => $this->svc->data($o))->values(),
                'halaman' => $hal->currentPage(), 'jumlah_halaman' => $hal->lastPage()]);
        });
    }

    public function store(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $foto = $request->filled('foto') ? base64_decode((string) $request->input('foto'), true) : null;
            if ($foto === false) {
                throw new PesananException('Foto tidak terbaca.');
            }
            $o = $this->svc->simpan($this->saya($request), ['item' => (array) $request->input('item', []), 'catatan' => $request->input('catatan')], $foto);

            return response()->json(['ok' => true, 'pesan' => "Stok opname {$o->nomor} tersimpan; stok sudah disesuaikan.", 'data' => $this->svc->data($o)], 201);
        });
    }

    public function show(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(fn () => response()->json(['ok' => true, 'data' => $this->svc->data($this->milik($request, $nomor))]));
    }

    public function foto(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor) {
            $o = $this->milik($request, $nomor);
            if (! $o->foto || ! Storage::disk('local')->exists($o->foto)) {
                throw new PesananException('Foto tidak ada.', 404);
            }
            $isi = Storage::disk('local')->get($o->foto);

            return response()->json(['ok' => true, 'mime' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($isi), 'isi' => base64_encode($isi)]);
        });
    }
}
