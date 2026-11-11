<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Widget Dashboard Analytics ("Kesehatan Finansial") — MURNI komposisi dari
 * 3 service yang sudah ada (NeracaService, LabaRugiFormalService,
 * BepOtomatisService). Tidak ada kalkulasi akuntansi baru di file ini sama
 * sekali (Rule bisnis #47) — cuma menyusun ulang output ketiganya jadi 1
 * bundel untuk ditampilkan di dashboard.
 */
class DashboardAnalyticsService
{
    public function __construct(
        private NeracaService $neracaService,
        private LabaRugiFormalService $labaRugiService,
        private BepOtomatisService $bepOtomatisService,
    ) {
    }

    /**
     * @param Carbon|null $asOf Tanggal acuan "sekarang" — default Carbon::now()
     *        untuk widget dashboard (behavior lama, tidak berubah). Parameter
     *        ini ditambahkan (backward compatible) supaya Laporan Eksekutif
     *        (Sesi A) bisa reuse service ini untuk periode historis, bukan
     *        cuma bulan berjalan.
     */
    public function getSnapshot(?int $cabangId = null, ?Carbon $asOf = null): array
    {
        $now = $asOf ? $asOf->copy() : Carbon::now();
        $mulaiBulanIni = $now->copy()->startOfMonth();
        $akhirBulanIni = $now->copy()->endOfDay();

        // Card 1: Profitabilitas Bulan Ini
        $labaRugi = $this->labaRugiService->hitungLabaRugi($mulaiBulanIni, $akhirBulanIni, $cabangId);
        $pendapatanBulanIni = $labaRugi['pendapatan']['total'];
        $labaBersihBulanIni = $labaRugi['laba_bersih_setelah_pajak'];
        $marginPersen = $pendapatanBulanIni > 0 ? ($labaBersihBulanIni / $pendapatanBulanIni) * 100 : null;

        // Card 2: BEP Status Bulan Ini
        $bep = $this->bepOtomatisService->hitungBepOtomatis($mulaiBulanIni, $akhirBulanIni, $cabangId);

        // Card 3: Rasio Keuangan Sederhana
        $neraca = $this->neracaService->hitungNeraca($now, $cabangId);
        $asetLancar = $neraca['aset']['lancar']['total'];
        $kewajibanJangkaPendek = $neraca['kewajiban']['jangka_pendek']['total'];
        // null = tidak ada kewajiban jangka pendek sama sekali (rasio "tak terhingga", ditampilkan sebagai "Aman")
        $rasioLancar = $kewajibanJangkaPendek > 0 ? $asetLancar / $kewajibanJangkaPendek : null;
        $totalModal = $neraca['modal']['total_modal'];
        // ROI sederhana: laba bersih bulan ini / total modal — bukan ROI baku per-investasi,
        // murni indikator kasar "seberapa produktif modal saat ini menghasilkan laba bulan ini"
        $roiPersen = $totalModal > 0 ? ($labaBersihBulanIni / $totalModal) * 100 : null;

        // Card 4: Trend 6 Bulan Pendapatan vs Beban
        $trendLabels = [];
        $trendPendapatan = [];
        $trendBeban = [];
        for ($i = 5; $i >= 0; $i--) {
            // subMonths() WAJIB dari startOfMonth() dulu (bukan dari tanggal
            // hari ini) — hindari overflow kalau tanggal hari ini 29/30/31
            // (lihat Rule bisnis #30, bug nyata yang pernah ditemukan).
            $bulan = $now->copy()->startOfMonth()->subMonths($i);
            $mulaiBulan = $bulan->copy()->startOfMonth();
            $akhirBulan = $bulan->copy()->endOfMonth();
            if ($akhirBulan->gt($now)) {
                $akhirBulan = $now->copy()->endOfDay();
            }

            $lr = $this->labaRugiService->hitungLabaRugi($mulaiBulan, $akhirBulan, $cabangId);

            $trendLabels[] = $bulan->translatedFormat('M Y');
            $trendPendapatan[] = round($lr['pendapatan']['total'] + $lr['pendapatan_lain']['total'], 2);
            $trendBeban[] = round($lr['hpp']['total'] + $lr['beban_operasional']['total'] + $lr['beban_lain']['total'], 2);
        }

        return [
            'pendapatan_bulan_ini' => $pendapatanBulanIni,
            'laba_bersih_bulan_ini' => $labaBersihBulanIni,
            'margin_persen' => $marginPersen,
            'bep' => $bep,
            'rasio_lancar' => $rasioLancar,
            'roi_persen' => $roiPersen,
            'trend' => [
                'labels' => $trendLabels,
                'pendapatan' => $trendPendapatan,
                'beban' => $trendBeban,
            ],
        ];
    }
}
