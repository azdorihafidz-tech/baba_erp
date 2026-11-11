<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop tabel fingerprint_attendances
        Schema::dropIfExists('fingerprint_attendances');

        // Drop kolom fingerprint_pin dari karyawans
        if (Schema::hasColumn('karyawans', 'fingerprint_pin')) {
            Schema::table('karyawans', function (Blueprint $table) {
                $table->dropUnique(['fingerprint_pin']);
                $table->dropColumn('fingerprint_pin');
            });
        }
    }

    public function down(): void
    {
        // Kembalikan kolom fingerprint_pin ke karyawans
        Schema::table('karyawans', function (Blueprint $table) {
            $table->string('fingerprint_pin', 50)
                ->nullable()
                ->unique()
                ->after('nik');
        });

        // Kembalikan tabel fingerprint_attendances
        Schema::create('fingerprint_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->nullable()->constrained('karyawans')->nullOnDelete();
            $table->string('fingerprint_pin', 50);
            $table->string('sn_mesin', 100);
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->dateTime('waktu_absen');
            $table->tinyInteger('status_code')->default(0);
            $table->enum('tipe', ['clock_in', 'clock_out', 'overtime_in', 'overtime_out'])->default('clock_in');
            $table->tinyInteger('verify_type')->default(1);
            $table->integer('work_code')->default(0);
            $table->text('raw_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sn_mesin', 'waktu_absen']);
            $table->index(['karyawan_id', 'waktu_absen']);
            $table->index('fingerprint_pin');
        });
    }
};
