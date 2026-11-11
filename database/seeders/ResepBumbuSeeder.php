<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\JenisOlahan;
use App\Models\ResepBumbu;
use Illuminate\Database\Seeder;

/**
 * Tahap 4 D'mentai (2026-09-13) — resep produksi dimsum/gyoza, menggantikan
 * data Berkah Mulyo (Bakso Kojek/Kuah/Bakar, sudah dihapus di Tahap 2).
 *
 * Beda penting dari resep lama: setiap resep di sini terhubung LANGSUNG ke
 * 1 Item produk jadi (`resep_bumbu.item_id`, kolom baru Tahap 3) — supaya
 * POS bisa otomatis menemukan & memotong stok komposisinya tanpa kasir
 * pilih resep manual (beda dari alur jasa giling lama yang manual-pilih).
 *
 * Takaran (`qty_per_unit`, sudah di-rename dari `qty_per_kg`) sekarang
 * berarti "per 1 unit produksi" (1 porsi/pcs produk jadi), BUKAN lagi
 * "per 1 kg gilingan".
 */
class ResepBumbuSeeder extends Seeder
{
    public function run(): void
    {
        $jenisDimsumId = JenisOlahan::where('slug', 'dimsum')->value('id');
        $jenisGyozaId  = JenisOlahan::where('slug', 'gyoza')->value('id');

        $resepList = [
            [
                'nama' => 'Resep Dimsum Mentai',
                'kode' => 'DIM-MENTAI',
                'produk_item_kode' => 'PJ-DIM-003', // Dimsum Mentai
                'jenis_olahan_id'  => $jenisDimsumId,
                'bahan' => [
                    ['kode_item' => 'BB-BHN-001', 'qty_per_unit' => 1,  'satuan' => 'pcs'], // Kulit Dimsum
                    ['kode_item' => 'BB-BHN-002', 'qty_per_unit' => 30, 'satuan' => 'g'],   // Isian Ayam
                    ['kode_item' => 'BB-BHN-004', 'qty_per_unit' => 5,  'satuan' => 'g'],   // Mayo Mentai
                    ['kode_item' => 'KM-KMS-001', 'qty_per_unit' => 1,  'satuan' => 'pcs'], // Kotak Dimsum
                    ['kode_item' => 'KM-KMS-002', 'qty_per_unit' => 1,  'satuan' => 'pcs'], // Sumpit
                ],
            ],
            [
                'nama' => 'Resep Dimsum Ayam',
                'kode' => 'DIM-AYAM',
                'produk_item_kode' => 'PJ-DIM-001', // Dimsum Ayam
                'jenis_olahan_id'  => $jenisDimsumId,
                'bahan' => [
                    ['kode_item' => 'BB-BHN-001', 'qty_per_unit' => 1,  'satuan' => 'pcs'], // Kulit Dimsum
                    ['kode_item' => 'BB-BHN-002', 'qty_per_unit' => 35, 'satuan' => 'g'],   // Isian Ayam
                    ['kode_item' => 'KM-KMS-001', 'qty_per_unit' => 1,  'satuan' => 'pcs'], // Kotak Dimsum
                    ['kode_item' => 'KM-KMS-002', 'qty_per_unit' => 1,  'satuan' => 'pcs'], // Sumpit
                ],
            ],
            [
                'nama' => 'Resep Gyoza Original',
                'kode' => 'GYZ-ORI',
                'produk_item_kode' => 'PJ-GYZ-001', // Gyoza Original
                'jenis_olahan_id'  => $jenisGyozaId,
                'bahan' => [
                    ['kode_item' => 'BB-BHN-002', 'qty_per_unit' => 25, 'satuan' => 'g'],  // Isian Ayam
                    ['kode_item' => 'BB-BHN-005', 'qty_per_unit' => 5,  'satuan' => 'ml'], // Saus Gyoza
                    ['kode_item' => 'BB-BHN-006', 'qty_per_unit' => 3,  'satuan' => 'ml'], // Minyak Goreng
                    ['kode_item' => 'KM-KMS-001', 'qty_per_unit' => 1,  'satuan' => 'pcs'], // Kotak Dimsum
                ],
            ],
        ];

        foreach ($resepList as $resepData) {
            $produkItem = Item::where('kode_item', $resepData['produk_item_kode'])->first();

            if (! $produkItem) {
                $this->command?->warn("  [{$resepData['nama']}] Produk '{$resepData['produk_item_kode']}' tidak ditemukan, di-skip.");
                continue;
            }

            $resep = ResepBumbu::updateOrCreate(
                ['kode' => $resepData['kode']],
                [
                    'nama'            => $resepData['nama'],
                    'item_id'         => $produkItem->id,
                    'jenis_olahan_id' => $resepData['jenis_olahan_id'],
                    'is_active'       => true,
                    'catatan'         => 'Data starting point dari seeder — silakan disesuaikan Owner.',
                ]
            );

            foreach ($resepData['bahan'] as $i => $bahan) {
                $item = Item::where('kode_item', $bahan['kode_item'])->first();

                if (! $item) {
                    $this->command?->warn("  [{$resepData['nama']}] Bahan '{$bahan['kode_item']}' tidak ditemukan di master, di-skip.");
                    continue;
                }

                $resep->items()->updateOrCreate(
                    ['item_id' => $item->id],
                    [
                        'qty_per_unit' => $bahan['qty_per_unit'],
                        'satuan'       => $bahan['satuan'],
                        'is_wajib'     => true,
                        'mode_harga'   => 'gratis',
                        'urutan'       => $i + 1,
                    ]
                );
            }
        }

        $this->command?->info('ResepBumbuSeeder selesai: Resep Dimsum Mentai, Dimsum Ayam, Gyoza Original di-seed/verified.');
    }
}
