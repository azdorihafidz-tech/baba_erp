<?php

namespace App\Services;

use App\Models\MemberAkun;
use App\Models\ProdukOnline;

/**
 * ERP BABA Tahap 2a — harga produk online untuk satu member (rancangan §4.2).
 * Urutan: harga khusus akun → harga per jabatan → harga gudang (bila membeli langsung ke gudang) → harga tetap.
 * null = belum ada harga yang berlaku → produk tidak bisa dibeli member itu.
 */
class HargaOnlineService
{
    public const SUMBER_GUDANG = 'gudang';
    public const SUMBER_UPLINE = 'upline';

    public const LABEL = ['akun' => 'Harga khusus Anda', 'jabatan' => 'Harga jabatan', 'gudang' => 'Harga gudang', 'tetap' => 'Harga tetap'];

    /** @return array{harga: float, dasar: string}|null */
    public function untuk(ProdukOnline $produk, ?MemberAkun $member, string $sumber = self::SUMBER_GUDANG): ?array
    {
        $harga = $produk->relationLoaded('harga') ? $produk->harga : $produk->harga()->get();

        if ($member) {
            $akun = $harga->first(fn ($h) => $h->jenis === 'akun' && (int) $h->member_id === (int) $member->member_id);
            if ($akun) {
                return ['harga' => (float) $akun->harga, 'dasar' => 'akun'];
            }
            if ($member->jabatan_id) {
                $jab = $harga->first(fn ($h) => $h->jenis === 'jabatan' && (int) $h->jabatan_id === (int) $member->jabatan_id);
                if ($jab) {
                    return ['harga' => (float) $jab->harga, 'dasar' => 'jabatan'];
                }
            }
        }
        if ($sumber === self::SUMBER_GUDANG) {
            $gudang = $harga->first(fn ($h) => $h->jenis === 'gudang');
            if ($gudang) {
                return ['harga' => (float) $gudang->harga, 'dasar' => 'gudang'];
            }
        }
        $tetap = $harga->first(fn ($h) => $h->jenis === 'tetap');

        return $tetap ? ['harga' => (float) $tetap->harga, 'dasar' => 'tetap'] : null;
    }
}
