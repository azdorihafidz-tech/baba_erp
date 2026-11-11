<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Extend face_attendances.status to include 'duplikat' ─────────
        // Idempotent: check current enum values before modifying
        $currentType = DB::select("SHOW COLUMNS FROM face_attendances WHERE Field='status'")[0]->Type ?? '';
        if (!str_contains($currentType, 'duplikat')) {
            DB::statement("ALTER TABLE face_attendances MODIFY COLUMN status ENUM('valid','invalid_lokasi','tidak_dikenali','liveness_gagal','duplikat') NOT NULL DEFAULT 'tidak_dikenali'");
        }

        // ── 2. Add new columns to absensis (additive only — idempotent) ──────
        Schema::table('absensis', function (Blueprint $table) {
            $cols = Schema::getColumnListing('absensis');

            if (!in_array('jam_lembur_masuk', $cols))
                $table->time('jam_lembur_masuk')->nullable()->after('jam_keluar');
            if (!in_array('jam_lembur_keluar', $cols))
                $table->time('jam_lembur_keluar')->nullable()->after('jam_lembur_masuk');

            if (!in_array('lat_masuk', $cols))
                $table->decimal('lat_masuk', 10, 8)->nullable()->after('jam_lembur_keluar');
            if (!in_array('lng_masuk', $cols))
                $table->decimal('lng_masuk', 11, 8)->nullable()->after('lat_masuk');
            if (!in_array('lat_keluar', $cols))
                $table->decimal('lat_keluar', 10, 8)->nullable()->after('lng_masuk');
            if (!in_array('lng_keluar', $cols))
                $table->decimal('lng_keluar', 11, 8)->nullable()->after('lat_keluar');
            if (!in_array('lat_lembur_masuk', $cols))
                $table->decimal('lat_lembur_masuk', 10, 8)->nullable()->after('lng_keluar');
            if (!in_array('lng_lembur_masuk', $cols))
                $table->decimal('lng_lembur_masuk', 11, 8)->nullable()->after('lat_lembur_masuk');
            if (!in_array('lat_lembur_keluar', $cols))
                $table->decimal('lat_lembur_keluar', 10, 8)->nullable()->after('lng_lembur_masuk');
            if (!in_array('lng_lembur_keluar', $cols))
                $table->decimal('lng_lembur_keluar', 11, 8)->nullable()->after('lat_lembur_keluar');

            if (!in_array('foto_masuk', $cols))
                $table->string('foto_masuk')->nullable()->after('lng_lembur_keluar');
            if (!in_array('foto_keluar', $cols))
                $table->string('foto_keluar')->nullable()->after('foto_masuk');
            if (!in_array('foto_lembur_masuk', $cols))
                $table->string('foto_lembur_masuk')->nullable()->after('foto_keluar');
            if (!in_array('foto_lembur_keluar', $cols))
                $table->string('foto_lembur_keluar')->nullable()->after('foto_lembur_masuk');

            if (!in_array('face_attendance_masuk_id', $cols))
                $table->unsignedBigInteger('face_attendance_masuk_id')->nullable()->after('foto_lembur_keluar');
            if (!in_array('face_attendance_keluar_id', $cols))
                $table->unsignedBigInteger('face_attendance_keluar_id')->nullable()->after('face_attendance_masuk_id');
            if (!in_array('face_attendance_lembur_masuk_id', $cols))
                $table->unsignedBigInteger('face_attendance_lembur_masuk_id')->nullable()->after('face_attendance_keluar_id');
            if (!in_array('face_attendance_lembur_keluar_id', $cols))
                $table->unsignedBigInteger('face_attendance_lembur_keluar_id')->nullable()->after('face_attendance_lembur_masuk_id');
        });

        // Add foreign keys only if columns exist but FK doesn't yet
        $existingFKs = array_column(
            DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME='absensis' AND CONSTRAINT_TYPE='FOREIGN KEY' AND TABLE_SCHEMA=DATABASE()"),
            'CONSTRAINT_NAME'
        );
        Schema::table('absensis', function (Blueprint $table) use ($existingFKs) {
            if (!in_array('absensis_face_attendance_masuk_id_foreign', $existingFKs))
                $table->foreign('face_attendance_masuk_id')->references('id')->on('face_attendances')->nullOnDelete();
            if (!in_array('absensis_face_attendance_keluar_id_foreign', $existingFKs))
                $table->foreign('face_attendance_keluar_id')->references('id')->on('face_attendances')->nullOnDelete();
            if (!in_array('absensis_face_attendance_lembur_masuk_id_foreign', $existingFKs))
                $table->foreign('face_attendance_lembur_masuk_id')->references('id')->on('face_attendances')->nullOnDelete();
            if (!in_array('absensis_face_attendance_lembur_keluar_id_foreign', $existingFKs))
                $table->foreign('face_attendance_lembur_keluar_id')->references('id')->on('face_attendances')->nullOnDelete();
        });

        // ── 3. Change unique constraint ───────────────────────────────────────
        // IMPORTANT: Add new unique index FIRST so MySQL still has a karyawan_id
        // index to satisfy the FK on karyawan_id before dropping the old one.
        $existingIndexes = array_column(
            DB::select("SHOW INDEX FROM absensis"),
            'Key_name'
        );

        if (!in_array('absensis_karyawan_tanggal_cabang_unique', $existingIndexes)) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->unique(
                    ['karyawan_id', 'tanggal', 'cabang_id'],
                    'absensis_karyawan_tanggal_cabang_unique'
                );
            });
        }

        if (in_array('absensis_karyawan_id_tanggal_unique', $existingIndexes)) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->dropUnique('absensis_karyawan_id_tanggal_unique');
            });
        }
    }

    public function down(): void
    {
        // Restore old unique (add first, then drop new)
        $existingIndexes = array_column(DB::select("SHOW INDEX FROM absensis"), 'Key_name');

        if (!in_array('absensis_karyawan_id_tanggal_unique', $existingIndexes)) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->unique(['karyawan_id', 'tanggal'], 'absensis_karyawan_id_tanggal_unique');
            });
        }
        if (in_array('absensis_karyawan_tanggal_cabang_unique', $existingIndexes)) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->dropUnique('absensis_karyawan_tanggal_cabang_unique');
            });
        }

        Schema::table('absensis', function (Blueprint $table) {
            $table->dropForeign(['face_attendance_masuk_id']);
            $table->dropForeign(['face_attendance_keluar_id']);
            $table->dropForeign(['face_attendance_lembur_masuk_id']);
            $table->dropForeign(['face_attendance_lembur_keluar_id']);

            $table->dropColumn([
                'jam_lembur_masuk', 'jam_lembur_keluar',
                'lat_masuk', 'lng_masuk', 'lat_keluar', 'lng_keluar',
                'lat_lembur_masuk', 'lng_lembur_masuk',
                'lat_lembur_keluar', 'lng_lembur_keluar',
                'foto_masuk', 'foto_keluar', 'foto_lembur_masuk', 'foto_lembur_keluar',
                'face_attendance_masuk_id', 'face_attendance_keluar_id',
                'face_attendance_lembur_masuk_id', 'face_attendance_lembur_keluar_id',
            ]);
        });

        DB::statement("ALTER TABLE face_attendances MODIFY COLUMN status ENUM('valid','invalid_lokasi','tidak_dikenali','liveness_gagal') NOT NULL DEFAULT 'tidak_dikenali'");
    }
};
