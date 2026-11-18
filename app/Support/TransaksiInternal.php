<?php

namespace App\Support;

use App\Services\ClosingService;
use Illuminate\Support\Facades\DB;

/**
 * 4.51 — Transaksi kas yang BUKAN pemasukan/pengeluaran usaha:
 * mutasi internal (Transfer Antar Kas MUTASI-*, Setoran Kasir SETORKSR-*, Transfer Dana SETOR-*)
 * dan Saldo Awal kas (SALDO). Aturan sama dengan Closing (ClosingService::KODE_MUTASI).
 *
 * Dipakai laporan bergaya "laba rugi" berbasis kas (Laporan → Keuangan, Keuangan → Laporan
 * Keuangan, Cabang vs Cabang) supaya angka tidak membengkak oleh uang yang hanya berpindah tempat.
 */
class TransaksiInternal
{
    private static ?array $ids = null;

    /** @return string[] */
    public static function kode(): array
    {
        return array_merge(array_keys(ClosingService::KODE_MUTASI), ['SALDO']);
    }

    /** id kategori_transaksis yang termasuk (beserta sub-kategorinya). @return int[] */
    public static function kategoriIds(): array
    {
        if (self::$ids !== null) {
            return self::$ids;
        }
        $induk = DB::table('kategori_transaksis')->whereIn('kode', self::kode())->pluck('id')->all();
        $anak  = $induk ? DB::table('kategori_transaksis')->whereIn('parent_id', $induk)->pluck('id')->all() : [];

        return self::$ids = array_values(array_unique(array_merge($induk, $anak)));
    }

    /** Batasi query transaksi_keuangans ke transaksi USAHA saja (tanpa mutasi internal & saldo awal). */
    public static function kecualikan($query, string $tabel = 'transaksi_keuangans')
    {
        $ids = self::kategoriIds();

        return $query->where(function ($q) use ($ids, $tabel) {
            $q->whereNull("{$tabel}.kategori_id");
            if ($ids) {
                $q->orWhereNotIn("{$tabel}.kategori_id", $ids);
            } else {
                $q->orWhereNotNull("{$tabel}.kategori_id");
            }
        })->where(function ($q) use ($tabel) {
            // transaksi lama tanpa kategori_id: saldo awal dikenali dari kolom enum
            $q->whereNotNull("{$tabel}.kategori_id")->orWhere("{$tabel}.kategori", '!=', 'saldo_awal');
        });
    }

    /** Kebalikan kecualikan(): hanya mutasi internal & saldo awal. */
    public static function hanya($query, string $tabel = 'transaksi_keuangans')
    {
        $ids = self::kategoriIds();

        return $query->where(function ($q) use ($ids, $tabel) {
            if ($ids) {
                $q->whereIn("{$tabel}.kategori_id", $ids);
            }
            $q->orWhere(fn ($q2) => $q2->whereNull("{$tabel}.kategori_id")->where("{$tabel}.kategori", 'saldo_awal'));
        });
    }

    /** Untuk test: kosongkan cache id. */
    public static function reset(): void
    {
        self::$ids = null;
    }
}
