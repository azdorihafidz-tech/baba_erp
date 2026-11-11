<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemisahan data per cabang (CLAUDE.md 4.39, 2026-10-19) — fondasi skema:
 * - items.cabang_id & resep_bumbu.cabang_id = pemilik cabang (NULL = data lama
 *   yang belum dipecah; dipecah oleh PemecahanDataCabangService).
 * - Kode unik PER CABANG (dulu unik global) — barang sama di cabang berbeda
 *   dipasangkan lewat kode yang sama.
 * - pemecahan_cabang_maps = catatan barang lama → salinan per cabang, dipakai
 *   untuk membatalkan pemecahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('cabang_id')->nullable()->after('id')->constrained('cabangs')->nullOnDelete();
            $table->dropUnique('items_kode_item_unique');
            $table->unique(['cabang_id', 'kode_item'], 'items_cabang_kode_unique');
        });

        Schema::table('resep_bumbu', function (Blueprint $table) {
            $table->foreignId('cabang_id')->nullable()->after('id')->constrained('cabangs')->nullOnDelete();
            $table->dropUnique('resep_bumbu_kode_unique');
            $table->unique(['cabang_id', 'kode'], 'resep_bumbu_cabang_kode_unique');
        });

        Schema::create('pemecahan_cabang_maps', function (Blueprint $table) {
            $table->id();
            $table->string('tipe', 20); // item | resep_bumbu | item_variant
            $table->unsignedBigInteger('lama_id');
            $table->unsignedBigInteger('baru_id');
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->json('data_lama')->nullable(); // nilai asli baris induk (harga dll) utk dikembalikan
            $table->timestamp('created_at')->nullable();
            $table->index(['tipe', 'lama_id']);
        });
    }

    public function down(): void
    {
        // Kembalikan data dulu (hapus salinan, arahkan ulang riwayat) baru skema.
        if (Schema::hasTable('pemecahan_cabang_maps') && DB::table('pemecahan_cabang_maps')->exists()) {
            app(\App\Services\PemecahanDataCabangService::class)->batalkan();
        }

        Schema::dropIfExists('pemecahan_cabang_maps');

        // FK dilepas dulu — index unik (cabang_id, kode) dipakai FK sehingga tidak bisa dihapus duluan
        Schema::table('resep_bumbu', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->dropUnique('resep_bumbu_cabang_kode_unique');
            $table->dropColumn('cabang_id');
            $table->unique('kode', 'resep_bumbu_kode_unique');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->dropUnique('items_cabang_kode_unique');
            $table->dropColumn('cabang_id');
            $table->unique('kode_item', 'items_kode_item_unique');
        });
    }
};
