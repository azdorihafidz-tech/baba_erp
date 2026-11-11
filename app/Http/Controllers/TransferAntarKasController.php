<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Kas;
use App\Models\TransaksiKeuangan;
use App\Services\TransferAntarKasService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransferAntarKasController extends Controller
{
    public function __construct(private readonly TransferAntarKasService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('transfer_antar_kas.view'), 403);

        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id');

        // Hanya sisi MUTASI-OUT yang ditampilkan per baris (1 baris = 1 transfer),
        // sisi MUTASI-IN pasangannya cukup dilihat via halaman Detail — pola
        // persis SetoranController::index() (hanya SETOR-OUT yang di-list).
        $query = TransaksiKeuangan::with(['cabang', 'kas', 'createdBy'])
            ->where('referensi_type', 'transfer_antar_kas')
            ->whereHas('kategoriDinamis', fn ($q) => $q->where('kode', 'MUTASI-OUT'))
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id');

        if (!$user->canAccessAllBranches()) {
            $cabangId = $activeCabangId ?? $user->defaultCabangId();
            $query->where('cabang_id', $cabangId);
        } elseif ($activeCabangId) {
            $query->where('cabang_id', $activeCabangId);
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_transaksi', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('kas', fn ($k) => $k->where('nama_kas', 'like', "%{$search}%"));
            });
        }

        $transfers = $query->paginate(20)->withQueryString();

        $cabangOptions = $user->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : collect();

        return view('transfer-antar-kas.index', compact('transfers', 'cabangOptions'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('transfer_antar_kas.create'), 403);

        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $cabangId       = $activeCabangId ?? $user->defaultCabangId();

        $kasList = Kas::aktif()->where('cabang_id', $cabangId)->orderBy('nama_kas')->get();

        if ($kasList->count() < 2) {
            return redirect()->route('transfer-antar-kas.index')
                ->with('error', 'Cabang ini minimal harus punya 2 Kas aktif untuk melakukan Transfer Antar Kas.');
        }

        return view('transfer-antar-kas.create', compact('kasList'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('transfer_antar_kas.create'), 403);

        $request->validate([
            'tanggal'       => 'required|date',
            'kas_asal_id'   => 'required|exists:kas,id',
            'kas_tujuan_id' => 'required|exists:kas,id',
            'jumlah'        => 'required|numeric|min:1',
            'keterangan'    => 'nullable|string|max:255',
        ]);

        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $cabangId       = $activeCabangId ?? $user->defaultCabangId();

        $kasAsal = Kas::findOrFail($request->kas_asal_id);
        abort_unless($kasAsal->cabang_id == $cabangId, 403, 'Kas asal bukan milik cabang aktif.');

        try {
            $this->service->buatTransfer(
                kasAsalId: (int) $request->kas_asal_id,
                kasTujuanId: (int) $request->kas_tujuan_id,
                jumlah: (float) $request->jumlah,
                tanggal: $request->tanggal,
                keterangan: $request->keterangan,
                userId: auth()->id(),
            );
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('transfer-antar-kas.index')->with('success', 'Transfer antar kas berhasil dicatat.');
    }

    public function show($transaksi)
    {
        abort_unless(auth()->user()->can('transfer_antar_kas.view'), 403);

        $transaksi = TransaksiKeuangan::with(['cabang', 'kas', 'createdBy'])->findOrFail($transaksi);
        abort_unless($transaksi->referensi_type === 'transfer_antar_kas', 404);

        [$trxKeluar, $trxMasuk] = $this->service->resolvePasangan($transaksi);
        $trxKeluar->loadMissing(['cabang', 'kas', 'createdBy']);
        $trxMasuk->loadMissing(['cabang', 'kas', 'createdBy']);

        return view('transfer-antar-kas.show', compact('trxKeluar', 'trxMasuk'));
    }

    public function destroy($transaksi)
    {
        abort_unless(auth()->user()->can('transfer_antar_kas.delete'), 403);

        $transaksi = TransaksiKeuangan::withTrashed()->findOrFail($transaksi);
        abort_unless($transaksi->referensi_type === 'transfer_antar_kas', 404);

        try {
            $this->service->hapusTransfer($transaksi);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('transfer-antar-kas.index')
            ->with('success', 'Transfer antar kas berhasil dihapus. Saldo kas dikembalikan.');
    }
}
