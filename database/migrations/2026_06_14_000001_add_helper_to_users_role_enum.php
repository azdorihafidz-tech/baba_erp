<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner','admin_pusat','admin_gudang','manajer_cabang','kasir','operator_produksi','helper') NOT NULL DEFAULT 'kasir'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner','admin_pusat','admin_gudang','manajer_cabang','kasir','operator_produksi') NOT NULL DEFAULT 'kasir'");
    }
};
