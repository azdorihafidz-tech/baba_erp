<?php

use App\Support\HakAksesMenu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CLAUDE.md 4.43 — izin per menu + pewarisan centang (sekali jalan).
 * Semua yang ditambahkan dicatat di hak_akses_migrasi_logs supaya down()
 * hanya membatalkan perubahan migration ini, bukan centangan buatan Owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hak_akses_migrasi_logs', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 10); // izin | centang | label
            $table->string('role')->nullable();
            $table->unsignedBigInteger('permission_id')->nullable();
            $table->string('nama')->nullable();
            $table->string('nilai_lama')->nullable();
            $table->timestamps();
        });

        $hasil = HakAksesMenu::terapkan();
        $now = now();
        $log = [];
        foreach ($hasil['izin_dibuat'] as $id) {
            $log[] = ['jenis' => 'izin', 'role' => null, 'permission_id' => $id, 'nama' => null, 'nilai_lama' => null, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($hasil['centang'] as $c) {
            $log[] = ['jenis' => 'centang', 'role' => $c['role'], 'permission_id' => $c['permission_id'], 'nama' => null, 'nilai_lama' => null, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($hasil['label_lama'] as $nama => $lama) {
            $log[] = ['jenis' => 'label', 'role' => null, 'permission_id' => null, 'nama' => $nama, 'nilai_lama' => $lama, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($log, 200) as $chunk) {
            DB::table('hak_akses_migrasi_logs')->insert($chunk);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hak_akses_migrasi_logs')) {
            foreach (DB::table('hak_akses_migrasi_logs')->where('jenis', 'centang')->get() as $c) {
                DB::table('role_permissions')->where('role', $c->role)->where('permission_id', $c->permission_id)->delete();
            }
            foreach (DB::table('hak_akses_migrasi_logs')->where('jenis', 'label')->get() as $l) {
                DB::table('permissions')->where('name', $l->nama)->update(['display_name' => $l->nilai_lama]);
            }
            // role_permissions ikut terhapus (cascadeOnDelete)
            DB::table('permissions')->whereIn('id', DB::table('hak_akses_migrasi_logs')->where('jenis', 'izin')->pluck('permission_id'))->delete();
        }
        Schema::dropIfExists('hak_akses_migrasi_logs');
        HakAksesMenu::lupakanCache();
    }
};
