<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class HelperRolePermissionSeeder extends Seeder
{
    /**
     * Role 'helper' terdaftar via RoleUser::Helper enum.
     * Tidak ada permission yang di-assign secara default —
     * Owner dapat mengatur sendiri via halaman /role.
     */
    public function run(): void
    {
        // Tidak ada permission default untuk role helper.
        // Semua permission diatur oleh Owner via UI Role & Permissions.
        $this->command->info('Role "helper" sudah tersedia via enum. Tidak ada permission default yang di-assign.');
    }
}
