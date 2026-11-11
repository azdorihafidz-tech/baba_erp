<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('kas', 'deleted_at')) {
            Schema::table('kas', function (Blueprint $table) {
                $table->softDeletes()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kas', 'deleted_at')) {
            Schema::table('kas', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
