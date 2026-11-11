<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B (2026-10-18, CLAUDE.md 4.38) — wajib upload bukti untuk
 * pembayaran Transfer / QRIS, diatur per cabang. Default TRUE = sama dgn
 * perilaku POS sebelumnya (form POS selalu meminta bukti non-tunai).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->boolean('wajib_bukti_transfer')->default(true)->after('service_charge_persen');
            $table->boolean('wajib_bukti_qris')->default(true)->after('wajib_bukti_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn(['wajib_bukti_transfer', 'wajib_bukti_qris']);
        });
    }
};
