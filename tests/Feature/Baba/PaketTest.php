<?php

namespace Tests\Feature\Baba;

use App\Http\Middleware\VerifikasiAplikasiMember;
use App\Models\Cabang;
use App\Models\JadwalOtomatis;
use App\Models\MemberAkun;
use App\Models\Pelanggan;
use App\Models\TokoOnlinePengaturan;
use App\Services\JadwalOtomatisService;
use App\Support\Paket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** Porting Dimsum 4.53 Tahap B ke BABA — paket modul, masa langganan, segel, Jadwal Otomatis & API aplikasi member. */
class PaketTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private const KUNCI = 'kunci-uji-paket-0123456789abcdef0123';

    protected function setUp(): void
    {
        parent::setUp();
        Paket::simpan(array_keys(Paket::MODUL), null, 7, null);
    }

    private function semuaKecuali(string ...$mati): array
    {
        return array_values(array_diff(array_keys(Paket::MODUL), $mati));
    }

    private function berakhir(Carbon $tanggal, int $tenggang = 7): void
    {
        Paket::simpan(array_keys(Paket::MODUL), $tanggal->toDateString(), $tenggang, null);
    }

    private function api(string $method, string $path, int $member, ?array $data = null)
    {
        $profil = base64_encode(json_encode(['id' => $member, 'nama' => 'M' . $member, 'jabatan_id' => 21, 'aktif' => true]));
        $body = $data === null ? '' : json_encode($data);
        $waktu = (string) time();
        $nonce = Str::random(32);
        $tanda = hash_hmac('sha256', VerifikasiAplikasiMember::kanonik($method, $path, '', $waktu, $nonce, (string) $member, $profil, $body), self::KUNCI);

        return $this->call($method, $path, [], [], [], ['HTTP_X-Baba-Waktu' => $waktu, 'HTTP_X-Baba-Nonce' => $nonce, 'HTTP_X-Baba-Member' => (string) $member,
            'HTTP_X-Baba-Profil' => $profil, 'HTTP_X-Baba-Tanda' => $tanda, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], $body === '' ? null : $body);
    }

    public function test_bawaan_lengkap_tanpa_batas_dan_peta_modul(): void
    {
        $this->assertSame('Lengkap', Paket::namaPaket());
        $this->assertTrue(Paket::data()['segel_valid']);
        $this->assertSame('tanpa_batas', Paket::statusLangganan()['kode']);
        $this->assertSame('toko_online', Paket::modulUntukIzin('toko_online.pesanan'));
        $this->assertSame('toko_online', Paket::modulUntukRoute('toko-online.pesanan.index'));
        $this->assertSame('produksi', Paket::modulUntukRoute('produksi.resep.index'));
        $this->assertSame('stok', Paket::modulUntukRoute('produk-toko.index'));
        $this->assertNull(Paket::modulUntukRoute('pengaturan.jadwal'), 'Jadwal Otomatis = inti');
        $this->assertNull(Paket::modulUntukRoute('keuangan.index'), 'Kas & Transaksi = inti');
        $this->assertNull(Paket::modulUntukRoute('tim-it.index'));
        // semua izin di database terpetakan ke modul yang ada (atau inti)
        foreach (DB::table('permissions')->pluck('name') as $izin) {
            $m = Paket::modulUntukIzin($izin);
            $this->assertTrue($m === null || isset(Paket::MODUL[$m]), $izin);
        }
    }

    public function test_modul_mati_hilang_untuk_semua_termasuk_owner_dan_kembali_saat_dinyalakan(): void
    {
        $owner = $this->buatUser('owner');
        $admin = $this->buatUser('admin_pusat');
        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertSee(route('stok-opname.index'), false);

        Paket::simpan($this->semuaKecuali('stok_opname'), null, 7, null);
        $this->assertFalse($owner->hasPermission('stok_opname.lihat'));
        $this->assertFalse($owner->can('stok_opname.lihat'));
        $this->assertFalse($admin->hasPermission('stok_opname.setujui'));
        $this->assertNotContains('stok_opname.setujui', $owner->getPermissions());
        $this->assertTrue($owner->hasPermission('stok.view'), 'modul lain tetap');
        $this->assertTrue($owner->hasPermission('cabang.view'), 'inti tetap');

        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertDontSee(route('stok-opname.index'), false);
        $this->actingAs($owner)->get(route('stok-opname.index'))->assertForbidden()->assertSee('tidak termasuk paket');
        $this->actingAs($owner)->getJson(route('stok-opname.index'))->assertForbidden()->assertJson(['ok' => false]);
        $this->actingAs($this->buatUser('teknisi'))->get(route('stok-opname.index'))->assertForbidden();

        Paket::simpan(array_keys(Paket::MODUL), null, 7, null);
        $this->assertTrue($admin->hasPermission('stok_opname.setujui'), 'centangan lama kembali');
        $this->actingAs($owner)->get(route('stok-opname.index'))->assertOk();
    }

    public function test_role_hak_akses_menyembunyikan_dan_tidak_menghapus_centangan_modul_mati(): void
    {
        $owner = $this->buatUser('owner');
        $idOpname = DB::table('permissions')->where('name', 'stok_opname.lihat')->value('id');
        $idStok = DB::table('permissions')->where('name', 'stok.view')->value('id');
        $this->assertTrue(DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $idOpname)->exists());

        Paket::simpan($this->semuaKecuali('stok_opname'), null, 7, null);
        $this->actingAs($owner)->get(route('role.index'))->assertOk()->assertDontSee('stok_opname.lihat');
        $this->actingAs($owner)->post(route('role.update-permissions', 'kasir'), ['permission_ids' => [$idStok]])->assertSessionHas('success');
        $this->assertTrue(DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $idOpname)->exists(), 'centangan tersembunyi tetap');
    }

    public function test_ketergantungan_dan_nama_paket(): void
    {
        $hasil = Paket::simpan(['produksi'], null, 7, null);
        $this->assertEqualsCanonicalizing(['produksi', 'stok'], $hasil['baru']['modul']);
        $this->assertSame('Kustom', Paket::namaPaket());
        Paket::simpan(['penjualan'], null, 7, null);
        $this->assertSame('Dasar', Paket::namaPaket(), 'Kasir butuh Stok');
        Paket::simpan(['akuntansi'], null, 7, null);
        $this->assertTrue(Paket::aktif('keuangan'));
        Paket::simpan(['kiriman'], null, 7, null);
        $this->assertEqualsCanonicalizing(['kiriman', 'stok', 'penjualan'], Paket::data()['modul']);
        Paket::simpan(['toko_online'], null, 7, null);
        $this->assertEqualsCanonicalizing(['toko_online', 'stok'], Paket::data()['modul']);
    }

    public function test_halaman_paket_khusus_tim_it_dan_tercatat(): void
    {
        $t = $this->buatUser('teknisi');
        $this->actingAs($this->buatUser('owner'))->get(route('paket.index'))->assertForbidden();
        $this->actingAs($t)->get(route('paket.index'))->assertOk()->assertSee('Lengkap')->assertSee('Toko Online')->assertSee('Produksi Parfum');
        $this->actingAs($t)->post(route('paket.update'), ['modul' => ['penjualan', 'stok'], 'masa_tenggang' => 7])->assertSessionHas('success');
        Paket::lupakan();
        $this->assertSame('Dasar', Paket::namaPaket());
        $this->assertTrue(DB::table('activity_log')->where('log_name', 'TimIT')->where('description', 'like', 'Ubah paket%')->exists());
    }

    public function test_segel_rusak_mode_lunak(): void
    {
        DB::table('lisensi')->update(['modul' => json_encode(['stok', 'penjualan'])]); // diubah langsung di database
        Paket::lupakan();
        $this->assertFalse(Paket::data()['segel_valid']);
        $this->assertFalse(Paket::bacaSaja(), 'mode lunak: tidak dikunci');
        $this->assertFalse(Paket::aktif('toko_online'), 'isi yang ada tetap dipakai');

        $t = $this->buatUser('teknisi');
        $this->assertGreaterThanOrEqual(1, Paket::peringatkanSegelRusak());
        $this->assertSame(0, Paket::peringatkanSegelRusak(), 'sekali per hari');
        $this->assertTrue(DB::table('notifications')->where('notifiable_id', $t->id)->where('data', 'like', '%Segel lisensi%')->exists());
        $this->actingAs($t)->get(route('paket.index'))->assertOk()->assertSee('Segel lisensi tidak cocok');

        $this->actingAs($t)->post(route('paket.update'), ['modul' => array_keys(Paket::MODUL), 'masa_tenggang' => 7]);
        Paket::lupakan();
        $this->assertTrue(Paket::data()['segel_valid']);
    }

    public function test_jadwal_otomatis_dilewati_kecuali_kirim_ro(): void
    {
        $svc = app(JadwalOtomatisService::class);
        $svc->pastikan();
        Paket::simpan($this->semuaKecuali('toko_online', 'aset'), null, 7, null);
        $h = $svc->jalankan('pesanan_online', 'manual');
        $this->assertSame('dilewati', $h['status']);
        $this->assertStringContainsString('Toko Online', $h['pesan']);
        $this->assertSame('dilewati', JadwalOtomatis::where('kode', 'pesanan_online')->value('terakhir_status'));
        $this->assertSame('dilewati', $svc->jalankan('aset_depresiasi', 'manual')['status']);
        $this->assertSame('berhasil', $svc->jalankan('ro_kirim', 'manual')['status'], 'Kirim RO tetap jalan (P2)');

        Paket::simpan(array_keys(Paket::MODUL), today()->subDays(30)->toDateString(), 7, null); // baca-saja
        $this->assertSame('dilewati', $svc->jalankan('recurring', 'manual')['status']);
        $this->assertSame('berhasil', $svc->jalankan('ro_kirim', 'manual')['status']);

        $t = $this->buatUser('teknisi');
        $this->actingAs($t)->post(route('pengaturan.jadwal.jalankan', 'recurring'))->assertSessionHas('warning');
        $this->actingAs($t)->get(route('pengaturan.jadwal'))->assertOk()->assertSee('Dilewati')->assertSee('baca-saja');
    }

    public function test_status_langganan_bertahap(): void
    {
        $sampai = Carbon::parse('2027-01-31');
        $this->berakhir($sampai);
        $this->assertSame('aktif', Paket::statusLangganan(Carbon::parse('2027-01-01'))['kode']);
        $this->assertSame('segera', Paket::statusLangganan(Carbon::parse('2027-01-17'))['kode']);
        $this->assertSame(14, Paket::statusLangganan(Carbon::parse('2027-01-17'))['sisa_hari']);
        $this->assertSame('segera', Paket::statusLangganan(Carbon::parse('2027-01-31 23:00'))['kode']);
        $this->assertSame('tenggang', Paket::statusLangganan(Carbon::parse('2027-02-01'))['kode']);
        $this->assertSame('tenggang', Paket::statusLangganan(Carbon::parse('2027-02-07'))['kode']);
        $this->assertSame('baca_saja', Paket::statusLangganan(Carbon::parse('2027-02-08'))['kode']);
        $this->berakhir($sampai, 0);
        $this->assertSame('baca_saja', Paket::statusLangganan(Carbon::parse('2027-02-01'))['kode']);
    }

    public function test_pita_baca_saja_dan_tim_it_tidak_terkena(): void
    {
        $owner = $this->buatUser('owner');
        $kasir = $this->buatUser('kasir', Cabang::where('tipe', 'cabang')->orderBy('id')->value('id'));

        $this->berakhir(today()->addDays(10));
        $this->actingAs($owner)->get(route('panduan.index'))->assertSee('Langganan aplikasi berakhir');
        $this->actingAs($kasir)->get(route('panduan.index'))->assertDontSee('Langganan aplikasi berakhir');
        $this->berakhir(today()->subDays(2));
        $this->actingAs($kasir)->get(route('panduan.index'))->assertSee('Masa tenggang sampai');

        $this->berakhir(today()->subDays(30));
        $this->actingAs($kasir)->get(route('panduan.index'))->assertSee('Mode baca-saja');
        $nama = 'Pelanggan Baca Saja ' . uniqid();
        $this->actingAs($owner)->post(route('pelanggan.store'), ['nama_pelanggan' => $nama, 'kode_pelanggan' => 'PLG-T' . rand(100000, 999999), 'telepon' => '0812' . rand(1000000, 9999999)])
            ->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'baca-saja'));
        $this->assertFalse(Pelanggan::where('nama_pelanggan', $nama)->exists());
        $this->actingAs($owner)->postJson(route('pelanggan.store'), ['nama_pelanggan' => $nama])->assertStatus(423)->assertJson(['ok' => false]);
        $this->actingAs($owner)->get(route('pelanggan.index'))->assertOk();
        $this->actingAs($owner)->get(route('stok-opname.laporan'))->assertOk();
        $this->actingAs($owner)->post(route('notifikasi.read-all'))->assertSessionMissing('error');

        $t = $this->buatUser('teknisi');
        $nama2 = 'Pelanggan Teknisi ' . uniqid();
        $this->actingAs($t)->post(route('pelanggan.store'), ['nama_pelanggan' => $nama2, 'kode_pelanggan' => 'PLG-T' . rand(100000, 999999), 'telepon' => '0813' . rand(1000000, 9999999)]);
        $this->assertTrue(Pelanggan::where('nama_pelanggan', $nama2)->exists(), 'Tim IT tetap bisa menyimpan');
        $this->actingAs($t)->get(route('panduan.index'))->assertSee('Anda Tim IT: tidak terkena mode baca-saja');
    }

    public function test_api_aplikasi_member_ikut_paket(): void
    {
        config(['baba.member_api.secret' => self::KUNCI, 'baba.member_app.url' => 'http://member.uji']);
        Cache::flush();
        MemberAkun::updateOrCreate(['member_id' => 941001], ['nama' => 'Uji Paket', 'jabatan_id' => 21, 'aktif' => true]);
        TokoOnlinePengaturan::ambil()->update(['status_toko' => 'buka', 'akun_uji' => []]);

        $this->api('GET', '/api/member/status-toko', 941001)->assertOk()->assertJson(['buka' => true]);
        $this->api('GET', '/api/member/katalog', 941001)->assertOk();

        // modul Toko Online mati → seperti toko ditutup, melihat tetap boleh
        Paket::simpan($this->semuaKecuali('toko_online'), null, 7, null);
        $this->api('GET', '/api/member/status-toko', 941001)->assertOk()->assertJson(['buka' => false]);
        $this->api('GET', '/api/member/katalog', 941001)->assertStatus(403)->assertJson(['paket' => 'mati']);
        $this->api('POST', '/api/member/pesanan', 941001, ['item' => []])->assertStatus(403);
        $this->api('POST', '/api/member/stok/opname', 941001, ['item' => []])->assertStatus(403);
        $this->api('GET', '/api/member/pesanan', 941001)->assertOk();
        $this->api('GET', '/api/member/stok', 941001)->assertOk();
        $this->api('GET', '/api/member/sehat', 941001)->assertOk();

        // baca-saja → simpan ditolak 423, melihat boleh
        Paket::simpan(array_keys(Paket::MODUL), today()->subDays(30)->toDateString(), 7, null);
        $this->api('GET', '/api/member/status-toko', 941001)->assertJson(['buka' => false]);
        $this->api('POST', '/api/member/pesanan', 941001, ['item' => []])->assertStatus(423)->assertJson(['paket' => 'baca_saja']);
        $this->api('POST', '/api/member/kasir', 941001, ['item' => []])->assertStatus(423);
        $this->api('GET', '/api/member/kasir', 941001)->assertOk();
        $this->api('GET', '/api/member/laporan', 941001)->assertOk();
    }

    public function test_potong_gaji_butuh_modul_penggajian(): void
    {
        Paket::simpan($this->semuaKecuali('penggajian'), null, 7, null);
        $this->assertFalse(Paket::aktif('penggajian'));
        $this->assertTrue(Paket::aktif('stok_opname'), 'stok opname tetap aktif');
        $src = file_get_contents(app_path('Services/GantiRugiStokService.php'));
        $this->assertStringContainsString("Paket::aktif('penggajian')", $src);
    }

    /** Penjaga: setiap link sidebar milik modul yang dimatikan hilang dari menu Owner. */
    public function test_link_sidebar_modul_mati_tidak_tampil_untuk_owner(): void
    {
        $owner = $this->buatUser('owner', Cabang::where('tipe', 'cabang')->orderBy('id')->value('id'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        preg_match_all("/route\\('([a-z0-9\\.\\-_]+)'\\)/", substr($layout, strpos($layout, '<nav id="sidebar"')), $m);
        $route = array_values(array_unique($m[1]));
        $bocor = [];
        foreach (array_keys(Paket::MODUL) as $modul) {
            $mati = [$modul];
            do {
                $tambah = false;
                foreach (Paket::MODUL as $k => $def) {
                    if (! in_array($k, $mati, true) && array_intersect($def[2], $mati)) {
                        $mati[] = $k;
                        $tambah = true;
                    }
                }
            } while ($tambah);
            Paket::simpan(array_values(array_diff(array_keys(Paket::MODUL), $mati)), null, 7, null);
            $html = $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertOk()->getContent();
            $nav = substr($html, (int) strpos($html, '<nav id="sidebar"'));
            $nav = substr($nav, 0, (int) strpos($nav, '</nav>'));
            foreach ($route as $r) {
                if (! in_array(Paket::modulUntukRoute($r), $mati, true)) {
                    continue;
                }
                try {
                    $url = route($r);
                } catch (\Throwable) {
                    continue;
                }
                if (str_contains($nav, 'href="' . $url . '"')) {
                    $bocor[] = "{$modul}: {$r}";
                }
            }
        }
        Paket::simpan(array_keys(Paket::MODUL), null, 7, null);
        $this->assertSame([], $bocor, 'Link sidebar masih tampil walau modulnya mati');
    }
}
