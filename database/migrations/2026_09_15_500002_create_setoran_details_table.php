<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 5 D'mentai — breakdown per metode bayar (tunai/transfer/qris/gojek/
 * grab), diisi otomatis dari SUM(order_payments) per metode. `jumlah_fisik`
 * cuma relevan/diedit utk metode 'tunai' (uang fisik bisa beda dari sistem
 * karena selisih kembalian dll) — metode non-tunai jumlah_fisik = jumlah_sistem
 * (uang non-tunai tidak bisa "kurang secara fisik").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setoran_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setoran_id')->constrained('setorans')->cascadeOnDelete();
            $table->string('metode', 20);
            $table->decimal('jumlah_sistem', 15, 2)->default(0);
            $table->decimal('jumlah_fisik', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setoran_details');
    }
};
