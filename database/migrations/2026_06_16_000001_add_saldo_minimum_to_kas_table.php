<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kas', function (Blueprint $table) {
            if (!Schema::hasColumn('kas', 'saldo_minimum')) {
                $table->decimal('saldo_minimum', 15, 2)->default(0)->after('saldo_sekarang');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kas', function (Blueprint $table) {
            if (Schema::hasColumn('kas', 'saldo_minimum')) {
                $table->dropColumn('saldo_minimum');
            }
        });
    }
};
