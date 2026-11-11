<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 3 - POS D'mentai (2026-09-13) — Split Payment. 1 Order bisa
     * dibayar dengan >1 metode (mis. Rp50rb tunai + Rp30rb QRIS). Tabel ini
     * jadi SUMBER KEBENARAN pembagian metode bayar; `orders.tipe_pembayaran`
     * dipertahankan sebagai ringkasan cepat saja (metode tunggal kalau cuma
     * 1 baris, atau metode pertama kalau split — ditentukan di service).
     *
     * Gojek/Grab TIDAK dapat Kas kategori sendiri (keputusan Owner) — kolom
     * `metode` di sini tetap catat granular utk laporan, tapi resolusi
     * kas_id di PenjualanService selalu fallback ke Kas kategori "transfer"
     * milik cabang untuk kedua metode itu.
     */
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('metode', ['tunai', 'transfer', 'qris', 'gojek', 'grab']);
            $table->decimal('jumlah', 15, 2);
            $table->string('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
