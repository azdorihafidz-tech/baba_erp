<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 3 - POS D'mentai (2026-09-13). Sebelumnya `resep_bumbu` cuma
     * tertaut ke `jenis_olahan_id` (dipilih manual oleh kasir di alur jasa
     * giling lama). POS baru butuh resep otomatis-ketemu saat kasir klik
     * produk dari grid — makanya ditambah relasi langsung ke item produk
     * jadi. Nullable: 1 item = 0 atau 1 resep (item tanpa resep = minuman
     * kemasan jadi, Item Tambahan, dll — potong stok cukup 1 unit item itu
     * sendiri, tanpa breakdown bahan baku).
     */
    public function up(): void
    {
        Schema::table('resep_bumbu', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()
                ->after('jenis_olahan_id')->constrained('items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resep_bumbu', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropColumn('item_id');
        });
    }
};
