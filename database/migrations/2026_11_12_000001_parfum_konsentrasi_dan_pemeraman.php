<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 1a — produksi parfum:
 * - parfum_konsentrasi: resep persen per konsentrasi (EDP, EDT, …) + lama pemeraman.
 * - resep_produksi.lama_peram_hari: > 0 = hasil produksi diperam dulu sebelum masuk stok.
 * - produksi_batches: status 'diperam', tanggal_siap, selesai peram (hasil akhir & susut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parfum_konsentrasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 30)->unique();          // EDP, EDT, …
            $table->string('keterangan', 150)->nullable();  // mis. Eau de Parfum
            $table->decimal('persen_bibit', 5, 2);          // % bibit dari volume cairan
            $table->decimal('persen_fixative', 5, 2)->default(0);
            $table->unsignedSmallInteger('lama_peram_hari')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::table('resep_produksi', function (Blueprint $table) {
            $table->unsignedSmallInteger('lama_peram_hari')->default(0)->after('qty_hasil');
        });

        Schema::table('produksi_batches', function (Blueprint $table) {
            $table->date('tanggal_siap')->nullable()->after('tanggal');
            $table->foreignId('diselesaikan_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('diselesaikan_at')->nullable()->after('diselesaikan_by');
        });
        DB::statement("ALTER TABLE produksi_batches MODIFY status ENUM('diperam','selesai','dibatalkan') NOT NULL DEFAULT 'selesai'");
    }

    public function down(): void
    {
        // Batch yang masih diperam tidak punya padanan lama → dianggap dibatalkan.
        DB::table('produksi_batches')->where('status', 'diperam')->update(['status' => 'dibatalkan']);
        DB::statement("ALTER TABLE produksi_batches MODIFY status ENUM('selesai','dibatalkan') NOT NULL DEFAULT 'selesai'");
        Schema::table('produksi_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diselesaikan_by');
            $table->dropColumn(['tanggal_siap', 'diselesaikan_at']);
        });
        Schema::table('resep_produksi', function (Blueprint $table) {
            $table->dropColumn('lama_peram_hari');
        });
        Schema::dropIfExists('parfum_konsentrasi');
    }
};
