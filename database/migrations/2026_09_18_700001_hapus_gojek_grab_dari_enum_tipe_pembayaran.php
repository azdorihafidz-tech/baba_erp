<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hapus opsi Gojek/Grab dari tipe pembayaran (keputusan Owner, 2026-09-18) —
 * D'mentai fokus retail walk-in, bukan food delivery. Order Gojek/Grab (kalau
 * ada) dicatat manual sebagai "Transfer" (sudah settle ke kategori Kas
 * "transfer" sejak awal, lihat TipePembayaran::kasKategori() lama).
 *
 * Data-migrate UNCONDITIONAL sebelum alter enum — aman no-op kalau memang
 * tidak ada baris gojek/grab (dikonfirmasi 0 baris baik di dev maupun
 * production sebelum migration ini dibuat), otomatis menangani kalau
 * suatu saat restore backup lama yang masih punya data itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->whereIn('tipe_pembayaran', ['gojek', 'grab'])->update(['tipe_pembayaran' => 'transfer']);
        DB::table('order_payments')->whereIn('metode', ['gojek', 'grab'])->update(['metode' => 'transfer']);

        DB::statement("ALTER TABLE orders MODIFY tipe_pembayaran ENUM('tunai','transfer','qris') NOT NULL DEFAULT 'tunai'");
        DB::statement("ALTER TABLE order_payments MODIFY metode ENUM('tunai','transfer','qris') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY tipe_pembayaran ENUM('tunai','transfer','qris','gojek','grab') NOT NULL DEFAULT 'tunai'");
        DB::statement("ALTER TABLE order_payments MODIFY metode ENUM('tunai','transfer','qris','gojek','grab') NOT NULL");

        // Data yang sudah ter-migrate ke 'transfer' TIDAK otomatis kembali ke
        // gojek/grab (information loss yang wajar/expected untuk rollback
        // semacam ini) -- enum-nya saja yang dikembalikan longgar.
    }
};
