<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Laporan Eksekutif — Halaman 8 (Rangkuman Final). Compose murni dari
 * BusinessOverviewService, BebanBreakdownService (via ringkasan yang sudah
 * dipanggil BusinessOverviewService), CrossCheckValidator, dan
 * EvidenceBasedFindingsService (Halaman 7) — nol kalkulasi akuntansi baru.
 * Yang genuinely baru di sini HANYA: (a) formula Health Score 5 dimensi
 * (heuristik rule-based, didisclose eksplisit BUKAN standar audit baku),
 * dan (b) logic sortir/filter untuk 3 Poin Utama + Pertanyaan Direksi
 * (murni menyusun ulang kesimpulan yang sudah ada, bukan analisis baru).
 */
class RangkumanFinalService
{
    public function __construct(
        private BusinessOverviewService $overviewService,
        private CrossCheckValidator $crossCheckValidator,
        private EvidenceBasedFindingsService $findingsService,
    ) {
    }

    public function getRangkumanFinal(Carbon $tanggal, ?int $cabangId = null): array
    {
        $overview = $this->overviewService->getCoverOverview($tanggal, $cabangId);
        $crossCheck = $this->crossCheckValidator->validateConsistency($tanggal, $cabangId);
        $findings = $this->findingsService->getFindings($tanggal, $cabangId);

        $healthScore = $this->hitungHealthScore($overview);
        $poinUtama = $this->tentukanPoinUtama($overview, $findings, $crossCheck);
        $pertanyaanDireksi = $this->generatePertanyaanDireksi($overview, $findings);

        return [
            'health_score' => $healthScore,
            'poin_utama' => $poinUtama,
            'pertanyaan_direksi' => $pertanyaanDireksi,
            'highlight' => [
                'laba_bersih_bulan_ini' => $overview['analytics']['laba_bersih_bulan_ini'],
                'bep_persentase_tercapai' => $overview['bep_vs_realisasi']['persentase_tercapai'],
                'balance_check' => $overview['neraca']['balance_check'],
                'posisi_kas' => $overview['kas_summary']['posisi_kas'],
                'cash_runway_bulan' => $overview['kas_summary']['cash_runway_bulan'],
                'semua_cross_check_match' => $crossCheck['semua_match'],
            ],
        ];
    }

    /**
     * Health Score 5 dimensi (0-100 tiap dimensi) — heuristik rule-based
     * sederhana berbasis threshold, BUKAN standar penilaian akuntansi baku.
     * Didisclose eksplisit di UI/panduan.
     */
    private function hitungHealthScore(array $overview): array
    {
        $analytics = $overview['analytics'];

        // Tahap 7 D'mentai (Bug 6, 2026-09-15) — margin_persen bisa null kalau
        // pendapatan_bulan_ini = 0 (mis. cabang/outlet baru, belum ada
        // penjualan bulan ini). skalakan() butuh float non-null, jadi WAJIB
        // null-check di sini, sama seperti pola rasio_lancar & rasio
        // kewajiban/aset di bawah -- crash 500 nyata sebelum fix ini
        // (dikonfirmasi reproduksi langsung, bukan dugaan).
        $skorProfitabilitas = $analytics['margin_persen'] === null
            ? 50.0
            : $this->skalakan($analytics['margin_persen'], [20 => 100, 10 => 75, 0 => 50, -20 => 25], 0);
        $skorLikuiditas = $analytics['rasio_lancar'] === null
            ? 100.0
            : $this->skalakan($analytics['rasio_lancar'], [2 => 100, 1.5 => 85, 1 => 70, 0.5 => 40], 15);

        $bep = $overview['bep_vs_realisasi'];
        $skorBep = $bep['bisa_bep'] ? (float) min(100, $bep['persentase_tercapai'] ?? 0) : 0.0;

        $totalPendapatan = $analytics['pendapatan_bulan_ini'];
        $rasioBebanTotal = null;
        if ($totalPendapatan > 0) {
            // Estimasi rasio total beban (HPP+operasional+lain) vs pendapatan dari margin —
            // (1 - margin%) mendekati rasio beban terhadap pendapatan untuk kebutuhan skor kasar ini.
            $rasioBebanTotal = 100 - $analytics['margin_persen'];
        }
        $skorEfisiensi = $rasioBebanTotal === null
            ? 50.0
            : $this->skalakanTurun($rasioBebanTotal, [50 => 100, 70 => 75, 100 => 50, 150 => 25], 0);

        $neraca = $overview['neraca'];
        $skorSolvabilitas = $neraca['balance_check'] ? 90.0 : 60.0;
        if ($neraca['kewajiban']['total_kewajiban'] > 0 && $neraca['aset']['total_aset'] > 0) {
            $rasioKewajibanAset = $neraca['kewajiban']['total_kewajiban'] / $neraca['aset']['total_aset'];
            if ($rasioKewajibanAset > 0.5) {
                $skorSolvabilitas = max(20.0, $skorSolvabilitas - 30);
            }
        }

        $dimensi = [
            'profitabilitas' => round($skorProfitabilitas, 1),
            'likuiditas' => round($skorLikuiditas, 1),
            'pencapaian_bep' => round($skorBep, 1),
            'efisiensi_beban' => round($skorEfisiensi, 1),
            'solvabilitas' => round($skorSolvabilitas, 1),
        ];

        $rataRata = round(array_sum($dimensi) / count($dimensi), 1);
        $label = match (true) {
            $rataRata >= 75 => 'Sehat',
            $rataRata >= 50 => 'Perlu Perhatian',
            default => 'Kritis',
        };

        return ['dimensi' => $dimensi, 'skor_rata_rata' => $rataRata, 'label' => $label];
    }

    /** Skala naik: makin tinggi nilai, makin tinggi skor. $tangga = [threshold => skor], urut descending key. */
    private function skalakan(float $nilai, array $tangga, float $default): float
    {
        krsort($tangga);
        foreach ($tangga as $threshold => $skor) {
            if ($nilai >= $threshold) {
                return $skor;
            }
        }
        return $default;
    }

    /** Skala turun: makin tinggi nilai, makin rendah skor (dipakai untuk rasio beban). */
    private function skalakanTurun(float $nilai, array $tangga, float $default): float
    {
        ksort($tangga);
        foreach ($tangga as $threshold => $skor) {
            if ($nilai <= $threshold) {
                return $skor;
            }
        }
        return $default;
    }

    /**
     * 3 Poin Utama — gabungkan kesimpulan dari BusinessOverviewService +
     * severity dari Findings + status CrossCheckValidator, sortir
     * KRITIS > PERHATIAN > POSITIF, ambil 3 teratas. Murni sortir/filter,
     * bukan analisis baru.
     */
    private function tentukanPoinUtama(array $overview, array $findings, array $crossCheck): array
    {
        $urutanSeverity = ['kritis' => 0, 'perhatian' => 1, 'mismatch' => 0, 'info' => 2, 'positif' => 3];
        $mapKategoriKeSeverity = ['perhatian' => 'perhatian', 'positif' => 'positif', 'kunci' => 'positif'];

        $semuaPoin = [];

        foreach ($overview['kesimpulan'] as $k) {
            $severity = $mapKategoriKeSeverity[$k['kategori']] ?? 'info';
            $semuaPoin[] = ['teks' => $k['teks'], 'severity' => $severity];
        }

        foreach ($findings as $f) {
            if (in_array($f['severity'], ['kritis', 'perhatian'], true)) {
                $semuaPoin[] = ['teks' => $f['judul'] . ': ' . $f['fakta'], 'severity' => $f['severity']];
            }
        }

        if (!$crossCheck['semua_match']) {
            $semuaPoin[] = ['teks' => 'Ditemukan MISMATCH pada verifikasi konsistensi data — lihat Halaman 6.', 'severity' => 'kritis'];
        }

        usort($semuaPoin, fn ($a, $b) => ($urutanSeverity[$a['severity']] ?? 9) <=> ($urutanSeverity[$b['severity']] ?? 9));

        return array_slice($semuaPoin, 0, 3);
    }

    /**
     * Pertanyaan untuk direksi — generate KONDISIONAL dari data (bukan
     * template statis): hanya muncul kalau kondisinya memang relevan.
     */
    private function generatePertanyaanDireksi(array $overview, array $findings): array
    {
        $pertanyaan = [];

        if ($overview['analytics']['laba_bersih_bulan_ini'] < 0) {
            $pertanyaan[] = 'Apakah perlu evaluasi ulang harga jual atau efisiensi biaya, mengingat laba bersih bulan ini masih negatif?';
        }

        $bep = $overview['bep_vs_realisasi'];
        if ($bep['bisa_bep'] && ($bep['persentase_tercapai'] ?? 0) < 100) {
            $pertanyaan[] = 'Apakah target volume produksi bulan ini realistis, mengingat BEP baru tercapai ' . number_format($bep['persentase_tercapai'], 1, ',', '.') . '%?';
        }

        $hutangFinding = collect($findings)->firstWhere('judul', 'Hutang Jatuh Tempo');
        if ($hutangFinding && in_array($hutangFinding['severity'], ['kritis', 'perhatian'], true)) {
            $pertanyaan[] = 'Kapan rencana pembayaran PO yang sudah lama tertunda? (' . $hutangFinding['fakta'] . ')';
        }

        if ($overview['kas_summary']['cash_runway_bulan'] !== null && $overview['kas_summary']['cash_runway_bulan'] < 3) {
            $pertanyaan[] = 'Apakah perlu suntikan modal tambahan, mengingat estimasi kas hanya bertahan ' . number_format($overview['kas_summary']['cash_runway_bulan'], 1, ',', '.') . ' bulan pada tren beban saat ini?';
        }

        if (empty($pertanyaan)) {
            $pertanyaan[] = 'Kondisi keuangan periode ini relatif stabil — tidak ada isu mendesak yang butuh keputusan direksi segera.';
        }

        return $pertanyaan;
    }
}
