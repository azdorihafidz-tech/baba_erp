<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_requests MODIFY COLUMN status ENUM('pending','disetujui','ditolak','dikirim','diterima','dibatalkan') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_requests MODIFY COLUMN status ENUM('pending','disetujui','ditolak','dikirim','diterima') NOT NULL DEFAULT 'pending'");
    }
};
