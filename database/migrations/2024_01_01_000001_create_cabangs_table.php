<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabangs', function (Blueprint $table) {
            $table->id();
            $table->string('nama_cabang');
            $table->string('kode_cabang', 10)->unique();
            $table->text('alamat')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->foreignId('kepala_cabang_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipe', ['cabang', 'gudang_pusat'])->default('cabang');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabangs');
    }
};
