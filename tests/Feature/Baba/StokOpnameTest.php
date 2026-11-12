<?php

namespace Tests\Feature\Baba;

use App\Exceptions\StokOpnameException;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Kas;
use App\Models\StockMovement;
use App\Models\StokOpname;
use App\Models\StokOpnameItem;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\LabaRugiFormalService;
use App\Services\StokOpnameService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 7a — stok opname: hitung buta, selisih (bukan menimpa), penyebab → keuangan, persetujuan, toko mitra. */
class StokOpnameTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private StokService $stok;
    private StokOpnameService $svc;
    /** @var array<string, Item> */
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
        $this->b = [
            'A' => $this->barang($this->gudang, 'pcs', 10, 20000, 'BCA'),
            'B' => $this->barang($this->gudang, 'ml', 500, 400),
            'C' => $this->barang($this->gudang, 'pcs', 5, 50000),
            'D' => $this->barang($this->gudang, 'pcs', 3, 10000),
        ];
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    }

    private function lokasi(string $tipe, ?string $jenis): Cabang
    {
        return Cabang::create(['nama_cabang' => 'Lokasi SO ' . uniqid(), 'kode_cabang' => 'SO' . rand(10000, 99999), 'tipe' => $tipe, 'jenis_toko' => $jenis, 'is_active' => true]);
    }

    private function barang(Cabang $c, string $satuan, float $qty, float $harga, ?string $barcode = null): Item
    {
        $kat = ItemCategory::firstOrCreate(['kode_kategori' => 'PFM'], ['nama_kategori' => 'Parfum Botol']);
        $k = strtoupper(Str::random(6));
        $i = Item::create(['cabang_id' => $c->id, 'kode_item' => "SO{$k}", 'barcode' => $barcode ? $barcode . $k : null, 'nama_item' => "Uji Opname {$k}", 'item_category_id' => $kat->id,
            'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => $satuan, 'track_stok' => true, 'is_active' => true]);
        $this->stok->masuk($i->id, $c->id, $qty, 'stok awal uji', 'uji', null, $harga);

        return $i;
    }

    private function baris(StokOpname $o, Item $i): StokOpnameItem
    {
        return StokOpnameItem::where('stok_opname_id', $o->id)->where('item_id', $i->id)->firstOrFail();
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

    /** helper menghitung (buta), admin gudang memeriksa & mengajukan, admin pusat menyetujui. */
    private function opnameDiajukan(): array
    {
        $helper = $this->buatUser('helper', $this->gudang->id);
        $adminG = $this->buatUser('admin_gudang', $this->gudang->id);
        $this->actingAs($helper);
        $o = $this->svc->mulai($helper, $this->gudang, ['cakupan' => 'barang', 'barang' => array_map(fn ($i) => $i->id, $this->b), 'hitung_buta' => true]);
        $this->svc->hitung($helper, $o, $this->baris($o, $this->b['A']), 7);
        $this->svc->hitung($helper, $o, $this->baris($o, $this->b['B']), 480);
        $this->svc->hitung($helper, $o, $this->baris($o, $this->b['C']), 6);
        $this->svc->hitung($helper, $o, $this->baris($o, $this->b['D']), 3);

        return [$o, $helper, $adminG];
    }

    public function test_alur_lengkap_selisih_penyebab_keuangan_dan_penjualan_saat_opname(): void
    {
        [$o, $helper, $adminG] = $this->opnameDiajukan();
        $this->assertSame('SO-' . now()->format('Ym') . '-', substr($o->nomor, 0, 10));
        $this->assertFalse($this->svc->lihatSistem($helper, $o), 'hitung buta: penghitung tidak melihat stok sistem');
        $this->assertTrue($this->svc->lihatSistem($adminG, $o));
        $this->assertSame(10.0, $this->baris($o, $this->b['A'])->qty_sistem);

        // toko tetap jualan: 2 botol A terjual SETELAH A dihitung → saat disetujui hanya selisih (−3) yang diterapkan
        $this->stok->keluar($this->b['A']->id, $this->gudang->id, 2, 'jual uji', 'uji', null);

        $this->actingAs($adminG);
        $m = implode(' ', $this->svc->masalah($o));
        $this->assertStringContainsString('isi penyebab kurang 3', $m);
        $this->assertStringContainsString('isi penyebab kurang 20', $m);
        $this->gagal(fn () => $this->svc->ajukan($adminG, $o), '/Belum bisa diajukan/');
        $this->gagal(fn () => $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['A']), [['penyebab' => 'rusak', 'qty' => 2]]), '/harus sama dengan selisih \(3 pcs\)/');
        $this->gagal(fn () => $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['A']), [['penyebab' => 'rusak', 'qty' => 1.5], ['penyebab' => 'hilang', 'qty' => 1.5]]), '/bilangan bulat/');
        $this->gagal(fn () => $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['C']), [['penyebab' => 'rusak', 'qty' => 1]]), '/hanya diisi untuk barang yang fisiknya kurang/');
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['A']), [['penyebab' => 'rusak', 'qty' => 2], ['penyebab' => 'hilang', 'qty' => 1, 'catatan' => 'tidak ditemukan']]);
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['B']), [['penyebab' => 'susut', 'qty' => 20]]);
        $this->assertStringContainsString('barang rusak wajib difoto', implode(' ', $this->svc->masalah($o)));
        $rusak = $this->baris($o, $this->b['A'])->rincian()->where('penyebab', 'rusak')->first();
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['A']), [['penyebab' => 'rusak', 'qty' => 2, 'foto' => $this->png], ['penyebab' => 'hilang', 'qty' => 1]]);
        $this->assertSame([], $this->svc->masalah($o), 'hilang Rp 20.000 < batas foto Rp 200.000');
        $foto = $this->baris($o, $this->b['A'])->rincian()->where('penyebab', 'rusak')->value('foto');
        Storage::disk('local')->assertExists($foto);
        $this->assertNotNull($rusak);

        $o = $this->svc->ajukan($adminG, $o);
        $this->assertSame('diajukan', $o->status);
        $ownerUji = User::where('role', 'owner')->orderBy('id')->get()->last();
        $this->assertSame(1, $ownerUji->notifications()->where('type', \App\Notifications\StokOpnameNotification::class)->count(), 'lonceng ke penyetuju');
        $this->assertSame(0, $helper->notifications()->count(), 'penghitung tanpa izin setujui tidak dikabari saat diajukan');
        $this->gagal(fn () => $this->svc->hitung($helper, $o->fresh(), $this->baris($o, $this->b['D']), 4), '/sudah menunggu persetujuan/i');
        $this->assertFalse($this->svc->boleh($adminG, $o, 'setujui'), 'admin gudang tanpa izin setujui');

        $kasSebelum = (float) Kas::sum('saldo_sekarang');
        $adminP = $this->buatUser('admin_pusat');
        $this->actingAs($adminP);
        $o = $this->svc->setujui($adminP, $o->fresh());
        $this->assertSame('selesai', $o->status);
        $this->assertSame(1, $adminG->notifications()->count(), 'pengaju dikabari disetujui');
        $this->assertStringContainsString('disetujui', $helper->notifications()->first()?->data['message'] ?? '');
        // stok: A 10 − 2 terjual − 3 = 5; B 480; C 6; D 3
        $this->assertEquals(5, $this->stok->getStok($this->b['A']->id, $this->gudang->id));
        $this->assertEquals(480, $this->stok->getStok($this->b['B']->id, $this->gudang->id));
        $this->assertEquals(6, $this->stok->getStok($this->b['C']->id, $this->gudang->id));
        $this->assertEquals(3, $this->stok->getStok($this->b['D']->id, $this->gudang->id));
        // keuangan per penyebab, tanpa kas
        $trx = TransaksiKeuangan::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->with('kategoriDinamis')->get();
        $per = $trx->mapWithKeys(fn ($t) => [$t->kategoriDinamis?->kode => (float) $t->jumlah])->all();
        $this->assertEquals(['SO-RUSAK' => 40000.0, 'SO-HILANG' => 20000.0, 'SO-SUSUT' => 8000.0, 'KOR-STOK' => 50000.0], $per);
        $this->assertTrue($trx->every(fn ($t) => $t->kas_id === null && (int) $t->cabang_id === $this->gudang->id));
        $this->assertEqualsWithDelta($kasSebelum, (float) Kas::sum('saldo_sekarang'), 0.01, 'barang rusak/hilang tidak memotong kas');
        $rg = $o->ringkasan;
        $this->assertSame([4, 1, 2, 1], [$rg['dihitung'], $rg['cocok'], $rg['kurang'], $rg['lebih']]);
        $this->assertEquals([68000, 50000, -18000, 25.0], [$rg['nilai_kurang'], $rg['nilai_lebih'], $rg['nilai_bersih'], $rg['akurasi']]);
        $this->assertSame(4, StockMovement::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->count());
        // Laba Rugi: akun per penyebab terisi
        $lr = app(LabaRugiFormalService::class)->hitungLabaRugi(Carbon::today(), Carbon::today(), $this->gudang->id);
        $akun = collect($lr['beban_operasional']['detail'] ?? $lr['beban_operasional'] ?? [])->pluck('jumlah', 'kode');
        $this->assertEquals(40000, $akun['6-1402'] ?? null);
        $this->assertEquals(8000, $akun['6-1404'] ?? null);
        // terkunci
        $this->assertFalse($this->svc->boleh($adminP, $o, 'batal'));
        $this->gagal(fn () => $this->svc->setujui($adminP, $o), '/tidak sedang menunggu/');
    }

    public function test_penghitung_tidak_bisa_menyetujui_kecuali_owner(): void
    {
        $adminP = $this->buatUser('admin_pusat');
        $this->actingAs($adminP);
        $o = $this->svc->mulai($adminP, $this->gudang, ['cakupan' => 'barang', 'barang' => [$this->b['D']->id]]);
        $this->svc->hitung($adminP, $o, $this->baris($o, $this->b['D']), 3);
        $o = $this->svc->ajukan($adminP, $o);
        $this->assertFalse($this->svc->boleh($adminP, $o, 'setujui'));
        $this->gagal(fn () => $this->svc->setujui($adminP, $o), '/ikut menghitung/');
        $owner = User::where('role', 'owner')->latest('id')->first();
        $this->actingAs($owner);
        $this->assertSame('selesai', $this->svc->setujui($owner, $o)->status);
        $this->assertEquals([1, 1, 100], [$o->fresh()->ringkasan['dihitung'], $o->fresh()->ringkasan['cocok'], $o->fresh()->ringkasan['akurasi']]);
        $this->assertSame(0, TransaksiKeuangan::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->count(), 'cocok semua = tanpa jurnal');
    }

    public function test_tolak_hitung_ulang_batal_dan_bentrok(): void
    {
        [$o, $helper, $adminG] = $this->opnameDiajukan();
        $this->actingAs($adminG);
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['A']), [['penyebab' => 'hilang', 'qty' => 3]]);
        $this->assertSame(1, $this->svc->mintaHitungUlang($adminG, $o, [$this->baris($o, $this->b['A'])->id]));
        $this->assertStringContainsString('diminta hitung ulang', implode(' ', $this->svc->masalah($o)));
        // hitung ulang mengubah selisih → rincian lama dihapus, jejak hitungan pertama tetap
        $this->actingAs($helper);
        $a = $this->svc->hitung($helper, $o, $this->baris($o, $this->b['A']), 8);
        $this->assertSame([2, 7.0, false, 0], [$a->hitung_ke, $a->qty_fisik_awal, $a->minta_hitung_ulang, $a->rincian()->count()]);
        // scan barcode = +1
        $this->assertSame($this->b['A']->id, $this->svc->cariBaris($o, $this->b['A']->barcode)->item_id);
        $a = $this->svc->hitung($helper, $o, $this->svc->cariBaris($o, $this->b['A']->barcode), 1, true);
        $this->assertSame(9.0, $a->qty_fisik);
        $this->assertNull($this->svc->cariBaris($o, 'TIDAKADA'));
        $this->gagal(fn () => $this->svc->hitung($helper, $o, $this->baris($o, $this->b['A']), -1), '/antara 0/');
        // bentrok: barang yang sama tidak boleh di dua opname berjalan
        $this->gagal(fn () => $this->svc->mulai($helper, $this->gudang, ['cakupan' => 'barang', 'barang' => [$this->b['A']->id]]), '/sedang dihitung di opname ' . $o->nomor . '/');
        // tolak
        $this->actingAs($adminG);
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['A']), [['penyebab' => 'hilang', 'qty' => 1]]);
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['B']), [['penyebab' => 'susut', 'qty' => 20]]);
        $o = $this->svc->ajukan($adminG, $o);
        $adminP = $this->buatUser('admin_pusat');
        $this->gagal(fn () => $this->svc->tolak($adminP, $o, 'x'), '/alasan penolakan/');
        $o = $this->svc->tolak($adminP, $o, 'Barang A dihitung ulang di rak belakang');
        $this->assertSame(['hitung', 'Barang A dihitung ulang di rak belakang'], [$o->status, $o->alasan_tolak]);
        // batal
        $this->gagal(fn () => $this->svc->batal($helper, $o, 'tidak jadi'), '/tidak punya izin membatalkan/');
        $o = $this->svc->batal($adminP, $o, 'Diulang bulan depan');
        $this->assertSame('batal', $o->status);
        $this->assertEquals(10, $this->stok->getStok($this->b['A']->id, $this->gudang->id), 'batal = stok tidak berubah');
        $this->assertNotNull($this->svc->mulai($helper, $this->gudang, ['cakupan' => 'barang', 'barang' => [$this->b['A']->id]]), 'setelah batal barang boleh dihitung lagi');
        $this->assertSame(['mulai', 'hitung_ulang', 'ajukan', 'tolak', 'batal'], $o->log()->pluck('aksi')->all());
    }

    public function test_stok_terjual_lebih_dari_hitungan_ditolak_tanpa_perubahan(): void
    {
        [$o, $helper, $adminG] = $this->opnameDiajukan();
        $this->actingAs($adminG);
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['A']), [['penyebab' => 'rusak', 'qty' => 3, 'foto' => $this->png]]);
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $this->b['B']), [['penyebab' => 'susut', 'qty' => 20]]);
        $o = $this->svc->ajukan($adminG, $o);
        $this->stok->keluar($this->b['A']->id, $this->gudang->id, 9, 'jual uji', 'uji', null); // tinggal 1, padahal perlu dikurangi 3
        $adminP = $this->buatUser('admin_pusat');
        $this->actingAs($adminP);
        $this->gagal(fn () => $this->svc->setujui($adminP, $o), '/sekarang tinggal 1, tidak cukup untuk dikurangi 3/');
        $this->assertSame('diajukan', $o->fresh()->status);
        $this->assertEquals(480 + 20, $this->stok->getStok($this->b['B']->id, $this->gudang->id), 'transaksi dibatalkan seluruhnya');
        $this->assertSame(0, TransaksiKeuangan::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->count());
    }

    public function test_hilang_bernilai_besar_wajib_foto(): void
    {
        $mahal = $this->barang($this->gudang, 'pcs', 4, 150000);
        $adminG = $this->buatUser('admin_gudang', $this->gudang->id);
        $this->actingAs($adminG);
        $o = $this->svc->mulai($adminG, $this->gudang, ['cakupan' => 'barang', 'barang' => [$mahal->id]]);
        $this->svc->hitung($adminG, $o, $this->baris($o, $mahal), 2);
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $mahal), [['penyebab' => 'hilang', 'qty' => 2]]);
        $this->assertStringContainsString('senilai Rp 300.000 wajib difoto', implode(' ', $this->svc->masalah($o)));
        $this->svc->simpanRincian($adminG, $o, $this->baris($o, $mahal), [['penyebab' => 'hilang', 'qty' => 1], ['penyebab' => 'tester', 'qty' => 1]]);
        $this->assertSame([], $this->svc->masalah($o), 'Rp 150.000 di bawah batas');
        $this->gagal(fn () => $this->svc->simpanRincian($adminG, $o, $this->baris($o, $mahal), [['penyebab' => 'hilang', 'qty' => 2, 'foto' => 'bukan gambar']]), '/JPG\/PNG\/WebP/');
    }

    public function test_toko_mitra_disetujui_pemilik_toko_dan_di_luar_keuangan_baba(): void
    {
        $mitra = $this->lokasi('cabang', 'kemitraan');
        $x = $this->barang($mitra, 'pcs', 10, 30000);
        $owner = auth()->user();
        $this->gagal(fn () => $this->svc->mulai($owner, $mitra, ['cakupan' => 'semua']), '/hanya bisa dimulai oleh orang toko itu sendiri/');
        $pemilik = $this->buatUser('pemilik_toko', $mitra->id);
        $this->actingAs($pemilik);
        $this->assertTrue($this->svc->lokasiUntuk($pemilik)->contains('id', $mitra->id));
        $this->assertFalse($this->svc->lokasiUntuk($pemilik)->contains('id', $this->gudang->id));
        $o = $this->svc->mulai($pemilik, $mitra, ['cakupan' => 'barang', 'barang' => [$x->id]]);
        $this->svc->hitung($pemilik, $o, $this->baris($o, $x), 8);
        $this->svc->simpanRincian($pemilik, $o, $this->baris($o, $x), [['penyebab' => 'tester', 'qty' => 2]]);
        $o = $this->svc->ajukan($pemilik, $o);
        $this->assertFalse($this->svc->boleh($owner, $o, 'setujui'), 'Owner BABA hanya melihat');
        $this->assertStringContainsString('Owner BABA hanya bisa melihat', $this->svc->sebabTidakBolehSetujui($owner, $o));
        $this->assertTrue($this->svc->bolehLihat($owner, $o));
        $this->assertTrue($this->svc->boleh($pemilik, $o, 'setujui'), 'pemilik toko boleh menyetujui hitungannya sendiri');
        $o = $this->svc->setujui($pemilik, $o);
        $this->assertEquals(8, $this->stok->getStok($x->id, $mitra->id));
        $this->assertSame(1, TransaksiKeuangan::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->where('cabang_id', $mitra->id)->count());
        $gabung = app(LabaRugiFormalService::class)->hitungLabaRugi(Carbon::today(), Carbon::today(), null);
        $tester = collect($gabung['beban_operasional']['detail'] ?? $gabung['beban_operasional'] ?? [])->firstWhere('kode', '6-1304');
        $this->assertEquals(0, $tester['jumlah'] ?? 0, 'beban tester toko mitra tidak masuk Laba Rugi gabungan BABA');
        $this->assertFalse($this->svc->bolehLihat($pemilik, StokOpname::make(['cabang_id' => $this->gudang->id])), 'pemilik toko tidak melihat lokasi lain');
    }

    public function test_halaman_dan_endpoint_hitung(): void
    {
        [$o, $helper, $adminG] = $this->opnameDiajukan();
        $this->actingAs($helper)->get(route('stok-opname.index'))->assertOk()->assertSee($o->nomor);
        $this->actingAs($helper)->get(route('stok-opname.mulai'))->assertOk();
        $hal = $this->actingAs($helper)->get(route('stok-opname.show', $o))->assertOk()->assertSee($this->b['A']->nama_item);
        $hal->assertDontSee('data-uji="tab-selisih"', false);
        $this->actingAs($adminG)->get(route('stok-opname.show', $o))->assertOk()->assertSee('data-uji="tab-selisih"', false)->assertSee('isi penyebab kurang 3');
        // JSON hitung: penghitung buta tidak menerima angka sistem
        $r = $this->actingAs($helper)->postJson(route('stok-opname.hitung', $o), ['kode' => $this->b['A']->barcode, 'tambah' => 1])->assertOk()->json();
        $this->assertSame([8.0, null, null], [(float) $r['baris']['fisik'], $r['baris']['sistem'], $r['baris']['selisih']]);
        $r = $this->actingAs($adminG)->postJson(route('stok-opname.hitung', $o), ['baris' => $this->baris($o, $this->b['D'])->id, 'qty' => 2])->assertOk()->json();
        $this->assertSame([3.0, -1.0], [(float) $r['baris']['sistem'], (float) $r['baris']['selisih']]);
        $this->actingAs($helper)->postJson(route('stok-opname.hitung', $o), ['kode' => 'TIDAK-ADA', 'tambah' => 1])->assertStatus(422)->assertJsonPath('ok', false);
        $this->actingAs($helper)->postJson(route('stok-opname.hitung', $o), ['baris' => $this->baris($o, $this->b['D'])->id, 'qty' => 'abc'])->assertStatus(422);
        // rincian lewat form (multipart + foto)
        $this->actingAs($adminG)->post(route('stok-opname.rincian', [$o, $this->baris($o, $this->b['D'])->id]), ['r' => [['penyebab' => 'rusak', 'qty' => 1,
            'foto' => \Illuminate\Http\UploadedFile::fake()->createWithContent('pecah.png', $this->png)]]])->assertRedirect();
        $x = $this->baris($o, $this->b['D'])->rincian()->first();
        $this->assertSame('rusak', $x->penyebab);
        $this->actingAs($adminG)->get(route('stok-opname.foto', [$o, $x->id]))->assertOk();
        // tanpa izin / lokasi lain
        $this->actingAs($this->buatUser('operator_produksi', $this->gudang->id))->get(route('stok-opname.index'))->assertForbidden();
        $lain = $this->buatUser('admin_gudang', $this->lokasi('gudang_pusat', null)->id);
        $this->actingAs($lain)->get(route('stok-opname.show', $o))->assertNotFound();
        $this->actingAs($lain)->get(route('stok-opname.foto', [$o, $x->id]))->assertNotFound();
        $this->actingAs($helper)->post(route('stok-opname.setujui', $o))->assertRedirect()->assertSessionHas('error');
    }

    public function test_scan_barcode_botol_di_toko_menemukan_barang_padanan(): void
    {
        $toko = $this->lokasi('cabang', 'baba');
        $padanan = Item::create(['cabang_id' => $toko->id, 'kode_item' => $this->b['A']->kode_item, 'nama_item' => $this->b['A']->nama_item, 'item_category_id' => $this->b['A']->item_category_id,
            'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
        $this->stok->masuk($padanan->id, $toko->id, 4, 'transfer uji', 'uji', null, 20000);
        $o = $this->svc->mulai(auth()->user(), $toko, ['cakupan' => 'semua']);
        $this->assertSame($padanan->id, $this->svc->cariBaris($o, $this->b['A']->barcode)?->item_id, 'barcode botol gudang → barang padanan toko');
        $this->assertSame($padanan->id, $this->svc->cariBaris($o, $this->b['A']->kode_item)?->item_id, 'kode barang juga bisa diketik');
    }

    public function test_penyesuaian_lama_audit_turun_tercatat_selisih_persediaan(): void
    {
        $adminP = $this->buatUser('admin_pusat');
        $this->actingAs($adminP);
        $this->stok->adjustment($this->b['C']->id, $this->gudang->id, 3, 'stok opname manual', null, 'audit');
        $t = TransaksiKeuangan::where('referensi_type', 'stock_movement')->latest('id')->with('kategoriDinamis')->first();
        $this->assertSame(['SO-SALAH', '6-1406', 100000.0, null], [$t->kategoriDinamis?->kode, $t->kategoriDinamis?->kode_akun_coa, (float) $t->jumlah, $t->kas_id]);
        $sebelum = TransaksiKeuangan::count();
        $this->stok->adjustment($this->b['C']->id, $this->gudang->id, 8, 'stok awal', null, 'audit', ['mode_distribusi' => 'batch_baru']);
        $this->assertSame($sebelum, TransaksiKeuangan::count(), 'audit naik (stok awal) tetap tanpa keuangan');
    }
}
