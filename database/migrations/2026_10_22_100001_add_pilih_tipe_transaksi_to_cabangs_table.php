<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLAUDE.md 4.41 — pilihan Tipe Transaksi di POS bisa disembunyikan per
 * cabang. pilih_tipe_transaksi = false → POS tidak menampilkan tombol
 * Dine-in/Takeaway/Frozen, transaksi otomatis memakai tipe_transaksi_default.
 * Default true = perilaku lama (tombol tampil).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->boolean('pilih_tipe_transaksi')->default(true)->after('nomor_meja_aktif');
            $table->string('tipe_transaksi_default', 20)->default('takeaway')->after('pilih_tipe_transaksi');
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn(['pilih_tipe_transaksi', 'tipe_transaksi_default']);
        });
    }
};
