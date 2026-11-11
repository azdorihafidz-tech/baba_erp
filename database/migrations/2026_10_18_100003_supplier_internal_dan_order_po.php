<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap C (2026-10-18, CLAUDE.md 4.38) — PO cabang ke Gudang Pusat tampil
 * di POS gudang:
 * - suppliers.cabang_id: supplier "internal" = salah satu lokasi kita
 *   (mis. Gudang Pusat). PO ke supplier ini muncul di POS lokasi tsb.
 * - orders.purchase_order_id: nota POS gudang yang memproses PO itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('cabang_id')->nullable()->after('kota')->constrained('cabangs')->nullOnDelete();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('parent_order_id')->constrained('purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cabang_id');
        });
    }
};
