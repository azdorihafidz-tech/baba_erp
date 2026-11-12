<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 7d — ganti rugi karyawan untuk barang HILANG di stok opname (rancangan stok opname §7):
 * nilai bawaan = harga pokok (boleh diubah dengan alasan), dibayar tunai (kas masuk) atau potong gaji (boleh dicicil;
 * cicilan otomatis masuk slip gaji bulan berikutnya, dibukukan saat slip ditandai dibayar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ganti_rugi_stok', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 30)->unique();                    // GR-202611-0001
            $table->foreignId('stok_opname_id')->nullable()->constrained('stok_opname')->nullOnDelete();
            $table->foreignId('stok_opname_rincian_id')->nullable()->constrained('stok_opname_rincian')->nullOnDelete();
            $table->foreignId('cabang_id')->constrained('cabangs')->restrictOnDelete();
            $table->foreignId('karyawan_id')->constrained('karyawans')->restrictOnDelete();
            $table->decimal('nilai', 15, 2);
            $table->decimal('nilai_pokok', 15, 2);                    // nilai kerugian (harga pokok) bagian ini
            $table->string('alasan', 500)->nullable();                // wajib bila nilai ≠ nilai pokok
            $table->string('cara', 12);                               // tunai / potong_gaji
            $table->unsignedTinyInteger('cicilan_bulan')->default(1);
            $table->char('mulai_periode', 7)->nullable();             // YYYY-MM (potong gaji)
            $table->decimal('terbayar', 15, 2)->default(0);
            $table->string('status', 10)->default('berjalan');        // berjalan / lunas / batal
            $table->string('catatan', 255)->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alasan_batal', 500)->nullable();
            $table->timestamps();
            $table->index(['karyawan_id', 'status']);
        });

        Schema::create('ganti_rugi_stok_bayar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ganti_rugi_stok_id')->constrained('ganti_rugi_stok')->cascadeOnDelete();
            $table->string('jenis', 12);                              // tunai / potong_gaji
            $table->decimal('jumlah', 15, 2);
            $table->char('periode', 7)->nullable();                   // potong gaji: periode slip
            $table->foreignId('kas_id')->nullable()->constrained('kas')->nullOnDelete();
            $table->foreignId('penggajian_id')->nullable()->constrained('penggajians')->cascadeOnDelete();
            $table->string('status', 10);                             // menunggu (slip belum dibayar) / dibayar
            $table->foreignId('transaksi_keuangan_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('penggajians', function (Blueprint $table) {
            $table->decimal('potongan_ganti_rugi', 15, 2)->default(0)->after('potongan_lain');
        });
    }

    public function down(): void
    {
        Schema::table('penggajians', function (Blueprint $table) {
            $table->dropColumn('potongan_ganti_rugi');
        });
        Schema::dropIfExists('ganti_rugi_stok_bayar');
        Schema::dropIfExists('ganti_rugi_stok');
    }
};
