<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCabang;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\StockBatch;
use App\Services\HargaResepService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Fase 2 pemisahan data per cabang (CLAUDE.md 4.39): semua menu mengikuti
 * cabang aktif di header, harga resep FIFO, Mode Harga & Wajib dihapus.
 */
class DataPerCabangFase2Test extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $a;
    private Cabang $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Cabang::create(['nama_cabang' => 'Outlet F2 A', 'kode_cabang' => 'F2A' . rand(100, 999), 'tipe' => 'cabang', 'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true]);
        $this->b = Cabang::create(['nama_cabang' => 'Outlet F2 B', 'kode_cabang' => 'F2B' . rand(100, 999), 'tipe' => 'cabang', 'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true]);
    }

    private function bahan(Cabang $c, array $extra = []): Item
    {
        return Item::create(array_merge(['cabang_id' => $c->id, 'kode_item' => 'F2-' . uniqid(), 'nama_item' => 'Bahan F2 ' . uniqid(), 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => 100, 'is_active' => true, 'track_stok' => true], $extra));
    }

    private function produk(Cabang $c, array $extra = []): Item
    {
        $kategori = \App\Models\ItemCategory::where('kode_kategori', '!=', 'TMB')->value('id');

        return Item::create(array_merge(['cabang_id' => $c->id, 'item_category_id' => $kategori, 'kode_item' => 'F2P-' . uniqid(), 'nama_item' => 'Dimsum F2 ' . uniqid(), 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 1500, 'is_active' => true, 'track_stok' => false], $extra));
    }

    private function owner(?Cabang $aktif)
    {
        return $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $aktif?->id]);
    }

    public function test_daftar_bahan_hanya_milik_cabang_aktif_dan_semua_cabang_tampil_lengkap(): void
    {
        $milikA = $this->bahan($this->a, ['nama_item' => 'Bahan F2 Milik A']);
        $milikB = $this->bahan($this->b, ['nama_item' => 'Bahan F2 Milik B']);

        $this->owner($this->a)->get(route('master.bahan-baku.index', ['search' => 'Bahan F2 Milik']))->assertOk()
            ->assertSee('Bahan F2 Milik A')->assertDontSee('Bahan F2 Milik B')->assertSee('Data milik');

        $this->owner(null)->get(route('master.bahan-baku.index', ['search' => 'Bahan F2 Milik']))->assertOk()
            ->assertSee('Bahan F2 Milik A')->assertSee('Bahan F2 Milik B')->assertSee('Outlet F2 B');
    }

    public function test_tambah_bahan_jadi_milik_cabang_aktif_dan_kode_unik_per_cabang(): void
    {
        $kode = 'F2K-' . uniqid();
        $payload = fn ($nama) => ['kode_item' => $kode, 'nama_item' => $nama, 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'is_active' => 1, 'track_stok' => 1, 'qty_minimum' => 3, 'stok_awal' => 7];

        $this->owner($this->a)->post(route('master.bahan-baku.store'), $payload('Gula A'))->assertRedirect(route('master.bahan-baku.index'));
        $itemA = Item::where('kode_item', strtoupper($kode))->where('cabang_id', $this->a->id)->firstOrFail();
        $this->assertEquals(7, (float) $itemA->stocks()->where('lokasi_id', $this->a->id)->value('qty'));
        $this->assertEquals(3, (float) $itemA->stocks()->where('lokasi_id', $this->a->id)->value('qty_minimum'));

        // Kode sama di cabang lain boleh, di cabang sama ditolak
        $this->owner($this->b)->post(route('master.bahan-baku.store'), $payload('Gula B'))->assertRedirect(route('master.bahan-baku.index'));
        $this->assertTrue(Item::where('kode_item', strtoupper($kode))->where('cabang_id', $this->b->id)->exists());
        $this->owner($this->a)->post(route('master.bahan-baku.store'), $payload('Gula A2'))->assertSessionHasErrors('kode_item');

        // Mode Semua Cabang: pilih cabang di form
        $kode2 = 'F2S-' . uniqid();
        $this->owner(null)->post(route('master.bahan-baku.store'), array_merge($payload('Garam'), ['kode_item' => $kode2, 'cabang_id' => $this->b->id]))->assertRedirect();
        $this->assertSame($this->b->id, Item::where('kode_item', strtoupper($kode2))->value('cabang_id'));
    }

    public function test_manajer_tidak_bisa_ubah_barang_cabang_lain(): void
    {
        $manajer = $this->buatUser('manajer_cabang', $this->a->id);
        $milikB = $this->bahan($this->b);

        $this->actingAs($manajer)->withSession(['active_cabang_id' => $this->a->id])->get(route('master.bahan-baku.edit', $milikB))->assertForbidden();
        $this->actingAs($manajer)->withSession(['active_cabang_id' => $this->a->id])->get(route('master.bahan-baku.index', ['search' => 'Bahan F2']))
            ->assertOk()->assertDontSee($milikB->nama_item);
    }

    public function test_harga_resep_fifo_batch_tertua_lalu_po_terakhir_lalu_standar(): void
    {
        $svc = app(HargaResepService::class);
        $ada = $this->bahan($this->a, ['harga_beli_terakhir' => 900, 'harga_po_terakhir' => 1300]);
        StockBatch::create(['item_id' => $ada->id, 'lokasi_id' => $this->a->id, 'qty_awal' => 10, 'qty_sisa' => 0, 'harga_beli_per_unit' => 800, 'tanggal_masuk' => now()->subDays(5), 'referensi_type' => 'test']);
        StockBatch::create(['item_id' => $ada->id, 'lokasi_id' => $this->a->id, 'qty_awal' => 10, 'qty_sisa' => 3, 'harga_beli_per_unit' => 1000, 'tanggal_masuk' => now()->subDays(3), 'referensi_type' => 'test']);
        StockBatch::create(['item_id' => $ada->id, 'lokasi_id' => $this->a->id, 'qty_awal' => 10, 'qty_sisa' => 10, 'harga_beli_per_unit' => 1200, 'tanggal_masuk' => now()->subDay(), 'referensi_type' => 'test']);

        $h = $svc->satu($ada);
        $this->assertEquals(1000, $h['harga'], 'batch tertua yang masih bersisa');
        $this->assertSame('fifo', $h['sumber']);
        $this->assertFalse($h['habis']);

        $habis = $this->bahan($this->a, ['harga_beli_terakhir' => 900, 'harga_po_terakhir' => 1300]);
        $this->assertEquals(1300, $svc->satu($habis)['harga']);
        $this->assertTrue($svc->satu($habis)['habis']);

        $baru = $this->bahan($this->a, ['harga_beli_terakhir' => 900]);
        $this->assertEquals(900, $svc->satu($baru)['harga']);
        $this->assertSame('standar', $svc->satu($baru)['sumber']);
    }

    public function test_form_produk_bahan_milik_cabang_harga_fifo_tanpa_mode_harga_dan_wajib(): void
    {
        $bahanA = $this->bahan($this->a, ['nama_item' => 'Sirup F2 A']);
        StockBatch::create(['item_id' => $bahanA->id, 'lokasi_id' => $this->a->id, 'qty_awal' => 5, 'qty_sisa' => 5, 'harga_beli_per_unit' => 1100, 'tanggal_masuk' => now(), 'referensi_type' => 'test']);
        $bahanB = $this->bahan($this->b, ['nama_item' => 'Sirup F2 B']);

        $res = $this->owner($this->a)->get(route('master.produk-jual.create'))->assertOk()
            ->assertSee('Sirup F2 A')->assertDontSee('Sirup F2 B')
            ->assertDontSee('Mode Harga <x-tooltip', false)
            ->assertDontSee('name="resep[${idx}][mode_harga]"', false)
            ->assertDontSee('name="resep[${idx}][is_wajib]"', false)
            ->assertSee('FIFO');
        $this->assertStringContainsString('"harga":1100', $res->getContent());

        // Simpan: bahan cabang lain diabaikan, produk milik cabang aktif
        $kode = 'F2PJ' . rand(1000, 9999);
        $this->owner($this->a)->post(route('master.produk-jual.store'), [
            'kode_item' => $kode, 'nama_item' => 'Es Sirup F2', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => '5.000', 'is_active' => 1,
            'resep' => [['item_id' => $bahanA->id, 'qty_per_unit' => 1, 'satuan' => 'pcs'], ['item_id' => $bahanB->id, 'qty_per_unit' => 1, 'satuan' => 'pcs']],
        ])->assertRedirect();
        $produk = Item::where('kode_item', strtoupper($kode))->firstOrFail();
        $this->assertSame($this->a->id, $produk->cabang_id);
        $resep = ResepBumbu::where('item_id', $produk->id)->first();
        $this->assertSame($this->a->id, $resep->cabang_id);
        $this->assertSame([$bahanA->id], $resep->items()->pluck('item_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('pakai_master', $resep->items()->value('mode_harga'));
    }

    public function test_hpp_bumbu_pusat_menghitung_semua_bahan_termasuk_yang_dulu_gratis(): void
    {
        $owner = $this->buatUser('owner');
        $bahan = $this->bahan($this->a, ['satuan' => 'kg', 'harga_beli_terakhir' => 2000]);
        $bumbu = ResepBumbu::create(['cabang_id' => $this->a->id, 'nama' => 'Bumbu F2', 'kode' => 'BF2-' . uniqid(), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahan->id, 'qty_per_unit' => 0.5, 'satuan' => 'kg', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);

        $this->actingAs($owner)->postJson(route('master.produk-jual.preview-bumbu', $bumbu), ['qty_per_unit' => 1])
            ->assertOk()->assertJsonPath('subtotal', 1000);

        $this->actingAs($owner)->withSession(['active_cabang_id' => $this->a->id])->get(route('master.resep-bumbu.edit', $bumbu))->assertOk()
            ->assertSee('Rp 1.000')->assertDontSee('isWajibCheck', false)->assertDontSee('modeHargaSelect', false);
    }

    public function test_bumbu_pusat_tidak_boleh_berisi_bahan_cabang_lain(): void
    {
        $bumbu = ResepBumbu::create(['cabang_id' => $this->a->id, 'nama' => 'Bumbu F2 X', 'kode' => 'BF2X-' . uniqid(), 'item_id' => null, 'is_active' => true]);
        $milikB = $this->bahan($this->b);

        $this->owner($this->a)->post(route('master.resep-bumbu.items.store', $bumbu), ['item_id' => $milikB->id, 'qty_per_unit' => 1, 'satuan' => 'pcs'])
            ->assertSessionHasErrors('item_id');
        $this->assertSame(0, $bumbu->items()->count());
    }

    public function test_pos_hanya_produk_milik_cabang_dan_harga_produknya_sendiri(): void
    {
        $milikA = $this->produk($this->a, ['nama_item' => 'Dimsum F2 Punya A', 'harga_jual' => 1500]);
        $milikB = $this->produk($this->b, ['nama_item' => 'Dimsum F2 Punya B']);
        // Baris item_cabang lama tidak boleh mengalahkan harga barang milik cabang
        ItemCabang::create(['item_id' => $milikA->id, 'cabang_id' => $this->a->id, 'harga_override' => 999, 'is_active' => true]);

        $this->assertEquals(1500, $milikA->fresh('itemCabang')->hargaEfektifDiCabang($this->a->id));
        $this->assertFalse($milikA->fresh('itemCabang')->tersediaDiCabang($this->b->id));

        $kasir = $this->buatUser('kasir', $this->a->id);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->a->id])->get(route('penjualan.pos'))->assertOk()
            ->assertSee('Dimsum F2 Punya A')->assertDontSee('Dimsum F2 Punya B');
    }

    public function test_daftar_po_ikut_cabang_aktif(): void
    {
        $supplier = \App\Models\Supplier::create(['nama_supplier' => 'Sup F2', 'kode_supplier' => 'SF2' . rand(100, 999), 'is_active' => true]);
        $poA = \App\Models\PurchaseOrder::create(['cabang_id' => $this->a->id, 'supplier_id' => $supplier->id, 'nomor_po' => 'PO-F2A-' . uniqid(), 'tanggal_po' => now()->toDateString(), 'status' => 'draft', 'total_harga' => 0]);
        $poB = \App\Models\PurchaseOrder::create(['cabang_id' => $this->b->id, 'supplier_id' => $supplier->id, 'nomor_po' => 'PO-F2B-' . uniqid(), 'tanggal_po' => now()->toDateString(), 'status' => 'draft', 'total_harga' => 0]);

        $this->owner($this->a)->get(route('pembelian.index'))->assertOk()->assertSee($poA->nomor_po)->assertDontSee($poB->nomor_po);

        $manajer = $this->buatUser('manajer_cabang', $this->b->id);
        $this->actingAs($manajer)->withSession(['active_cabang_id' => $this->b->id])->get(route('pembelian.index'))->assertOk()
            ->assertSee($poB->nomor_po)->assertDontSee($poA->nomor_po);
    }

    public function test_po_menolak_barang_milik_cabang_lain(): void
    {
        $supplier = \App\Models\Supplier::create(['nama_supplier' => 'Sup F2 PO', 'kode_supplier' => 'SFP' . rand(100, 999), 'is_active' => true]);
        $milikB = $this->bahan($this->b);

        $this->owner($this->a)->post(route('pembelian.store'), [
            'supplier_id' => $supplier->id, 'cabang_id' => $this->a->id, 'tanggal_po' => now()->toDateString(),
            'items' => [['item_id' => $milikB->id, 'qty_pesan' => 5, 'harga_satuan' => 100]],
        ])->assertSessionHasErrors('items');
    }
}
