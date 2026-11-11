<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: cek apakah head_office sudah ada
        $col = DB::selectOne("
            SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'cabangs'
              AND COLUMN_NAME = 'tipe'
        ");

        if ($col && str_contains($col->COLUMN_TYPE, 'head_office')) {
            return;
        }

        DB::statement("
            ALTER TABLE cabangs
            MODIFY COLUMN tipe ENUM('cabang','gudang_pusat','head_office') NOT NULL DEFAULT 'cabang'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE cabangs
            MODIFY COLUMN tipe ENUM('cabang','gudang_pusat') NOT NULL DEFAULT 'cabang'
        ");
    }
};
