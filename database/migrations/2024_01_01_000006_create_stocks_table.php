<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('lokasi_id')->constrained('cabangs')->cascadeOnDelete(); // cabang_id atau gudang_pusat_id
            $table->decimal('qty', 10, 3)->default(0);
            $table->decimal('qty_minimum', 10, 3)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['item_id', 'lokasi_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('lokasi_asal_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->foreignId('lokasi_tujuan_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->decimal('qty', 10, 3);
            $table->enum('tipe', ['masuk', 'keluar', 'transfer', 'adjustment']);
            $table->string('referensi_type')->nullable(); // purchase_order, stock_request, produksi, penjualan
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
};
