<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssetCategory;

class AssetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['kode_kategori' => 'MESIN', 'nama_kategori' => 'Mesin Produksi', 'deskripsi' => 'Mesin giling, mixer, dan alat produksi'],
            ['kode_kategori' => 'KEND',  'nama_kategori' => 'Kendaraan',       'deskripsi' => 'Mobil, motor operasional'],
            ['kode_kategori' => 'ELEK',  'nama_kategori' => 'Elektronik',      'deskripsi' => 'Komputer, printer, perangkat elektronik'],
            ['kode_kategori' => 'FRZE',  'nama_kategori' => 'Pendingin',       'deskripsi' => 'Freezer, kulkas, showcase'],
            ['kode_kategori' => 'FURN',  'nama_kategori' => 'Furniture',       'deskripsi' => 'Meja, kursi, lemari'],
            ['kode_kategori' => 'BNGN',  'nama_kategori' => 'Bangunan',        'deskripsi' => 'Gedung, renovasi bangunan'],
            ['kode_kategori' => 'PERL',  'nama_kategori' => 'Peralatan Dapur', 'deskripsi' => 'Wajan, pisau, peralatan masak'],
            ['kode_kategori' => 'LAIN',  'nama_kategori' => 'Lainnya',         'deskripsi' => 'Aset lain-lain'],
        ];

        foreach ($categories as $cat) {
            AssetCategory::firstOrCreate(['kode_kategori' => $cat['kode_kategori']], $cat);
        }
    }
}
