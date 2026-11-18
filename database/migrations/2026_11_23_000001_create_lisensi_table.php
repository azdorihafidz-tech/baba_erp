<?php

use App\Support\Paket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket modul & masa langganan per instalasi (porting Dimsum 4.53 (_rancangan/RANCANGAN-KAMUS-TIMIT-PAKET.md)).
 * Instalasi yang sudah ada otomatis = SEMUA modul, tanpa batas tanggal →
 * tidak ada yang berubah sampai Tim IT mengaturnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lisensi', function (Blueprint $table) {
            $table->id();
            $table->json('modul');
            $table->date('aktif_sampai')->nullable();
            $table->unsignedTinyInteger('masa_tenggang_hari')->default(7);
            $table->string('segel', 64);
            $table->foreignId('diubah_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('peringatan_segel_at')->nullable(); // notifikasi segel rusak terakhir (sekali per hari)
            $table->timestamps();
        });

        Paket::lupakan();
        Paket::simpan(array_keys(Paket::MODUL), null, 7, null);
    }

    public function down(): void
    {
        Schema::dropIfExists('lisensi');
        Paket::lupakan();
    }
};
