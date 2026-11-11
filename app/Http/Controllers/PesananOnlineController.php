<?php

namespace App\Http\Controllers;

use App\Exceptions\PesananException;
use App\Models\PesananOnline;
use App\Models\PesananOnlineBayar;
use App\Services\PesananOnlineService;
use App\Services\ProduksiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ERP BABA Tahap 2b — Toko Online › Pesanan Online: gudang memproses pesanan member
 * (isi ongkir, cek bukti transfer, kirim / siap diambil, batal). Izin toko_online.pesanan; hanya gudang yang boleh diakses.
 */
class PesananOnlineController extends Controller
{
    public const TAB = [
        'perlu'    => ['Perlu tindakan', ['menunggu_ongkir', 'menunggu_cek', 'disiapkan']],
        'bayar'    => ['Menunggu bayar', ['menunggu_bayar']],
        'jalan'    => ['Dikirim / siap diambil', ['dikirim', 'siap_diambil']],
        'selesai'  => ['Diterima', ['diterima']],
        'batal'    => ['Batal', ['batal']],
        'semua'    => ['Semua', null],
    ];

    public function __construct(private PesananOnlineService $svc)
    {
    }

    private function izin(): void
    {
        abort_unless(auth()->user()->can('toko_online.pesanan'), 403);
    }

    private function gudangIds()
    {
        return ProduksiService::gudangUntuk()->pluck('id');
    }

    private function pastikanBoleh(PesananOnline $p): void
    {
        abort_unless($this->gudangIds()->contains($p->sumber_cabang_id), 404);
    }

    public function index(Request $request)
    {
        $this->izin();
        $tab = array_key_exists($request->query('tab'), self::TAB) ? $request->query('tab') : 'perlu';
        $cari = trim((string) $request->query('cari', ''));
        $dasar = PesananOnline::whereIn('sumber_cabang_id', $this->gudangIds());

        $pesanan = $this->saringTab((clone $dasar)->with(['item', 'member']), $tab)
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('nomor', 'like', "%{$cari}%")
                ->orWhereIn('member_id', \App\Models\MemberAkun::where('nama', 'like', "%{$cari}%")->orWhere('no_id', 'like', "%{$cari}%")->orWhere('no_member', 'like', "%{$cari}%")->pluck('member_id'))))
            ->orderByRaw("FIELD(status,'menunggu_cek','menunggu_ongkir','disiapkan') DESC")->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $jumlah = [];
        foreach (array_keys(self::TAB) as $k) {
            $jumlah[$k] = $this->saringTab(clone $dasar, $k)->count();
        }

        return view('toko-online.pesanan.index', compact('pesanan', 'tab', 'jumlah', 'cari'));
    }

    /** Perlu tindakan = isi ongkir, cek bukti (termasuk bukti pelunasan tagihan), siapkan barang. */
    private function saringTab($q, string $tab)
    {
        $st = self::TAB[$tab][1];
        if ($tab === 'perlu') {
            return $q->where(fn ($w) => $w->whereIn('status', $st)->orWhere(fn ($t) => $t->where('status_bayar', 'menunggu_cek')->where('status', '!=', 'batal')));
        }

        return $st ? $q->whereIn('status', $st) : $q;
    }

    public function show(PesananOnline $pesanan)
    {
        $this->izin();
        $this->pastikanBoleh($pesanan);
        $pesanan->load(['item.barang', 'bayar.pengecek', 'log', 'cabang', 'member']);
        $stok = $pesanan->item->mapWithKeys(fn ($i) => [$i->id => app(\App\Services\StokService::class)->getStok($i->item_id, (int) $pesanan->sumber_cabang_id)]);

        return view('toko-online.pesanan.show', ['p' => $pesanan, 'stok' => $stok, 'rekening' => $this->svc->rekening((int) $pesanan->sumber_cabang_id)]);
    }

    private function aksi(PesananOnline $p, callable $fn, string $berhasil)
    {
        $this->izin();
        $this->pastikanBoleh($p);
        try {
            $fn();
        } catch (PesananException $e) {
            return back()->withInput()->with('error', e($e->getMessage())); // layout menampilkan pesan tanpa escape
        }

        return redirect()->route('toko-online.pesanan.show', $p)->with('success', $berhasil);
    }

    public function ongkir(Request $request, PesananOnline $pesanan)
    {
        $nilai = (float) str_replace(['.', ','], ['', '.'], (string) $request->input('ongkir', ''));
        if (trim((string) $request->input('ongkir', '')) === '') {
            return back()->with('error', 'Isi ongkir (0 bila gratis).');
        }

        return $this->aksi($pesanan, fn () => $this->svc->aturOngkir($pesanan, $nilai, auth()->user()), 'Ongkir disimpan dan member dikabari.');
    }

    public function terimaBukti(PesananOnline $pesanan, PesananOnlineBayar $bayar)
    {
        abort_unless($bayar->pesanan_online_id === $pesanan->id, 404);

        return $this->aksi($pesanan, fn () => $this->svc->terimaBukti($bayar, auth()->user()), 'Pembayaran diterima (lunas).');
    }

    public function tolakBukti(Request $request, PesananOnline $pesanan, PesananOnlineBayar $bayar)
    {
        abort_unless($bayar->pesanan_online_id === $pesanan->id, 404);

        return $this->aksi($pesanan, fn () => $this->svc->tolakBukti($bayar, (string) $request->input('alasan'), auth()->user()), 'Bukti ditolak; member diminta mengunggah ulang.');
    }

    public function keluarkan(Request $request, PesananOnline $pesanan)
    {
        return $this->aksi($pesanan, fn () => $this->svc->keluarkan($pesanan, auth()->user(), $request->input('kurir'), $request->input('nomor_resi')),
            $pesanan->cara_ambil === 'kirim' ? 'Pesanan dikirim; stok gudang dikurangi.' : 'Pesanan siap diambil; stok gudang dikurangi.');
    }

    public function sudahDiambil(PesananOnline $pesanan)
    {
        return $this->aksi($pesanan, fn () => $this->svc->sudahDiambil($pesanan, auth()->user()), 'Pesanan selesai (sudah diambil).');
    }

    public function batal(Request $request, PesananOnline $pesanan)
    {
        return $this->aksi($pesanan, fn () => $this->svc->batalAdmin($pesanan, (string) $request->input('alasan'), auth()->user()), 'Pesanan dibatalkan.');
    }

    /** File bukti transfer (tidak publik). */
    public function lihatBukti(PesananOnline $pesanan, PesananOnlineBayar $bayar)
    {
        $this->izin();
        $this->pastikanBoleh($pesanan);
        abort_unless($bayar->pesanan_online_id === $pesanan->id && Storage::disk('local')->exists($bayar->file), 404);

        return Storage::disk('local')->response($bayar->file, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
