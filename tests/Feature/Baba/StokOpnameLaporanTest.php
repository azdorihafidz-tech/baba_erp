<?php

namespace Tests\Feature\Baba;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StokOpname;
use App\Models\StokOpnameItem;
use App\Models\User;
use App\Services\LaporanKerugianStokService;
use App\Services\StokOpnameService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 7b — Berita Acara Stok Opname (PDF/Excel), Laporan Kerugian Stok, tautan kartu stok. */
class StokOpnameLaporanTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $mitra;
    private StokService $stok;
    private StokOpnameService $svc;
    private array $b = [];
    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs($this->buatUser('owner'));
        $this->stok = app(StokService::class);
        $this->svc = app(StokOpnameService::class);
        $this->gudang = $this->lokasi('gudang_pusat', null);
        $this->mitra = $this->lokasi('cabang', 'kemitraan');
        $this->b = ['A' => $this->barang($this->gudang, 10, 20000), 'C' => $this->barang($this->gudang, 5, 50000), 'D' => $this->barang($this->gudang, 3, 10000),
            'M' => $this->barang($this->mitra, 6, 30000)];
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAAABJRU5ErkJggg==');
    }

    private function lokasi(string $tipe, ?string $jenis): Cabang
    {
        return Cabang::create(['nama_cabang' => 'Lokasi LAP ' . uniqid(), 'kode_cabang' => 'LP' . rand(10000, 99999), 'tipe' => $tipe, 'jenis_toko' => $jenis, 'is_active' => true]);
    }

    private function barang(Cabang $c, float $qty, float $harga): Item
    {
        $kat = ItemCategory::firstOrCreate(['kode_kategori' => 'PFM'], ['nama_kategori' => 'Parfum Botol']);
        $k = strtoupper(Str::random(6));
        $i = Item::create(['cabang_id' => $c->id, 'kode_item' => "LP{$k}", 'nama_item' => "Uji Laporan {$k}", 'item_category_id' => $kat->id,
            'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
        $this->stok->masuk($i->id, $c->id, $qty, 'stok awal', 'uji', null, $harga);

        return $i;
    }

    private function baris(StokOpname $o, Item $i): StokOpnameItem
    {
        return StokOpnameItem::where('stok_opname_id', $o->id)->where('item_id', $i->id)->firstOrFail();
    }

    /** Opname gudang: A kurang 3 (rusak 2 + foto, hilang 1), C lebih 1, D cocok. Dihitung helper, diajukan admin gudang. */
    private function opnameGudang(bool $setujui = true): array
    {
        $helper = $this->buatUser('helper', $this->gudang->id);
        $adminG = $this->buatUser('admin_gudang', $this->gudang->id);
        $this->actingAs($helper);
        $o = $this->svc->mulai($helper, $this->gudang, ['cakupan' => 'barang', 'barang' => [$this->b['A']->id, $this->b['C']->id, $this->b['D']->id]]);
        $this->svc->hitung($helper, $o, $this->baris($o, $this->b['A']), 7);
        $this->svc->hitung($helper, $o, $this->baris($o, $this->b['C']), 6);
        $this->svc->hitung($helper, $o, $this->baris($o, $this->b['D']), 3);
        $this->actingAs($adminG);
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['A']), [['penyebab' => 'rusak', 'qty' => 2, 'foto' => $this->png], ['penyebab' => 'hilang', 'qty' => 1]]);
        $o = $this->svc->ajukan($adminG, $o);
        if ($setujui) {
            $ap = $this->buatUser('admin_pusat');
            $this->actingAs($ap);
            $o = $this->svc->setujui($ap, $o);
        }

        return [$o, $helper, $adminG];
    }

    public function test_berita_acara_pdf_excel_dan_hak_unduh(): void
    {
        [$o, $helper] = $this->opnameGudang(false);
        $ap = $this->buatUser('admin_pusat');
        // diajukan (belum disetujui): penghitung buta tidak boleh mengunduh, pemeriksa boleh (SEMENTARA)
        $this->actingAs($helper)->get(route('stok-opname.berita-acara', $o))->assertForbidden();
        $this->actingAs($helper)->get(route('stok-opname.berita-acara.excel', $o))->assertForbidden();
        $ba = $this->svc->beritaAcara($o);
        $this->assertFalse($ba['final']);
        $this->assertEquals([3, 1, 1, 1, 60000, 50000, -10000], [$ba['ringkasan']['dihitung'], $ba['ringkasan']['cocok'], $ba['ringkasan']['kurang'], $ba['ringkasan']['lebih'],
            $ba['ringkasan']['nilai_kurang'], $ba['ringkasan']['nilai_lebih'], $ba['ringkasan']['nilai_bersih']]);
        $this->actingAs($ap)->get(route('stok-opname.berita-acara', $o))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($ap);
        $o = $this->svc->setujui($ap, $o);
        $ba = $this->svc->beritaAcara($o);
        $this->assertTrue($ba['final']);
        $this->assertEquals(['rusak' => 40000, 'hilang' => 20000, 'lebih' => 50000], array_map(fn ($p) => $p['nilai'], $ba['per_penyebab']));
        $this->assertCount(1, $ba['foto']);
        $this->assertSame((float) $o->ringkasan['nilai_kurang'], (float) $ba['ringkasan']['nilai_kurang'], 'berita acara = angka final saat disetujui');
        $a = collect($ba['baris'])->firstWhere('kode', $this->b['A']->kode_item);
        $this->assertSame([10.0, 7.0, -3.0, -60000.0], [$a['sistem'], $a['fisik'], $a['selisih'], $a['nilai']]);
        $this->assertStringContainsString('Rusak / pecah / bocor 2', $a['penyebab']);
        // selesai: penghitung boleh mengunduh (opname sudah ditutup)
        $this->actingAs($helper)->get(route('stok-opname.berita-acara', $o))->assertOk()->assertHeader('content-type', 'application/pdf');
        $x = $this->actingAs($helper)->get(route('stok-opname.berita-acara.excel', $o))->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $x->headers->get('content-type'));
        $this->assertStringContainsString($o->nomor, (string) $x->headers->get('content-disposition'));
        // halaman opname memuat tombol berita acara
        $this->actingAs($ap)->get(route('stok-opname.show', $o))->assertOk()->assertSee('data-uji="ba-pdf"', false);
        // lokasi lain tidak boleh
        $lain = $this->buatUser('admin_gudang', $this->lokasi('gudang_pusat', null)->id);
        $this->actingAs($lain)->get(route('stok-opname.berita-acara', $o))->assertNotFound();
    }

    public function test_laporan_kerugian_per_penyebab_lokasi_barang_manual_dan_mitra(): void
    {
        [$o] = $this->opnameGudang();
        // penyesuaian manual di luar opname: 1 botol D rusak
        $this->actingAs($this->buatUser('admin_pusat'));
        $this->stok->adjustment($this->b['D']->id, $this->gudang->id, 2, 'pecah', null, 'rusak');
        // opname toko mitra (disetujui pemilik tokonya): M kurang 1 tester
        $pemilik = $this->buatUser('pemilik_toko', $this->mitra->id);
        $this->actingAs($pemilik);
        $om = $this->svc->mulai($pemilik, $this->mitra, ['cakupan' => 'semua']);
        $this->svc->hitung($pemilik, $om, $this->baris($om, $this->b['M']), 5);
        $this->svc->simpanRincian($pemilik, $om, $this->baris($om, $this->b['M']), [['penyebab' => 'tester', 'qty' => 1]]);
        $this->svc->setujui($pemilik, $this->svc->ajukan($pemilik, $om));

        $lap = app(LaporanKerugianStokService::class)->laporan(now()->startOfMonth(), now(), [$this->gudang->id]);
        $this->assertEquals([40000, 20000, 0, 50000], [$lap['per_penyebab']['rusak']['nilai'], $lap['per_penyebab']['hilang']['nilai'], $lap['per_penyebab']['tester']['nilai'], $lap['per_penyebab']['lebih']['nilai']]);
        $this->assertSame('6-1402 Kerugian Stok - Rusak', $lap['per_penyebab']['rusak']['akun']);
        $this->assertEquals([1, 33.3, 60000, 50000, -10000], [$lap['total']['opname'], $lap['total']['akurasi'], $lap['total']['nilai_kurang'], $lap['total']['nilai_lebih'], $lap['total']['bersih']]);
        $this->assertCount(1, $lap['per_lokasi']);
        $this->assertEquals([3, 60000, 1], [$lap['per_barang'][0]['qty'], $lap['per_barang'][0]['nilai'], $lap['per_barang'][0]['kali']]);
        $this->assertStringContainsString('Hilang 1', $lap['per_barang'][0]['penyebab']);
        $this->assertSame([$o->nomor], $lap['riwayat']->pluck('nomor')->all());
        $this->assertEquals([['lokasi' => $this->gudang->nama_cabang, 'kode' => 'BEBAN-STOK', 'nama' => 'Beban Kerugian Stok', 'jumlah' => 1, 'nilai' => 10000.0]], $lap['manual']);
        $this->assertEquals(10000, $lap['total']['manual_rugi']);

        // halaman: bawaan = lokasi BABA saja (mitra tidak ikut), "semua" = termasuk mitra
        $owner = User::where('role', 'owner')->orderBy('id')->get()->last();
        $this->actingAs($owner)->get(route('stok-opname.laporan'))->assertOk()->assertSee($this->gudang->nama_cabang)->assertDontSee($om->nomor);
        $this->actingAs($owner)->get(route('stok-opname.laporan', ['lokasi' => 'semua', 'tab' => 'riwayat']))->assertOk()->assertSee($om->nomor)->assertSee($o->nomor);
        // pemilik toko: hanya tokonya
        $this->actingAs($pemilik)->get(route('stok-opname.laporan', ['tab' => 'riwayat']))->assertOk()->assertSee($om->nomor)->assertDontSee($o->nomor);
        $this->actingAs($pemilik)->get(route('stok-opname.laporan', ['lokasi' => $this->gudang->id, 'tab' => 'riwayat']))->assertOk()->assertDontSee($o->nomor);
        // tanpa izin laporan
        $this->actingAs($this->buatUser('admin_gudang', $this->gudang->id))->get(route('stok-opname.laporan'))->assertForbidden();
        // Excel tiap tab + tanggal salah → bawaan
        foreach (array_keys(\App\Http\Controllers\StokOpnameController::TAB_LAPORAN) as $tab) {
            $r = $this->actingAs($owner)->get(route('stok-opname.laporan', ['tab' => $tab, 'excel' => 1]))->assertOk();
            $this->assertStringContainsString("kerugian-stok-{$tab}", (string) $r->headers->get('content-disposition'));
        }
        $this->actingAs($owner)->get(route('stok-opname.laporan', ['dari' => 'kemarin-lusa', 'sampai' => '2026-13-45']))->assertOk();
        $this->actingAs($owner)->get(route('stok-opname.laporan', ['dari' => '2026-12-01', 'sampai' => '2026-01-01']))->assertOk();
    }

    public function test_kartu_stok_bertautan_ke_opname(): void
    {
        [$o] = $this->opnameGudang();
        $owner = User::where('role', 'owner')->orderBy('id')->get()->last();
        $this->actingAs($owner)->get(route('stok.kartu', ['item_id' => $this->b['A']->id, 'lokasi_id' => $this->gudang->id]))->assertOk()
            ->assertSee(route('stok-opname.show', $o->id), false)->assertSee($o->nomor);
    }
}
