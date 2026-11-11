<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Services\KetersediaanProdukService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * CLAUDE.md 4.40 — "Stok tersedia" di POS dihitung dari bahan resep (dulu
 * selalu 0 krn produk jual tidak menyimpan stok sendiri), termasuk bahan di
 * dalam Bumbu Pusat; tanda Stok Habis memakai rumus yang sama.
 */
class StokTersediaPosTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = Cabang::create(['nama_cabang' => 'Outlet ST', 'kode_cabang' => 'ST' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true]);
    }

    private function bahan(string $nama, float $stok, string $satuan = 'pcs'): Item
    {
        $b = Item::create(['cabang_id' => $this->c->id, 'kode_item' => 'ST-' . uniqid(), 'nama_item' => $nama, 'tipe' => 'bahan_baku', 'satuan' => $satuan, 'is_active' => true, 'track_stok' => true]);
        if ($stok > 0) Stock::create(['item_id' => $b->id, 'lokasi_id' => $this->c->id, 'qty' => $stok, 'qty_minimum' => 0]);

        return $b;
    }

    private function produk(string $nama, array $baris): Item
    {
        $kategori = \App\Models\ItemCategory::where('kode_kategori', '!=', 'TMB')->value('id');
        $p = Item::create(['cabang_id' => $this->c->id, 'item_category_id' => $kategori, 'kode_item' => 'STP-' . uniqid(), 'nama_item' => $nama, 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 20000, 'is_active' => true, 'track_stok' => false]);
        $resep = ResepBumbu::create(['cabang_id' => $this->c->id, 'nama' => $nama, 'kode' => 'R' . rand(10000, 99999), 'item_id' => $p->id, 'is_active' => true]);
        foreach ($baris as $i => $r) {
            ResepBumbuItem::create(array_merge(['resep_bumbu_id' => $resep->id, 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => $i], $r));
        }

        return $p->fresh();
    }

    public function test_porsi_dari_bahan_paling_sedikit(): void
    {
        $dimsum = $this->bahan('Dimsum ST', 40);
        $kotak = $this->bahan('Kotak ST', 6);
        $p = $this->produk('Try Box ST', [
            ['item_id' => $dimsum->id, 'qty_per_unit' => 4, 'satuan' => 'pcs'],
            ['item_id' => $kotak->id, 'qty_per_unit' => 1, 'satuan' => 'pcs'],
        ]);

        $s = app(KetersediaanProdukService::class)->untuk(collect([$p]), $this->c->id)[$p->id];
        $this->assertEquals(6, $s['jumlah']);
        $this->assertSame('Kotak ST', $s['terbatas']);
        $this->assertTrue($s['bisa_dijual']);
        $this->assertSame('Stok tersedia: 6 porsi (terbatas: Kotak ST)', $s['info']);
        $this->assertFalse($s['menipis']);
    }

    public function test_bahan_di_dalam_bumbu_pusat_ikut_dihitung_dan_habis(): void
    {
        $dimsum = $this->bahan('Dimsum ST2', 40);
        $saus = $this->bahan('Saus ST2', 0.05, 'kg'); // cukup utk 2 porsi (0.02 kg/porsi)
        $bumbu = ResepBumbu::create(['cabang_id' => $this->c->id, 'nama' => 'Bumbu ST2', 'kode' => 'B' . rand(10000, 99999), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $saus->id, 'qty_per_unit' => 20, 'satuan' => 'gram', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);
        $p = $this->produk('Chizu ST2', [
            ['item_id' => $dimsum->id, 'qty_per_unit' => 4, 'satuan' => 'pcs'],
            ['resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 1, 'satuan' => 'porsi'],
        ]);

        $s = app(KetersediaanProdukService::class)->untuk(collect([$p]), $this->c->id)[$p->id];
        $this->assertEquals(2, $s['jumlah']);
        $this->assertSame('Saus ST2', $s['terbatas']);
        $this->assertTrue($s['menipis']);

        Stock::where('item_id', $saus->id)->update(['qty' => 0.01]);
        $this->assertFalse($p->fresh()->bisaDijualDiCabang($this->c->id), 'Stok Habis kalau bahan Bumbu Pusat habis');
    }

    public function test_produk_tanpa_resep_pakai_stoknya_sendiri(): void
    {
        $kategori = \App\Models\ItemCategory::where('kode_kategori', '!=', 'TMB')->value('id');
        $p = Item::create(['cabang_id' => $this->c->id, 'item_category_id' => $kategori, 'kode_item' => 'STN-' . uniqid(), 'nama_item' => 'Air Mineral ST', 'tipe' => 'produk_jual', 'satuan' => 'botol', 'harga_jual' => 5000, 'is_active' => true]);
        Stock::create(['item_id' => $p->id, 'lokasi_id' => $this->c->id, 'qty' => 12, 'qty_minimum' => 0]);

        $s = app(KetersediaanProdukService::class)->untuk(collect([$p]), $this->c->id)[$p->id];
        $this->assertSame('Stok tersedia: 12 botol', $s['info']);
        $this->assertTrue($s['bisa_dijual']);
    }

    public function test_resep_satu_bahan_pakai_satuan_produk_tanpa_keterangan_terbatas(): void
    {
        $sumpit = $this->bahan('Sumpit ST', 350);
        $p = $this->produk('Sumpit Jual ST', [['item_id' => $sumpit->id, 'qty_per_unit' => 1, 'satuan' => 'pcs']]);
        $p->update(['satuan' => 'pcs']);

        $s = app(KetersediaanProdukService::class)->untuk(collect([$p->fresh()]), $this->c->id)[$p->id];
        $this->assertSame('Stok tersedia: 350 pcs', $s['info']);
    }

    public function test_pos_menampilkan_stok_tersedia_dari_resep_bukan_nol(): void
    {
        $dimsum = $this->bahan('Dimsum ST3', 40);
        $kotak = $this->bahan('Kotak ST3', 6);
        $p = $this->produk('Best Box ST3', [
            ['item_id' => $dimsum->id, 'qty_per_unit' => 4, 'satuan' => 'pcs'],
            ['item_id' => $kotak->id, 'qty_per_unit' => 1, 'satuan' => 'pcs'],
        ]);

        $html = $this->actingAs($this->buatUser('kasir', $this->c->id))->withSession(['active_cabang_id' => $this->c->id])
            ->get(route('penjualan.pos'))->assertOk()->getContent();
        $this->assertStringContainsString('Stok tersedia: 6 porsi (terbatas: Kotak ST3)', $html);
        $this->assertStringContainsString('"stok_singkat":"6 porsi"', $html);
        $this->assertStringContainsString('opt.dataset.stokInfo', $html);
    }
}
