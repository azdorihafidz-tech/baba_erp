<?php

namespace App\Services;

use App\Http\Middleware\VerifikasiAplikasiMember;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ERP → aplikasi member (/api/erp/*, dijaga VerifikasiErp di aplikasi member).
 * Tanda tangan sama dengan arah sebaliknya (VerifikasiAplikasiMember::kanonik), kunci BABA_MEMBER_API_SECRET.
 */
class AplikasiMemberClient
{
    public function siap(): bool
    {
        return config('baba.member_app.url') !== '' && (string) config('baba.member_api.secret') !== '';
    }

    public function kirim(string $method, string $path, array $query = [], ?array $data = null): Response
    {
        $method = strtoupper($method);
        $url = config('baba.member_app.url') . '/' . ltrim($path, '/');
        $queryString = $query ? http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';
        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }
        $body = $data === null ? '' : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $waktu = (string) time();
        $nonce = Str::random(32);
        $path = '/' . ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $tanda = hash_hmac('sha256', VerifikasiAplikasiMember::kanonik($method, $path, $queryString, $waktu, $nonce, '', '', $body), (string) config('baba.member_api.secret'));

        $req = Http::timeout(config('baba.member_app.timeout', 8))->acceptJson()->withHeaders([
            'X-Baba-Waktu' => $waktu, 'X-Baba-Nonce' => $nonce, 'X-Baba-Member' => '', 'X-Baba-Profil' => '', 'X-Baba-Tanda' => $tanda,
        ]);
        if ($body !== '') {
            $req = $req->withBody($body, 'application/json');
        }

        return $req->send($method, $url);
    }

    /**
     * Daftar jabatan member [{id, nama, level}] — disimpan 10 menit. Melempar \RuntimeException bila gagal.
     *
     * @return array<int, array{id:int, nama:string, level:int}>
     */
    public function jabatan(): array
    {
        return Cache::remember('baba-member-jabatan', 600, function () {
            $res = $this->kirimAman('GET', '/api/erp/jabatan');

            return $res->json('data') ?? [];
        });
    }

    /** Cari akun member aktif (nama / No ID / BP-xxxxx). */
    public function cariAkun(string $q): array
    {
        return $this->kirimAman('GET', '/api/erp/akun', ['cari' => $q])->json('data') ?? [];
    }

    private function kirimAman(string $method, string $path, array $query = []): Response
    {
        if (! $this->siap()) {
            throw new \RuntimeException('Alamat aplikasi member (BABA_MEMBER_APP_URL) atau kunci sambungan belum diatur.');
        }
        try {
            $res = $this->kirim($method, $path, $query);
        } catch (\Throwable $e) {
            \Log::warning(__METHOD__ . ': ' . $e->getMessage());
            throw new \RuntimeException('Aplikasi member tidak bisa dihubungi.');
        }
        if (! $res->successful() || $res->json('ok') !== true) {
            throw new \RuntimeException('Aplikasi member menolak permintaan (kode ' . $res->status() . ').');
        }

        return $res;
    }
}
