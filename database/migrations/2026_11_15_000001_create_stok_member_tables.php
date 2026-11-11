<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 2c — order ke upline & stok member (rancangan §3, §6.1):
 * - stok_member (+ _mutasi): stok parfum milik member (bertambah saat pesanan diterima, berkurang saat dijual ke downline).
 * - toko_online_aturan_sumber: boleh belanja ke gudang / upline (semua / jabatan / akun). Tanpa aturan = gudang saja.
 * - toko_online_rekening.member_id: rekening upline untuk penjualan ke downline (cabang_id jadi boleh kosong).
 * - member_akun.upline_member_id: upline terdekat yang benar-benar akun (kursi kosong dilewati) — dikirim aplikasi member.
 * - pesanan_online.cara_bayar + 'tunai' (hanya pesanan ke upline); pesanan_online_bayar.dicek_member_id (dicek upline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_member', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->integer('qty')->default(0);
            $table->decimal('nilai_rata', 15, 2)->default(0);       // harga beli rata-rata per satuan
            $table->timestamps();
            $table->unique(['member_id', 'item_id']);
        });

        Schema::create('stok_member_mutasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->integer('qty');                                  // + masuk, − keluar
            $table->integer('sisa');
            $table->string('jenis', 20);                             // beli / jual / retur_masuk / retur_keluar / penyesuaian
            $table->decimal('harga', 15, 2)->nullable();
            $table->string('referensi_type', 30)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['member_id', 'item_id']);
        });

        Schema::create('toko_online_aturan_sumber', function (Blueprint $table) {
            $table->id();
            $table->enum('cakupan', ['semua', 'jabatan', 'akun']);
            $table->unsignedInteger('jabatan_id')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->boolean('boleh_gudang')->default(true);
            $table->boolean('boleh_upline')->default(false);
            $table->boolean('aktif')->default(true);
            $table->string('catatan', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('toko_online_rekening', function (Blueprint $table) {
            $table->unsignedBigInteger('cabang_id')->nullable()->change();
            $table->unsignedBigInteger('member_id')->nullable()->after('cabang_id');
            $table->index('member_id');
        });

        Schema::table('member_akun', function (Blueprint $table) {
            $table->unsignedBigInteger('upline_member_id')->nullable()->after('parent_member_id');
        });

        Schema::table('pesanan_online', function (Blueprint $table) {
            $table->enum('cara_bayar', ['transfer', 'tagihan', 'tunai'])->change();
            $table->index(['sumber_member_id', 'status']);
        });

        Schema::table('pesanan_online_bayar', function (Blueprint $table) {
            $table->unsignedBigInteger('dicek_member_id')->nullable()->after('dicek_by');
        });
    }

    public function down(): void
    {
        Schema::table('pesanan_online_bayar', fn (Blueprint $t) => $t->dropColumn('dicek_member_id'));
        Schema::table('pesanan_online', function (Blueprint $t) {
            $t->dropIndex(['sumber_member_id', 'status']);
            $t->enum('cara_bayar', ['transfer', 'tagihan'])->change();
        });
        Schema::table('member_akun', fn (Blueprint $t) => $t->dropColumn('upline_member_id'));
        Schema::table('toko_online_rekening', function (Blueprint $t) {
            $t->dropIndex(['member_id']);
            $t->dropColumn('member_id');
        });
        Schema::dropIfExists('toko_online_aturan_sumber');
        Schema::dropIfExists('stok_member_mutasi');
        Schema::dropIfExists('stok_member');
    }
};
