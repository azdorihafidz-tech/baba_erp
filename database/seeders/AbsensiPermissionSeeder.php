<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class AbsensiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $newPermissions = [
            // Absensi — tambahan permission baru
            ['name' => 'hapus_absensi',       'display_name' => 'Hapus Absensi',               'group' => 'hr'],
            ['name' => 'hapus_log_absensi',    'display_name' => 'Hapus Log Absensi Wajah',     'group' => 'hr'],
            ['name' => 'scan_absensi',         'display_name' => 'Scan Absensi Wajah',          'group' => 'hr'],
            ['name' => 'dashboard_absensi',    'display_name' => 'Dashboard Absensi',           'group' => 'hr'],
            ['name' => 'laporan_absensi',      'display_name' => 'Laporan Absensi',             'group' => 'hr'],
            ['name' => 'registrasi_wajah',     'display_name' => 'Registrasi Wajah Karyawan',  'group' => 'hr'],
            ['name' => 'pengaturan_penggajian','display_name' => 'Pengaturan Tarif Penggajian', 'group' => 'hr'],

            // Shift (group baru)
            ['name' => 'lihat_shift',          'display_name' => 'Lihat Shift',                 'group' => 'shift'],
            ['name' => 'kelola_shift',         'display_name' => 'Kelola Shift',                'group' => 'shift'],

            // Hari Libur (group baru)
            ['name' => 'kelola_hari_libur',    'display_name' => 'Kelola Hari Libur',           'group' => 'hari_libur'],
        ];

        foreach ($newPermissions as $data) {
            Permission::firstOrCreate(
                ['name' => $data['name']],
                ['display_name' => $data['display_name'], 'group' => $data['group']]
            );
        }

        $this->command->info('✅ Absensi permissions berhasil ditambahkan (idempotent).');
    }
}
