<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CabangSeeder::class,
            PermissionSeeder::class,        // Harus sebelum UserSeeder & RolePermissionSeeder
            JenisOlahanSeeder::class,
            KategoriTransaksiSeeder::class, // Buat kategori keuangan dinamis
            KategoriPengeluaranBackfillSeeder::class, // Backfill kategori_pengeluaran dari data lama (idempotent)
            UserSeeder::class,
            EvaluationAspectSeeder::class,
            ItemCategorySeeder::class,
            ItemSeeder::class,
            ResepBumbuSeeder::class,        // Seed 3 resep bumbu starting point (idempotent, butuh ItemSeeder & JenisOlahanSeeder)
            AssetCategorySeeder::class,
            PembelianPenjualanSeeder::class,
            BackfillStockQtyMinimumSeeder::class, // Backfill stocks.qty_minimum dari items.qty_minimum (idempotent)
            HRSeeder::class,
            RolePermissionSeeder::class,    // Harus paling akhir (butuh semua permission sudah ada)
            HakAksesMenuSeeder::class,      // 4.43 — izin per menu (setelah RolePermissionSeeder)
            TooltipAdjustmentSeeder::class, // Tooltip percontohan modul adjustment
            PanduanPosSeeder::class,        // Panduan percontohan modul POS
            PanduanStubSeeder::class,       // Panduan stub 46 menu + update modul POS → penjualan
            PanduanKontenSeeder::class,     // Isi konten nyata 46 panduan dari stub
            Sprint434PanduanUpdateSeeder::class, // Append section 2026-10-08 ke 9 panduan existing
            TooltipKontenSeeder::class,     // Tooltip field-field bermakna seluruh aplikasi
            ClosingPanduanSeeder::class,    // Panduan + tooltip Closing / Tutup Buku (2026-10-17)
            Perbaikan438PanduanSeeder::class, // Panduan + tooltip Perbaikan Harga dst (2026-10-18)
            IdentitasUsahaSeeder::class,     // Panduan + tooltip Identitas Usaha (2026-11-03)
            LabelProdukSeeder::class,        // Izin + panduan + tooltip Label Produk (2026-11-03)
            LaporanPenjualanKasirSeeder::class, // 4.46 — izin + panduan Laporan di menu Penjualan
            PosUbahHargaSeeder::class,          // 4.47 — izin Ubah Harga Jual di POS + panduan POS
            WarnaAplikasiSeeder::class,         // 4.48 — panduan & tooltip Warna Aplikasi (setelah IdentitasUsahaSeeder)
            Porting449Seeder::class,           // 4.49 — porting coffeeshop: label gambar, POS HP, produksi, kiriman, retur (setelah LabelProdukSeeder)
            ProduksiParfumSeeder::class,         // BABA Tahap 1 — konsentrasi parfum, panduan Produksi Parfum (setelah Porting449Seeder)
        ]);
    }
}
