<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah default tunjangan per karyawan
        Schema::table('karyawans', function (Blueprint $table) {
            $table->decimal('tunjangan_jabatan', 15, 2)->default(0)->after('gaji_pokok');
            $table->decimal('tunjangan_makan', 15, 2)->default(0)->after('tunjangan_jabatan');
            $table->decimal('tunjangan_transport', 15, 2)->default(0)->after('tunjangan_makan');
            $table->decimal('tunjangan_bpjs_kesehatan_persen', 5, 2)->default(1)->after('tunjangan_transport'); // % ditanggung karyawan
            $table->decimal('tunjangan_bpjs_tk_persen', 5, 2)->default(2)->after('tunjangan_bpjs_kesehatan_persen'); // JHT %
        });

        // Perluas kolom penggajian
        Schema::table('penggajians', function (Blueprint $table) {
            // Penambahan detail tunjangan
            $table->decimal('tunjangan_jabatan', 15, 2)->default(0)->after('tunjangan');
            $table->decimal('tunjangan_makan', 15, 2)->default(0)->after('tunjangan_jabatan');
            $table->decimal('tunjangan_transport', 15, 2)->default(0)->after('tunjangan_makan');
            $table->decimal('tunjangan_kehadiran', 15, 2)->default(0)->after('tunjangan_transport');
            $table->decimal('insentif', 15, 2)->default(0)->after('tunjangan_kehadiran');
            $table->decimal('thr', 15, 2)->default(0)->after('insentif');
            $table->decimal('komisi', 15, 2)->default(0)->after('thr');
            // Potongan detail
            $table->decimal('bpjs_kesehatan', 15, 2)->default(0)->after('potongan_lain');
            $table->decimal('bpjs_ketenagakerjaan', 15, 2)->default(0)->after('bpjs_kesehatan');
            $table->decimal('pph21', 15, 2)->default(0)->after('bpjs_ketenagakerjaan');
            $table->decimal('kasbon', 15, 2)->default(0)->after('pph21');
        });
    }

    public function down(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropColumn(['tunjangan_jabatan','tunjangan_makan','tunjangan_transport','tunjangan_bpjs_kesehatan_persen','tunjangan_bpjs_tk_persen']);
        });
        Schema::table('penggajians', function (Blueprint $table) {
            $table->dropColumn(['tunjangan_jabatan','tunjangan_makan','tunjangan_transport','tunjangan_kehadiran','insentif','thr','komisi','bpjs_kesehatan','bpjs_ketenagakerjaan','pph21','kasbon']);
        });
    }
};
