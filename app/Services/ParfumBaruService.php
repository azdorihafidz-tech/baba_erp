<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ParfumKonsentrasi;
use App\Models\ResepProduksi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ERP BABA Tahap 1b — "Buat Parfum": dari 1 aroma (bibit) + 1 konsentrasi + beberapa ukuran botol,
 * buat sekaligus:
 *  - barang Cairan "<Aroma> <Konsentrasi>" (ml) + Resep racik per 1.000 ml dari persen konsentrasi
 *    (bibit / fixative / alkohol) dengan lama pemeraman konsentrasi;
 *  - per ukuran: barang "<Aroma> <Konsentrasi> <N> ml" (pcs) + Resep isi botol
 *    (N ml cairan + 1 botol + 1 dari setiap kemasan tambahan).
 * Semua dalam satu transaksi lewat ProduksiService::simpanResep (aturan gudang & bahan sama).
 */
class ParfumBaruService
{
    public const VOLUME_RACIK = 1000; // resep racik dihitung per 1.000 ml cairan

    public function __construct(private ProduksiService $produksi) {}

    /**
     * @param array{cabang_id:int, bibit_id:int, nama_aroma:string, konsentrasi_id:int, fixative_id:?int, alkohol_id:int,
     *              ukuran: array<int, array{ml:float, botol_id:int}>, kemasan_ids?: array<int,int>} $data
     * @return array{cairan: Item, botol: array<int, Item>}
     */
    public function buat(array $data, int $userId): array
    {
        $gudangId = (int) $data['cabang_id'];
        if (! ProduksiService::gudangUntuk()->contains('id', $gudangId)) {
            throw new \Exception('Parfum hanya bisa dibuat di gudang (cabang tipe Gudang Pusat) yang Anda kelola.');
        }
        $k = ParfumKonsentrasi::aktif()->find((int) $data['konsentrasi_id']);
        if (! $k) {
            throw new \Exception('Konsentrasi tidak ditemukan atau nonaktif.');
        }
        $boleh = ProduksiService::barangOptions($gudangId)->keyBy('id');
        $ambil = function ($id, string $label) use ($boleh): Item {
            $item = $id ? $boleh->get((int) $id) : null;
            if (! $item) {
                throw new \Exception("{$label} harus barang milik gudang ini (Bahan Baku & Kemasan).");
            }

            return $item;
        };

        $bibit = $ambil($data['bibit_id'] ?? null, 'Bibit');
        $alkohol = $ambil($data['alkohol_id'] ?? null, 'Alkohol');
        $fixative = (float) $k->persen_fixative > 0 ? $ambil($data['fixative_id'] ?? null, 'Fixative') : null;
        if (collect([$bibit->id, $alkohol->id, $fixative?->id])->filter()->duplicates()->isNotEmpty()) {
            throw new \Exception('Bibit, fixative, dan alkohol harus barang yang berbeda.');
        }

        $aroma = trim(preg_replace('/\s+/', ' ', (string) $data['nama_aroma']));
        if ($aroma === '') {
            throw new \Exception('Isi nama aroma.');
        }

        $ukuran = collect($data['ukuran'] ?? [])
            ->filter(fn ($u) => (float) ($u['ml'] ?? 0) > 0 && ! empty($u['botol_id']))
            ->map(fn ($u) => ['ml' => round((float) $u['ml'], 2), 'botol' => $ambil($u['botol_id'], 'Botol')])
            ->values();
        if ($ukuran->isEmpty()) {
            throw new \Exception('Isi minimal satu ukuran botol beserta barang botolnya.');
        }
        if ($ukuran->pluck('ml')->duplicates()->isNotEmpty()) {
            throw new \Exception('Ada ukuran botol yang sama lebih dari sekali.');
        }
        $kemasan = collect($data['kemasan_ids'] ?? [])->filter()->map(fn ($id) => $ambil($id, 'Kemasan tambahan'))->unique('id')->values();
        if ($kemasan->pluck('id')->intersect($ukuran->pluck('botol.id'))->isNotEmpty()) {
            throw new \Exception('Botol tidak perlu dipilih lagi di Kemasan tambahan.');
        }

        $namaCairan = "Cairan {$aroma} {$k->nama}";
        $namaBotol = fn (float $ml) => "{$aroma} {$k->nama} " . self::ml($ml) . ' ml';
        $semuaNama = $ukuran->map(fn ($u) => $namaBotol($u['ml']))->prepend($namaCairan);
        $sudahAda = Item::milikCabang($gudangId)->whereIn('nama_item', $semuaNama)->pluck('nama_item');
        if ($sudahAda->isNotEmpty()) {
            throw new \Exception('Sudah ada barang dengan nama: ' . $sudahAda->implode(', ') . '. Ubah nama aroma, atau pakai Resep Produksi yang sudah ada.');
        }

        return DB::transaction(function () use ($gudangId, $k, $bibit, $alkohol, $fixative, $aroma, $ukuran, $kemasan, $namaCairan, $namaBotol, $userId) {
            $katCairan = $this->kategori('CRN', 'Cairan Parfum', 'Cairan hasil racik (bibit + fixative + alkohol), satuan ml.');
            $katBotol = $this->kategori('PFM', 'Parfum Botol', 'Parfum jadi dalam botol, satuan pcs.');

            $cairan = $this->barang($gudangId, $namaCairan, 'ml', $katCairan->id, 'CRN-' . $aroma . '-' . $k->nama);

            $volume = self::VOLUME_RACIK;
            $bahan = [['item_id' => $bibit->id, 'qty' => round($volume * (float) $k->persen_bibit / 100, 3), 'satuan' => self::satuanTakaran($bibit)]];
            if ($fixative) {
                $bahan[] = ['item_id' => $fixative->id, 'qty' => round($volume * (float) $k->persen_fixative / 100, 3), 'satuan' => self::satuanTakaran($fixative)];
            }
            $bahan[] = ['item_id' => $alkohol->id, 'qty' => round($volume * $k->persen_alkohol / 100, 3), 'satuan' => self::satuanTakaran($alkohol)];

            $this->produksi->simpanResep([
                'cabang_id' => $gudangId, 'item_id' => $cairan->id, 'qty_hasil' => $volume, 'lama_peram_hari' => $k->lama_peram_hari,
                'catatan' => "Dibuat lewat Buat Parfum: {$k->nama} ({$k->keterangan}) — bibit " . self::ml((float) $k->persen_bibit) . '%'
                    . ($fixative ? ', fixative ' . self::ml((float) $k->persen_fixative) . '%' : '') . ', alkohol ' . self::ml($k->persen_alkohol) . '%.',
                'is_active' => true, 'bahan' => $bahan,
            ], null, $userId);

            $botolDibuat = [];
            foreach ($ukuran as $u) {
                $nama = $namaBotol($u['ml']);
                $botol = $this->barang($gudangId, $nama, 'pcs', $katBotol->id, 'PFM-' . $aroma . '-' . $k->nama . '-' . self::ml($u['ml']));
                $isi = [['item_id' => $cairan->id, 'qty' => $u['ml'], 'satuan' => 'ml'], ['item_id' => $u['botol']->id, 'qty' => 1, 'satuan' => $u['botol']->satuan]];
                foreach ($kemasan as $km) {
                    $isi[] = ['item_id' => $km->id, 'qty' => 1, 'satuan' => $km->satuan];
                }
                $this->produksi->simpanResep([
                    'cabang_id' => $gudangId, 'item_id' => $botol->id, 'qty_hasil' => 1, 'lama_peram_hari' => 0,
                    'catatan' => 'Dibuat lewat Buat Parfum: isi botol ' . self::ml($u['ml']) . ' ml.',
                    'is_active' => true, 'bahan' => $isi,
                ], null, $userId);
                $botolDibuat[] = $botol;
            }

            return ['cairan' => $cairan, 'botol' => $botolDibuat];
        });
    }

    /** Takaran resep mengikuti jenis satuan stok: berat → g, selain itu ml (1 ml dianggap setara 1 g untuk bibit cair). */
    public static function satuanTakaran(Item $item): string
    {
        return in_array(strtolower(trim((string) $item->satuan)), ['g', 'gr', 'gram', 'kg', 'kilogram', 'ons'], true) ? 'g' : 'ml';
    }

    public static function ml(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }

    private function kategori(string $kode, string $nama, string $deskripsi): ItemCategory
    {
        return ItemCategory::firstOrCreate(['nama_kategori' => $nama], ['kode_kategori' => $kode, 'deskripsi' => $deskripsi]);
    }

    private function barang(int $gudangId, string $nama, string $satuan, int $kategoriId, string $kodeDasar): Item
    {
        $dasar = substr(strtoupper(Str::slug($kodeDasar, '-')), 0, 26);
        $kode = $dasar;
        for ($i = 2; Item::withTrashed()->where('kode_item', $kode)->where(fn ($q) => $q->where('cabang_id', $gudangId)->orWhereNull('cabang_id'))->exists(); $i++) {
            $kode = $dasar . '-' . $i;
        }

        return Item::create([
            'cabang_id' => $gudangId, 'kode_item' => $kode, 'nama_item' => $nama, 'item_category_id' => $kategoriId,
            'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => $satuan, 'track_stok' => true,
            'harga_jual' => 0, 'harga_beli_terakhir' => 0, 'qty_minimum' => 0, 'is_active' => true,
        ]);
    }
}
