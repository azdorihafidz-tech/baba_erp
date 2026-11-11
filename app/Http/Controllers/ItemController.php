<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Services\CascadeDeleteService;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('item.view'), 403);

        // 4.39 — ikut cabang aktif di header
        $cabangId = \App\Support\CabangAktif::id();
        $query = Item::with('category', 'cabang')->milikCabang($cabangId)->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_item', 'like', '%'.$request->search.'%')
                  ->orWhere('kode_item', 'like', '%'.$request->search.'%');
            });
        }
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }
        // Fase 5 — Modul Perlengkapan (Rule #66): filter jenis BARU,
        // independen dari filter tipe existing di atas (tidak disentuh).
        if ($request->filled('jenis')) {
            $query->where('jenis', $request->jenis);
        }
        if ($request->filled('kategori')) {
            $query->where('item_category_id', $request->kategori);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        $items      = $query->paginate(20)->withQueryString();
        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $tipes      = ['bahan_baku' => 'Bahan Baku', 'kemasan' => 'Kemasan', 'tambahan_gratis' => 'Tambahan Gratis', 'produk_jual' => 'Produk Jual', 'produk_tambahan' => 'Produk Tambahan'];

        // Sprint 4.33 (2026-10-01) — rename stat `produk_jadi` → `produk_jual`
        // mengikuti enum DB terbaru (Tahap 2.5 rename, CLAUDE.md 8.3). Key lama
        // `produk_jadi` di-alias supaya kalau ada view lain yg masih akses tidak crash.
        $jumlahProdukJual = Item::milikCabang($cabangId)->where('tipe', 'produk_jual')->count();
        $stats = [
            'total'       => Item::milikCabang($cabangId)->count(),
            'bahan_baku'  => Item::milikCabang($cabangId)->where('tipe', 'bahan_baku')->count(),
            'produk_jual' => $jumlahProdukJual,
            'produk_jadi' => $jumlahProdukJual, // alias legacy
            'nonaktif'    => Item::milikCabang($cabangId)->where('is_active', false)->count(),
        ];

        return view('item.index', compact('items', 'categories', 'tipes', 'stats'));
    }

    public function create()
    {
        // Sprint 4.34 (2026-10-08) — Master Barang Lengkap jadi READ-ONLY overview.
        // Buat barang baru lewat 2 menu khusus: Bahan Baku (3 tipe non-POS) atau
        // Produk Jual (2 tipe POS). Keputusan Owner: 1 model, produk jual = POS
        // display saja; stok fisik real cuma di bahan_baku/kemasan/tambahan_gratis.
        return redirect()->route('master.bahan-baku.index')
            ->with('info', 'Master Barang Lengkap sekarang read-only. Buat barang baru lewat menu "Bahan Baku & Kemasan" (bahan/kemasan/tambahan) atau "Produk Jual" (menu POS).');
    }

    public function store(ItemRequest $request)
    {
        // Sprint 4.34 — route store tidak boleh dipanggil lagi; redirect defensif
        // kalau ada bookmark/devtools lama. Guard sebagai pengganti abort 410
        // (biar user tidak lihat error page tapi diarahkan ke tempat yg benar).
        return redirect()->route('master.bahan-baku.index')
            ->with('info', 'Tambah barang sekarang lewat menu "Bahan Baku & Kemasan" atau "Produk Jual".');
    }

    public function show(Item $item)
    {
        abort_unless(auth()->user()->can('item.view'), 403);

        $item->load(['category', 'stocks.lokasi']);

        $recentMovements = StockMovement::with(['lokasiAsal', 'lokasiTujuan', 'user'])
            ->where('item_id', $item->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('item.show', compact('item', 'recentMovements'));
    }

    public function edit(Item $item)
    {
        // Sprint 4.34 — redirect ke menu yang bersangkutan berdasarkan tipe.
        // Produk jual/tambahan → menu Produk Jual; sisanya → menu Bahan Baku.
        abort_unless(auth()->user()->can('item.view'), 403);

        if (in_array($item->tipe, ['produk_jual', 'produk_tambahan'], true)) {
            return redirect()->route('master.produk-jual.edit', $item);
        }
        return redirect()->route('master.bahan-baku.edit', $item);
    }

    public function update(ItemRequest $request, Item $item)
    {
        // Sprint 4.34 — tidak boleh update dari Master Barang Lengkap lagi;
        // redirect defensif kalau ada POST langsung ke route lama.
        return $this->edit($item);
    }

    public function destroy(Item $item, CascadeDeleteService $cascadeService)
    {
        // Sprint 4.34 — tidak boleh hapus dari Master Barang Lengkap lagi
        // (sumber kekacauan [[4.33]] tipe item). Hapus lewat menu masing-masing.
        return redirect()->route('item.index')
            ->with('info', 'Hapus barang sekarang lewat menu "Bahan Baku & Kemasan" atau "Produk Jual" (sesuai tipe).');
    }

    // ===== KATEGORI (inline) =====

    public function storeKategori(Request $request)
    {
        // 4.43: modal Tambah Kategori dipakai form Bahan Baku & Produk Jual
        abort_unless(auth()->user()->canAny([
            'master.bahan_baku.create', 'master.bahan_baku.edit', 'master.produk_jual.create', 'master.produk_jual.edit', 'item.create',
        ]), 403);

        $request->validate([
            'kode_kategori' => 'required|string|max:20|unique:item_categories,kode_kategori',
            'nama_kategori' => 'required|string|max:100',
        ]);

        ItemCategory::create([
            'kode_kategori' => strtoupper($request->kode_kategori),
            'nama_kategori' => $request->nama_kategori,
        ]);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }
}
