<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Item dummy D'mentai (Tahap 2 - Master Data, 2026-09-13) — menggantikan
     * item Berkah Mulyo (Daging/Bakso/Sosis/dst, sudah dihapus via
     * CleanupBerkahMulyoDataSeeder). Foto sengaja NULL (placeholder ikon
     * generik ditangani di level view POS nanti, Tahap 3).
     *
     * "Dimsum Mentai" (PJ-DIM-003) sengaja diberi `punya_varian=true` — jadi
     * item contoh untuk fitur Varian (lihat ItemVarianSeeder).
     *
     * Tahap 2.5 D'mentai (2026-09-14) — tipe di sini SUDAH pakai nilai BARU
     * (produk_jual/tambahan_gratis/produk_tambahan), BUKAN lagi produk_jadi/
     * lainnya. Ditemukan saat testing: migration `extend_tipe_enum_in_items_
     * table` reklasifikasi data lewat UPDATE, yang hanya efektif kalau
     * `items` SUDAH terisi saat migration jalan (skenario dev DB yang sudah
     * ada datanya). Di fresh install (migrate lalu seed dari nol), migration
     * jalan duluan sebelum tabel `items` terisi -> UPDATE-nya no-op, dan
     * seeder ini yang jadi satu-satunya sumber kebenaran nilai `tipe`. Kalau
     * pernah revert ke nilai lama di sini, POS/PenjualanService/dst ikut
     * salah (lihat whitelist tipe di `Item::bisaDijualDiCabang()` & CLAUDE.md
     * 8.3).
     */
    public function run(): void
    {
        $catId = fn (string $kode) => ItemCategory::where('kode_kategori', $kode)->value('id');

        $items = [
            // ===== Dimsum (produk jadi) =====
            ['kode_item' => 'PJ-DIM-001', 'nama_item' => 'Dimsum Ayam',       'item_category_id' => $catId('DIM'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 10000, 'harga_beli_terakhir' => 6000, 'qty_minimum' => 20],
            ['kode_item' => 'PJ-DIM-002', 'nama_item' => 'Dimsum Udang',      'item_category_id' => $catId('DIM'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 12000, 'harga_beli_terakhir' => 7500, 'qty_minimum' => 20],
            ['kode_item' => 'PJ-DIM-003', 'nama_item' => 'Dimsum Mentai',     'item_category_id' => $catId('DIM'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 15000, 'harga_beli_terakhir' => 9000, 'qty_minimum' => 20, 'punya_varian' => true],
            ['kode_item' => 'PJ-DIM-004', 'nama_item' => 'Dimsum Ayam Jamur', 'item_category_id' => $catId('DIM'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 11000, 'harga_beli_terakhir' => 6500, 'qty_minimum' => 20],

            // ===== Gyoza (produk jadi) =====
            ['kode_item' => 'PJ-GYZ-001', 'nama_item' => 'Gyoza Original', 'item_category_id' => $catId('GYZ'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 13000, 'harga_beli_terakhir' => 8000, 'qty_minimum' => 15],
            ['kode_item' => 'PJ-GYZ-002', 'nama_item' => 'Gyoza Pedas',    'item_category_id' => $catId('GYZ'), 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 14000, 'harga_beli_terakhir' => 8500, 'qty_minimum' => 15],

            // ===== Minuman (produk jadi) =====
            ['kode_item' => 'PJ-MNM-001', 'nama_item' => 'Es Teh Manis', 'item_category_id' => $catId('MNM'), 'tipe' => 'produk_jual', 'satuan' => 'gelas', 'harga_jual' => 6000, 'harga_beli_terakhir' => 2000, 'qty_minimum' => 30],
            ['kode_item' => 'PJ-MNM-002', 'nama_item' => 'Air Mineral',  'item_category_id' => $catId('MNM'), 'tipe' => 'produk_jual', 'satuan' => 'botol', 'harga_jual' => 5000, 'harga_beli_terakhir' => 3000, 'qty_minimum' => 30],

            // ===== Frozen (produk jadi) =====
            ['kode_item' => 'PJ-FRZ-001', 'nama_item' => 'Frozen Dimsum Ayam (isi 10)', 'item_category_id' => $catId('FRZ'), 'tipe' => 'produk_jual', 'satuan' => 'pack', 'harga_jual' => 35000, 'harga_beli_terakhir' => 22000, 'qty_minimum' => 10],
            ['kode_item' => 'PJ-FRZ-002', 'nama_item' => 'Frozen Gyoza (isi 10)',       'item_category_id' => $catId('FRZ'), 'tipe' => 'produk_jual', 'satuan' => 'pack', 'harga_jual' => 38000, 'harga_beli_terakhir' => 24000, 'qty_minimum' => 10],

            // ===== Bahan Baku =====
            ['kode_item' => 'BB-BHN-001', 'nama_item' => 'Kulit Dimsum',   'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 300, 'qty_minimum' => 200],
            ['kode_item' => 'BB-BHN-002', 'nama_item' => 'Isian Ayam',     'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_beli_terakhir' => 45000, 'qty_minimum' => 5],
            ['kode_item' => 'BB-BHN-003', 'nama_item' => 'Isian Udang',    'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_beli_terakhir' => 70000, 'qty_minimum' => 5],
            ['kode_item' => 'BB-BHN-004', 'nama_item' => 'Mayo Mentai',    'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_beli_terakhir' => 55000, 'qty_minimum' => 3],
            ['kode_item' => 'BB-BHN-005', 'nama_item' => 'Saus Gyoza',     'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'liter', 'harga_beli_terakhir' => 40000, 'qty_minimum' => 3],
            ['kode_item' => 'BB-BHN-006', 'nama_item' => 'Minyak Goreng',  'item_category_id' => $catId('BHN'), 'tipe' => 'bahan_baku', 'satuan' => 'liter', 'harga_beli_terakhir' => 18000, 'qty_minimum' => 10],

            // ===== Kemasan =====
            ['kode_item' => 'KM-KMS-001', 'nama_item' => 'Kotak Dimsum',    'item_category_id' => $catId('KMS'), 'tipe' => 'kemasan', 'satuan' => 'pcs', 'harga_beli_terakhir' => 800, 'qty_minimum' => 100],
            ['kode_item' => 'KM-KMS-002', 'nama_item' => 'Sumpit',          'item_category_id' => $catId('KMS'), 'tipe' => 'kemasan', 'satuan' => 'pcs', 'harga_beli_terakhir' => 200, 'qty_minimum' => 200],
            ['kode_item' => 'KM-KMS-003', 'nama_item' => 'Plastik Kemasan', 'item_category_id' => $catId('KMS'), 'tipe' => 'kemasan', 'satuan' => 'pcs', 'harga_beli_terakhir' => 300, 'qty_minimum' => 200],

            // ===== Item Tambahan (add-on POS) =====
            ['kode_item' => 'TB-TMB-001', 'nama_item' => 'Garpu Plastik',     'item_category_id' => $catId('TMB'), 'tipe' => 'tambahan_gratis', 'satuan' => 'pcs', 'harga_jual' => 0,   'harga_beli_terakhir' => 100, 'qty_minimum' => 100],
            ['kode_item' => 'TB-TMB-002', 'nama_item' => 'Saus Cabai Extra',  'item_category_id' => $catId('TMB'), 'tipe' => 'produk_tambahan', 'satuan' => 'pcs', 'harga_jual' => 500, 'harga_beli_terakhir' => 150, 'qty_minimum' => 50],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(['kode_item' => $item['kode_item']], array_merge($item, ['is_active' => true]));
        }
    }
}
