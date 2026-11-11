<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_items', 'hpp')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('hpp', 15, 2)->default(0)->after('total_harga');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('hpp');
        });
    }
};
