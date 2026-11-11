<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 2 - Master Data (2026-09-13) — Fitur Varian Produk. Nilai per
     * atribut, contoh: atribut "Size" -> values "S"/"M"/"L".
     */
    public function up(): void
    {
        Schema::create('item_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_attribute_id')->constrained('item_attributes')->cascadeOnDelete();
            $table->string('nilai'); // contoh: "S", "M", "L"
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index('item_attribute_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_attribute_values');
    }
};
