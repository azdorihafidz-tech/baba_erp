<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap D (2026-10-18, CLAUDE.md 4.38) — role bebas buatan Owner (mis.
 * "Kru"). 7 role sistem tetap di enum App\Enums\RoleUser; role buatan
 * disimpan di custom_roles. users.role diubah dari ENUM ke VARCHAR supaya
 * bisa menampung kode role buatan. Hak akses tetap lewat role_permissions
 * (sudah berbasis teks kode role).
 */
return new class extends Migration
{
    private const ROLE_SISTEM = ['owner', 'admin_pusat', 'admin_gudang', 'manajer_cabang', 'kasir', 'operator_produksi', 'helper'];

    public function up(): void
    {
        Schema::create('custom_roles', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->boolean('akses_semua_cabang')->default(false);
            $table->string('deskripsi', 255)->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'kasir'");
    }

    public function down(): void
    {
        // User dgn role buatan dikembalikan ke 'helper' (role paling terbatas)
        // sebelum kolom kembali menjadi ENUM, supaya tidak ada data terpotong.
        DB::table('users')->whereNotIn('role', self::ROLE_SISTEM)->update(['role' => 'helper']);
        $kode = collect(DB::table('custom_roles')->pluck('kode'))->all();
        if ($kode) {
            DB::table('role_permissions')->whereIn('role', $kode)->delete();
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('" . implode("','", self::ROLE_SISTEM) . "') NOT NULL DEFAULT 'kasir'");
        Schema::dropIfExists('custom_roles');
    }
};
