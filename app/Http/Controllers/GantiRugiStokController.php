<?php

namespace App\Http\Controllers;

use App\Exceptions\StokOpnameException;
use App\Models\Cabang;
use App\Models\GantiRugiStok;
use App\Models\Kas;
use App\Models\StokOpnameRincian;
use App\Services\GantiRugiStokService;
use Illuminate\Http\Request;

/** ERP BABA Tahap 7d — Stok & Gudang › Ganti Rugi Karyawan (barang hilang di stok opname). Izin stok_opname.ganti_rugi. */
class GantiRugiStokController extends Controller
{
    public const TAB = ['berjalan' => 'Berjalan', 'lunas' => 'Lunas', 'batal' => 'Dibatalkan', 'semua' => 'Semua'];

    public function __construct(private GantiRugiStokService $svc)
    {
    }

    private function lokasiTerlihat()
    {
        $u = auth()->user();

        return $u->canAccessAllBranches() ? Cabang::orderBy('nama_cabang')->get() : $u->cabangs()->orderBy('nama_cabang')->get();
    }

    private function izin(?GantiRugiStok $g = null): void
    {
        abort_unless(auth()->user()->can('stok_opname.ganti_rugi'), 403);
        if ($g) {
            abort_unless($this->lokasiTerlihat()->contains('id', $g->cabang_id), 404);
        }
    }

    public function index(Request $request)
    {
        $this->izin();
        $lokasi = $this->lokasiTerlihat();
        $tab = array_key_exists($request->query('tab'), self::TAB) ? $request->query('tab') : 'berjalan';
        $dasar = GantiRugiStok::whereIn('cabang_id', $lokasi->pluck('id'));
        $jumlah = collect(array_keys(self::TAB))->mapWithKeys(fn ($k) => [$k => $k === 'semua' ? (clone $dasar)->count() : (clone $dasar)->where('status', $k)->count()])->all();
        $daftar = (clone $dasar)->when($tab !== 'semua', fn ($q) => $q->where('status', $tab))
            ->with(['karyawan', 'cabang', 'opname', 'rincian.itemOpname.barang', 'bayar.kas', 'bayar.penggajian'])->orderByDesc('id')->paginate(30)->withQueryString();
        $ringkas = ['dibebankan' => (float) (clone $dasar)->where('status', '!=', 'batal')->sum('nilai'), 'terbayar' => (float) (clone $dasar)->sum('terbayar')];
        $ringkas['tunai'] = (float) \App\Models\GantiRugiStokBayar::whereIn('ganti_rugi_stok_id', (clone $dasar)->pluck('id'))->where('jenis', 'tunai')->sum('jumlah');
        $ringkas['potong_gaji'] = (float) \App\Models\GantiRugiStokBayar::whereIn('ganti_rugi_stok_id', (clone $dasar)->pluck('id'))->where('jenis', 'potong_gaji')->where('status', 'dibayar')->sum('jumlah');
        $ringkas['sisa'] = (float) (clone $dasar)->where('status', 'berjalan')->get()->sum(fn ($g) => $g->sisa);
        $kas = Kas::whereIn('cabang_id', $daftar->pluck('cabang_id')->unique())->where('is_active', true)->orderBy('nama_kas')->get(['id', 'cabang_id', 'nama_kas']);

        return view('stok-opname.ganti-rugi', compact('daftar', 'tab', 'jumlah', 'ringkas', 'kas'));
    }

    /** Bebankan barang hilang (satu rincian opname) ke karyawan. */
    public function bebankan(Request $request, StokOpnameRincian $rincian)
    {
        abort_unless(auth()->user()->can('stok_opname.ganti_rugi'), 403);
        try {
            $g = $this->svc->bebankan(auth()->user(), $rincian, $request->only(['karyawan_id', 'nilai', 'alasan', 'cara', 'cicilan_bulan', 'catatan']));
        } catch (StokOpnameException $e) {
            return back()->with('error', e($e->getMessage()))->withInput();
        }
        $g->load('karyawan');

        return back()->with('success', e("Ganti rugi {$g->nomor} dibebankan ke {$g->karyawan?->nama_lengkap}: " . GantiRugiStok::CARA[$g->cara]
            . ($g->cara === 'potong_gaji' ? " {$g->cicilan_bulan}× mulai {$g->mulai_periode}" : '') . '.'));
    }

    public function bayarTunai(Request $request, GantiRugiStok $gantiRugi)
    {
        $this->izin($gantiRugi);
        try {
            $this->svc->bayarTunai(auth()->user(), $gantiRugi, $request->input('jumlah'), $request->input('kas_id'));
        } catch (StokOpnameException $e) {
            return back()->with('error', e($e->getMessage()));
        }

        return back()->with('success', e("Pembayaran tunai {$gantiRugi->nomor} tercatat."));
    }

    public function batal(Request $request, GantiRugiStok $gantiRugi)
    {
        $this->izin($gantiRugi);
        try {
            $this->svc->batal(auth()->user(), $gantiRugi, (string) $request->input('alasan'));
        } catch (StokOpnameException $e) {
            return back()->with('error', e($e->getMessage()));
        }

        return back()->with('success', e("Ganti rugi {$gantiRugi->nomor} dibatalkan."));
    }
}
