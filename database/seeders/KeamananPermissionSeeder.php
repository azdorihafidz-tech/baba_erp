<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class KeamananPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Audit Log
            ['name' => 'lihat_audit_log',        'display_name' => 'Lihat Audit Log',          'group' => 'keamanan'],
            ['name' => 'export_audit_log',        'display_name' => 'Export Audit Log',         'group' => 'keamanan'],

            // Data Terhapus
            ['name' => 'lihat_data_terhapus',     'display_name' => 'Lihat Data Terhapus',      'group' => 'keamanan'],
            ['name' => 'restore_data_terhapus',   'display_name' => 'Restore Data Terhapus',    'group' => 'keamanan'],
            ['name' => 'hapus_permanen_data',     'display_name' => 'Hapus Permanen Data',      'group' => 'keamanan'],

            // Backup Database
            ['name' => 'lihat_backup',            'display_name' => 'Lihat Backup Database',    'group' => 'keamanan'],
            ['name' => 'buat_backup',             'display_name' => 'Buat Backup Manual',       'group' => 'keamanan'],
            ['name' => 'download_backup',         'display_name' => 'Download File Backup',     'group' => 'keamanan'],
            ['name' => 'hapus_backup',            'display_name' => 'Hapus File Backup',        'group' => 'keamanan'],
        ];

        foreach ($permissions as $data) {
            Permission::firstOrCreate(
                ['name' => $data['name']],
                ['display_name' => $data['display_name'], 'group' => $data['group']]
            );
        }

        $this->command->info('✅ Keamanan permissions berhasil ditambahkan (idempotent).');
    }
}
