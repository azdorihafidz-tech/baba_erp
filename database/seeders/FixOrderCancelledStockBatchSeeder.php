<?php

namespace Database\Seeders;

use App\Enums\TipeStockMovement;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data fix generic untuk order dibatalkan yang kena bug lama
 * PenjualanService::batalkan() (sebelum fix restore-stock_batches-dengan-
 * harga-persis ada) — batalkan() versi lama memanggil StokService::masuk()
 * tanpa $hargaBeli, jadi stocks.qty naik lagi tapi stock_batches.qty_sisa
 * TIDAK PERNAH dipulihkan. Seeder ini HANYA membereskan data yang SUDAH
 * terlanjur salah, bukan mengubah logic kalkulasi (itu sudah diperbaiki
 * terpisah di PenjualanService::batalkan() itu sendiri).
 *
 * Auto-detect SEMUA order berstatus dibatalkan (tidak hardcode ke order
 * tertentu) yang punya stock_movement 'keluar' tapi belum ada StockBatch
 * pasangan referensi_type=order+referensi_id+item_id — generic-loop, sama
 * pola dengan FixAsetDepresiasiSeeder. Idempotent — aman dijalankan
 * berkali-kali, order yang sudah benar (baik lewat batalkan() versi baru,
 * maupun sudah pernah dikoreksi seeder ini sebelumnya) otomatis di-skip.
 */
class FixOrderCancelledStockBatchSeeder extends Seeder
{
    public function run(): void
    {
        $orders = Order::withTrashed()->where('status', 'dibatalkan')->orderBy('id')->get();
        $jumlahDiperbaiki = 0;
        $totalNilaiDikoreksi = 0.0;

        foreach ($orders as $order) {
            $keluarPerItem = StockMovement::where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->where('tipe', TipeStockMovement::Keluar->value)
                ->get()
                ->groupBy('item_id');

            if ($keluarPerItem->isEmpty()) {
                continue;
            }

            foreach ($keluarPerItem as $itemId => $movements) {
                $sudahDireStore = StockBatch::withTrashed()
                    ->where('item_id', $itemId)
                    ->where('referensi_type', 'order')
                    ->where('referensi_id', $order->id)
                    ->exists();

                if ($sudahDireStore) {
                    continue;
                }

                $totalQty = (float) $movements->sum('qty');
                $lokasiId = $movements->first()->lokasi_asal_id ?? $order->cabang_id;
                if (!$lokasiId || $totalQty <= 0) {
                    continue;
                }

                // Harga = HPP yang BENAR-BENAR tercatat saat penjualan asli
                // (order_items.hpp / qty), bukan harga batch aktif sekarang
                // atau harga_beli_terakhir generik — sama prinsip dengan fix
                // di PenjualanService::batalkan().
                $orderItem = OrderItem::where('order_id', $order->id)->where('item_id', $itemId)->first();
                $hargaBeli = ($orderItem && (float) $orderItem->qty > 0)
                    ? (float) $orderItem->hpp / (float) $orderItem->qty
                    : (float) (Item::find($itemId)?->harga_beli_terakhir ?? 0);

                if ($hargaBeli <= 0) {
                    continue; // tidak ada dasar harga yang valid, skip drpd bikin batch senilai 0
                }

                DB::transaction(function () use ($itemId, $lokasiId, $totalQty, $order, $hargaBeli) {
                    StockBatch::create([
                        'item_id'             => $itemId,
                        'lokasi_id'           => $lokasiId,
                        'referensi_type'      => 'order',
                        'referensi_id'        => $order->id,
                        'qty_awal'            => $totalQty,
                        'qty_sisa'            => $totalQty,
                        'harga_beli_per_unit' => $hargaBeli,
                        // Tanggal historis (saat order dibatalkan), bukan hari ini —
                        // supaya urutan FIFO ke depan benar (stok ini sudah tersedia
                        // sejak tanggal itu, bukan baru masuk hari seeder dijalankan).
                        'tanggal_masuk'       => $order->updated_at?->toDateString() ?? today(),
                    ]);
                });

                $nilai = $totalQty * $hargaBeli;
                $totalNilaiDikoreksi += $nilai;
                $jumlahDiperbaiki++;

                $nama = Item::find($itemId)?->nama_item ?? "item #{$itemId}";
                $pesan = "  [Fix Order Dibatalkan] Order #{$order->id} ({$order->nomor_order}): "
                    . "{$nama} qty {$totalQty} @ Rp" . number_format($hargaBeli, 2)
                    . ' = Rp' . number_format($nilai, 2) . ' dikoreksi (batch baru dibuat)';
                $this->command?->info($pesan);
                Log::info('FixOrderCancelledStockBatchSeeder: ' . $pesan);
            }
        }

        if ($jumlahDiperbaiki === 0) {
            $this->command?->info('  [Fix Order Dibatalkan] Semua order dibatalkan sudah benar, tidak ada aksi.');
        } else {
            $this->command?->info(
                "  [Fix Order Dibatalkan] TOTAL: {$jumlahDiperbaiki} item dikoreksi, total nilai Rp"
                . number_format($totalNilaiDikoreksi, 2)
            );
        }
    }
}
