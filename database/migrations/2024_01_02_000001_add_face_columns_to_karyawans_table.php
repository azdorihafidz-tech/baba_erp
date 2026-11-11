<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->longText('face_data')->nullable()->after('foto')
                ->comment('JSON array of face descriptors dari face-api.js');
            $table->json('face_photos')->nullable()->after('face_data')
                ->comment('Path foto-foto pendaftaran wajah');
            $table->timestamp('face_registered_at')->nullable()->after('face_photos');
        });
    }

    public function down(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropColumn(['face_data', 'face_photos', 'face_registered_at']);
        });
    }
};
