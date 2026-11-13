<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 8 — Jadwal Otomatis: saklar & jam tiap tugas terjadwal + catatan hasil terakhir, dan detak cron
 * (pengaturan_umums.cron_terakhir). Hosting mematikan proc_open → tugas dijalankan di dalam proses (JadwalOtomatisService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_otomatis', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->boolean('aktif')->default(false);
            $table->string('jam', 5)->nullable();                     // HH:MM untuk tugas harian yang jamnya boleh diatur
            $table->json('opsi')->nullable();                         // mis. {"hari_sebelum": 3} pengingat stok opname
            $table->timestamp('terakhir_mulai')->nullable();
            $table->timestamp('terakhir_selesai')->nullable();
            $table->string('terakhir_status', 10)->nullable();        // berhasil / gagal
            $table->string('terakhir_pesan', 500)->nullable();
            $table->string('terakhir_oleh', 20)->nullable();          // jadwal / manual
            $table->timestamps();
        });

        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->timestamp('cron_terakhir')->nullable()->after('opname_batas_foto_hilang');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->dropColumn('cron_terakhir');
        });
        Schema::dropIfExists('jadwal_otomatis');
    }
};
