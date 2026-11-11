<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCabang;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Services\PemecahanDataCabangService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 1 pemisahan data per cabang (CLAUDE.md 4.39): pemecahan data lama.
 */
class PemecahanDataCabangTest extends TestCase
{
    use DatabaseTransactions;

    private Cabang $gudang;
    private Cabang $o1;
    private Cabang $o2;
    private Item $bahan;
    private Item $bahanBumbu;
    private Item $produk;
    private ResepBumbu $bumbu;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $svc = app(PemecahanDataCabangService::class);
        $this->gudang = Cabang::find($svc->cabangUtamaDefault())
            ?? Cabang::create(['nama_cabang' => 'Gudang PDC', 'kode_cabang' => 'GPDC' . rand(10, 99), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->o1 = Cabang::create(['nama_cabang' => 'Outlet PDC 1', 'kode_cabang' => 'PDC1' . rand(100, 999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->o2 = Cabang::create(['nama_cabang' => 'Outlet PDC 2', 'kode_cabang' => 'PDC2' . rand(100, 999), 'tipe' => 'cabang', 'is_active' => true]);

        $this->bahan = Item::create(['kode_item' => 'PDC-B-' . uniqid(), 'nama_item' => 'Kulit PDC', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 100, 'is_active' => true, 'track_stok' => true]);
        $this->bahanBumbu = Item::create(['kode_item' => 'PDC-C-' . uniqid(), 'nama_item' => 'Saus PDC', 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_beli_terakhir' => 1000, 'is_active' => true, 'track_stok' => true]);
        $this->produk = Item::create(['kode_item' => 'PDC-P-' . uniqid(), 'nama_item' => 'Dimsum PDC', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 1200, 'is_active' => true, 'track_stok' => false]);

        // Bahan: stok di gudang & outlet 1, harga beli khusus outlet 1
        Stock::create(['item_id' => $this->bahan->id, 'lokasi_id' => $this->gudang->id, 'qty' => 10, 'qty_minimum' => 4]);
        Stock::create(['item_id' => $this->bahan->id, 'lokasi_id' => $this->o1->id, 'qty' => 5, 'qty_minimum' => 2]);
        StockBatch::create(['item_id' => $this->bahan->id, 'lokasi_id' => $this->gudang->id, 'qty_awal' => 10, 'qty_sisa' => 10, 'harga_beli_per_unit' => 100, 'tanggal_masuk' => now(), 'referensi_type' => 'test']);
        StockBatch::create(['item_id' => $this->bahan->id, 'lokasi_id' => $this->o1->id, 'qty_awal' => 5, 'qty_sisa' => 5, 'harga_beli_per_unit' => 120, 'tanggal_masuk' => now(), 'referensi_type' => 'test']);
        ItemCabang::create(['item_id' => $this->bahan->id, 'cabang_id' => $this->o1->id, 'harga_beli' => 130, 'is_active' => true]);

        // Produk: dijual di outlet 1 (harga 1500), tidak di outlet 2
        ItemCabang::create(['item_id' => $this->produk->id, 'cabang_id' => $this->o1->id, 'harga_override' => 1500, 'is_active' => true]);
        ItemCabang::create(['item_id' => $this->produk->id, 'cabang_id' => $this->o2->id, 'is_active' => false]);

        // Resep produk: 2 kulit + 1 porsi Bumbu Pusat (isi saus 0.1 kg)
        $this->bumbu = ResepBumbu::create(['nama' => 'Bumbu PDC', 'kode' => 'BPDC-' . uniqid(), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $this->bumbu->id, 'item_id' => $this->bahanBumbu->id, 'qty_per_unit' => 0.1, 'satuan' => 'kg', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);
        $resep = ResepBumbu::create(['nama' => 'Dimsum PDC', 'kode' => 'RPDC-' . uniqid(), 'item_id' => $this->produk->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $this->bahan->id, 'qty_per_unit' => 2, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'resep_bumbu_ref_id' => $this->bumbu->id, 'qty_per_unit' => 1, 'satuan' => 'porsi', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 1]);

        // Riwayat penjualan di outlet 1
        $this->order = Order::withoutEvents(fn () => Order::create([
            'cabang_id' => $this->o1->id, 'nomor_order' => 'PDC-' . uniqid(), 'tanggal_order' => now(), 'tipe_order' => 'penjualan',
            'total_harga' => 1500, 'total_bayar' => 1500, 'status' => 'selesai',
        ]));
        OrderItem::withoutEvents(fn () => OrderItem::create(['order_id' => $this->order->id, 'item_id' => $this->produk->id, 'nama_item' => 'Dimsum PDC', 'qty' => 1, 'satuan' => 'pcs', 'harga_satuan' => 1500, 'total_harga' => 1500]));
    }

    private function milik(Item $asli, Cabang $c): ?Item
    {
        return Item::where('kode_item', $asli->kode_item)->where('cabang_id', $c->id)->first();
    }

    public function test_simulasi_tidak_mengubah_data_dan_rencana_benar(): void
    {
        $r = app(PemecahanDataCabangService::class)->rencana();

        $this->assertSame([$this->gudang->id, $this->o1->id], $r['items'][$this->bahan->id]['cabang']);
        $this->assertSame([$this->o1->id], $r['items'][$this->produk->id]['cabang'], 'produk hanya dijual di outlet 1');
        $this->assertSame([$this->o1->id], $r['bumbu'][$this->bumbu->id]['cabang'], 'bumbu ikut produk pemakainya');
        $this->assertSame([$this->o1->id], $r['items'][$this->bahanBumbu->id]['cabang'], 'bahan bumbu ikut cabang bumbu');
        $this->assertNull($this->bahan->fresh()->cabang_id);
        $this->assertSame(0, DB::table('pemecahan_cabang_maps')->count());
    }

    public function test_pecah_stok_harga_resep_riwayat_ikut_cabangnya(): void
    {
        app(PemecahanDataCabangService::class)->jalankan();

        // Bahan: asli milik gudang, salinan milik outlet 1 dgn harga beli outlet
        $this->assertSame($this->gudang->id, $this->bahan->fresh()->cabang_id);
        $bahanO1 = $this->milik($this->bahan, $this->o1);
        $this->assertNotNull($bahanO1);
        $this->assertNotSame($this->bahan->id, $bahanO1->id);
        $this->assertEquals(130, (float) $bahanO1->harga_beli_terakhir);
        $this->assertEquals(100, (float) $this->bahan->fresh()->harga_beli_terakhir);
        $this->assertNull($this->milik($this->bahan, $this->o2), 'outlet 2 tidak memakai bahan ini');

        // Stok & batch pindah ke salinan cabangnya, jumlah tetap
        $this->assertEquals(5, (float) Stock::where('item_id', $bahanO1->id)->where('lokasi_id', $this->o1->id)->value('qty'));
        $this->assertEquals(2, (float) Stock::where('item_id', $bahanO1->id)->where('lokasi_id', $this->o1->id)->value('qty_minimum'));
        $this->assertEquals(10, (float) Stock::where('item_id', $this->bahan->id)->where('lokasi_id', $this->gudang->id)->value('qty'));
        $this->assertEquals(120, (float) StockBatch::where('item_id', $bahanO1->id)->value('harga_beli_per_unit'));
        $this->assertFalse(Stock::where('item_id', $this->bahan->id)->where('lokasi_id', $this->o1->id)->exists());

        // Produk: milik outlet 1, harga outlet jadi harga jualnya, riwayat order tetap menunjuk produk ini
        $produk = $this->produk->fresh();
        $this->assertSame($this->o1->id, $produk->cabang_id);
        $this->assertEquals(1500, (float) $produk->harga_jual);
        $this->assertSame($produk->id, (int) OrderItem::where('order_id', $this->order->id)->value('item_id'));

        // Resep produk & Bumbu Pusat memakai bahan milik outlet 1
        $resep = ResepBumbu::where('item_id', $produk->id)->first();
        $this->assertSame($this->o1->id, $resep->cabang_id);
        $this->assertSame($bahanO1->id, (int) $resep->items()->whereNotNull('item_id')->value('item_id'));
        $bumbuRef = (int) $resep->items()->whereNotNull('resep_bumbu_ref_id')->value('resep_bumbu_ref_id');
        $this->assertSame($this->o1->id, ResepBumbu::find($bumbuRef)->cabang_id);
        $this->assertSame($this->o1->id, $this->bahanBumbu->fresh()->cabang_id);
        $this->assertFalse(app(PemecahanDataCabangService::class)->perluDipecah());

        // Idempotent
        $hasil = app(PemecahanDataCabangService::class)->jalankan();
        $this->assertSame(0, $hasil['salinan']);
    }

    public function test_batalkan_mengembalikan_seperti_semula(): void
    {
        $svc = app(PemecahanDataCabangService::class);
        $itemSebelum = Item::withTrashed()->count();
        $svc->jalankan();
        $svc->batalkan();

        $this->assertSame($itemSebelum, Item::withTrashed()->count());
        $this->assertNull($this->bahan->fresh()->cabang_id);
        $this->assertEquals(1200, (float) $this->produk->fresh()->harga_jual, 'harga asli kembali');
        $this->assertEquals(5, (float) Stock::where('item_id', $this->bahan->id)->where('lokasi_id', $this->o1->id)->value('qty'));
        $this->assertSame($this->bahan->id, (int) ResepBumbuItem::where('resep_bumbu_id', ResepBumbu::where('item_id', $this->produk->id)->value('id'))->whereNotNull('item_id')->value('item_id'));
        $this->assertSame(0, DB::table('pemecahan_cabang_maps')->count());
    }

    public function test_command_simulasi(): void
    {
        $this->artisan('erp:pecah-cabang', ['--simulasi' => true])
            ->expectsOutputToContain('SIMULASI')
            ->assertSuccessful();
        $this->assertNull($this->bahan->fresh()->cabang_id);
    }

    public function test_scope_milik_cabang_termasuk_barang_belum_dipecah(): void
    {
        $milikO2 = Item::create(['cabang_id' => $this->o2->id, 'kode_item' => 'PDC-X-' . uniqid(), 'nama_item' => 'Milik O2', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'is_active' => true]);
        $ids = Item::milikCabang($this->o1->id)->pluck('id');
        $this->assertTrue($ids->contains($this->bahan->id), 'belum dipecah tetap tampil');
        $this->assertFalse($ids->contains($milikO2->id));
    }
}
