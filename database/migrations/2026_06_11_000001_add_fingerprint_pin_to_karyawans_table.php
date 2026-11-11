<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->string('fingerprint_pin', 50)
                ->nullable()
                ->unique()
                ->after('nik')
                ->comment('PIN karyawan di mesin fingerspot/ZKTeco untuk matching USER_ID');
        });
    }

    public function down(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropUnique(['fingerprint_pin']);
            $table->dropColumn('fingerprint_pin');
        });
    }
};
