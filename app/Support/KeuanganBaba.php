<?php

namespace App\Support;

use App\Models\Cabang;

/**
 * ERP BABA Tahap 4 — batas keuangan BABA (rancangan §8).
 *
 * Toko Kemitraan & Toko Refill Member (cabangs.jenis_toko = kemitraan / toko_member) memakai kasir ERP,
 * tetapi uang & barangnya milik mitra / member: TIDAK masuk keuangan BABA (laba rugi, neraca, buku besar,
 * kas, closing, dashboard keuangan). Laporan gabungan (tanpa pilihan cabang) mengecualikan lokasi itu;
 * laporan yang memilih satu lokasi tetap menampilkan lokasi itu apa adanya (laporan milik toko itu sendiri).
 */
class KeuanganBaba
{
    /** @return array<int> id lokasi yang BUKAN milik BABA */
    public static function idBukanBaba(): array
    {
        return Cabang::withTrashed()->whereIn('jenis_toko', ['kemitraan', 'toko_member'])->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Saring query: satu lokasi dipilih → hanya lokasi itu; gabungan → tanpa toko kemitraan / refill member.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $q
     */
    public static function saring($q, string $kolom, ?int $cabangId)
    {
        if ($cabangId) {
            return $q->where($kolom, $cabangId);
        }
        $bukan = self::idBukanBaba();

        return $bukan ? $q->where(fn ($w) => $w->whereNotIn($kolom, $bukan)->orWhereNull($kolom)) : $q;
    }

    public static function milikBaba(?Cabang $c): bool
    {
        return $c !== null && ! in_array($c->jenis_toko, ['kemitraan', 'toko_member'], true);
    }
}
