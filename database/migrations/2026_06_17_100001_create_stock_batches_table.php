<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_batches')) {
            return;
        }

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('lokasi_id')->constrained('cabangs')->cascadeOnDelete();
            $table->string('referensi_type', 50)->nullable(); // 'purchase_order', 'stock_transfer', 'adjustment', 'initial'
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->decimal('qty_awal', 10, 3);
            $table->decimal('qty_sisa', 10, 3);
            $table->decimal('harga_beli_per_unit', 15, 2)->default(0);
            $table->date('tanggal_masuk');
            $table->text('catatan')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['item_id', 'lokasi_id', 'tanggal_masuk'], 'sb_item_lokasi_tgl');
            $table->index(['item_id', 'lokasi_id', 'qty_sisa'], 'sb_item_lokasi_sisa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
