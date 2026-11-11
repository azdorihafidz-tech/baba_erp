<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Semua tabel yang terdaftar di TrashController::$models (fitur Data Terhapus).
     * Kolom nullable, tanpa FK (konsisten dengan pola changed_by di tabel _histories —
     * disimpan langsung sebagai id, bukan relasi, supaya migrasi aman & tidak
     * tersandung constraint kalau user penghapus kelak ikut terhapus).
     */
    private array $tables = [
        'orders', 'purchase_orders', 'suppliers', 'absensis', 'karyawans',
        'cabangs', 'shifts', 'hari_liburs', 'penggajians', 'users',
        'absen_devices', 'item_categories', 'pelanggans', 'stocks',
        'stock_requests', 'stock_transfers', 'items', 'assets',
        'transaksi_keuangans', 'kas', 'kategori_transaksis',
        'recurring_transaksis', 'stock_batches', 'cutis',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_by')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    if (Schema::hasColumn($table, 'deleted_at')) {
                        $t->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
                    } else {
                        $t->unsignedBigInteger('deleted_by')->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_by')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('deleted_by');
                });
            }
        }
    }
};
