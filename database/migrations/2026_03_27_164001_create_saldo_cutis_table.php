<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldo_cutis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawans')->cascadeOnDelete();
            $table->integer('tahun');
            $table->integer('saldo_awal')->default(12); // 12 hari per tahun
            $table->integer('terpakai')->default(0);
            $table->integer('sisa')->default(12); // dihitung manual: saldo_awal - terpakai
            $table->timestamps();
            $table->unique(['karyawan_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_cutis');
    }
};
