<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustmentStokRequest;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Services\StokService;
use Illuminate\Http\Request;

class StokController extends Controller
{
    public function __construct(private StokService $stokService) {}

    /**
     * Tampilkan stok per lokasi aktif
     */
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $user       = auth()->user();
        $activeLokId = session('active_cabang_id');

        // Tentukan lokasi yang ditampilkan
        if ($user->canAccessAllBranches() && !$activeLokId) {
            // Owner mode semua cabang
            $lokasiList = Cabang::aktif()->get();
            $lokasiAktif = null;
        } else {
            $lokasiId   = $activeLokId ?? $user->defaultCabangId();
            $lokasiAktif = Cabang::find($lokasiId);
            $lokasiList  = $lokasiAktif ? collect([$lokasiAktif]) : collect();
        }

        $lokasiIds = $lokasiList->pluck('id');

        // Sprint 4.34 — exclude produk_jual/produk_tambahan (dibuat lewat resep,
        // bukan ditrack stok fisik). Konsisten dgn Dashboard Stok + Laporan Stok.
        $query = Stock::with(['item.category', 'lokasi'])
            ->whereIn('lokasi_id', $lokasiIds)
            ->whereHas('item', fn($q) => $q->whereIn('tipe', \App\Models\Item::TIPE_DAPAT_DIBELI));

        if ($request->filled('search')) {
            $query->whereHas('item', fn($q) => $q->where('nama_item','like','%'.$request->search.'%')
                ->orWhere('kode_item','like','%'.$request->search.'%'));
        }
        if ($request->filled('kategori')) {
            $query->whereHas('item', fn($q) => $q->where('item_category_id', $request->kategori));
        }
        if ($request->filled('tipe')) {
            $query->whereHas('item', fn($q) => $q->where('tipe', $request->tipe));
        }
        if ($request->filled('lokasi') && $user->canAccessAllBranches()) {
            $query->where('lokasi_id', $request->lokasi);
        }
        if ($request->filled('kritis') && $request->kritis === '1') {
            $query->whereColumn('qty','<=','qty_minimum')->where('qty_minimum','>',0);
        }

        $stocks     = $query->orderBy('lokasi_id')->orderByDesc('qty')->paginate(25)->withQueryString();
        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $tipes      = ['bahan_baku'=>'Bahan Baku','kemasan'=>'Kemasan','tambahan_gratis'=>'Tambahan Gratis'];

        $statBelowMin = Stock::whereIn('lokasi_id',$lokasiIds)->whereColumn('qty','<=','qty_minimum')->where('qty_minimum','>',0)->count();
        $statTotal    = Stock::whereIn('lokasi_id',$lokasiIds)->count();
        $statNilai    = Stock::whereIn('lokasi_id',$lokasiIds)
            ->join('items','stocks.item_id','=','items.id')
            ->selectRaw('SUM(stocks.qty * COALESCE(items.harga_beli_terakhir,0)) as total')
            ->value('total') ?? 0;

        // Bug pre-existing ditemukan saat testing Tahap 2.5 (bukan disebabkan
        // restructure ini): view butuh $authUser tapi tidak pernah di-compact
        // -> selalu 500 di halaman ini utk mode Owner "semua cabang". Fix minimal.
        $authUser = $user;
        return view('stok.index', compact('stocks','lokasiList','lokasiAktif','categories','tipes','statBelowMin','statTotal','statNilai','authUser'));
    }

    /**
     * Kartu stok: history pergerakan per item
     */
    public function kartu(Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $items    = Item::aktif()->milikCabang(\App\Support\CabangAktif::id())->with('cabang')->orderBy('nama_item')->get();
        $lokasiList = auth()->user()->canAccessAllBranches()
            ? Cabang::aktif()->get()
            : auth()->user()->cabangs()->aktif()->get();

        $movements = collect();
        $item      = null;
        $lokasi    = null;

        if ($request->filled('item_id') && $request->filled('lokasi_id')) {
            $item   = Item::findOrFail($request->item_id);
            $lokasi = Cabang::findOrFail($request->lokasi_id);

            $query = StockMovement::with(['user','lokasiAsal','lokasiTujuan'])
                ->where('item_id', $request->item_id)
                ->where(function($q) use ($request) {
                    $q->where('lokasi_asal_id', $request->lokasi_id)
                      ->orWhere('lokasi_tujuan_id', $request->lokasi_id);
                });

            if ($request->filled('dari')) $query->whereDate('created_at', '>=', $request->dari);
            if ($request->filled('sampai')) $query->whereDate('created_at', '<=', $request->sampai);

            $movements = $query->orderByDesc('created_at')->paginate(30)->withQueryString();
        }

        $stokSaat = ($item && $lokasi)
            ? $this->stokService->getStok($item->id, $lokasi->id)
            : 0;

        // Minimum per lokasi (stocks.qty_minimum), bukan nilai awal di master barang
        $minimumLokasi = ($item && $lokasi)
            ? (float) (Stock::where('item_id', $item->id)->where('lokasi_id', $lokasi->id)->value('qty_minimum') ?? $item->qty_minimum ?? 0)
            : 0;
        $selectedItem = $item;
        $selectedLokasi = $lokasi;

        return view('stok.kartu', compact('items','lokasiList','movements','item','lokasi','stokSaat','minimumLokasi','selectedItem','selectedLokasi'));
    }

    /**
     * Form adjustment stok (GET)
     */
    public function adjustmentForm(Request $request)
    {
        abort_unless(auth()->user()->can('stok.adjustment'), 403);

        $user       = auth()->user();
        $lokasiList = $user->canAccessAllBranches()
            ? Cabang::aktif()->get()
            : $user->cabangs()->aktif()->get();
        $items = Item::aktif()->milikCabang(\App\Support\CabangAktif::id())->with('cabang')->orderBy('nama_item')->get();

        return view('stok.adjustment', compact('lokasiList', 'items'));
    }

    /**
     * Proses adjustment stok (POST)
     */
    public function adjustmentStore(AdjustmentStokRequest $request)
    {
        try {
            $batchDistribusi = null;
            if ($request->mode_distribusi === 'manual' && $request->has('batch_distribusi')) {
                $batchDistribusi = collect($request->batch_distribusi)
                    ->filter(fn($b) => isset($b['qty']) && (float)$b['qty'] > 0)
                    ->values()
                    ->toArray();
            }

            // Sprint Unit Family (2026-09-27): kalau user input dlm unit_beli,
            // convert qty_fisik ke unit pakai + tambah audit trail ke catatan.
            $itemModel = \App\Models\Item::find((int) $request->item_id);
            // 4.39 — barang harus milik lokasi yang di-adjust
            if ($itemModel && $itemModel->cabang_id !== null && (int) $itemModel->cabang_id !== (int) $request->lokasi_id) {
                return back()->withInput()->with('error', "Barang {$itemModel->nama_item} bukan milik lokasi ini.");
            }
            $qtyFisikRaw = (float) $request->qty_fisik;
            $unitInput = $request->input('unit_input');
            $qtyFisikPakai = $itemModel ? $itemModel->convertToUnitPakai($qtyFisikRaw, $unitInput) : $qtyFisikRaw;
            $catatan = (string) ($request->catatan ?? '');
            if ($itemModel && $itemModel->hasUnitBeli() && $unitInput
                && strcasecmp($unitInput, (string) $itemModel->unit_beli) === 0) {
                $prefix = '[Input: ' . rtrim(rtrim(number_format($qtyFisikRaw, 3, '.', ''), '0'), '.') . ' ' . $unitInput . ']';
                $catatan = trim($prefix . ' ' . $catatan);
            }

            $options = [
                'mode_distribusi' => $request->mode_distribusi,
                'harga_custom'    => $request->filled('harga_custom') ? (float)$request->harga_custom : null,
                'batch_id_target' => $request->filled('batch_id_target') ? (int)$request->batch_id_target : null,
                'catat_sebagai_biaya' => $request->boolean('catat_sebagai_biaya'),
            ];

            $this->stokService->adjustment(
                (int)   $request->item_id,
                (int)   $request->lokasi_id,
                $qtyFisikPakai,
                $catatan,
                $batchDistribusi,
                $request->alasan,
                $options
            );

            return redirect()->route('stok.index')
                ->with('success', 'Penyesuaian stok berhasil dicatat.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Set threshold qty_minimum per lokasi (bukan global di Item) — dipakai
     * untuk alert stok minimum & notifikasi otomatis. Pakai Eloquent update()
     * (bukan raw query) supaya StockObserver otomatis catat history-nya.
     */
    public function setMinimum(Request $request, Stock $stock)
    {
        abort_unless(auth()->user()->can('stok.minimum.set'), 403);

        $validated = $request->validate([
            'qty_minimum' => 'required|numeric|min:0',
        ]);

        $namaItem = $stock->item?->nama_item ?? 'item';
        $stock->update(['qty_minimum' => $validated['qty_minimum']]);

        return back()->with('success', "Qty minimum {$namaItem} berhasil diubah menjadi " . fmt_qty($validated['qty_minimum']) . '.');
    }

    /**
     * Reset stok ke 0 — fitur TERPISAH dari Adjustment, khusus cleanup data
     * test / fresh start item baru. Owner-only via permission granular
     * (default TIDAK di-assign ke role manapun). Konfirmasi ketat: user
     * harus ketik ulang nama item persis sebelum request diterima.
     */
    public function reset(Request $request, Stock $stock)
    {
        abort_unless(auth()->user()->can('stok.hapus.reset'), 403);

        $request->validate([
            'konfirmasi_nama' => 'required|string',
        ]);

        $namaItem = $stock->item?->nama_item ?? '';
        if (trim($request->konfirmasi_nama) !== trim($namaItem)) {
            return back()->with('error', 'Nama item konfirmasi tidak cocok — reset stok dibatalkan.');
        }

        $namaCabang = $stock->lokasi?->nama_cabang ?? 'lokasi ini';
        $this->stokService->resetStok($stock);

        return back()->with('success', "Stok {$namaItem} di {$namaCabang} berhasil di-reset ke 0.");
    }

    /**
     * API: Ambil qty stok untuk AJAX request
     */
    public function apiQty(Request $request)
    {
        $request->validate([
            'item_id'   => 'required|exists:items,id',
            'lokasi_id' => 'required|exists:cabangs,id',
        ]);

        $qty    = $this->stokService->getStok((int) $request->item_id, (int) $request->lokasi_id);
        $item   = Item::find($request->item_id);
        $min    = Stock::where('item_id', $request->item_id)
                    ->where('lokasi_id', $request->lokasi_id)
                    ->value('qty_minimum') ?? 0;

        return response()->json([
            'qty'              => (float) $qty,
            'satuan'           => $item?->satuan ?? '',
            'qty_minimum'      => (float) $min,
            // 4.38-E — harga beli di lokasi ini (fallback Harga Standar)
            'harga_beli_terakhir' => (float) ($item?->hargaBeliDiCabang((int) $request->lokasi_id) ?? 0),
        ]);
    }

    /**
     * API: Ambil daftar batch aktif untuk item + lokasi (untuk adjustment form)
     */
    public function apiBatches(Request $request)
    {
        $request->validate([
            'item_id'   => 'required|exists:items,id',
            'lokasi_id' => 'required|exists:cabangs,id',
        ]);

        $batches = StockBatch::where('item_id', $request->item_id)
            ->where('lokasi_id', $request->lokasi_id)
            ->where('qty_sisa', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('tanggal_masuk', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'qty_awal', 'qty_sisa', 'harga_beli_per_unit', 'tanggal_masuk', 'referensi_type']);

        return response()->json(['batches' => $batches]);
    }
}
