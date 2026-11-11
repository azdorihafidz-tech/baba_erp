<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 3 - POS D'mentai (2026-09-13). Simpan NOMINAL fee yang benar-benar
     * dikenakan saat order dibuat (bukan cuma baca config cabang saat ini) —
     * config `cabangs.take_away_fee`/`service_charge_persen` bisa berubah
     * nanti, tapi histori order harus tetap membekukan angka aslinya.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('service_charge', 12, 2)->default(0)->after('diskon');
            $table->decimal('take_away_fee', 12, 2)->default(0)->after('service_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['service_charge', 'take_away_fee']);
        });
    }
};
