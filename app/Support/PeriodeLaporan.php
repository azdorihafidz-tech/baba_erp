<?php

namespace App\Support;

use App\Services\LaporanTransaksiKasirService as Lt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 4.46 — Pilihan periode untuk Laporan Penjualan Produk & Kategori (menu
 * Penjualan): ?periode=hari_ini|bulan_ini|tahun_ini|semua, atau ?dari=&sampai=
 * (rentang, dibalik otomatis kalau terbalik). Bawaan: hari ini.
 */
class PeriodeLaporan
{
    public const CEPAT = [
        'hari_ini'  => 'Hari ini',
        'bulan_ini' => 'Bulan ini',
        'tahun_ini' => 'Tahun ini',
        'semua'     => 'Selama ini',
    ];

    /**
     * @return array{dari:Carbon,sampai:Carbon,label:string,cepat:?string,params:array}
     */
    public static function baca(Request $request, ?int $cabangId): array
    {
        if ($request->filled('dari') && $request->filled('sampai')) {
            $a = self::tanggal($request->dari);
            $b = self::tanggal($request->sampai);
            [$dari, $sampai] = $a->lte($b) ? [$a, $b] : [$b, $a];

            return [
                'dari' => $dari, 'sampai' => $sampai, 'cepat' => null,
                'label' => Lt::labelRentang($dari, $sampai),
                'params' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            ];
        }

        $kunci = array_key_exists((string) $request->periode, self::CEPAT) ? $request->periode : 'hari_ini';
        $hari = today();
        [$dari, $sampai] = match ($kunci) {
            'bulan_ini' => [$hari->copy()->startOfMonth(), $hari->copy()],
            'tahun_ini' => [$hari->copy()->startOfYear(), $hari->copy()],
            'semua'     => [self::awalData($cabangId), $hari->copy()],
            default     => [$hari->copy(), $hari->copy()],
        };
        $label = match ($kunci) {
            'hari_ini'  => Lt::tanggal($hari),
            'bulan_ini' => Lt::BULAN[$hari->month] . ' ' . $hari->year,
            'tahun_ini' => 'Tahun ' . $hari->year,
            default     => 'Selama ini',
        };

        return ['dari' => $dari, 'sampai' => $sampai, 'cepat' => $kunci, 'label' => $label, 'params' => ['periode' => $kunci]];
    }

    /** Tanggal order sah pertama (untuk "Selama ini"); tidak ada data = hari ini. */
    private static function awalData(?int $cabangId): Carbon
    {
        $min = DB::table('orders')->whereNull('deleted_at')
            ->whereNotIn('status', \App\Enums\StatusOrder::bukanPenjualan())
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->min('tanggal_order');

        return $min ? Carbon::parse($min)->startOfDay() : today();
    }

    private static function tanggal($nilai): Carbon
    {
        try {
            $t = Carbon::createFromFormat('Y-m-d', (string) $nilai)->startOfDay();
        } catch (\Throwable) {
            abort(404);
        }
        abort_unless($t->year >= 2000 && $t->year <= 2100, 404);

        return $t;
    }
}
