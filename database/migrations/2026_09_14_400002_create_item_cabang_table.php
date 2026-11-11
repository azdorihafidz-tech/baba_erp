<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 2.5 D'mentai — pivot ketersediaan produk_jual/produk_tambahan per
 * outlet (harga bisa di-override per cabang). Config murni, bukan data
 * transaksional -> hard FK cascadeDelete cukup, tidak perlu SoftDeletes.
 *
 * Fallback penting (lihat Item::tersediaDiCabang()): item TANPA row sama
 * sekali di tabel ini dianggap AKTIF di SEMUA cabang -> 21 item lama yang
 * belum pernah di-assign eksplisit tetap tampil normal di POS semua outlet,
 * tidak breaking. Row eksplisit is_active=false = sengaja di-exclude.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_cabang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('cabang_id')->constrained('cabangs')->cascadeOnDelete();
            $table->decimal('harga_override', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['item_id', 'cabang_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_cabang');
    }
};
