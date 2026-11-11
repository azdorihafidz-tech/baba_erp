<?php

namespace App\Services;

use App\Enums\StatusOrder;
use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Sprint 4.32 (2026-10-05) — 5 metrik analisis penjualan utk 3 lokasi:
 * Dashboard Pusat, Dashboard Cabang, Laporan Penjualan.
 *
 * 1 query agregasi per DATE (SUM omzet + COUNT trx), seluruh metrik
 * dihitung di memory dari hasil query itu — tidak ada N+1, tidak ada
 * subquery per metrik. Cache 5 menit (per cabang+periode), tanpa
 * auto-invalidate (trade-off disetujui Owner: trade freshness vs
 * kompleksitas invalidation hook di order create/edit/batal).
 */
class AnalisisPenjualanService
{
    public const CACHE_TTL = 300; // 5 menit

    /** @return array{rata_rata_nilai_transaksi:float, rata_rata_jumlah_transaksi:float, hari_nilai_tertinggi:array, hari_jumlah_tertinggi:array, rata_rata_omzet_harian:float, total_omzet:float, total_transaksi:int, hari_aktif:int} */
    public function hitung(?int $cabangId, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $key = sprintf(
            'analisis_penjualan.%s.%s.%s',
            $cabangId ?? 'all',
            $dari->toDateString(),
            $sampai->toDateString(),
        );

        return Cache::remember($key, self::CACHE_TTL, function () use ($cabangId, $dari, $sampai) {
            $query = Order::withoutGlobalScopes()
                ->selectRaw('DATE(tanggal_order) as tanggal, SUM(total_bayar) as omzet_harian, COUNT(*) as jumlah_harian')
                ->penjualanSah()
                ->whereBetween('tanggal_order', [$dari->toDateString(), $sampai->toDateString()]);

            if ($cabangId) {
                $query->where('cabang_id', $cabangId);
            }

            $rows = $query->groupBy('tanggal')->get();

            $totalOmzet = (float) $rows->sum('omzet_harian');
            $totalTransaksi = (int) $rows->sum('jumlah_harian');
            $hariAktif = $rows->count();

            $topOmzet = $rows->sortByDesc('omzet_harian')->first();
            $topJumlah = $rows->sortByDesc('jumlah_harian')->first();

            return [
                'rata_rata_nilai_transaksi'  => $totalTransaksi > 0 ? (float) ($totalOmzet / $totalTransaksi) : 0.0,
                'rata_rata_jumlah_transaksi' => $hariAktif > 0 ? (float) ($totalTransaksi / $hariAktif) : 0.0,
                'hari_nilai_tertinggi'       => [
                    'tanggal' => $topOmzet?->tanggal,
                    'nilai'   => (float) ($topOmzet?->omzet_harian ?? 0),
                ],
                'hari_jumlah_tertinggi'      => [
                    'tanggal' => $topJumlah?->tanggal,
                    'nilai'   => (int) ($topJumlah?->jumlah_harian ?? 0),
                ],
                'rata_rata_omzet_harian'     => $hariAktif > 0 ? (float) ($totalOmzet / $hariAktif) : 0.0,
                'total_omzet'                => $totalOmzet,
                'total_transaksi'            => $totalTransaksi,
                'hari_aktif'                 => $hariAktif,
            ];
        });
    }

    /**
     * Resolve preset periode ke rentang tanggal.
     * Dipakai bareng dropdown filter di component + Laporan Penjualan.
     *
     * @return array{dari:Carbon, sampai:Carbon, label:string}
     */
    public static function presetPeriode(string $preset): array
    {
        $now = Carbon::now();
        return match ($preset) {
            'hari_ini'       => ['dari' => $now->copy()->startOfDay(), 'sampai' => $now->copy()->endOfDay(), 'label' => 'Hari Ini'],
            '7_hari'         => ['dari' => $now->copy()->subDays(6)->startOfDay(), 'sampai' => $now->copy()->endOfDay(), 'label' => '7 Hari Terakhir'],
            '30_hari'        => ['dari' => $now->copy()->subDays(29)->startOfDay(), 'sampai' => $now->copy()->endOfDay(), 'label' => '30 Hari Terakhir'],
            'bulan_ini'      => ['dari' => $now->copy()->startOfMonth(), 'sampai' => $now->copy()->endOfDay(), 'label' => 'Bulan Ini'],
            'bulan_lalu'     => ['dari' => $now->copy()->subMonthNoOverflow()->startOfMonth(), 'sampai' => $now->copy()->subMonthNoOverflow()->endOfMonth(), 'label' => 'Bulan Lalu'],
            'tahun_ini'      => ['dari' => $now->copy()->startOfYear(), 'sampai' => $now->copy()->endOfDay(), 'label' => 'Tahun Ini'],
            default          => ['dari' => $now->copy()->startOfMonth(), 'sampai' => $now->copy()->endOfDay(), 'label' => 'Bulan Ini'],
        };
    }

    /** Daftar preset utk render dropdown. */
    public static function daftarPreset(): array
    {
        return [
            'hari_ini'   => 'Hari Ini',
            '7_hari'     => '7 Hari Terakhir',
            '30_hari'    => '30 Hari Terakhir',
            'bulan_ini' => 'Bulan Ini',
            'bulan_lalu' => 'Bulan Lalu',
            'tahun_ini'  => 'Tahun Ini',
        ];
    }
}
