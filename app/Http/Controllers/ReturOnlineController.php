<?php

namespace App\Http\Controllers;

use App\Exceptions\PesananException;
use App\Models\ReturOnline;
use App\Services\ProduksiService;
use App\Services\ReturOnlineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ERP BABA Tahap 2d — Toko Online › Retur Online: gudang memutuskan retur pesanan gudang (setujui = barang sudah kembali).
 * Retur pesanan ke upline hanya dipantau (diputuskan upline di aplikasi member). Izin toko_online.pesanan.
 */
class ReturOnlineController extends Controller
{
    public const TAB = ['diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'semua' => 'Semua', 'antar' => 'Antar member'];

    public function __construct(private ReturOnlineService $svc)
    {
    }

    private function izin(): void
    {
        abort_unless(auth()->user()->can('toko_online.pesanan'), 403);
    }

    private function lihatSemua(): bool
    {
        return (bool) auth()->user()->canAccessAllBranches();
    }

    private function pastikanBoleh(ReturOnline $r): void
    {
        abort_unless($r->sumber === 'upline' ? $this->lihatSemua() : ProduksiService::gudangUntuk()->pluck('id')->contains($r->sumber_cabang_id), 404);
    }

    public function index(Request $request)
    {
        $this->izin();
        $tab = array_key_exists($request->query('tab'), self::TAB) ? $request->query('tab') : 'diajukan';
        if ($tab === 'antar' && ! $this->lihatSemua()) {
            $tab = 'diajukan';
        }
        $dasar = ReturOnline::where('sumber', 'gudang')->whereIn('sumber_cabang_id', ProduksiService::gudangUntuk()->pluck('id'));
        $antar = ReturOnline::where('sumber', 'upline');
        $q = $tab === 'antar' ? clone $antar : (clone $dasar)->when($tab !== 'semua', fn ($w) => $w->where('status', $tab));
        $retur = $q->with(['item', 'pesanan', 'member'])->orderByDesc('id')->paginate(25)->withQueryString();
        $jumlah = [];
        foreach (array_keys(self::TAB) as $k) {
            if ($k === 'antar') {
                if ($this->lihatSemua()) {
                    $jumlah[$k] = (clone $antar)->where('status', 'diajukan')->count();
                }
                continue;
            }
            $jumlah[$k] = $k === 'semua' ? (clone $dasar)->count() : (clone $dasar)->where('status', $k)->count();
        }

        return view('toko-online.retur.index', compact('retur', 'tab', 'jumlah'));
    }

    public function show(ReturOnline $retur)
    {
        $this->izin();
        $this->pastikanBoleh($retur);
        $retur->load(['item', 'pesanan', 'member', 'cabang']);

        return view('toko-online.retur.show', ['r' => $retur]);
    }

    public function setujui(Request $request, ReturOnline $retur)
    {
        return $this->aksi($retur, fn () => $this->svc->setujui($retur, auth()->user(), $request->input('pengembalian'), $request->input('catatan')), 'Retur disetujui; stok gudang bertambah.');
    }

    public function tolak(Request $request, ReturOnline $retur)
    {
        return $this->aksi($retur, fn () => $this->svc->tolak($retur, auth()->user(), (string) $request->input('alasan')), 'Retur ditolak.');
    }

    private function aksi(ReturOnline $r, callable $fn, string $berhasil)
    {
        $this->izin();
        $this->pastikanBoleh($r);
        try {
            $fn();
        } catch (PesananException $e) {
            return back()->withInput()->with('error', e($e->getMessage())); // layout menampilkan pesan tanpa escape
        }

        return redirect()->route('toko-online.retur.show', $r)->with('success', $berhasil);
    }

    public function foto(ReturOnline $retur)
    {
        $this->izin();
        $this->pastikanBoleh($retur);
        abort_unless($retur->foto && Storage::disk('local')->exists($retur->foto), 404);

        return Storage::disk('local')->response($retur->foto, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
