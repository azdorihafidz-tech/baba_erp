<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Services\PadananBarangService;
use App\Services\PurchaseOrderService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Fase 3 pemisahan data per cabang (CLAUDE.md 4.39): transfer, PO cabang ke
 * Gudang, permintaan stok — barang dipasangkan antar cabang lewat Kode Item.
 */
class AlurAntarCabangFase3Test extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $outlet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gudang = Cabang::where('tipe', 'gudang_pusat')->orderBy('id')->first() ?? Cabang::create(['nama_cabang' => 'Gudang F3', 'kode_cabang' => 'GF3' . rand(100, 999), 'tipe' => 'gudang_pusat', 'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true, 'frozen_aktif' => true]);
        $this->outlet = Cabang::create(['nama_cabang' => 'Outlet F3', 'kode_cabang' => 'OF3' . rand(100, 999), 'tipe' => 'cabang', 'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true, 'frozen_aktif' => true]);
    }

    private function item(Cabang $c, string $kode, array $extra = []): Item
    {
        return Item::create(array_merge(['cabang_id' => $c->id, 'kode_item' => $kode, 'nama_item' => 'Kulit F3', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => 100, 'is_active' => true, 'track_stok' => true], $extra));
    }

    private function transferTerkirim(Item $sumber, float $qty): StockTransfer
    {
        $this->actingAs($this->buatUser('owner'));
        app(StokService::class)->masuk($sumber->id, $this->gudang->id, 20, 'stok awal', 'test', 1, 150);
        $t = StockTransfer::create(['nomor_transfer' => 'TRF-F3-' . uniqid(), 'dari_lokasi_id' => $this->gudang->id, 'ke_lokasi_id' => $this->outlet->id,
            'tanggal_kirim' => now()->toDateString(), 'status' => 'draft', 'created_by' => auth()->id()]);
        StockTransferItem::create(['stock_transfer_id' => $t->id, 'item_id' => $sumber->id, 'qty_kirim' => $qty]);
        app(StokService::class)->prosesKirimTransfer($t->fresh('items.item'));

        return $t->fresh('items.item');
    }

    public function test_padanan_cari_dan_buat_lewat_kode_item(): void
    {
        $kode = 'F3-' . uniqid();
        $g = $this->item($this->gudang, $kode);
        $svc = app(PadananBarangService::class);

        $this->assertNull($svc->cari($g, $this->outlet->id));
        $o = $svc->untukCabang($g, $this->outlet->id);
        $this->assertSame($this->outlet->id, $o->cabang_id);
        $this->assertSame($kode, $o->kode_item);
        $this->assertSame($o->id, $svc->untukCabang($g, $this->outlet->id)->id, 'tidak dobel');
        $this->assertSame($g->id, $svc->untukCabang($g, $this->gudang->id)->id);
    }

    public function test_terima_transfer_masuk_ke_barang_milik_cabang_tujuan_dgn_modal_fifo(): void
    {
        $kode = 'F3T-' . uniqid();
        $g = $this->item($this->gudang, $kode);
        $o = $this->item($this->outlet, $kode, ['harga_beli_terakhir' => 999]);

        $t = $this->transferTerkirim($g, 5);
        app(StokService::class)->prosesTerimaTransfer($t);

        $this->assertEquals(5, (float) Stock::where('item_id', $o->id)->where('lokasi_id', $this->outlet->id)->value('qty'));
        $this->assertFalse(Stock::where('item_id', $g->id)->where('lokasi_id', $this->outlet->id)->exists(), 'stok tidak nyasar ke barang gudang');
        $this->assertEquals(150, (float) StockBatch::where('item_id', $o->id)->where('lokasi_id', $this->outlet->id)->value('harga_beli_per_unit'));
        $this->assertEquals(15, (float) Stock::where('item_id', $g->id)->where('lokasi_id', $this->gudang->id)->value('qty'));
    }

    public function test_terima_transfer_membuat_barang_di_cabang_tujuan_kalau_belum_ada(): void
    {
        $kode = 'F3N-' . uniqid();
        $g = $this->item($this->gudang, $kode, ['nama_item' => 'Saus Baru F3']);

        $t = $this->transferTerkirim($g, 3);
        app(StokService::class)->prosesTerimaTransfer($t);

        $o = Item::where('cabang_id', $this->outlet->id)->where('kode_item', $kode)->first();
        $this->assertNotNull($o);
        $this->assertSame('Saus Baru F3', $o->nama_item);
        $this->assertEquals(3, (float) Stock::where('item_id', $o->id)->where('lokasi_id', $this->outlet->id)->value('qty'));
    }

    public function test_transfer_menolak_barang_bukan_milik_lokasi_asal(): void
    {
        $milikOutlet = $this->item($this->outlet, 'F3X-' . uniqid());

        $this->actingAs($this->buatUser('owner'))->post(route('stock-transfer.store'), [
            'dari_lokasi_id' => $this->gudang->id, 'ke_lokasi_id' => $this->outlet->id, 'tanggal_kirim' => now()->toDateString(),
            'items' => [['item_id' => $milikOutlet->id, 'qty_kirim' => 1]],
        ])->assertSessionHas('error');
        $this->assertFalse(StockTransfer::where('dari_lokasi_id', $this->gudang->id)->where('ke_lokasi_id', $this->outlet->id)->exists());
    }

    public function test_po_ke_gudang_dicatat_sbg_barang_milik_cabang_pemesan_dan_stok_masuk_ke_sana(): void
    {
        $supGudang = Supplier::create(['kode_supplier' => 'SGF3' . rand(100, 999), 'nama_supplier' => 'Gudang F3 Sup', 'is_active' => true, 'cabang_id' => $this->gudang->id]);
        $kode = 'F3P-' . uniqid();
        $produkGudang = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => $kode, 'nama_item' => 'Dimsum Frozen F3', 'tipe' => 'produk_jual',
            'satuan' => 'pack', 'harga_jual' => 15000, 'is_active' => true, 'track_stok' => false]);

        $this->actingAs($this->buatUser('owner'))->post(route('pembelian.store'), [
            'supplier_id' => $supGudang->id, 'cabang_id' => $this->outlet->id, 'tanggal_po' => now()->toDateString(),
            'items' => [['item_id' => $produkGudang->id, 'qty_pesan' => 4, 'harga_satuan' => 15000]],
        ])->assertRedirect(route('pembelian.index'));

        $po = PurchaseOrder::where('supplier_id', $supGudang->id)->latest('id')->firstOrFail();
        $barangOutlet = Item::where('cabang_id', $this->outlet->id)->where('kode_item', $kode)->firstOrFail();
        $this->assertSame('bahan_baku', $barangOutlet->tipe, 'produk gudang jadi stok bahan di cabang');
        $this->assertSame($barangOutlet->id, (int) $po->items()->value('item_id'));

        // POS gudang: isi keranjang dari PO memakai produk milik gudang
        $kasirGudang = $this->buatUser('kasir', $this->gudang->id);
        $po->update(['status' => 'disetujui']);
        $html = $this->actingAs($kasirGudang)->withSession(['active_cabang_id' => $this->gudang->id])->get(route('penjualan.pos'))->assertOk()->getContent();
        $this->assertStringContainsString('"item_id":' . $produkGudang->id, $html);

        // Diterima → stok masuk ke barang milik outlet
        $po->update(['status' => 'dikirim_supplier']);
        $this->actingAs($this->buatUser('owner'));
        app(PurchaseOrderService::class)->terima($po->fresh('items'), []);
        $this->assertEquals(4, (float) Stock::where('item_id', $barangOutlet->id)->where('lokasi_id', $this->outlet->id)->value('qty'));
    }

    public function test_permintaan_stok_hanya_barang_milik_peminta_dan_prefill_transfer_pakai_barang_gudang(): void
    {
        $kode = 'F3R-' . uniqid();
        $g = $this->item($this->gudang, $kode);
        $o = $this->item($this->outlet, $kode);
        $manajer = $this->buatUser('manajer_cabang', $this->outlet->id);

        $this->actingAs($manajer)->withSession(['active_cabang_id' => $this->outlet->id])->post(route('stock-request.store'), [
            'items' => [['item_id' => $g->id, 'qty_diminta' => 2]],
        ])->assertSessionHas('error');

        $this->actingAs($manajer)->withSession(['active_cabang_id' => $this->outlet->id])->post(route('stock-request.store'), [
            'items' => [['item_id' => $o->id, 'qty_diminta' => 2]],
        ])->assertRedirect();
        $req = \App\Models\StockRequest::where('cabang_id', $this->outlet->id)->latest('id')->firstOrFail();

        $res = $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $this->gudang->id])
            ->get(route('stock-transfer.create', ['dari_request' => $req->id]))->assertOk();
        $this->assertStringContainsString('"item_id":' . $g->id, $res->getContent());
    }
}
