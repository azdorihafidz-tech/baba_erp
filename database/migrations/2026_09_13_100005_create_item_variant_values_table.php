<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 2 - Master Data (2026-09-13) — Fitur Varian Produk. Pivot murni
     * (tanpa SoftDeletes, sama pola seperti `cabang_user`) yang menyatukan
     * kombinasi N-dimensi: 1 ItemVariant terhubung ke N ItemAttributeValue
     * (1 value per atribut, mis. Size=M + Rasa=Mentai = 2 baris pivot).
     */
    public function up(): void
    {
        Schema::create('item_variant_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_variant_id')->constrained('item_variants')->cascadeOnDelete();
            $table->foreignId('item_attribute_value_id')->constrained('item_attribute_values')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['item_variant_id', 'item_attribute_value_id'], 'item_variant_values_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_variant_values');
    }
};
