<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 3 - POS D'mentai (2026-09-13). 100% additive — order lama
     * (kalaupun ada) tetap valid, kolom nullable/default aman.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('tipe_transaksi', ['dine_in', 'takeaway', 'frozen'])
                ->nullable()->after('tipe_order');
            $table->string('nomor_meja', 20)->nullable()->after('tipe_transaksi');
            $table->date('tanggal_expired_frozen')->nullable()->after('nomor_meja');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tipe_transaksi', 'nomor_meja', 'tanggal_expired_frozen']);
        });
    }
};
