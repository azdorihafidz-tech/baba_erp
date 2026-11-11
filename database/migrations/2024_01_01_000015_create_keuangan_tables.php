<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->string('nama_kas'); // Kas Tunai, Kas Bank BRI, dll
            $table->enum('tipe_kas', ['tunai', 'bank'])->default('tunai');
            $table->string('nomor_rekening', 30)->nullable();
            $table->string('nama_bank', 50)->nullable();
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->decimal('saldo_sekarang', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('transaksi_keuangans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->foreignId('kas_id')->nullable()->constrained('kas')->nullOnDelete();
            $table->string('nomor_transaksi', 50)->unique();
            $table->date('tanggal_transaksi');
            $table->enum('tipe', ['pemasukan', 'pengeluaran']);
            $table->enum('kategori', [
                'penjualan', 'jasa_giling', 'pembelian_bahan', 'gaji',
                'sewa_gedung', 'penyusutan', 'operasional', 'pembelian_aset', 'lainnya'
            ]);
            $table->string('keterangan');
            $table->decimal('jumlah', 15, 2);
            $table->string('referensi_type')->nullable(); // order, purchase_order, penggajian, dll
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_keuangans');
        Schema::dropIfExists('kas');
    }
};
