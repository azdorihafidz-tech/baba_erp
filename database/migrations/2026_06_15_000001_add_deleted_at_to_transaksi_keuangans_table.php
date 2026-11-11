<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Column may already exist if added manually or by a previous deploy
        if (!Schema::hasColumn('transaksi_keuangans', 'deleted_at')) {
            Schema::table('transaksi_keuangans', function (Blueprint $table) {
                $table->softDeletes()->after('catatan');
            });
        }
    }

    public function down(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
