<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\ProdukTokoService;
use Illuminate\Http\Request;

/** ERP BABA Tahap 3 — Kasir › Produk Parfum Toko: buat / ubah harga produk kasir botol & isi ulang per ml untuk satu toko. */
class ProdukTokoController extends Controller
{
    public function __construct(private ProdukTokoService $svc)
    {
    }

    private function izin(): void
    {
        abort_unless(auth()->user()->can('master.produk_jual.create'), 403);
    }

    private function toko(Request $request): ?Cabang
    {
        $daftar = $this->svc->daftarToko();
        $id = (int) ($request->input('toko') ?: session('active_cabang_id'));

        return $daftar->firstWhere('id', $id) ?? $daftar->first();
    }

    public function index(Request $request)
    {
        $this->izin();
        $toko = $this->toko($request);

        return view('produk-toko.index', ['daftarToko' => $this->svc->daftarToko(), 'toko' => $toko, 'rencana' => $toko ? $this->svc->rencana($toko) : null]);
    }

    public function simpan(Request $request)
    {
        $this->izin();
        $toko = $this->toko($request);
        abort_unless($toko && (int) $request->input('toko') === $toko->id, 404);
        try {
            $h = $this->svc->simpan($toko, (array) $request->input('produk', []), auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', e($e->getMessage()));
        }
        activity()->performedOn($toko)->log("Produk parfum toko {$toko->nama_cabang}: {$h['dibuat']} dibuat, {$h['diubah']} diubah, {$h['dimatikan']} dimatikan");

        return redirect()->route('produk-toko.index', ['toko' => $toko->id])
            ->with('success', "Produk kasir {$toko->nama_cabang} disimpan: {$h['dibuat']} baru, {$h['diubah']} diubah, {$h['dimatikan']} dimatikan.");
    }
}
