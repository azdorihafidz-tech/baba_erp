<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            // Program existing (Fase 1, "500 Kg") semuanya default 'auto_track'
            // -- backfill otomatis lewat DEFAULT kolom, tidak perlu seeder
            // data-migration terpisah.
            $table->enum('tipe_program', ['auto_track', 'event_based'])
                ->default('auto_track')->after('nama');
            // Nominal voucher default utk klaim event_based (bisa di-override
            // per klaim saat approve) -- nullable karena tidak relevan utk
            // program auto_track.
            $table->decimal('nominal_voucher', 12, 2)->nullable()->after('hadiah');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->dropColumn(['tipe_program', 'nominal_voucher']);
        });
    }
};
