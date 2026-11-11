<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nik', 20)->unique(); // nomor induk karyawan
            $table->string('nama_lengkap');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->date('tanggal_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('jabatan');
            $table->enum('tipe_karyawan', ['tetap', 'kontrak', 'harian'])->default('tetap');
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->string('no_rekening', 30)->nullable();
            $table->string('nama_bank', 50)->nullable();
            $table->enum('status', ['aktif', 'tidak_aktif', 'keluar'])->default('aktif');
            $table->foreignId('atasan_id')->nullable()->constrained('karyawans')->nullOnDelete();
            $table->string('foto')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('absensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawans')->cascadeOnDelete();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->date('tanggal');
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpha', 'libur', 'cuti'])->default('hadir');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();
            $table->decimal('jam_lembur', 5, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['karyawan_id', 'tanggal']);
        });

        Schema::create('penggajians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawans')->cascadeOnDelete();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->string('periode', 7); // YYYY-MM
            $table->integer('jumlah_hari_kerja')->default(0);
            $table->integer('jumlah_hadir')->default(0);
            $table->integer('jumlah_alpha')->default(0);
            $table->decimal('jam_lembur_total', 8, 2)->default(0);
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('tunjangan', 15, 2)->default(0);
            $table->decimal('uang_lembur', 15, 2)->default(0);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('potongan_absensi', 15, 2)->default(0);
            $table->decimal('potongan_lain', 15, 2)->default(0);
            $table->decimal('total_gaji', 15, 2)->default(0);
            $table->enum('status', ['draft', 'disetujui', 'dibayar'])->default('draft');
            $table->date('tanggal_bayar')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['karyawan_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penggajians');
        Schema::dropIfExists('absensis');
        Schema::dropIfExists('karyawans');
    }
};
