<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closing / Tutup Buku Tahap 2 — snapshot closing + kunci periode.
 *
 * 1 baris = 1 versi closing. cabang_id NULL = closing keseluruhan
 * (konsolidasi, tidak mengunci apa pun — kunci datang dari closing per
 * cabang). Status 'final' = mengunci periode, 'dibuka' = sudah dibuka
 * Owner (riwayat tetap disimpan, closing ulang membuat versi baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closings', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['harian', 'bulanan']);
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs');
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->string('label_periode', 100);
            $table->unsignedInteger('versi')->default(1);
            $table->enum('status', ['final', 'dibuka'])->default('final');

            // Ringkasan (untuk daftar riwayat tanpa decode JSON)
            $table->decimal('omzet', 15, 2)->default(0);
            $table->decimal('hpp', 15, 2)->default(0);
            $table->decimal('laba_bersih', 15, 2)->default(0);
            $table->decimal('saldo_kas_akhir', 15, 2)->default(0);
            $table->decimal('nilai_stok', 15, 2)->default(0);
            $table->decimal('total_selisih_fisik', 15, 2)->nullable();

            $table->longText('data'); // JSON hasil ClosingService::hitung() saat closing
            $table->text('catatan')->nullable();

            $table->foreignId('ditutup_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ditutup_pada');
            $table->foreignId('dibuka_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dibuka_pada')->nullable();
            $table->text('alasan_buka')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'status', 'periode_mulai', 'periode_selesai'], 'closings_kunci_idx');
            $table->index(['jenis', 'periode_mulai']);
        });

        Schema::create('closing_kas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('closing_id')->constrained('closings')->cascadeOnDelete();
            $table->foreignId('kas_id')->nullable()->constrained('kas')->nullOnDelete();
            $table->string('nama_kas');
            $table->string('nama_cabang')->nullable();
            $table->enum('grup', ['tunai', 'bank']);
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->decimal('masuk', 15, 2)->default(0);
            $table->decimal('keluar', 15, 2)->default(0);
            $table->decimal('mutasi_masuk', 15, 2)->default(0);
            $table->decimal('mutasi_keluar', 15, 2)->default(0);
            $table->decimal('saldo_akhir', 15, 2)->default(0);
            $table->decimal('saldo_fisik', 15, 2)->nullable(); // hasil hitung uang laci (closing harian, kas tunai)
            $table->decimal('selisih', 15, 2)->nullable();     // fisik − sistem
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_kas');
        Schema::dropIfExists('closings');
    }
};
