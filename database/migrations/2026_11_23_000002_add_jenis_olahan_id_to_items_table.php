<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.50 — Jenis Menu (jenis_olahans) bisa dipilih di Produk Jual. Saat dijual, namanya
 * disalin ke order_items.jenis_olahan supaya Laporan Laba Rugi "Per Jenis Menu" terisi
 * (dulu selalu "-" karena order POS tidak pernah menyimpannya). Kosong = data lama, aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('jenis_olahan_id')->nullable()->after('item_category_id')
                ->constrained('jenis_olahans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['jenis_olahan_id']);
            $table->dropColumn('jenis_olahan_id');
        });
    }
};
