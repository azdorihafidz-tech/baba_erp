<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Harga bahan untuk komposisi resep (CLAUDE.md 4.39, keputusan Owner):
 * 1. Stok masih ada → harga batch FIFO TERTUA yang masih bersisa di lokasi
 *    pemilik bahan (harga yang terpakai pada penjualan berikutnya).
 * 2. Stok habis     → harga PO terakhir (ditandai "stok habis").
 * 3. Belum pernah PO → Harga Standar.
 * Lokasi = cabang pemilik bahan (data terpisah per cabang); bahan lama yang
 * belum dipecah (cabang_id NULL) memakai $lokasiDefault.
 */
class HargaResepService
{
    /**
     * @param Collection<int, Item>|array<int, Item> $items
     * @return array<int, array{harga: float, sumber: string, info: string, habis: bool}>
     */
    public function untuk($items, ?int $lokasiDefault = null): array
    {
        $items = collect($items)->filter()->unique('id');
        if ($items->isEmpty()) {
            return [];
        }

        $pasangan = $items->mapWithKeys(fn (Item $i) => [$i->id => $i->cabang_id ?? $lokasiDefault]);
        $batches = DB::table('stock_batches')
            ->whereIn('item_id', $pasangan->keys())
            ->where('qty_sisa', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('tanggal_masuk')->orderBy('id')
            ->get(['item_id', 'lokasi_id', 'qty_sisa', 'harga_beli_per_unit', 'tanggal_masuk'])
            ->groupBy('item_id');

        $hasil = [];
        foreach ($items as $item) {
            $lokasi = $pasangan[$item->id];
            $batch = $lokasi ? ($batches[$item->id] ?? collect())->firstWhere('lokasi_id', $lokasi) : null;

            if ($batch) {
                $hasil[$item->id] = [
                    'harga' => (float) $batch->harga_beli_per_unit,
                    'sumber' => 'fifo',
                    'info' => 'FIFO · batch ' . \Carbon\Carbon::parse($batch->tanggal_masuk)->format('d/m') . ' (sisa ' . fmt_qty($batch->qty_sisa) . ')',
                    'habis' => false,
                ];
            } elseif ($item->harga_po_terakhir !== null) {
                $hasil[$item->id] = ['harga' => (float) $item->harga_po_terakhir, 'sumber' => 'po_terakhir', 'info' => 'stok habis · harga PO terakhir', 'habis' => true];
            } else {
                $hasil[$item->id] = ['harga' => (float) ($item->harga_beli_terakhir ?? 0), 'sumber' => 'standar', 'info' => 'stok habis · Harga Standar', 'habis' => true];
            }
        }

        return $hasil;
    }

    public function satu(Item $item, ?int $lokasiDefault = null): array
    {
        return $this->untuk([$item], $lokasiDefault)[$item->id];
    }
}
