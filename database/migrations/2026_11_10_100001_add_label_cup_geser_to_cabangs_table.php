<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.49 Tahap D (porting erp-coffeeshop d285822) — geser posisi Label Produk
 * per cabang (mm). Printer label + gulungan stiker bisa menggeser isi beberapa
 * mm (coffeeshop: Blueprint ECO80BT ±3,5 mm ke kanan). Diatur sekali di
 * Cabang → Edit → Label Produk, berlaku untuk semua tablet/HP cabang itu;
 * pengaturan di perangkat (localStorage) ditambahkan di atas nilai ini.
 * Default 0 = posisi sama seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->decimal('label_cup_geser_x', 4, 1)->default(0)->after('label_cup_tinggi_mm');
            $table->decimal('label_cup_geser_y', 4, 1)->default(0)->after('label_cup_geser_x');
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn(['label_cup_geser_x', 'label_cup_geser_y']);
        });
    }
};
