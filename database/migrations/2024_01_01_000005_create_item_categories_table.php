<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('kode_kategori', 20)->unique();
            $table->string('nama_kategori');
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('kode_item', 30)->unique();
            $table->string('nama_item');
            $table->foreignId('item_category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->enum('tipe', ['bahan_baku', 'produk_jadi', 'kemasan', 'lainnya'])->default('bahan_baku');
            $table->string('satuan', 20)->default('kg'); // kg, pcs, liter, dll
            $table->decimal('harga_jual', 15, 2)->nullable();
            $table->decimal('harga_beli_terakhir', 15, 2)->nullable();
            $table->decimal('qty_minimum', 10, 3)->default(0); // stok minimum global
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
    }
};
