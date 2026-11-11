<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $gudangPusat = Cabang::where('kode_cabang', 'GP001')->first();
        $cabangA     = Cabang::where('kode_cabang', 'CA001')->first();
        $cabangB     = Cabang::where('kode_cabang', 'CB001')->first();

        $users = [
            // Owner — akses semua cabang
            [
                'name'      => 'Admin Owner',
                'email'     => 'admin@berkahmulyo.com',
                'password'  => Hash::make('password'),
                'role'      => 'owner',
                'telepon'   => '081234567890',
                'is_active' => true,
                'cabangs'   => [
                    $gudangPusat?->id => ['is_default' => true],
                    $cabangA?->id     => ['is_default' => false],
                    $cabangB?->id     => ['is_default' => false],
                ],
            ],
            // Admin Pusat
            [
                'name'      => 'Admin Pusat',
                'email'     => 'adminpusat@berkahmulyo.com',
                'password'  => Hash::make('password'),
                'role'      => 'admin_pusat',
                'telepon'   => '081234567801',
                'is_active' => true,
                'cabangs'   => [
                    $gudangPusat?->id => ['is_default' => true],
                    $cabangA?->id     => ['is_default' => false],
                    $cabangB?->id     => ['is_default' => false],
                ],
            ],
            // Admin Gudang Pusat
            [
                'name'      => 'Admin Gudang',
                'email'     => 'gudang@berkahmulyo.com',
                'password'  => Hash::make('password'),
                'role'      => 'admin_gudang',
                'telepon'   => '081234567893',
                'is_active' => true,
                'cabangs'   => [
                    $gudangPusat?->id => ['is_default' => true],
                ],
            ],
            // Manajer Cabang A
            [
                'name'      => 'Budi Manajer',
                'email'     => 'manajer.a@berkahmulyo.com',
                'password'  => Hash::make('password'),
                'role'      => 'manajer_cabang',
                'telepon'   => '081234567891',
                'is_active' => true,
                'cabangs'   => [
                    $cabangA?->id => ['is_default' => true],
                ],
            ],
            // Manajer Cabang B
            [
                'name'      => 'Sari Manajer',
                'email'     => 'manajer.b@berkahmulyo.com',
                'password'  => Hash::make('password'),
                'role'      => 'manajer_cabang',
                'telepon'   => '081234567895',
                'is_active' => true,
                'cabangs'   => [
                    $cabangB?->id => ['is_default' => true],
                ],
            ],
            // Kasir Cabang A
            [
                'name'      => 'Rani Kasir A',
                'email'     => 'kasir.a@berkahmulyo.com',
                'password'  => Hash::make('password'),
                'role'      => 'kasir',
                'telepon'   => '081234567892',
                'is_active' => true,
                'cabangs'   => [
                    $cabangA?->id => ['is_default' => true],
                ],
            ],
            // Kasir Cabang B
            [
                'name'      => 'Dewi Kasir B',
                'email'     => 'kasir.b@berkahmulyo.com',
                'password'  => Hash::make('password'),
                'role'      => 'kasir',
                'telepon'   => '081234567896',
                'is_active' => true,
                'cabangs'   => [
                    $cabangB?->id => ['is_default' => true],
                ],
            ],
            // Operator Produksi Cabang A
            [
                'name'      => 'Hendra Operator A',
                'email'     => 'operator.a@berkahmulyo.com',
                'password'  => Hash::make('password'),
                'role'      => 'operator_produksi',
                'telepon'   => '081234567894',
                'is_active' => true,
                'cabangs'   => [
                    $cabangA?->id => ['is_default' => true],
                ],
            ],
            // Operator Produksi Cabang B
            [
                'name'      => 'Joko Operator B',
                'email'     => 'operator.b@berkahmulyo.com',
                'password'  => Hash::make('password'),
                'role'      => 'operator_produksi',
                'telepon'   => '081234567897',
                'is_active' => true,
                'cabangs'   => [
                    $cabangB?->id => ['is_default' => true],
                ],
            ],
        ];

        foreach ($users as $userData) {
            $cabangs = $userData['cabangs'];
            unset($userData['cabangs']);

            $user = User::create(array_merge($userData, ['email_verified_at' => now()]));

            // Assign cabang — filter null keys
            $validCabangs = array_filter($cabangs, fn($id) => !is_null($id), ARRAY_FILTER_USE_KEY);
            if (!empty($validCabangs)) {
                $user->cabangs()->attach($validCabangs);
            }
        }
    }
}
