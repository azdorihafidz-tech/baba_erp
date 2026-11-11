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
use App\Models\StokMember;
use App\Models\TokoOnlinePengaturan;
use App\Models\TokoOnlineRekening;
use App\Models\User;
use App\Services\MemberAkunService;
use App\Services\PesananOnlineService;
use App\Services\StokMemberService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 2c — belanja ke upline, stok member, Toko Saya (pesanan masuk, stok, rekening), aturan sumber. */
class PesananUplineTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private const KUNCI = 'kunci-uji-upline-0123456789abcdef0123';
    private const JAB_SUP = 27;
    private const JAB_DIST = 21;

    private Cabang $gudang;
    private Item $botol;
    private ProdukOnline $produk;
    private User $admin;
    private PesananOnlineService $svc;
    private MemberAkun $upline;   // Supplier
    private MemberAkun $pembeli;  // Distributor di bawahnya

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => self::KUNCI, 'baba.member_app.url' => 'http://member.uji']);
        Cache::flush();
        Storage::fake('local');
        Http::fake(['member.uji/*' => Http::response(['ok' => true])]);
        TokoOnlinePengaturan::ambil()->update(['hari_terima_otomatis' => 7, 'jam_batas_bayar' => 48]);
        AturanTagihan::query()->update(['aktif' => false]);
        AturanSumber::query()->update(['aktif' => false]);

        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang UP ' . uniqid(), 'kode_cabang' => 'UP' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        TokoOnlineRekening::create(['cabang_id' => $this->gudang->id, 'bank' => 'BCA', 'nomor' => '111', 'atas_nama' => 'BABA', 'aktif' => true]);
        $this->botol = Item::create(['kode_item' => 'UPU-' . strtoupper(Str::random(6)), 'nama_item' => 'Musk Upline EDP 50 ml', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $this->gudang->id]);
        $this->produk = ProdukOnline::create(['item_id' => $this->botol->id, 'nama_tampil' => 'Musk Upline EDP 50 ml', 'aktif' => true]);
        foreach ([['jenis' => 'tetap', 'harga' => 150000], ['jenis' => 'gudang', 'harga' => 130000], ['jenis' => 'jabatan', 'jabatan_id' => self::JAB_SUP, 'harga' => 100000]] as $h) {
            ProdukOnlineHarga::create(['produk_online_id' => $this->produk->id] + $h);
        }
        $this->admin = $this->buatUser('owner');
        $this->actingAs($this->admin);
        app(StokService::class)->masuk($this->botol->id, $this->gudang->id, 20, 'uji', 'uji', null, 27000);
        auth()->logout();

        $this->svc = app(PesananOnlineService::class);
        $this->upline = MemberAkun::updateOrCreate(['member_id' => 980001], ['nama' => 'Supplier Uji', 'no_id' => 'B99-01', 'jabatan_id' => self::JAB_SUP, 'jabatan' => 'SUPPLIER', 'aktif' => true]);
        $this->pembeli = MemberAkun::updateOrCreate(['member_id' => 980002], ['nama' => 'Dist Uji', 'no_id' => 'B99-01-01', 'jabatan_id' => self::JAB_DIST, 'jabatan' => 'DISTRIBUTOR', 'aktif' => true, 'upline_member_id' => 980001]);
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

    private function bolehUpline(): void
    {
        AturanSumber::create(['cakupan' => 'semua', 'boleh_gudang' => true, 'boleh_upline' => true, 'aktif' => true]);
    }

    /** Upline belanja ke gudang 5 botol (Rp 100.000 harga jabatan Supplier) sampai diterima → stok upline 5. */
    private function isiStokUpline(int $qty = 5): PesananOnline
    {
        $p = $this->svc->buat($this->upline, ['cara_ambil' => 'ambil', 'cara_bayar' => 'transfer', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => $qty]]]);
        $this->svc->terimaBukti($this->svc->unggahBukti($p, $this->upline, $this->png('stok' . $qty . uniqid())), $this->admin);
        $this->svc->keluarkan($p->fresh(), $this->admin);
        $this->svc->terimaMember($p->fresh(), $this->upline);

        return $p->fresh();
    }

    private function png(string $isi): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==') . $isi;
    }

    private function keUpline(int $qty, array $lain = []): PesananOnline
    {
        return $this->svc->buat($this->pembeli, $lain + ['sumber' => 'upline', 'cara_ambil' => 'ambil', 'cara_bayar' => 'tunai', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => $qty]]]);
    }

    // ===================== stok member =====================

    public function test_pesanan_diterima_menambah_stok_pembeli_dengan_modal_rata_rata(): void
    {
        $this->isiStokUpline(5);
        $s = StokMember::where('member_id', 980001)->where('item_id', $this->botol->id)->first();
        $this->assertSame(5, $s->qty);
        $this->assertEquals(100000, (float) $s->nilai_rata);
        // beli lagi 5 dengan harga khusus akun 80.000 → rata-rata 90.000
        ProdukOnlineHarga::create(['produk_online_id' => $this->produk->id, 'jenis' => 'akun', 'member_id' => 980001, 'harga' => 80000]);
        $this->isiStokUpline(5);
        $s->refresh();
        $this->assertSame(10, $s->qty);
        $this->assertEquals(90000, (float) $s->nilai_rata);
        $this->assertSame(2, \App\Models\StokMemberMutasi::where('member_id', 980001)->where('jenis', 'beli')->count());
        // stok member tidak pernah minus
        $this->expectException(PesananException::class);
        app(StokMemberService::class)->keluar(980001, $this->botol->id, 11, 'jual');
    }

    // ===================== aturan sumber & upline =====================

    public function test_aturan_sumber_dan_upline_aktif(): void
    {
        $this->isiStokUpline(5);
        $this->assertSame(['gudang' => true, 'upline' => false], $this->svc->sumberBoleh($this->pembeli));
        $this->gagal(fn () => $this->keUpline(1), 'belum diizinkan belanja ke upline');
        AturanSumber::create(['cakupan' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'boleh_gudang' => false, 'boleh_upline' => true, 'aktif' => true]);
        $this->gagal(fn () => $this->svc->buat($this->pembeli, ['cara_ambil' => 'ambil', 'cara_bayar' => 'transfer', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => 1]]]), 'langsung ke gudang');
        $this->assertSame('disiapkan', $this->keUpline(1)->status); // tunai + ambil
        // aturan akun mengalahkan aturan jabatan
        AturanSumber::create(['cakupan' => 'akun', 'member_id' => 980002, 'boleh_gudang' => true, 'boleh_upline' => false, 'aktif' => true]);
        $this->gagal(fn () => $this->keUpline(1), 'belum diizinkan belanja ke upline');
        AturanSumber::where('cakupan', 'akun')->update(['aktif' => false]);
        // upline tidak aktif / tidak ada
        $this->upline->update(['aktif' => false]);
        $this->gagal(fn () => $this->keUpline(1), 'tidak ada upline aktif');
        $this->upline->update(['aktif' => true]);
        $this->pembeli->update(['upline_member_id' => null]);
        $this->gagal(fn () => $this->keUpline(1), 'tidak ada upline aktif');
    }

    public function test_pesanan_ke_upline_stok_harga_bayar(): void
    {
        $this->bolehUpline();
        $this->gagal(fn () => $this->keUpline(1), 'sedang habis di upline Anda');
        $this->isiStokUpline(5);
        $this->gagal(fn () => $this->keUpline(6), 'tinggal 5');
        $p = $this->keUpline(3);
        // ke upline: harga gudang dilewati → harga tetap (Distributor tidak punya harga jabatan)
        $this->assertEquals(450000, (float) $p->total);
        $this->assertSame('tetap', $p->item->first()->dasar_harga);
        $this->assertSame(980001, $p->sumber_member_id);
        $this->assertNull($p->sumber_cabang_id);
        $this->assertSame(2, $this->svc->tersediaUpline($this->botol->id, 980001));
        $this->gagal(fn () => $this->keUpline(1, ['cara_bayar' => 'tagihan']), 'transfer atau tunai');
        $this->gagal(fn () => $this->keUpline(1, ['cara_bayar' => 'transfer']), 'Upline Anda belum mencantumkan rekening');
        TokoOnlineRekening::create(['member_id' => 980001, 'bank' => 'BRI', 'nomor' => '222', 'atas_nama' => 'Supplier Uji', 'aktif' => true]);
        $t = $this->keUpline(1, ['cara_bayar' => 'transfer']);
        $this->assertSame('menunggu_bayar', $t->status);
        $this->assertSame('222', $this->svc->untukMember($t, true)['rekening'][0]['nomor']);
        // pesanan gudang tidak terpengaruh stok upline
        $this->assertEquals(15, $this->svc->tersedia($this->botol->id, $this->gudang->id));
    }

    public function test_upline_memproses_tunai_kirim_diterima_stok_pindah(): void
    {
        $this->bolehUpline();
        $this->isiStokUpline(5);
        $p = $this->keUpline(2, ['cara_ambil' => 'kirim', 'kirim_nama' => 'Dist', 'kirim_hp' => '0812', 'kirim_alamat' => 'Jl. Downline No. 3, Medan']);
        $this->assertSame('menunggu_ongkir', $p->status);
        // orang lain / admin tidak bisa memproses pesanan ke upline
        $lain = MemberAkun::updateOrCreate(['member_id' => 980009], ['nama' => 'Lain', 'jabatan_id' => self::JAB_SUP, 'aktif' => true]);
        $this->gagal(fn () => $this->svc->aturOngkir($p, 10000, $lain), 'tidak ditemukan');
        $this->gagal(fn () => $this->svc->aturOngkir($p, 10000, $this->admin), 'diproses oleh upline');
        $this->gagal(fn () => $this->svc->terimaTunai($p, $this->upline), 'tidak sedang menunggu pembayaran tunai');

        $this->svc->aturOngkir($p, 10000, $this->upline);
        $p = $p->fresh();
        $this->assertSame(['disiapkan', 310000.0], [$p->status, (float) $p->total]); // tunai → langsung disiapkan
        Http::assertNotSent(fn (\Illuminate\Http\Client\Request $r) => ($r['user_id'] ?? null) === 980002); // tindakan antar member dikabarkan aplikasi member, bukan ERP

        $this->svc->terimaTunai($p, $this->upline);
        $this->assertSame('lunas', $p->fresh()->status_bayar);
        $this->gagal(fn () => $this->svc->terimaTunai($p->fresh(), $this->upline), 'sudah lunas');

        $this->svc->keluarkan($p->fresh(), $this->upline, 'Ojek', null);
        $p = $p->fresh(['item']);
        $this->assertSame('dikirim', $p->status);
        $this->assertSame(3, app(StokMemberService::class)->qty(980001, $this->botol->id));
        $this->assertEquals(100000, (float) $p->item->first()->hpp); // modal upline
        $this->gagal(fn () => $this->svc->batalAdmin($p, 'coba', $this->upline), 'retur');

        $this->svc->terimaMember($p, $this->pembeli);
        $this->assertSame(2, app(StokMemberService::class)->qty(980002, $this->botol->id));
        $this->assertEquals(150000, (float) StokMember::where('member_id', 980002)->value('nilai_rata'));
        $rinci = $this->svc->untukPenjual($p->fresh());
        $this->assertSame('Pembeli', collect($rinci['riwayat'])->last()['oleh']);
        $this->assertSame('Anda', collect($this->svc->untukMember($p->fresh(), true)['riwayat'])->last()['oleh']);
    }

    public function test_bukti_transfer_dicek_upline_dan_batal(): void
    {
        $this->bolehUpline();
        $this->isiStokUpline(5);
        TokoOnlineRekening::create(['member_id' => 980001, 'bank' => 'BRI', 'nomor' => '222', 'atas_nama' => 'Supplier Uji', 'aktif' => true]);
        $p = $this->keUpline(1, ['cara_bayar' => 'transfer']);
        $b = $this->svc->unggahBukti($p, $this->pembeli, $this->png('up1'));
        $this->assertSame(980001, (int) $this->svc->untukPenjual($p->fresh())['boleh']['cek_bukti'] === $b->id ? 980001 : 0);
        $this->gagal(fn () => $this->svc->terimaBukti($b, $this->admin), 'diproses oleh upline');
        $this->svc->terimaBukti($b, $this->upline);
        $this->assertSame(980001, (int) $b->fresh()->dicek_member_id);
        $this->assertSame(['disiapkan', 'lunas'], [$p->fresh()->status, $p->fresh()->status_bayar]);
        // admin boleh membatalkan pesanan ke upline yang macet; stok dipesan kembali
        $this->svc->batalAdmin($p->fresh(), 'Upline tidak merespons', $this->admin);
        $this->assertSame('batal', $p->fresh()->status);
        $this->assertSame(5, $this->svc->tersediaUpline($this->botol->id, 980001));
    }

    public function test_otomatis_diterima_menambah_stok_pembeli(): void
    {
        $this->bolehUpline();
        $this->isiStokUpline(3);
        $p = $this->keUpline(1);
        $this->svc->keluarkan($p, $this->upline);
        $p->fresh()->update(['dikirim_at' => now()->subDays(8)]);
        $this->assertSame(1, $this->svc->jalankanOtomatis()['diterima']);
        $this->assertSame(1, app(StokMemberService::class)->qty(980002, $this->botol->id));
        $this->assertTrue($p->fresh()->diterima_otomatis);
    }

    public function test_profil_mengirim_upline_terdekat(): void
    {
        $m = app(MemberAkunService::class)->simpan(970002, ['id' => 970002, 'nama' => 'Baru', 'jabatan_id' => 24, 'aktif' => true, 'parent_id' => 5555,
            'upline' => ['id' => 970001, 'nama' => 'Upline Nyata', 'no_id' => 'B01-02', 'jabatan_id' => 21, 'jabatan' => 'DISTRIBUTOR', 'aktif' => true]]);
        $this->assertSame(970001, $m->upline_member_id);
        $this->assertSame(5555, $m->parent_member_id);
        $this->assertSame('Upline Nyata', MemberAkun::where('member_id', 970001)->value('nama'));
        $m = app(MemberAkunService::class)->simpan(970002, ['id' => 970002, 'nama' => 'Baru', 'jabatan_id' => 24, 'aktif' => true, 'upline' => null]);
        $this->assertNull($m->upline_member_id);
    }

    // ===================== API Toko Saya =====================

    private function api(string $method, string $path, MemberAkun $m, ?array $data = null)
    {
        [$pathSaja, $query] = array_pad(explode('?', $path, 2), 2, '');
        $up = $m->upline_member_id ? MemberAkun::where('member_id', $m->upline_member_id)->first() : null;
        $profil = base64_encode(json_encode(['id' => $m->member_id, 'nama' => $m->nama, 'no_id' => $m->no_id, 'jabatan_id' => $m->jabatan_id, 'jabatan' => $m->jabatan, 'aktif' => $m->aktif,
            'upline' => $up ? ['id' => $up->member_id, 'nama' => $up->nama, 'no_id' => $up->no_id, 'jabatan_id' => $up->jabatan_id, 'jabatan' => $up->jabatan, 'aktif' => $up->aktif] : null]));
        $body = $data === null ? '' : json_encode($data);
        $waktu = (string) time();
        $nonce = Str::random(32);
        $tanda = hash_hmac('sha256', VerifikasiAplikasiMember::kanonik($method, $pathSaja, $query, $waktu, $nonce, (string) $m->member_id, $profil, $body), self::KUNCI);

        return $this->call($method, $path, [], [], [], ['HTTP_X-Baba-Waktu' => $waktu, 'HTTP_X-Baba-Nonce' => $nonce, 'HTTP_X-Baba-Member' => (string) $m->member_id,
            'HTTP_X-Baba-Profil' => $profil, 'HTTP_X-Baba-Tanda' => $tanda, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], $body === '' ? null : $body);
    }

    public function test_api_belanja_ke_upline_dan_toko_saya(): void
    {
        $this->bolehUpline();
        $this->isiStokUpline(4);
        $opsi = $this->api('GET', '/api/member/checkout-opsi', $this->pembeli)->assertOk();
        $this->assertSame(['gudang' => true, 'upline' => true], $opsi->json('sumber'));
        $this->assertSame(980001, $opsi->json('upline.id'));
        $kat = $this->api('GET', '/api/member/katalog?sumber=upline', $this->pembeli)->assertOk();
        $this->assertSame([4], collect($kat->json('data'))->pluck('stok')->all());
        $this->assertStringContainsString('Supplier Uji', $kat->json('upline.label'));

        // rekening upline (Toko Saya)
        $this->api('POST', '/api/member/rekening', $this->upline, ['bank' => 'BRI', 'nomor' => 'abc', 'atas_nama' => 'S'])->assertStatus(422);
        $rek = $this->api('POST', '/api/member/rekening', $this->upline, ['bank' => 'BRI', 'nomor' => '333 444', 'atas_nama' => 'Supplier Uji'])->assertOk();
        $idRek = $rek->json('data.0.id');
        $this->api('POST', "/api/member/rekening/{$idRek}/aktif", $this->pembeli)->assertStatus(422); // bukan miliknya

        $buat = $this->api('POST', '/api/member/pesanan', $this->pembeli, ['sumber' => 'upline', 'cara_ambil' => 'ambil', 'cara_bayar' => 'transfer',
            'item' => [['produk_online_id' => $this->produk->id, 'qty' => 2]]])->assertStatus(201);
        $nomor = $buat->json('data.nomor');
        $this->assertSame(980001, $buat->json('data.penjual_id'));
        $this->assertSame('333 444', $buat->json('data.rekening.0.nomor'));
        $this->api('POST', "/api/member/pesanan/{$nomor}/bukti", $this->pembeli, ['file' => base64_encode($this->png('api-up'))])->assertOk();

        // upline: pesanan masuk
        $daftar = $this->api('GET', '/api/member/masuk?kelompok=perlu', $this->upline)->assertOk();
        $this->assertSame($nomor, $daftar->json('data.0.nomor'));
        $this->assertSame(1, $daftar->json('jumlah.perlu'));
        $this->assertSame(980002, $daftar->json('data.0.pembeli.id'));
        $this->api('GET', "/api/member/masuk/{$nomor}", $this->pembeli)->assertStatus(422); // pembeli bukan penjual
        $rinci = $this->api('GET', "/api/member/masuk/{$nomor}", $this->upline)->assertOk();
        $idBukti = $rinci->json('data.boleh.cek_bukti');
        $file = $this->api('GET', "/api/member/masuk/{$nomor}/bayar/{$idBukti}/file", $this->upline)->assertOk();
        $this->assertSame('image/png', $file->json('mime'));
        $this->api('POST', "/api/member/masuk/{$nomor}/bayar/{$idBukti}/terima", $this->upline)->assertOk()->assertJsonPath('data.status', 'disiapkan');
        $this->api('POST', "/api/member/masuk/{$nomor}/keluarkan", $this->upline)->assertOk()->assertJsonPath('data.status', 'siap_diambil');
        $this->api('POST', "/api/member/masuk/{$nomor}/sudah-diambil", $this->upline)->assertOk()->assertJsonPath('data.status', 'diterima');
        $this->api('POST', "/api/member/masuk/{$nomor}/hapus", $this->upline)->assertNotFound();

        $stok = $this->api('GET', '/api/member/stok', $this->upline)->assertOk();
        $this->assertSame(2, $stok->json('data.0.qty'));
        $this->assertSame('jual', $stok->json('mutasi.0.jenis'));
        $this->assertSame(2, $this->api('GET', '/api/member/stok', $this->pembeli)->json('data.0.qty'));
    }

    // ===================== halaman admin ERP =====================

    public function test_admin_antar_member_stok_member_dan_aturan_sumber(): void
    {
        $this->bolehUpline();
        $this->isiStokUpline(3);
        $p = $this->keUpline(1);
        $this->actingAs($this->admin);
        $this->get(route('toko-online.pesanan.index', ['tab' => 'antar']))->assertOk()->assertSee($p->nomor)->assertSee('Antar member');
        $this->get(route('toko-online.pesanan.index'))->assertOk()->assertDontSee($p->nomor); // tab gudang tidak memuat pesanan upline
        $this->get(route('toko-online.pesanan.show', $p))->assertOk()->assertSee('Pesanan ke <strong>upline</strong>', false)->assertDontSee('Siap diambil</button>', false);
        $this->get(route('toko-online.stok-member.index'))->assertOk()->assertSee('Supplier Uji');
        $this->get(route('toko-online.stok-member.show', 980001))->assertOk()->assertSee('Beli (pesanan diterima)')->assertDontSee('Server Error');
        $this->post(route('toko-online.pengaturan.sumber'), ['cakupan' => 'jabatan', 'jabatan_id' => self::JAB_DIST])->assertSessionHas('error');
        $this->post(route('toko-online.pengaturan.sumber'), ['cakupan' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'boleh_upline' => '1'])->assertSessionHas('success');
        $this->assertSame(['gudang' => false, 'upline' => true], $this->svc->sumberBoleh($this->pembeli));
        $this->get(route('toko-online.pengaturan'))->assertOk()->assertSee('Sumber barang');

        $gudangLain = Cabang::create(['nama_cabang' => 'Gudang Lain UP ' . uniqid(), 'kode_cabang' => 'UL' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $ag = $this->buatUser('admin_gudang', $gudangLain->id);
        $this->actingAs($ag)->get(route('toko-online.pesanan.show', $p))->assertNotFound();
        $this->actingAs($ag)->get(route('toko-online.pesanan.index', ['tab' => 'antar']))->assertOk()->assertDontSee($p->nomor);
        $this->actingAs($this->buatUser('kasir'))->get(route('toko-online.stok-member.index'))->assertForbidden();
    }
}
