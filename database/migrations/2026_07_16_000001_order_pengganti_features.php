<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'tampil_di_antrian')) {
                $table->boolean('tampil_di_antrian')->default(true)->after('status_produksi');
            }
            if (!Schema::hasColumn('orders', 'alasan_pembatalan_kategori')) {
                $table->string('alasan_pembatalan_kategori', 50)->nullable()->after('status');
            }
            if (!Schema::hasColumn('orders', 'alasan_pembatalan_detail')) {
                $table->text('alasan_pembatalan_detail')->nullable()->after('alasan_pembatalan_kategori');
            }
        });

        // Kolom self-referencing FK terpisah supaya try/catch tidak menggagalkan
        // ADD COLUMN lain kalau constraint sudah pernah dibuat sebelumnya.
        if (!Schema::hasColumn('orders', 'parent_order_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('parent_order_id')->nullable()->after('id')
                    ->constrained('orders')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'parent_order_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['parent_order_id']);
                $table->dropColumn('parent_order_id');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach (['alasan_pembatalan_detail', 'alasan_pembatalan_kategori', 'tampil_di_antrian'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
