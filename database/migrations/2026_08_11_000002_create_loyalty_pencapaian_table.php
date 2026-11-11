<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_pencapaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelanggan_id')->constrained('pelanggans');
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs');
            $table->date('tanggal_tercapai');
            $table->decimal('progress_kg', 10, 2);
            $table->enum('status', ['tercapai', 'hadiah_diberikan', 'expired'])->default('tercapai');
            $table->text('catatan_hadiah')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['pelanggan_id', 'loyalty_program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_pencapaian');
    }
};
