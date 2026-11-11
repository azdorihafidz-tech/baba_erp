<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * SEMENTARA — data testing Batch 1 (Tahap 3+4), BUKAN bagian permanen dari
 * seeder pipeline (tidak didaftarkan di DatabaseSeeder). Dihapus setelah
 * verifikasi selesai kalau perlu, atau dibiarkan sebagai data awal dev lokal.
 */
class TestingBatch1SeederTemp extends Seeder
{
    public function run(): void
    {
        $gudang = Cabang::where('kode_cabang', 'GP001')->first();
        $outlet1 = Cabang::where('kode_cabang', 'OUT001')->first();

        // Kas untuk Gudang Pusat + Outlet 1, tunai+qris+transfer
        foreach ([$gudang, $outlet1] as $cabang) {
            foreach (['tunai', 'qris', 'transfer'] as $tipe) {
                Kas::updateOrCreate(
                    ['cabang_id' => $cabang->id, 'default_untuk' => $tipe],
                    [
                        'nama_kas' => 'Kas ' . ucfirst($tipe) . ' - ' . $cabang->nama_cabang,
                        'saldo_awal' => 0,
                        'saldo_sekarang' => 0,
                        'is_active' => true,
                    ]
                );
            }
        }
        $this->command?->info('Kas dibuat untuk Gudang Pusat + Outlet 1 (tunai/qris/transfer).');

        // Stok bahan baku di Gudang Pusat (banyak, buat di-transfer)
        $bahanKode = ['BB-BHN-001', 'BB-BHN-002', 'BB-BHN-003', 'BB-BHN-004', 'BB-BHN-005', 'BB-BHN-006', 'KM-KMS-001', 'KM-KMS-002', 'KM-KMS-003'];
        foreach ($bahanKode as $kode) {
            $item = Item::where('kode_item', $kode)->first();
            if (!$item) continue;

            DB::table('stocks')->updateOrInsert(
                ['item_id' => $item->id, 'lokasi_id' => $gudang->id],
                ['qty' => 1000, 'qty_minimum' => $item->qty_minimum ?? 0, 'updated_at' => now()]
            );
        }
        $this->command?->info('Stok 1000 unit tiap bahan baku/kemasan di Gudang Pusat.');

        // "Transfer" stok ke Outlet 1 — simulasi langsung tulis ke stocks (bukan lewat StockTransfer service, murni utk testing cepat)
        foreach ($bahanKode as $kode) {
            $item = Item::where('kode_item', $kode)->first();
            if (!$item) continue;

            DB::table('stocks')->updateOrInsert(
                ['item_id' => $item->id, 'lokasi_id' => $outlet1->id],
                ['qty' => 100, 'qty_minimum' => $item->qty_minimum ?? 0, 'updated_at' => now()]
            );
        }
        $this->command?->info('Stok 100 unit tiap bahan baku/kemasan di Outlet 1 (simulasi hasil transfer).');

        // Sengaja SISAKAN 1 bahan (Isian Udang) TANPA stok di Outlet 1 — utk test "stok habis"
        $isianUdang = Item::where('kode_item', 'BB-BHN-003')->first();
        if ($isianUdang) {
            DB::table('stocks')->updateOrInsert(
                ['item_id' => $isianUdang->id, 'lokasi_id' => $outlet1->id],
                ['qty' => 0, 'qty_minimum' => 0, 'updated_at' => now()]
            );
            $this->command?->info('Isian Udang SENGAJA di-set 0 stok di Outlet 1 (utk test produk Dimsum Udang "habis").');
        }
    }
}
