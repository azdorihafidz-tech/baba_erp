<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 3 — kasir member (Toko Saya › Kasir di aplikasi member): member menjual parfum dari stoknya
 * ke pelanggan. Stok member berkurang (stok_member_mutasi jenis kasir). Tidak menambah RO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan_member', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 30)->unique();                   // KSR-202611-0001
            $table->unsignedBigInteger('member_id');
            $table->decimal('total', 15, 2);
            $table->decimal('modal', 15, 2)->default(0);
            $table->string('bayar', 10);                             // tunai / transfer / qris
            $table->decimal('diterima', 15, 2)->nullable();          // uang diterima (tunai) → kembalian
            $table->string('pelanggan_nama', 100)->nullable();
            $table->string('pelanggan_hp', 30)->nullable();
            $table->string('catatan', 255)->nullable();
            $table->string('status', 10)->default('selesai');       // selesai / batal
            $table->string('batal_alasan', 255)->nullable();
            $table->timestamp('batal_at')->nullable();
            $table->timestamps();
            $table->index(['member_id', 'created_at']);
        });

        Schema::create('penjualan_member_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_member_id')->constrained('penjualan_member')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('nama', 150);
            $table->string('kategori', 50)->nullable();
            $table->unsignedInteger('qty');
            $table->decimal('harga', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('modal', 15, 2)->default(0);             // modal rata-rata per satuan saat dijual
            $table->timestamps();
        });

        Schema::table('stok_member', function (Blueprint $table) {
            $table->decimal('harga_jual', 15, 2)->nullable()->after('nilai_rata'); // harga jual kasir pilihan member
        });
    }

    public function down(): void
    {
        Schema::table('stok_member', fn (Blueprint $t) => $t->dropColumn('harga_jual'));
        Schema::dropIfExists('penjualan_member_item');
        Schema::dropIfExists('penjualan_member');
    }
};
