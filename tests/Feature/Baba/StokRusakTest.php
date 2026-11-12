<?php

namespace Tests\Feature\Baba;

use App\Exceptions\StokOpnameException;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Kas;
use App\Models\StokOpname;
use App\Models\StokOpnameItem;
use App\Models\StokRusak;
use App\Models\Supplier;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\KasBawaanService;
use App\Services\LabaRugiFormalService;
use App\Services\StokOpnameService;
use App\Services\StokRusakService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 7c — stok rusak ditahan dari opname + tindak lanjut (pakai lagi, pemasok ganti/uang/potong, musnahkan). */
class StokRusakTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private StokService $stok;
    private StokOpnameService $op;
    private StokRusakService $svc;
    private Item $botol;
    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs($this->buatUser('owner'));
        $this->stok = app(StokService::class);
        $this->op = app(StokOpnameService::class);
        $this->svc = app(StokRusakService::class);
        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang RSK ' . uniqid(), 'kode_cabang' => 'RS' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $kat = ItemCategory::firstOrCreate(['kode_kategori' => 'PFM'], ['nama_kategori' => 'Parfum Botol']);
        $k = strtoupper(Str::random(6));
        $this->botol = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => "RS{$k}", 'nama_item' => "Uji Rusak {$k}", 'item_category_id' => $kat->id,
            'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
        $this->stok->masuk($this->botol->id, $this->gudang->id, 20, 'stok awal', 'uji', null, 25000);
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    }

    /** Opname: 10 botol rusak (fisik 10 dari 20), disetujui admin pusat. */
    private function rusakDariOpname(): StokRusak
    {
        $adminG = $this->buatUser('admin_gudang', $this->gudang->id);
        $this->actingAs($adminG);
        $o = $this->op->mulai($adminG, $this->gudang, ['cakupan' => 'barang', 'barang' => [$this->botol->id]]);
        $row = StokOpnameItem::where('stok_opname_id', $o->id)->firstOrFail();
        $row = $this->op->hitung($adminG, $o, $row, 10);
        $this->op->simpanRincian($adminG, $o, $row, [['penyebab' => 'rusak', 'qty' => 10, 'foto' => $this->png, 'catatan' => 'jatuh dari rak']]);
        $o = $this->op->ajukan($adminG, $o);
        $ap = $this->buatUser('admin_pusat');
        $this->actingAs($ap);
        $o = $this->op->setujui($ap, $o);
        $this->assertSame(1, $o->ringkasan['stok_rusak']);

        return StokRusak::where('stok_opname_id', $o->id)->firstOrFail();
    }

    private function gagal(callable $f, string $pola): void
    {
        try {
            $f();
            $this->fail("Seharusnya ditolak: {$pola}");
        } catch (StokOpnameException $e) {
            $this->assertMatchesRegularExpression($pola, $e->getMessage());
        }
    }

    public function test_tindak_lanjut_dan_pencatatan_keuangan(): void
    {
        $r = $this->rusakDariOpname();
        $this->assertSame([10.0, 10.0, 25000.0, 'ditahan', 'jatuh dari rak'], [$r->qty, $r->qty_sisa, $r->harga_pokok, $r->status, $r->catatan]);
        $this->assertEquals(10, $this->stok->getStok($this->botol->id, $this->gudang->id), 'barang rusak sudah keluar dari stok jual');
        $u = auth()->user();
        $kas = app(KasBawaanService::class)->pastikan($this->gudang)['tunai'];
        $saldoAwal = (float) $kas->fresh()->saldo_sekarang;
        $sup = Supplier::create(['kode_supplier' => 'SP' . rand(1000, 9999), 'nama_supplier' => 'Pemasok Botol Uji']);

        // 1) pakai lagi 2 → stok +2 seharga pokok, kerugian rusak −50.000
        $this->svc->tindak($u, $r, ['jenis' => 'pakai_lagi', 'qty' => 2]);
        $this->assertEquals(12, $this->stok->getStok($this->botol->id, $this->gudang->id));
        $this->assertEqualsWithDelta(25000, app(StokService::class)->hargaPokokRata($this->botol->id, $this->gudang->id), 0.01);
        // 2) pemasok ganti 3 → stok +3, kerugian −75.000
        $this->svc->tindak($u, $r, ['jenis' => 'pemasok_ganti', 'qty' => 3, 'supplier_id' => $sup->id]);
        $this->assertEquals(15, $this->stok->getStok($this->botol->id, $this->gudang->id));
        // 3) pemasok uang 2 → Rp 40.000 masuk kas tunai, Pemulihan Kerugian Stok
        $this->gagal(fn () => $this->svc->tindak($u, $r, ['jenis' => 'pemasok_uang', 'qty' => 2, 'nominal' => 0, 'kas_id' => $kas->id]), '/jumlah uang/');
        $lain = app(KasBawaanService::class)->pastikan(Cabang::create(['nama_cabang' => 'Lain ' . uniqid(), 'kode_cabang' => 'LN' . rand(10000, 99999), 'tipe' => 'cabang', 'jenis_toko' => 'baba', 'is_active' => true]))['tunai'];
        $this->gagal(fn () => $this->svc->tindak($u, $r, ['jenis' => 'pemasok_uang', 'qty' => 2, 'nominal' => 40000, 'kas_id' => $lain->id]), '/kas lokasi ini/');
        $this->svc->tindak($u, $r, ['jenis' => 'pemasok_uang', 'qty' => 2, 'nominal' => 40000, 'kas_id' => $kas->id, 'supplier_id' => $sup->id]);
        $this->assertEqualsWithDelta($saldoAwal + 40000, (float) $kas->fresh()->saldo_sekarang, 0.01);
        // 4) potong tagihan 1 → catatan wajib, tanpa jurnal
        $this->gagal(fn () => $this->svc->tindak($u, $r, ['jenis' => 'pemasok_potong', 'qty' => 1]), '/nomor PO/');
        $this->svc->tindak($u, $r, ['jenis' => 'pemasok_potong', 'qty' => 1, 'catatan' => 'PO-2026-011 dipotong 1 botol']);
        // 5) musnahkan: foto wajib, jumlah tidak boleh lebih dari sisa, bilangan bulat
        $this->gagal(fn () => $this->svc->tindak($u, $r, ['jenis' => 'musnahkan', 'qty' => 2]), '/wajib difoto/');
        $this->gagal(fn () => $this->svc->tindak($u, $r, ['jenis' => 'musnahkan', 'qty' => 3, 'foto' => $this->png]), '/melebihi sisa/');
        $this->gagal(fn () => $this->svc->tindak($u, $r, ['jenis' => 'musnahkan', 'qty' => 1.5, 'foto' => $this->png]), '/bilangan bulat/');
        $t = $this->svc->tindak($u, $r, ['jenis' => 'musnahkan', 'qty' => 2, 'foto' => $this->png, 'catatan' => 'dibuang']);
        Storage::disk('local')->assertExists($t->foto);
        $r->refresh();
        $this->assertSame([0.0, 'selesai'], [$r->qty_sisa, $r->status]);
        $this->gagal(fn () => $this->svc->tindak($u, $r, ['jenis' => 'pakai_lagi', 'qty' => 1]), '/melebihi sisa/');
        $this->assertEquals(15, $this->stok->getStok($this->botol->id, $this->gudang->id), 'uang / potong / musnah tidak menambah stok');

        // keuangan: rusak 250.000 − 50.000 − 75.000 = 125.000; pemulihan 40.000 (pendapatan lain)
        $lr = app(LabaRugiFormalService::class)->hitungLabaRugi(Carbon::today(), Carbon::today(), $this->gudang->id);
        $beban = collect($lr['beban_operasional']['detail'])->pluck('jumlah', 'kode');
        $lain = collect($lr['pendapatan_lain']['detail'])->pluck('jumlah', 'kode');
        $this->assertEquals(125000, $beban['6-1402']);
        $this->assertEquals(40000, $lain['7-1103']);
        $this->assertSame(3, TransaksiKeuangan::where('referensi_type', 'stok_rusak')->where('referensi_id', $r->id)->count());
        $this->assertSame([2.0, 3.0, 2.0, 1.0, 2.0], $r->tindakan()->pluck('qty')->all());
        $this->assertSame([50000.0, 75000.0, 40000.0, 0.0, 0.0], $r->tindakan()->pluck('nilai_pulih')->all());
        // Laporan Kerugian Stok tab Stok rusak
        $lap = app(\App\Services\LaporanKerugianStokService::class)->laporan(now()->startOfMonth(), now(), [$this->gudang->id]);
        $this->assertEquals([165000, 0, 1, 50000], [$lap['total']['dipulihkan'], $lap['rusak']['nilai_ditahan'], $lap['rusak']['per_jenis']['musnahkan']['kali'], $lap['rusak']['per_jenis']['musnahkan']['nilai_pokok']]);
        $this->assertSame([], $lap['rusak']['ditahan']);
    }

    public function test_hak_lokasi_toko_mitra_dan_halaman(): void
    {
        $r = $this->rusakDariOpname();
        $adminG = $this->buatUser('admin_gudang', $this->gudang->id);
        $this->assertTrue($this->svc->boleh($adminG, $r));
        $this->assertFalse($this->svc->boleh($this->buatUser('helper', $this->gudang->id), $r), 'helper tanpa izin kelola stok rusak');
        $this->assertFalse($this->svc->boleh($this->buatUser('admin_gudang', Cabang::create(['nama_cabang' => 'G2 ' . uniqid(), 'kode_cabang' => 'G2' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true])->id), $r));
        // halaman
        $this->actingAs($adminG)->get(route('stok-opname.rusak'))->assertOk()->assertSee($this->botol->nama_item)->assertSee('data-uji="tindak"', false);
        $this->actingAs($this->buatUser('helper', $this->gudang->id))->get(route('stok-opname.rusak'))->assertForbidden();
        $this->actingAs($adminG)->post(route('stok-opname.rusak.tindak', $r), ['jenis' => 'musnahkan', 'qty' => 1, 'catatan' => 'pecah',
            'foto' => UploadedFile::fake()->createWithContent('pecah.png', $this->png)])->assertRedirect()->assertSessionHas('success');
        $t = $r->tindakan()->first();
        $this->actingAs($adminG)->get(route('stok-opname.rusak.foto', $t))->assertOk();
        $this->actingAs($adminG)->get(route('stok-opname.rusak.musnah', $t))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($adminG)->post(route('stok-opname.rusak.tindak', $r), ['jenis' => 'musnahkan', 'qty' => 1])->assertSessionHas('error');
        $lainG = $this->buatUser('admin_gudang', Cabang::create(['nama_cabang' => 'G3 ' . uniqid(), 'kode_cabang' => 'G3' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true])->id);
        $this->actingAs($lainG)->post(route('stok-opname.rusak.tindak', $r), ['jenis' => 'pakai_lagi', 'qty' => 1])->assertNotFound();
        $this->actingAs($lainG)->get(route('stok-opname.rusak.foto', $t))->assertNotFound();

        // toko mitra: Owner BABA tidak bisa menindaklanjuti; pemilik toko bisa
        $mitra = Cabang::create(['nama_cabang' => 'Mitra RSK ' . uniqid(), 'kode_cabang' => 'MR' . rand(10000, 99999), 'tipe' => 'cabang', 'jenis_toko' => 'kemitraan', 'is_active' => true]);
        $x = Item::create(['cabang_id' => $mitra->id, 'kode_item' => 'MX' . Str::random(5), 'nama_item' => 'Barang Mitra', 'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
        $this->stok->masuk($x->id, $mitra->id, 3, 'awal', 'uji', null, 10000);
        $pemilik = $this->buatUser('pemilik_toko', $mitra->id);
        $this->actingAs($pemilik);
        $o = $this->op->mulai($pemilik, $mitra, ['cakupan' => 'semua']);
        $row = StokOpnameItem::where('stok_opname_id', $o->id)->firstOrFail();
        $row = $this->op->hitung($pemilik, $o, $row, 2);
        $this->op->simpanRincian($pemilik, $o, $row, [['penyebab' => 'rusak', 'qty' => 1, 'foto' => $this->png]]);
        $o = $this->op->setujui($pemilik, $this->op->ajukan($pemilik, $o));
        $rm = StokRusak::where('stok_opname_id', $o->id)->firstOrFail();
        $owner = User::where('role', 'owner')->orderBy('id')->get()->last();
        $this->assertFalse($this->svc->boleh($owner, $rm));
        $this->gagal(fn () => $this->svc->tindak($owner, $rm, ['jenis' => 'pakai_lagi', 'qty' => 1]), '/pemilik toko itu sendiri/');
        $this->assertTrue($this->svc->boleh($pemilik, $rm));
        $this->svc->tindak($pemilik, $rm, ['jenis' => 'pakai_lagi', 'qty' => 1]);
        $this->assertEquals(3, $this->stok->getStok($x->id, $mitra->id));
    }
}
