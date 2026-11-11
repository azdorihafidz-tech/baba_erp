<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    /**
     * Kategori D'mentai (Tahap 2 - Master Data, 2026-09-13) — menggantikan
     * kategori Berkah Mulyo (Daging/Bumbu/Bakso/dst, sudah dihapus via
     * CleanupBerkahMulyoDataSeeder).
     */
    public function run(): void
    {
        $categories = [
            ['kode_kategori' => 'DIM', 'nama_kategori' => 'Dimsum',        'deskripsi' => 'Produk jadi dimsum (kukus/goreng)'],
            ['kode_kategori' => 'GYZ', 'nama_kategori' => 'Gyoza',         'deskripsi' => 'Produk jadi gyoza'],
            ['kode_kategori' => 'MNM', 'nama_kategori' => 'Minuman',       'deskripsi' => 'Minuman kemasan/racikan'],
            ['kode_kategori' => 'FRZ', 'nama_kategori' => 'Frozen',        'deskripsi' => 'Produk beku siap masak (frozen pack)'],
            ['kode_kategori' => 'BHN', 'nama_kategori' => 'Bahan Baku',    'deskripsi' => 'Bahan baku produksi dimsum & gyoza'],
            ['kode_kategori' => 'KMS', 'nama_kategori' => 'Kemasan',       'deskripsi' => 'Kotak, sumpit, plastik kemasan'],
            ['kode_kategori' => 'TMB', 'nama_kategori' => 'Item Tambahan', 'deskripsi' => 'Garpu, saus extra, dan add-on lain'],
        ];

        foreach ($categories as $cat) {
            ItemCategory::updateOrCreate(['kode_kategori' => $cat['kode_kategori']], $cat);
        }
    }
}
