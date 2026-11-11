<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 7 D'mentai — Extend LoyaltyService::auto_track (CLAUDE.md 4.1 B2).
 * `sumber_data` sebelumnya cuma py 1 nilai valid (orders.berat_daging_kg,
 * lini jasa giling Berkah Mulyo) — tambah 2 basis baru utk D'mentai:
 * total belanja Rp dan jumlah transaksi. Extend (bukan replace) supaya
 * program lama yang masih pakai basis kg tetap valid (backward compat).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loyalty_programs MODIFY sumber_data ENUM(
            'orders.berat_daging_kg','orders.total_bayar','orders.count'
        ) NOT NULL DEFAULT 'orders.berat_daging_kg'");
    }

    public function down(): void
    {
        DB::table('loyalty_programs')
            ->whereIn('sumber_data', ['orders.total_bayar', 'orders.count'])
            ->update(['sumber_data' => 'orders.berat_daging_kg']);

        DB::statement("ALTER TABLE loyalty_programs MODIFY sumber_data ENUM(
            'orders.berat_daging_kg'
        ) NOT NULL DEFAULT 'orders.berat_daging_kg'");
    }
};
