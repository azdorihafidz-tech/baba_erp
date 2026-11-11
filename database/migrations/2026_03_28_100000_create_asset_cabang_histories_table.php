<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asset History
        if (!Schema::hasTable('asset_histories'))
        Schema::create('asset_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();
            $table->index('asset_id');
            $table->index('action');
        });

        // Cabang History
        if (!Schema::hasTable('cabang_histories'))
        Schema::create('cabang_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cabang_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();
            $table->index('cabang_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_histories');
        Schema::dropIfExists('cabang_histories');
    }
};
