<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.49 Tahap B3 — Retur cabang → Gudang (porting erp-coffeeshop 40bd55e). Barang dari
 * kiriman yang sudah diterima dikembalikan ke pengirim; uang dikembalikan atau
 * tidak dipilih setiap retur. Retur dengan uang kembali membuat 1 order koreksi
 * bernilai minus di pengirim (orders.retur_internal_id) supaya pendapatan &
 * keuntungan pengirim di semua laporan ikut turun. Hanya tabel/kolom baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_internals', function (Blueprint $table) {
            $table->id();
            $table->string('kode_retur', 30)->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();        // kiriman asal
            $table->foreignId('dari_cabang_id')->constrained('cabangs')->restrictOnDelete(); // cabang yang mengembalikan
            $table->foreignId('ke_cabang_id')->constrained('cabangs')->restrictOnDelete();   // gudang pengirim
            $table->enum('alasan', ['basi', 'rusak', 'kelebihan', 'lainnya']);
            $table->text('catatan')->nullable();
            $table->boolean('uang_kembali')->default(true);
            $table->decimal('nilai', 15, 2)->default(0); // harga yang dibayar cabang untuk barang yang diretur
            $table->enum('status', ['menunggu', 'diterima', 'ditolak', 'dibatalkan'])->default('menunggu');
            $table->foreignId('dibuat_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diproses_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->string('alasan_tolak', 500)->nullable();
            $table->timestamps();

            $table->index(['ke_cabang_id', 'status']);
            $table->index(['dari_cabang_id', 'status']);
        });

        Schema::create('retur_internal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retur_internal_id')->constrained('retur_internals')->cascadeOnDelete();
            $table->foreignId('order_kiriman_item_id')->constrained('order_kiriman_items')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete(); // barang milik cabang yang diretur (data per cabang 4.39)
            $table->decimal('qty', 10, 3);
            $table->decimal('harga_per_unit', 15, 2)->default(0); // dibayar cabang saat Terima kiriman
            $table->decimal('hpp_per_unit', 15, 2)->default(0);   // modal pengirim saat kiriman
            $table->decimal('hpp_cabang', 15, 2)->default(0);     // modal FIFO yang keluar dari stok cabang
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('retur_internal_id')->nullable()->after('alasan_tolak')->constrained('retur_internals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retur_internal_id');
        });
        Schema::dropIfExists('retur_internal_items');
        Schema::dropIfExists('retur_internals');
    }
};
