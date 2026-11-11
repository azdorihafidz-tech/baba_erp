<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Item;
use App\Services\AmbilProdukCabangService;
use App\Support\CabangAktif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menu Produk Jual → "Ambil dari Cabang Lain" (CLAUDE.md 4.42): pilih cabang
 * sumber & produk → layar pemeriksaan bahan (kode + nama) → salin jadi milik
 * cabang tujuan. Dipakai juga oleh centang "Buat juga di cabang…" di form
 * Tambah Produk Jual (langsung ke layar pemeriksaan).
 */
class AmbilProdukCabangController extends Controller
{
    public function __construct(private AmbilProdukCabangService $service)
    {
    }

    /** Langkah 1 — pilih cabang sumber & produk. */
    public function index(Request $request)
    {
        $pilihanTujuan = CabangAktif::pilihan();
        $tujuanId = CabangAktif::id() ?? ($request->filled('tujuan') ? (int) $request->input('tujuan') : null);
        $tujuan = $tujuanId ? $pilihanTujuan->firstWhere('id', $tujuanId) : null;
        abort_if($tujuanId && ! $tujuan, 403);

        $cabangSumber = Cabang::aktif()->when($tujuan, fn ($q) => $q->where('id', '!=', $tujuan->id))->orderBy('nama_cabang')->get();
        $sumberId = $request->filled('sumber') ? (int) $request->input('sumber') : null;
        $sumber = $sumberId ? $cabangSumber->firstWhere('id', $sumberId) : null;

        $produk = collect();
        $kodeTujuan = collect();
        if ($sumber && $tujuan) {
            $produk = $this->service->produkSumber($sumber->id);
            if ($request->filled('search')) {
                $kata = $this->service->kunci($request->input('search'));
                $produk = $produk->filter(fn ($p) => str_contains($this->service->kunci($p->nama_item), $kata) || str_contains($this->service->kunci($p->kode_item), $kata))->values();
            }
            $kodeTujuan = Item::withTrashed()->where('cabang_id', $tujuan->id)->pluck('kode_item')->map(fn ($k) => $this->service->kunci($k))->flip();
        }

        return view('master.produk-jual.ambil', compact('pilihanTujuan', 'tujuan', 'cabangSumber', 'sumber', 'produk', 'kodeTujuan'));
    }

    /** Langkah 2 — layar pemeriksaan (1 atau beberapa cabang tujuan). */
    public function periksa(Request $request)
    {
        [$sumber, $produkIds, $tujuanList] = $this->bacaPermintaan($request);
        if (! $sumber || ! $produkIds || $tujuanList->isEmpty()) {
            return redirect()->route('master.produk-jual.ambil.index')
                ->with('warning', 'Pilih cabang sumber dan minimal 1 produk dulu.');
        }

        $rencana = $tujuanList->map(fn ($t) => $this->service->rencana($sumber->id, $produkIds, $t->id));

        return view('master.produk-jual.ambil-periksa', compact('sumber', 'produkIds', 'rencana'));
    }

    /** Langkah 3 — salin sesuai keputusan. */
    public function simpan(Request $request)
    {
        [$sumber, $produkIds, $tujuanList] = $this->bacaPermintaan($request);
        abort_if(! $sumber || ! $produkIds || $tujuanList->isEmpty(), 422);

        try {
            $hasil = DB::transaction(fn () => $tujuanList->mapWithKeys(fn ($t) => [$t->nama_cabang => $this->service->jalankan(
                $sumber->id, $produkIds, $t->id,
                (array) $request->input("salin.{$t->id}", []),
                (array) $request->input("bahan.{$t->id}", []),
                (array) $request->input("bumbu.{$t->id}", []),
            )]));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $pesan = $hasil->map(function ($h, $nama) {
            $bagian = [count($h['produk']) . ' produk disalin'];
            if ($h['bahan_baru']) $bagian[] = count($h['bahan_baru']) . ' bahan baru (stok 0)';
            if ($h['bumbu_baru']) $bagian[] = count($h['bumbu_baru']) . ' Bumbu Pusat baru';
            if ($h['dilewati']) $bagian[] = count($h['dilewati']) . ' dilewati';

            return "<strong>" . e($nama) . "</strong>: " . implode(', ', $bagian);
        })->implode('<br>');

        return redirect()->route('master.produk-jual.index')
            ->with(collect($hasil)->sum(fn ($h) => count($h['produk'])) > 0 ? 'success' : 'warning', $pesan);
    }

    /**
     * Baca & validasi sumber/produk/tujuan dari request.
     * Tujuan: cabang aktif (mode 1 cabang) atau tujuan[] yang boleh diakses user.
     *
     * @return array{0: ?Cabang, 1: array<int>, 2: \Illuminate\Support\Collection<int, Cabang>}
     */
    private function bacaPermintaan(Request $request): array
    {
        $sumber = $request->filled('sumber') ? Cabang::aktif()->find((int) $request->input('sumber')) : null;
        $produkIds = collect((array) $request->input('produk', []))->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        $pilihan = CabangAktif::pilihan();
        $tujuanIds = collect((array) $request->input('tujuan', []))->map(fn ($id) => (int) $id)->filter()->unique();
        if ($tujuanIds->isEmpty() && CabangAktif::id()) {
            $tujuanIds = collect([CabangAktif::id()]);
        }
        foreach ($tujuanIds as $id) {
            abort_unless($pilihan->contains('id', $id) && CabangAktif::bolehAkses($id), 403);
        }
        $tujuanList = $pilihan->whereIn('id', $tujuanIds->all())
            ->when($sumber, fn ($c) => $c->where('id', '!=', $sumber->id))->values();

        return [$sumber, $produkIds, $tujuanList];
    }
}
