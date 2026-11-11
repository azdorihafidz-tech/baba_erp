<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 3 - POS D'mentai (2026-09-13). Simpan varian mana yang terjual
     * (mis. "Dimsum Mentai - Size M") — nullable karena mayoritas item tidak
     * punya varian sama sekali.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('item_variant_id')->nullable()
                ->after('item_id')->constrained('item_variants')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['item_variant_id']);
            $table->dropColumn('item_variant_id');
        });
    }
};
