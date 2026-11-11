<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kategori_transaksis', function (Blueprint $table) {
            if (!Schema::hasColumn('kategori_transaksis', 'kode_akun_coa')) {
                // Tanpa FK keras ke chart_of_accounts.kode — konsisten dengan
                // parent_kode di ChartOfAccount sendiri (hindari FK constraint
                // yang bisa memblokir soft-delete/reorganisasi kode akun).
                $table->string('kode_akun_coa', 6)->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('kategori_transaksis', 'tipe_biaya')) {
                $table->enum('tipe_biaya', ['tetap', 'variabel'])->nullable()->after('kode_akun_coa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kategori_transaksis', function (Blueprint $table) {
            if (Schema::hasColumn('kategori_transaksis', 'tipe_biaya')) {
                $table->dropColumn('tipe_biaya');
            }
            if (Schema::hasColumn('kategori_transaksis', 'kode_akun_coa')) {
                $table->dropColumn('kode_akun_coa');
            }
        });
    }
};
