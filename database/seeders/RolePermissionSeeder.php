<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Default permissions per role.
     * Owner mendapat semua permission secara otomatis via Gate::before.
     */
    private array $defaults = [

        'admin_pusat' => [
            'cabang.view', 'cabang.create', 'cabang.edit', 'cabang.delete',
            'user.view', 'user.create', 'user.edit', 'user.delete',
            'order.view', 'order.create', 'order.edit', 'order.delete', 'order.batalkan', 'order.kembalikan',
            'order.bill_tersimpan.batalkan',
            'pelanggan.view', 'pelanggan.create', 'pelanggan.edit', 'pelanggan.delete',
            'stok.view', 'stok.adjustment', 'stok.request', 'stok.transfer',
            'item.view', 'item.create', 'item.edit', 'item.delete',
            'master.bahan_baku.view', 'master.bahan_baku.create', 'master.bahan_baku.edit', 'master.bahan_baku.delete',
            'master.produk_jual.view', 'master.produk_jual.create', 'master.produk_jual.edit', 'master.produk_jual.delete',
            'jenis-olahan.view', 'jenis-olahan.manage',
            'pembelian.view', 'pembelian.create', 'pembelian.approve', 'pembelian.delete',
            'pembelian.kirim-supplier', 'pembelian.terima',
            'keuangan.view', 'keuangan.create', 'keuangan.edit', 'keuangan.delete', 'laporan.view',
            'keuangan.kas_view', 'keuangan.kas_create', 'keuangan.kas_edit', 'keuangan.kas_delete',
            'kas.buka_laci',
            'kategori.view', 'kategori.create', 'kategori.edit', 'kategori.delete',
            'setoran.view', 'setoran.create', 'setoran.batal', 'setoran.terima', 'setoran.delete',
            'karyawan.view', 'karyawan.create', 'karyawan.edit', 'karyawan.delete',
            'absensi.view', 'absensi.manage',
            // Absensi baru — semua kecuali kelola_hari_libur & pengaturan_penggajian
            'hapus_absensi', 'hapus_log_absensi',
            'scan_absensi', 'dashboard_absensi', 'laporan_absensi',
            'registrasi_wajah',
            'lihat_shift', 'kelola_shift',
            'penggajian.view', 'penggajian.manage',
            'evaluasi.view', 'evaluasi.manage', 'evaluasi.fill',
            'aset.view', 'aset.manage', 'aset.depresiasi.auto',
            'bep.view', 'bep.manage',
            'cuti.view', 'cuti.create', 'cuti.approve', 'cuti.delete',
            // Keamanan — lihat & export audit, lihat trash (restore/hapus/backup: Owner only)
            'lihat_audit_log', 'export_audit_log',
            'lihat_data_terhapus',
            // Antrian Produksi
            'antrian.lihat', 'antrian.kelola', 'antrian.display',
            // Pengaturan Umum
            'pengaturan.umum_lihat',
            // Recurring Transaction
            'recurring.view', 'recurring.create', 'recurring.edit', 'recurring.delete',
            // Loyalty Fase 2 — approve/reject/issued sesuai brief Owner
            // (khusus workflow klaim, BUKAN loyalty.view/manage Fase 1 yang
            // tetap Owner-only konsisten pola existing)
            'loyalty.klaim.view', 'loyalty.klaim.approve', 'loyalty.klaim.reject', 'loyalty.klaim.issued',
            // Batal Bayar PO (Fase 4) — default Owner + admin_pusat sesuai
            // keputusan Owner. Manajer Cabang bisa ditambah nanti manual
            // lewat UI Role & Hak Akses kalau diperlukan.
            'po.batal_bayar.action',
            // Pemakaian Perlengkapan + Laporan-nya (Fase 5, Rule #66)
            'pemakaian_perlengkapan.view', 'pemakaian_perlengkapan.create',
            'laporan.perlengkapan.view', 'laporan.perlengkapan.print', 'laporan.perlengkapan.export',
            // Master Varian Produk (Tahap 2 D'mentai) — DB+Model saja, UI Tahap 3
            'master.item_varian.view', 'master.item_varian.create', 'master.item_varian.edit', 'master.item_varian.delete',
            // Tahap 5/6 D'mentai — admin_pusat = role HO yang approve/reject
            // Setoran Kasir + lihat dashboard/laporan owner
            'setoran_kasir.view', 'setoran_kasir.approve', 'setoran_kasir.reject',
            'dashboard.owner.view', 'laporan.setoran_kasir.view', 'laporan.setoran_kasir.export',
            // Closing / Tutup Buku (2026-10-17)
            ...ClosingPermissionSeeder::ROLE_DEFAULTS['admin_pusat'],
            'label_cup.print', // 4.44 Poin 2 — Label Produk
            ...LaporanPenjualanKasirSeeder::ROLE_DEFAULTS['admin_pusat'], // 4.46
            ...Porting449Seeder::ROLE_DEFAULTS['admin_pusat'], // 4.49
        ],

        'admin_gudang' => [
            'karyawan.view',
            'stok.view', 'stok.adjustment', 'stok.transfer',
            'item.view', 'item.create', 'item.edit',
            'master.bahan_baku.view', 'master.bahan_baku.create', 'master.bahan_baku.edit',
            'master.produk_jual.view',
            'pembelian.view', 'pembelian.create', 'pembelian.approve',
            'pembelian.kirim-supplier', 'pembelian.terima',
            'keuangan.view', 'laporan.view',
            'aset.view',
            'absensi.view',
            'evaluasi.fill',
            'cuti.view', 'cuti.create', 'cuti.delete',
            // Absensi baru untuk admin gudang
            'scan_absensi', 'dashboard_absensi', 'laporan_absensi',
            'registrasi_wajah',
            'hapus_absensi', 'hapus_log_absensi',
            'lihat_shift',
            // Pemakaian Perlengkapan (Fase 5, Rule #66)
            'pemakaian_perlengkapan.view', 'pemakaian_perlengkapan.create',
            // Master Varian Produk (Tahap 2 D'mentai) — DB+Model saja, UI Tahap 3
            'master.item_varian.view', 'master.item_varian.create', 'master.item_varian.edit', 'master.item_varian.delete',
            ...Porting449Seeder::ROLE_DEFAULTS['admin_gudang'], // 4.49
        ],

        'manajer_cabang' => [
            'order.view', 'order.create', 'order.batalkan',
            'pelanggan.view', 'pelanggan.create', 'pelanggan.edit',
            'stok.view', 'stok.request', 'stok.adjustment',
            'item.view', 'item.edit',
            'master.bahan_baku.view', 'master.bahan_baku.edit',
            'master.produk_jual.view', 'master.produk_jual.edit',
            'pembelian.view', 'pembelian.create',
            'keuangan.view', 'keuangan.create', 'keuangan.edit', 'keuangan.delete', 'laporan.view',
            'keuangan.kas_view', 'keuangan.kas_create', 'keuangan.kas_edit', 'keuangan.kas_delete',
            'kas.buka_laci',
            'kategori.view',
            'setoran.view', 'setoran.create', 'setoran.batal',
            'karyawan.view',
            'absensi.view', 'absensi.manage',
            // Absensi baru untuk manajer (tanpa hapus)
            'scan_absensi', 'dashboard_absensi', 'laporan_absensi',
            'registrasi_wajah',
            'lihat_shift',
            'penggajian.view', 'penggajian.manage',
            'evaluasi.view', 'evaluasi.manage', 'evaluasi.fill',
            'aset.view',
            'bep.view',
            'cuti.view', 'cuti.create', 'cuti.approve', 'cuti.delete',
            // Antrian Produksi
            'antrian.lihat', 'antrian.kelola', 'antrian.display',
            // Recurring — manajer bisa lihat & create
            'recurring.view', 'recurring.create',
            // Pemakaian Perlengkapan (Fase 5, Rule #66)
            'pemakaian_perlengkapan.view', 'pemakaian_perlengkapan.create',
            // Master Varian Produk (Tahap 2 D'mentai) — DB+Model saja, UI Tahap 3
            'master.item_varian.view', 'master.item_varian.create', 'master.item_varian.edit', 'master.item_varian.delete',
            // Tahap 5 D'mentai — manajer cabang bisa submit setoran (mewakili
            // kasir) + lihat riwayat setoran cabangnya sendiri
            'setoran_kasir.view', 'setoran_kasir.create',
            // Closing / Tutup Buku (2026-10-17)
            ...ClosingPermissionSeeder::ROLE_DEFAULTS['manajer_cabang'],
            'label_cup.print', // 4.44 Poin 2 — Label Produk
            ...LaporanPenjualanKasirSeeder::ROLE_DEFAULTS['manajer_cabang'], // 4.46
            ...Porting449Seeder::ROLE_DEFAULTS['manajer_cabang'], // 4.49
        ],

        'kasir' => [
            'order.view', 'order.create',
            'pelanggan.view', 'pelanggan.create',
            'karyawan.view',
            'stok.view',
            'keuangan.view', 'keuangan.kas_view',
            'setoran.view', 'setoran.create', 'setoran.batal',
            'absensi.view',
            'evaluasi.fill',
            'cuti.view', 'cuti.create', 'cuti.delete',
            // Absensi baru untuk kasir
            'scan_absensi',
            // Antrian — kasir bisa lihat & akses display
            'antrian.lihat', 'antrian.display',
            // Loyalty Fase 2 — kasir yang input klaim event di lapangan
            // (approve/reject/issued tetap Owner+Admin Pusat saja)
            'loyalty.klaim.view', 'loyalty.klaim.buat',
            // Tahap 5 D'mentai — kasir submit Setoran Kasir harian
            'setoran_kasir.view', 'setoran_kasir.create',
            // Closing / Tutup Buku (2026-10-17)
            ...ClosingPermissionSeeder::ROLE_DEFAULTS['kasir'],
            'label_cup.print', // 4.44 Poin 2 — Label Produk
            ...LaporanPenjualanKasirSeeder::ROLE_DEFAULTS['kasir'], // 4.46
            ...Porting449Seeder::ROLE_DEFAULTS['kasir'], // 4.49
        ],

        'operator_produksi' => [
            'order.view',
            'pelanggan.view', 'pelanggan.create',
            'karyawan.view',
            'stok.view', 'stok.request',
            'item.view',
            'master.bahan_baku.view',
            'master.produk_jual.view',
            'absensi.view',
            'evaluasi.fill',
            'cuti.view', 'cuti.create', 'cuti.delete',
            // Absensi baru untuk operator
            'scan_absensi',
            // Antrian — operator bisa kelola & lihat display
            'antrian.lihat', 'antrian.kelola', 'antrian.display',
            ...Porting449Seeder::ROLE_DEFAULTS['operator_produksi'], // 4.49
        ],

        'helper' => [
            'karyawan.view',
            'absensi.view',
            'evaluasi.fill',
            'cuti.view',
            'scan_absensi',
            'antrian.lihat',
        ],
    ];

    public function run(): void
    {
        // Ambil semua permission name → id
        $permMap = Permission::pluck('id', 'name')->toArray();

        foreach ($this->defaults as $role => $permNames) {
            // Hapus yang lama untuk role ini
            DB::table('role_permissions')->where('role', $role)->delete();

            $inserts = [];
            foreach ($permNames as $name) {
                if (isset($permMap[$name])) {
                    $inserts[] = [
                        'role'          => $role,
                        'permission_id' => $permMap[$name],
                    ];
                }
            }

            if (!empty($inserts)) {
                DB::table('role_permissions')->insert($inserts);
            }

            $this->command->line("  Role <info>{$role}</info>: " . count($inserts) . " permissions");
        }
    }
}
