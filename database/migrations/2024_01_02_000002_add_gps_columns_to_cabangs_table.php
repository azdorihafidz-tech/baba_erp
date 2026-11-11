<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('is_active');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->unsignedInteger('radius_absen_meter')->default(100)->after('longitude');
            $table->string('jam_masuk', 5)->default('08:00')->after('radius_absen_meter')
                ->comment('Jam masuk kerja cabang format HH:MM');
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius_absen_meter', 'jam_masuk']);
        });
    }
};
