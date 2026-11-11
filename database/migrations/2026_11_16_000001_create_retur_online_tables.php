<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 2d — retur pesanan online (rancangan §6.3):
 * pembeli mengajukan retur barang dari pesanan yang sudah diterima → penjual (gudang / upline) memutuskan:
 *   setujui = barang sudah kembali: stok pembeli −, stok penjual +, nilai retur memotong tagihan / dikembalikan, RO − (Tahap 2e);
 *   tolak = alasan wajib. Pembeli bisa membatalkan selama masih diajukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_online', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 30)->unique();                   // RTR-202611-0001
            $table->foreignId('pesanan_online_id')->constrained('pesanan_online')->restrictOnDelete();
            $table->unsignedBigInteger('member_id');                 // pembeli
            $table->enum('sumber', ['gudang', 'upline']);
            $table->foreignId('sumber_cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->unsignedBigInteger('sumber_member_id')->nullable();
            $table->string('status', 15);                            // diajukan / disetujui / ditolak / batal
            $table->string('alasan', 500);
            $table->string('foto', 255)->nullable();                 // storage/app/private/retur-online
            $table->decimal('nilai', 15, 2);
            $table->string('pengembalian', 20)->nullable();          // potong_tagihan / transfer / tunai
            $table->string('catatan_penjual', 255)->nullable();
            $table->timestamp('diputus_at')->nullable();
            $table->string('diputus_oleh_jenis', 10)->nullable();    // admin / member
            $table->unsignedBigInteger('diputus_oleh_id')->nullable();
            $table->string('diputus_oleh_nama', 150)->nullable();
            $table->timestamps();
            $table->index(['member_id', 'status']);
            $table->index(['sumber_member_id', 'status']);
        });

        Schema::create('retur_online_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retur_online_id')->constrained('retur_online')->cascadeOnDelete();
            $table->foreignId('pesanan_online_item_id')->constrained('pesanan_online_item')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('nama', 150);
            $table->unsignedInteger('qty');
            $table->decimal('harga', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::table('pesanan_online', function (Blueprint $table) {
            $table->decimal('nilai_retur', 15, 2)->default(0)->after('total');   // retur disetujui → memotong yang harus dibayar
        });

        Schema::table('toko_online_pengaturan', function (Blueprint $table) {
            $table->unsignedSmallInteger('hari_batas_retur')->default(7)->after('jam_batas_bayar'); // 0 = tanpa batas
        });
    }

    public function down(): void
    {
        Schema::table('toko_online_pengaturan', fn (Blueprint $t) => $t->dropColumn('hari_batas_retur'));
        Schema::table('pesanan_online', fn (Blueprint $t) => $t->dropColumn('nilai_retur'));
        Schema::dropIfExists('retur_online_item');
        Schema::dropIfExists('retur_online');
    }
};
