<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.49 Tahap B1 — Produksi bahan setengah jadi di gudang (porting erp-coffeeshop
 * 1139fe5, rancangan coffeeshop FASE_PRODUKSI_GEROBAK_DESIGN.md).
 *
 * Resep Produksi SENGAJA terpisah dari `resep_bumbu` (komposisi Produk Jual):
 * `Item::resep` dibaca POS/cek bisa dijual/Master Produk Jual sebagai "menu
 * yang punya komposisi". Tabel terpisah = 0 perubahan logika POS go-live.
 * Hanya tabel baru, tidak ada kolom lama yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resep_produksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->unique()->constrained('items')->cascadeOnDelete(); // barang hasil
            $table->decimal('qty_hasil', 10, 3)->default(1);  // hasil per 1 resep, satuan = satuan barang hasil
            $table->text('catatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('resep_produksi_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resep_produksi_id')->constrained('resep_produksi')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete(); // bahan
            $table->decimal('qty', 10, 3);      // takaran per resep, dalam `satuan`
            $table->string('satuan', 20);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('produksi_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kode_batch', 30)->unique();
            $table->date('tanggal');
            $table->foreignId('cabang_id')->constrained('cabangs')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete(); // barang hasil
            $table->foreignId('resep_produksi_id')->nullable()->constrained('resep_produksi')->nullOnDelete();
            $table->decimal('qty_rencana', 10, 3);
            $table->decimal('qty_hasil', 10, 3);
            $table->decimal('biaya_bahan', 15, 2)->default(0);     // total modal FIFO bahan terpakai
            $table->decimal('modal_otomatis', 15, 2)->default(0);  // biaya_bahan / qty_hasil
            $table->decimal('modal_per_unit', 15, 2)->default(0);  // yang dipakai (otomatis atau manual)
            $table->boolean('modal_manual')->default(false);
            $table->enum('status', ['selesai', 'dibatalkan'])->default('selesai');
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dibatalkan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->string('alasan_batal', 500)->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'tanggal']);
        });

        Schema::create('produksi_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produksi_batch_id')->constrained('produksi_batches')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('qty', 10, 3);          // dalam satuan stok bahan
            $table->decimal('biaya', 15, 2)->default(0); // modal FIFO yang terpakai
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produksi_batch_items');
        Schema::dropIfExists('produksi_batches');
        Schema::dropIfExists('resep_produksi_items');
        Schema::dropIfExists('resep_produksi');
    }
};
