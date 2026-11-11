<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemVariant;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Models\StockBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Fase 4 pemisahan data per cabang (CLAUDE.md 4.39): FIFO Batch Detail di
 * form Bahan Baku & salin semua data barang saat tambah cabang.
 */
class FifoDanSalinCabangFase4Test extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Cabang::create(['nama_cabang' => 'Outlet F4', 'kode_cabang' => 'F4' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
    }

    private function bahan(array $extra = []): Item
    {
        return Item::create(array_merge(['cabang_id' => $this->a->id, 'kode_item' => 'F4-' . uniqid(), 'nama_item' => 'Sirup F4', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => 900, 'is_active' => true, 'track_stok' => true], $extra));
    }

    public function test_fifo_batch_detail_hanya_batch_bersisa_dan_tandai_next(): void
    {
        $item = $this->bahan();
        $lain = Cabang::create(['nama_cabang' => 'Lain F4', 'kode_cabang' => 'L4' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $habis = StockBatch::create(['item_id' => $item->id, 'lokasi_id' => $this->a->id, 'qty_awal' => 10, 'qty_sisa' => 0, 'harga_beli_per_unit' => 777, 'tanggal_masuk' => now()->subDays(9), 'referensi_type' => 'purchase_order']);
        $tua = StockBatch::create(['item_id' => $item->id, 'lokasi_id' => $this->a->id, 'qty_awal' => 10, 'qty_sisa' => 3, 'harga_beli_per_unit' => 1000, 'tanggal_masuk' => now()->subDays(5), 'referensi_type' => 'purchase_order']);
        $baru = StockBatch::create(['item_id' => $item->id, 'lokasi_id' => $this->a->id, 'qty_awal' => 10, 'qty_sisa' => 10, 'harga_beli_per_unit' => 1200, 'tanggal_masuk' => now()->subDay(), 'referensi_type' => 'stock_transfer']);
        $cabangLain = StockBatch::create(['item_id' => $item->id, 'lokasi_id' => $lain->id, 'qty_awal' => 4, 'qty_sisa' => 4, 'harga_beli_per_unit' => 5555, 'tanggal_masuk' => now()->subDays(20), 'referensi_type' => 'test']);

        $this->actingAs($this->buatUser('owner'))->get(route('master.bahan-baku.edit', $item))->assertOk()
            ->assertSee('FIFO Batch Detail')
            ->assertSee('#' . $tua->id)->assertSee('#' . $baru->id)
            ->assertDontSee('#' . $habis->id . '<', false)
            ->assertDontSee('Rp 777')->assertDontSee('Rp 5.555')
            ->assertSeeInOrder(['#' . $tua->id, '↑ NEXT', '#' . $baru->id])
            ->assertSee('Rp 15.000'); // 3×1.000 + 10×1.200
    }

    public function test_fifo_batch_detail_kosong_saat_stok_habis(): void
    {
        $item = $this->bahan(['harga_po_terakhir' => 1300]);

        $this->actingAs($this->buatUser('owner'))->get(route('master.bahan-baku.edit', $item))->assertOk()
            ->assertSee('Stok habis')->assertSee('Rp 1.300');
    }

    public function test_tambah_cabang_salin_semua_barang_resep_bumbu_varian_dan_minimum(): void
    {
        $owner = $this->buatUser('owner');
        $kodeBahan = 'F4B-' . uniqid();
        $bahan = $this->bahan(['kode_item' => $kodeBahan, 'harga_beli_terakhir' => 1100]);
        Stock::create(['item_id' => $bahan->id, 'lokasi_id' => $this->a->id, 'qty' => 40, 'qty_minimum' => 12]);
        $produk = Item::create(['cabang_id' => $this->a->id, 'kode_item' => 'F4P-' . uniqid(), 'nama_item' => 'Es Sirup F4', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 6000, 'is_active' => true, 'track_stok' => false, 'punya_varian' => true]);
        $attr = ItemAttribute::create(['item_id' => $produk->id, 'nama' => 'Size', 'urutan' => 0]);
        $nilai = ItemAttributeValue::create(['item_attribute_id' => $attr->id, 'nilai' => 'L', 'urutan' => 0]);
        $var = ItemVariant::create(['item_id' => $produk->id, 'harga_override' => 7000, 'is_active' => true, 'urutan' => 0]);
        DB::table('item_variant_values')->insert(['item_variant_id' => $var->id, 'item_attribute_value_id' => $nilai->id]);

        $bumbu = ResepBumbu::create(['cabang_id' => $this->a->id, 'nama' => 'Bumbu F4', 'kode' => 'BF4' . rand(100, 999), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahan->id, 'qty_per_unit' => 1, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);
        $resep = ResepBumbu::create(['cabang_id' => $this->a->id, 'nama' => 'Es Sirup F4', 'kode' => 'RF4' . rand(100, 999), 'item_id' => $produk->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $bahan->id, 'qty_per_unit' => 2, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 1, 'satuan' => 'porsi', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 1]);

        $kodeCabang = 'F4N' . rand(100, 999);
        $this->actingAs($owner)->post(route('cabang.store'), [
            'kode_cabang' => $kodeCabang, 'nama_cabang' => 'Outlet F4 Baru', 'tipe' => 'cabang', 'salin_dari_cabang_id' => $this->a->id,
        ])->assertRedirect(route('cabang.index'));
        $baru = Cabang::where('kode_cabang', strtoupper($kodeCabang))->firstOrFail();

        $bahanBaru = Item::where('cabang_id', $baru->id)->where('kode_item', $kodeBahan)->firstOrFail();
        $this->assertEquals(1100, (float) $bahanBaru->harga_beli_terakhir);
        $st = Stock::where('item_id', $bahanBaru->id)->where('lokasi_id', $baru->id)->first();
        $this->assertEquals(12, (float) $st->qty_minimum);
        $this->assertEquals(0, (float) $st->qty, 'stok tidak disalin');

        $produkBaru = Item::where('cabang_id', $baru->id)->where('kode_item', $produk->kode_item)->firstOrFail();
        $this->assertEquals(6000, (float) $produkBaru->harga_jual);
        $this->assertEquals(7000, (float) $produkBaru->variants()->value('harga_override'));
        $this->assertSame('L', $produkBaru->variants()->first()->attributeValues()->value('nilai'));

        $resepBaru = ResepBumbu::where('item_id', $produkBaru->id)->firstOrFail();
        $this->assertSame($baru->id, $resepBaru->cabang_id);
        $this->assertSame($bahanBaru->id, (int) $resepBaru->items()->whereNotNull('item_id')->value('item_id'));
        $bumbuBaru = ResepBumbu::find((int) $resepBaru->items()->whereNotNull('resep_bumbu_ref_id')->value('resep_bumbu_ref_id'));
        $this->assertSame($baru->id, $bumbuBaru->cabang_id);
        $this->assertSame($bahanBaru->id, (int) $bumbuBaru->items()->value('item_id'));

        // Data cabang sumber tidak berubah
        $this->assertSame($this->a->id, $bahan->fresh()->cabang_id);
        $this->assertEquals(40, (float) Stock::where('item_id', $bahan->id)->where('lokasi_id', $this->a->id)->value('qty'));
    }
}
