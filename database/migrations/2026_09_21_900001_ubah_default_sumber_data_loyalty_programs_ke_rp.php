<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug fix 2026-09-21 (Owner: "tipe program auto-track pilihan kumulatif kg
 * giling, padahal kita tidak pakai kilo, ubah jadi total pembelian"):
 * `loyalty_programs.sumber_data`/`satuan_qty` masih ber-DEFAULT
 * 'orders.berat_daging_kg'/'kg' warisan Berkah Mulyo (migration
 * 2026_08_11_000001, di-widen jadi 3 pilihan di 2026_09_17_600001 tapi
 * DEFAULT-nya TIDAK ikut diubah). D'mentai (retail dimsum) tidak pernah
 * pakai basis kg -- default seharusnya "Total Belanja (Rp)"
 * (`orders.total_bayar`/`Rp`), basis yang genuinely relevan untuk bisnis
 * retail. `enum('orders.berat_daging_kg', ...)` TETAP DIPERTAHANKAN sbg
 * pilihan (backward compat kalau ada program lama pakai basis itu) --
 * cuma DEFAULT-nya yang berubah, bukan value validnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loyalty_programs MODIFY sumber_data ENUM(
            'orders.berat_daging_kg','orders.total_bayar','orders.count'
        ) NOT NULL DEFAULT 'orders.total_bayar'");

        DB::statement("ALTER TABLE loyalty_programs MODIFY satuan_qty VARCHAR(20) NOT NULL DEFAULT 'Rp'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loyalty_programs MODIFY sumber_data ENUM(
            'orders.berat_daging_kg','orders.total_bayar','orders.count'
        ) NOT NULL DEFAULT 'orders.berat_daging_kg'");

        DB::statement("ALTER TABLE loyalty_programs MODIFY satuan_qty VARCHAR(20) NOT NULL DEFAULT 'kg'");
    }
};
