<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 5 D'mentai — Setoran Kasir (rekonsiliasi kas harian cabang -> HO).
 * 1 baris = 1 cabang + 1 tanggal (bukan per-shift, lihat CLAUDE.md 7.3 —
 * tidak ada infrastruktur shift kasir di codebase ini).
 *
 * Uang belum berpindah sama sekali sampai status='approved' — `transaksi_out_id`/
 * `transaksi_in_id` diisi SAAT approve (bukan saat submit), beda dari pola
 * Transfer Dana existing (SetoranController) yang langsung potong kas asal di
 * submit. Alasan: Setoran Kasir murni "laporan kas di tangan", uang fisik
 * belum pernah keluar cabang sampai HO benar-benar konfirmasi terima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setorans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs')->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('disubmit_oleh')->constrained('users')->cascadeOnDelete();
            $table->timestamp('disubmit_pada')->nullable();
            $table->decimal('total_penjualan_sistem', 15, 2)->default(0);
            $table->decimal('total_disetor', 15, 2)->default(0);
            $table->decimal('selisih', 15, 2)->default(0);
            $table->string('status', 20)->default('menunggu');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->string('ditolak_alasan')->nullable();
            $table->string('bukti_foto')->nullable();
            $table->text('catatan_kasir')->nullable();
            $table->text('catatan_ho')->nullable();
            $table->foreignId('transaksi_out_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->foreignId('transaksi_in_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Catatan: unique ini TIDAK soft-delete-aware (kalau baris di-hapus
            // permanen oleh Owner lalu kasir submit ulang utk tanggal sama di
            // hari yang sama, akan ke-block oleh baris trashed) — edge case
            // langka (submit ulang di HARI YANG SAMA setelah dihapus permanen),
            // diterima sebagai keterbatasan; pola fix soft-delete-safe generated
            // column (lihat migration `fix_kas_soft_delete_unique_constraint`)
            // bisa diterapkan nanti kalau ini benar-benar jadi masalah nyata.
            $table->unique(['cabang_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setorans');
    }
};
