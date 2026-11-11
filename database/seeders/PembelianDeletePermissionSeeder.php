<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PembelianDeletePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate(
            ['name' => 'pembelian.delete'],
            ['display_name' => 'Hapus Purchase Order', 'group' => 'pembelian']
        );
    }
}
