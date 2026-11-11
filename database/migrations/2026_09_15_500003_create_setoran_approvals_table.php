<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 5 D'mentai — audit trail submit/approve/reject per setoran. Berguna
 * kalau kasir revise setoran yang ditolak berkali-kali (setoran.status cuma
 * simpan state TERKINI, tabel ini simpan SELURUH histori aksi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setoran_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setoran_id')->constrained('setorans')->cascadeOnDelete();
            $table->string('action', 20);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('dilakukan_pada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setoran_approvals');
    }
};
