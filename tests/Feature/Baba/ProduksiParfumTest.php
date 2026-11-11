<?php

namespace Tests\Feature\Baba;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ParfumKonsentrasi;
use App\Models\ProduksiBatch;
use App\Models\ResepProduksi;
use App\Models\Stock;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * ERP BABA Tahap 1a — produksi parfum 2 tingkat:
 * racik cairan (bibit + fixative + alkohol, diperam 14 hari) → selesai peram & saring (susut) →
 * isi botol (cairan + botol + sprayer) dengan modal berantai.
 */
class ProduksiParfumTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Item $bibit;
    private Item $fixative;
    private Item $alkohol;
    private Item $botol;
    private Item $sprayer;
    private Item $cairan;
    private Item $parfum50;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang Parfum Uji ' . uniqid(), 'kode_cabang' => 'GP' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);

        $this->bibit = $this->barang('Bibit Ocean Uji', 'ml', 1500);       // Rp 1.500 / ml
        $this->fixative = $this->barang('Fixative Uji', 'ml', 400);
        $this->alkohol = $this->barang('Alkohol Uji', 'liter', 60000);     // stok liter, takaran ml
        $this->botol = $this->barang('Botol 50 ml Uji', 'pcs', 5000);
        $this->sprayer = $this->barang('Sprayer Uji', 'pcs', 1500);
        $this->cairan = $this->barang('Cairan Ocean EDP Uji', 'ml', 0);
        $this->parfum50 = $this->barang('Ocean EDP 50 ml Uji', 'pcs', 0);

        $this->actingAs($this->buatUser('owner'));
        $stok = app(StokService::class);
        $stok->masuk($this->bibit->id, $this->gudang->id, 1000, 'uji', 'uji', null, 1500);
        $stok->masuk($this->fixative->id, $this->gudang->id, 500, 'uji', 'uji', null, 400);
        $stok->masuk($this->alkohol->id, $this->gudang->id, 10, 'uji', 'uji', null, 60000);
        $stok->masuk($this->botol->id, $this->gudang->id, 100, 'uji', 'uji', null, 5000);
        $stok->masuk($this->sprayer->id, $this->gudang->id, 100, 'uji', 'uji', null, 1500);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function barang(string $nama, string $satuan, float $harga): Item
    {
        return Item::create([
            'kode_item' => 'PFM-' . strtoupper(substr(md5($nama . uniqid()), 0, 8)), 'nama_item' => $nama,
            'tipe' => 'bahan_baku', 'satuan' => $satuan, 'harga_beli_terakhir' => $harga, 'harga_jual' => 0,
            'is_active' => true, 'cabang_id' => $this->gudang->id,
        ]);
    }

    private function stok(Item $i): float
    {
        return (float) Stock::where('item_id', $i->id)->where('lokasi_id', $this->gudang->id)->value('qty');
    }

    /** Resep racik EDP 20% bibit + 5% fixative + 75% alkohol untuk 1.000 ml, diperam 14 hari. */
    private function resepRacik(int $peram = 14): ResepProduksi
    {
        $this->post(route('produksi.resep.store'), [
            'cabang_id' => $this->gudang->id, 'item_id' => $this->cairan->id, 'qty_hasil' => 1000, 'lama_peram_hari' => $peram, 'is_active' => 1,
            'bahan' => [
                ['item_id' => $this->bibit->id, 'qty' => 200, 'satuan' => 'ml'],
                ['item_id' => $this->fixative->id, 'qty' => 50, 'satuan' => 'ml'],
                ['item_id' => $this->alkohol->id, 'qty' => 750, 'satuan' => 'ml'],
            ],
        ])->assertRedirect(route('produksi.resep.index'))->assertSessionHas('success');

        return ResepProduksi::where('item_id', $this->cairan->id)->firstOrFail();
    }

    private function racik(ResepProduksi $resep, float $ml = 2000): ProduksiBatch
    {
        $this->post(route('produksi.store'), [
            'cabang_id' => $this->gudang->id, 'resep_produksi_id' => $resep->id, 'tanggal' => '2026-11-12', 'qty_rencana' => $ml,
        ])->assertSessionHas('success');

        return ProduksiBatch::where('item_id', $this->cairan->id)->latest('id')->firstOrFail();
    }

    public function test_konsentrasi_bawaan_dan_persen_alkohol(): void
    {
        $edp = ParfumKonsentrasi::where('nama', 'EDP')->firstOrFail();
        $this->assertSame(75.0, $edp->persen_alkohol);
        $this->get(route('produksi.konsentrasi.index'))->assertOk()->assertSee('Eau de Parfum')->assertSee('75%');
    }

    public function test_tambah_ubah_konsentrasi_dan_validasi_persen(): void
    {
        $this->post(route('produksi.konsentrasi.store'), ['nama' => 'Uji Body Mist', 'persen_bibit' => 3, 'persen_fixative' => 1, 'lama_peram_hari' => 2, 'is_active' => 1])
            ->assertSessionHas('success');
        $k = ParfumKonsentrasi::where('nama', 'Uji Body Mist')->firstOrFail();
        $this->assertSame(96.0, $k->persen_alkohol);

        $this->put(route('produksi.konsentrasi.update', $k), ['nama' => 'Uji Body Mist', 'persen_bibit' => 80, 'persen_fixative' => 25, 'lama_peram_hari' => 2])
            ->assertSessionHasErrors('persen_bibit');
        $this->post(route('produksi.konsentrasi.store'), ['nama' => 'EDP', 'persen_bibit' => 20, 'lama_peram_hari' => 1])
            ->assertSessionHasErrors('nama');
    }

    public function test_racik_berpemeraman_memotong_bahan_tanpa_menambah_stok_cairan(): void
    {
        $batch = $this->racik($this->resepRacik(), 2000);

        $this->assertSame('diperam', $batch->status);
        $this->assertSame('2026-11-26', $batch->tanggal_siap->toDateString());
        $this->assertEqualsWithDelta(600, $this->stok($this->bibit), 0.001);     // 1000 − 400
        $this->assertEqualsWithDelta(400, $this->stok($this->fixative), 0.001);  // 500 − 100
        $this->assertEqualsWithDelta(8.5, $this->stok($this->alkohol), 0.001);   // 10 − 1,5 liter
        $this->assertEqualsWithDelta(0, $this->stok($this->cairan), 0.001);      // belum masuk stok
        // biaya: 400×1500 + 100×400 + 1,5×60000 = 600.000 + 40.000 + 90.000
        $this->assertEqualsWithDelta(730000, (float) $batch->biaya_bahan, 0.01);

        $this->get(route('produksi.index'))->assertOk()->assertSee('Diperam · siap 26/11');
        $this->get(route('produksi.show', $batch))->assertOk()->assertSee('Tombol aktif mulai 26/11/2026');
    }

    public function test_selesai_peram_ditolak_sebelum_tanggal_siap(): void
    {
        $batch = $this->racik($this->resepRacik(), 2000);
        $this->post(route('produksi.selesai-peram', $batch), ['qty_hasil' => 1950])->assertSessionHas('error');
        $this->assertSame('diperam', $batch->fresh()->status);
        $this->assertEqualsWithDelta(0, $this->stok($this->cairan), 0.001);
    }

    public function test_selesai_peram_menambah_stok_dengan_susut_dan_modal(): void
    {
        $batch = $this->racik($this->resepRacik(), 2000);
        Carbon::setTestNow('2026-11-26 09:00:00');

        $this->post(route('produksi.selesai-peram', $batch), ['qty_hasil' => 2001])->assertSessionHas('error'); // > diracik
        $this->post(route('produksi.selesai-peram', $batch), ['qty_hasil' => 1950])->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame('selesai', $batch->status);
        $this->assertEqualsWithDelta(1950, $this->stok($this->cairan), 0.001);
        $this->assertEqualsWithDelta(50, $batch->susut, 0.001);
        $this->assertEqualsWithDelta(374.36, (float) $batch->modal_per_unit, 0.01); // 730.000 ÷ 1.950 ml
        $this->assertNotNull($batch->diselesaikan_at);
        $this->get(route('produksi.show', $batch))->assertOk()->assertSee('susut 50');
    }

    public function test_batal_saat_diperam_mengembalikan_bahan(): void
    {
        $batch = $this->racik($this->resepRacik(), 2000);
        $this->post(route('produksi.batalkan', $batch), ['alasan' => 'salah racik'])->assertSessionHas('success');

        $this->assertSame('dibatalkan', $batch->fresh()->status);
        $this->assertEqualsWithDelta(1000, $this->stok($this->bibit), 0.001);
        $this->assertEqualsWithDelta(10, $this->stok($this->alkohol), 0.001);
        $this->assertEqualsWithDelta(0, $this->stok($this->cairan), 0.001);
    }

    public function test_isi_botol_memakai_cairan_dengan_modal_berantai(): void
    {
        $racik = $this->racik($this->resepRacik(), 2000);
        Carbon::setTestNow('2026-11-26 09:00:00');
        $this->post(route('produksi.selesai-peram', $racik), ['qty_hasil' => 1950])->assertSessionHas('success');
        $modalMl = (float) $racik->fresh()->modal_per_unit;

        // Resep isi botol: 1 botol = 50 ml cairan + 1 botol + 1 sprayer, tanpa pemeraman
        $this->post(route('produksi.resep.store'), [
            'cabang_id' => $this->gudang->id, 'item_id' => $this->parfum50->id, 'qty_hasil' => 1, 'lama_peram_hari' => 0, 'is_active' => 1,
            'bahan' => [
                ['item_id' => $this->cairan->id, 'qty' => 50, 'satuan' => 'ml'],
                ['item_id' => $this->botol->id, 'qty' => 1, 'satuan' => 'pcs'],
                ['item_id' => $this->sprayer->id, 'qty' => 1, 'satuan' => 'pcs'],
            ],
        ])->assertSessionHas('success');
        $resep = ResepProduksi::where('item_id', $this->parfum50->id)->firstOrFail();

        $this->post(route('produksi.store'), [
            'cabang_id' => $this->gudang->id, 'resep_produksi_id' => $resep->id, 'tanggal' => '2026-11-26', 'qty_rencana' => 20, 'qty_hasil' => 20,
        ])->assertSessionHas('success');
        $isi = ProduksiBatch::where('item_id', $this->parfum50->id)->latest('id')->firstOrFail();

        $this->assertSame('selesai', $isi->status);
        $this->assertEqualsWithDelta(20, $this->stok($this->parfum50), 0.001);
        $this->assertEqualsWithDelta(950, $this->stok($this->cairan), 0.001);  // 1950 − 1000 ml
        $this->assertEqualsWithDelta(80, $this->stok($this->botol), 0.001);
        // modal per botol = 50 ml × modal cairan + botol 5.000 + sprayer 1.500
        $this->assertEqualsWithDelta(50 * $modalMl + 6500, (float) $isi->modal_per_unit, 1.0);
    }

    public function test_isi_botol_ditolak_selama_cairan_masih_diperam(): void
    {
        $this->racik($this->resepRacik(), 2000); // diperam, cairan belum ada di stok
        $this->post(route('produksi.resep.store'), [
            'cabang_id' => $this->gudang->id, 'item_id' => $this->parfum50->id, 'qty_hasil' => 1, 'is_active' => 1,
            'bahan' => [['item_id' => $this->cairan->id, 'qty' => 50, 'satuan' => 'ml'], ['item_id' => $this->botol->id, 'qty' => 1, 'satuan' => 'pcs']],
        ]);
        $resep = ResepProduksi::where('item_id', $this->parfum50->id)->firstOrFail();

        $this->post(route('produksi.store'), [
            'cabang_id' => $this->gudang->id, 'resep_produksi_id' => $resep->id, 'tanggal' => '2026-11-12', 'qty_rencana' => 1, 'qty_hasil' => 1,
        ])->assertSessionHas('error');
        $this->assertEqualsWithDelta(0, $this->stok($this->parfum50), 0.001);
    }

    public function test_resep_tanpa_pemeraman_tetap_seperti_semula(): void
    {
        $resep = $this->resepRacik(0);
        $this->post(route('produksi.store'), [
            'cabang_id' => $this->gudang->id, 'resep_produksi_id' => $resep->id, 'tanggal' => '2026-11-12', 'qty_rencana' => 1000, 'qty_hasil' => 980,
        ])->assertSessionHas('success');
        $b = ProduksiBatch::where('item_id', $this->cairan->id)->latest('id')->firstOrFail();
        $this->assertSame('selesai', $b->status);
        $this->assertNull($b->tanggal_siap);
        $this->assertEqualsWithDelta(980, $this->stok($this->cairan), 0.001);
    }
}
