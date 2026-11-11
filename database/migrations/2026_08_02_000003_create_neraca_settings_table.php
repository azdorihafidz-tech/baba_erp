<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton config untuk Neraca — "Modal Owner" tidak bisa di-derive
     * otomatis dari transaksi (modal awal usaha tercatat sebagai Transfer/
     * Perpindahan Dana antar kas, bukan kategori Modal tersendiri — lihat
     * Rule bisnis di CLAUDE.md). Owner set manual, mirip pola PengaturanGaji.
     */
    public function up(): void
    {
        Schema::create('neraca_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('modal_owner', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neraca_settings');
    }
};
