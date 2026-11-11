<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Ubah ENUM('bakso','sosis','tempura') → VARCHAR(50).
    // Data lama tetap valid; kolom nullable agar tidak break record jasa giling kosong.
    public function up(): void
    {
        DB::statement('ALTER TABLE order_items MODIFY COLUMN jenis_olahan VARCHAR(50) NULL');
    }

    public function down(): void
    {
        // Rollback: kembalikan ke ENUM (data di luar enum akan hilang — jarang dipakai)
        DB::statement("ALTER TABLE order_items MODIFY COLUMN jenis_olahan ENUM('bakso','sosis','tempura') NULL");
    }
};
