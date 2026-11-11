<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 100% additive (Rule #58) — item existing otomatis jenis='bahan_baku'
        // + track_stok=true (default), zero perubahan behavior untuk data lama.
        Schema::table('items', function (Blueprint $table) {
            $table->enum('jenis', ['bahan_baku', 'perlengkapan'])
                ->default('bahan_baku')->after('tipe');
            $table->boolean('track_stok')->default(true)->after('jenis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['jenis', 'track_stok']);
        });
    }
};
