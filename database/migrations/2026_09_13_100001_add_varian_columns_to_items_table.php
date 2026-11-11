<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 2 - Master Data (2026-09-13). 100% additive — item existing
     * otomatis punya_varian=false/stok_per_varian=false (default kolom),
     * zero perubahan behavior untuk data lama.
     *
     * `foto` dibundel di sini sekalian (bukan migration terpisah) supaya
     * Tahap 3 (POS grid produk dengan gambar) tidak perlu migration baru
     * lagi — kolom ini belum dipakai UI manapun sampai Tahap 3.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('punya_varian')->default(false)->after('is_active');
            $table->boolean('stok_per_varian')->default(false)->after('punya_varian');
            $table->string('foto')->nullable()->after('stok_per_varian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['punya_varian', 'stok_per_varian', 'foto']);
        });
    }
};
