<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penggajians', function (Blueprint $table) {
            $table->decimal('tarif_lembur_per_jam_override', 12, 2)->nullable()->after('jam_lembur_total');
            $table->decimal('potongan_alpa_per_hari_override', 12, 2)->nullable()->after('tarif_lembur_per_jam_override');
            $table->boolean('uang_lembur_manual')->default(false)->after('potongan_alpa_per_hari_override');
            $table->boolean('potongan_alpa_manual')->default(false)->after('uang_lembur_manual');
        });
    }

    public function down(): void
    {
        Schema::table('penggajians', function (Blueprint $table) {
            $table->dropColumn([
                'tarif_lembur_per_jam_override',
                'potongan_alpa_per_hari_override',
                'uang_lembur_manual',
                'potongan_alpa_manual',
            ]);
        });
    }
};
