<?php

namespace App\Http\Controllers\Api\Member;

use App\Exceptions\PesananException;
use App\Http\Controllers\Controller;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\PesananOnlineBayar;
use App\Models\StokMember;
use App\Models\StokMemberMutasi;
use App\Models\TokoOnlineRekening;
use App\Services\PesananOnlineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ERP BABA Tahap 2c — Toko Saya member (dipanggil server aplikasi member): pesanan masuk dari downline
 * (upline memproses seperti gudang), stok saya, rekening saya. Aturan di PesananOnlineService.
 */
class TokoSayaController extends Controller
{
    public function __construct(private PesananOnlineService $svc)
    {
    }

    private function saya(Request $request): MemberAkun
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

    /** Pesanan masuk milik saya sebagai penjual (upline). */
    private function masuk(Request $request, string $nomor): PesananOnline
    {
        $p = PesananOnline::where('nomor', $nomor)->where('sumber', 'upline')->where('sumber_member_id', $this->saya($request)->member_id)->first();
        if (! $p) {
            throw new PesananException('Pesanan tidak ditemukan.');
        }

        return $p;
    }

    private function hasil(PesananOnline $p, string $pesan): JsonResponse
    {
        return response()->json(['ok' => true, 'pesan' => $pesan, 'data' => $this->svc->untukPenjual($p->fresh())]);
    }

    public function masukIndex(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $m = $this->saya($request);
            $kel = $request->query('kelompok');
            $dasar = PesananOnline::where('sumber', 'upline')->where('sumber_member_id', $m->member_id);
            $hal = (clone $dasar)->with(['item', 'member'])
                ->when($kel === 'perlu', fn ($q) => $this->svc->saringPerluTindakan($q))
                ->when($kel === 'berjalan', fn ($q) => $q->whereIn('status', PesananOnline::BERJALAN))
                ->when($kel === 'selesai', fn ($q) => $q->where('status', 'diterima'))
                ->when($kel === 'batal', fn ($q) => $q->where('status', 'batal'))
                ->orderByDesc('id')->paginate(20);

            return response()->json(['ok' => true,
                'data' => collect($hal->items())->map(fn ($p) => $this->svc->untukPenjual($p, false))->values(),
                'halaman' => $hal->currentPage(), 'jumlah_halaman' => $hal->lastPage(),
                'jumlah' => ['perlu' => $this->svc->saringPerluTindakan(clone $dasar)->count(), 'berjalan' => (clone $dasar)->whereIn('status', PesananOnline::BERJALAN)->count()]]);
        });
    }

    public function masukRinci(Request $request, string $nomor): JsonResponse
    {
        return $this->jalankan(fn () => response()->json(['ok' => true, 'data' => $this->svc->untukPenjual($this->masuk($request, $nomor))]));
    }

    public function masukAksi(Request $request, string $nomor, string $aksi): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor, $aksi) {
            $p = $this->masuk($request, $nomor);
            $saya = $this->saya($request);
            switch ($aksi) {
                case 'ongkir':
                    $teks = trim((string) $request->input('ongkir', ''));
                    if ($teks === '') {
                        throw new PesananException('Isi ongkir (0 bila gratis).');
                    }
                    $this->svc->aturOngkir($p, (float) str_replace(['.', ','], ['', '.'], $teks), $saya);

                    return $this->hasil($p, 'Ongkir disimpan.');
                case 'keluarkan':
                    $this->svc->keluarkan($p, $saya, $request->input('kurir'), $request->input('nomor_resi'));

                    return $this->hasil($p, $p->cara_ambil === 'kirim' ? 'Pesanan dikirim; stok Anda dikurangi.' : 'Pesanan siap diambil; stok Anda dikurangi.');
                case 'sudah-diambil':
                    $this->svc->sudahDiambil($p, $saya);

                    return $this->hasil($p, 'Pesanan selesai.');
                case 'tunai':
                    $this->svc->terimaTunai($p, $saya);

                    return $this->hasil($p, 'Pembayaran tunai dicatat lunas.');
                case 'batal':
                    $this->svc->batalAdmin($p, (string) $request->input('alasan', ''), $saya);

                    return $this->hasil($p, 'Pesanan dibatalkan.');
            }
            throw new PesananException('Aksi tidak dikenal.');
        });
    }

    public function masukBukti(Request $request, string $nomor, int $id, string $aksi): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor, $id, $aksi) {
            $p = $this->masuk($request, $nomor);
            $b = PesananOnlineBayar::where('pesanan_online_id', $p->id)->find($id) ?? throw new PesananException('Bukti tidak ditemukan.');
            if ($aksi === 'terima') {
                $this->svc->terimaBukti($b, $this->saya($request));

                return $this->hasil($p, 'Pembayaran diterima (lunas).');
            }
            $this->svc->tolakBukti($b, (string) $request->input('alasan', ''), $this->saya($request));

            return $this->hasil($p, 'Bukti ditolak; pembeli diminta mengunggah ulang.');
        });
    }

    public function masukBuktiFile(Request $request, string $nomor, int $id): JsonResponse
    {
        return $this->jalankan(function () use ($request, $nomor, $id) {
            $p = $this->masuk($request, $nomor);
            $b = PesananOnlineBayar::where('pesanan_online_id', $p->id)->find($id);
            if (! $b || ! Storage::disk('local')->exists($b->file)) {
                throw new PesananException('File bukti tidak ditemukan.');
            }
            $isi = Storage::disk('local')->get($b->file);

            return response()->json(['ok' => true, 'mime' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($isi), 'isi' => base64_encode($isi)]);
        });
    }

    public function stok(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $m = $this->saya($request);
            $stok = StokMember::with('item')->where('member_id', $m->member_id)->orderByDesc('qty')->get();
            $mutasi = StokMemberMutasi::with('item')->where('member_id', $m->member_id)->orderByDesc('id')->limit(50)->get();

            return response()->json(['ok' => true,
                'data' => $stok->map(fn ($s) => ['item_id' => $s->item_id, 'nama' => $s->item?->nama_item, 'satuan' => $s->item?->satuan,
                    'qty' => $s->qty, 'dipesan' => $this->svc->dipesanMember($s->item_id, $m->member_id), 'nilai_rata' => (float) $s->nilai_rata])->values(),
                'mutasi' => $mutasi->map(fn ($x) => ['tanggal' => $x->created_at?->toIso8601String(), 'nama' => $x->item?->nama_item, 'qty' => $x->qty,
                    'sisa' => $x->sisa, 'jenis' => $x->jenis, 'jenis_label' => StokMemberMutasi::JENIS[$x->jenis] ?? $x->jenis, 'catatan' => $x->catatan])->values()]);
        });
    }

    public function rekening(Request $request): JsonResponse
    {
        return $this->jalankan(fn () => response()->json(['ok' => true, 'data' => TokoOnlineRekening::where('member_id', $this->saya($request)->member_id)
            ->orderByDesc('aktif')->orderBy('id')->get(['id', 'bank', 'nomor', 'atas_nama', 'aktif'])]));
    }

    public function rekeningSimpan(Request $request): JsonResponse
    {
        return $this->jalankan(function () use ($request) {
            $m = $this->saya($request);
            $bank = trim((string) $request->input('bank', ''));
            $nomor = trim((string) $request->input('nomor', ''));
            $an = trim((string) $request->input('atas_nama', ''));
            if ($bank === '' || $nomor === '' || $an === '') {
                throw new PesananException('Isi bank, nomor rekening, dan atas nama.');
            }
            if (! preg_match('/^[0-9 .\-]{4,40}$/', $nomor) || mb_strlen($bank) > 50 || mb_strlen($an) > 100) {
                throw new PesananException('Nomor rekening hanya angka (4–40 digit).');
            }
            if (TokoOnlineRekening::where('member_id', $m->member_id)->count() >= 5) {
                throw new PesananException('Maksimal 5 rekening. Sembunyikan / pakai rekening yang ada.');
            }
            TokoOnlineRekening::create(['member_id' => $m->member_id, 'cabang_id' => null, 'bank' => $bank, 'nomor' => $nomor, 'atas_nama' => $an, 'aktif' => true]);

            return $this->rekening($request);
        });
    }

    public function rekeningAktif(Request $request, int $id): JsonResponse
    {
        return $this->jalankan(function () use ($request, $id) {
            $r = TokoOnlineRekening::where('member_id', $this->saya($request)->member_id)->find($id) ?? throw new PesananException('Rekening tidak ditemukan.');
            $r->update(['aktif' => ! $r->aktif]);

            return $this->rekening($request);
        });
    }
}
