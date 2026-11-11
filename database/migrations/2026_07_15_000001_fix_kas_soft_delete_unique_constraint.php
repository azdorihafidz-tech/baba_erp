<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = $this->getIndexNames();
        $cols    = Schema::getColumnListing('kas');

              // 1. Selalu pastikan ada backing index khusus untuk FK cabang_id
        //    sebelum drop composite unique. Cek by nama, bukan by posisi kolom.
        if (!in_array('kas_cabang_id_index', $indexes)) {
            DB::statement('ALTER TABLE kas ADD INDEX kas_cabang_id_index (cabang_id)');
        }

        // 2. Drop composite unique constraint lama — tidak null-aware terhadap
        //    baris yang sudah soft-deleted, sehingga memblokir pembuatan kas
        //    baru dengan kombinasi cabang_id + default_untuk yang sama persis
        //    dengan kas lama yang sudah dihapus (soft delete).
        if (in_array('kas_cabang_default_unique', $indexes)) {
            DB::statement('ALTER TABLE kas DROP INDEX kas_cabang_default_unique');
        }

        // 3. Tambah virtual generated column: 'active' saat baris masih aktif,
        //    NULL saat sudah soft-deleted. MySQL/MariaDB mengabaikan NULL pada
        //    kolom manapun dalam composite UNIQUE index, jadi baris yang
        //    soft-deleted tidak akan pernah konflik dengan baris lain.
        if (!in_array('soft_uniq_key', $cols)) {
            DB::statement("
                ALTER TABLE kas
                ADD COLUMN soft_uniq_key VARCHAR(10)
                    GENERATED ALWAYS AS (
                        IF(deleted_at IS NULL, 'active', NULL)
                    ) STORED
            ");
        }

        // 4. Unique index composite baru — efektif hanya utk baris aktif.
        if (!in_array('kas_cabang_default_soft_unique', $indexes)) {
            DB::statement('ALTER TABLE kas ADD UNIQUE INDEX kas_cabang_default_soft_unique (cabang_id, default_untuk, soft_uniq_key)');
        }
    }

    public function down(): void
    {
        $indexes = $this->getIndexNames();
        $cols    = Schema::getColumnListing('kas');

        if (in_array('kas_cabang_default_soft_unique', $indexes)) {
            DB::statement('ALTER TABLE kas DROP INDEX kas_cabang_default_soft_unique');
        }

        if (in_array('soft_uniq_key', $cols)) {
            DB::statement('ALTER TABLE kas DROP COLUMN soft_uniq_key');
        }

        if (!in_array('kas_cabang_default_unique', $indexes)) {
            DB::statement('ALTER TABLE kas ADD UNIQUE INDEX kas_cabang_default_unique (cabang_id, default_untuk)');
        }

        // Hapus plain index yang ditambahkan di up() — kondisi awal tidak
        // punya index ini (kalau memang kita yang menambahkannya).
        if (in_array('kas_cabang_id_index', $indexes)) {
            DB::statement('ALTER TABLE kas DROP INDEX kas_cabang_id_index');
        }
    }

    private function getIndexNames(): array
    {
        return array_column(DB::select('SHOW INDEX FROM kas'), 'Key_name');
    }
};
