<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom default_untuk ke tabel kas
        if (!Schema::hasColumn('kas', 'default_untuk')) {
            Schema::table('kas', function (Blueprint $table) {
                $table->enum('default_untuk', ['tunai', 'transfer', 'qris'])
                      ->nullable()
                      ->after('tipe_kas');
            });
        }

        // Unique index: 1 kas default per tipe per cabang (MySQL allow multiple NULLs)
        try {
            Schema::table('kas', function (Blueprint $table) {
                $table->unique(['cabang_id', 'default_untuk'], 'kas_cabang_default_unique');
            });
        } catch (\Exception $e) {
            // Index sudah ada, skip
        }

        // Tambah kolom kas_id ke tabel orders
        if (!Schema::hasColumn('orders', 'kas_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('kas_id')
                      ->nullable()
                      ->after('tipe_pembayaran')
                      ->constrained('kas')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'kas_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['kas_id']);
                $table->dropColumn('kas_id');
            });
        }

        if (Schema::hasColumn('kas', 'default_untuk')) {
            Schema::table('kas', function (Blueprint $table) {
                try {
                    $table->dropUnique('kas_cabang_default_unique');
                } catch (\Exception $e) {}
                $table->dropColumn('default_untuk');
            });
        }
    }
};
