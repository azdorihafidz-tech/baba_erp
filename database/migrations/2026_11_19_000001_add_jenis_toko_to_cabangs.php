<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 3 — jenis toko (Pemegang Stok, rancangan §3) untuk cabang bertipe "cabang":
 *   baba (toko BABA, keuangan penuh) | kemitraan (toko mitra) | toko_member (toko refill milik member, member_id = pemiliknya).
 * Kemitraan & toko member hanya melihat laporan transaksi, produk, kategori (keuangan BABA terpisah — Tahap 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->string('jenis_toko', 20)->nullable()->after('tipe');
            $table->unsignedBigInteger('member_id')->nullable()->after('jenis_toko');
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', fn (Blueprint $t) => $t->dropColumn(['jenis_toko', 'member_id']));
    }
};
