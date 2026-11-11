<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kategori_transaksis')) {
            Schema::create('kategori_transaksis', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 30)->unique();
                $table->string('nama', 100);
                $table->enum('tipe', ['pemasukan', 'pengeluaran', 'keduanya'])->default('keduanya');
                $table->foreignId('parent_id')->nullable()->constrained('kategori_transaksis')->nullOnDelete();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_transaksis');
    }
};
