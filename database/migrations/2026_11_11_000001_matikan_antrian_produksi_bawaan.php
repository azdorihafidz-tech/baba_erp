<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA: antrian produksi (fitur dimsum: nomor antrian, TV display) tidak dipakai usaha parfum.
 * Bawaan cabang baru = mati, dan semua cabang yang ada dimatikan. Kode antrian di POS dibereskan di Tahap 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cabangs', 'antrian_produksi_aktif')) {
            return;
        }
        DB::statement('ALTER TABLE cabangs MODIFY antrian_produksi_aktif TINYINT(1) NOT NULL DEFAULT 0');
        DB::table('cabangs')->update(['antrian_produksi_aktif' => 0]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cabangs', 'antrian_produksi_aktif')) {
            return;
        }
        // Hanya nilai bawaan yang dikembalikan; nilai per cabang tidak dinyalakan ulang.
        DB::statement('ALTER TABLE cabangs MODIFY antrian_produksi_aktif TINYINT(1) NOT NULL DEFAULT 1');
    }
};
