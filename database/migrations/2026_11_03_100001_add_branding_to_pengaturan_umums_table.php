<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4.44 Poin 3 (Identitas Usaha, porting erp-coffeeshop) — nama singkat, tagline
 * & telepon di Pengaturan Umum, supaya kode yang sama bisa dipakai usaha lain
 * tanpa mengubah program (lihat App\Support\Branding).
 *
 * Instalasi yang sudah jalan (D'mentai live & demo): diisi nilai yang SELAMA INI
 * ditulis langsung di tampilan (sidebar/struk/PDF: "D'mentai" · "Dimsum & Gyoza"),
 * supaya setelah deploy tampilan tidak berubah. Owner bisa menggantinya di
 * Pengaturan Umum. Instalasi baru (migrate sebelum seed, belum ada cabang)
 * dibiarkan kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            if (! Schema::hasColumn('pengaturan_umums', 'nama_singkat')) {
                $table->string('nama_singkat', 30)->nullable()->after('nama_perusahaan');
            }
            if (! Schema::hasColumn('pengaturan_umums', 'tagline')) {
                $table->string('tagline', 150)->nullable()->after('nama_singkat');
            }
            if (! Schema::hasColumn('pengaturan_umums', 'telepon')) {
                $table->string('telepon', 30)->nullable()->after('alamat_perusahaan');
            }
        });

        if (! DB::table('cabangs')->exists()) {
            return;
        }

        if (DB::table('pengaturan_umums')->doesntExist()) {
            DB::table('pengaturan_umums')->insert([
                'nama_perusahaan' => "D'mentai",
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        DB::table('pengaturan_umums')->whereNull('nama_singkat')->update(['nama_singkat' => "D'mentai"]);
        DB::table('pengaturan_umums')->whereNull('tagline')->update(['tagline' => 'Dimsum & Gyoza']);
    }

    public function down(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->dropColumn(['nama_singkat', 'tagline', 'telepon']);
        });
    }
};
