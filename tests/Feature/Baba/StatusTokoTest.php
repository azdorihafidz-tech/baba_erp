<?php

namespace Tests\Feature\Baba;

use App\Http\Middleware\VerifikasiAplikasiMember;
use App\Models\MemberAkun;
use App\Models\TokoOnlinePengaturan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 2f — saklar toko online (tutup / uji coba akun tertentu / buka) ditegakkan di server ERP. */
class StatusTokoTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private const KUNCI = 'kunci-uji-status-toko-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => self::KUNCI, 'baba.member_app.url' => 'http://member.uji']);
        Cache::flush();
        MemberAkun::updateOrCreate(['member_id' => 940001], ['nama' => 'Uji Coba', 'jabatan_id' => 21, 'aktif' => true]);
        MemberAkun::updateOrCreate(['member_id' => 940002], ['nama' => 'Bukan Uji', 'jabatan_id' => 21, 'aktif' => true]);
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

    public function test_saklar_tutup_uji_buka(): void
    {
        $set = TokoOnlinePengaturan::ambil();
        $set->update(['status_toko' => 'tutup', 'akun_uji' => []]);
        $this->api('GET', '/api/member/status-toko', 940001)->assertOk()->assertJson(['buka' => false, 'punya_riwayat' => false]);
        $this->api('GET', '/api/member/katalog', 940001)->assertStatus(403)->assertJson(['pesan' => 'Toko online belum dibuka.']);
        $this->api('GET', '/api/member/checkout-opsi', 940001)->assertStatus(403);
        $this->api('POST', '/api/member/pesanan', 940001, ['cara_ambil' => 'ambil', 'cara_bayar' => 'transfer', 'item' => []])->assertStatus(403);
        $this->api('POST', '/api/member/pesanan/ONL-202611-9999/retur', 940001, ['alasan' => 'x'])->assertStatus(403);
        // pesanan yang sudah berjalan tetap bisa dilihat / diproses
        $this->api('GET', '/api/member/pesanan', 940001)->assertOk();
        $this->api('GET', '/api/member/masuk', 940001)->assertOk();

        $set->update(['status_toko' => 'uji', 'akun_uji' => [940001]]);
        $this->api('GET', '/api/member/status-toko', 940001)->assertJson(['buka' => true]);
        $this->api('GET', '/api/member/katalog', 940001)->assertOk();
        $this->api('GET', '/api/member/status-toko', 940002)->assertJson(['buka' => false]);
        $this->api('GET', '/api/member/katalog', 940002)->assertStatus(403);

        $set->update(['status_toko' => 'buka']);
        $this->api('GET', '/api/member/katalog', 940002)->assertOk();
        $this->assertSame(['perlu_bayar' => 0, 'masuk_perlu' => 0, 'retur_diajukan' => 0], $this->api('GET', '/api/member/status-toko', 940002)->json('jumlah'));
    }

    public function test_halaman_pengaturan_status(): void
    {
        $this->actingAs($this->buatUser('owner'));
        $this->get(route('toko-online.pengaturan'))->assertOk()->assertSee('Status toko online untuk member');
        $this->put(route('toko-online.pengaturan.status'), ['status_toko' => 'uji'])->assertSessionHas('error');
        $this->put(route('toko-online.pengaturan.status'), ['status_toko' => 'uji', 'akun_uji' => [940001, 999999]])->assertSessionHas('success');
        $this->assertSame([940001], TokoOnlinePengaturan::ambil()->akun_uji); // akun yang belum dikenal ERP diabaikan
        $this->put(route('toko-online.pengaturan.status'), ['status_toko' => 'asal'])->assertSessionHasErrors('status_toko');
        $this->put(route('toko-online.pengaturan.status'), ['status_toko' => 'buka'])->assertSessionHas('success');
        $this->assertSame('buka', TokoOnlinePengaturan::ambil()->status_toko);
        $this->actingAs($this->buatUser('kasir'))->put(route('toko-online.pengaturan.status'), ['status_toko' => 'tutup'])->assertForbidden();
    }
}
