<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksi_keuangans', 'kategori_pengeluaran')) {
                $table->string('kategori_pengeluaran', 30)->nullable()->after('kategori_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_keuangans', 'kategori_pengeluaran')) {
                $table->dropColumn('kategori_pengeluaran');
            }
        });
    }
};
