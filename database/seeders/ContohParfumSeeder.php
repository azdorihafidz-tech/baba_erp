<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ParfumKonsentrasi;
use App\Models\ProdukOnline;
use App\Models\ProdukOnlineHarga;
use App\Models\ResepProduksi;
use App\Models\User;
use App\Services\ParfumBaruService;
use App\Services\ProduksiService;
use App\Services\StokService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * DATA CONTOH lokal ERP BABA Tahap 1 (BUKAN untuk go-live; tidak terdaftar di GoLiveSeeder/DatabaseSeeder).
 * Jalankan: php artisan db:seed --class=ContohParfumSeeder
 * Isi: bahan & kemasan contoh + stok, 3 parfum EDP lewat Buat Parfum (30/50/100 ml),
 * 1 racik sedang diperam (Ocean Breeze), 1 alur lengkap sampai botol (Vanilla Oud).
 * Semua barang contoh berketerangan "Data contoh" supaya mudah dikenali/dihapus.
 */
class ContohParfumSeeder extends Seeder
{
    public const KETERANGAN = 'Data contoh (ContohParfumSeeder) — boleh dihapus';

    public function run(): void
    {
        $gudang = Cabang::where('kode_cabang', 'HO-01')->first() ?? Cabang::where('tipe', 'gudang_pusat')->first();
        $owner = User::where('role', 'owner')->first();
        if (! $gudang || ! $owner) {
            $this->command?->error('Gudang Pusat / Owner belum ada — jalankan GoLiveSeeder dulu.');

            return;
        }
        if (Item::where('cabang_id', $gudang->id)->where('nama_item', 'Bibit Ocean Breeze')->exists()) {
            $this->command?->info('Data contoh parfum sudah ada — dilewati.');
            $this->produkOnline($gudang);

            return;
        }
        Auth::login($owner);
        session(['active_cabang_id' => $gudang->id]);

        $katBahan = ItemCategory::firstOrCreate(['nama_kategori' => 'Bahan Parfum'], ['kode_kategori' => 'BHP', 'deskripsi' => 'Bibit, fixative, alkohol.']);
        $katKemasan = ItemCategory::firstOrCreate(['nama_kategori' => 'Kemasan Parfum'], ['kode_kategori' => 'KMP', 'deskripsi' => 'Botol, sprayer, label, box.']);
        $stok = app(StokService::class);

        $b = [];
        foreach ([
            // kunci => [nama, satuan, harga per satuan, stok awal, kategori]
            'ocean'   => ['Bibit Ocean Breeze', 'ml', 1500, 3000, $katBahan],
            'vanilla' => ['Bibit Vanilla Oud', 'ml', 1800, 3000, $katBahan],
            'rose'    => ['Bibit Rose Musk', 'ml', 1300, 3000, $katBahan],
            'fix'     => ['Fixative', 'ml', 350, 2000, $katBahan],
            'alk'     => ['Alkohol 96%', 'liter', 55000, 40, $katBahan],
            'b30'     => ['Botol 30 ml', 'pcs', 2500, 300, $katKemasan],
            'b50'     => ['Botol 50 ml', 'pcs', 3500, 300, $katKemasan],
            'b100'    => ['Botol 100 ml', 'pcs', 5000, 200, $katKemasan],
            'spr'     => ['Sprayer', 'pcs', 800, 800, $katKemasan],
            'lbl'     => ['Label Parfum', 'pcs', 300, 800, $katKemasan],
            'box'     => ['Box Parfum', 'pcs', 1500, 800, $katKemasan],
        ] as $k => [$nama, $satuan, $harga, $qty, $kat]) {
            $b[$k] = Item::create([
                'cabang_id' => $gudang->id, 'kode_item' => 'CTH-' . strtoupper($k), 'nama_item' => $nama, 'item_category_id' => $kat->id,
                'tipe' => $kat->id === $katKemasan->id ? 'kemasan' : 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => $satuan,
                'track_stok' => true, 'harga_jual' => 0, 'harga_beli_terakhir' => $harga, 'qty_minimum' => 0, 'is_active' => true,
                'deskripsi' => self::KETERANGAN,
            ]);
            $stok->masuk($b[$k]->id, $gudang->id, $qty, 'Stok awal data contoh', 'contoh', null, $harga);
        }

        $edp = ParfumKonsentrasi::where('nama', 'EDP')->firstOrFail();
        $parfum = app(ParfumBaruService::class);
        foreach (['ocean' => 'Ocean Breeze', 'vanilla' => 'Vanilla Oud', 'rose' => 'Rose Musk'] as $k => $aroma) {
            $parfum->buat([
                'cabang_id' => $gudang->id, 'bibit_id' => $b[$k]->id, 'nama_aroma' => $aroma, 'konsentrasi_id' => $edp->id,
                'fixative_id' => $b['fix']->id, 'alkohol_id' => $b['alk']->id,
                'ukuran' => [['ml' => 30, 'botol_id' => $b['b30']->id], ['ml' => 50, 'botol_id' => $b['b50']->id], ['ml' => 100, 'botol_id' => $b['b100']->id]],
                'kemasan_ids' => [$b['spr']->id, $b['lbl']->id, $b['box']->id],
            ], $owner->id);
        }
        Item::where('cabang_id', $gudang->id)->where(fn ($q) => $q->where('nama_item', 'like', 'Cairan %')->orWhere('nama_item', 'like', '% EDP % ml'))
            ->update(['deskripsi' => self::KETERANGAN]);

        $produksi = app(ProduksiService::class);
        $resep = fn (string $nama) => ResepProduksi::whereHas('item', fn ($q) => $q->where('cabang_id', $gudang->id)->where('nama_item', $nama))->firstOrFail();

        // Vanilla Oud: diracik 16 hari lalu → selesai peram (susut 30 ml) → isi 20 botol 50 ml
        $racikVanilla = $produksi->produksi([
            'cabang_id' => $gudang->id, 'resep_produksi_id' => $resep('Cairan Vanilla Oud EDP')->id,
            'tanggal' => now()->subDays(16)->toDateString(), 'qty_rencana' => 2000, 'catatan' => 'Data contoh',
        ], $owner->id);
        $produksi->selesaiPeram($racikVanilla, 1970, null, $owner->id);
        $produksi->produksi([
            'cabang_id' => $gudang->id, 'resep_produksi_id' => $resep('Vanilla Oud EDP 50 ml')->id,
            'tanggal' => now()->toDateString(), 'qty_rencana' => 20, 'qty_hasil' => 20, 'catatan' => 'Data contoh',
        ], $owner->id);

        // Ocean Breeze: diracik hari ini → sedang diperam 14 hari
        $produksi->produksi([
            'cabang_id' => $gudang->id, 'resep_produksi_id' => $resep('Cairan Ocean Breeze EDP')->id,
            'tanggal' => now()->toDateString(), 'qty_rencana' => 3000, 'catatan' => 'Data contoh',
        ], $owner->id);

        $this->produkOnline($gudang);
        $this->command?->info('Data contoh parfum dibuat di ' . $gudang->nama_cabang . '.');
    }

    /** Tahap 2a — produk online contoh (botol 50 ml) dengan harga contoh: tetap, gudang, Distributor (21), Reseller (24). */
    private function produkOnline(Cabang $gudang): void
    {
        foreach (['Ocean Breeze' => 'Unisex', 'Vanilla Oud' => 'Pria', 'Rose Musk' => 'Wanita'] as $aroma => $kategori) {
            $item = Item::where('cabang_id', $gudang->id)->where('nama_item', "{$aroma} EDP 50 ml")->first();
            if (! $item || ProdukOnline::where('item_id', $item->id)->exists()) {
                continue;
            }
            $p = ProdukOnline::create([
                'item_id' => $item->id, 'nama_tampil' => "{$aroma} EDP 50 ml", 'kategori' => $kategori, 'aktif' => true,
                'deskripsi' => 'Data contoh — Eau de Parfum 50 ml.',
            ]);
            foreach ([['tetap', null, 150000], ['gudang', null, 135000], ['jabatan', 21, 110000], ['jabatan', 24, 125000]] as [$jenis, $jab, $harga]) {
                ProdukOnlineHarga::create(['produk_online_id' => $p->id, 'jenis' => $jenis, 'jabatan_id' => $jab, 'harga' => $harga]);
            }
        }
        // Tahap 2b — rekening contoh & tagihan contoh untuk Distributor (21): batas Rp 1.000.000, tempo 14 hari
        if (\Illuminate\Support\Facades\Schema::hasTable('toko_online_rekening')) {
            \App\Models\TokoOnlineRekening::firstOrCreate(['cabang_id' => $gudang->id, 'nomor' => '0000000000'],
                ['bank' => 'BCA', 'atas_nama' => 'BABA PARFUME (rekening contoh)', 'aktif' => true]);
            \App\Models\AturanTagihan::firstOrCreate(['cakupan' => 'jabatan', 'jabatan_id' => 21, 'aktif' => true],
                ['batas_nominal' => 1000000, 'tempo_hari' => 14, 'catatan' => 'Data contoh']);
        }
        // Tahap 2c — contoh: semua member boleh belanja ke gudang & ke upline
        if (\Illuminate\Support\Facades\Schema::hasTable('toko_online_aturan_sumber')) {
            \App\Models\AturanSumber::firstOrCreate(['cakupan' => 'semua', 'aktif' => true],
                ['boleh_gudang' => true, 'boleh_upline' => true, 'catatan' => 'Data contoh']);
        }
        // Tahap 2f — lokal: toko dibuka untuk semua member (go-live: bawaan "tutup", dibuka Owner)
        if (\Illuminate\Support\Facades\Schema::hasColumn('toko_online_pengaturan', 'status_toko')) {
            \App\Models\TokoOnlinePengaturan::ambil()->update(['status_toko' => 'buka']);
        }
    }
}
