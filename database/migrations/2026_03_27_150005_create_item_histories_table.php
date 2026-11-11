<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();

            $table->index('item_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_histories');
    }
};
