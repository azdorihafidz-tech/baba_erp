<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tahap 4 - Rename D'mentai (2026-09-13). Ditunda dari Tahap 2 supaya
     * dikerjakan SEKALI LENGKAP setelah kebutuhan POS jelas (Tahap 3) —
     * lihat CLAUDE.md 8.3. Basis takaran resep sekarang "per 1 unit
     * produksi" (porsi/pcs), bukan lagi "per 1 kg gilingan".
     *
     * MariaDB server ini 10.4.32 — DI BAWAH 10.5.2, jadi TIDAK support
     * syntax native `RENAME COLUMN`. Pakai `CHANGE COLUMN` (syntax lama,
     * kompatibel, tidak butuh doctrine/dbal yang memang belum terinstall).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE resep_bumbu_items CHANGE qty_per_kg qty_per_unit DECIMAL(10,3) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE resep_bumbu_items CHANGE qty_per_unit qty_per_kg DECIMAL(10,3) NOT NULL');
    }
};
