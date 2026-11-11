<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bep_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->string('periode', 7); // YYYY-MM
            $table->decimal('total_biaya_tetap', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cabang_id', 'periode']);
        });

        Schema::create('bep_fixed_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bep_setting_id')->constrained('bep_settings')->cascadeOnDelete();
            $table->string('nama_komponen');
            $table->enum('kategori', ['gaji', 'sewa', 'depresiasi', 'listrik', 'asuransi', 'lainnya'])->default('lainnya');
            $table->decimal('jumlah', 15, 2);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('bep_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bep_setting_id')->constrained('bep_settings')->cascadeOnDelete();
            $table->string('nama_produk');
            $table->enum('tipe', ['produk', 'jasa_giling'])->default('produk');
            $table->decimal('harga_jual_per_unit', 15, 2);
            $table->decimal('biaya_variabel_per_unit', 15, 2);
            $table->decimal('margin_kontribusi', 15, 2)->default(0); // harga_jual - biaya_variabel
            $table->decimal('bep_unit', 15, 3)->nullable();
            $table->decimal('bep_rupiah', 15, 2)->nullable();
            $table->decimal('target_penjualan_unit', 15, 3)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('bep_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->string('periode', 7); // YYYY-MM
            $table->decimal('total_biaya_tetap', 15, 2)->default(0);
            $table->decimal('total_biaya_variabel', 15, 2)->default(0);
            $table->decimal('total_pendapatan', 15, 2)->default(0);
            $table->boolean('bep_tercapai')->default(false);
            $table->decimal('selisih_dari_bep', 15, 2)->default(0); // positif = melebihi BEP
            $table->decimal('persentase_bep', 8, 2)->default(0); // % pencapaian BEP
            $table->timestamps();

            $table->unique(['cabang_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bep_reports');
        Schema::dropIfExists('bep_products');
        Schema::dropIfExists('bep_fixed_cost_items');
        Schema::dropIfExists('bep_settings');
    }
};
