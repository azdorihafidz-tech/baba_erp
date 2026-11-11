<?php

namespace Database\Seeders;

use App\Models\EvaluationAspect;
use Illuminate\Database\Seeder;

class EvaluationAspectSeeder extends Seeder
{
    public function run(): void
    {
        $aspects = [
            [
                'nama_aspek' => 'Kedisiplinan',
                'deskripsi' => 'Kehadiran, ketepatan waktu, kepatuhan aturan kerja dan SOP perusahaan',
                'bobot_persen' => 25.00,
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'nama_aspek' => 'Kinerja / Produktivitas',
                'deskripsi' => 'Hasil kerja, kecepatan, kualitas output produksi, dan pencapaian target',
                'bobot_persen' => 30.00,
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'nama_aspek' => 'Kerjasama Tim',
                'deskripsi' => 'Komunikasi, kolaborasi dengan rekan, membantu sesama, dan sikap positif',
                'bobot_persen' => 20.00,
                'urutan' => 3,
                'is_active' => true,
            ],
            [
                'nama_aspek' => 'Kebersihan & Kerapian',
                'deskripsi' => 'Kebersihan area kerja, kerapian diri, dan hygiene dalam proses produksi',
                'bobot_persen' => 10.00,
                'urutan' => 4,
                'is_active' => true,
            ],
            [
                'nama_aspek' => 'Inisiatif / Kemandirian',
                'deskripsi' => 'Proaktif dalam bekerja, problem solving mandiri, tidak selalu menunggu perintah',
                'bobot_persen' => 15.00,
                'urutan' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($aspects as $aspect) {
            EvaluationAspect::create($aspect);
        }
    }
}
