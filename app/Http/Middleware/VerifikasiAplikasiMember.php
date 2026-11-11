<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya server aplikasi member yang boleh memanggil /api/member/*.
 *
 * Header wajib:
 *   X-Baba-Waktu  : unix time (detik) saat dikirim
 *   X-Baba-Nonce  : teks acak 16–64 karakter, sekali pakai
 *   X-Baba-Member : id akun member (tbl_user.id di aplikasi member) atas nama siapa permintaan dibuat, kosong = sistem
 *   X-Baba-Tanda  : hex HMAC-SHA256(secret, kanonik)
 * kanonik = METHOD \n PATH \n QUERY \n WAKTU \n NONCE \n MEMBER \n sha256(body)
 *   PATH  = path tanpa domain, diawali "/" (mis. /api/member/sehat)
 *   QUERY = query string apa adanya (tanpa "?"), kosong bila tidak ada
 *
 * Ditolak 401 bila tanda salah / waktu lewat toleransi / nonce dipakai ulang; 503 bila kunci belum diatur.
 * Pesan galat sengaja umum (tidak menyebut bagian mana yang salah).
 */
class VerifikasiAplikasiMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('baba.member_api.secret');
        if ($secret === '') {
            return response()->json(['ok' => false, 'pesan' => 'Sambungan aplikasi member belum diatur.'], 503);
        }

        $waktu = (string) $request->header('X-Baba-Waktu', '');
        $nonce = (string) $request->header('X-Baba-Nonce', '');
        $member = (string) $request->header('X-Baba-Member', '');
        $tanda = strtolower((string) $request->header('X-Baba-Tanda', ''));
        $toleransi = max(30, (int) config('baba.member_api.toleransi_detik', 300));

        $formatOk = ctype_digit($waktu)
            && preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce)
            && ($member === '' || ctype_digit($member))
            && preg_match('/^[0-9a-f]{64}$/', $tanda);
        if (! $formatOk || abs(time() - (int) $waktu) > $toleransi) {
            return $this->tolak();
        }

        $harap = hash_hmac('sha256', self::kanonik(
            $request->getMethod(),
            '/' . ltrim($request->getPathInfo(), '/'),
            (string) $request->server->get('QUERY_STRING', ''),
            $waktu,
            $nonce,
            $member,
            $request->getContent()
        ), $secret);

        if (! hash_equals($harap, $tanda)) {
            return $this->tolak();
        }

        // Nonce sekali pakai (diingat selama 2× toleransi). add() = false bila sudah ada.
        if (! Cache::add('baba-member-api-nonce:' . $nonce, 1, $toleransi * 2)) {
            return $this->tolak();
        }

        $request->attributes->set('member_id', $member === '' ? null : (int) $member);

        return $next($request);
    }

    public static function kanonik(string $method, string $path, string $query, string $waktu, string $nonce, string $member, string $body): string
    {
        return implode("\n", [strtoupper($method), $path, $query, $waktu, $nonce, $member, hash('sha256', $body)]);
    }

    private function tolak(): Response
    {
        return response()->json(['ok' => false, 'pesan' => 'Permintaan tidak sah.'], 401);
    }
}
