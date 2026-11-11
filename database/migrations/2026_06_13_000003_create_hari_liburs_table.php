<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hari_liburs', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->index();
            $table->string('nama', 100);
            $table->enum('tipe', ['nasional', 'cabang'])->default('nasional');
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tanggal', 'cabang_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hari_liburs');
    }
};
