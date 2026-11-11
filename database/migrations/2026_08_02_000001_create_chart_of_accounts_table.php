<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 6)->unique();
            $table->string('nama', 100);
            $table->enum('tipe', [
                'aset', 'kewajiban', 'modal', 'pendapatan', 'hpp',
                'beban_operasional', 'pendapatan_lain', 'beban_lain',
            ]);
            $table->string('subtipe', 50)->nullable();
            // Referensi ke kode induk (bukan FK keras — konsisten dengan pola
            // deleted_by/changed_by di codebase ini yang menghindari FK constraint
            // supaya soft-delete parent tidak memblokir/merepotkan child).
            $table->string('parent_kode', 6)->nullable();
            $table->enum('saldo_normal', ['debet', 'kredit']);
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_leaf')->default(true);
            $table->unsignedInteger('urutan')->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index('parent_kode');
            $table->index('tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
