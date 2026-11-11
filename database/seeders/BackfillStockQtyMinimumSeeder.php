<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data migration SEKALI JALAN: backfill stocks.qty_minimum (threshold per
 * lokasi) dari items.qty_minimum (threshold global) untuk baris stocks lama
 * yang qty_minimum-nya masih 0 — supaya alert/notifikasi stok minimum yang
 * sudah ada (StokMinimumNotification) langsung aktif untuk data existing,
 * konsisten dengan fix di StokService::updateStok() untuk data baru.
 *
 * IDEMPOTENT: guard `stocks.qty_minimum = 0` — baris yang qty_minimum-nya
 * SUDAH di-set manual oleh user (lewat "Set Minimum" atau backfill
 * sebelumnya) ke nilai > 0 TIDAK PERNAH ditimpa. Jalan berkali-kali hasilnya
 * sama, aman.
 *
 * Pakai raw DB::table()->update() (bukan Eloquent) supaya TIDAK memicu
 * StockObserver — backfill massal ini bukan aksi user, jadi sengaja tidak
 * dicatat ke stock_histories (beda dengan setMinimum() di StokController
 * yang memang aksi user dan harus punya audit trail).
 */
class BackfillStockQtyMinimumSeeder extends Seeder
{
    public function run(): void
    {
        $totalStocks = DB::table('stocks')->count();
        $zeroBefore  = DB::table('stocks')->where('qty_minimum', 0)->count();

        $this->command->info('=== Backfill stocks.qty_minimum dari items.qty_minimum ===');
        $this->command->info("Total baris stocks           : {$totalStocks}");
        $this->command->info("qty_minimum = 0 sebelum      : {$zeroBefore}");

        $updated = DB::table('stocks')
            ->join('items', 'stocks.item_id', '=', 'items.id')
            ->where('stocks.qty_minimum', 0)
            ->where('items.qty_minimum', '>', 0)
            ->whereNull('stocks.deleted_at')
            ->whereNull('items.deleted_at')
            ->update(['stocks.qty_minimum' => DB::raw('items.qty_minimum')]);

        $zeroAfter = DB::table('stocks')->where('qty_minimum', 0)->count();

        $this->command->info("Baris ter-update sesi ini    : {$updated}");
        $this->command->info("qty_minimum = 0 sesudah      : {$zeroAfter}");

        if ($zeroAfter > 0) {
            $this->command->warn("Sisa {$zeroAfter} baris tetap qty_minimum=0 — item master-nya juga belum punya qty_minimum (items.qty_minimum=0). Set manual lewat menu Stok > Set Minimum kalau perlu.");
        }
    }
}
