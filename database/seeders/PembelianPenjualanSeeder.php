<?php

namespace Database\Seeders;

use App\Enums\StatusOrder;
use App\Enums\StatusPurchaseOrder;
use App\Enums\TipeOrder;
use App\Enums\TipePembayaran;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pelanggan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class PembelianPenjualanSeeder extends Seeder
{
    public function run(): void
    {
        // ===== SUPPLIER =====
        $suppliers = [
            [
                'kode_supplier' => 'SUP-001',
                'nama_supplier' => 'PT Daging Segar Nusantara',
                'kontak_person' => 'Budi Santoso',
                'telepon'       => '0812-1111-2222',
                'kota'          => 'Surabaya',
                'is_active'     => true,
            ],
            [
                'kode_supplier' => 'SUP-002',
                'nama_supplier' => 'UD Tepung Makmur',
                'kontak_person' => 'Siti Aminah',
                'telepon'       => '0813-3333-4444',
                'kota'          => 'Malang',
                'is_active'     => true,
            ],
            [
                'kode_supplier' => 'SUP-003',
                'nama_supplier' => 'CV Bumbu Berkah',
                'kontak_person' => 'Ahmad Fauzi',
                'telepon'       => '0815-5555-6666',
                'kota'          => 'Sidoarjo',
                'is_active'     => true,
            ],
        ];

        foreach ($suppliers as $sup) {
            Supplier::firstOrCreate(['kode_supplier' => $sup['kode_supplier']], $sup);
        }

        // ===== PELANGGAN =====
        $pelanggans = [
            [
                'kode_pelanggan' => 'PLG-001',
                'nama_pelanggan' => 'Warung Makan Bu Sari',
                'telepon'        => '0812-9999-1111',
                'kota'           => 'Surabaya',
                'is_active'      => true,
            ],
            [
                'kode_pelanggan' => 'PLG-002',
                'nama_pelanggan' => 'Restoran Padang Minang',
                'telepon'        => '0813-8888-2222',
                'kota'           => 'Surabaya',
                'is_active'      => true,
            ],
            [
                'kode_pelanggan' => 'PLG-003',
                'nama_pelanggan' => 'Toko Bakso Pak Joko',
                'telepon'        => '0811-7777-3333',
                'kota'           => 'Sidoarjo',
                'is_active'      => true,
            ],
        ];

        foreach ($pelanggans as $p) {
            Pelanggan::firstOrCreate(['kode_pelanggan' => $p['kode_pelanggan']], $p);
        }

        // ===== PURCHASE ORDERS =====
        $gudangPusat = Cabang::where('tipe', 'gudang_pusat')->first();
        $cabangA     = Cabang::where('tipe', 'cabang')->first();
        $supplierA   = Supplier::where('kode_supplier', 'SUP-001')->first();
        $supplierB   = Supplier::where('kode_supplier', 'SUP-002')->first();

        $itemDagingSapi  = Item::where('kode_item', 'BB-DGN-001')->first();
        $itemDagingAyam  = Item::where('kode_item', 'BB-DGN-002')->first();
        $itemTepung      = Item::where('kode_item', 'BB-TPG-001')->first();

        $adminUser = User::first();

        if ($gudangPusat && $supplierA && $itemDagingSapi && $itemDagingAyam) {
            $po1 = PurchaseOrder::firstOrCreate(
                ['nomor_po' => 'PO-' . now()->subDays(15)->format('Ymd') . '-001'],
                [
                    'cabang_id'          => $gudangPusat->id,
                    'supplier_id'        => $supplierA->id,
                    'nomor_po'           => 'PO-' . now()->subDays(15)->format('Ymd') . '-001',
                    'tanggal_po'         => now()->subDays(15)->toDateString(),
                    'tanggal_terima'     => now()->subDays(13)->toDateString(),
                    'status'             => StatusPurchaseOrder::Diterima,
                    'pembelian_langsung' => false,
                    'total_harga'        => (100 * 120000) + (50 * 35000),
                    'created_by'         => $adminUser?->id,
                    'approved_by'        => $adminUser?->id,
                    'approved_at'        => now()->subDays(14),
                ]
            );

            if ($po1->wasRecentlyCreated) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po1->id,
                    'item_id'           => $itemDagingSapi->id,
                    'qty_pesan'         => 100,
                    'qty_terima'        => 100,
                    'harga_satuan'      => 120000,
                    'total_harga'       => 12000000,
                ]);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po1->id,
                    'item_id'           => $itemDagingAyam->id,
                    'qty_pesan'         => 50,
                    'qty_terima'        => 50,
                    'harga_satuan'      => 35000,
                    'total_harga'       => 1750000,
                ]);
            }
        }

        if ($cabangA && $supplierB && $itemTepung) {
            $po2 = PurchaseOrder::firstOrCreate(
                ['nomor_po' => 'PO-' . now()->subDays(3)->format('Ymd') . '-001'],
                [
                    'cabang_id'          => $cabangA->id,
                    'supplier_id'        => $supplierB->id,
                    'nomor_po'           => 'PO-' . now()->subDays(3)->format('Ymd') . '-001',
                    'tanggal_po'         => now()->subDays(3)->toDateString(),
                    'status'             => StatusPurchaseOrder::Disetujui,
                    'pembelian_langsung' => true,
                    'alasan_langsung'    => 'Stok tepung mendesak habis',
                    'total_harga'        => 50 * 12000,
                    'created_by'         => $adminUser?->id,
                    'approved_by'        => $adminUser?->id,
                    'approved_at'        => now()->subDays(2),
                ]
            );

            if ($po2->wasRecentlyCreated) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po2->id,
                    'item_id'           => $itemTepung->id,
                    'qty_pesan'         => 50,
                    'harga_satuan'      => 12000,
                    'total_harga'       => 600000,
                ]);
            }
        }

        // ===== ORDERS =====
        $pelangganBuSari = Pelanggan::where('kode_pelanggan', 'PLG-001')->first();
        $itemBakso       = Item::where('kode_item', 'PJ-BKS-001')->first();
        $itemSosis       = Item::where('kode_item', 'PJ-SOS-001')->first();

        if ($cabangA) {
            // Order jasa giling
            $order1 = Order::firstOrCreate(
                ['nomor_order' => 'ORD-' . now()->subDays(5)->format('Ymd') . '-001'],
                [
                    'cabang_id'      => $cabangA->id,
                    'nomor_order'    => 'ORD-' . now()->subDays(5)->format('Ymd') . '-001',
                    'tanggal_order'  => now()->subDays(5)->toDateString(),
                    'tipe_order'     => TipeOrder::JasaGiling,
                    'pelanggan_id'   => $pelangganBuSari?->id,
                    'nama_pelanggan' => $pelangganBuSari?->nama_pelanggan ?? 'Warung Makan Bu Sari',
                    'telepon_pelanggan' => $pelangganBuSari?->telepon,
                    'total_harga'    => 10 * 15000,
                    'diskon'         => 0,
                    'total_bayar'    => 10 * 15000,
                    'jumlah_bayar'   => 10 * 15000,
                    'kembalian'      => 0,
                    'tipe_pembayaran' => TipePembayaran::Tunai,
                    'status'         => StatusOrder::Selesai,
                    'kasir_id'       => $adminUser?->id,
                ]
            );

            if ($order1->wasRecentlyCreated) {
                OrderItem::create([
                    'order_id'     => $order1->id,
                    'item_id'      => null,
                    'nama_item'    => 'Jasa Giling Bakso',
                    'qty'          => 10,
                    'satuan'       => 'kg',
                    'harga_satuan' => 15000,
                    'total_harga'  => 150000,
                    'berat_daging' => 10,
                    'jenis_olahan' => 'bakso',
                ]);
            }

            // Order produk jadi
            if ($itemBakso && $itemSosis) {
                $totalBakso = 5 * 55000;
                $totalSosis = 3 * 45000;
                $order2 = Order::firstOrCreate(
                    ['nomor_order' => 'ORD-' . now()->subDays(2)->format('Ymd') . '-001'],
                    [
                        'cabang_id'      => $cabangA->id,
                        'nomor_order'    => 'ORD-' . now()->subDays(2)->format('Ymd') . '-001',
                        'tanggal_order'  => now()->subDays(2)->toDateString(),
                        'tipe_order'     => TipeOrder::ProdukJadi,
                        'nama_pelanggan' => 'Pembeli Umum',
                        'total_harga'    => $totalBakso + $totalSosis,
                        'diskon'         => 0,
                        'total_bayar'    => $totalBakso + $totalSosis,
                        'jumlah_bayar'   => $totalBakso + $totalSosis,
                        'kembalian'      => 0,
                        'tipe_pembayaran' => TipePembayaran::Tunai,
                        'status'         => StatusOrder::Selesai,
                        'kasir_id'       => $adminUser?->id,
                    ]
                );

                if ($order2->wasRecentlyCreated) {
                    OrderItem::create([
                        'order_id'     => $order2->id,
                        'item_id'      => $itemBakso->id,
                        'nama_item'    => $itemBakso->nama_item,
                        'qty'          => 5,
                        'satuan'       => $itemBakso->satuan,
                        'harga_satuan' => 55000,
                        'total_harga'  => $totalBakso,
                    ]);
                    OrderItem::create([
                        'order_id'     => $order2->id,
                        'item_id'      => $itemSosis->id,
                        'nama_item'    => $itemSosis->nama_item,
                        'qty'          => 3,
                        'satuan'       => $itemSosis->satuan,
                        'harga_satuan' => 45000,
                        'total_harga'  => $totalSosis,
                    ]);
                }
            }
        }
    }
}
