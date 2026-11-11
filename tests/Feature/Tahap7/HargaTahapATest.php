<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCabang;
use App\Models\ItemVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Services\PenjualanService;
use App\Services\PurchaseOrderService;
use App\Services\StokService;
use Database\Seeders\Perbaikan438PanduanSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Perbaikan Harga Tahap A (CLAUDE.md 4.38, 2026-10-18): Harga Standar tidak
 * ditimpa PO, Harga PO Terakhir, harga beli per outlet, form PO pack, harga
 * varian per outlet, modal FIFO transfer, nilai stok dashboard FIFO.
 */
class HargaTahapATest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private int $seq = 0;

    private function cabang(string $nama, string $tipe = 'cabang'): Cabang
    {
        return Cabang::create(['nama_cabang' => $nama, 'kode_cabang' => 'HTA' . (++$this->seq) . rand(100, 999), 'tipe' => $tipe, 'is_active' => true]);
    }

    private function bahan(float $hargaStandar = 100, array $extra = []): Item
    {
        return Item::create(array_merge([
            'kode_item' => 'HTA-' . uniqid(), 'nama_item' => 'Teh Test', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => $hargaStandar, 'is_active' => true, 'track_stok' => true,
        ], $extra));
    }

    private function poDiterima(Item $item, Cabang $cabang, float $qty, float $hargaPerPcs): PurchaseOrder
    {
        $supplier = Supplier::firstOrCreate(['nama_supplier' => 'Supplier HTA'], ['kode_supplier' => 'SUP-HTA', 'is_active' => true]);
        $po = PurchaseOrder::create([
            'cabang_id' => $cabang->id, 'supplier_id' => $supplier->id, 'nomor_po' => 'PO-HTA-' . uniqid(),
            'tanggal_po' => now()->toDateString(), 'status' => 'dikirim_supplier', 'total_harga' => $qty * $hargaPerPcs,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id, 'item_id' => $item->id, 'qty_pesan' => $qty,
            'harga_satuan' => $hargaPerPcs, 'total_harga' => $qty * $hargaPerPcs,
        ]);
        app(PurchaseOrderService::class)->terima($po->fresh('items'), []);

        return $po;
    }

    // ===== Poin 1: Harga Standar tidak ditimpa PO, FIFO tetap =====

    public function test_po_diterima_tidak_menimpa_harga_standar_dan_fifo_tetap(): void
    {
        $cab = $this->cabang('Gudang HTA');
        $item = $this->bahan(100);

        $this->poDiterima($item, $cab, 10, 100); // PO lama
        $this->poDiterima($item, $cab, 10, 120); // PO baru

        $item->refresh();
        $this->assertEquals(100, (float) $item->harga_beli_terakhir, 'Harga Standar tidak berubah');
        $this->assertEquals(120, (float) $item->harga_po_terakhir);
        $this->assertNotNull($item->harga_po_terakhir_at);

        // FIFO: keluar 15 pcs = 10 × 100 + 5 × 120
        $hpp = app(StokService::class)->keluar($item->id, $cab->id, 15, 'test', 'order', 1);
        $this->assertEquals(1600, $hpp);
    }

    public function test_po_pertama_mengisi_harga_standar_yang_masih_kosong(): void
    {
        $cab = $this->cabang('Gudang HTA2');
        $item = $this->bahan(0);

        $this->poDiterima($item, $cab, 5, 250);

        $this->assertEquals(250, (float) $item->fresh()->harga_beli_terakhir);
    }

    // ===== Poin 2: harga beli per outlet =====

    public function test_form_bahan_baku_tampil_harga_standar_po_terakhir_dan_outlet(): void
    {
        $this->seed(Perbaikan438PanduanSeeder::class);
        $admin = $this->buatUser('admin_pusat');
        $item = $this->bahan(100, ['unit_beli' => 'pack', 'isi_per_unit_beli' => 50, 'harga_po_terakhir' => 110, 'harga_po_terakhir_at' => now()]);

        $this->actingAs($admin)->get(route('master.bahan-baku.edit', $item))->assertOk()
            ->assertSee('Harga Standar per')->assertSee('Harga PO Terakhir')->assertSee('Rp 110')
            ->assertSee('Harga Beli per Outlet')->assertSee('inputHargaPerUnitBeli', false)
            ->assertSee('Tidak berubah otomatis', false);

        $this->actingAs($admin)->get(route('master.bahan-baku.create'))->assertOk()->assertSee('Harga Standar per');
        $this->actingAs($admin)->get(route('master.bahan-baku.index'))->assertOk()->assertSee('PO Terakhir');
    }

    // ===== Form PO: harga per outlet & unit pack =====

    public function test_form_po_membawa_harga_outlet_dan_logika_konversi_pack(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $a = $this->cabang('Outlet HTA PO');
        $item = $this->bahan(100, ['unit_beli' => 'pack', 'isi_per_unit_beli' => 50]);
        ItemCabang::create(['item_id' => $item->id, 'cabang_id' => $a->id, 'harga_beli' => 150, 'is_active' => true]);

        $html = $this->actingAs($admin)->get(route('pembelian.create'))->assertOk()->getContent();

        $this->assertStringContainsString('"harga_cabang":{"' . $a->id . '":150', $html);
        $this->assertStringContainsString('function setHargaOtomatis', $html);
        $this->assertStringContainsString('function onUnitChange', $html);
        $this->assertStringContainsString('onchange="onUnitChange(${idx})"', $html);
    }

    // ===== Poin 3: harga varian per outlet =====

    public function test_harga_varian_tanpa_override_ikut_harga_outlet(): void
    {
        $cab = $this->cabang('Outlet HTA Var');
        $produk = Item::create([
            'kode_item' => 'HTA-PJ-' . uniqid(), 'nama_item' => 'Dimsum Var', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 150, 'is_active' => true, 'punya_varian' => true,
        ]);
        ItemCabang::create(['item_id' => $produk->id, 'cabang_id' => $cab->id, 'harga_override' => 200, 'is_active' => true]);
        $tanpa = ItemVariant::create(['item_id' => $produk->id, 'sku' => 'V1-' . uniqid(), 'is_active' => true]);
        $dengan = ItemVariant::create(['item_id' => $produk->id, 'sku' => 'V2-' . uniqid(), 'harga_override' => 180, 'is_active' => true]);

        $this->assertEquals(200, $tanpa->hargaEfektifDiCabang($cab->id));
        $this->assertEquals(150, $tanpa->hargaEfektifDiCabang(null));
        $this->assertEquals(180, $dengan->hargaEfektifDiCabang($cab->id));

        // Server: resolveItemRows memakai harga outlet
        $m = new \ReflectionMethod(PenjualanService::class, 'resolveItemRows');
        $m->setAccessible(true);
        [$rows, $total] = $m->invoke(app(PenjualanService::class), [[
            'item_id' => $produk->id, 'item_variant_id' => $tanpa->id, 'nama_item' => 'Dimsum Var',
            'qty' => 2, 'satuan' => 'pcs', 'harga_satuan' => 1,
        ]], $cab->id);
        $this->assertEquals(200, $rows[0]['harga_satuan']);
        $this->assertEquals(400, $total);
    }

    // ===== Poin 4: transfer & dashboard memakai FIFO =====

    public function test_transfer_memakai_modal_fifo_asal_dan_batal_mengembalikan_modal(): void
    {
        $gudang = $this->cabang('Gudang HTA T', 'gudang_pusat');
        $outlet = $this->cabang('Outlet HTA T');
        $item = $this->bahan(999); // harga master sengaja beda
        app(StokService::class)->masuk($item->id, $gudang->id, 20, 'stok awal', null, null, 100);

        $buat = function () use ($gudang, $outlet, $item) {
            $t = StockTransfer::create([
                'nomor_transfer' => 'TRF-HTA-' . uniqid(), 'dari_lokasi_id' => $gudang->id, 'ke_lokasi_id' => $outlet->id,
                'tanggal_kirim' => now()->toDateString(), 'status' => 'draft',
            ]);
            StockTransferItem::create(['stock_transfer_id' => $t->id, 'item_id' => $item->id, 'qty_kirim' => 5]);

            return $t->fresh('items');
        };

        $t = $buat();
        app(StokService::class)->prosesKirimTransfer($t);
        $this->assertEquals(100, (float) $t->items->first()->fresh()->harga_per_unit);

        app(StokService::class)->prosesTerimaTransfer($t->fresh('items'));
        $batchOutlet = StockBatch::where('item_id', $item->id)->where('lokasi_id', $outlet->id)->firstOrFail();
        $this->assertEquals(100, (float) $batchOutlet->harga_beli_per_unit, 'bukan harga master 999');

        // Batal setelah kirim → stok kembali ke gudang dgn modal 100 (dulu Rp 0)
        $t2 = $buat();
        app(StokService::class)->prosesKirimTransfer($t2);
        $t2->update(['status' => 'dikirim']);
        $admin = $this->buatUser('owner');
        $this->actingAs($admin)->post(route('stock-transfer.batalkan', $t2), ['alasan' => 'test'])->assertRedirect();
        $kembali = StockBatch::where('item_id', $item->id)->where('lokasi_id', $gudang->id)
            ->where('referensi_type', 'stock_transfer')->where('referensi_id', $t2->id)->first();
        $this->assertNotNull($kembali);
        $this->assertEquals(100, (float) $kembali->harga_beli_per_unit);
    }

    public function test_dashboard_stok_nilai_memakai_fifo(): void
    {
        $gudang = $this->cabang('Gudang HTA D', 'gudang_pusat');
        $item = $this->bahan(999);
        app(StokService::class)->masuk($item->id, $gudang->id, 10, 'stok awal', null, null, 100);

        $ctrl = app(\App\Http\Controllers\StokDashboardController::class);
        $m = new \ReflectionMethod($ctrl, 'nilaiFifo');
        $m->setAccessible(true);
        $this->assertEquals(1000, $m->invoke($ctrl, $item->id, $gudang->id, 10.0, 999.0));
        $this->assertEquals(1000 + 2 * 999, $m->invoke($ctrl, $item->id, $gudang->id, 12.0, 999.0), 'qty tanpa batch pakai Harga Standar');

        $this->actingAs($this->buatUser('owner'))->get(route('stok.dashboard'))->assertOk();
    }

    public function test_halaman_terkait_harga_tetap_normal(): void
    {
        $owner = $this->buatUser('owner');
        $item = $this->bahan(100);
        foreach ([route('master.bahan-baku.index'), route('pembelian.create'), route('stok.dashboard'), route('stok.adjustment'),
                  route('item.index'), route('item.show', $item), route('master.produk-jual.create'), route('penjualan.index')] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }

        // POS butuh cabang aktif (kasir selalu terhubung ke cabang)
        $cab = Cabang::where('tipe', '!=', 'gudang_pusat')->orderBy('id')->firstOrFail();
        $this->actingAs($this->buatUser('kasir', $cab->id))->withSession(['active_cabang_id' => $cab->id])
            ->get(route('penjualan.pos'))->assertOk();
    }
}
