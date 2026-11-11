<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'owner', 'admin_pusat', 'admin_gudang',
                'manajer_cabang', 'kasir', 'operator_produksi'
            ])->default('kasir')->after('email');
            $table->string('telepon', 20)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('telepon');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'telepon', 'is_active']);
        });
    }
};
