<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->nullable()->constrained('karyawans')->nullOnDelete();
            $table->foreignId('cabang_id')->constrained('cabangs')->cascadeOnDelete();
            $table->enum('tipe', ['clock_in', 'clock_out'])->default('clock_in');
            $table->timestamp('waktu');
            $table->string('foto_absen')->nullable()->comment('Path foto saat absen');
            $table->decimal('confidence_score', 5, 2)->nullable()->comment('Skor keyakinan wajah 0-100');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('jarak_dari_cabang')->nullable()->comment('Jarak dalam meter');
            $table->foreignId('device_id')->nullable()->constrained('absen_devices')->nullOnDelete();
            $table->enum('status', ['valid', 'invalid_lokasi', 'tidak_dikenali', 'liveness_gagal'])
                ->default('tidak_dikenali');
            $table->string('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_attendances');
    }
};
