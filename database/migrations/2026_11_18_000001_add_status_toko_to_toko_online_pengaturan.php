<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 2f — saklar toko online yang diatur Owner dari ERP:
 *   tutup (bawaan) = menu Belanja / Pesanan Saya / Toko Saya di aplikasi member tetap "Segera hadir";
 *   uji = hanya akun di akun_uji (uji coba sebelum dibuka); buka = semua member.
 * Aplikasi member tetap punya pengaman TOKO_ONLINE_AKTIF di .env-nya (keduanya harus mengizinkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toko_online_pengaturan', function (Blueprint $table) {
            $table->string('status_toko', 10)->default('tutup')->after('id');
            $table->json('akun_uji')->nullable()->after('status_toko');
        });
    }

    public function down(): void
    {
        Schema::table('toko_online_pengaturan', fn (Blueprint $t) => $t->dropColumn(['status_toko', 'akun_uji']));
    }
};
