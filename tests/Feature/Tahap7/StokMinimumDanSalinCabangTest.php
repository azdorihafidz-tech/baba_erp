<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCabang;
use App\Models\Panduan;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\Tooltip;
use App\Services\StokService;
use Database\Seeders\Perbaikan438PanduanSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Audit menu stok (Qty Minimum per lokasi, harga adjustment), salin cabang
 * untuk data lama, dan panduan pemisahan data per cabang (CLAUDE.md 4.38/4.39).
 */
class StokMinimumDanSalinCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private int $seq = 0;

    private function cabang(string $nama, string $tipe = 'cabang'): Cabang
    {
        return Cabang::create(['nama_cabang' => $nama, 'kode_cabang' => 'TF' . (++$this->seq) . rand(100, 999), 'tipe' => $tipe, 'is_active' => true]);
    }

    private function bahan(float $harga = 100, array $extra = []): Item
    {
        return Item::create(array_merge([
            'kode_item' => 'TF-' . uniqid(), 'nama_item' => 'Bahan TF ' . uniqid(), 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => $harga, 'is_active' => true, 'track_stok' => true, 'qty_minimum' => 5,
        ], $extra));
    }

    private function produk(float $harga = 1200, array $extra = []): Item
    {
        return Item::create(array_merge([
            'kode_item' => 'TFP-' . uniqid(), 'nama_item' => 'Dimsum TF ' . uniqid(), 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => $harga, 'is_active' => true, 'track_stok' => false,
        ], $extra));
    }

    // ===== Tambah cabang: salin pengaturan =====

    public function test_tambah_cabang_salin_harga_dan_pengaturan_dari_cabang_lain(): void
    {
        $owner = $this->buatUser('owner');
        $sumber = $this->cabang('Outlet TF Sumber');
        $p = $this->produk(1200);
        $tidakDijual = $this->produk(900);
        $b = $this->bahan(100);
        ItemCabang::create(['item_id' => $p->id, 'cabang_id' => $sumber->id, 'harga_override' => 1500, 'is_active' => true]);
        ItemCabang::create(['item_id' => $tidakDijual->id, 'cabang_id' => $sumber->id, 'is_active' => false]);
        ItemCabang::create(['item_id' => $b->id, 'cabang_id' => $sumber->id, 'harga_beli' => 140, 'is_active' => true]);
        Stock::create(['item_id' => $b->id, 'lokasi_id' => $sumber->id, 'qty' => 50, 'qty_minimum' => 25]);

        $this->actingAs($owner)->get(route('cabang.create'))->assertOk()->assertSee('Salin Harga &amp; Pengaturan Barang dari', false);

        $kode = 'TFN' . rand(100, 999);
        $this->actingAs($owner)->post(route('cabang.store'), [
            'kode_cabang' => $kode, 'nama_cabang' => 'Outlet TF Baru Salin', 'tipe' => 'cabang',
            'salin_dari_cabang_id' => $sumber->id,
        ])->assertRedirect(route('cabang.index'));

        $baru = Cabang::where('kode_cabang', strtoupper($kode))->firstOrFail();
        $this->assertEquals(1500, $p->fresh('itemCabang')->hargaEfektifDiCabang($baru->id));
        $this->assertFalse($tidakDijual->fresh('itemCabang')->tersediaDiCabang($baru->id));
        $this->assertEquals(140, $b->fresh()->hargaBeliDiCabang($baru->id));
        $st = Stock::where('item_id', $b->id)->where('lokasi_id', $baru->id)->first();
        $this->assertEquals(25, (float) $st->qty_minimum);
        $this->assertEquals(0, (float) $st->qty, 'stok tidak ikut disalin');
    }

    // ===== Audit menu stok =====

    public function test_kartu_stok_tampilkan_minimum_lokasi(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TF Kartu');
        $item = $this->bahan(100, ['qty_minimum' => 3]);
        Stock::create(['item_id' => $item->id, 'lokasi_id' => $cab->id, 'qty' => 9, 'qty_minimum' => 11]);

        $this->actingAs($owner)->get(route('stok.kartu', ['item_id' => $item->id, 'lokasi_id' => $cab->id]))->assertOk()
            ->assertSee('Outlet TF Kartu')
            ->assertSeeInOrder(['11', 'Minimum (pcs)']);
    }

    public function test_laporan_stok_minimum_abaikan_barang_tanpa_minimum(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TF Laporan');
        $tanpaMin = $this->bahan(100, ['nama_item' => 'Bahan TF Tanpa Minimum', 'qty_minimum' => 0]);
        $menipis = $this->bahan(100, ['nama_item' => 'Bahan TF Menipis']);
        Stock::create(['item_id' => $tanpaMin->id, 'lokasi_id' => $cab->id, 'qty' => 0, 'qty_minimum' => 0]);
        Stock::create(['item_id' => $menipis->id, 'lokasi_id' => $cab->id, 'qty' => 2, 'qty_minimum' => 10]);

        $this->actingAs($owner)->get(route('laporan.stok.minimum', ['lokasi_id' => $cab->id]))->assertOk()
            ->assertSee('Bahan TF Menipis')
            ->assertDontSee('Bahan TF Tanpa Minimum');
    }

    public function test_adjustment_tambah_stok_tanpa_harga_pakai_harga_outlet(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TF Adj');
        $item = $this->bahan(100);
        ItemCabang::create(['item_id' => $item->id, 'cabang_id' => $cab->id, 'harga_beli' => 140, 'is_active' => true]);

        $this->actingAs($owner);
        app(StokService::class)->adjustment($item->id, $cab->id, 10, 'opname test');

        $batch = StockBatch::where('item_id', $item->id)->where('lokasi_id', $cab->id)->latest('id')->first();
        $this->assertEquals(140, (float) $batch->harga_beli_per_unit);

        $this->actingAs($owner)->getJson(route('api.stok.qty', ['item_id' => $item->id, 'lokasi_id' => $cab->id]))
            ->assertOk()->assertJsonPath('harga_beli_terakhir', 140);
    }

    public function test_panduan_dan_tooltip_data_per_cabang(): void
    {
        $this->seed(Perbaikan438PanduanSeeder::class);
        $this->seed(Perbaikan438PanduanSeeder::class); // idempotent

        $bahan = (string) Panduan::where('slug', 'bahan-baku')->value('konten');
        $this->assertSame(1, substr_count($bahan, '<!-- PEMISAHAN-CABANG-439 -->'));
        $this->assertStringContainsString('FIFO Batch Detail', $bahan);
        // Teks lama "harga beli per outlet" (belum pernah dirilis) sudah dibuang dari seeder
        $this->assertStringNotContainsString('Harga Beli per Outlet', file_get_contents(database_path('seeders/Perbaikan438PanduanSeeder.php')));
        $this->assertStringContainsString('Mode Harga', (string) Panduan::where('slug', 'produk-jual')->value('konten'));
        foreach (['master_bahan_baku.qty_minimum_cabang', 'master_bahan_baku.fifo_batch', 'master_produk_jual.harga_master_preview', 'cabang.salin_pengaturan'] as $key) {
            $this->assertTrue(Tooltip::where('key', $key)->exists(), $key);
        }
    }
}
