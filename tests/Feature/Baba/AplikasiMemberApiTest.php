<?php

namespace Tests\Feature\Baba;

use App\Http\Middleware\VerifikasiAplikasiMember;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Tahap 0 bagian 3 — jalur API antar-server aplikasi member → ERP (/api/member/*). */
class AplikasiMemberApiTest extends TestCase
{
    private const KUNCI = 'kunci-rahasia-uji-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => self::KUNCI, 'baba.member_api.toleransi_detik' => 300]);
        Cache::flush();
    }

    /** Header bertanda tangan seperti yang dikirim aplikasi member. (Pakai get(), bukan getJson(): getJson mengirim body "[]" pada GET.) */
    private function header(string $method, string $path, string $query = '', string $member = '', ?int $waktu = null, ?string $nonce = null, string $body = '', string $kunci = self::KUNCI): array
    {
        $waktu = (string) ($waktu ?? time());
        $nonce ??= Str::random(32);
        $tanda = hash_hmac('sha256', VerifikasiAplikasiMember::kanonik($method, $path, $query, $waktu, $nonce, $member, $body), $kunci);

        return ['X-Baba-Waktu' => $waktu, 'X-Baba-Nonce' => $nonce, 'X-Baba-Member' => $member, 'X-Baba-Tanda' => $tanda, 'Accept' => 'application/json'];
    }

    public function test_tanda_benar_diterima_dan_member_dikenali(): void
    {
        $this->get('/api/member/sehat', $this->header('GET', '/api/member/sehat', '', '284'))
            ->assertOk()
            ->assertJson(['ok' => true, 'erp' => 'BABA PARFUME', 'member_id' => 284]);
    }

    public function test_query_string_ikut_ditandatangani(): void
    {
        $this->get('/api/member/sehat?a=1&b=dua', $this->header('GET', '/api/member/sehat', 'a=1&b=dua'))->assertOk();
        // query diubah setelah ditandatangani → ditolak
        $this->get('/api/member/sehat?a=2&b=dua', $this->header('GET', '/api/member/sehat', 'a=1&b=dua'))->assertStatus(401);
    }

    public function test_tanpa_header_atau_tanda_salah_ditolak(): void
    {
        $this->get('/api/member/sehat')->assertStatus(401)->assertJson(['ok' => false]);
        $this->get('/api/member/sehat', $this->header('GET', '/api/member/sehat', kunci: 'kunci-lain-yang-salah-000000000000'))->assertStatus(401);
    }

    public function test_member_diganti_setelah_ditandatangani_ditolak(): void
    {
        $h = $this->header('GET', '/api/member/sehat', '', '284');
        $h['X-Baba-Member'] = '6'; // menyamar sebagai akun lain
        $this->get('/api/member/sehat', $h)->assertStatus(401);
    }

    public function test_waktu_kedaluwarsa_ditolak(): void
    {
        $this->get('/api/member/sehat', $this->header('GET', '/api/member/sehat', waktu: time() - 301))->assertStatus(401);
        $this->get('/api/member/sehat', $this->header('GET', '/api/member/sehat', waktu: time() + 301))->assertStatus(401);
    }

    public function test_kirim_ulang_nonce_yang_sama_ditolak(): void
    {
        $h = $this->header('GET', '/api/member/sehat', nonce: 'nonce-sekali-pakai-1234');
        $this->get('/api/member/sehat', $h)->assertOk();
        $this->get('/api/member/sehat', $h)->assertStatus(401);
    }

    public function test_kunci_belum_diatur_503(): void
    {
        config(['baba.member_api.secret' => null]);
        $this->get('/api/member/sehat', $this->header('GET', '/api/member/sehat'))->assertStatus(503);
    }
}
