<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 2 - Master Data (2026-09-13) — Fitur Varian Produk. 1 baris =
     * 1 kombinasi nilai atribut (mis. Size M + Rasa Mentai), dihubungkan via
     * pivot `item_variant_values`.
     *
     * KEPUTUSAN PENTING (disetujui user sesi ini): kolom `stok` di sini
     * MURNI PLACEHOLDER integer — belum diintegrasikan ke sistem stok FIFO
     * existing (`stocks`/`stock_batches`/`stock_movements`). Keputusan
     * integrasi nyata (apakah butuh `item_variant_id` nullable di tabel
     * `stocks`, dst) ditunda ke Tahap 3/4 setelah alur POS jelas — supaya
     * tidak menyentuh 3 tabel inti yang sudah battle-tested sebelum tahu
     * kebutuhan pastinya.
     *
     * `resep_override` juga RESERVED (JSON nullable) — belum dibaca/ditulis
     * logic manapun, formatnya belum diputuskan (baru relevan saat Tahap 3/4
     * menentukan bagaimana varian bisa override komposisi resep produksi).
     */
    public function up(): void
    {
        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->decimal('harga_override', 15, 2)->nullable();
            $table->integer('stok')->default(0); // placeholder, lihat catatan di atas
            $table->json('resep_override')->nullable(); // reserved, belum dipakai
            $table->boolean('is_active')->default(true);
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
        Schema::dropIfExists('item_variants');
    }
};
