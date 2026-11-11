<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 2a — toko online:
 * - member_akun: salinan identitas akun aplikasi member (diperbarui tiap permintaan dari aplikasi member).
 * - produk_online: barang (parfum botol milik gudang) yang dijual di menu Belanja aplikasi member.
 * - produk_online_harga: harga tetap / gudang / per jabatan / khusus akun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_akun', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->unique();      // tbl_user.id di aplikasi member
            $table->string('nama', 150);
            $table->string('no_id', 30)->nullable();
            $table->string('no_member', 20)->nullable();             // BP-00284
            $table->unsignedInteger('jabatan_id')->nullable();       // tbl_mvo_type.id
            $table->string('jabatan', 60)->nullable();
            $table->unsignedBigInteger('parent_member_id')->nullable(); // upline langsung
            $table->string('cabang', 20)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamp('diperbarui_at')->nullable();
            $table->timestamps();
            $table->index('jabatan_id');
        });

        Schema::create('produk_online', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->unique()->constrained('items')->restrictOnDelete();
            $table->string('nama_tampil', 150);
            $table->string('kategori', 50)->nullable();               // Pria / Wanita / Unisex …
            $table->text('deskripsi')->nullable();
            $table->string('foto', 255)->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('urutan')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('produk_online_harga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_online_id')->constrained('produk_online')->cascadeOnDelete();
            $table->enum('jenis', ['tetap', 'gudang', 'jabatan', 'akun']);
            $table->unsignedInteger('jabatan_id')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->decimal('harga', 15, 2);
            $table->timestamps();
            $table->index(['produk_online_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_online_harga');
        Schema::dropIfExists('produk_online');
        Schema::dropIfExists('member_akun');
    }
};
