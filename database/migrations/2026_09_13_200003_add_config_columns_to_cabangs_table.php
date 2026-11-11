<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 3 - POS D'mentai (2026-09-13). Config per-outlet, konsisten
     * prinsip "kalau bisa jadi config jangan hardcode" (CLAUDE.md 1.4) —
     * POS ini dirancang dipakai berbagai jenis bisnis food, jadi tiap
     * outlet bisa aktif/nonaktifkan tipe transaksi & atur fee sendiri.
     * Default semua tipe transaksi AKTIF + fee 0 supaya outlet existing
     * langsung bisa pakai tanpa setup manual dulu.
     */
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->boolean('dine_in_aktif')->default(true)->after('izinkan_sembunyi_harga_struk');
            $table->boolean('takeaway_aktif')->default(true)->after('dine_in_aktif');
            $table->boolean('frozen_aktif')->default(true)->after('takeaway_aktif');
            $table->boolean('nomor_meja_aktif')->default(true)->after('frozen_aktif');
            $table->decimal('take_away_fee', 10, 2)->default(0)->after('nomor_meja_aktif');
            $table->decimal('service_charge_persen', 5, 2)->default(0)->after('take_away_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn([
                'dine_in_aktif', 'takeaway_aktif', 'frozen_aktif',
                'nomor_meja_aktif', 'take_away_fee', 'service_charge_persen',
            ]);
        });
    }
};
