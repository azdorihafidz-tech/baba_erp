<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            if (!Schema::hasColumn('cabangs', 'izinkan_sembunyi_harga_struk')) {
                $table->boolean('izinkan_sembunyi_harga_struk')->default(false)->after('footer_struk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumnIfExists('izinkan_sembunyi_harga_struk');
        });
    }
};
