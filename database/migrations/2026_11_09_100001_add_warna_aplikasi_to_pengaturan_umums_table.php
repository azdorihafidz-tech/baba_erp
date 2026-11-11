<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.48 — Warna Aplikasi di Pengaturan Umum. Kolom NULL = warna bawaan program
 * (oranye #FF6B00, sidebar hitam #1A1A1A, latar krim #FFF8E7), jadi setelah
 * migrate tampilan tidak berubah sampai Owner memilih warna sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->string('warna_utama', 7)->nullable()->after('telepon');
            $table->string('warna_sidebar', 7)->nullable()->after('warna_utama');
            $table->string('warna_latar', 7)->nullable()->after('warna_sidebar');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->dropColumn(['warna_utama', 'warna_sidebar', 'warna_latar']);
        });
    }
};
