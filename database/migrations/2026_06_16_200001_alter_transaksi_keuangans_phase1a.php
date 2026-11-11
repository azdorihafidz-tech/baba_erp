<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksi_keuangans', 'kategori_id')) {
                $table->foreignId('kategori_id')
                    ->nullable()
                    ->after('kategori')
                    ->constrained('kategori_transaksis')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('transaksi_keuangans', 'bukti_path')) {
                $table->string('bukti_path', 500)->nullable()->after('catatan');
            }
            if (!Schema::hasColumn('transaksi_keuangans', 'setoran_pair_id')) {
                $table->unsignedBigInteger('setoran_pair_id')->nullable()->after('bukti_path');
                $table->foreign('setoran_pair_id')
                    ->references('id')
                    ->on('transaksi_keuangans')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_keuangans', 'setoran_pair_id')) {
                $table->dropForeign(['setoran_pair_id']);
                $table->dropColumn('setoran_pair_id');
            }
            if (Schema::hasColumn('transaksi_keuangans', 'bukti_path')) {
                $table->dropColumn('bukti_path');
            }
            if (Schema::hasColumn('transaksi_keuangans', 'kategori_id')) {
                $table->dropForeign(['kategori_id']);
                $table->dropColumn('kategori_id');
            }
        });
    }
};
