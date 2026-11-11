<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Nullable — data lama NULL (aman). Diisi PurchaseOrderService::kirimSupplier()
            // saat status berubah ke dikirim_supplier, dipakai untuk hitung umur PO
            // di Dashboard PO / widget dashboard.
            $table->datetime('tanggal_kirim')->nullable()->after('tanggal_terima');

            // Index untuk performa query dashboard PO (filter per cabang + status).
            $table->index('status');
            $table->index(['cabang_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['cabang_id', 'status']);
            $table->dropIndex(['status']);
            $table->dropColumn('tanggal_kirim');
        });
    }
};
