<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend face_attendances.tipe enum to include lembur_masuk, lembur_keluar
        DB::statement("ALTER TABLE face_attendances MODIFY COLUMN tipe ENUM('clock_in','clock_out','lembur_masuk','lembur_keluar') NOT NULL DEFAULT 'clock_in'");

        // Add tipe_absensi to absensis table
        Schema::table('absensis', function (Blueprint $table) {
            $table->enum('tipe_absensi', ['masuk', 'keluar', 'lembur_masuk', 'lembur_keluar'])
                  ->nullable()
                  ->after('jam_masuk');
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE face_attendances MODIFY COLUMN tipe ENUM('clock_in','clock_out') NOT NULL DEFAULT 'clock_in'");

        Schema::table('absensis', function (Blueprint $table) {
            $table->dropColumn('tipe_absensi');
        });
    }
};
