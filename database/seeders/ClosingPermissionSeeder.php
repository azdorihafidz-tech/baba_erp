<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Permission menu Closing / Tutup Buku — ADDITIVE & idempotent.
 *
 * Aman dijalankan di production: hanya menambah permission + mapping role
 * yang belum ada (insertOrIgnore), TIDAK menghapus mapping role lain yang
 * mungkin sudah diubah Owner lewat UI Role & Hak Akses (beda dengan
 * RolePermissionSeeder yang delete-lalu-insert per role).
 */
class ClosingPermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        ['name' => 'closing.view',        'display_name' => 'Lihat Closing / Tutup Buku',              'group' => 'keuangan'],
        ['name' => 'closing.harian',      'display_name' => 'Closing Harian (hitung uang fisik)',      'group' => 'keuangan'],
        ['name' => 'closing.bulanan',     'display_name' => 'Tutup Buku Bulanan (kunci periode)',      'group' => 'keuangan'],
        ['name' => 'closing.konsolidasi', 'display_name' => 'Lihat Closing Keseluruhan (semua cabang)', 'group' => 'keuangan'],
        ['name' => 'closing.buka_kunci',  'display_name' => 'Buka Kunci Periode Closing',              'group' => 'keuangan'],
        ['name' => 'closing.export',      'display_name' => 'Export Closing (Excel/PDF)',              'group' => 'keuangan'],
    ];

    /** Default mapping — sama dengan entry di RolePermissionSeeder. */
    public const ROLE_DEFAULTS = [
        'admin_pusat'    => ['closing.view', 'closing.harian', 'closing.bulanan', 'closing.konsolidasi', 'closing.export'],
        'manajer_cabang' => ['closing.view', 'closing.harian', 'closing.export'],
        'kasir'          => ['closing.view', 'closing.harian'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $data) {
            Permission::firstOrCreate(
                ['name' => $data['name']],
                ['display_name' => $data['display_name'], 'group' => $data['group']]
            );
        }

        $permMap = Permission::whereIn('name', array_column(self::PERMISSIONS, 'name'))->pluck('id', 'name');
        $rows = [];
        foreach (self::ROLE_DEFAULTS as $role => $names) {
            foreach ($names as $name) {
                if (isset($permMap[$name])) {
                    $rows[] = ['role' => $role, 'permission_id' => $permMap[$name]];
                }
            }
        }
        DB::table('role_permissions')->insertOrIgnore($rows);

        Cache::forget('all_permission_names');
        foreach (array_keys(self::ROLE_DEFAULTS) as $role) {
            Cache::forget("role_permissions_{$role}");
        }

        $this->command?->info('✅ Closing permissions ditambahkan (idempotent, mapping role lama tidak disentuh).');
    }
}
