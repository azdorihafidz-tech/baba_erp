<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\JenisOlahan;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Services\HargaResepService;
use App\Support\CabangAktif;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

/**
 * CRUD Master Bumbu Pusat (nama tampilan; route/permission/model tetap
 * "resep-bumbu"/"resep_bumbu", zero migration) — murni master data (helper untuk POS
 * auto-populate item bumbu). Tidak menyentuh PenjualanService/StokService
 * sama sekali; hanya dikonsumsi read-only oleh endpoint AJAX POS.
 */
class MasterResepBumbuController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('master.resep_bumbu.view'), 403);

        // 4.39 — Bumbu Pusat & resep milik cabang aktif
        $query = ResepBumbu::with(['jenisOlahan', 'item', 'cabang'])->withCount('items')->milikCabang(CabangAktif::id())->orderBy('nama');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%");
            });
        }

        $resepBumbus = $query->paginate(20)->withQueryString();

        $cabangAktif = CabangAktif::model();

        return view('master.resep-bumbu.index', compact('resepBumbus', 'cabangAktif'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('master.resep_bumbu.create'), 403);

        $jenisOlahans = JenisOlahan::aktif()->orderBy('nama')->get();

        $cabangAktif   = CabangAktif::model();
        $pilihanCabang = CabangAktif::pilihan();

        return view('master.resep-bumbu.create', compact('jenisOlahans', 'cabangAktif', 'pilihanCabang'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('master.resep_bumbu.create'), 403);

        $cabangId = CabangAktif::pemilikBaru($request->filled('cabang_id') ? (int) $request->input('cabang_id') : null);
        abort_unless($cabangId, 422, 'Pilih cabang pemilik Bumbu Pusat ini.');
        $validated = $request->validate([
            'nama'            => 'required|string|max:100',
            'kode'            => ['required', 'string', 'max:20', Rule::unique('resep_bumbu', 'kode')->where('cabang_id', $cabangId)],
            'jenis_olahan_id' => 'nullable|exists:jenis_olahans,id',
            'catatan'         => 'nullable|string',
        ]);
        $validated['is_active']   = true;
        $validated['dibuat_oleh'] = auth()->id();
        $validated['cabang_id']   = $cabangId;

        $resep = ResepBumbu::create($validated);

        return redirect()->route('master.resep-bumbu.edit', $resep)
            ->with('success', "Resep \"{$resep->nama}\" berhasil dibuat. Silakan tambahkan bahan di bawah.");
    }

    public function edit(ResepBumbu $resepBumbu)
    {
        abort_unless(auth()->user()->can('master.resep_bumbu.edit'), 403);
        abort_unless(CabangAktif::bolehAkses($resepBumbu->cabang_id), 403);

        // Tahap 2.5 D'mentai — resep yang sudah terhubung ke Produk Jual
        // (item_id terisi) source-of-truth-nya SEKARANG di form Produk Jual
        // (Section "Komposisi/Resep" terintegrasi) supaya tidak ada 2 tempat
        // edit terpisah utk data yang sama. Resep legacy tanpa item_id
        // (kalau ada) tetap pakai form lama di bawah.
        if ($resepBumbu->item_id && auth()->user()->can('master.produk_jual.edit')) {
            return redirect()->route('master.produk-jual.edit', $resepBumbu->item_id)
                ->with('success', "Resep \"{$resepBumbu->nama}\" dikelola lewat form Produk Jual (section Komposisi/Resep) — scroll ke bawah.");
        }

        $resepBumbu->load('items.item');
        $jenisOlahans   = JenisOlahan::aktif()->orderBy('nama')->get();
        // Bahan milik cabang Bumbu Pusat ini; harga = FIFO stok cabang itu
        $bahanBakuItems = Item::whereIn('tipe', ['bahan_baku', 'kemasan'])
            ->milikCabang($resepBumbu->cabang_id)
            ->where('is_active', true)
            ->orderBy('nama_item')
            ->get();
        $hargaBahan = app(HargaResepService::class)->untuk($bahanBakuItems->merge($resepBumbu->items->pluck('item')), $resepBumbu->cabang_id);

        return view('master.resep-bumbu.edit', compact('resepBumbu', 'jenisOlahans', 'bahanBakuItems', 'hargaBahan'));
    }

    public function update(Request $request, ResepBumbu $resepBumbu)
    {
        abort_unless(auth()->user()->can('master.resep_bumbu.edit'), 403);
        abort_unless(CabangAktif::bolehAkses($resepBumbu->cabang_id), 403);

        $validated = $request->validate([
            'nama'            => 'required|string|max:100',
            'kode'            => ['required', 'string', 'max:20', Rule::unique('resep_bumbu', 'kode')->ignore($resepBumbu->id)
                ->where(fn ($q) => $resepBumbu->cabang_id ? $q->where('cabang_id', $resepBumbu->cabang_id) : $q->whereNull('cabang_id'))],
            'jenis_olahan_id' => 'nullable|exists:jenis_olahans,id',
            'catatan'         => 'nullable|string',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $resepBumbu->update($validated);

        return redirect()->route('master.resep-bumbu.edit', $resepBumbu)
            ->with('success', "Resep \"{$resepBumbu->nama}\" berhasil diperbarui.");
    }

    public function destroy(ResepBumbu $resepBumbu)
    {
        abort_unless(auth()->user()->can('master.resep_bumbu.delete'), 403);
        abort_unless(CabangAktif::bolehAkses($resepBumbu->cabang_id), 403);

        // Tidak hard delete — nonaktifkan saja (pola sama seperti Master Jenis
        // Olahan), resep yang sudah pernah dipakai order tetap valid utk histori.
        $resepBumbu->update(['is_active' => false]);

        return redirect()->route('master.resep-bumbu.index')
            ->with('success', "Resep \"{$resepBumbu->nama}\" dinonaktifkan.");
    }

    public function storeItem(Request $request, ResepBumbu $resepBumbu)
    {
        abort_unless(auth()->user()->can('master.resep_bumbu.edit'), 403);
        abort_unless(CabangAktif::bolehAkses($resepBumbu->cabang_id), 403);

        $validated = $request->validate([
            // 4.39 — bahan harus milik cabang yang sama dgn Bumbu Pusat
            'item_id'    => ['required', Rule::exists('items', 'id')->where(fn ($q) => $resepBumbu->cabang_id ? $q->where(fn ($w) => $w->where('cabang_id', $resepBumbu->cabang_id)->orWhereNull('cabang_id')) : $q)],
            'qty_per_unit' => 'required|numeric|min:0.001',
            'satuan'     => 'required|string|max:20',
        ], ['item_id.exists' => 'Bahan harus milik cabang yang sama dengan Bumbu Pusat ini.']);
        // 4.39 — Wajib & Mode Harga tidak dipakai lagi: semua bahan selalu dihitung
        $validated['is_wajib']   = true;
        $validated['mode_harga'] = 'pakai_master';
        $validated['urutan']   = ((int) $resepBumbu->items()->max('urutan')) + 1;

        $resepBumbu->items()->create($validated);

        return back()->with('success', 'Bahan berhasil ditambahkan ke resep.');
    }

    public function destroyItem(ResepBumbu $resepBumbu, ResepBumbuItem $resepBumbuItem)
    {
        abort_unless(auth()->user()->can('master.resep_bumbu.edit'), 403);
        abort_unless(CabangAktif::bolehAkses($resepBumbu->cabang_id), 403);

        abort_unless($resepBumbuItem->resep_bumbu_id === $resepBumbu->id, 404);

        $resepBumbuItem->delete();

        return back()->with('success', 'Bahan berhasil dihapus dari resep.');
    }
}
