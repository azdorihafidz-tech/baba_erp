<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StokSeeder extends Seeder
{
    public function run(): void
    {
        $gudang  = Cabang::where('tipe', 'gudang_pusat')->first();
        $cabangA = Cabang::where('kode_cabang', 'CA001')->first();
        $cabangB = Cabang::where('kode_cabang', 'CB001')->first();
        $owner   = User::where('email', 'admin@berkahmulyo.com')->first();
        $manajerA = User::where('email', 'manajer.a@berkahmulyo.com')->first();

        $items = Item::all()->keyBy('kode_item');

        // ===== 1. STOK GUDANG PUSAT =====
        $stokGudang = [
            'BB-DGN-001' => ['qty' => 150,  'qty_min' => 20],
            'BB-DGN-002' => ['qty' => 80,   'qty_min' => 15],
            'BB-DGN-003' => ['qty' => 60,   'qty_min' => 10],
            'BB-TPG-001' => ['qty' => 300,  'qty_min' => 50],
            'BB-TPG-002' => ['qty' => 200,  'qty_min' => 30],
            'BB-BMB-001' => ['qty' => 45,   'qty_min' => 10],
            'BB-BMB-002' => ['qty' => 80,   'qty_min' => 10],
            'BB-BMB-003' => ['qty' => 15,   'qty_min' => 5],
            'BB-BMB-004' => ['qty' => 25,   'qty_min' => 5],
            'PJ-BKS-001' => ['qty' => 200,  'qty_min' => 30],
            'PJ-BKS-002' => ['qty' => 150,  'qty_min' => 30],
            'PJ-SOS-001' => ['qty' => 120,  'qty_min' => 20],
            'PJ-SOS-002' => ['qty' => 100,  'qty_min' => 20],
            'PJ-TMP-001' => ['qty' => 80,   'qty_min' => 15],
            'KM-PLT-001' => ['qty' => 2000000, 'qty_min' => 50000],
            'KM-KRT-001' => ['qty' => 5000,   'qty_min' => 500],
            'KM-LBL-001' => ['qty' => 2000000,'qty_min' => 50000],
        ];

        foreach ($stokGudang as $kode => $data) {
            if (!isset($items[$kode])) continue;
            Stock::updateOrCreate(
                ['item_id' => $items[$kode]->id, 'lokasi_id' => $gudang->id],
                ['qty' => $data['qty'], 'qty_minimum' => $data['qty_min'], 'updated_at' => now()]
            );
        }

        // ===== 2. STOK CABANG A =====
        $stokCabangA = [
            'BB-DGN-001' => ['qty' => 25,  'qty_min' => 10],
            'BB-DGN-002' => ['qty' => 15,  'qty_min' => 8],
            'BB-TPG-001' => ['qty' => 40,  'qty_min' => 20],
            'BB-BMB-001' => ['qty' => 5,   'qty_min' => 5],   // stok kritis
            'BB-BMB-002' => ['qty' => 10,  'qty_min' => 5],
            'PJ-BKS-001' => ['qty' => 30,  'qty_min' => 20],
            'PJ-BKS-002' => ['qty' => 25,  'qty_min' => 20],
            'PJ-SOS-001' => ['qty' => 8,   'qty_min' => 15],  // stok kritis
            'PJ-TMP-001' => ['qty' => 12,  'qty_min' => 10],
            'KM-PLT-001' => ['qty' => 300, 'qty_min' => 200],
        ];

        foreach ($stokCabangA as $kode => $data) {
            if (!isset($items[$kode])) continue;
            Stock::updateOrCreate(
                ['item_id' => $items[$kode]->id, 'lokasi_id' => $cabangA->id],
                ['qty' => $data['qty'], 'qty_minimum' => $data['qty_min'], 'updated_at' => now()]
            );
        }

        // ===== 3. STOK CABANG B =====
        $stokCabangB = [
            'BB-DGN-001' => ['qty' => 20,  'qty_min' => 10],
            'BB-TPG-001' => ['qty' => 35,  'qty_min' => 20],
            'BB-BMB-002' => ['qty' => 3,   'qty_min' => 5],   // stok kritis
            'PJ-BKS-001' => ['qty' => 45,  'qty_min' => 20],
            'PJ-SOS-002' => ['qty' => 20,  'qty_min' => 15],
            'PJ-TMP-001' => ['qty' => 5,   'qty_min' => 10],  // stok kritis
            'KM-PLT-001' => ['qty' => 150, 'qty_min' => 200], // stok kritis
        ];

        foreach ($stokCabangB as $kode => $data) {
            if (!isset($items[$kode])) continue;
            Stock::updateOrCreate(
                ['item_id' => $items[$kode]->id, 'lokasi_id' => $cabangB->id],
                ['qty' => $data['qty'], 'qty_minimum' => $data['qty_min'], 'updated_at' => now()]
            );
        }

        // ===== 4. STOCK MOVEMENTS (history) =====
        // Catat beberapa movement awal sebagai data history
        $movements = [
            // Gudang pusat dapat stok dari pembelian
            ['item_id' => $items['BB-DGN-001']->id, 'lokasi_tujuan_id' => $gudang->id, 'qty' => 150, 'tipe' => 'masuk', 'catatan' => 'Pembelian awal dari supplier', 'created_at' => now()->subDays(30)],
            ['item_id' => $items['BB-TPG-001']->id, 'lokasi_tujuan_id' => $gudang->id, 'qty' => 300, 'tipe' => 'masuk', 'catatan' => 'Pembelian awal dari supplier', 'created_at' => now()->subDays(30)],
            ['item_id' => $items['PJ-BKS-001']->id, 'lokasi_tujuan_id' => $gudang->id, 'qty' => 200, 'tipe' => 'masuk', 'catatan' => 'Produksi batch pertama', 'created_at' => now()->subDays(15)],
            // Transfer ke cabang A
            ['item_id' => $items['BB-DGN-001']->id, 'lokasi_asal_id' => $gudang->id, 'lokasi_tujuan_id' => $cabangA->id, 'qty' => 25, 'tipe' => 'transfer', 'catatan' => 'Transfer ke Cabang A', 'created_at' => now()->subDays(20)],
            ['item_id' => $items['PJ-BKS-001']->id, 'lokasi_asal_id' => $gudang->id, 'lokasi_tujuan_id' => $cabangA->id, 'qty' => 30, 'tipe' => 'transfer', 'catatan' => 'Transfer ke Cabang A', 'created_at' => now()->subDays(10)],
            // Transfer ke cabang B
            ['item_id' => $items['BB-DGN-001']->id, 'lokasi_asal_id' => $gudang->id, 'lokasi_tujuan_id' => $cabangB->id, 'qty' => 20, 'tipe' => 'transfer', 'catatan' => 'Transfer ke Cabang B', 'created_at' => now()->subDays(18)],
            // Penjualan dari cabang A
            ['item_id' => $items['PJ-BKS-001']->id, 'lokasi_asal_id' => $cabangA->id, 'qty' => 10, 'tipe' => 'keluar', 'catatan' => 'Penjualan', 'created_at' => now()->subDays(5)],
            ['item_id' => $items['PJ-SOS-001']->id, 'lokasi_asal_id' => $cabangA->id, 'qty' => 7, 'tipe' => 'keluar', 'catatan' => 'Penjualan', 'created_at' => now()->subDays(3)],
        ];

        foreach ($movements as $mov) {
            StockMovement::create(array_merge($mov, [
                'lokasi_asal_id'   => $mov['lokasi_asal_id'] ?? null,
                'lokasi_tujuan_id' => $mov['lokasi_tujuan_id'] ?? null,
                'user_id'          => $owner->id,
            ]));
        }

        // ===== 5. STOCK REQUEST (contoh permintaan) =====
        // Request dari Cabang A — status pending
        $req1 = StockRequest::create([
            'cabang_id'       => $cabangA->id,
            'nomor_request'   => 'REQ-' . date('Ymd') . '-001',
            'tanggal_request' => today()->subDays(2),
            'status'          => 'pending',
            'catatan'         => 'Stok bawang putih dan sosis menipis',
            'created_by'      => $manajerA->id,
        ]);
        StockRequestItem::insert([
            ['stock_request_id' => $req1->id, 'item_id' => $items['BB-BMB-001']->id, 'qty_diminta' => 10, 'catatan' => 'Butuh segera'],
            ['stock_request_id' => $req1->id, 'item_id' => $items['PJ-SOS-001']->id, 'qty_diminta' => 20, 'catatan' => null],
        ]);

        // Request dari Cabang A — status disetujui
        $req2 = StockRequest::create([
            'cabang_id'       => $cabangA->id,
            'nomor_request'   => 'REQ-' . date('Ymd', strtotime('-7 days')) . '-001',
            'tanggal_request' => today()->subDays(7),
            'status'          => 'disetujui',
            'approved_by'     => $owner->id,
            'approved_at'     => now()->subDays(6),
            'catatan'         => 'Permintaan rutin mingguan',
            'created_by'      => $manajerA->id,
        ]);
        StockRequestItem::insert([
            ['stock_request_id' => $req2->id, 'item_id' => $items['BB-DGN-001']->id, 'qty_diminta' => 30, 'qty_disetujui' => 25, 'catatan' => null],
            ['stock_request_id' => $req2->id, 'item_id' => $items['BB-TPG-001']->id, 'qty_diminta' => 50, 'qty_disetujui' => 50, 'catatan' => null],
        ]);

        // ===== 6. STOCK TRANSFER (contoh transfer) =====
        // Transfer dikirim dari gudang ke cabang A (sudah diterima)
        $tr1 = StockTransfer::create([
            'nomor_transfer'  => 'TRF-' . date('Ymd', strtotime('-14 days')) . '-001',
            'dari_lokasi_id'  => $gudang->id,
            'ke_lokasi_id'    => $cabangA->id,
            'tanggal_kirim'   => today()->subDays(14),
            'tanggal_terima'  => today()->subDays(13),
            'status'          => 'diterima',
            'catatan'         => 'Transfer rutin mingguan',
            'created_by'      => $owner->id,
            'received_by'     => $manajerA->id,
        ]);
        StockTransferItem::insert([
            ['stock_transfer_id' => $tr1->id, 'item_id' => $items['BB-DGN-001']->id, 'qty_kirim' => 25, 'qty_terima' => 25, 'catatan' => null],
            ['stock_transfer_id' => $tr1->id, 'item_id' => $items['PJ-BKS-001']->id, 'qty_kirim' => 30, 'qty_terima' => 30, 'catatan' => null],
        ]);

        // Transfer draft — belum dikirim (dari request req2)
        $tr2 = StockTransfer::create([
            'nomor_transfer'   => 'TRF-' . date('Ymd', strtotime('-5 days')) . '-001',
            'dari_lokasi_id'   => $gudang->id,
            'ke_lokasi_id'     => $cabangA->id,
            'tanggal_kirim'    => today()->subDays(5),
            'status'           => 'draft',
            'stock_request_id' => $req2->id,
            'catatan'          => 'Dibuat dari permintaan ' . $req2->nomor_request,
            'created_by'       => $owner->id,
        ]);
        StockTransferItem::insert([
            ['stock_transfer_id' => $tr2->id, 'item_id' => $items['BB-DGN-001']->id, 'qty_kirim' => 25, 'qty_terima' => null, 'catatan' => null],
            ['stock_transfer_id' => $tr2->id, 'item_id' => $items['BB-TPG-001']->id, 'qty_kirim' => 50, 'qty_terima' => null, 'catatan' => null],
        ]);

        $this->command->info('StokSeeder selesai: ' . Stock::count() . ' stok, ' . StockRequest::count() . ' request, ' . StockTransfer::count() . ' transfer.');
    }
}
