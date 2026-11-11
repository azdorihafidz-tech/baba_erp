<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan Harga Tahap A (2026-10-18) — semua ADDITIVE, nullable:
 * - items.harga_po_terakhir(+_at): harga beli dari PO terakhir, terisi
 *   otomatis saat PO diterima, INFO saja. Kolom lama `harga_beli_terakhir`
 *   sekarang bermakna "Harga Standar" (manual, tidak lagi ditimpa PO).
 * - item_cabang.harga_beli: harga beli bahan/kemasan per outlet (harga awal
 *   PO cabang itu). Beda dari harga_override (harga JUAL produk per outlet).
 * - stock_transfer_items.harga_per_unit: modal FIFO rata-rata yang benar-
 *   benar keluar dari lokasi asal saat dikirim, dipakai sbg harga batch di
 *   lokasi tujuan (dulu memakai harga master global).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('harga_po_terakhir', 15, 2)->nullable()->after('harga_beli_terakhir');
            $table->dateTime('harga_po_terakhir_at')->nullable()->after('harga_po_terakhir');
        });

        Schema::table('item_cabang', function (Blueprint $table) {
            $table->decimal('harga_beli', 15, 2)->nullable()->after('harga_override');
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->decimal('harga_per_unit', 15, 4)->nullable()->after('qty_terima');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', fn (Blueprint $table) => $table->dropColumn('harga_per_unit'));
        Schema::table('item_cabang', fn (Blueprint $table) => $table->dropColumn('harga_beli'));
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn(['harga_po_terakhir', 'harga_po_terakhir_at']));
    }
};
