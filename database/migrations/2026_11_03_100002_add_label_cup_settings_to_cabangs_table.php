<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.44 Poin 2 — pengaturan cetak Label Produk (stiker) per cabang, porting
 * erp-coffeeshop "Label Cup" (nama kolom sengaja sama supaya perbaikan
 * berikutnya mudah di-porting). Di Dimsum bawaannya MATI (label_cup_aktif
 * = false) supaya cabang yang sudah berjalan tidak berubah apa pun sampai
 * Owner menyalakannya di Cabang → Edit. Kategori NULL = semua kategori
 * produk jual. Ukuran bawaan 40×30 mm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            if (! Schema::hasColumn('cabangs', 'label_cup_aktif')) {
                $table->boolean('label_cup_aktif')->default(false)->after('izinkan_sembunyi_harga_struk');
                $table->json('label_cup_kategori')->nullable()->after('label_cup_aktif');
                $table->unsignedSmallInteger('label_cup_lebar_mm')->default(40)->after('label_cup_kategori');
                $table->unsignedSmallInteger('label_cup_tinggi_mm')->default(30)->after('label_cup_lebar_mm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn(['label_cup_aktif', 'label_cup_kategori', 'label_cup_lebar_mm', 'label_cup_tinggi_mm']);
        });
    }
};
