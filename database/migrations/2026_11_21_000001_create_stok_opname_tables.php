<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 7a — Stok Opname (rancangan ../_rancangan/RANCANGAN-STOK-OPNAME.md):
 * - stok_opname: satu sesi hitung per lokasi (hitung → diajukan → selesai | batal; ditolak = kembali ke hitung).
 * - stok_opname_item: barang dalam cakupan; qty_sistem dicatat SAAT barang dihitung (toko tetap boleh jualan),
 *   saat disetujui hanya selisih (fisik − sistem) yang diterapkan.
 * - stok_opname_rincian: pecahan selisih per penyebab (rusak/hilang/susut/tester/kedaluwarsa/salah_catat/lebih) + foto.
 * - stok_opname_log: riwayat aksi.
 * - pengaturan_umums: hitung buta bawaan & batas nilai barang hilang yang wajib foto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_opname', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 30)->unique();                    // SO-202611-0001
            $table->foreignId('cabang_id')->constrained('cabangs')->restrictOnDelete();
            $table->string('cakupan', 10);                            // semua / kategori / barang
            $table->string('cakupan_ket', 500)->nullable();           // nama kategori / jumlah barang pilihan
            $table->boolean('hitung_buta')->default(true);
            $table->string('status', 12);                             // hitung / diajukan / selesai / batal
            $table->string('catatan', 500)->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diajukan_at')->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable();
            $table->string('alasan_tolak', 500)->nullable();          // penolakan terakhir (opname kembali ke hitung)
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alasan_batal', 500)->nullable();
            $table->json('ringkasan')->nullable();                    // diisi saat disetujui (angka final berita acara)
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('stok_opname_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_id')->constrained('stok_opname')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('satuan', 20)->nullable();
            $table->decimal('qty_sistem', 15, 3)->nullable();         // stok sistem saat barang dihitung
            $table->decimal('qty_fisik', 15, 3)->nullable();          // null = belum dihitung
            $table->decimal('qty_fisik_awal', 15, 3)->nullable();     // hitungan pertama (jejak hitung ulang)
            $table->unsignedTinyInteger('hitung_ke')->default(0);
            $table->boolean('minta_hitung_ulang')->default(false);
            $table->foreignId('dihitung_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dihitung_at')->nullable();
            $table->decimal('harga_pokok', 15, 4)->nullable();        // per satuan; final saat disetujui
            $table->decimal('nilai_selisih', 15, 2)->nullable();      // final saat disetujui (− kurang, + lebih)
            $table->string('catatan', 255)->nullable();
            $table->timestamps();
            $table->unique(['stok_opname_id', 'item_id']);
        });

        Schema::create('stok_opname_rincian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_item_id')->constrained('stok_opname_item')->cascadeOnDelete();
            $table->string('penyebab', 15);
            $table->decimal('qty', 15, 3);
            $table->decimal('nilai', 15, 2)->nullable();              // nilai pokok final saat disetujui
            $table->string('foto', 255)->nullable();                  // storage/app/private/stok-opname
            $table->string('catatan', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('stok_opname_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_id')->constrained('stok_opname')->cascadeOnDelete();
            $table->string('aksi', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catatan', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->boolean('opname_hitung_buta')->default(true)->after('mode_ro');
            $table->decimal('opname_batas_foto_hilang', 15, 2)->default(200000)->after('opname_hitung_buta');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->dropColumn(['opname_hitung_buta', 'opname_batas_foto_hilang']);
        });
        Schema::dropIfExists('stok_opname_log');
        Schema::dropIfExists('stok_opname_rincian');
        Schema::dropIfExists('stok_opname_item');
        Schema::dropIfExists('stok_opname');
    }
};
