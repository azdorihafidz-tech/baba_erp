<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fase 5 - Modul Perlengkapan (Rule #66). Tabel BARU, tidak menyentuh
        // stock_movements/items existing sama sekali. Setiap baris di sini
        // 1:1 dengan 1 StockMovement (tipe=keluar) yang dibuat via
        // StokService::keluar() existing, ditautkan lewat referensi_type=
        // 'pemakaian_perlengkapan' + referensi_id=id baris ini (pola
        // polymorphic yang sudah dipakai purchase_order/order/kas, Rule #32).
        Schema::create('pemakaian_perlengkapans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->decimal('qty', 10, 3);
            $table->date('tanggal_pemakaian');
            // Nilai (HPP) dari batch FIFO yang dikonsumsi StokService::keluar()
            // saat baris ini disimpan - murni utk laporan, tidak pernah
            // dipakai untuk transaksi keuangan (Metode A: beban sudah diakui
            // saat PO dibayar, lihat Rule #66).
            $table->decimal('nilai', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['item_id', 'cabang_id']);
            $table->index('tanggal_pemakaian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemakaian_perlengkapans');
    }
};
