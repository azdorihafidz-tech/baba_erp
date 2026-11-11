<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Karyawan History
        Schema::create('karyawan_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();
            $table->index('karyawan_id');
            $table->index('action');
        });

        // Penggajian History
        Schema::create('penggajian_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penggajian_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();
            $table->index('penggajian_id');
            $table->index('action');
        });

        // Cuti History
        Schema::create('cuti_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuti_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();
            $table->index('cuti_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan_histories');
        Schema::dropIfExists('penggajian_histories');
        Schema::dropIfExists('cuti_histories');
    }
};
