<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug fix 2026-09-19 (ditemukan Owner, error 500 saat submit "Tambah
 * Transaksi" pemasukan kas dgn kategori "Saldo Awal"): `transaksi_keuangans.
 * kategori` masih raw MySQL ENUM warisan Berkah Mulyo (migration
 * 2024_01_01_000015) dengan 9 nilai TETAP, tidak pernah di-widen sejak
 * `App\Enums\KategoriTransaksi` (level PHP) ditambah case `SaldoAwal =
 * 'saldo_awal'` + kategori_transaksis seeder row 'SALDO' (is_system=true,
 * dipilih otomatis saat bikin Kas baru dgn saldo awal > 0, ATAU dipilih
 * manual di dropdown "Tambah Transaksi" — `KategoriTransaksi::toEnumValue()`
 * di app/Models/KategoriTransaksi.php SUDAH memetakan kode 'SALDO' ke
 * 'saldo_awal', tapi kolom DB tidak pernah ikut di-ALTER). Insert manapun
 * dgn kategori='saldo_awal' gagal (SQLSTATE 01000: Data truncated).
 *
 * Fix: widen ENUM tambah 'saldo_awal' -- SATU-SATUNYA nilai yang kurang,
 * dikonfirmasi dari toEnumValue() (9 value lama + 'saldo_awal' = lengkap,
 * default 'lainnya' utk kode lain manapun).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transaksi_keuangans MODIFY kategori ENUM(
            'penjualan','jasa_giling','pembelian_bahan','gaji','sewa_gedung',
            'penyusutan','operasional','pembelian_aset','lainnya','saldo_awal'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::table('transaksi_keuangans')
            ->where('kategori', 'saldo_awal')
            ->update(['kategori' => 'lainnya']);

        DB::statement("ALTER TABLE transaksi_keuangans MODIFY kategori ENUM(
            'penjualan','jasa_giling','pembelian_bahan','gaji','sewa_gedung',
            'penyusutan','operasional','pembelian_aset','lainnya'
        ) NOT NULL");
    }
};
