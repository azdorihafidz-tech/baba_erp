<?php

namespace Database\Seeders;

use App\Models\JenisOlahan;
use Illuminate\Database\Seeder;

class JenisOlahanSeeder extends Seeder
{
    /**
     * Tahap 4 D'mentai (2026-09-13) — menggantikan data Berkah Mulyo
     * (Bakso/Sosis/Tempura, sudah dihapus di Tahap 2 via
     * CleanupBerkahMulyoDataSeeder). Dipakai buat pengelompokan resep
     * produksi (resep_bumbu.jenis_olahan_id), bukan lagi utk dropdown
     * "Jenis Olahan" POS lama (yang sudah retired bareng jasa_giling).
     */
    public function run(): void
    {
        $data = [
            ['nama' => 'Dimsum', 'slug' => 'dimsum', 'is_active' => true],
            ['nama' => 'Gyoza',  'slug' => 'gyoza',  'is_active' => true],
        ];

        foreach ($data as $row) {
            JenisOlahan::updateOrCreate(
                ['slug' => $row['slug']],
                $row
            );
        }
    }
}
