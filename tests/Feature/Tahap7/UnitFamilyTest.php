<?php

namespace Tests\Feature\Tahap7;

use App\Enums\StatusPurchaseOrder;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Stock;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint Unit Family (2026-09-27) — dukung input PO/Adjustment dlm unit
 * beli (pack/karung/dus) yang auto-convert ke unit pakai (satuan).
 * Non-breaking: data lama (unit_beli NULL) tetap jalan sebagai mode legacy.
 */
class UnitFamilyTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function supplier(): Supplier
    {
        return Supplier::firstOrCreate(
            ['kode_supplier' => 'SUP-UF-01'],
            ['nama_supplier' => 'Supplier UF', 'is_active' => true]
        );
    }

    private function itemPack(): Item
    {
        return Item::create([
            'kode_item' => 'BB-UF-PACK', 'nama_item' => 'Sumpit Bambu',
            'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'unit_beli' => 'pack', 'isi_per_unit_beli' => 100,
            'harga_beli_terakhir' => 50, 'is_active' => true,
        ]);
    }

    private function itemNoPack(): Item
    {
        return Item::create([
            'kode_item' => 'BB-UF-NOPACK', 'nama_item' => 'Bahan Legacy',
            'tipe' => 'bahan_baku', 'satuan' => 'kg',
            'harga_beli_terakhir' => 20000, 'is_active' => true,
        ]);
    }

    // ===== Model =====

    public function test_konversi_ke_unit_pakai_akurat(): void
    {
        $item = $this->itemPack();
        $this->assertEqualsWithDelta(1000, $item->convertToUnitPakai(10, 'pack'), 0.001);
        $this->assertEqualsWithDelta(1000, $item->convertToUnitPakai(10, 'PACK'), 0.001, 'case-insensitive');
        $this->assertEqualsWithDelta(500, $item->convertToUnitPakai(500, 'pcs'), 0.001, 'unit pakai passthrough');
        $this->assertEqualsWithDelta(500, $item->convertToUnitPakai(500, null), 0.001, 'unit null passthrough');
    }

    public function test_has_unit_beli(): void
    {
        $this->assertTrue($this->itemPack()->hasUnitBeli());
        $this->assertFalse($this->itemNoPack()->hasUnitBeli());
        $itemNolIsi = Item::create(['kode_item' => 'BB-UF-NOLISI', 'nama_item' => 'X', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'unit_beli' => 'pack', 'isi_per_unit_beli' => 0, 'is_active' => true]);
        $this->assertFalse($itemNolIsi->hasUnitBeli(), 'isi=0 dianggap tidak aktif');
    }

    // ===== Master Bahan Baku (form validation) =====

    public function test_master_bahan_baku_simpan_dengan_unit_beli(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->post(route('master.bahan-baku.store'), [
            'kode_item' => 'BB-TES-1', 'nama_item' => 'Test Sumpit',
            'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'harga_beli_terakhir' => '50', 'qty_minimum' => 100,
            'unit_beli' => 'pack', 'isi_per_unit_beli' => 100,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['kode_item' => 'BB-TES-1', 'unit_beli' => 'pack', 'isi_per_unit_beli' => 100]);
    }

    public function test_master_bahan_baku_simpan_tanpa_unit_beli_backward_compat(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->post(route('master.bahan-baku.store'), [
            'kode_item' => 'BB-TES-2', 'nama_item' => 'Test Legacy',
            'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_beli_terakhir' => '20000',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['kode_item' => 'BB-TES-2', 'unit_beli' => null, 'isi_per_unit_beli' => null]);
    }

    public function test_master_bahan_baku_tolak_kalau_hanya_salah_satu_diisi(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $r1 = $this->actingAs($admin)->post(route('master.bahan-baku.store'), [
            'kode_item' => 'BB-TES-3A', 'nama_item' => 'X', 'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'unit_beli' => 'pack',
        ]);
        $r1->assertSessionHasErrors('isi_per_unit_beli');

        $r2 = $this->actingAs($admin)->post(route('master.bahan-baku.store'), [
            'kode_item' => 'BB-TES-3B', 'nama_item' => 'X', 'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'isi_per_unit_beli' => 50,
        ]);
        $r2->assertSessionHasErrors('unit_beli');

        $r3 = $this->actingAs($admin)->post(route('master.bahan-baku.store'), [
            'kode_item' => 'BB-TES-3C', 'nama_item' => 'X', 'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'unit_beli' => 'pack', 'isi_per_unit_beli' => 0,
        ]);
        $r3->assertSessionHasErrors('isi_per_unit_beli');
    }

    // ===== PO Create (dgn unit_input) =====

    public function test_po_input_pack_convert_qty_dan_harga_ke_pcs(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = $this->itemPack();
        $cabang = Cabang::orderBy('id')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('pembelian.store'), [
            'supplier_id' => $this->supplier()->id,
            'tanggal_po' => now()->toDateString(),
            'cabang_id' => $cabang->id,
            'items' => [
                ['item_id' => $item->id, 'qty_pesan' => 10, 'harga_satuan' => 5000, 'unit_input' => 'pack'],
            ],
        ]);
        $response->assertRedirect(route('pembelian.index'));

        $po = PurchaseOrder::latest('id')->first();
        $poItem = $po->items->first();
        $this->assertEqualsWithDelta(1000, (float) $poItem->qty_pesan, 0.001, '10 pack × 100 = 1000 pcs');
        $this->assertEqualsWithDelta(50, (float) $poItem->harga_satuan, 0.001, 'Rp 5.000/pack ÷ 100 = Rp 50/pcs');
        $this->assertEqualsWithDelta(50000, (float) $poItem->total_harga, 0.001);
        $this->assertEquals('pack', $poItem->unit_input);
        $this->assertEqualsWithDelta(10, (float) $poItem->qty_input, 0.001);
    }

    public function test_po_input_pcs_backward_compat_tanpa_konversi(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = $this->itemPack();
        $cabang = Cabang::orderBy('id')->firstOrFail();

        $this->actingAs($admin)->post(route('pembelian.store'), [
            'supplier_id' => $this->supplier()->id,
            'tanggal_po' => now()->toDateString(),
            'cabang_id' => $cabang->id,
            'items' => [
                ['item_id' => $item->id, 'qty_pesan' => 500, 'harga_satuan' => 50, 'unit_input' => 'pcs'],
            ],
        ])->assertRedirect();

        $poItem = PurchaseOrder::latest('id')->first()->items->first();
        $this->assertEqualsWithDelta(500, (float) $poItem->qty_pesan, 0.001);
        $this->assertEqualsWithDelta(50, (float) $poItem->harga_satuan, 0.001);
        $this->assertNull($poItem->unit_input, 'unit_input NULL kalau bukan unit_beli');
        $this->assertNull($poItem->qty_input);
    }

    public function test_po_item_tanpa_unit_beli_tetap_works(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = $this->itemNoPack();
        $cabang = Cabang::orderBy('id')->firstOrFail();

        $this->actingAs($admin)->post(route('pembelian.store'), [
            'supplier_id' => $this->supplier()->id,
            'tanggal_po' => now()->toDateString(),
            'cabang_id' => $cabang->id,
            'items' => [
                ['item_id' => $item->id, 'qty_pesan' => 2.5, 'harga_satuan' => 20000],
            ],
        ])->assertRedirect();

        $poItem = PurchaseOrder::latest('id')->first()->items->first();
        $this->assertEqualsWithDelta(2.5, (float) $poItem->qty_pesan, 0.001);
        $this->assertNull($poItem->unit_input);
    }

    // ===== Adjustment =====

    public function test_adjustment_input_pack_convert_qty_fisik_ke_pcs(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = $this->itemPack();
        $cabang = Cabang::orderBy('id')->firstOrFail();
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 0, 'qty_minimum' => 0]);

        $response = $this->actingAs($admin)->post(route('stok.adjustment.store'), [
            'item_id' => $item->id, 'lokasi_id' => $cabang->id,
            'qty_fisik' => 5, 'unit_input' => 'pack',
            'alasan' => 'audit', 'catatan' => 'stok opname',
            'mode_distribusi' => 'batch_baru',
        ]);
        $response->assertRedirect(route('stok.index'));

        $stok = Stock::where('item_id', $item->id)->where('lokasi_id', $cabang->id)->first();
        $this->assertEqualsWithDelta(500, (float) $stok->qty, 0.001, '5 pack × 100 = 500 pcs');

        // Audit trail masuk ke stock_movement catatan
        $movement = \App\Models\StockMovement::where('item_id', $item->id)->orderByDesc('id')->first();
        $this->assertStringContainsString('Input: 5 pack', (string) $movement?->catatan);
    }

    public function test_adjustment_tanpa_unit_input_backward_compat(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = $this->itemNoPack();
        $cabang = Cabang::orderBy('id')->firstOrFail();
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 0, 'qty_minimum' => 0]);

        $this->actingAs($admin)->post(route('stok.adjustment.store'), [
            'item_id' => $item->id, 'lokasi_id' => $cabang->id,
            'qty_fisik' => 5, 'alasan' => 'audit',
            'mode_distribusi' => 'batch_baru',
        ])->assertRedirect();

        $stok = Stock::where('item_id', $item->id)->where('lokasi_id', $cabang->id)->first();
        $this->assertEqualsWithDelta(5, (float) $stok->qty, 0.001);
    }

    // ===== Laporan Stok kolom Setara Pack =====

    public function test_laporan_stok_tampil_kolom_setara_pack(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = $this->itemPack();
        $cabang = Cabang::orderBy('id')->firstOrFail();
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 500, 'qty_minimum' => 0]);

        $response = $this->actingAs($admin)->get(route('laporan.stok'));
        $response->assertOk();
        $response->assertSee('Setara Pack');
        $response->assertSee('5 pack', false); // 500 pcs / 100 = 5 pack
    }

    // ===== Migration non-breaking =====

    public function test_kolom_baru_nullable_data_existing_aman(): void
    {
        $cols = collect(\DB::select("SHOW COLUMNS FROM items WHERE Field IN ('unit_beli','isi_per_unit_beli')"))->keyBy('Field');
        $this->assertEquals('YES', $cols['unit_beli']->Null);
        $this->assertEquals('YES', $cols['isi_per_unit_beli']->Null);

        $poCols = collect(\DB::select("SHOW COLUMNS FROM purchase_order_items WHERE Field IN ('unit_input','qty_input')"))->keyBy('Field');
        $this->assertEquals('YES', $poCols['unit_input']->Null);
        $this->assertEquals('YES', $poCols['qty_input']->Null);
    }
}
