<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: hanya alter jika masih enum
        $type = DB::selectOne(
            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'bep_fixed_cost_items'
               AND COLUMN_NAME = 'kategori'"
        )?->DATA_TYPE ?? '';

        if (strtolower($type) === 'enum') {
            DB::statement(
                "ALTER TABLE bep_fixed_cost_items
                 MODIFY COLUMN kategori VARCHAR(50) NOT NULL DEFAULT 'lainnya'"
            );
        }
    }

    public function down(): void
    {
        // Kembalikan ke enum lama (nilai luar range akan jadi 'lainnya')
        DB::statement(
            "ALTER TABLE bep_fixed_cost_items
             MODIFY COLUMN kategori ENUM('gaji','sewa','depresiasi','listrik','asuransi','lainnya')
             NOT NULL DEFAULT 'lainnya'"
        );
    }
};
