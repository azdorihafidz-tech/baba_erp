<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Porting Dimsum 4.51 temuan G ke BABA: Pengaturan Penggajian → "Potongan telat per menit" dulu
 * tersimpan tapi tidak pernah dipakai menghitung gaji. Kolom 0 → slip lama tidak berubah.
 * (Bayar gaji ke kas sudah diperbaiki BABA di Tahap 5 lewat JurnalGajiService — tidak memakai kolom kas Dimsum.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penggajians', function (Blueprint $table) {
            if (! Schema::hasColumn('penggajians', 'menit_telat_total')) {
                $table->unsignedInteger('menit_telat_total')->default(0)->after('jumlah_alpha');
            }
            if (! Schema::hasColumn('penggajians', 'potongan_telat')) {
                $table->decimal('potongan_telat', 15, 2)->default(0)->after('potongan_absensi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('penggajians', function (Blueprint $table) {
            $table->dropColumn(['menit_telat_total', 'potongan_telat']);
        });
    }
};
