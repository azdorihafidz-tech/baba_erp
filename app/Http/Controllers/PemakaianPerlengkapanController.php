<?php

namespace App\Http\Controllers;

use App\Http\Requests\PemakaianPerlengkapanRequest;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\PemakaianPerlengkapan;
use App\Services\PemakaianPerlengkapanService;
use Illuminate\Http\Request;

/**
 * Fase 5 — Modul Perlengkapan (Rule #66). Controller BARU, menu terpisah
 * dari Adjustment Stok existing (StokController TIDAK disentuh).
 */
class PemakaianPerlengkapanController extends Controller
{
    public function __construct(private PemakaianPerlengkapanService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('pemakaian_perlengkapan.view'), 403);

        $user = auth()->user();
        $lokasiIds = $user->canAccessAllBranches()
            ? Cabang::aktif()->pluck('id')
            : $user->cabangs()->aktif()->pluck('cabangs.id');

        $query = PemakaianPerlengkapan::with(['item', 'cabang', 'createdBy'])
            ->whereIn('cabang_id', $lokasiIds)
            ->latest('tanggal_pemakaian')
            ->latest('id');

        if ($request->filled('search')) {
            $query->whereHas('item', fn($q) => $q->where('nama_item', 'like', '%' . $request->search . '%'));
        }
        if ($request->filled('dari')) {
            $query->whereDate('tanggal_pemakaian', '>=', $request->dari);
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tanggal_pemakaian', '<=', $request->sampai);
        }

        $pemakaians = $query->paginate(20)->withQueryString();

        return view('pemakaian-perlengkapan.index', compact('pemakaians'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('pemakaian_perlengkapan.create'), 403);

        $user = auth()->user();
        $items = Item::perlengkapan()->where('track_stok', true)->aktif()->milikCabang(\App\Support\CabangAktif::id())->with('cabang')->orderBy('nama_item')->get();
        $lokasiList = $user->canAccessAllBranches()
            ? Cabang::aktif()->get()
            : $user->cabangs()->aktif()->get();

        return view('pemakaian-perlengkapan.create', compact('items', 'lokasiList'));
    }

    public function store(PemakaianPerlengkapanRequest $request)
    {
        // 4.39 — perlengkapan harus milik cabang tempat pemakaian
        $item = \App\Models\Item::find($request->input('item_id'));
        if ($item && $item->cabang_id !== null && (int) $item->cabang_id !== (int) $request->input('cabang_id')) {
            return back()->with('error', "Barang {$item->nama_item} bukan milik cabang ini.")->withInput();
        }

        try {
            $this->service->simpan($request->validated());

            return redirect()->route('pemakaian-perlengkapan.index')
                ->with('success', 'Pemakaian perlengkapan berhasil dicatat.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(PemakaianPerlengkapan $pemakaianPerlengkapan)
    {
        abort_unless(auth()->user()->can('pemakaian_perlengkapan.view'), 403);

        $pemakaianPerlengkapan->load(['item', 'cabang', 'createdBy']);

        return view('pemakaian-perlengkapan.show', ['pemakaian' => $pemakaianPerlengkapan]);
    }
}
