<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->string('nomor_order', 50)->unique();
            $table->date('tanggal_order');
            $table->enum('tipe_order', ['jasa_giling', 'produk_jadi']);
            $table->string('nama_pelanggan')->nullable();
            $table->string('telepon_pelanggan', 20)->nullable();
            $table->decimal('total_harga', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('total_bayar', 15, 2)->default(0);
            $table->decimal('jumlah_bayar', 15, 2)->default(0);
            $table->decimal('kembalian', 15, 2)->default(0);
            $table->enum('tipe_pembayaran', ['tunai', 'transfer', 'qris'])->default('tunai');
            $table->enum('status', ['pending', 'proses', 'selesai', 'dibatalkan'])->default('pending');
            $table->text('catatan')->nullable();
            $table->foreignId('kasir_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('nama_item'); // bisa nama custom untuk jasa giling
            $table->decimal('qty', 10, 3);
            $table->string('satuan', 20)->default('kg');
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('total_harga', 15, 2);
            // Khusus jasa giling
            $table->decimal('berat_daging', 10, 3)->nullable();
            $table->enum('jenis_olahan', ['bakso', 'sosis', 'tempura'])->nullable();
            $table->text('catatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
