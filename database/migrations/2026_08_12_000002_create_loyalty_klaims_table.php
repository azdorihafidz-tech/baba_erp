<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_klaims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs');
            $table->foreignId('pelanggan_id')->constrained('pelanggans');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            // Terima keduanya: path file upload (Storage::store) ATAU teks
            // link URL post sosmed -- kolom sama, disimpan apa adanya sesuai
            // yang dikirim kasir (form nentuin salah satu).
            $table->text('bukti_url');
            $table->text('bukti_catatan')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'issued'])->default('pending');
            // Snapshot nominal saat approve -- kalau program.nominal_voucher
            // berubah nanti, klaim lama tidak ikut berubah (voucher yg sudah
            // di-approve harus tetap sesuai nilai saat itu).
            $table->decimal('nominal_voucher', 12, 2)->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->text('catatan_issued')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['pelanggan_id', 'loyalty_program_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_klaims');
    }
};
