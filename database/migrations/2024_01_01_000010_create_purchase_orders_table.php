<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs'); // lokasi tujuan penerimaan
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('nomor_po', 50)->unique();
            $table->date('tanggal_po');
            $table->date('tanggal_terima')->nullable();
            $table->enum('status', ['draft', 'disetujui', 'dikirim_supplier', 'diterima', 'dibatalkan'])->default('draft');
            $table->boolean('pembelian_langsung')->default(false); // true = cabang beli langsung ke vendor (mendesak)
            $table->decimal('total_harga', 15, 2)->default(0);
            $table->text('alasan_langsung')->nullable(); // alasan pembelian langsung
            $table->text('catatan')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty_pesan', 10, 3);
            $table->decimal('qty_terima', 10, 3)->nullable();
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('total_harga', 15, 2);
            $table->text('catatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
