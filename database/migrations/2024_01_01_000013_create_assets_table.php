<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('kode_kategori', 20)->unique();
            $table->string('nama_kategori');
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('kode_aset', 30)->unique();
            $table->string('nama_aset');
            $table->foreignId('kategori_aset_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->foreignId('lokasi_id')->constrained('cabangs');
            $table->date('tanggal_perolehan');
            $table->decimal('harga_perolehan', 15, 2);
            $table->decimal('nilai_residu', 15, 2)->default(0);
            $table->integer('umur_ekonomis_bulan'); // dalam bulan
            $table->enum('metode_penyusutan', ['garis_lurus', 'saldo_menurun', 'satuan_produksi'])->default('garis_lurus');
            $table->decimal('tarif_penyusutan', 8, 4)->nullable(); // untuk metode saldo menurun (%)
            $table->decimal('estimasi_produksi_total', 15, 3)->nullable(); // untuk metode satuan produksi
            $table->enum('kondisi', ['baik', 'rusak_ringan', 'rusak_berat', 'dihapuskan'])->default('baik');
            $table->enum('status', ['aktif', 'tidak_aktif', 'dijual', 'dihapuskan'])->default('aktif');
            $table->decimal('nilai_buku', 15, 2)->nullable(); // nilai buku terkini
            $table->string('foto')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('periode', 7); // YYYY-MM
            $table->decimal('nilai_buku_awal', 15, 2);
            $table->decimal('jumlah_penyusutan', 15, 2);
            $table->decimal('akumulasi_penyusutan', 15, 2);
            $table->decimal('nilai_buku_akhir', 15, 2);
            $table->decimal('produksi_aktual', 15, 3)->nullable(); // untuk satuan produksi
            $table->timestamps();

            $table->unique(['asset_id', 'periode']);
        });

        Schema::create('asset_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('dari_lokasi_id')->constrained('cabangs');
            $table->foreignId('ke_lokasi_id')->constrained('cabangs');
            $table->date('tanggal_mutasi');
            $table->text('alasan');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->date('tanggal_maintenance');
            $table->enum('jenis', ['perawatan_rutin', 'perbaikan', 'overhaul'])->default('perawatan_rutin');
            $table->text('deskripsi');
            $table->decimal('biaya', 15, 2)->default(0);
            $table->string('vendor_maintenance')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->date('tanggal_disposal');
            $table->enum('tipe', ['dijual', 'dibuang', 'dihibahkan'])->default('dijual');
            $table->decimal('nilai_jual', 15, 2)->default(0);
            $table->decimal('nilai_buku_saat_disposal', 15, 2);
            $table->decimal('keuntungan_kerugian', 15, 2)->default(0); // positif = untung, negatif = rugi
            $table->string('pembeli')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('asset_mutations');
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
