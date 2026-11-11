<?php

namespace Database\Seeders;

use App\Models\Tooltip;
use Illuminate\Database\Seeder;

class TooltipAdjustmentSeeder extends Seeder
{
    public function run(): void
    {
        $tooltips = [
            [
                'key'     => 'adjustment.lokasi',
                'title'   => 'Lokasi',
                'content' => 'Cabang tempat melakukan audit stok fisik.',
                'urutan'  => 1,
            ],
            [
                'key'     => 'adjustment.item',
                'title'   => 'Item',
                'content' => 'Pilih barang yang akan disesuaikan stoknya.',
                'urutan'  => 2,
            ],
            [
                'key'     => 'adjustment.stok_saat_ini',
                'title'   => 'Stok di Sistem',
                'content' => 'Total stok yang tercatat di sistem (jumlah semua batch FIFO).',
                'urutan'  => 3,
            ],
            [
                'key'     => 'adjustment.qty_fisik',
                'title'   => 'Qty Stok Fisik',
                'content' => 'Hasil hitung manual saat audit fisik di gudang. Sistem akan membandingkan dengan stok di sistem.',
                'urutan'  => 4,
            ],
            [
                'key'     => 'adjustment.distribusi_turun',
                'title'   => 'Distribusi Batch',
                'content' => 'FIFO Otomatis: sistem ambil dari batch tertua. Manual: pilih sendiri batch mana yang berkurang.',
                'urutan'  => 5,
            ],
            [
                'key'     => 'adjustment.distribusi_naik',
                'title'   => 'Distribusi Batch',
                'content' => 'Batch Baru: bikin batch baru dengan harga sendiri. Batch Existing: tambah qty ke batch yang sudah ada.',
                'urutan'  => 6,
            ],
            [
                'key'     => 'adjustment.alasan',
                'title'   => 'Alasan',
                'content' => 'Pilih alasan adjustment — penting untuk audit trail & laporan kerugian.',
                'urutan'  => 7,
            ],
            [
                'key'     => 'adjustment.catatan',
                'title'   => 'Catatan',
                'content' => 'Detail tambahan opsional, mis. nomor BA Audit, lokasi rak yang susut.',
                'urutan'  => 8,
            ],
            [
                'key'     => 'adjustment.batch_stok',
                'title'   => 'Batch Stok Aktif',
                'content' => 'Daftar batch FIFO yang masih punya sisa. Urutan dari tertua (warna kuning = next consume).',
                'urutan'  => 9,
            ],
        ];

        foreach ($tooltips as $data) {
            Tooltip::firstOrCreate(
                ['key' => $data['key']],
                array_merge($data, ['modul' => 'adjustment', 'aktif' => true])
            );
        }

        $this->command->info('TooltipAdjustmentSeeder: ' . count($tooltips) . ' tooltip adjustment selesai.');
    }
}
