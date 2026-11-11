<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_gajis', function (Blueprint $table) {
            $table->id();
            $table->decimal('tarif_lembur_per_jam', 12, 2)->default(15000);
            $table->decimal('potongan_alpa_per_hari', 12, 2)->default(50000);
            $table->decimal('potongan_telat_per_menit', 12, 2)->default(0);
            $table->unsignedTinyInteger('hari_kerja_per_minggu')->default(6);
            $table->timestamps();
        });

        // Seed satu baris default langsung di migration
        DB::table('pengaturan_gajis')->insert([
            'tarif_lembur_per_jam'     => 15000,
            'potongan_alpa_per_hari'   => 50000,
            'potongan_telat_per_menit' => 0,
            'hari_kerja_per_minggu'    => 6,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_gajis');
    }
};
