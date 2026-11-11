<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_attendances', function (Blueprint $table) {
            $table->boolean('is_liveness_passed')->nullable()->after('status')
                ->comment('Apakah liveness check (kedip + gerak kepala) berhasil dilewati');
        });
    }

    public function down(): void
    {
        Schema::table('face_attendances', function (Blueprint $table) {
            $table->dropColumn('is_liveness_passed');
        });
    }
};
