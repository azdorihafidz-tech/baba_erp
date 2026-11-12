<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 7f — stok opname member (Toko Saya › Stok Saya; rancangan stok opname §11): tanggung jawab member sendiri,
 * tanpa persetujuan, HANYA mengurangi stok (fisik < stok), per barang dengan penyebab; tidak mengubah RO; pembukuan member
 * (bukan BABA). Stok berubah lewat StokMemberService (mutasi jenis "opname").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opname_member', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 30)->unique();                    // OPM-202611-0001
            $table->unsignedBigInteger('member_id');                  // tbl_user.id aplikasi member
            $table->decimal('total_nilai', 15, 2)->default(0);        // nilai modal yang berkurang
            $table->string('catatan', 255)->nullable();
            $table->string('foto', 255)->nullable();                  // storage/app/private/opname-member
            $table->timestamps();
            $table->index(['member_id', 'created_at']);
        });

        Schema::create('opname_member_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opname_member_id')->constrained('opname_member')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('nama', 150);
            $table->string('satuan', 20)->nullable();
            $table->integer('stok_sebelum');
            $table->integer('fisik');
            $table->integer('qty');                                   // berkurang (stok_sebelum − fisik)
            $table->string('penyebab', 15);
            $table->decimal('nilai', 15, 2);                          // qty × modal rata-rata
            $table->string('catatan', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opname_member_item');
        Schema::dropIfExists('opname_member');
    }
};
