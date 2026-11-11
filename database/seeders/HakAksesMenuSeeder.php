<?php

namespace Database\Seeders;

use App\Support\HakAksesMenu;
use Illuminate\Database\Seeder;

/**
 * CLAUDE.md 4.43 — izin per menu + pewarisan centang untuk instalasi baru
 * (DatabaseSeeder/GoLiveSeeder, dijalankan SETELAH RolePermissionSeeder).
 * Database yang sudah berjalan memakai migration 2026_11_02_100001.
 */
class HakAksesMenuSeeder extends Seeder
{
    public function run(): void
    {
        HakAksesMenu::terapkan();
        $this->command?->info('✅ Izin per menu 4.43 diterapkan.');
    }
}
