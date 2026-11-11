<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AntrianPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name'         => 'antrian.lihat',
                'display_name' => 'Lihat Antrian Produksi',
                'group'        => 'antrian',
                'description'  => 'Akses halaman antrian (operator/display)',
            ],
            [
                'name'         => 'antrian.kelola',
                'display_name' => 'Kelola Status Antrian',
                'group'        => 'antrian',
                'description'  => 'Update status produksi (mulai kerja, selesai, simpan, ambil)',
            ],
            [
                'name'         => 'antrian.display',
                'display_name' => 'Akses Layar Display Antrian',
                'group'        => 'antrian',
                'description'  => 'Akses layar TV public untuk display antrian',
            ],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                [
                    'display_name' => $perm['display_name'],
                    'group'        => $perm['group'],
                    'description'  => $perm['description'] ?? null,
                ]
            );
        }

        $this->command->info('Permission antrian.* berhasil ditambahkan.');

        // Assign ke role setelah permission pasti ada
        $permMap = Permission::whereIn('name', ['antrian.lihat', 'antrian.kelola', 'antrian.display'])
            ->pluck('id', 'name');

        $roleAssignments = [
            'admin_pusat'      => ['antrian.lihat', 'antrian.kelola', 'antrian.display'],
            'manajer_cabang'   => ['antrian.lihat', 'antrian.kelola', 'antrian.display'],
            'kasir'            => ['antrian.lihat', 'antrian.display'],
            'admin_gudang'     => ['antrian.lihat'],
            'operator_produksi'=> ['antrian.lihat', 'antrian.kelola', 'antrian.display'],
        ];

        foreach ($roleAssignments as $role => $permNames) {
            foreach ($permNames as $name) {
                if (!isset($permMap[$name])) {
                    continue;
                }

                $exists = DB::table('role_permissions')
                    ->where('role', $role)
                    ->where('permission_id', $permMap[$name])
                    ->exists();

                if (!$exists) {
                    DB::table('role_permissions')->insert([
                        'role'          => $role,
                        'permission_id' => $permMap[$name],
                    ]);
                }
            }

            $this->command->line("  Role <info>{$role}</info>: antrian permissions assigned.");
        }
    }
}
