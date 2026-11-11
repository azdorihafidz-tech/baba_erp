<?php

namespace Tests\Feature\Baba;

use App\Http\Middleware\VerifikasiAplikasiMember;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\MemberAkun;
use App\Models\ProdukOnline;
use App\Models\ProdukOnlineHarga;
use App\Services\HargaOnlineService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 2a — Produk Online, daftar harga, katalog untuk aplikasi member, sambungan ERP → aplikasi member. */
class TokoOnlineTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private const KUNCI = 'kunci-uji-toko-online-0123456789abcdef';
    private const JAB_DIST = 21;
    private const JAB_RESELLER = 24;

    private Cabang $gudang;
    private Item $botol;
    private Item $botol2;

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => self::KUNCI, 'baba.member_app.url' => 'http://member.uji']);
        Cache::flush();
        \App\Models\TokoOnlinePengaturan::ambil()->update(['status_toko' => 'buka']); // Tahap 2f: saklar toko
        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang TO Uji ' . uniqid(), 'kode_cabang' => 'TO' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->botol = $this->barang('Ocean Uji EDP 50 ml');
        $this->botol2 = $this->barang('Rose Uji EDP 30 ml');
        $this->actingAs($this->buatUser('owner'));
        app(StokService::class)->masuk($this->botol->id, $this->gudang->id, 12, 'uji', 'uji', null, 27000);
    }

    private function barang(string $nama): Item
    {
        return Item::create(['kode_item' => 'TOU-' . strtoupper(substr(md5($nama . uniqid()), 0, 8)), 'nama_item' => $nama, 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $this->gudang->id]);
    }

    private function produk(Item $item, array $harga, bool $aktif = true): ProdukOnline
    {
        $p = ProdukOnline::create(['item_id' => $item->id, 'nama_tampil' => $item->nama_item, 'kategori' => 'Unisex', 'aktif' => $aktif]);
        foreach ($harga as $h) {
            ProdukOnlineHarga::create(['produk_online_id' => $p->id] + $h);
        }

        return $p->load('harga');
    }

    private function member(int $id, ?int $jabatan, bool $aktif = true): MemberAkun
    {
        return MemberAkun::create(['member_id' => $id, 'nama' => "Member {$id}", 'jabatan_id' => $jabatan, 'aktif' => $aktif]);
    }

    /** Permintaan bertanda tangan seperti dari aplikasi member. */
    private function katalog(int $memberId, ?array $profil, string $query = '')
    {
        $p = $profil ? base64_encode(json_encode($profil)) : '';
        $waktu = (string) time();
        $nonce = Str::random(32);
        $tanda = hash_hmac('sha256', VerifikasiAplikasiMember::kanonik('GET', '/api/member/katalog', $query, $waktu, $nonce, (string) $memberId, $p, ''), self::KUNCI);

        return $this->get('/api/member/katalog' . ($query ? "?{$query}" : ''), ['X-Baba-Waktu' => $waktu, 'X-Baba-Nonce' => $nonce, 'X-Baba-Member' => (string) $memberId,
            'X-Baba-Profil' => $p, 'X-Baba-Tanda' => $tanda, 'Accept' => 'application/json']);
    }

    public function test_urutan_harga_akun_jabatan_gudang_tetap(): void
    {
        $p = $this->produk($this->botol, [
            ['jenis' => 'tetap', 'harga' => 120000], ['jenis' => 'gudang', 'harga' => 110000],
            ['jenis' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'harga' => 90000],
            ['jenis' => 'akun', 'member_id' => 7001, 'harga' => 80000],
        ]);
        $s = app(HargaOnlineService::class);

        $this->assertSame(['harga' => 80000.0, 'dasar' => 'akun'], $s->untuk($p, $this->member(7001, self::JAB_DIST)));
        $this->assertSame(['harga' => 90000.0, 'dasar' => 'jabatan'], $s->untuk($p, $this->member(7002, self::JAB_DIST)));
        $this->assertSame(['harga' => 110000.0, 'dasar' => 'gudang'], $s->untuk($p, $this->member(7003, self::JAB_RESELLER)));
        $this->assertSame(['harga' => 120000.0, 'dasar' => 'tetap'], $s->untuk($p, MemberAkun::where('member_id', 7003)->first(), HargaOnlineService::SUMBER_UPLINE));

        $tanpa = $this->produk($this->botol2, [['jenis' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'harga' => 50000]]);
        $this->assertNull($s->untuk($tanpa, MemberAkun::where('member_id', 7003)->first()));
    }

    public function test_katalog_api_harga_sesuai_member_dan_stok(): void
    {
        $this->produk($this->botol, [['jenis' => 'gudang', 'harga' => 110000], ['jenis' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'harga' => 90000]]);
        $this->produk($this->botol2, [['jenis' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'harga' => 50000]]); // hanya untuk Distributor
        $this->produk($this->barang('Disembunyikan Uji'), [['jenis' => 'tetap', 'harga' => 1]], false);

        $dist = ['id' => 8101, 'nama' => 'Dist Uji', 'jabatan_id' => self::JAB_DIST, 'jabatan' => 'DISTRIBUTOR', 'aktif' => true];
        $r = $this->katalog(8101, $dist)->assertOk()->json();
        $data = collect($r['data'])->keyBy('nama');
        $this->assertSame(90000, (int) $data['Ocean Uji EDP 50 ml']['harga']);
        $this->assertSame('Harga jabatan', $data['Ocean Uji EDP 50 ml']['label_harga']);
        $this->assertEquals(12, $data['Ocean Uji EDP 50 ml']['stok']);
        $this->assertSame(50000, (int) $data['Rose Uji EDP 30 ml']['harga']);
        $this->assertFalse($data->has('Disembunyikan Uji'));

        $reseller = ['id' => 8102, 'nama' => 'Reseller Uji', 'jabatan_id' => self::JAB_RESELLER, 'jabatan' => 'RESELLER', 'aktif' => true];
        $data2 = collect($this->katalog(8102, $reseller)->assertOk()->json('data'))->keyBy('nama');
        $this->assertSame(110000, (int) $data2['Ocean Uji EDP 50 ml']['harga']);
        $this->assertFalse($data2->has('Rose Uji EDP 30 ml')); // tanpa harga yang berlaku → tidak tampil
        $this->assertSame(self::JAB_RESELLER, MemberAkun::where('member_id', 8102)->value('jabatan_id'));
    }

    public function test_katalog_ditolak_tanpa_profil_atau_akun_belum_aktif(): void
    {
        $this->katalog(8201, null)->assertStatus(422);
        $this->katalog(8202, ['id' => 8202, 'nama' => 'Belum Aktif', 'jabatan_id' => self::JAB_DIST, 'aktif' => false])->assertStatus(403);
    }

    public function test_tambah_dan_ubah_produk_online_dengan_harga_jabatan_dan_akun(): void
    {
        Http::fake(['member.uji/api/erp/jabatan*' => Http::response(['ok' => true, 'data' => [
            ['id' => self::JAB_DIST, 'nama' => 'DISTRIBUTOR', 'level' => 6], ['id' => self::JAB_RESELLER, 'nama' => 'RESELLER', 'level' => 7],
        ]])]);

        $this->get(route('toko-online.produk.create'))->assertOk()->assertSee('Ocean Uji EDP 50 ml');
        $this->post(route('toko-online.produk.store'), ['item_id' => $this->botol->id, 'nama_tampil' => 'Ocean Breeze 50 ml', 'kategori' => 'Unisex',
            'harga_tetap' => 120000, 'harga_gudang' => '', 'aktif' => 1])->assertSessionHas('success');
        $p = ProdukOnline::where('item_id', $this->botol->id)->firstOrFail();

        $this->get(route('toko-online.produk.edit', $p))->assertOk()->assertSee('DISTRIBUTOR')->assertSee('RESELLER');
        $this->put(route('toko-online.produk.update', $p), ['item_id' => $this->botol->id, 'nama_tampil' => 'Ocean Breeze 50 ml', 'aktif' => 1,
            'harga_tetap' => 125000, 'harga_gudang' => 115000,
            'harga_jabatan' => [self::JAB_DIST => 95000, self::JAB_RESELLER => ''], 'harga_akun' => [9001 => 85000]])->assertSessionHas('success');

        $p->refresh()->load('harga');
        $this->assertSame(125000.0, $p->hargaJenis('tetap'));
        $this->assertSame(115000.0, $p->hargaJenis('gudang'));
        $this->assertSame(1, $p->harga->where('jenis', 'jabatan')->count());
        $this->assertSame(85000.0, (float) $p->harga->firstWhere('member_id', 9001)->harga);

        // hapus harga khusus & kosongkan gudang
        $this->put(route('toko-online.produk.update', $p), ['item_id' => $this->botol->id, 'nama_tampil' => 'Ocean Breeze 50 ml', 'aktif' => 0,
            'harga_tetap' => 125000, 'harga_jabatan' => [self::JAB_DIST => 95000]])->assertSessionHas('success');
        $p->refresh()->load('harga');
        $this->assertFalse($p->aktif);
        $this->assertNull($p->hargaJenis('gudang'));
        $this->assertSame(0, $p->harga->where('jenis', 'akun')->count());
        $this->assertTrue(\Spatie\Activitylog\Models\Activity::where('log_name', 'ProdukOnlineHarga')->exists(), 'perubahan harga tercatat');
    }

    public function test_barang_gudang_lain_atau_sudah_dijual_ditolak_dan_tanpa_izin_403(): void
    {
        $this->produk($this->botol, [['jenis' => 'tetap', 'harga' => 1]]);
        $this->post(route('toko-online.produk.store'), ['item_id' => $this->botol->id, 'nama_tampil' => 'Ganda'])->assertSessionHasErrors('item_id');

        $kasir = $this->buatUser('kasir');
        $this->actingAs($kasir)->get(route('toko-online.produk.index'))->assertForbidden();
    }

    public function test_edit_tetap_bisa_saat_aplikasi_member_tidak_terhubung(): void
    {
        Http::fake(['member.uji/*' => Http::response('gagal', 500)]);
        $p = $this->produk($this->botol, [['jenis' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'harga' => 90000]]);
        $this->get(route('toko-online.produk.edit', $p))->assertOk()->assertSee('Daftar jabatan belum bisa diambil')
            ->assertSee('name="harga_jabatan[' . self::JAB_DIST . ']"', false); // harga tersimpan ikut terkirim saat disimpan
    }

    public function test_cari_akun_lewat_aplikasi_member_ditandatangani(): void
    {
        Http::fake(['member.uji/api/erp/akun*' => Http::response(['ok' => true, 'data' => [
            ['id' => 342, 'nama' => 'Atika Uji', 'no_id' => 'B56-14', 'no_member' => 'BP-00342', 'jabatan_id' => 27, 'jabatan' => 'SUPPLIER', 'cabang' => 'MDN'],
        ]])]);
        $this->getJson(route('toko-online.cari-akun', ['q' => 'Atika']))->assertOk()->assertJsonPath('data.0.no_member', 'BP-00342');
        $this->assertSame('B56-14', MemberAkun::where('member_id', 342)->value('no_id'));

        Http::assertSent(function (HttpRequest $r) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            $q = (string) parse_url($r->url(), PHP_URL_QUERY);
            $harap = hash_hmac('sha256', VerifikasiAplikasiMember::kanonik('GET', $path, $q, $r->header('X-Baba-Waktu')[0], $r->header('X-Baba-Nonce')[0], '', '', ''), self::KUNCI);

            return $path === '/api/erp/akun' && $q === 'cari=Atika' && hash_equals($harap, $r->header('X-Baba-Tanda')[0]);
        });
    }
}
