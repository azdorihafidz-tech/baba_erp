<?php

namespace Tests\Feature\Baba;

use App\Exceptions\PesananException;
use App\Http\Middleware\VerifikasiAplikasiMember;
use App\Models\AturanTagihan;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\ProdukOnline;
use App\Models\ProdukOnlineHarga;
use App\Models\TokoOnlinePengaturan;
use App\Models\TokoOnlineRekening;
use App\Models\User;
use App\Services\PesananOnlineService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 2b — pesanan online member ke gudang: checkout, bayar transfer / tagihan, proses gudang, otomatis, API. */
class PesananOnlineTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private const KUNCI = 'kunci-uji-pesanan-online-0123456789abcdef';
    private const JAB_DIST = 21;
    private const JAB_RESELLER = 24;

    private Cabang $gudang;
    private Item $botol;
    private ProdukOnline $produk;
    private User $admin;
    private PesananOnlineService $svc;
    private MemberAkun $dist;
    private MemberAkun $reseller;

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => self::KUNCI, 'baba.member_app.url' => 'http://member.uji']);
        Cache::flush();
        Storage::fake('local');
        Http::fake(['member.uji/*' => Http::response(['ok' => true])]);
        TokoOnlinePengaturan::ambil()->update(['status_toko' => 'buka', 'hari_terima_otomatis' => 7, 'jam_batas_bayar' => 48]);
        AturanTagihan::query()->update(['aktif' => false]); // aturan contoh di DB tes tidak ikut mempengaruhi

        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang PO Uji ' . uniqid(), 'kode_cabang' => 'PO' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true, 'alamat' => 'Jl. Uji No. 1']);
        TokoOnlineRekening::create(['cabang_id' => $this->gudang->id, 'bank' => 'BCA', 'nomor' => '1234567890', 'atas_nama' => 'BABA Uji', 'aktif' => true]);
        $this->botol = Item::create(['kode_item' => 'POU-' . strtoupper(Str::random(6)), 'nama_item' => 'Ocean Pesan EDP 50 ml', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $this->gudang->id]);
        $this->produk = ProdukOnline::create(['item_id' => $this->botol->id, 'nama_tampil' => 'Ocean Pesan EDP 50 ml', 'aktif' => true]);
        foreach ([['jenis' => 'tetap', 'harga' => 150000], ['jenis' => 'gudang', 'harga' => 135000], ['jenis' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'harga' => 110000]] as $h) {
            ProdukOnlineHarga::create(['produk_online_id' => $this->produk->id] + $h);
        }
        $this->admin = $this->buatUser('owner');
        $this->actingAs($this->admin);
        app(StokService::class)->masuk($this->botol->id, $this->gudang->id, 10, 'uji', 'uji', null, 27000);
        auth()->logout();

        $this->svc = app(PesananOnlineService::class);
        $this->dist = MemberAkun::updateOrCreate(['member_id' => 990001], ['nama' => 'Dist Uji', 'no_id' => 'B99-01-01', 'jabatan_id' => self::JAB_DIST, 'jabatan' => 'DISTRIBUTOR', 'aktif' => true]);
        $this->reseller = MemberAkun::updateOrCreate(['member_id' => 990002], ['nama' => 'Reseller Uji', 'jabatan_id' => self::JAB_RESELLER, 'jabatan' => 'RESELLER', 'aktif' => true]);
    }

    private function pesan(MemberAkun $m, int $qty = 2, array $lain = []): PesananOnline
    {
        return $this->svc->buat($m, $lain + ['cara_ambil' => 'ambil', 'cara_bayar' => 'transfer', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => $qty]]]);
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

    private function png(string $isi = 'a'): string
    {
        // PNG valid kecil; isi berbeda → hash berbeda
        $img = imagecreatetruecolor(4, 4);
        imagecolorallocate($img, ord($isi[0]), 10, 10);
        imagestring($img, 1, 0, 0, $isi, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);

        return ob_get_clean() . $isi; // byte tambahan di belakang tetap PNG, hash unik
    }

    // ===================== checkout =====================

    public function test_checkout_transfer_ambil_harga_jabatan_dan_stok_dipesan(): void
    {
        $p = $this->pesan($this->dist, 3);
        $this->assertSame('menunggu_bayar', $p->status);
        $this->assertEquals(330000, (float) $p->total);
        $this->assertEquals(0, (float) $p->ongkir);
        $this->assertSame('jabatan', $p->item->first()->dasar_harga);
        $this->assertNotNull($p->batas_bayar_at);
        $this->assertMatchesRegularExpression('/^ONL-\d{6}-\d{4}$/', $p->nomor);
        $this->assertEquals(7, $this->svc->tersedia($this->botol->id, $this->gudang->id)); // 10 − 3 dipesan
        // Reseller tanpa harga jabatan → harga gudang
        $this->assertEquals(135000, (float) $this->pesan($this->reseller, 1)->total);
        $this->assertEquals(10, app(StokService::class)->getStok($this->botol->id, $this->gudang->id)); // stok fisik belum berubah
    }

    public function test_checkout_ditolak_untuk_kondisi_salah(): void
    {
        $this->gagal(fn () => $this->pesan($this->dist, 11), 'tinggal 10');
        $this->pesan($this->dist, 8);
        $this->gagal(fn () => $this->pesan($this->reseller, 3), 'tinggal 2');
        $this->gagal(fn () => $this->pesan($this->dist, 0), 'angka bulat');
        $this->gagal(fn () => $this->pesan($this->dist, 1, ['item' => []]), 'kosong');
        $this->gagal(fn () => $this->pesan($this->dist, 1, ['item' => [['produk_online_id' => $this->produk->id, 'qty' => 1.5]]]), 'angka bulat');
        $this->gagal(fn () => $this->pesan($this->dist, 1, ['cara_ambil' => 'kirim', 'kirim_nama' => 'A', 'kirim_hp' => '08', 'kirim_alamat' => 'pendek']), 'alamat lengkap');
        $this->gagal(fn () => $this->pesan($this->dist, 1, ['cara_bayar' => 'kredit']), 'cara bayar');
        $superadmin = MemberAkun::updateOrCreate(['member_id' => 990009], ['nama' => 'SA', 'jabatan_id' => null, 'jabatan' => 'SUPERADMIN', 'aktif' => true]);
        $this->gagal(fn () => $this->pesan($superadmin, 1), 'superadmin');
        $nonaktif = MemberAkun::updateOrCreate(['member_id' => 990008], ['nama' => 'Off', 'jabatan_id' => self::JAB_DIST, 'aktif' => false]);
        $this->gagal(fn () => $this->pesan($nonaktif, 1), 'belum aktif');
        $this->produk->update(['aktif' => false]);
        $this->gagal(fn () => $this->pesan($this->dist, 1), 'tidak dijual');
        $this->produk->update(['aktif' => true]);
        TokoOnlineRekening::where('cabang_id', $this->gudang->id)->update(['aktif' => false]);
        $this->gagal(fn () => $this->pesan($this->dist, 1), 'rekening');
    }

    public function test_satu_pesanan_satu_gudang(): void
    {
        $g2 = Cabang::create(['nama_cabang' => 'Gudang Dua ' . uniqid(), 'kode_cabang' => 'PD' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $item2 = Item::create(['kode_item' => 'POD-' . strtoupper(Str::random(6)), 'nama_item' => 'Rose Dua', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $g2->id]);
        $p2 = ProdukOnline::create(['item_id' => $item2->id, 'nama_tampil' => 'Rose Dua', 'aktif' => true]);
        ProdukOnlineHarga::create(['produk_online_id' => $p2->id, 'jenis' => 'tetap', 'harga' => 100000]);
        $this->gagal(fn () => $this->pesan($this->dist, 1, ['item' => [['produk_online_id' => $this->produk->id, 'qty' => 1], ['produk_online_id' => $p2->id, 'qty' => 1]]]), 'satu gudang');
    }

    // ===================== tagihan =====================

    public function test_tagihan_aturan_dan_batas_nominal(): void
    {
        $this->gagal(fn () => $this->pesan($this->dist, 1, ['cara_bayar' => 'tagihan']), 'belum diizinkan');
        AturanTagihan::create(['cakupan' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'batas_nominal' => 300000, 'tempo_hari' => 10, 'aktif' => true]);
        $p = $this->pesan($this->dist, 2, ['cara_bayar' => 'tagihan']); // 220.000
        $this->assertSame('disiapkan', $p->status);
        $this->assertSame(now()->addDays(10)->toDateString(), $p->jatuh_tempo->toDateString());
        $this->assertEquals(80000, $this->svc->infoTagihan($this->dist)['sisa']);
        $this->gagal(fn () => $this->pesan($this->dist, 1, ['cara_bayar' => 'tagihan']), 'Sisa batas Anda Rp 80.000');
        // reseller tidak termasuk aturan jabatan Distributor
        $this->gagal(fn () => $this->pesan($this->reseller, 1, ['cara_bayar' => 'tagihan']), 'belum diizinkan');
        // aturan akun lebih diutamakan dari jabatan
        AturanTagihan::create(['cakupan' => 'akun', 'member_id' => $this->dist->member_id, 'batas_nominal' => 1000000, 'tempo_hari' => 30, 'aktif' => true]);
        $this->assertEquals(1000000, $this->svc->infoTagihan($this->dist)['batas']);
        $this->pesan($this->dist, 1, ['cara_bayar' => 'tagihan']);
        // lunasi tagihan pertama → terpakai berkurang
        $this->svc->keluarkan($p, $this->admin);
        $this->svc->unggahBukti($p->fresh(), $this->dist, $this->png('t1'));
        $this->assertSame('siap_diambil', $p->fresh()->status);
        $this->assertSame('menunggu_cek', $p->fresh()->status_bayar);
        $this->svc->terimaBukti($p->fresh()->bayar->first(), $this->admin);
        $this->assertSame('lunas', $p->fresh()->status_bayar);
        $this->assertEquals(110000, $this->svc->tagihanTerpakai($this->dist->member_id));
    }

    // ===================== alur lengkap dikirim + transfer =====================

    public function test_alur_kirim_ongkir_bukti_tolak_terima_kirim_diterima(): void
    {
        $p = $this->pesan($this->dist, 2, ['cara_ambil' => 'kirim', 'kirim_nama' => 'Budi', 'kirim_hp' => '0812', 'kirim_alamat' => 'Jl. Melati No. 5, Medan']);
        $this->assertSame('menunggu_ongkir', $p->status);
        $this->assertNull($p->ongkir);
        $this->gagal(fn () => $this->svc->unggahBukti($p, $this->dist, $this->png('x')), 'tidak sedang menunggu pembayaran');

        $p = $this->svc->aturOngkir($p, 25000, $this->admin);
        $this->assertSame('menunggu_bayar', $p->status);
        $this->assertEquals(245000, (float) $p->total);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/api/erp/notifikasi') && $r['user_id'] === 990001 && str_contains($r['judul'], 'Ongkir'));

        $this->gagal(fn () => $this->svc->unggahBukti($p, $this->dist, 'bukan gambar'), 'JPG/PNG/WebP');
        $this->gagal(fn () => $this->svc->unggahBukti($p, $this->dist, str_repeat('a', PesananOnlineService::MAKS_BUKTI + 1)), '4 MB');
        $this->gagal(fn () => $this->svc->unggahBukti($p, $this->reseller, $this->png('x')), 'tidak ditemukan');

        $b1 = $this->svc->unggahBukti($p, $this->dist, $this->png('b1'));
        $this->assertSame('menunggu_cek', $p->fresh()->status);
        Storage::disk('local')->assertExists($b1->file);
        $this->gagal(fn () => $this->svc->unggahBukti($p->fresh(), $this->dist, $this->png('b2')), 'masih dicek');
        $this->gagal(fn () => $this->svc->batalMember($p->fresh(), $this->dist, null), 'sedang dicek');

        $this->gagal(fn () => $this->svc->tolakBukti($b1, '', $this->admin), 'alasan');
        $this->svc->tolakBukti($b1, 'Nominal kurang', $this->admin);
        $this->assertSame('menunggu_bayar', $p->fresh()->status);
        $this->gagal(fn () => $this->svc->terimaBukti($b1->fresh(), $this->admin), 'sudah dicek');
        // bukti yang sama tidak bisa dipakai lagi
        $this->gagal(fn () => $this->svc->unggahBukti($p->fresh(), $this->dist, $this->png('b1')), 'sudah pernah diunggah untuk pesanan ' . $p->nomor);

        $b2 = $this->svc->unggahBukti($p->fresh(), $this->dist, $this->png('b2'));
        $this->svc->terimaBukti($b2, $this->admin);
        $p = $p->fresh();
        $this->assertSame(['disiapkan', 'lunas'], [$p->status, $p->status_bayar]);

        $this->gagal(fn () => $this->svc->keluarkan($p, $this->admin, ''), 'kurir');
        $this->svc->keluarkan($p, $this->admin, 'JNE', 'RESI123');
        $p = $p->fresh(['item']);
        $this->assertSame(['dikirim', true, 'JNE', 'RESI123'], [$p->status, $p->stok_keluar, $p->kurir, $p->nomor_resi]);
        $this->assertEquals(8, app(StokService::class)->getStok($this->botol->id, $this->gudang->id));
        $this->assertEquals(27000, (float) $p->item->first()->hpp);
        $this->assertEquals(8, $this->svc->tersedia($this->botol->id, $this->gudang->id));
        $this->gagal(fn () => $this->svc->keluarkan($p, $this->admin, 'JNE'), 'Disiapkan');
        $this->gagal(fn () => $this->svc->batalAdmin($p, 'coba', $this->admin), 'retur');

        $this->svc->terimaMember($p, $this->dist);
        $this->assertSame('diterima', $p->fresh()->status);
        $this->gagal(fn () => $this->svc->terimaMember($p->fresh(), $this->dist), 'sudah diterima');
        $this->assertGreaterThanOrEqual(7, $p->log()->count());
    }

    public function test_batal_member_dan_admin_melepas_stok_dipesan(): void
    {
        $p1 = $this->pesan($this->dist, 4);
        $this->svc->batalMember($p1, $this->dist, 'salah pilih');
        $this->assertSame('batal', $p1->fresh()->status);
        $this->assertEquals(10, $this->svc->tersedia($this->botol->id, $this->gudang->id));

        AturanTagihan::create(['cakupan' => 'semua', 'batas_nominal' => 5000000, 'tempo_hari' => 7, 'aktif' => true]);
        $p2 = $this->pesan($this->dist, 5, ['cara_bayar' => 'tagihan']);
        $this->gagal(fn () => $this->svc->batalMember($p2, $this->dist, null), 'sudah diproses');
        $this->gagal(fn () => $this->svc->batalAdmin($p2, '', $this->admin), 'alasan');
        $this->svc->batalAdmin($p2, 'Stok rusak', $this->admin);
        $this->assertSame('Stok rusak', $p2->fresh()->batal_alasan);
        $this->assertEquals(10, $this->svc->tersedia($this->botol->id, $this->gudang->id));
        $this->assertEquals(0, $this->svc->tagihanTerpakai($this->dist->member_id)); // tagihan batal tidak dihitung
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/api/erp/notifikasi') && str_contains($r['judul'], 'dibatalkan'));
    }

    public function test_ambil_sendiri_siap_diambil_lalu_sudah_diambil_admin(): void
    {
        $p = $this->pesan($this->dist, 1);
        $this->svc->terimaBukti($this->svc->unggahBukti($p, $this->dist, $this->png('a1')), $this->admin);
        $this->svc->keluarkan($p->fresh(), $this->admin);
        $this->assertSame('siap_diambil', $p->fresh()->status);
        $this->svc->sudahDiambil($p->fresh(), $this->admin);
        $this->assertSame('diterima', $p->fresh()->status);
    }

    public function test_otomatis_diterima_dan_batal_lewat_batas_bayar(): void
    {
        $lama = $this->pesan($this->dist, 1);
        $this->svc->terimaBukti($this->svc->unggahBukti($lama, $this->dist, $this->png('o1')), $this->admin);
        $this->svc->keluarkan($lama->fresh(), $this->admin);
        $lama->fresh()->update(['dikirim_at' => now()->subDays(8)]);
        $baru = $this->pesan($this->dist, 1);
        $this->svc->terimaBukti($this->svc->unggahBukti($baru, $this->dist, $this->png('o2')), $this->admin);
        $this->svc->keluarkan($baru->fresh(), $this->admin);
        $telat = $this->pesan($this->reseller, 2);
        $telat->update(['batas_bayar_at' => now()->subHour()]);
        $belum = $this->pesan($this->reseller, 1);

        $this->assertSame(['diterima' => 1, 'batal' => 1], $this->svc->jalankanOtomatis());
        $this->assertTrue($lama->fresh()->diterima_otomatis);
        $this->assertSame('siap_diambil', $baru->fresh()->status);
        $this->assertSame('batal', $telat->fresh()->status);
        $this->assertSame('menunggu_bayar', $belum->fresh()->status);
        $this->assertSame(['diterima' => 0, 'batal' => 0], $this->svc->jalankanOtomatis()); // idempotent
        $this->artisan('pesanan-online:otomatis')->assertSuccessful();
    }

    // ===================== API untuk aplikasi member =====================

    private function api(string $method, string $path, MemberAkun $m, ?array $data = null)
    {
        [$pathSaja, $query] = array_pad(explode('?', $path, 2), 2, '');
        $profil = base64_encode(json_encode(['id' => $m->member_id, 'nama' => $m->nama, 'no_id' => $m->no_id, 'jabatan_id' => $m->jabatan_id, 'jabatan' => $m->jabatan, 'aktif' => $m->aktif]));
        $body = $data === null ? '' : json_encode($data);
        $waktu = (string) time();
        $nonce = Str::random(32);
        $tanda = hash_hmac('sha256', VerifikasiAplikasiMember::kanonik($method, $pathSaja, $query, $waktu, $nonce, (string) $m->member_id, $profil, $body), self::KUNCI);
        $h = ['HTTP_X-Baba-Waktu' => $waktu, 'HTTP_X-Baba-Nonce' => $nonce, 'HTTP_X-Baba-Member' => (string) $m->member_id, 'HTTP_X-Baba-Profil' => $profil,
            'HTTP_X-Baba-Tanda' => $tanda, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];

        return $this->call($method, $path, [], [], [], $h, $body === '' ? null : $body);
    }

    public function test_api_member_checkout_daftar_rincian_bukti_terima(): void
    {
        $opsi = $this->api('GET', '/api/member/checkout-opsi', $this->dist)->assertOk();
        $g = collect($opsi->json('gudang'))->firstWhere('id', $this->gudang->id);
        $this->assertSame('1234567890', $g['rekening'][0]['nomor']);
        $this->assertNull($opsi->json('tagihan'));

        $kat = $this->api('GET', '/api/member/katalog', $this->dist)->assertOk();
        $this->assertEquals(10, collect($kat->json('data'))->firstWhere('id', $this->produk->id)['stok']);

        $buat = $this->api('POST', '/api/member/pesanan', $this->dist, ['cara_ambil' => 'ambil', 'cara_bayar' => 'transfer', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => 2]]])->assertStatus(201);
        $nomor = $buat->json('data.nomor');
        $this->assertTrue($buat->json('data.boleh.unggah_bukti'));
        $this->assertSame('1234567890', $buat->json('data.rekening.0.nomor'));
        $this->assertEquals(8, collect($this->api('GET', '/api/member/katalog', $this->dist)->json('data'))->firstWhere('id', $this->produk->id)['stok']);

        $this->api('POST', '/api/member/pesanan', $this->dist, ['cara_ambil' => 'ambil', 'cara_bayar' => 'transfer', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => 99]]])
            ->assertStatus(422)->assertJson(['ok' => false]);

        $daftar = $this->api('GET', '/api/member/pesanan?kelompok=berjalan', $this->dist)->assertOk();
        $this->assertSame($nomor, $daftar->json('data.0.nomor'));
        $this->assertSame(1, $daftar->json('jumlah.perlu_bayar'));
        // pesanan orang lain tidak terlihat
        $this->api('GET', "/api/member/pesanan/{$nomor}", $this->reseller)->assertStatus(422)->assertJson(['pesan' => 'Pesanan tidak ditemukan.']);
        $this->assertCount(0, $this->api('GET', '/api/member/pesanan', $this->reseller)->json('data'));

        $this->api('POST', "/api/member/pesanan/{$nomor}/bukti", $this->dist, ['file' => 'data:image/png;base64,' . base64_encode($this->png('api'))])
            ->assertOk()->assertJsonPath('data.status', 'menunggu_cek');
        $this->api('POST', "/api/member/pesanan/{$nomor}/bukti", $this->dist, ['file' => '%%%'])->assertStatus(422);

        $p = PesananOnline::where('nomor', $nomor)->first();
        $this->svc->terimaBukti($p->bayar->first(), $this->admin);
        $this->svc->keluarkan($p->fresh(), $this->admin);
        $this->api('POST', "/api/member/pesanan/{$nomor}/terima", $this->dist)->assertOk()->assertJsonPath('data.status', 'diterima');
        $rinci = $this->api('GET', "/api/member/pesanan/{$nomor}", $this->dist)->assertOk();
        $this->assertSame('Anda', $rinci->json('data.riwayat.0.oleh'));
        $this->assertFalse($rinci->json('data.boleh.terima'));

        $b = $this->api('POST', '/api/member/pesanan', $this->dist, ['cara_ambil' => 'ambil', 'cara_bayar' => 'transfer', 'item' => [['produk_online_id' => $this->produk->id, 'qty' => 1]]])->json('data.nomor');
        $this->api('POST', "/api/member/pesanan/{$b}/batal", $this->dist, ['alasan' => 'ganti aroma'])->assertOk()->assertJsonPath('data.status', 'batal');
    }

    // ===================== halaman admin ERP =====================

    public function test_halaman_admin_izin_dan_aksi(): void
    {
        $p = $this->pesan($this->dist, 2, ['cara_ambil' => 'kirim', 'kirim_nama' => 'Budi', 'kirim_hp' => '0812', 'kirim_alamat' => 'Jl. Melati No. 5, Medan']);
        $this->actingAs($this->buatUser('kasir'))->get(route('toko-online.pesanan.index'))->assertForbidden();
        $this->actingAs($this->buatUser('kasir'))->get(route('toko-online.pengaturan'))->assertForbidden();
        // admin gudang lain (tidak memegang gudang ini) tidak bisa membuka pesanan
        $lain = Cabang::create(['nama_cabang' => 'Gudang Lain ' . uniqid(), 'kode_cabang' => 'PL' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->actingAs($this->buatUser('admin_gudang', $lain->id))->get(route('toko-online.pesanan.show', $p))->assertNotFound();

        $this->actingAs($this->admin);
        $this->get(route('toko-online.pesanan.index'))->assertOk()->assertSee($p->nomor)->assertSee('Perlu tindakan');
        $this->get(route('toko-online.pesanan.show', $p))->assertOk()->assertSee('Isi ongkir');
        $this->post(route('toko-online.pesanan.ongkir', $p), ['ongkir' => '20.000'])->assertRedirect();
        $this->assertEquals(240000, (float) $p->fresh()->total);
        $b = $this->svc->unggahBukti($p->fresh(), $this->dist, $this->png('w1'));
        $this->get(route('toko-online.pesanan.show', $p))->assertOk()->assertSee('Cek bukti transfer');
        $this->get(route('toko-online.pesanan.bukti.file', [$p, $b]))->assertOk();
        $this->post(route('toko-online.pesanan.bukti.terima', [$p, $b]))->assertRedirect();
        $this->post(route('toko-online.pesanan.keluarkan', $p), ['kurir' => ''])->assertSessionHas('error');
        $this->post(route('toko-online.pesanan.keluarkan', $p), ['kurir' => 'J&T', 'nomor_resi' => 'JT1'])->assertSessionHas('success');
        $this->assertSame('dikirim', $p->fresh()->status);
        $this->get(route('toko-online.pesanan.index', ['tab' => 'jalan']))->assertOk()->assertSee($p->nomor);

        $this->get(route('toko-online.pengaturan'))->assertOk()->assertSee('Rekening tujuan transfer');
        $this->post(route('toko-online.pengaturan.rekening'), ['cabang_id' => $this->gudang->id, 'bank' => 'BRI', 'nomor' => 'abc', 'atas_nama' => 'X'])->assertSessionHasErrors('nomor');
        $this->post(route('toko-online.pengaturan.tagihan'), ['cakupan' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'batas_nominal' => 500000, 'tempo_hari' => 14])->assertRedirect();
        $this->post(route('toko-online.pengaturan.tagihan'), ['cakupan' => 'jabatan', 'jabatan_id' => self::JAB_DIST, 'batas_nominal' => 700000, 'tempo_hari' => 14])->assertRedirect();
        $this->assertSame(1, AturanTagihan::where('cakupan', 'jabatan')->where('jabatan_id', self::JAB_DIST)->where('aktif', true)->count());
        $this->assertEquals(700000, $this->svc->infoTagihan($this->dist)['batas']);
        $this->put(route('toko-online.pengaturan.umum'), ['hari_terima_otomatis' => 5, 'jam_batas_bayar' => 24, 'hari_batas_retur' => 7])->assertRedirect();
        $this->assertSame(5, TokoOnlinePengaturan::ambil()->hari_terima_otomatis);
    }
}
