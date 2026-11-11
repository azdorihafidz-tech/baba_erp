<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- ORDERS: tambah field antrian produksi ---
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'nomor_antrian')) {
                $table->smallInteger('nomor_antrian')->unsigned()->nullable();
            }
            if (!Schema::hasColumn('orders', 'berat_daging_kg')) {
                $table->decimal('berat_daging_kg', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('orders', 'status_produksi')) {
                $table->string('status_produksi', 20)->nullable()
                    ->comment('menunggu/dikerjakan/selesai/disimpan/diambil');
            }
            if (!Schema::hasColumn('orders', 'lokasi_rak')) {
                $table->string('lokasi_rak', 50)->nullable();
            }
            if (!Schema::hasColumn('orders', 'dikerjakan_oleh_id')) {
                $table->unsignedBigInteger('dikerjakan_oleh_id')->nullable();
            }
            if (!Schema::hasColumn('orders', 'waktu_mulai_kerja')) {
                $table->timestamp('waktu_mulai_kerja')->nullable();
            }
            if (!Schema::hasColumn('orders', 'waktu_selesai_kerja')) {
                $table->timestamp('waktu_selesai_kerja')->nullable();
            }
            if (!Schema::hasColumn('orders', 'waktu_disimpan')) {
                $table->timestamp('waktu_disimpan')->nullable();
            }
            if (!Schema::hasColumn('orders', 'waktu_diambil')) {
                $table->timestamp('waktu_diambil')->nullable();
            }
            if (!Schema::hasColumn('orders', 'catatan_produksi')) {
                $table->text('catatan_produksi')->nullable();
            }
        });

        // Foreign key dikerjakan_oleh_id — terpisah agar mudah di-skip jika sudah ada
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('dikerjakan_oleh_id')
                    ->references('id')->on('karyawans')
                    ->nullOnDelete();
            });
        } catch (\Throwable) {
            // FK sudah ada atau tabel karyawans belum tersedia
        }

        // UNIQUE antrian per cabang per hari (NULL diabaikan oleh MySQL UNIQUE)
        try {
            DB::statement('ALTER TABLE orders ADD UNIQUE INDEX orders_antrian_unique (cabang_id, tanggal_order, nomor_antrian)');
        } catch (\Throwable) {
            // Index sudah ada
        }

        // Index status produksi untuk query display & operator
        try {
            DB::statement('ALTER TABLE orders ADD INDEX orders_cabang_status_produksi_idx (cabang_id, status_produksi)');
        } catch (\Throwable) {
            // Index sudah ada
        }

        // --- CABANGS: tambah toggle antrian produksi ---
        if (!Schema::hasColumn('cabangs', 'antrian_produksi_aktif')) {
            Schema::table('cabangs', function (Blueprint $table) {
                $table->boolean('antrian_produksi_aktif')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            try { $table->dropForeign(['dikerjakan_oleh_id']); } catch (\Throwable) {}
            try { $table->dropIndex('orders_antrian_unique'); } catch (\Throwable) {}
            try { $table->dropIndex('orders_cabang_status_produksi_idx'); } catch (\Throwable) {}

            $cols = [
                'nomor_antrian', 'berat_daging_kg', 'status_produksi', 'lokasi_rak',
                'dikerjakan_oleh_id', 'waktu_mulai_kerja', 'waktu_selesai_kerja',
                'waktu_disimpan', 'waktu_diambil', 'catatan_produksi',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('cabangs', 'antrian_produksi_aktif')) {
            Schema::table('cabangs', function (Blueprint $table) {
                $table->dropColumn('antrian_produksi_aktif');
            });
        }
    }
};
