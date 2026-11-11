<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = $this->getIndexNames();
        $cols    = Schema::getColumnListing('absensis');

        // 1. Tambah plain index karyawan_id DULU agar FK tidak kehilangan backing index
        //    MariaDB: composite unique (karyawan_id, tanggal, cabang_id) adalah satu-satunya
        //    index dengan karyawan_id sebagai leading column → wajib ada penggantinya sebelum drop
        if (!in_array('absensis_karyawan_id_index', $indexes)) {
            DB::statement('ALTER TABLE absensis ADD INDEX absensis_karyawan_id_index (karyawan_id)');
        }

        // 2. Drop composite unique constraint lama (tidak null-aware → blokir soft-deleted rows)
        if (in_array('absensis_karyawan_tanggal_cabang_unique', $indexes)) {
            DB::statement('ALTER TABLE absensis DROP INDEX absensis_karyawan_tanggal_cabang_unique');
        }

        // 3. Tambah virtual generated column: NULL saat soft-deleted → diabaikan UNIQUE index
        //    MariaDB 10.2+ & MySQL 5.7+ mengabaikan NULL pada UNIQUE index
        if (!in_array('soft_uniq_key', $cols)) {
            DB::statement("
                ALTER TABLE absensis
                ADD COLUMN soft_uniq_key VARCHAR(80)
                    GENERATED ALWAYS AS (
                        IF(deleted_at IS NULL,
                           CONCAT(karyawan_id, '_', DATE(tanggal), '_', cabang_id),
                           NULL)
                    ) STORED
            ");
        }

        // 4. Unique index di virtual column (efektif hanya untuk baris non-deleted)
        if (!in_array('absensis_soft_unique', $indexes)) {
            DB::statement('ALTER TABLE absensis ADD UNIQUE INDEX absensis_soft_unique (soft_uniq_key)');
        }
    }

    public function down(): void
    {
        $indexes = $this->getIndexNames();
        $cols    = Schema::getColumnListing('absensis');

        if (in_array('absensis_soft_unique', $indexes)) {
            DB::statement('ALTER TABLE absensis DROP INDEX absensis_soft_unique');
        }

        if (in_array('soft_uniq_key', $cols)) {
            DB::statement('ALTER TABLE absensis DROP COLUMN soft_uniq_key');
        }

        // Kembalikan composite unique (plain karyawan_id index masih ada sebagai backing FK)
        if (!in_array('absensis_karyawan_tanggal_cabang_unique', $indexes)) {
            DB::statement('ALTER TABLE absensis ADD UNIQUE INDEX absensis_karyawan_tanggal_cabang_unique (karyawan_id, tanggal, cabang_id)');
        }

        // Hapus plain index yang ditambahkan di up() — kondisi awal tidak punya ini
        if (in_array('absensis_karyawan_id_index', $indexes)) {
            DB::statement('ALTER TABLE absensis DROP INDEX absensis_karyawan_id_index');
        }
    }

    private function getIndexNames(): array
    {
        return array_column(DB::select('SHOW INDEX FROM absensis'), 'Key_name');
    }
};
