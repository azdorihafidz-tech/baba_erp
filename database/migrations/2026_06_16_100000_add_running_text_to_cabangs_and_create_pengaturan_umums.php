<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Running text per cabang ──────────────────────────────────────────
        Schema::table('cabangs', function (Blueprint $table) {
            if (!Schema::hasColumn('cabangs', 'running_text')) {
                $table->text('running_text')->nullable()->after('antrian_produksi_aktif');
            }
            if (!Schema::hasColumn('cabangs', 'running_text_aktif')) {
                $table->boolean('running_text_aktif')->default(false)->after('running_text');
            }
        });

        // ── Pengaturan Umum (singleton) ──────────────────────────────────────
        if (!Schema::hasTable('pengaturan_umums')) {
            Schema::create('pengaturan_umums', function (Blueprint $table) {
                $table->id();
                $table->string('nama_perusahaan', 100)->default('Berkah Mulyo');
                $table->string('logo_path', 255)->nullable();
                $table->text('alamat_perusahaan')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumnIfExists('running_text');
            $table->dropColumnIfExists('running_text_aktif');
        });

        Schema::dropIfExists('pengaturan_umums');
    }
};
