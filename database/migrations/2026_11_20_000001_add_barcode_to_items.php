<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ERP BABA Tahap 6a — barcode botol parfum untuk scan di kasir (POS). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'barcode')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('barcode', 64)->nullable()->after('kode_item');
                $table->index('barcode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('items', 'barcode')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropIndex(['barcode']);
                $table->dropColumn('barcode');
            });
        }
    }
};
