<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closing / Tutup Buku Tahap 1 — tanggal mulai periode bulanan
 * (mis. 26 = periode 26 bulan lalu s/d 25 bulan ini). Default 1 =
 * bulan kalender biasa, supaya tidak mengubah apa pun sampai Owner
 * mengisinya sendiri di Pengaturan Umum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->unsignedTinyInteger('tanggal_mulai_periode')->default(1)->after('alamat_perusahaan');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->dropColumn('tanggal_mulai_periode');
        });
    }
};
