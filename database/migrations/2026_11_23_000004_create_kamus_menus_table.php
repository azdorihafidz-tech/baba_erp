<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.50 Kamus Menu — penjelasan detail tiap menu (fungsi, syarat muncul, alur,
 * dampak tiap tombol ke stok/kas/laporan, wajib/jangan, menu mirip). Isinya
 * ditulis di KamusMenuSeeder (bukan diketik lewat UI), jadi tabel ini hanya
 * tempat simpan + cari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kamus_menus', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('judul', 150);
            $table->string('kelompok', 60);          // sama dgn judul bagian sidebar
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('letak', 255);            // Penjualan → POS / Kasir
            $table->string('ringkasan', 255);        // 1 kalimat fungsi
            $table->longText('konten');              // Markdown
            $table->text('kata_kunci')->nullable();  // kata sehari-hari utk pencarian
            $table->json('izin')->nullable();        // salah satu cukup; null = semua user login; '@owner', '@semua_cabang'
            $table->json('route_names')->nullable(); // pola nama route halaman menu ini (tombol Kamus di header)
            $table->json('terkait')->nullable();     // slug kamus menu lain
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['kelompok', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kamus_menus');
    }
};
