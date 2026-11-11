<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 2b — pesanan online member (rancangan §6.1–6.2):
 * - toko_online_pengaturan: satu baris (terima otomatis, batas waktu bayar).
 * - toko_online_rekening: rekening tujuan transfer per gudang.
 * - toko_online_aturan_tagihan: siapa boleh bayar dengan tagihan (semua / jabatan / akun) + batas nominal + tempo.
 * - pesanan_online (+ _item, _bayar, _log).
 *   status   = alur barang: menunggu_ongkir → menunggu_bayar → menunggu_cek → disiapkan → dikirim / siap_diambil → diterima; batal.
 *   status_bayar = belum / menunggu_cek / lunas (tagihan bisa lunas setelah barang diterima).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toko_online_pengaturan', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('hari_terima_otomatis')->default(7);   // dikirim/siap diambil → diterima otomatis
            $table->unsignedSmallInteger('jam_batas_bayar')->default(48);       // menunggu bayar lebih lama → batal otomatis (0 = tidak)
            $table->timestamps();
        });

        Schema::create('toko_online_rekening', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs')->cascadeOnDelete();
            $table->string('bank', 50);
            $table->string('nomor', 40);
            $table->string('atas_nama', 100);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('toko_online_aturan_tagihan', function (Blueprint $table) {
            $table->id();
            $table->enum('cakupan', ['semua', 'jabatan', 'akun']);
            $table->unsignedInteger('jabatan_id')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->decimal('batas_nominal', 15, 2);                // total tagihan belum lunas maksimal
            $table->unsignedSmallInteger('tempo_hari')->default(14);
            $table->boolean('aktif')->default(true);
            $table->string('catatan', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pesanan_online', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 30)->unique();                  // ONL-202611-0001
            $table->unsignedBigInteger('member_id');                // pembeli (tbl_user.id aplikasi member)
            $table->enum('sumber', ['gudang', 'upline'])->default('gudang');
            $table->foreignId('sumber_cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->unsignedBigInteger('sumber_member_id')->nullable(); // upline penjual (Tahap 2c)
            $table->enum('cara_ambil', ['ambil', 'kirim']);
            $table->string('kirim_nama', 100)->nullable();
            $table->string('kirim_hp', 30)->nullable();
            $table->text('kirim_alamat')->nullable();
            $table->string('catatan', 500)->nullable();
            $table->enum('cara_bayar', ['transfer', 'tagihan']);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('ongkir', 15, 2)->nullable();           // null = belum diisi admin (dikirim)
            $table->decimal('total', 15, 2);
            $table->string('status', 20);
            $table->string('status_bayar', 20)->default('belum');
            $table->timestamp('batas_bayar_at')->nullable();        // transfer: lewat ini masih belum bayar → batal otomatis
            $table->date('jatuh_tempo')->nullable();                // tagihan
            $table->timestamp('lunas_at')->nullable();
            $table->string('kurir', 50)->nullable();
            $table->string('nomor_resi', 60)->nullable();
            $table->boolean('stok_keluar')->default(false);
            $table->timestamp('dikirim_at')->nullable();             // dikirim / siap diambil (stok keluar)
            $table->timestamp('diterima_at')->nullable();
            $table->boolean('diterima_otomatis')->default(false);
            $table->timestamp('batal_at')->nullable();
            $table->string('batal_alasan', 255)->nullable();
            $table->timestamps();
            $table->index(['member_id', 'status']);
            $table->index(['status', 'sumber_cabang_id']);
        });

        Schema::create('pesanan_online_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_online_id')->constrained('pesanan_online')->cascadeOnDelete();
            $table->foreignId('produk_online_id')->nullable()->constrained('produk_online')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('nama', 150);
            $table->string('satuan', 20);
            $table->unsignedInteger('qty');
            $table->decimal('harga', 15, 2);
            $table->string('dasar_harga', 10);                       // akun / jabatan / gudang / tetap
            $table->decimal('subtotal', 15, 2);
            $table->decimal('hpp', 15, 2)->nullable();               // diisi saat stok keluar (FIFO)
            $table->unsignedInteger('qty_retur')->default(0);        // Tahap 2d
            $table->timestamps();
        });

        Schema::create('pesanan_online_bayar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_online_id')->constrained('pesanan_online')->cascadeOnDelete();
            $table->string('file', 255);
            $table->char('hash', 64)->unique();                      // bukti yang sama tidak bisa dipakai dua kali
            $table->decimal('nominal', 15, 2);
            $table->string('status', 15)->default('menunggu');       // menunggu / diterima / ditolak
            $table->string('alasan', 255)->nullable();
            $table->foreignId('dicek_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dicek_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pesanan_online_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_online_id')->constrained('pesanan_online')->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('catatan', 500)->nullable();
            $table->string('oleh_jenis', 10);                         // member / admin / sistem
            $table->unsignedBigInteger('oleh_id')->nullable();        // member_id atau users.id
            $table->string('oleh_nama', 150)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan_online_log');
        Schema::dropIfExists('pesanan_online_bayar');
        Schema::dropIfExists('pesanan_online_item');
        Schema::dropIfExists('pesanan_online');
        Schema::dropIfExists('toko_online_aturan_tagihan');
        Schema::dropIfExists('toko_online_rekening');
        Schema::dropIfExists('toko_online_pengaturan');
    }
};
