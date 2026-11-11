<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksi_keuangans', 'status_setoran')) {
                $table->enum('status_setoran', ['menunggu_diterima', 'diterima', 'ditolak', 'dibatalkan'])
                      ->nullable()->after('setoran_pair_id');
            }
            if (!Schema::hasColumn('transaksi_keuangans', 'alasan_tolak_setoran')) {
                $table->text('alasan_tolak_setoran')->nullable()->after('status_setoran');
            }
            if (!Schema::hasColumn('transaksi_keuangans', 'diterima_oleh_id')) {
                $table->foreignId('diterima_oleh_id')->nullable()->after('alasan_tolak_setoran')
                      ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('transaksi_keuangans', 'waktu_diterima')) {
                $table->timestamp('waktu_diterima')->nullable()->after('diterima_oleh_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table) {
            $table->dropForeign(['diterima_oleh_id']);
            $table->dropColumn(['status_setoran', 'alasan_tolak_setoran', 'diterima_oleh_id', 'waktu_diterima']);
        });
    }
};
