<?php

namespace Tests\Feature\Baba;

use App\Models\AturanSumber;
use App\Models\AturanTagihan;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\MemberAkun;
use App\Models\PengaturanUmum;
use App\Models\PesananOnline;
use App\Models\ProdukOnline;
use App\Models\ProdukOnlineHarga;
use App\Models\RoCatatan;
use App\Models\TokoOnlinePengaturan;
use App\Models\TokoOnlineRekening;
use App\Models\User;
use App\Services\PesananOnlineService;
use App\Services\ReturOnlineService;
use App\Services\RoService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 2e — mode RO & RO otomatis ke aplikasi member (+ botol, − retur/batal, antrian kirim ulang). */
class RoOnlineTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private ProdukOnline $produk;
    private User $admin;
    private PesananOnlineService $svc;
    private MemberAkun $m;
    private MemberAkun $upline;
    private bool $memberHidup = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => 'kunci-uji-ro-0123456789abcdef0123456789', 'baba.member_app.url' => 'http://member.uji']);
        Cache::flush();
        Storage::fake('local');
        Http::fake(function (HttpRequest $r) {
            if (str_contains($r->url(), '/api/erp/ro')) {
                return $this->memberHidup ? Http::response(['ok' => true, 'id' => 7700 + (int) $r['qty']], 201) : Http::response('mati', 500);
            }

            return Http::response(['ok' => true]);
        });
        TokoOnlinePengaturan::ambil()->update(['hari_terima_otomatis' => 7, 'jam_batas_bayar' => 48, 'hari_batas_retur' => 7]);
        AturanTagihan::query()->update(['aktif' => false]);
        AturanSumber::query()->update(['aktif' => false]);
        AturanSumber::create(['cakupan' => 'semua', 'boleh_gudang' => true, 'boleh_upline' => true, 'aktif' => true]);
        AturanTagihan::create(['cakupan' => 'semua', 'batas_nominal' => 5000000, 'tempo_hari' => 14, 'aktif' => true]);
        $this->modeRo('lunas');

        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang RO ' . uniqid(), 'kode_cabang' => 'RO' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        TokoOnlineRekening::create(['cabang_id' => $this->gudang->id, 'bank' => 'BCA', 'nomor' => '111', 'atas_nama' => 'BABA', 'aktif' => true]);
        $item = Item::create(['kode_item' => 'ROU-' . strtoupper(Str::random(6)), 'nama_item' => 'Oud RO EDP 50 ml', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $this->gudang->id]);
        $this->produk = ProdukOnline::create(['item_id' => $item->id, 'nama_tampil' => 'Oud RO EDP 50 ml', 'aktif' => true]);
        ProdukOnlineHarga::create(['produk_online_id' => $this->produk->id, 'jenis' => 'tetap', 'harga' => 100000]);
        $this->admin = $this->buatUser('owner');
        $this->actingAs($this->admin);
        app(StokService::class)->masuk($item->id, $this->gudang->id, 50, 'uji', 'uji', null, 27000);
        auth()->logout();
        $this->svc = app(PesananOnlineService::class);
        $this->upline = MemberAkun::updateOrCreate(['member_id' => 950001], ['nama' => 'Upline RO', 'jabatan_id' => 27, 'aktif' => true]);
        $this->m = MemberAkun::updateOrCreate(['member_id' => 950002], ['nama' => 'Pembeli RO', 'jabatan_id' => 21, 'aktif' => true, 'upline_member_id' => 950001]);
    }

    private function modeRo(string $mode): void
    {
        PengaturanUmum::query()->orderBy('id')->first()?->update(['mode_ro' => $mode]) ?? PengaturanUmum::create(['nama_perusahaan' => 'BABA', 'mode_ro' => $mode]);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==') . Str::random(8);
    }

    private function pesan(int $qty, string $bayar = 'transfer', ?MemberAkun $m = null, string $sumber = 'gudang'): PesananOnline
    {
        return $this->svc->buat($m ?? $this->m, ['sumber' => $sumber, 'cara_ambil' => 'ambil', 'cara_bayar' => $bayar, 'item' => [['produk_online_id' => $this->produk->id, 'qty' => $qty]]]);
    }

    private function lunasi(PesananOnline $p, ?MemberAkun $m = null): void
    {
        $this->svc->terimaBukti($this->svc->unggahBukti($p->fresh(), $m ?? $this->m, $this->png()), $this->admin);
    }

    private function terima(PesananOnline $p, ?MemberAkun $m = null): PesananOnline
    {
        $this->svc->keluarkan($p->fresh(), $this->admin);
        $this->svc->terimaMember($p->fresh(), $m ?? $this->m);

        return $p->fresh(['item']);
    }

    public function test_mode_lunas_ro_langsung_dihitung_dan_terkirim(): void
    {
        $p = $this->pesan(3);
        $this->assertSame(0, RoCatatan::count() - RoCatatan::where('ref', '!=', 'ERP-RO-' . $p->nomor)->count()); // belum lunas → belum ada
        $this->lunasi($p);
        $c = RoCatatan::where('ref', 'ERP-RO-' . $p->nomor)->first();
        $this->assertNotNull($c);
        $this->assertSame([3, false, 'terkirim', 7703], [$c->qty, $c->perlu_approve, $c->status, $c->member_doc_id]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/api/erp/ro') && $r['user_id'] === 950002 && $r['qty'] === 3 && $r['perlu_approve'] === false
            && $r['ref'] === 'ERP-RO-' . $p->nomor && str_contains($r['keterangan'], $p->nomor));
        // dipicu lagi (mis. diterima) → tidak dobel
        $this->terima($p);
        $this->assertSame(1, RoCatatan::where('pesanan_online_id', $p->id)->count());
    }

    public function test_mode_lunas_disetujui_dan_hanya_disetujui(): void
    {
        $this->modeRo('lunas_disetujui');
        $a = $this->pesan(2);
        $this->lunasi($a);
        $this->assertTrue(RoCatatan::where('ref', 'ERP-RO-' . $a->nomor)->value('perlu_approve'));

        $this->modeRo('disetujui');
        $b = $this->pesan(4, 'tagihan'); // belum lunas
        $this->assertFalse(RoCatatan::where('pesanan_online_id', $b->id)->exists());
        $this->terima($b);
        $c = RoCatatan::where('ref', 'ERP-RO-' . $b->nomor)->first();
        $this->assertSame([4, true, 'disetujui'], [$c->qty, $c->perlu_approve, $c->mode]);
        $t = $this->pesan(1);
        $this->lunasi($t); // mode disetujui: lunas tidak memicu
        $this->assertFalse(RoCatatan::where('pesanan_online_id', $t->id)->exists());
    }

    public function test_retur_setelah_ro_minus_dan_sebelum_ro_bersih(): void
    {
        $retur = app(ReturOnlineService::class);
        // setelah RO tercatat → −RO
        $p = $this->terima(tap($this->pesan(3), fn ($x) => $this->lunasi($x)));
        $r = $retur->ajukan($p, $this->m, ['alasan' => 'Botol bocor', 'item' => [['id' => $p->item->first()->id, 'qty' => 2]]]);
        $retur->setujui($r, $this->admin, 'transfer');
        $min = RoCatatan::where('ref', 'ERP-RO-' . $r->nomor)->first();
        $this->assertSame([-2, 'retur', 'terkirim'], [$min->qty, $min->jenis, $min->status]);
        $this->assertSame(1, (int) RoCatatan::where('pesanan_online_id', $p->id)->sum('qty'));

        // tagihan, retur sebelum lunas (mode lunas) → tanpa −RO; lunas kemudian → +RO bersih
        $q = $this->terima($this->pesan(4, 'tagihan'));
        $r2 = $retur->ajukan($q, $this->m, ['alasan' => 'Salah aroma', 'item' => [['id' => $q->item->first()->id, 'qty' => 1]]]);
        $retur->setujui($r2, $this->admin);
        $this->assertFalse(RoCatatan::where('pesanan_online_id', $q->id)->exists());
        $this->lunasi($q);
        $this->assertSame(3, RoCatatan::where('ref', 'ERP-RO-' . $q->nomor)->value('qty'));

        // retur seluruh sisa tagihan → lunas karena retur → tidak ada RO (bersih 0)
        $z = $this->terima($this->pesan(1, 'tagihan'));
        $r3 = $retur->ajukan($z, $this->m, ['alasan' => 'Rusak total', 'item' => [['id' => $z->item->first()->id, 'qty' => 1]]]);
        $retur->setujui($r3, $this->admin);
        $this->assertSame('lunas', $z->fresh()->status_bayar);
        $this->assertFalse(RoCatatan::where('pesanan_online_id', $z->id)->exists());
    }

    public function test_batal_setelah_lunas_minus_ro(): void
    {
        $p = $this->pesan(2);
        $this->lunasi($p);
        $this->svc->batalAdmin($p->fresh(), 'Stok rusak saat dikemas', $this->admin);
        $this->assertSame(-2, RoCatatan::where('ref', 'ERP-RO-BATAL-' . $p->nomor)->value('qty'));
        $this->assertSame(0, (int) RoCatatan::where('pesanan_online_id', $p->id)->sum('qty'));
        // batal sebelum lunas → tidak ada catatan sama sekali
        $q = $this->pesan(1);
        $this->svc->batalMember($q, $this->m, 'ganti');
        $this->assertFalse(RoCatatan::where('pesanan_online_id', $q->id)->exists());
    }

    public function test_gagal_kirim_dicoba_ulang_dan_upline_tunai(): void
    {
        $this->memberHidup = false;
        $p = $this->pesan(2);
        $this->lunasi($p);
        $c = RoCatatan::where('ref', 'ERP-RO-' . $p->nomor)->first();
        $this->assertSame(['gagal', 1], [$c->status, $c->percobaan]);
        $this->memberHidup = true;
        $this->artisan('ro:kirim')->assertSuccessful();
        $c->refresh();
        $this->assertSame(['terkirim', 7702], [$c->status, $c->member_doc_id]);

        // pesanan ke upline (tunai) juga menambah RO pembeli saat lunas
        $u = $this->terima(tap($this->pesan(5, 'transfer', $this->upline), fn ($x) => $this->lunasi($x, $this->upline)), $this->upline);
        $k = $this->pesan(2, 'tunai', $this->m, 'upline');
        $this->svc->terimaTunai($k, $this->upline);
        $this->assertSame([2, 950002], [RoCatatan::where('ref', 'ERP-RO-' . $k->nomor)->value('qty'), RoCatatan::where('ref', 'ERP-RO-' . $k->nomor)->value('member_id')]);
        $this->assertSame(5, RoCatatan::where('ref', 'ERP-RO-' . $u->nomor)->value('qty'));
    }

    public function test_dipicu_dari_aplikasi_member_masuk_antrian_tanpa_menelepon_balik(): void
    {
        $this->modeRo('disetujui');
        config(['baba.member_api.secret' => 'kunci-uji-ro-0123456789abcdef0123456789']);
        $p = $this->pesan(2);
        $this->lunasi($p);
        $this->svc->keluarkan($p->fresh(), $this->admin);
        // pembeli menekan Diterima lewat aplikasi member (permintaan bertanda tangan ke /api/member/...)
        $profil = base64_encode(json_encode(['id' => 950002, 'nama' => 'Pembeli RO', 'jabatan_id' => 21, 'aktif' => true]));
        $path = "/api/member/pesanan/{$p->nomor}/terima";
        $waktu = (string) time();
        $nonce = Str::random(32);
        $tanda = hash_hmac('sha256', \App\Http\Middleware\VerifikasiAplikasiMember::kanonik('POST', $path, '', $waktu, $nonce, '950002', $profil, ''), 'kunci-uji-ro-0123456789abcdef0123456789');
        $this->call('POST', $path, [], [], [], ['HTTP_X-Baba-Waktu' => $waktu, 'HTTP_X-Baba-Nonce' => $nonce, 'HTTP_X-Baba-Member' => '950002',
            'HTTP_X-Baba-Profil' => $profil, 'HTTP_X-Baba-Tanda' => $tanda, 'HTTP_ACCEPT' => 'application/json'])->assertOk();
        $c = RoCatatan::where('ref', 'ERP-RO-' . $p->nomor)->first();
        $this->assertSame(['menunggu', 0], [$c->status, $c->percobaan]);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/api/erp/ro') && $r['ref'] === 'ERP-RO-' . $p->nomor);
        $this->artisan('ro:kirim')->assertSuccessful();
        $this->assertSame('terkirim', $c->fresh()->status);
    }

    public function test_pengaturan_umum_mode_ro_dan_halaman_catatan(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('pengaturan.umum'))->assertOk()->assertSee('RO dari Belanja Online')->assertSee('Saat lunas + disetujui');
        $nama = PengaturanUmum::query()->orderBy('id')->value('nama_perusahaan') ?? 'BABA';
        $this->put(route('pengaturan.umum.update'), ['nama_perusahaan' => $nama, 'mode_ro' => 'asal'])->assertSessionHasErrors('mode_ro');
        $this->put(route('pengaturan.umum.update'), ['nama_perusahaan' => $nama, 'mode_ro' => 'disetujui'])->assertSessionHasNoErrors();
        $this->assertSame('disetujui', app(RoService::class)->mode());

        $this->modeRo('lunas');
        $this->memberHidup = false;
        $p = $this->pesan(1);
        $this->lunasi($p);
        $this->get(route('toko-online.ro.index'))->assertOk()->assertSee($p->nomor)->assertSee('Langsung dihitung');
        $this->get(route('toko-online.ro.index', ['status' => 'gagal']))->assertOk()->assertSee($p->nomor)->assertSee('Kirim ulang yang tertunda');
        $this->memberHidup = true;
        $this->post(route('toko-online.ro.kirim-ulang'))->assertSessionHas('success');
        $this->assertSame('terkirim', RoCatatan::where('ref', 'ERP-RO-' . $p->nomor)->value('status'));
        $this->actingAs($this->buatUser('kasir'))->get(route('toko-online.ro.index'))->assertForbidden();
    }
}
