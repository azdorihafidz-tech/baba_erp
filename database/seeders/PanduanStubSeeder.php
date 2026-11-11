<?php

namespace Database\Seeders;

use App\Models\Panduan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PanduanStubSeeder extends Seeder
{
    public function run(): void
    {
        // Update modul panduan POS yang sudah ada dari 'pos' → 'penjualan'
        DB::table('panduan')
            ->where('slug', 'pos')
            ->whereNotNull('id')
            ->update(['modul' => 'penjualan']);

        $stub = implode("\n", [
            "## Tujuan",
            "[Belum diisi — Owner/Admin Pusat dapat mengedit panduan ini di **Pengaturan → Panduan Helper**]",
            "",
            "## Langkah-langkah",
            "",
            "1. [Belum diisi]",
            "2. [Belum diisi]",
            "3. [Belum diisi]",
            "",
            "## Catatan Penting",
            "",
            "- [Belum diisi]",
            "",
            "## Troubleshooting",
            "",
            "- [Belum diisi]",
        ]);

        $entries = [
            // ── PENJUALAN ──────────────────────────────────────────
            ['slug' => 'riwayat-order',     'judul' => 'Cara Pakai Menu Riwayat Order',      'modul' => 'penjualan', 'urutan' => 2],
            ['slug' => 'pelanggan',          'judul' => 'Cara Pakai Menu Pelanggan',           'modul' => 'penjualan', 'urutan' => 3],

            // ── ANTRIAN ────────────────────────────────────────────
            ['slug' => 'antrian-cek',        'judul' => 'Cara Pakai Menu Cek Antrian',         'modul' => 'antrian',   'urutan' => 1],
            ['slug' => 'antrian-operator',   'judul' => 'Cara Pakai Menu Kelola Antrian',      'modul' => 'antrian',   'urutan' => 2],

            // ── STOK ───────────────────────────────────────────────
            ['slug' => 'dashboard-stok',     'judul' => 'Cara Pakai Dashboard Stok',           'modul' => 'stok',      'urutan' => 1],
            ['slug' => 'stok-barang',        'judul' => 'Cara Pakai Menu Stok Barang',         'modul' => 'stok',      'urutan' => 2],
            ['slug' => 'stok-adjustment',    'judul' => 'Cara Pakai Adjustment Stok',          'modul' => 'stok',      'urutan' => 3],
            ['slug' => 'permintaan-stok',    'judul' => 'Cara Pakai Menu Permintaan Stok',     'modul' => 'stok',      'urutan' => 4],
            ['slug' => 'transfer-stok',      'judul' => 'Cara Pakai Menu Transfer Stok',       'modul' => 'stok',      'urutan' => 5],
            ['slug' => 'master-barang',      'judul' => 'Cara Pakai Menu Master Barang',       'modul' => 'stok',      'urutan' => 6],

            // ── PEMBELIAN ──────────────────────────────────────────
            ['slug' => 'pembelian',          'judul' => 'Cara Pakai Menu Purchase Order',      'modul' => 'pembelian', 'urutan' => 1],
            ['slug' => 'supplier',           'judul' => 'Cara Pakai Menu Supplier',            'modul' => 'pembelian', 'urutan' => 2],

            // ── KEUANGAN ───────────────────────────────────────────
            ['slug' => 'dashboard-keuangan', 'judul' => 'Cara Pakai Dashboard Keuangan',       'modul' => 'keuangan',  'urutan' => 1],
            ['slug' => 'kas-transaksi',      'judul' => 'Cara Pakai Menu Kas & Transaksi',     'modul' => 'keuangan',  'urutan' => 2],
            ['slug' => 'keuangan-laporan',   'judul' => 'Cara Pakai Menu Laporan Keuangan',    'modul' => 'keuangan',  'urutan' => 3],
            ['slug' => 'setoran',            'judul' => 'Cara Pakai Menu Setoran ke Pusat',    'modul' => 'keuangan',  'urutan' => 4],
            ['slug' => 'kategori-transaksi', 'judul' => 'Cara Pakai Menu Kategori Transaksi',  'modul' => 'keuangan',  'urutan' => 5],
            ['slug' => 'bep',                'judul' => 'Cara Pakai Menu Analisis BEP',        'modul' => 'keuangan',  'urutan' => 6],
            ['slug' => 'recurring',          'judul' => 'Cara Pakai Menu Transaksi Berulang',  'modul' => 'keuangan',  'urutan' => 7],

            // ── SDM ────────────────────────────────────────────────
            ['slug' => 'karyawan',           'judul' => 'Cara Pakai Menu Karyawan',            'modul' => 'sdm',       'urutan' => 1],
            ['slug' => 'dashboard-absensi',  'judul' => 'Cara Pakai Dashboard Absensi',        'modul' => 'sdm',       'urutan' => 2],
            ['slug' => 'scan-absensi',       'judul' => 'Cara Pakai Menu Scan Absensi',        'modul' => 'sdm',       'urutan' => 3],
            ['slug' => 'absensi-manual',     'judul' => 'Cara Pakai Menu Absensi Manual',      'modul' => 'sdm',       'urutan' => 4],
            ['slug' => 'absensi-saya',       'judul' => 'Cara Pakai Menu Absensi Saya',        'modul' => 'sdm',       'urutan' => 5],
            ['slug' => 'rekap-absensi',      'judul' => 'Cara Pakai Menu Rekap Bulanan Absensi', 'modul' => 'sdm',     'urutan' => 6],
            ['slug' => 'registrasi-wajah',   'judul' => 'Cara Pakai Menu Registrasi Wajah',   'modul' => 'sdm',       'urutan' => 7],
            ['slug' => 'shift',              'judul' => 'Cara Pakai Menu Kelola Shift',        'modul' => 'sdm',       'urutan' => 8],
            ['slug' => 'hari-libur',         'judul' => 'Cara Pakai Menu Hari Libur',          'modul' => 'sdm',       'urutan' => 9],
            ['slug' => 'penggajian',         'judul' => 'Cara Pakai Menu Penggajian',          'modul' => 'sdm',       'urutan' => 10],
            ['slug' => 'cuti',               'judul' => 'Cara Pakai Menu Cuti & Izin',         'modul' => 'sdm',       'urutan' => 11],
            ['slug' => 'evaluasi',           'judul' => 'Cara Pakai Menu Penilaian 360°',      'modul' => 'sdm',       'urutan' => 12],
            ['slug' => 'evaluasi-saya',      'judul' => 'Cara Pakai Menu Penilaian Saya',      'modul' => 'sdm',       'urutan' => 13],

            // ── ASET ───────────────────────────────────────────────
            ['slug' => 'aset',               'judul' => 'Cara Pakai Menu Manajemen Aset',      'modul' => 'aset',      'urutan' => 1],

            // ── LAPORAN ────────────────────────────────────────────
            ['slug' => 'laporan-penjualan',  'judul' => 'Cara Pakai Laporan Penjualan',        'modul' => 'laporan',   'urutan' => 1],
            ['slug' => 'laporan-stok',       'judul' => 'Cara Pakai Laporan Stok',             'modul' => 'laporan',   'urutan' => 2],
            ['slug' => 'laporan-keuangan',   'judul' => 'Cara Pakai Laporan Keuangan',         'modul' => 'laporan',   'urutan' => 3],
            ['slug' => 'laporan-hr',         'judul' => 'Cara Pakai Laporan HR/SDM',           'modul' => 'laporan',   'urutan' => 4],
            ['slug' => 'laporan-absensi',    'judul' => 'Cara Pakai Laporan Absensi',          'modul' => 'laporan',   'urutan' => 5],
            ['slug' => 'laporan-aset',       'judul' => 'Cara Pakai Laporan Aset',             'modul' => 'laporan',   'urutan' => 6],
            ['slug' => 'laporan-bep',        'judul' => 'Cara Pakai Laporan BEP',              'modul' => 'laporan',   'urutan' => 7],
            ['slug' => 'laporan-setoran',    'judul' => 'Cara Pakai Laporan Setoran',          'modul' => 'laporan',   'urutan' => 8],
            ['slug' => 'laporan-kategori',   'judul' => 'Cara Pakai Laporan Per Kategori',     'modul' => 'laporan',   'urutan' => 9],
            ['slug' => 'laporan-audit-bukti','judul' => 'Cara Pakai Laporan Audit Bukti',      'modul' => 'laporan',   'urutan' => 10],
            ['slug' => 'laporan-saldo-kas',  'judul' => 'Cara Pakai Laporan Saldo Kas',        'modul' => 'laporan',   'urutan' => 11],
            ['slug' => 'laporan-cabang',     'judul' => 'Cara Pakai Laporan Cabang vs Cabang', 'modul' => 'laporan',   'urutan' => 12],
            ['slug' => 'setoran-harian',     'judul' => 'Cara Pakai Laporan Setoran Harian',   'modul' => 'laporan',   'urutan' => 13],

            // ── LAINNYA ────────────────────────────────────────────
            ['slug' => 'notifikasi',         'judul' => 'Cara Pakai Menu Notifikasi',          'modul' => 'lainnya',   'urutan' => 1],
        ];

        foreach ($entries as $entry) {
            Panduan::firstOrCreate(
                ['slug' => $entry['slug']],
                [
                    'judul'  => $entry['judul'],
                    'konten' => $stub,
                    'modul'  => $entry['modul'],
                    'urutan' => $entry['urutan'],
                    'aktif'  => true,
                ]
            );
        }
    }
}
