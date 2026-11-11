<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tahap 3 - POS D'mentai (2026-09-13). Tabel `orders` dikonfirmasi KOSONG
     * (0 baris, sudah dibersihkan total di Tahap 2) sebelum migration ini
     * ditulis — jadi ALTER enum di sini aman tanpa risiko data.
     *
     * `tipe_order`: TAMBAH 'penjualan' (dipakai SEMUA order baru D'mentai ke
     * depan, apapun tipe_transaksinya). SENGAJA TIDAK menghapus 'jasa_giling'
     * / 'produk_jadi' dari enum — beberapa service lama (BepOtomatisService,
     * BusinessOverviewService, DashboardController, PelangganController)
     * masih punya query literal `where('tipe_order','jasa_giling')` yang jadi
     * gap terdokumentasi (CLAUDE.md 4.1, ditunda ke Tahap 5/6) — menghapus
     * value enum tidak akan meng-crash-kan query itu (tetap syntactically
     * valid, cuma selalu kosong), tapi tidak ada untungnya dihapus sekarang.
     *
     * `tipe_pembayaran`: TAMBAH 'gojek','grab' — uangnya settle ke Kas
     * kategori "transfer" existing (keputusan Owner), field ini cuma dipakai
     * sebagai ringkasan/filter cepat di level order; sumber kebenaran
     * pembagian metode bayar sesungguhnya ada di tabel baru `order_payments`.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY tipe_order ENUM('jasa_giling','produk_jadi','penjualan') NOT NULL");
        DB::statement("ALTER TABLE orders MODIFY tipe_pembayaran ENUM('tunai','transfer','qris','gojek','grab') NOT NULL DEFAULT 'tunai'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY tipe_order ENUM('jasa_giling','produk_jadi') NOT NULL");
        DB::statement("ALTER TABLE orders MODIFY tipe_pembayaran ENUM('tunai','transfer','qris') NOT NULL DEFAULT 'tunai'");
    }
};
