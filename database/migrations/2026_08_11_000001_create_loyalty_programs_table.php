<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->decimal('target_qty_kg', 10, 2);
            $table->string('satuan_qty', 20)->default('kg');
            // Sumber kolom yang dihitung LoyaltyService — satu-satunya sumber
            // valid saat ini adalah 'orders.berat_daging_kg' (lihat catatan
            // audit: order_items.qty TIDAK bisa dipakai, mencampur bumbu+
            // kemasan+daging jadi satu angka yang salah). Kolom ini disiapkan
            // sebagai enum eksplisit (bukan hardcode di service) supaya
            // program masa depan dengan basis data lain tinggal nambah opsi.
            $table->enum('sumber_data', ['orders.berat_daging_kg'])->default('orders.berat_daging_kg');
            // Tipe order yang dihitung — string sederhana dulu (bukan JSON),
            // sesuai prinsip YAGNI: belum ada kebutuhan multi-tipe konkret.
            $table->string('tipe_item', 30)->default('jasa_giling');
            $table->date('periode_mulai')->nullable();
            $table->date('periode_akhir')->nullable();
            $table->text('hadiah');
            $table->boolean('berulang')->default(false);
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
