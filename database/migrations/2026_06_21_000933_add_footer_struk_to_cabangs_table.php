<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            if (!Schema::hasColumn('cabangs', 'footer_struk')) {
                $table->text('footer_struk')->nullable()->after('running_text_aktif');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumnIfExists('footer_struk');
        });
    }
};
