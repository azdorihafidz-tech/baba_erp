<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProdukJualRequest;
use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemCategory;
use App\Models\ItemVariant;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Services\CascadeDeleteService;
use App\Services\HargaResepService;
use App\Support\CabangAktif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Tahap 2.5 D'mentai — menu "Produk Jual": form LENGKAP (foto + resep +
 * varian + ketersediaan outlet) untuk items bertipe produk_jual/produk_tambahan.
 * Tetap 1 tabel `items` (lihat CLAUDE.md Tahap 2.5) — controller ini cuma
 * beda filter+form dari Master Bahan Baku.
 */
class MasterProdukJualController extends Controller
{
    private const TIPE_SCOPE = ['produk_jual', 'produk_tambahan'];
    private const MAX_DIMENSI = 800;

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('master.produk_jual.view'), 403);

        $cabangId = CabangAktif::id();
        $query = Item::with('category', 'cabang')->whereIn('tipe', self::TIPE_SCOPE)->milikCabang($cabangId)->orderBy('nama_item');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_item', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_item', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->filled('kategori')) {
            $query->where('item_category_id', $request->kategori);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        $items      = $query->paginate(20)->withQueryString();
        $categories = ItemCategory::orderBy('nama_kategori')->get();

        $cabangAktif = CabangAktif::model();

        return view('master.produk-jual.index', compact('items', 'categories', 'cabangAktif'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('master.produk_jual.create'), 403);

        $categories    = ItemCategory::orderBy('nama_kategori')->get();
        $cabangAktif   = CabangAktif::model();
        $pilihanCabang = CabangAktif::pilihan();
        // Cabang pemilik produk baru: cabang aktif, mode Semua Cabang → ?cabang_id= / Gudang Pusat
        $cabangPemilik = CabangAktif::pemilikBaru(request()->filled('cabang_id') ? (int) request('cabang_id') : null);
        [$bahanOptions, $hargaBahan] = $this->opsiBahan($cabangPemilik);

        return view('master.produk-jual.create', compact('categories', 'cabangAktif', 'pilihanCabang', 'cabangPemilik', 'bahanOptions', 'hargaBahan'));
    }

    public function store(ProdukJualRequest $request)
    {
        abort_unless(auth()->user()->can('master.produk_jual.create'), 403);

        $data = $request->safe()->only([
            'kode_item', 'nama_item', 'item_category_id', 'tipe', 'satuan',
            'harga_jual', 'deskripsi',
        ]);
        $data['cabang_id']       = $request->cabangPemilik();
        if (! $data['cabang_id']) {
            return back()->withInput()->withErrors(['cabang_id' => 'Pilih cabang pemilik produk ini (atau pilih cabang di pojok kanan atas).']);
        }
        $data['kode_item']       = strtoupper($data['kode_item']);
        $data['is_active']       = $request->boolean('is_active', true);
        $data['punya_varian']    = $request->boolean('punya_varian') && filled($request->input('atribut'));
        $data['stok_per_varian'] = $request->boolean('stok_per_varian');
        // Sprint 4.34 (2026-10-08) — produk jual murni POS display, stok fisik
        // selalu dihitung dari expand resep ke bahan_baku saat POS checkout
        // (lihat PenjualanService::cekResepCukup). track_stok dipaksa false
        // supaya tidak muncul di Dashboard/Laporan Stok.
        $data['track_stok']      = false;

        if ($request->hasFile('foto')) {
            $data['foto'] = $this->simpanFoto($request->file('foto'));
        }

        $item = Item::create($data);

        $this->syncResep($item, (array) $request->input('resep', []));
        if ($data['punya_varian']) {
            $this->syncAttributesAndVariants($item, (array) $request->input('atribut', []), (array) $request->input('harga_override', []));
        }

        // 4.42 — "Buat juga di cabang": lanjut ke layar pemeriksaan komposisi
        $salinKe = CabangAktif::pilihan()->pluck('id')
            ->intersect(collect((array) $request->input('salin_ke_cabang', []))->map(fn ($id) => (int) $id))
            ->reject(fn ($id) => $id === (int) $item->cabang_id)->values();
        if ($salinKe->isNotEmpty()) {
            return redirect()->route('master.produk-jual.ambil.periksa', [
                'sumber' => $item->cabang_id, 'produk' => [$item->id], 'tujuan' => $salinKe->all(),
            ])->with('success', "Produk {$item->nama_item} berhasil ditambahkan. Periksa komposisi untuk cabang lain lalu klik Salin Produk.");
        }

        return redirect()->route('master.produk-jual.index')
            ->with('success', "Produk {$item->nama_item} berhasil ditambahkan.");
    }

    public function edit(Item $produkJual)
    {
        abort_unless(auth()->user()->can('master.produk_jual.edit'), 403);
        abort_unless(in_array($produkJual->tipe, self::TIPE_SCOPE, true), 404);
        abort_unless(CabangAktif::bolehAkses($produkJual->cabang_id), 403);

        $produkJual->load([
            'resep.items.item', 'resep.items.resepBumbuRef',
            'attributes.values',
            'variants' => fn ($q) => $q->orderBy('urutan'),
            'variants.attributeValues.attribute',
            'itemCabang',
        ]);

        $categories = ItemCategory::orderBy('nama_kategori')->get();
        [$bahanOptions, $hargaBahan] = $this->opsiBahan($produkJual->cabang_id);

        return view('master.produk-jual.edit', [
            'item' => $produkJual, 'categories' => $categories,
            'bahanOptions' => $bahanOptions, 'hargaBahan' => $hargaBahan, 'cabangPemilik' => $produkJual->cabang_id,
        ]);
    }

    public function update(ProdukJualRequest $request, Item $produkJual)
    {
        abort_unless(auth()->user()->can('master.produk_jual.edit'), 403);
        abort_unless(in_array($produkJual->tipe, self::TIPE_SCOPE, true), 404);
        abort_unless(CabangAktif::bolehAkses($produkJual->cabang_id), 403);

        $data = $request->safe()->only([
            'kode_item', 'nama_item', 'item_category_id', 'tipe', 'satuan',
            'harga_jual', 'deskripsi',
        ]);
        $data['kode_item']       = strtoupper($data['kode_item']);
        $data['is_active']       = $request->boolean('is_active', true);
        $data['stok_per_varian'] = $request->boolean('stok_per_varian');
        // Sprint 4.34 — konsisten dgn store(): produk jual tidak track stok.
        $data['track_stok']      = false;

        $punyaVarianBaru = $request->boolean('punya_varian') && filled($request->input('atribut'));
        $data['punya_varian'] = $punyaVarianBaru;

        if ($request->boolean('hapus_foto') && $produkJual->foto) {
            Storage::disk('public')->delete($produkJual->foto);
            $data['foto'] = null;
        }
        if ($request->hasFile('foto')) {
            if ($produkJual->foto) {
                Storage::disk('public')->delete($produkJual->foto);
            }
            $data['foto'] = $this->simpanFoto($request->file('foto'));
        }

        $produkJual->update($data);

        $this->syncResep($produkJual, (array) $request->input('resep', []));
        // Toggle OFF varian: SENGAJA tidak menghapus attribute/variant lama
        // (bisa direferensikan order_items.item_variant_id historis) — cukup
        // matikan flag, POS otomatis berhenti menampilkan modal varian.
        if ($punyaVarianBaru) {
            $this->syncAttributesAndVariants($produkJual, (array) $request->input('atribut', []), (array) $request->input('harga_override', []));
        }
        return redirect()->route('master.produk-jual.index')
            ->with('success', "Produk {$produkJual->nama_item} berhasil diperbarui.");
    }

    public function destroy(Item $produkJual, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('master.produk_jual.delete'), 403);
        abort_unless(in_array($produkJual->tipe, self::TIPE_SCOPE, true), 404);
        abort_unless(CabangAktif::bolehAkses($produkJual->cabang_id), 403);

        try {
            $nama = $produkJual->nama_item;
            if ($produkJual->foto) {
                Storage::disk('public')->delete($produkJual->foto);
            }
            $cascadeService->deleteItemCascade($produkJual);

            return redirect()->route('master.produk-jual.index')
                ->with('success', "Produk <strong>{$nama}</strong> berhasil dihapus.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus produk: ' . $e->getMessage());
        }
    }

    /**
     * AJAX helper (dipakai kalkulator resep di form) — hitung breakdown
     * bahan untuk N pcs produksi. Murni preview, tidak menyentuh DB.
     * Baris linked (Import Bumbu Pusat) ditampilkan SEBAGAI 1 baris
     * teragregasi (bukan pecah per bahan dalam bumbu) — dihitung LIVE dari
     * komposisi bumbu saat ini, jadi otomatis akurat kalau bumbu diedit.
     */
    public function kalkulatorResep(Request $request, Item $produkJual)
    {
        abort_unless(auth()->user()->can('master.produk_jual.view'), 403);

        $jumlah = max(1, (int) $request->input('jumlah', 1));

        // Bug fix 2026-09-19: tombol "Simulasi Produksi Lengkap" dulu SELALU baca
        // resep dari DB (data tersimpan), padahal user bisa saja sedang mengedit
        // form (qty baru) TANPA klik Simpan dulu -- hasilnya server hitung dari
        // angka lama, beda dari yang ditampilkan form (lihat CLAUDE.md 4.19).
        // Fix: kalau frontend kirim payload `resep[]` (isi form saat ini), pakai
        // itu; fallback ke DB kalau kosong (backward compat + Create belum ada
        // $produkJual tersimpan, walau tombol ini cuma muncul di halaman Edit).
        $resepInput = $request->input('resep');

        if (is_array($resepInput) && count($resepInput) > 0) {
            $rows = $this->normalisasiResepDariForm($resepInput);
        } else {
            $produkJual->load('resep.items.item', 'resep.items.resepBumbuRef.items.item');
            $rows = ($produkJual->resep?->items ?? collect())->map(fn ($ri) => [
                'is_linked'       => $ri->isLinked(),
                'resep_bumbu_ref' => $ri->resepBumbuRef,
                'item'            => $ri->item,
                'qty_per_unit'    => (float) $ri->qty_per_unit,
                'satuan'          => $ri->satuan,
            ]);
        }

        $breakdown = [];
        $totalHpp = 0.0;
        $harga = $this->hargaBahanUntukRows($rows, $produkJual->cabang_id);

        foreach ($rows as $row) {
            if ($row['is_linked']) {
                $bumbu = $row['resep_bumbu_ref'];
                if (! $bumbu) continue;
                $subtotalBumbu = $this->hitungSubtotalBumbuTunggal($bumbu, $row['qty_per_unit'], $jumlah, $harga);
                $totalHpp += $subtotalBumbu;
                $breakdown[] = [
                    'nama'     => '🧂 ' . $bumbu->nama . ' (Bumbu Pusat)',
                    'qty'      => round($row['qty_per_unit'] * $jumlah, 3),
                    'satuan'   => 'porsi',
                    'hpp_satuan' => null,
                    'subtotal' => $subtotalBumbu,
                    'linked'   => true,
                ];
                continue;
            }

            $item = $row['item'];
            if (! $item) continue;
            $qtyDibutuhkan = $row['qty_per_unit'] * $jumlah;
            $hppSatuan = (float) ($harga[$item->id]['harga'] ?? 0);
            $subtotal = $qtyDibutuhkan * $hppSatuan;
            $totalHpp += $subtotal;

            $breakdown[] = [
                'nama'  => $item->nama_item,
                'qty'   => round($qtyDibutuhkan, 3),
                'satuan'=> $row['satuan'],
                'hpp_satuan' => $hppSatuan,
                'subtotal' => $subtotal,
                'linked' => false,
            ];
        }

        return response()->json(['jumlah_produksi' => $jumlah, 'breakdown' => $breakdown, 'total_hpp' => $totalHpp]);
    }

    /**
     * Expand 1 baris Bumbu Pusat (linked) jadi subtotal Rp -- dipakai kedua
     * endpoint kalkulator (kalkulatorResep() utk resep 1 produk penuh, DAN
     * previewSubtotalBumbu() utk preview 1 bumbu berdiri sendiri) supaya
     * logic-nya 1 sumber, tidak duplikat.
     */
    private function hitungSubtotalBumbuTunggal(ResepBumbu $bumbu, float $qtyPerUnit, int $jumlah, ?array $harga = null): float
    {
        // 4.39 — semua bahan selalu dihitung (Mode Harga dihapus), harga FIFO cabang bahan
        $harga ??= app(HargaResepService::class)->untuk($bumbu->items->pluck('item'), $bumbu->cabang_id);
        $subtotal = 0.0;
        foreach ($bumbu->items as $inner) {
            if (! $inner->item) continue;
            $qtyInner = $inner->qty_per_unit_dalam_kg * $qtyPerUnit * $jumlah;
            $subtotal += $qtyInner * (float) ($harga[$inner->item->id]['harga'] ?? 0);
        }

        return $subtotal;
    }

    /** Harga FIFO semua bahan di rows resep (bahan manual + isi Bumbu Pusat). */
    private function hargaBahanUntukRows(\Illuminate\Support\Collection $rows, ?int $cabangId): array
    {
        $items = collect();
        foreach ($rows as $row) {
            if ($row['is_linked'] && $row['resep_bumbu_ref']) {
                $items = $items->merge($row['resep_bumbu_ref']->items->pluck('item'));
            } elseif (! $row['is_linked'] && $row['item']) {
                $items->push($row['item']);
            }
        }

        return app(HargaResepService::class)->untuk($items, $cabangId);
    }

    /**
     * Opsi bahan resep milik cabang pemilik produk + harga FIFO-nya (4.39).
     * @return array{0: \Illuminate\Support\Collection, 1: array}
     */
    private function opsiBahan(?int $cabangId): array
    {
        $bahan = Item::whereIn('tipe', ['bahan_baku', 'kemasan', 'tambahan_gratis'])
            ->milikCabang($cabangId)->aktif()->orderBy('nama_item')->get();

        return [$bahan, app(HargaResepService::class)->untuk($bahan, $cabangId)];
    }

    /**
     * Bug fix 2026-09-19: hitungSubtotalLinkedBaris() (JS, preview live baris
     * Bumbu Pusat di section Resep) dulu pakai kalkulatorResep() yang route-nya
     * `/{produkJual}/kalkulator-resep` -- butuh Item YANG SUDAH TERSIMPAN. Di
     * halaman Create belum ada $item sama sekali, jadi endpoint itu genuinely
     * tidak bisa dipanggil -- subtotal Bumbu Pusat selalu "—"/Rp0 di Create
     * (lihat CLAUDE.md 4.19). Padahal subtotal 1 bumbu TIDAK butuh produk sama
     * sekali (cuma butuh: bumbu apa + qty berapa porsi) -- endpoint ini
     * decouple dari Item, di-bind langsung ke ResepBumbu, jadi jalan di Create
     * MAUPUN Edit tanpa syarat produk tersimpan.
     */
    public function previewSubtotalBumbu(Request $request, ResepBumbu $bumbu)
    {
        abort_unless(auth()->user()->can('master.produk_jual.edit'), 403);

        $jumlah = max(1, (int) $request->input('jumlah', 1));
        $qtyPerUnit = (float) $request->input('qty_per_unit', 1);
        $bumbu->load('items.item');

        return response()->json([
            'subtotal' => $this->hitungSubtotalBumbuTunggal($bumbu, $qtyPerUnit, $jumlah),
        ]);
    }

    /**
     * Normalisasi payload `resep[]` dari form (JS serialize, lihat
     * hitungKalkulator() di _form.blade.php) jadi bentuk seragam dgn baris
     * dari DB, supaya loop kalkulatorResep() di atas tidak perlu tahu asal
     * datanya. Item/ResepBumbu di-batch-load (bukan query per baris) --
     * jumlah baris resep 1 produk biasanya kecil (<20), tapi tetap dihindari
     * N+1 sejak awal.
     */
    private function normalisasiResepDariForm(array $resepInput): \Illuminate\Support\Collection
    {
        $itemIds = collect($resepInput)->pluck('item_id')->filter()->unique()->values();
        $bumbuIds = collect($resepInput)->pluck('resep_bumbu_ref_id')->filter()->unique()->values();

        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');
        $bumbus = ResepBumbu::whereIn('id', $bumbuIds)->with('items.item')->get()->keyBy('id');

        return collect($resepInput)->map(function ($row) use ($items, $bumbus) {
            $bumbuRefId = $row['resep_bumbu_ref_id'] ?? null;
            $isLinked = ! empty($bumbuRefId);

            return [
                'is_linked'       => $isLinked,
                'resep_bumbu_ref' => $isLinked ? ($bumbus[$bumbuRefId] ?? null) : null,
                'item'            => ! $isLinked ? ($items[$row['item_id'] ?? null] ?? null) : null,
                'qty_per_unit'    => (float) ($row['qty_per_unit'] ?? 0),
                'satuan'          => $row['satuan'] ?? '',
            ];
        });
    }

    /**
     * AJAX picker "Import dari Bumbu Pusat" — list Master Bumbu aktif
     * (ResepBumbu tanpa item_id) utk dipilih di form Produk Jual. Reuse
     * permission master.produk_jual.edit (bukan bikin permission baru,
     * keputusan Owner 2026-09-15) -- siapapun yang boleh edit produk boleh
     * lihat & pakai daftar bumbu ini.
     */
    public function listBumbuPusat(Request $request)
    {
        abort_unless(auth()->user()->can('master.produk_jual.edit'), 403);

        $search = $request->input('search', '');

        // Sprint 4.31 (2026-09-29): eager-load items.item supaya picker bisa
        // tampilkan accordion detail bahan + harga_beli + subtotal + total HPP
        // per bumbu -- rumus subtotal SAMA dgn hitungSubtotalBumbuTunggal()
        // (qty_per_unit_dalam_kg × harga_beli_terakhir) supaya konsisten.
        $cabangId = $request->filled('cabang_id') ? (int) $request->input('cabang_id') : CabangAktif::id();
        abort_unless(CabangAktif::bolehAkses($cabangId), 403);
        $bumbus = ResepBumbu::whereNull('item_id')
            ->milikCabang($cabangId)
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->with(['items' => fn ($q) => $q->orderBy('urutan'), 'items.item'])
            ->withCount('items')
            ->orderBy('nama')
            ->limit(50)
            ->get(['id', 'nama', 'kode']);

        $harga = app(HargaResepService::class)->untuk($bumbus->flatMap(fn ($b) => $b->items->pluck('item')), $cabangId);

        return response()->json([
            'data' => $bumbus->map(function ($b) use ($harga) {
                $bahanDetail = $b->items->map(function ($ri) use ($harga) {
                    $h = $ri->item ? ($harga[$ri->item->id] ?? null) : null;
                    $hargaBeli = (float) ($h['harga'] ?? 0);
                    $qtyKg = $ri->qty_per_unit_dalam_kg;
                    $subtotal = round($qtyKg * $hargaBeli, 2);
                    return [
                        'nama_bahan' => $ri->item?->nama_item ?? '(bahan terhapus)',
                        'kode_bahan' => $ri->item?->kode_item ?? '-',
                        'qty'        => (float) $ri->qty_per_unit,
                        'satuan'     => $ri->satuan,
                        'harga_beli' => $hargaBeli,
                        'harga_info' => $h['info'] ?? '',
                        'habis'      => (bool) ($h['habis'] ?? false),
                        'subtotal'   => $subtotal,
                    ];
                });
                return [
                    'id' => $b->id,
                    'nama' => $b->nama,
                    'kode' => $b->kode,
                    'jumlah_bahan' => $b->items_count,
                    'total_hpp' => (float) $bahanDetail->sum('subtotal'),
                    'items' => $bahanDetail->values(),
                ];
            }),
        ]);
    }

    // ===== Helpers privat =====

    private function simpanFoto($file): string
    {
        $mime = $file->getMimeType();
        $ext  = $mime === 'image/png' ? 'png' : 'jpg';
        $filename = 'produk/' . uniqid('produk_', true) . '.' . $ext;

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png'  => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : null,
            default      => null,
        };

        if (! $src) {
            // GD gagal decode (format tidak didukung server ini) — fallback simpan apa adanya
            return $file->store('produk', 'public');
        }

        $width  = imagesx($src);
        $height = imagesy($src);

        if ($width > self::MAX_DIMENSI || $height > self::MAX_DIMENSI) {
            $ratio  = min(self::MAX_DIMENSI / $width, self::MAX_DIMENSI / $height);
            $newW   = (int) round($width * $ratio);
            $newH   = (int) round($height * $ratio);
            $dst    = imagecreatetruecolor($newW, $newH);

            if ($mime === 'image/png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($src);
            $src = $dst;
        }

        Storage::disk('public')->makeDirectory('produk');
        $fullPath = Storage::disk('public')->path($filename);

        if ($ext === 'png') {
            imagepng($src, $fullPath);
        } else {
            imagejpeg($src, $fullPath, 85);
        }
        imagedestroy($src);

        return $filename;
    }

    /**
     * Wipe & rebuild resep (ResepBumbuItem plain table, tidak ada FK order
     * yang bergantung ke baris resep). Fitur "Import dari Bumbu Pusat"
     * (2026-09-17) — 1 baris resep sekarang bisa item_id LANGSUNG (bahan
     * manual) ATAU resep_bumbu_ref_id (link ke Master Bumbu Pusat).
     */
    private function syncResep(Item $item, array $resepInput): void
    {
        $resep = $item->resep()->first();

        $resepInput = array_values(array_filter($resepInput, fn ($r) => !empty($r['item_id']) || !empty($r['resep_bumbu_ref_id'])));

        if (empty($resepInput)) {
            if ($resep) {
                $resep->items()->delete();
                $resep->delete();
            }
            return;
        }

        if (! $resep) {
            $resep = ResepBumbu::create([
                'cabang_id'    => $item->cabang_id,
                'nama'         => $item->nama_item,
                // kolom kode maks 20 karakter — Kode Item panjang dulu bikin error 500
                'kode'         => strlen('RESEP-' . $item->kode_item) <= 20 ? 'RESEP-' . $item->kode_item : 'RESEP-' . $item->id,
                'item_id'      => $item->id,
                'is_active'    => true,
                'dibuat_oleh'  => auth()->id(),
            ]);
        }

        // Master Bumbu Pusat valid utk di-link: item_id NULL (murni bumbu,
        // bukan resep produk lain) DAN bukan resep milik item ini sendiri
        // (self-reference, walau secara struktural mustahil krn resep item
        // ini selalu punya item_id != null -- tetap dicek eksplisit demi
        // defense-in-depth).
        $bumbuValidIds = ResepBumbu::whereNull('item_id')
            ->milikCabang($item->cabang_id)
            ->where('id', '!=', $resep->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
        // 4.39 — bahan resep harus milik cabang yang sama dgn produk
        $bahanValidIds = Item::milikCabang($item->cabang_id)
            ->whereIn('id', collect($resepInput)->pluck('item_id')->filter())
            ->pluck('id')->map(fn ($v) => (int) $v)->all();

        $resep->items()->delete();
        foreach ($resepInput as $i => $row) {
            $resepBumbuRefId = !empty($row['resep_bumbu_ref_id']) ? (int) $row['resep_bumbu_ref_id'] : null;

            if ($resepBumbuRefId && !in_array($resepBumbuRefId, $bumbuValidIds, true)) {
                // Bumbu tidak valid (nonaktif/dihapus/sudah bukan master bumbu murni
                // sejak dipilih di form) -- skip baris ini daripada simpan data korup.
                continue;
            }
            if (! $resepBumbuRefId && ! in_array((int) $row['item_id'], $bahanValidIds, true)) {
                continue; // bahan milik cabang lain
            }

            ResepBumbuItem::create([
                'resep_bumbu_id'      => $resep->id,
                'item_id'             => $resepBumbuRefId ? null : $row['item_id'],
                'resep_bumbu_ref_id'  => $resepBumbuRefId,
                'qty_per_unit'        => $row['qty_per_unit'],
                'satuan'              => $resepBumbuRefId ? 'porsi' : $row['satuan'],
                // 4.39 — Wajib & Mode Harga tidak dipakai lagi (semua bahan selalu dihitung)
                'is_wajib'            => true,
                'mode_harga'          => 'pakai_master',
                'urutan'              => $i,
            ]);
        }
    }

    /**
     * Sync struktur atribut+nilai+varian TANPA merusak riwayat order lama:
     *  - Attribute/value dicocokkan by nama (updateOrCreate) -> id tetap
     *    sama kalau nama tidak berubah.
     *  - Kombinasi varian yang SAMA (signature = set attribute_value_id)
     *    dipertahankan (id, harga_override existing tidak hilang kecuali
     *    di-override eksplisit dari form).
     *  - Kombinasi yang sudah tidak match struktur baru -> SOFT DELETE
     *    (bukan hard delete) supaya order_items.item_variant_id historis
     *    tetap valid.
     */
    private function syncAttributesAndVariants(Item $item, array $atributInput, array $hargaOverrideInput): void
    {
        $atributInput = array_values(array_filter($atributInput, fn ($a) => filled($a['nama'] ?? null) && filled($a['nilai'] ?? null)));

        // Snapshot variant lama (signature -> variant) SEBELUM attribute/value diubah.
        $variantLama = $item->variants()->with('attributeValues')->get()
            ->mapWithKeys(function (ItemVariant $v) {
                $sig = $v->attributeValues->pluck('id')->sort()->implode(',');
                return [$sig => $v];
            });

        $attributeIdsAktif = [];
        $valueGroupsByAttribute = []; // [attributeId => [valueId, ...]] urut sesuai input

        foreach ($atributInput as $ai => $row) {
            // updateOrCreate() tidak bisa restore soft-delete (deleted_at
            // bukan fillable) -> restore manual via set atribut langsung.
            $attribute = ItemAttribute::withTrashed()
                ->firstOrNew(['item_id' => $item->id, 'nama' => trim($row['nama'])]);
            $attribute->urutan = $ai;
            if ($attribute->trashed()) $attribute->deleted_at = null;
            $attribute->save();
            $attributeIdsAktif[] = $attribute->id;

            $nilaiList = array_values(array_filter(array_map('trim', explode(',', $row['nilai']))));
            $valueIds = [];
            foreach ($nilaiList as $vi => $nilai) {
                $value = ItemAttributeValue::withTrashed()
                    ->firstOrNew(['item_attribute_id' => $attribute->id, 'nilai' => $nilai]);
                $value->urutan = $vi;
                if ($value->trashed()) $value->deleted_at = null;
                $value->save();
                $valueIds[] = $value->id;
            }
            $valueGroupsByAttribute[] = $valueIds;

            // Value yang tidak lagi disubmit -> soft delete (attribute masih aktif)
            ItemAttributeValue::where('item_attribute_id', $attribute->id)
                ->whereNotIn('id', $valueIds)->delete();
        }

        // Attribute yang tidak lagi disubmit -> soft delete
        ItemAttribute::where('item_id', $item->id)
            ->whereNotIn('id', $attributeIdsAktif)->delete();

        // Cartesian product dari value groups (urut sesuai urutan attribute/value submit)
        $combos = [[]];
        foreach ($valueGroupsByAttribute as $valueIds) {
            $next = [];
            foreach ($combos as $combo) {
                foreach ($valueIds as $valueId) {
                    $next[] = [...$combo, $valueId];
                }
            }
            $combos = $next;
        }

        $signatureBaru = [];
        foreach ($combos as $i => $combo) {
            $sig = collect($combo)->sort()->implode(',');
            $signatureBaru[] = $sig;

            if (isset($variantLama[$sig])) {
                $variant = $variantLama[$sig];
                if (array_key_exists($i, $hargaOverrideInput) && $hargaOverrideInput[$i] !== '') {
                    $variant->harga_override = $hargaOverrideInput[$i];
                }
                $variant->is_active = true;
                $variant->urutan = $i;
                $variant->save();
                continue;
            }

            $variant = ItemVariant::create([
                'item_id'        => $item->id,
                'harga_override' => ($hargaOverrideInput[$i] ?? '') !== '' ? $hargaOverrideInput[$i] : null,
                'is_active'      => true,
                'urutan'         => $i,
            ]);
            $variant->attributeValues()->sync($combo);
        }

        // Kombinasi lama yang tidak match struktur baru -> soft delete (preserve histori order).
        // PENTING: PHP auto-cast array key numerik-string ("1") jadi integer di
        // mapWithKeys() saat membangun $variantLama -> $sig di sini bisa berupa
        // int walau $signatureBaru isinya string murni (implode selalu string).
        // WAJIB cast (string) sebelum in_array(strict) supaya produk ber-1-atribut
        // (signature "1"/"2"/dst, bukan gabungan "1,2") tidak salah dianggap
        // "tidak match" lalu ke-soft-delete semua padahal seharusnya dipertahankan.
        foreach ($variantLama as $sig => $variant) {
            if (! in_array((string) $sig, $signatureBaru, true)) {
                $variant->delete();
            }
        }
    }

}
