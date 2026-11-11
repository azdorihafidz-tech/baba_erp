<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint Unit Family (2026-09-27): dukung input PO/Adjustment dlm unit beli
 * (pack/karung/dus) yang auto-convert ke unit pakai (pcs/kg). Non-breaking:
 * semua kolom nullable, data existing (unit_beli NULL) otomatis tetap jalan
 * sbg mode "unit pakai saja". `items.satuan` maknanya tetap "unit pakai" —
 * stock_batches/stocks/stock_movements/order_items semua tetap dlm unit itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->string('unit_beli', 20)->nullable()->after('satuan');
            $t->decimal('isi_per_unit_beli', 10, 3)->nullable()->after('unit_beli');
        });

        Schema::table('purchase_order_items', function (Blueprint $t) {
            $t->string('unit_input', 20)->nullable()->after('qty_terima');
            $t->decimal('qty_input', 10, 3)->nullable()->after('unit_input');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $t) {
            $t->dropColumn(['unit_input', 'qty_input']);
        });

        Schema::table('items', function (Blueprint $t) {
            $t->dropColumn(['unit_beli', 'isi_per_unit_beli']);
        });
    }
};
