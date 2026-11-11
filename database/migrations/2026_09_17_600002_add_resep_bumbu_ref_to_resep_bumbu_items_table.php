<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur "Import dari Bumbu Pusat" D'mentai (2026-09-17) — 1 baris resep
 * produk sekarang bisa berupa LINK ke sebuah Master Bumbu Pusat (ResepBumbu
 * tanpa item_id) alih-alih item bahan baku langsung. item_id dibuat nullable
 * (baris linked tidak punya item_id), resep_bumbu_ref_id ditambah sebagai FK
 * nullable ke resep_bumbu.id.
 *
 * Anti cyclic-reference BY CONSTRUCTION (bukan cuma validasi runtime):
 * kolom ini hanya pernah diisi lewat MasterProdukJualController::syncResep()
 * (form Produk Jual) — MasterResepBumbuController::storeItem() (form Master
 * Bumbu Pusat sendiri) TIDAK PERNAH mengisi kolom ini, jadi item milik
 * sebuah "master bumbu" selalu item_id langsung, tidak mungkin nested ke
 * bumbu lain. Kedalaman referensi maksimal 1 level: Produk -> Bumbu -> Bahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resep_bumbu_items', function (Blueprint $table) {
            $table->foreignId('resep_bumbu_ref_id')->nullable()
                ->after('item_id')->constrained('resep_bumbu')->nullOnDelete();
        });

        DB::statement('ALTER TABLE resep_bumbu_items MODIFY item_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('resep_bumbu_items', function (Blueprint $table) {
            $table->dropForeign(['resep_bumbu_ref_id']);
            $table->dropColumn('resep_bumbu_ref_id');
        });

        DB::table('resep_bumbu_items')->whereNull('item_id')->delete();
        DB::statement('ALTER TABLE resep_bumbu_items MODIFY item_id BIGINT UNSIGNED NOT NULL');
    }
};
