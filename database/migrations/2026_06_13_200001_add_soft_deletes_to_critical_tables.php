<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel yang ditambahkan soft delete (deleted_at).
     * Idempotent: cek hasColumn sebelum tambah.
     */
    private array $tables = [
        'orders',
        'order_items',
        'purchase_orders',
        'purchase_order_items',
        'suppliers',
        'absensis',
        'face_attendances',
        'karyawans',
        'cabangs',
        'shifts',
        'hari_liburs',
        'penggajians',
        'pengaturan_gajis',
        'transaksi_keuangans',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (!Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
