<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_transfer', 50)->unique();
            $table->foreignId('dari_lokasi_id')->constrained('cabangs');
            $table->foreignId('ke_lokasi_id')->constrained('cabangs');
            $table->date('tanggal_kirim');
            $table->date('tanggal_terima')->nullable();
            $table->enum('status', ['draft', 'dikirim', 'diterima_sebagian', 'diterima', 'dibatalkan'])->default('draft');
            $table->foreignId('stock_request_id')->nullable()->constrained('stock_requests')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty_kirim', 10, 3);
            $table->decimal('qty_terima', 10, 3)->nullable();
            $table->text('catatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
