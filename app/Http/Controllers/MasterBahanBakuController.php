<?php

namespace App\Http\Controllers;

use App\Http\Requests\BahanBakuRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use App\Services\CascadeDeleteService;
use App\Support\CabangAktif;
use Illuminate\Http\Request;

/**
 * Tahap 2.5 D'mentai — menu "Bahan Baku & Kemasan": form SIMPLE (tanpa
 * foto/resep/varian) untuk items bertipe bahan_baku/kemasan/tambahan_gratis.
 * Beda tabel? TIDAK — tetap 1 tabel `items`, cuma difilter+form terpisah
 * dari Master Produk Jual (lihat CLAUDE.md Tahap 2.5).
 *
 * CLAUDE.md 4.39 — data terpisah per cabang: daftar & data baru mengikuti
 * cabang aktif di header; "Semua Cabang" = katalog lengkap + kolom Cabang.
 */
class MasterBahanBakuController extends Controller
{
    private const TIPE_SCOPE = ['bahan_baku', 'kemasan', 'tambahan_gratis'];

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.view'), 403);

        $cabangId = CabangAktif::id();
        $query = Item::with('category', 'cabang')->whereIn('tipe', self::TIPE_SCOPE)->milikCabang($cabangId)
            ->orderBy('nama_item');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_item', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_item', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->filled('tipe') && in_array($request->tipe, self::TIPE_SCOPE, true)) {
            $query->where('tipe', $request->tipe);
        }
        if ($request->filled('kategori')) {
            $query->where('item_category_id', $request->kategori);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        $items      = $query->paginate(20)->withQueryString();
        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $tipes      = ['bahan_baku' => 'Bahan Baku', 'kemasan' => 'Kemasan', 'tambahan_gratis' => 'Tambahan Gratis'];

        $hitung = fn ($tipe) => Item::milikCabang($cabangId)->whereIn('tipe', (array) $tipe)->count();
        $stats = [
            'total'           => $hitung(self::TIPE_SCOPE),
            'bahan_baku'      => $hitung('bahan_baku'),
            'kemasan'         => $hitung('kemasan'),
            'tambahan_gratis' => $hitung('tambahan_gratis'),
        ];

        // Stok & minimum di cabang pemilik masing-masing barang
        $stok = Stock::whereIn('item_id', $items->pluck('id'))->get()
            ->groupBy('item_id')->map(fn ($rows, $itemId) => $rows->firstWhere('lokasi_id', $items->firstWhere('id', $itemId)?->cabang_id ?? $cabangId) ?? $rows->first());
        $cabangAktif = CabangAktif::model();

        return view('master.bahan-baku.index', compact('items', 'categories', 'tipes', 'stats', 'stok', 'cabangAktif'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('master.bahan_baku.create'), 403);

        $categories  = ItemCategory::orderBy('nama_kategori')->get();
        $cabangAktif = CabangAktif::model();
        $pilihanCabang = CabangAktif::pilihan();

        return view('master.bahan-baku.create', compact('categories', 'cabangAktif', 'pilihanCabang'));
    }

    public function store(BahanBakuRequest $request)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.create'), 403);

        $data = $request->safe()->except(['stok_awal']);
        $data['cabang_id']  = $request->cabangPemilik();
        if (! $data['cabang_id']) {
            return back()->withInput()->withErrors(['cabang_id' => 'Pilih cabang pemilik barang ini (atau pilih cabang di pojok kanan atas).']);
        }
        $data['kode_item']  = strtoupper($data['kode_item']);
        $data['is_active']  = $request->boolean('is_active', true);
        // Sprint 4.34 — checkbox-style: absen (unchecked) → false. Form create
        // UI default checked, jadi kalau user tidak uncheck → kirim '1' → true.
        $data['track_stok'] = $request->boolean('track_stok');

        $item = Item::create($data);

        $stokAwal = (float) $request->input('stok_awal', 0);
        if ($stokAwal > 0) {
            Stock::updateOrCreate(
                ['item_id' => $item->id, 'lokasi_id' => $item->cabang_id],
                ['qty' => $stokAwal, 'qty_minimum' => $item->qty_minimum ?? 0]
            );
        }
        $this->syncMinimum($item);

        return redirect()->route('master.bahan-baku.index')
            ->with('success', "Bahan/Kemasan {$item->nama_item} berhasil ditambahkan.");
    }

    public function edit(Item $bahanBaku)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.edit'), 403);
        abort_unless(in_array($bahanBaku->tipe, self::TIPE_SCOPE, true), 404);
        abort_unless(CabangAktif::bolehAkses($bahanBaku->cabang_id), 403);

        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $bahanBaku->load('cabang');
        // FIFO Batch Detail: batch yang masih bersisa di cabang pemilik, tertua dulu
        $batches = \App\Models\StockBatch::with('lokasi')->where('item_id', $bahanBaku->id)->where('qty_sisa', '>', 0)
            ->when($bahanBaku->cabang_id, fn ($q) => $q->where('lokasi_id', $bahanBaku->cabang_id))
            ->orderBy('tanggal_masuk')->orderBy('id')->get();

        return view('master.bahan-baku.edit', ['item' => $bahanBaku, 'categories' => $categories, 'batches' => $batches]);
    }

    public function update(BahanBakuRequest $request, Item $bahanBaku)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.edit'), 403);
        abort_unless(in_array($bahanBaku->tipe, self::TIPE_SCOPE, true), 404);
        abort_unless(CabangAktif::bolehAkses($bahanBaku->cabang_id), 403);

        $data = $request->safe()->except(['stok_awal', 'cabang_id']);
        $data['kode_item']  = strtoupper($data['kode_item']);
        $data['is_active']  = $request->boolean('is_active', true);
        // Sprint 4.34 — checkbox-style konsisten dgn store(): absen → false.
        $data['track_stok'] = $request->boolean('track_stok');

        $bahanBaku->update($data);
        $this->syncMinimum($bahanBaku);

        return redirect()->route('master.bahan-baku.index')
            ->with('success', "Bahan/Kemasan {$bahanBaku->nama_item} berhasil diperbarui.");
    }

    /**
     * Qty Minimum barang = minimum di cabang pemiliknya → disalin ke
     * stocks.qty_minimum (dipakai alert & Laporan Stok Minimum). Qty stok
     * tidak disentuh; baris stok baru (qty 0) hanya dibuat kalau minimum > 0.
     */
    private function syncMinimum(Item $item): void
    {
        if (! $item->cabang_id) {
            return;
        }
        $min = (float) ($item->qty_minimum ?? 0);
        $stok = Stock::withTrashed()->where('item_id', $item->id)->where('lokasi_id', $item->cabang_id)->first();

        if ($stok) {
            if ((float) $stok->qty_minimum !== $min) {
                $stok->qty_minimum = $min;
                $stok->updated_at = now();
                $stok->save();
            }
        } elseif ($min > 0) {
            $stok = new Stock(['item_id' => $item->id, 'lokasi_id' => $item->cabang_id, 'qty' => 0, 'qty_minimum' => $min]);
            $stok->updated_at = now();
            $stok->save();
        }
    }

    public function destroy(Item $bahanBaku, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.delete'), 403);
        abort_unless(in_array($bahanBaku->tipe, self::TIPE_SCOPE, true), 404);
        abort_unless(CabangAktif::bolehAkses($bahanBaku->cabang_id), 403);

        try {
            $nama = $bahanBaku->nama_item;
            $cascadeService->deleteItemCascade($bahanBaku);

            return redirect()->route('master.bahan-baku.index')
                ->with('success', "Item <strong>{$nama}</strong> berhasil dihapus.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus item: ' . $e->getMessage());
        }
    }
}
