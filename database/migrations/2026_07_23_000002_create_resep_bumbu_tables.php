<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('resep_bumbu')) {
            Schema::create('resep_bumbu', function (Blueprint $table) {
                $table->id();
                $table->string('nama', 100);
                $table->string('kode', 20)->unique();
                // Jenis olahan induk (mis. "Bakso Kojek" -> jenis olahan "Bakso") —
                // dipakai untuk auto-isi dropdown Jenis Olahan existing di POS saat
                // resep diterapkan. Nullable karena tidak wajib dikaitkan.
                $table->foreignId('jenis_olahan_id')->nullable()->constrained('jenis_olahans')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->text('catatan')->nullable();
                $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('resep_bumbu_items')) {
            Schema::create('resep_bumbu_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resep_bumbu_id')->constrained('resep_bumbu')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('items');
                $table->decimal('qty_per_kg', 10, 3); // takaran per 1 kg gilingan, dalam satuan kolom `satuan`
                $table->string('satuan', 20); // g, kg, ml, dll — utk interpretasi qty_per_kg (bukan satuan stok item)
                $table->boolean('is_wajib')->default(true);
                // gratis: harga_satuan dikirim 0 ke POS (bumbu include, default).
                // pakai_master: harga_satuan dikirim = items.harga_jual (dicharge terpisah).
                $table->enum('mode_harga', ['gratis', 'pakai_master'])->default('gratis');
                $table->unsignedInteger('urutan')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resep_bumbu_items');
        Schema::dropIfExists('resep_bumbu');
    }
};
