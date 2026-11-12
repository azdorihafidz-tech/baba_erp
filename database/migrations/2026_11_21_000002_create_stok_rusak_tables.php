<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 7c — stok rusak ditahan (rancangan stok opname §6). Barang yang disetujui "rusak" di stok opname keluar dari
 * stok jual (kerugian sudah dicatat saat opname disetujui) dan ditahan di sini sampai ditindaklanjuti:
 * kembali ke pemasok (diganti barang / uang kembali / potong tagihan), dimusnahkan, atau dipakai lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_rusak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('stok_opname_id')->nullable()->constrained('stok_opname')->nullOnDelete();
            $table->foreignId('stok_opname_rincian_id')->nullable()->unique()->constrained('stok_opname_rincian')->nullOnDelete();
            $table->string('satuan', 20)->nullable();
            $table->decimal('qty', 15, 3);                            // jumlah awal ditahan
            $table->decimal('qty_sisa', 15, 3);                       // masih ditahan
            $table->decimal('harga_pokok', 15, 4);                    // per satuan (nilai kerugian / qty)
            $table->string('status', 10)->default('ditahan');         // ditahan / selesai
            $table->string('catatan', 255)->nullable();
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('stok_rusak_tindakan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_rusak_id')->constrained('stok_rusak')->cascadeOnDelete();
            $table->string('jenis', 20);                              // pakai_lagi / musnahkan / pemasok_ganti / pemasok_uang / pemasok_potong
            $table->decimal('qty', 15, 3);
            $table->decimal('nilai_pokok', 15, 2);                    // qty × harga pokok
            $table->decimal('nilai_pulih', 15, 2)->default(0);        // kerugian yang dipulihkan (dicatat di keuangan)
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('kas_id')->nullable()->constrained('kas')->nullOnDelete();
            $table->foreignId('transaksi_keuangan_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->string('foto', 255)->nullable();                  // storage/app/private/stok-rusak
            $table->string('catatan', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_rusak_tindakan');
        Schema::dropIfExists('stok_rusak');
    }
};
