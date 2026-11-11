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

/** ERP BABA Tahap 1b — Buat Parfum: cairan + resep racik (persen konsentrasi) + botol per ukuran + resep isi botol. */
class BuatParfumTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private array $b = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang BP Uji ' . uniqid(), 'kode_cabang' => 'BP' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        foreach ([
            'bibit' => ['Bibit Rose Uji', 'ml', 1200], 'fix' => ['Fixative Uji', 'ml', 300], 'alk' => ['Alkohol 96% Uji', 'liter', 50000],
            'b30' => ['Botol 30 ml Uji', 'pcs', 3000], 'b50' => ['Botol 50 ml Uji', 'pcs', 4000], 'spr' => ['Sprayer Uji', 'pcs', 1000], 'lbl' => ['Label Uji', 'pcs', 500],
        ] as $k => [$n, $s, $h]) {
            $this->b[$k] = Item::create(['kode_item' => 'BPU-' . strtoupper(substr(md5($n . uniqid()), 0, 8)), 'nama_item' => $n, 'tipe' => 'bahan_baku', 'satuan' => $s,
                'harga_beli_terakhir' => $h, 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $this->gudang->id]);
        }
        $this->actingAs($this->buatUser('owner'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function kirim(array $ubah = [])
    {
        return $this->post(route('produksi.parfum.store'), array_merge([
            'cabang_id' => $this->gudang->id, 'bibit_id' => $this->b['bibit']->id, 'nama_aroma' => 'Rose Uji',
            'konsentrasi_id' => ParfumKonsentrasi::where('nama', 'EDP')->value('id'),
            'fixative_id' => $this->b['fix']->id, 'alkohol_id' => $this->b['alk']->id,
            'ukuran' => [['ml' => 30, 'botol_id' => $this->b['b30']->id], ['ml' => 50, 'botol_id' => $this->b['b50']->id], ['ml' => '', 'botol_id' => '']],
            'kemasan_ids' => [$this->b['spr']->id, $this->b['lbl']->id],
        ], $ubah));
    }

    public function test_halaman_buat_parfum_tampil_dengan_tebakan_bahan(): void
    {
        $this->get(route('produksi.parfum.create', ['cabang_id' => $this->gudang->id]))->assertOk()
            ->assertSee('Buat Parfum')->assertSee('Yang akan dibuat')->assertSee('Bibit Rose Uji (ml)');
    }

    public function test_membuat_cairan_botol_dan_resep_sesuai_persen(): void
    {
        $this->kirim()->assertRedirect(route('produksi.resep.index'))->assertSessionHas('success');

        $cairan = Item::where('cabang_id', $this->gudang->id)->where('nama_item', 'Cairan Rose Uji EDP')->firstOrFail();
        $this->assertSame('ml', $cairan->satuan);
        $this->assertSame('Cairan Parfum', $cairan->category?->nama_kategori);
        $racik = ResepProduksi::with('items')->where('item_id', $cairan->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000, (float) $racik->qty_hasil, 0.001);
        $this->assertSame(14, $racik->lama_peram_hari);
        $takaran = $racik->items->mapWithKeys(fn ($i) => [$i->item_id => [(float) $i->qty, $i->satuan]]);
        $this->assertSame([200.0, 'ml'], $takaran[$this->b['bibit']->id]);
        $this->assertSame([50.0, 'ml'], $takaran[$this->b['fix']->id]);
        $this->assertSame([750.0, 'ml'], $takaran[$this->b['alk']->id]);

        foreach ([30 => 'b30', 50 => 'b50'] as $ml => $botol) {
            $item = Item::where('cabang_id', $this->gudang->id)->where('nama_item', "Rose Uji EDP {$ml} ml")->firstOrFail();
            $this->assertSame('pcs', $item->satuan);
            $this->assertSame('Parfum Botol', $item->category?->nama_kategori);
            $isi = ResepProduksi::with('items')->where('item_id', $item->id)->firstOrFail();
            $this->assertSame(0, $isi->lama_peram_hari);
            $q = $isi->items->mapWithKeys(fn ($i) => [$i->item_id => (float) $i->qty]);
            $this->assertEqualsWithDelta($ml, $q[$cairan->id], 0.001);
            $this->assertEqualsWithDelta(1, $q[$this->b[$botol]->id], 0.001);
            $this->assertEqualsWithDelta(1, $q[$this->b['spr']->id], 0.001);
            $this->assertEqualsWithDelta(1, $q[$this->b['lbl']->id], 0.001);
            $this->assertCount(4, $q);
        }
    }

    public function test_hasil_buat_parfum_bisa_diproduksi_sampai_botol(): void
    {
        $this->kirim()->assertSessionHas('success');
        $stok = app(StokService::class);
        foreach (['bibit' => 1000, 'fix' => 500, 'alk' => 5, 'b30' => 50, 'b50' => 50, 'spr' => 100, 'lbl' => 100] as $k => $q) {
            $stok->masuk($this->b[$k]->id, $this->gudang->id, $q, 'uji', 'uji', null, (float) $this->b[$k]->harga_beli_terakhir);
        }
        $cairan = Item::where('nama_item', 'Cairan Rose Uji EDP')->where('cabang_id', $this->gudang->id)->firstOrFail();
        $botol50 = Item::where('nama_item', 'Rose Uji EDP 50 ml')->where('cabang_id', $this->gudang->id)->firstOrFail();

        $this->post(route('produksi.store'), ['cabang_id' => $this->gudang->id, 'resep_produksi_id' => ResepProduksi::where('item_id', $cairan->id)->value('id'),
            'tanggal' => '2026-11-12', 'qty_rencana' => 2000])->assertSessionHas('success');
        $racik = ProduksiBatch::where('item_id', $cairan->id)->latest('id')->firstOrFail();
        $this->assertSame('diperam', $racik->status);

        Carbon::setTestNow('2026-11-26 08:00:00');
        $this->post(route('produksi.selesai-peram', $racik), ['qty_hasil' => 1980])->assertSessionHas('success');
        $this->post(route('produksi.store'), ['cabang_id' => $this->gudang->id, 'resep_produksi_id' => ResepProduksi::where('item_id', $botol50->id)->value('id'),
            'tanggal' => '2026-11-26', 'qty_rencana' => 10, 'qty_hasil' => 10])->assertSessionHas('success');

        $this->assertEqualsWithDelta(10, (float) Stock::where('item_id', $botol50->id)->where('lokasi_id', $this->gudang->id)->value('qty'), 0.001);
        $this->assertEqualsWithDelta(1480, (float) Stock::where('item_id', $cairan->id)->where('lokasi_id', $this->gudang->id)->value('qty'), 0.001);
    }

    public function test_tanpa_fixative_bila_konsentrasi_fixative_nol(): void
    {
        $k = ParfumKonsentrasi::create(['nama' => 'Uji Tanpa Fix', 'persen_bibit' => 15, 'persen_fixative' => 0, 'lama_peram_hari' => 0, 'is_active' => true]);
        $this->kirim(['konsentrasi_id' => $k->id, 'fixative_id' => ''])->assertSessionHas('success');
        $cairan = Item::where('nama_item', 'Cairan Rose Uji Uji Tanpa Fix')->firstOrFail();
        $racik = ResepProduksi::with('items')->where('item_id', $cairan->id)->firstOrFail();
        $this->assertCount(2, $racik->items);
        $this->assertSame(0, $racik->lama_peram_hari);
        $this->assertEqualsWithDelta(850, (float) $racik->items->firstWhere('item_id', $this->b['alk']->id)->qty, 0.001);
    }

    public function test_ditolak_bila_nama_sudah_ada_dan_tidak_ada_yang_tersimpan_setengah(): void
    {
        $this->kirim()->assertSessionHas('success');
        $jumlah = Item::where('cabang_id', $this->gudang->id)->count();
        $this->kirim()->assertSessionHas('error');
        $this->assertSame($jumlah, Item::where('cabang_id', $this->gudang->id)->count());
    }

    public function test_validasi_ukuran_dan_bahan(): void
    {
        $this->kirim(['ukuran' => [['ml' => '', 'botol_id' => '']]])->assertSessionHas('error');
        $this->kirim(['ukuran' => [['ml' => 30, 'botol_id' => $this->b['b30']->id], ['ml' => 30, 'botol_id' => $this->b['b50']->id]]])->assertSessionHas('error');
        $this->kirim(['alkohol_id' => $this->b['bibit']->id])->assertSessionHas('error');                 // bibit = alkohol
        $this->kirim(['kemasan_ids' => [$this->b['b30']->id]])->assertSessionHas('error');                 // botol dobel di kemasan
        $lain = Cabang::create(['nama_cabang' => 'Gudang Lain ' . uniqid(), 'kode_cabang' => 'GL' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $asing = Item::create(['kode_item' => 'ASG-' . rand(1000, 9999), 'nama_item' => 'Bibit Asing', 'tipe' => 'bahan_baku', 'satuan' => 'ml', 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $lain->id]);
        $this->kirim(['bibit_id' => $asing->id])->assertSessionHas('error');                               // barang gudang lain
        $this->assertSame(0, Item::where('nama_item', 'like', 'Cairan Rose Uji%')->count());
    }
}
