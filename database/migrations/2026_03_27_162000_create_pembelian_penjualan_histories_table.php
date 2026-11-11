<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchase Order Histories
        Schema::create('purchase_order_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();

            $table->index('purchase_order_id');
            $table->index('action');
        });

        // Purchase Order Item Histories
        Schema::create('purchase_order_item_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();

            $table->index('purchase_order_item_id');
            $table->index('action');
        });

        // Order Histories
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();

            $table->index('order_id');
            $table->index('action');
        });

        // Order Item Histories
        Schema::create('order_item_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->enum('action', ['updated', 'deleted']);
            $table->json('data_lama');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at');
            $table->string('keterangan')->nullable();

            $table->index('order_item_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_histories');
        Schema::dropIfExists('order_histories');
        Schema::dropIfExists('purchase_order_item_histories');
        Schema::dropIfExists('purchase_order_histories');
    }
};
