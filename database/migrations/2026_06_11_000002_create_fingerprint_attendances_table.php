<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_attendances', function (Blueprint $table) {
            $table->id();

            // Relasi ke karyawan — nullable karena mungkin PIN belum terdaftar di sistem
            $table->foreignId('karyawan_id')
                ->nullable()
                ->constrained('karyawans')
                ->nullOnDelete();

            // Raw PIN dari mesin (disimpan selalu, walau karyawan tidak ditemukan)
            $table->string('fingerprint_pin', 50);

            // Serial number mesin pengirim data
            $table->string('sn_mesin', 100);

            // Cabang diambil dari absen_devices berdasarkan SN, atau dari karyawan
            $table->foreignId('cabang_id')
                ->nullable()
                ->constrained('cabangs')
                ->nullOnDelete();

            // Waktu absen sesuai clock mesin
            $table->dateTime('waktu_absen');

            // Kode status mentah dari mesin: 0=clock_in, 1=clock_out, 4=overtime_in, 5=overtime_out
            $table->tinyInteger('status_code')->default(0);

            $table->enum('tipe', ['clock_in', 'clock_out', 'overtime_in', 'overtime_out'])
                ->default('clock_in');

            // Tipe verifikasi: 0=password, 1=fingerprint, 2=card, 3=face, 15=multi
            $table->tinyInteger('verify_type')->default(1);

            $table->integer('work_code')->default(0);

            // Baris mentah dari body POST mesin untuk debugging
            $table->text('raw_data')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Index untuk lookup cepat
            $table->index(['sn_mesin', 'waktu_absen']);
            $table->index(['karyawan_id', 'waktu_absen']);
            $table->index('fingerprint_pin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_attendances');
    }
};
