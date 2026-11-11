<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transaksis', function (Blueprint $table) {
            $table->id();
            $table->string('nama_template');
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->foreignId('kas_id')->nullable()->constrained('kas')->nullOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('kategori_transaksis')->nullOnDelete();
            $table->enum('tipe', ['pemasukan', 'pengeluaran']);
            $table->decimal('jumlah', 15, 2);
            $table->string('keterangan');
            $table->enum('frekuensi', ['harian', 'mingguan', 'bulanan', 'tahunan'])->default('bulanan');
            $table->tinyInteger('tanggal_jatuh_tempo')->default(1); // 1-31 for bulanan
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir')->nullable();
            $table->date('tanggal_terakhir_generate')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_approve')->default(false);
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transaksis');
    }
};
