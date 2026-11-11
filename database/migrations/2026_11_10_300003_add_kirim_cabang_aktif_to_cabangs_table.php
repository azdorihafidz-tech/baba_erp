<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.49 (permintaan Owner) — saklar "Aktifkan Kirim ke Cabang di POS" per cabang
 * Gudang Pusat. Bawaan MATI → POS Gudang tetap seperti sebelumnya sampai
 * dinyalakan di Cabang → Edit → Config POS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->boolean('kirim_cabang_aktif')->default(false)->after('wajib_bukti_qris');
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn('kirim_cabang_aktif');
        });
    }
};