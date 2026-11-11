<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP BABA Tahap 2e — RO otomatis dari belanja online (rancangan §6.4):
 * - pengaturan_umums.mode_ro: lunas | lunas_disetujui | disetujui (diatur Owner di Pengaturan Umum).
 * - ro_catatan: antrian catatan RO (+ botol saat pesanan dihitung, − saat retur disetujui / pesanan batal setelah RO tercatat)
 *   yang dikirim ke aplikasi member sebagai dokumen RO (tbl_mvo_document, erp_ref unik). Gagal kirim → dicoba ulang terjadwal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->string('mode_ro', 20)->default('lunas')->after('tanggal_mulai_periode');
        });

        Schema::create('ro_catatan', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 60)->unique();                     // ERP-RO-ONL-202611-0001 / ERP-RO-RTR-… / ERP-RO-BATAL-…
            $table->unsignedBigInteger('member_id');
            $table->integer('qty');                                   // botol; negatif = pengurang
            $table->string('jenis', 10);                              // pesanan / retur / batal
            $table->foreignId('pesanan_online_id')->nullable()->constrained('pesanan_online')->nullOnDelete();
            $table->foreignId('retur_online_id')->nullable()->constrained('retur_online')->nullOnDelete();
            $table->date('tanggal');
            $table->string('mode', 20);
            $table->boolean('perlu_approve')->default(false);
            $table->string('status', 10)->default('menunggu');        // menunggu / terkirim / gagal
            $table->unsignedSmallInteger('percobaan')->default(0);
            $table->string('pesan_galat', 255)->nullable();
            $table->unsignedBigInteger('member_doc_id')->nullable();  // tbl_mvo_document.id di aplikasi member
            $table->timestamp('terkirim_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'percobaan']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ro_catatan');
        Schema::table('pengaturan_umums', fn (Blueprint $t) => $t->dropColumn('mode_ro'));
    }
};
