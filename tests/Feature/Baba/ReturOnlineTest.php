<?php

namespace Tests\Feature\Baba;

use App\Exceptions\PesananException;
use App\Http\Middleware\VerifikasiAplikasiMember;
use App\Models\AturanSumber;
use App\Models\AturanTagihan;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\ProdukOnline;
use App\Models\ProdukOnlineHarga;
use App\Models\ReturOnline;
use App\Models\TokoOnlinePengaturan;
use App\Models\TokoOnlineRekening;
use App\Models\User;
use App\Services\PesananOnlineService;
use App\Services\ReturOnlineService;
use App\Services\StokMemberService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 2d — retur pesanan online (gudang & upline): ajukan, setujui (stok & nilai), tolak, batal, API, halaman ERP. */
class ReturOnlineTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private const KUNCI = 'kunci-uji-retur-0123456789abcdef0123456';

    private Cabang $gudang;
    private Item $botol;
    private ProdukOnline $produk;
    private User $admin;
    private PesananOnlineService $svc;
    private ReturOnlineService $retur;
    private StokMemberService $sm;
    private MemberAkun $upline;
    private MemberAkun $pembeli;

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => self::KUNCI, 'baba.member_app.url' => 'http://member.uji']);
        Cache::flush();
        Storage::fake('local');
        Http::fake(['member.uji/*' => Http::response(['ok' => true])]);
        TokoOnlinePengaturan::ambil()->update(['hari_terima_otomatis' => 7, 'jam_batas_bayar' => 48, 'hari_batas_retur' => 7]);
        AturanTagihan::query()->update(['aktif' => false]);
        AturanSumber::query()->update(['aktif' => false]);
        AturanSumber::create(['cakupan' => 'semua', 'boleh_gudang' => true, 'boleh_upline' => true, 'aktif' => true]);

        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang RT ' . uniqid(), 'kode_cabang' => 'RT' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        TokoOnlineRekening::create(['cabang_id' => $this->gudang->id, 'bank' => 'BCA', 'nomor' => '111', 'atas_nama' => 'BABA', 'aktif' => true]);
        $this->botol = Item::create(['kode_item' => 'RTU-' . strtoupper(Str::random(6)), 'nama_item' => 'Amber Retur EDP 50 ml', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $this->gudang->id]);
        $this->produk = ProdukOnline::create(['item_id' => $this->botol->id, 'nama_tampil' => 'Amber Retur EDP 50 ml', 'aktif' => true]);
        ProdukOnlineHarga::create(['produk_online_id' => $this->produk->id, 'jenis' => 'tetap', 'harga' => 100000]);
        $this->admin = $this->buatUser('owner');
        $this->actingAs($this->admin);
        app(StokService::class)->masuk($this->botol->id, $this->gudang->id, 30, 'uji', 'uji', null, 27000);
        auth()->logout();

        $this->svc = app(PesananOnlineService::class);
        $this->retur = app(ReturOnlineService::class);
        $this->sm = app(StokMemberService::class);
        $this->upline = MemberAkun::updateOrCreate(['member_id' => 960001], ['nama' => 'Upline Retur', 'no_id' => 'B98-01', 'jabatan_id' => 27, 'jabatan' => 'SUPPLIER', 'aktif' => true]);
        $this->pembeli = MemberAkun::updateOrCreate(['member_id' => 960002], ['nama' => 'Pembeli Retur', 'no_id' => 'B98-01-01', 'jabatan_id' => 21, 'jabatan' => 'DISTRIBUTOR', 'aktif' => true, 'upline_member_id' => 960001]);
    }

    private function gagal(callable $fn, string $potongan): void
    {
        try {
            $fn();
            $this->fail("Seharusnya ditolak: {$potongan}");
        } catch (PesananException $e) {
            $this->assertStringContainsString($potongan, $e->getMessage());
        }
    }

    private function png(string $isi): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==') . $isi;
    }

    /** Pesanan gudang diterima. $bayar: transfer (lunas) / tagihan (belum lunas). */
    private function pesananDiterima(MemberAkun $m, int $qty, string $bayar = 'transfer'): PesananOnline
    {
        $p = $this->svc->buat($m, ['cara_ambil' => 'ambil', 'cara_bayar' => $bayar, 'item' => [['produk_online_id' => $this->produk->id, 'qty' => $qty]]]);
        if ($bayar === 'transfer') {
            $this->svc->terimaBukti($this->svc->unggahBukti($p, $m, $this->png('b' . uniqid())), $this->admin);
        }
        $this->svc->keluarkan($p->fresh(), $this->admin);
        $this->svc->terimaMember($p->fresh(), $m);

        return $p->fresh(['item']);
    }

    private function idItem(PesananOnline $p): int
    {
        return $p->item->first()->id;
    }

    public function test_ajukan_ditolak_untuk_kondisi_salah(): void
    {
        $belum = $this->svc->buat($this->pembeli, ['cara_ambil' => 'ambil', 'cara_bayar' => 'transfer', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => 1]]]);
        $this->gagal(fn () => $this->retur->ajukan($belum, $this->pembeli, ['alasan' => 'Botol bocor', 'item' => [['id' => $belum->item->first()->id, 'qty' => 1]]]), 'sudah diterima');
        $p = $this->pesananDiterima($this->pembeli, 3);
        $id = $this->idItem($p);
        $this->gagal(fn () => $this->retur->ajukan($p, $this->upline, ['alasan' => 'Botol bocor', 'item' => [['id' => $id, 'qty' => 1]]]), 'tidak ditemukan');
        $this->gagal(fn () => $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'x', 'item' => [['id' => $id, 'qty' => 1]]]), 'alasan');
        $this->gagal(fn () => $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Botol bocor', 'item' => [['id' => $id, 'qty' => 0]]]), 'Pilih barang');
        $this->gagal(fn () => $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Botol bocor', 'item' => [['id' => $id, 'qty' => 4]]]), 'maksimal 3');
        $this->gagal(fn () => $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Botol bocor', 'item' => [['id' => 999999, 'qty' => 1]]]), 'tidak ada di pesanan');
        $this->gagal(fn () => $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Botol bocor', 'item' => [['id' => $id, 'qty' => 1]]], 'bukan gambar'), 'JPG/PNG/WebP');
        // barang sudah dijual sebagian → stok member tinggal 1
        $this->sm->keluar(960002, $this->botol->id, 2, 'jual');
        $this->gagal(fn () => $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Botol bocor', 'item' => [['id' => $id, 'qty' => 2]]]), 'tinggal 1');
        // lewat batas hari
        $p->update(['diterima_at' => now()->subDays(8)]);
        $this->gagal(fn () => $this->retur->ajukan($p->fresh(), $this->pembeli, ['alasan' => 'Botol bocor', 'item' => [['id' => $id, 'qty' => 1]]]), 'Batas waktu retur');
        $this->assertSame(0, array_sum($this->retur->bisaRetur($p->fresh())));
        TokoOnlinePengaturan::ambil()->update(['hari_batas_retur' => 0]); // tanpa batas
        $this->assertSame(3, array_sum($this->retur->bisaRetur($p->fresh())));
    }

    public function test_setujui_retur_gudang_lunas_stok_dan_dana(): void
    {
        $p = $this->pesananDiterima($this->pembeli, 3);
        $this->assertEquals(27, app(StokService::class)->getStok($this->botol->id, $this->gudang->id));
        $r = $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Dua botol bocor', 'item' => [['id' => $this->idItem($p), 'qty' => 2]]], $this->png('foto'));
        Storage::disk('local')->assertExists($r->foto);
        $this->assertSame(['diajukan', 200000.0], [$r->status, (float) $r->nilai]);
        $this->assertSame(1, array_sum($this->retur->bisaRetur($p->fresh()))); // 2 sedang diajukan
        $this->gagal(fn () => $this->retur->setujui($r, $this->upline), 'tidak ditemukan');            // bukan penjualnya
        $this->gagal(fn () => $this->retur->setujui($r, $this->admin), 'cara mengembalikan dana'); // lunas → wajib pilih
        $this->retur->setujui($r, $this->admin, 'transfer', 'Sudah dicek');
        $r->refresh();
        $p->refresh();
        $this->assertSame(['disetujui', 'transfer'], [$r->status, $r->pengembalian]);
        $this->assertEquals(29, app(StokService::class)->getStok($this->botol->id, $this->gudang->id));
        $this->assertSame(1, $this->sm->qty(960002, $this->botol->id));
        $this->assertSame(2, $p->item->first()->qty_retur);
        $this->assertEquals(200000, (float) $p->nilai_retur);
        $this->assertSame('lunas', $p->status_bayar);
        $this->gagal(fn () => $this->retur->setujui($r, $this->admin, 'transfer'), 'sudah diputuskan');
        // sisa 1 masih bisa diretur, lalu tolak
        $r2 = $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Aroma berbeda', 'item' => [['id' => $this->idItem($p), 'qty' => 1]]]);
        $this->gagal(fn () => $this->retur->tolak($r2, $this->admin, ''), 'alasan');
        $this->retur->tolak($r2, $this->admin, 'Segel sudah dibuka');
        $this->assertSame('ditolak', $r2->fresh()->status);
        $this->assertSame(1, $this->sm->qty(960002, $this->botol->id));
        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/erp/notifikasi') && str_contains($req['judul'], 'Retur ' . $r->nomor . ' disetujui'));
    }

    public function test_retur_tagihan_memotong_dan_melunasi(): void
    {
        AturanTagihan::create(['cakupan' => 'semua', 'batas_nominal' => 5000000, 'tempo_hari' => 14, 'aktif' => true]);
        $p = $this->pesananDiterima($this->pembeli, 2, 'tagihan');
        $this->assertEquals(200000, $this->svc->tagihanTerpakai(960002));
        $r = $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Botol retak', 'item' => [['id' => $this->idItem($p), 'qty' => 1]]]);
        $this->retur->setujui($r, $this->admin);
        $p->refresh();
        $this->assertSame('potong_tagihan', $r->fresh()->pengembalian);
        $this->assertEquals(100000, $p->sisa_bayar);
        $this->assertEquals(100000, $this->svc->tagihanTerpakai(960002));
        // bukti pelunasan memakai sisa bayar
        $b = $this->svc->unggahBukti($p, $this->pembeli, $this->png('sisa'));
        $this->assertEquals(100000, (float) $b->nominal);
        $this->svc->tolakBukti($b, 'uji', $this->admin);
        // retur sisa → otomatis lunas
        $r2 = $this->retur->ajukan($p->fresh(), $this->pembeli, ['alasan' => 'Botol retak juga', 'item' => [['id' => $this->idItem($p), 'qty' => 1]]]);
        $this->retur->setujui($r2, $this->admin);
        $p->refresh();
        $this->assertSame(['lunas', 0.0], [$p->status_bayar, $p->sisa_bayar]);
        $this->assertEquals(0, $this->svc->tagihanTerpakai(960002));
        $this->assertFalse($this->svc->untukMember($p, true)['boleh']['unggah_bukti']);
    }

    public function test_batal_pembeli_dan_retur_ke_upline(): void
    {
        // upline punya stok dari gudang, menjual 2 ke pembeli
        $this->pesananDiterima($this->upline, 5);
        $p = $this->svc->buat($this->pembeli, ['sumber' => 'upline', 'cara_ambil' => 'ambil', 'cara_bayar' => 'tunai', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => 2]]]);
        $this->svc->keluarkan($p, $this->upline);
        $this->svc->terimaMember($p->fresh(), $this->pembeli);
        $p = $p->fresh(['item']);
        $this->assertSame(3, $this->sm->qty(960001, $this->botol->id));

        $r = $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Salah aroma', 'item' => [['id' => $this->idItem($p), 'qty' => 1]]]);
        $this->retur->batalPembeli($r, $this->pembeli);
        $this->assertSame('batal', $r->fresh()->status);
        $this->gagal(fn () => $this->retur->batalPembeli($r->fresh(), $this->pembeli), 'sudah diputuskan');

        $r = $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Salah aroma', 'item' => [['id' => $this->idItem($p), 'qty' => 2]]]);
        $this->gagal(fn () => $this->retur->setujui($r, $this->admin), 'diputuskan oleh upline');
        $this->retur->setujui($r, $this->upline); // tunai belum lunas → potong
        $this->assertSame(5, $this->sm->qty(960001, $this->botol->id));
        $this->assertSame(0, $this->sm->qty(960002, $this->botol->id));
        $this->assertSame('retur_masuk', \App\Models\StokMemberMutasi::where('member_id', 960001)->orderByDesc('id')->value('jenis'));
        $this->assertSame(['lunas', 'potong_tagihan'], [$p->fresh()->status_bayar, $r->fresh()->pengembalian]);
        Http::assertNotSent(fn ($req) => str_contains($req['judul'] ?? '', $r->nomor)); // antar member dikabarkan aplikasi member
    }

    private function api(string $method, string $path, MemberAkun $m, ?array $data = null)
    {
        [$pathSaja, $query] = array_pad(explode('?', $path, 2), 2, '');
        $up = $m->upline_member_id ? MemberAkun::where('member_id', $m->upline_member_id)->first() : null;
        $profil = base64_encode(json_encode(['id' => $m->member_id, 'nama' => $m->nama, 'no_id' => $m->no_id, 'jabatan_id' => $m->jabatan_id, 'jabatan' => $m->jabatan, 'aktif' => true,
            'upline' => $up ? ['id' => $up->member_id, 'nama' => $up->nama, 'no_id' => $up->no_id, 'jabatan_id' => $up->jabatan_id, 'jabatan' => $up->jabatan, 'aktif' => true] : null]));
        $body = $data === null ? '' : json_encode($data);
        $waktu = (string) time();
        $nonce = Str::random(32);
        $tanda = hash_hmac('sha256', VerifikasiAplikasiMember::kanonik($method, $pathSaja, $query, $waktu, $nonce, (string) $m->member_id, $profil, $body), self::KUNCI);

        return $this->call($method, $path, [], [], [], ['HTTP_X-Baba-Waktu' => $waktu, 'HTTP_X-Baba-Nonce' => $nonce, 'HTTP_X-Baba-Member' => (string) $m->member_id,
            'HTTP_X-Baba-Profil' => $profil, 'HTTP_X-Baba-Tanda' => $tanda, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], $body === '' ? null : $body);
    }

    public function test_api_retur_pembeli_dan_upline(): void
    {
        $this->pesananDiterima($this->upline, 4);
        $p = $this->svc->buat($this->pembeli, ['sumber' => 'upline', 'cara_ambil' => 'ambil', 'cara_bayar' => 'tunai', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => 2]]]);
        $this->svc->terimaTunai($p, $this->upline);
        $this->svc->keluarkan($p->fresh(), $this->upline);
        $this->svc->terimaMember($p->fresh(), $this->pembeli);

        $rinci = $this->api('GET', "/api/member/pesanan/{$p->nomor}", $this->pembeli)->assertOk();
        $this->assertTrue($rinci->json('data.boleh.retur'));
        $itemId = $rinci->json('data.item.0.id');
        $this->assertSame(2, $rinci->json('data.item.0.bisa_retur'));

        $this->api('POST', "/api/member/pesanan/{$p->nomor}/retur", $this->pembeli, ['alasan' => 'x', 'item' => [['id' => $itemId, 'qty' => 1]]])->assertStatus(422);
        $aj = $this->api('POST', "/api/member/pesanan/{$p->nomor}/retur", $this->pembeli, ['alasan' => 'Botol bocor saat dibuka', 'item' => [['id' => $itemId, 'qty' => 1]],
            'foto' => 'data:image/png;base64,' . base64_encode($this->png('api'))])->assertStatus(201);
        $nomor = $aj->json('data.nomor');
        $this->assertTrue($aj->json('data.ada_foto'));
        $this->assertSame($nomor, $this->api('GET', '/api/member/retur', $this->pembeli)->json('data.0.nomor'));
        $this->assertSame($nomor, $this->api('GET', "/api/member/pesanan/{$p->nomor}", $this->pembeli)->json('data.retur.0.nomor'));
        $this->api('GET', "/api/member/retur/{$nomor}", $this->upline)->assertStatus(422); // bukan miliknya sebagai pembeli

        $masuk = $this->api('GET', '/api/member/retur-masuk', $this->upline)->assertOk();
        $this->assertSame([$nomor, 1], [$masuk->json('data.0.nomor'), $masuk->json('jumlah.diajukan')]);
        $this->assertTrue($masuk->json('data.0.pesanan_lunas'));
        $this->api('GET', "/api/member/retur-masuk/{$nomor}", $this->pembeli)->assertStatus(422);
        $this->assertSame('image/png', $this->api('GET', "/api/member/retur-masuk/{$nomor}/foto", $this->upline)->json('mime'));
        $this->api('POST', "/api/member/retur-masuk/{$nomor}/setujui", $this->upline, [])->assertStatus(422); // lunas → wajib pilih pengembalian
        $ok = $this->api('POST', "/api/member/retur-masuk/{$nomor}/setujui", $this->upline, ['pengembalian' => 'tunai'])->assertOk();
        $this->assertSame(['disetujui', 'tunai'], [$ok->json('data.status'), $ok->json('data.pengembalian')]);
        $this->assertSame(3, $this->sm->qty(960001, $this->botol->id));

        $aj2 = $this->api('POST', "/api/member/pesanan/{$p->nomor}/retur", $this->pembeli, ['alasan' => 'Botol kedua juga bocor', 'item' => [['id' => $itemId, 'qty' => 1]]])->assertStatus(201);
        $this->api('POST', '/api/member/retur/' . $aj2->json('data.nomor') . '/batal', $this->pembeli)->assertOk()->assertJsonPath('data.status', 'batal');
        $aj3 = $this->api('POST', "/api/member/pesanan/{$p->nomor}/retur", $this->pembeli, ['alasan' => 'Botol kedua juga bocor', 'item' => [['id' => $itemId, 'qty' => 1]]])->assertStatus(201);
        $this->api('POST', '/api/member/retur-masuk/' . $aj3->json('data.nomor') . '/tolak', $this->upline, ['alasan' => 'Segel dibuka'])->assertOk()->assertJsonPath('data.status', 'ditolak');
    }

    public function test_halaman_retur_erp(): void
    {
        $p = $this->pesananDiterima($this->pembeli, 2);
        $r = $this->retur->ajukan($p, $this->pembeli, ['alasan' => 'Botol bocor', 'item' => [['id' => $this->idItem($p), 'qty' => 1]]], $this->png('erp'));
        $this->actingAs($this->buatUser('kasir'))->get(route('toko-online.retur.index'))->assertForbidden();
        $this->actingAs($this->admin);
        $this->get(route('toko-online.retur.index'))->assertOk()->assertSee($r->nomor)->assertSee('Antar member');
        $this->get(route('toko-online.retur.show', $r))->assertOk()->assertSee('Setujui retur')->assertSee('Dana ditransfer kembali');
        $this->get(route('toko-online.retur.foto', $r))->assertOk();
        $this->post(route('toko-online.retur.setujui', $r), [])->assertSessionHas('error');
        $this->post(route('toko-online.retur.setujui', $r), ['pengembalian' => 'tunai'])->assertSessionHas('success');
        $this->assertSame('disetujui', $r->fresh()->status);
        $this->get(route('toko-online.retur.index', ['tab' => 'disetujui']))->assertOk()->assertSee($r->nomor);
        $this->get(route('toko-online.pengaturan'))->assertOk()->assertSee('Batas pengajuan retur');

        $lain = Cabang::create(['nama_cabang' => 'Gudang Lain RT ' . uniqid(), 'kode_cabang' => 'RL' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->actingAs($this->buatUser('admin_gudang', $lain->id))->get(route('toko-online.retur.show', $r))->assertNotFound();
    }
}
