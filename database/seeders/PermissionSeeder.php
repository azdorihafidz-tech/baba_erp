<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Cabang
            ['name' => 'cabang.view', 'display_name' => 'Lihat Cabang', 'group' => 'cabang'],
            ['name' => 'cabang.create', 'display_name' => 'Tambah Cabang', 'group' => 'cabang'],
            ['name' => 'cabang.edit', 'display_name' => 'Edit Cabang', 'group' => 'cabang'],
            ['name' => 'cabang.delete', 'display_name' => 'Hapus Cabang', 'group' => 'cabang'],

            // User Management
            ['name' => 'user.view', 'display_name' => 'Lihat User', 'group' => 'user'],
            ['name' => 'user.create', 'display_name' => 'Tambah User', 'group' => 'user'],
            ['name' => 'user.edit', 'display_name' => 'Edit User', 'group' => 'user'],
            ['name' => 'user.delete', 'display_name' => 'Hapus User', 'group' => 'user'],

            // Penjualan / Order
            ['name' => 'order.view',     'display_name' => 'Lihat Order',     'group' => 'penjualan'],
            ['name' => 'order.create',   'display_name' => 'Buat Order',      'group' => 'penjualan'],
            ['name' => 'order.edit',     'display_name' => 'Edit Order',      'group' => 'penjualan'],
            ['name' => 'order.delete',   'display_name' => 'Hapus Order',     'group' => 'penjualan'],
            ['name' => 'order.batalkan',    'display_name' => 'Batalkan Order',            'group' => 'penjualan'],
            ['name' => 'order.kembalikan', 'display_name' => 'Kembalikan Order Dibatalkan', 'group' => 'penjualan'],
            // Tahap 7 D'mentai (Bug 2b) — batalkan BILL TERSIMPAN (status
            // Pending, belum dibayar/potong stok sama sekali) di POS.
            // SENGAJA permission TERPISAH dari 'order.batalkan' (yang
            // membatalkan order Selesai — reversal stok+kas kompleks &
            // beresiko tinggi). Membatalkan bill Pending jauh lebih ringan
            // (reuse PenjualanService::batalkan(), tidak ada apapun yang
            // di-restore krn memang belum ada yang dipotong/dicatat).
            ['name' => 'order.bill_tersimpan.batalkan', 'display_name' => 'Batalkan Bill Tersimpan (POS)', 'group' => 'penjualan'],

            // Pelanggan
            ['name' => 'pelanggan.view',   'display_name' => 'Lihat Pelanggan',   'group' => 'pelanggan'],
            ['name' => 'pelanggan.create', 'display_name' => 'Tambah Pelanggan',  'group' => 'pelanggan'],
            ['name' => 'pelanggan.edit',   'display_name' => 'Edit Pelanggan',    'group' => 'pelanggan'],
            ['name' => 'pelanggan.delete', 'display_name' => 'Hapus Pelanggan',   'group' => 'pelanggan'],

            // Stok
            ['name' => 'stok.view', 'display_name' => 'Lihat Stok', 'group' => 'stok'],
            ['name' => 'stok.adjustment', 'display_name' => 'Penyesuaian Stok', 'group' => 'stok'],
            ['name' => 'stok.request', 'display_name' => 'Buat Permintaan Stok', 'group' => 'stok'],
            ['name' => 'stok.transfer', 'display_name' => 'Transfer Stok', 'group' => 'stok'],
            ['name' => 'stok.minimum.set', 'display_name' => 'Set Threshold Stok Minimum per Lokasi', 'group' => 'stok'],
            // Hapus/Reset Stok — sengaja TIDAK di-assign ke role manapun secara
            // default di RolePermissionSeeder, harus dicentang manual per role
            // lewat UI Role & Hak Akses. Owner tetap bypass via Gate::before.
            ['name' => 'stok.hapus.reset', 'display_name' => 'Hapus/Reset Stok ke 0 (Destruktif)', 'group' => 'stok'],

            // Item / Master Barang
            ['name' => 'item.view',   'display_name' => 'Lihat Master Barang',  'group' => 'stok'],
            // Sprint 4.34 (2026-10-08) — item.create/edit/delete DEPRECATED:
            // menu Master Barang Lengkap jadi read-only overview. CRUD lewat
            // menu Bahan Baku (`master.bahan_baku.*`) atau Produk Jual
            // (`master.produk_jual.*`). Permission tetap di-seed utk backward
            // compat (bookmark/panduan lama) tapi tombolnya sudah hilang dari UI.
            ['name' => 'item.create', 'display_name' => 'Tambah Master Barang [DEPRECATED]', 'group' => 'stok'],
            ['name' => 'item.edit',   'display_name' => 'Edit Master Barang [DEPRECATED]',   'group' => 'stok'],
            ['name' => 'item.delete', 'display_name' => 'Hapus Master Barang [DEPRECATED]',  'group' => 'stok'],

            // Tahap 2.5 D'mentai — split menu Master Item jadi 2: Bahan Baku &
            // Kemasan (murni CRUD sederhana) vs Produk Jual (foto+resep+varian+
            // outlet). Namespace 'master.*' konsisten dgn master.item_varian.*
            // yang sudah ada.
            ['name' => 'master.bahan_baku.view',   'display_name' => 'Lihat Bahan Baku & Kemasan',  'group' => 'master'],
            ['name' => 'master.bahan_baku.create', 'display_name' => 'Tambah Bahan Baku & Kemasan', 'group' => 'master'],
            ['name' => 'master.bahan_baku.edit',   'display_name' => 'Edit Bahan Baku & Kemasan',   'group' => 'master'],
            ['name' => 'master.bahan_baku.delete', 'display_name' => 'Hapus Bahan Baku & Kemasan',  'group' => 'master'],
            ['name' => 'master.produk_jual.view',   'display_name' => 'Lihat Produk Jual',  'group' => 'master'],
            ['name' => 'master.produk_jual.create', 'display_name' => 'Tambah Produk Jual', 'group' => 'master'],
            ['name' => 'master.produk_jual.edit',   'display_name' => 'Edit Produk Jual',   'group' => 'master'],
            ['name' => 'master.produk_jual.delete', 'display_name' => 'Hapus Produk Jual',  'group' => 'master'],

            // Master Jenis Menu (nama tampilan; permission name tetap "jenis-olahan.*", zero migration)
            ['name' => 'jenis-olahan.view',   'display_name' => 'Lihat Master Jenis Menu',  'group' => 'penjualan'],
            ['name' => 'jenis-olahan.manage', 'display_name' => 'Kelola Master Jenis Menu', 'group' => 'penjualan'],

            // Pembelian
            ['name' => 'pembelian.view', 'display_name' => 'Lihat Pembelian', 'group' => 'pembelian'],
            ['name' => 'pembelian.create', 'display_name' => 'Buat PO', 'group' => 'pembelian'],
            ['name' => 'pembelian.approve', 'display_name' => 'Approve PO', 'group' => 'pembelian'],
            ['name' => 'pembelian.delete', 'display_name' => 'Hapus Purchase Order', 'group' => 'pembelian'],
            ['name' => 'pembelian.kirim-supplier', 'display_name' => 'Kirim PO ke Supplier', 'group' => 'pembelian'],
            ['name' => 'pembelian.terima', 'display_name' => 'Terima Barang dari PO', 'group' => 'pembelian'],

            // Keuangan
            ['name' => 'keuangan.view', 'display_name' => 'Lihat Keuangan', 'group' => 'keuangan'],
            ['name' => 'keuangan.create', 'display_name' => 'Tambah Transaksi', 'group' => 'keuangan'],
            ['name' => 'keuangan.edit', 'display_name' => 'Edit Transaksi Manual', 'group' => 'keuangan'],
            ['name' => 'keuangan.delete', 'display_name' => 'Hapus Transaksi Manual', 'group' => 'keuangan'],
            ['name' => 'laporan.view', 'display_name' => 'Lihat Laporan', 'group' => 'keuangan'],

            // HR
            ['name' => 'karyawan.view',   'display_name' => 'Lihat Karyawan',   'group' => 'hr'],
            ['name' => 'karyawan.create', 'display_name' => 'Tambah Karyawan',  'group' => 'hr'],
            ['name' => 'karyawan.edit',   'display_name' => 'Edit Karyawan',    'group' => 'hr'],
            ['name' => 'karyawan.delete', 'display_name' => 'Hapus Karyawan',   'group' => 'hr'],
            ['name' => 'absensi.view', 'display_name' => 'Lihat Absensi', 'group' => 'hr'],
            ['name' => 'absensi.manage', 'display_name' => 'Kelola Absensi', 'group' => 'hr'],
            ['name' => 'penggajian.view', 'display_name' => 'Lihat Penggajian', 'group' => 'hr'],
            ['name' => 'penggajian.manage', 'display_name' => 'Kelola Penggajian', 'group' => 'hr'],
            ['name' => 'cuti.view',    'display_name' => 'Lihat Pengajuan Cuti',   'group' => 'hr'],
            ['name' => 'cuti.create',  'display_name' => 'Ajukan/Edit Cuti',       'group' => 'hr'],
            ['name' => 'cuti.approve', 'display_name' => 'Setujui/Tolak Cuti',     'group' => 'hr'],
            ['name' => 'cuti.delete',  'display_name' => 'Hapus Pengajuan Cuti',   'group' => 'hr'],

            // Evaluasi
            ['name' => 'evaluasi.view', 'display_name' => 'Lihat Evaluasi', 'group' => 'evaluasi'],
            ['name' => 'evaluasi.manage', 'display_name' => 'Kelola Evaluasi', 'group' => 'evaluasi'],
            ['name' => 'evaluasi.fill', 'display_name' => 'Isi Form Penilaian', 'group' => 'evaluasi'],

            // Aset
            ['name' => 'aset.view', 'display_name' => 'Lihat Aset', 'group' => 'aset'],
            ['name' => 'aset.manage', 'display_name' => 'Kelola Aset', 'group' => 'aset'],
            // Auto Depresiasi (Fase 1 Akuntansi) — trigger manual bulk "Generate
            // Depresiasi Bulan Ini". Default: Owner + Admin Pusat (lihat RolePermissionSeeder).
            ['name' => 'aset.depresiasi.auto', 'display_name' => 'Generate Depresiasi Otomatis (Bulk)', 'group' => 'aset'],

            // BEP
            ['name' => 'bep.view', 'display_name' => 'Lihat BEP', 'group' => 'bep'],
            ['name' => 'bep.manage', 'display_name' => 'Kelola BEP', 'group' => 'bep'],

            // Antrian Produksi
            ['name' => 'antrian.lihat',   'display_name' => 'Lihat Antrian Produksi', 'group' => 'antrian'],
            ['name' => 'antrian.kelola',  'display_name' => 'Kelola Antrian Produksi', 'group' => 'antrian'],
            ['name' => 'antrian.display', 'display_name' => 'Display TV Antrian', 'group' => 'antrian'],

            // Pengaturan Umum
            ['name' => 'pengaturan.umum_lihat', 'display_name' => 'Lihat Pengaturan Umum', 'group' => 'pengaturan'],
            ['name' => 'pengaturan.umum_edit',  'display_name' => 'Edit Pengaturan Umum', 'group' => 'pengaturan'],

            // Keuangan — Kas (Phase 1A)
            ['name' => 'keuangan.kas_view',   'display_name' => 'Lihat Kas',   'group' => 'keuangan'],
            ['name' => 'keuangan.kas_create', 'display_name' => 'Tambah Kas',  'group' => 'keuangan'],
            ['name' => 'keuangan.kas_edit',   'display_name' => 'Edit Kas',    'group' => 'keuangan'],
            ['name' => 'keuangan.kas_delete', 'display_name' => 'Hapus Kas',   'group' => 'keuangan'],

            // Laci Kasir (Cash Drawer)
            ['name' => 'kas.buka_laci', 'display_name' => 'Buka Laci Kasir Manual', 'group' => 'keuangan'],

            // Kategori Transaksi Dinamis (Phase 1A)
            ['name' => 'kategori.view',   'display_name' => 'Lihat Kategori Transaksi',   'group' => 'keuangan'],
            ['name' => 'kategori.create', 'display_name' => 'Tambah Kategori Transaksi',  'group' => 'keuangan'],
            ['name' => 'kategori.edit',   'display_name' => 'Edit Kategori Transaksi',    'group' => 'keuangan'],
            ['name' => 'kategori.delete', 'display_name' => 'Hapus Kategori Transaksi',   'group' => 'keuangan'],

            // Setoran ke Pusat
            // Nama permission (key) SENGAJA tidak diubah ('setoran.*') — cuma
            // display_name yang di-rename ke istilah "Transfer Dana" biar
            // konsisten dengan rename UI, supaya assignment role existing di
            // RolePermissionSeeder tidak perlu disentuh sama sekali.
            ['name' => 'setoran.view',   'display_name' => 'Lihat Transfer Dana',            'group' => 'keuangan'],
            ['name' => 'setoran.create', 'display_name' => 'Buat Transfer Dana',              'group' => 'keuangan'],
            ['name' => 'setoran.batal',  'display_name' => 'Batal Transfer Dana (Pengirim)',  'group' => 'keuangan'],
            ['name' => 'setoran.terima', 'display_name' => 'Terima/Tolak Transfer Dana',      'group' => 'keuangan'],
            ['name' => 'setoran.delete', 'display_name' => 'Hapus Permanen Transfer Dana',    'group' => 'keuangan'],

            // Recurring Transaction
            ['name' => 'recurring.view',   'display_name' => 'Lihat Transaksi Berulang',   'group' => 'keuangan'],
            ['name' => 'recurring.create', 'display_name' => 'Buat/Generate Transaksi Berulang', 'group' => 'keuangan'],
            ['name' => 'recurring.edit',   'display_name' => 'Edit Transaksi Berulang',    'group' => 'keuangan'],
            ['name' => 'recurring.delete', 'display_name' => 'Hapus Transaksi Berulang',   'group' => 'keuangan'],

            // Laporan Setoran Harian (konsolidasi) — sengaja TIDAK di-assign ke role
            // manapun secara default di RolePermissionSeeder, harus dicentang manual
            // per role lewat UI Role & Hak Akses. Owner tetap bypass via Gate::before.
            ['name' => 'laporan.setoran_harian.view',   'display_name' => 'Lihat Laporan Setoran Harian',   'group' => 'laporan'],
            ['name' => 'laporan.setoran_harian.print',  'display_name' => 'Print Laporan Setoran Harian',   'group' => 'laporan'],
            ['name' => 'laporan.setoran_harian.export', 'display_name' => 'Export Laporan Setoran Harian',  'group' => 'laporan'],
            ['name' => 'laporan.ranking_kasir.view', 'display_name' => 'Lihat Ranking Kasir', 'group' => 'laporan'],

            // Laporan Konsumsi Bahan Baku — sengaja TIDAK di-assign ke role manapun
            // secara default di RolePermissionSeeder, harus dicentang manual per
            // role lewat UI Role & Hak Akses. Owner tetap bypass via Gate::before.
            ['name' => 'laporan.konsumsi_bahan.view',   'display_name' => 'Lihat Laporan Konsumsi Bahan Baku',   'group' => 'laporan'],
            ['name' => 'laporan.konsumsi_bahan.print',  'display_name' => 'Print Laporan Konsumsi Bahan Baku',   'group' => 'laporan'],
            ['name' => 'laporan.konsumsi_bahan.export', 'display_name' => 'Export Laporan Konsumsi Bahan Baku',  'group' => 'laporan'],

            // Laporan Laba Rugi — sengaja TIDAK di-assign ke role manapun
            // secara default di RolePermissionSeeder, harus dicentang manual
            // per role lewat UI Role & Hak Akses. Owner tetap bypass via Gate::before.
            ['name' => 'laporan.laba_rugi.view',   'display_name' => 'Lihat Laporan Laba Rugi',   'group' => 'laporan'],
            ['name' => 'laporan.laba_rugi.print',  'display_name' => 'Print Laporan Laba Rugi',   'group' => 'laporan'],
            ['name' => 'laporan.laba_rugi.export', 'display_name' => 'Export Laporan Laba Rugi',  'group' => 'laporan'],

            // PO Tools (Pilih PO di Kas Keluar + Dashboard PO) — sengaja TIDAK
            // di-assign ke role manapun secara default di RolePermissionSeeder,
            // harus dicentang manual per role lewat UI Role & Hak Akses.
            // Owner tetap bypass via Gate::before.
            ['name' => 'kas.pilih_po.view',   'display_name' => 'Pilih PO di Form Kas Keluar', 'group' => 'keuangan'],
            ['name' => 'po_dashboard.view',   'display_name' => 'Lihat Dashboard PO',           'group' => 'pembelian'],
            ['name' => 'po_dashboard.action', 'display_name' => 'Quick Action Dashboard PO',    'group' => 'pembelian'],

            // Master Bumbu Pusat (nama tampilan; permission name tetap
            // "master.resep_bumbu.*", zero migration) — sengaja TIDAK
            // di-assign ke role manapun secara default di RolePermissionSeeder,
            // harus dicentang manual per role lewat UI Role & Hak Akses. Owner
            // tetap bypass via Gate::before.
            // Catatan: endpoint AJAX POS (GET /pos/resep-bumbu/{id}) TIDAK digated
            // permission ini — itu murni helper auto-fill utk kasir, bukan CRUD.
            ['name' => 'master.resep_bumbu.view',   'display_name' => 'Lihat Master Bumbu Pusat',   'group' => 'master'],
            ['name' => 'master.resep_bumbu.create', 'display_name' => 'Tambah Master Bumbu Pusat',  'group' => 'master'],
            ['name' => 'master.resep_bumbu.edit',   'display_name' => 'Edit Master Bumbu Pusat',    'group' => 'master'],
            ['name' => 'master.resep_bumbu.delete', 'display_name' => 'Hapus Master Bumbu Pusat',   'group' => 'master'],

            // Master Varian Produk (Tahap 2 D'mentai, 2026-09-13) — DB+Model
            // sudah ada, UI dikerjakan bareng POS di Tahap 3. Permission
            // ditambah sekarang (bukan default-kosong seperti resep_bumbu)
            // karena diminta di-assign ke admin_pusat/admin_gudang/manajer_cabang
            // sejak awal — lihat RolePermissionSeeder.
            ['name' => 'master.item_varian.view',   'display_name' => 'Lihat Varian Produk',  'group' => 'master'],
            ['name' => 'master.item_varian.create', 'display_name' => 'Tambah Varian Produk', 'group' => 'master'],
            ['name' => 'master.item_varian.edit',   'display_name' => 'Edit Varian Produk',   'group' => 'master'],
            ['name' => 'master.item_varian.delete', 'display_name' => 'Hapus Varian Produk',  'group' => 'master'],

            // Cleanup Tool (audit produksi 2026-07-26, Bug 1/2/3) — link transaksi
            // historis ke PO, assign Kas Sumber ke transaksi lama kas_id NULL,
            // dan sinkron saldo kas dari riwayat. Sengaja TIDAK di-assign ke role
            // manapun secara default di RolePermissionSeeder, harus dicentang
            // manual per role lewat UI Role & Hak Akses. Owner tetap bypass
            // via Gate::before.
            ['name' => 'transaksi.link_po.action',  'display_name' => 'Link Transaksi Existing ke PO (Cleanup)', 'group' => 'keuangan'],
            ['name' => 'transaksi.assign_kas.action', 'display_name' => 'Assign Kas Sumber ke Transaksi Lama (Cleanup)', 'group' => 'keuangan'],
            ['name' => 'kas.sinkron_saldo.action',  'display_name' => 'Sinkron Saldo Kas dari Riwayat (Cleanup)', 'group' => 'keuangan'],

            // Transfer Antar Kas — mutasi dana dalam 1 cabang (Tunai <-> Bank dll),
            // BEDA dari Transfer/Perpindahan Dana (setoran.*) yang antar cabang.
            // Sengaja TIDAK di-assign ke role manapun secara default, harus
            // dicentang manual per role lewat UI Role & Hak Akses. Owner tetap
            // bypass via Gate::before.
            ['name' => 'transfer_antar_kas.view',   'display_name' => 'Lihat Transfer Antar Kas',   'group' => 'keuangan'],
            ['name' => 'transfer_antar_kas.create', 'display_name' => 'Buat Transfer Antar Kas',    'group' => 'keuangan'],
            ['name' => 'transfer_antar_kas.delete', 'display_name' => 'Hapus Transfer Antar Kas',   'group' => 'keuangan'],

            // Tahap 5 D'mentai — Setoran Kasir (rekonsiliasi harian cabang -> HO,
            // auto-hitung dari order_payments). Namespace BEDA dari 'setoran.*'
            // (Transfer Dana antar cabang, generik) supaya role yang sudah py
            // setoran.* tidak otomatis dapat akses fitur ini tanpa sengaja.
            ['name' => 'setoran_kasir.view',    'display_name' => 'Lihat Setoran Kasir',        'group' => 'keuangan'],
            ['name' => 'setoran_kasir.create',  'display_name' => 'Submit Setoran Kasir',       'group' => 'keuangan'],
            ['name' => 'setoran_kasir.approve', 'display_name' => 'Approve Setoran Kasir (HO)', 'group' => 'keuangan'],
            ['name' => 'setoran_kasir.reject',  'display_name' => 'Reject Setoran Kasir (HO)',  'group' => 'keuangan'],

            // Closing / Tutup Buku (2026-10-17) — closing harian/mingguan/bulanan
            // per cabang & keseluruhan. buka_kunci SENGAJA tidak di-assign ke
            // role mana pun (Owner-only via Gate::before).
            ...ClosingPermissionSeeder::PERMISSIONS,

            // 4.44 Poin 2 — Label Produk (stiker); fiturnya mati per cabang sampai dinyalakan
            LabelProdukSeeder::PERMISSION,

            // 4.46 — Laporan Transaksi/Produk/Kategori di menu Penjualan (bisa dibuka kasir)
            ...LaporanPenjualanKasirSeeder::PERMISSIONS,
            PosUbahHargaSeeder::PERMISSION,
            ...Porting449Seeder::PERMISSIONS, // 4.49 — produksi/kiriman/retur (porting coffeeshop)

            // Chart of Accounts (Fase 1 Akuntansi) — Owner-only default, sama
            // pola dengan master.resep_bumbu.* dan lihat_backup/dll. Kalau perlu
            // delegasi, Owner centang manual di UI Role & Hak Akses.
            ['name' => 'coa.view',   'display_name' => 'Lihat Chart of Accounts',   'group' => 'akuntansi'],
            ['name' => 'coa.manage', 'display_name' => 'Kelola Chart of Accounts',  'group' => 'akuntansi'],

            // Laporan Neraca & Laba Rugi Formal (Fase 2 Akuntansi) — Owner-only
            // default, pola sama dengan coa.*/master.resep_bumbu.*. Delegable
            // manual via UI Role & Hak Akses.
            ['name' => 'laporan.neraca.view',   'display_name' => 'Lihat Laporan Neraca',   'group' => 'akuntansi'],
            ['name' => 'laporan.neraca.export', 'display_name' => 'Export PDF Laporan Neraca', 'group' => 'akuntansi'],
            ['name' => 'laporan.laba_rugi_formal.view',   'display_name' => 'Lihat Laporan Laba Rugi Formal',   'group' => 'akuntansi'],
            ['name' => 'laporan.laba_rugi_formal.export', 'display_name' => 'Export PDF Laporan Laba Rugi Formal', 'group' => 'akuntansi'],

            // Laporan BEP Otomatis (Fase 3) — extend menu BEP existing,
            // group 'laporan' konsisten dengan laporan.konsumsi_bahan.*/
            // laporan.setoran_harian.*. Owner-only default, delegable manual.
            ['name' => 'laporan.bep_otomatis.view',   'display_name' => 'Lihat Laporan BEP Otomatis',   'group' => 'laporan'],
            ['name' => 'laporan.bep_otomatis.export', 'display_name' => 'Export PDF Laporan BEP Otomatis', 'group' => 'laporan'],

            // Buku Besar (Fase 3) — extend keluarga akuntansi COA/Neraca/Laba
            // Rugi Formal, group 'akuntansi'. Owner-only default, delegable manual.
            ['name' => 'laporan.buku_besar.view',   'display_name' => 'Lihat Buku Besar',   'group' => 'akuntansi'],
            ['name' => 'laporan.buku_besar.export', 'display_name' => 'Export PDF Buku Besar', 'group' => 'akuntansi'],

            // Widget Dashboard Analytics (Fase 3) — group baru 'dashboard',
            // Owner-only default, delegable manual.
            ['name' => 'dashboard.analytics.view', 'display_name' => 'Lihat Widget Dashboard Analytics', 'group' => 'dashboard'],
            // Tahap 6 D'mentai — widget setoran/keuangan HO di dashboard Owner
            ['name' => 'dashboard.owner.view', 'display_name' => 'Lihat Widget Dashboard Owner (Setoran)', 'group' => 'dashboard'],

            // Laporan Eksekutif Keuangan (Sesi A) — group 'akuntansi',
            // Owner-only default, delegable manual.
            ['name' => 'laporan.eksekutif.view',   'display_name' => 'Lihat Laporan Eksekutif Keuangan',   'group' => 'akuntansi'],
            ['name' => 'laporan.eksekutif.export', 'display_name' => 'Export PDF Laporan Eksekutif Keuangan', 'group' => 'akuntansi'],

            // Interactive Simulator BEP (Sesi B) — group 'laporan',
            // Owner-only default, delegable manual.
            ['name' => 'laporan.simulator.view', 'display_name' => 'Lihat Simulator BEP', 'group' => 'laporan'],
            // Sprint 3 Batch 2 lanjutan (2026-09-22) — Export Snapshot Simulator BEP
            ['name' => 'laporan.simulator.export', 'display_name' => 'Export Snapshot Simulator BEP', 'group' => 'laporan'],

            // Analisa Jam Ramai (Peak Hours) — group 'laporan', murni dari
            // orders.created_at. Owner-only default, delegable manual.
            ['name' => 'laporan.jam_ramai.view', 'display_name' => 'Lihat Laporan Jam Ramai', 'group' => 'laporan'],
            // Sprint 3 Batch 2 (2026-09-22) — Export Excel/PDF Laporan Jam Ramai
            ['name' => 'laporan.jam_ramai.export', 'display_name' => 'Export Laporan Jam Ramai', 'group' => 'laporan'],
            // Tahap 6 D'mentai — Laporan Setoran Kasir (beda dari laporan.view
            // generik yang sudah dipakai LaporanSetoranController existing utk
            // Transfer Dana)
            ['name' => 'laporan.setoran_kasir.view', 'display_name' => 'Lihat Laporan Setoran Kasir', 'group' => 'laporan'],
            ['name' => 'laporan.setoran_kasir.export', 'display_name' => 'Export Laporan Setoran Kasir', 'group' => 'laporan'],

            // Program Loyalty (Fase 1: tracking otomatis kumulatif kg giling)
            // — group baru 'loyalty', Owner-only default, delegable manual.
            ['name' => 'loyalty.view',   'display_name' => 'Lihat Program Loyalty',   'group' => 'loyalty'],
            ['name' => 'loyalty.manage', 'display_name' => 'Kelola Program Loyalty',  'group' => 'loyalty'],

            // Program Loyalty Fase 2 (event-based, klaim voucher) — group
            // sama 'loyalty'. loyalty.klaim.view/buat DEFAULT ke Kasir juga
            // (kasir yang input klaim di lapangan), approve/reject/issued
            // tetap Owner+Admin Pusat saja.
            ['name' => 'loyalty.klaim.view',    'display_name' => 'Lihat Klaim Loyalty',            'group' => 'loyalty'],
            ['name' => 'loyalty.klaim.buat',    'display_name' => 'Buat Klaim Loyalty',             'group' => 'loyalty'],
            ['name' => 'loyalty.klaim.approve', 'display_name' => 'Approve Klaim Loyalty',          'group' => 'loyalty'],
            ['name' => 'loyalty.klaim.reject',  'display_name' => 'Reject Klaim Loyalty',           'group' => 'loyalty'],
            ['name' => 'loyalty.klaim.issued',  'display_name' => 'Tandai Voucher Klaim Diberikan', 'group' => 'loyalty'],

            // Batal Bayar PO (Fase 4) — rollback pembayaran PO (restore saldo
            // kas + hapus transaksi ter-link). Default Owner + admin_pusat via
            // RolePermissionSeeder; role lain bisa ditambah manual lewat UI
            // Role & Hak Akses. Group 'pembelian', konsisten po_dashboard.*.
            ['name' => 'po.batal_bayar.action', 'display_name' => 'Batal Bayar PO', 'group' => 'pembelian'],

            // Pemakaian Perlengkapan (Fase 5, Rule #66) — menu BARU terpisah
            // dari stok.adjustment existing. .delete/.export disiapkan untuk
            // fitur susulan (belum ada route-nya di Fase 5 ini — route yang
            // terdaftar cuma index/create/store/show).
            ['name' => 'pemakaian_perlengkapan.view',   'display_name' => 'Lihat Pemakaian Perlengkapan',   'group' => 'stok'],
            ['name' => 'pemakaian_perlengkapan.create', 'display_name' => 'Catat Pemakaian Perlengkapan',   'group' => 'stok'],
            ['name' => 'pemakaian_perlengkapan.delete', 'display_name' => 'Batal Pemakaian Perlengkapan',   'group' => 'stok'],
            ['name' => 'pemakaian_perlengkapan.export', 'display_name' => 'Export Pemakaian Perlengkapan',  'group' => 'stok'],

            // Laporan Pemakaian Perlengkapan (Fase 5, Rule #66) — group
            // 'laporan' konsisten laporan.konsumsi_bahan.*/laporan.laba_rugi.*.
            ['name' => 'laporan.perlengkapan.view',   'display_name' => 'Lihat Laporan Pemakaian Perlengkapan',   'group' => 'laporan'],
            ['name' => 'laporan.perlengkapan.print',  'display_name' => 'Print Laporan Pemakaian Perlengkapan',   'group' => 'laporan'],
            ['name' => 'laporan.perlengkapan.export', 'display_name' => 'Export Laporan Pemakaian Perlengkapan',  'group' => 'laporan'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }
    }
}
