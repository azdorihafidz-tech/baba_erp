<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 2 - Master Data (2026-09-13) — Fitur Varian Produk (CLAUDE.md
     * 7.2), STRUKTUR DB + MODEL SAJA, UI dikerjakan bareng POS di Tahap 3.
     *
     * Atribut di-scope PER ITEM (bukan taxonomy global lintas-produk) —
     * konsisten dengan diagram CLAUDE.md 7.2 yang menggantungkan
     * ItemAttribute langsung di bawah node Item. Contoh: item "Dimsum
     * Mentai" punya ItemAttribute "Size" dengan values S/M/L.
     */
    public function up(): void
    {
        Schema::create('item_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('nama'); // contoh: "Size", "Rasa", "Level Pedas"
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_attributes');
    }
};
