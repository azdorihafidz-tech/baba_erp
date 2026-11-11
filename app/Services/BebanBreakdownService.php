<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;

/**
 * Laporan Eksekutif — Breakdown Beban per kategori/akun COA. REUSE penuh
 * dari LabaRugiFormalService (agregat per akun) dan BukuBesarService (detail
 * transaksi per akun) — TIDAK ada resolusi kode akun baru ditulis di sini
 * (Rule bisnis #47). Murni READ.
 *
 * CATATAN PENTING soal breakdown per karyawan untuk akun Gaji (6-1101):
 * Tabel `penggajians` (payroll per karyawan) TIDAK terhubung otomatis ke
 * `transaksi_keuangans` kategori Gaji — bayar gaji dicatat manual oleh
 * Admin/Owner di Kas Keluar, terpisah dari proses hitung payroll. Karena
 * itu total per-karyawan dari `penggajians` BISA TIDAK SAMA dengan total
 * transaksi_keuangans akun 6-1101 yang dipakai di Laba Rugi Formal/BEP —
 * breakdown per karyawan di sini SENGAJA ditampilkan sebagai info
 * TAMBAHAN/informasional dengan disclaimer eksplisit, BUKAN diklaim sebagai
 * rincian resmi dari total akun 6-1101.
 */
class BebanBreakdownService
{
    private const KODE_AKUN_GAJI = '6-1101';

    /** Threshold alert rasio beban terhadap total pendapatan (heuristik sederhana, bukan baku industri). */
    private const THRESHOLD_KRITIS_PERSEN = 50.0;
    private const THRESHOLD_PERHATIAN_PERSEN = 30.0;

    public function __construct(
        private LabaRugiFormalService $labaRugiService,
        private BukuBesarService $bukuBesarService,
    ) {
    }

    /**
     * Ringkasan SEMUA akun beban (HPP + Beban Operasional + Beban Lain)
     * untuk 1 periode — dipakai sebagai daftar drill-down di Halaman 6.
     */
    public function getRingkasanSemuaKategori(Carbon $mulai, Carbon $akhir, ?int $cabangId = null): array
    {
        $labaRugi = $this->labaRugiService->hitungLabaRugi($mulai, $akhir, $cabangId);

        $totalPendapatan = $labaRugi['pendapatan']['total'] + $labaRugi['pendapatan_lain']['total'];
        $totalBeban = $labaRugi['hpp']['total'] + $labaRugi['beban_operasional']['total'] + $labaRugi['beban_lain']['total'];

        $semuaDetail = array_merge(
            $labaRugi['hpp']['detail'],
            $labaRugi['beban_operasional']['detail'],
            $labaRugi['beban_lain']['detail']
        );

        $kategori = collect($semuaDetail)
            ->filter(fn ($d) => $d['jumlah'] > 0)
            ->map(fn ($d) => $this->formatRingkasanAkun($d, $totalBeban, $totalPendapatan))
            ->sortByDesc('jumlah')
            ->values()
            ->toArray();

        return [
            'total_pendapatan' => $totalPendapatan,
            'total_beban' => $totalBeban,
            'kategori' => $kategori,
        ];
    }

    /**
     * Detail 1 akun beban — nominal, rasio, trend 6 bulan, breakdown
     * khusus (Gaji -> per karyawan), alert, dan REUSE BukuBesarService
     * untuk daftar transaksi mentahnya (link "Lihat Detail").
     */
    public function getBebanDetail(string $kodeAkun, Carbon $mulai, Carbon $akhir, ?int $cabangId = null): array
    {
        $labaRugi = $this->labaRugiService->hitungLabaRugi($mulai, $akhir, $cabangId);
        $totalPendapatan = $labaRugi['pendapatan']['total'] + $labaRugi['pendapatan_lain']['total'];
        $totalBeban = $labaRugi['hpp']['total'] + $labaRugi['beban_operasional']['total'] + $labaRugi['beban_lain']['total'];

        $akunInfo = $this->cariAkunDariLabaRugi($labaRugi, $kodeAkun);
        $jumlah = $akunInfo['jumlah'] ?? 0.0;
        $nama = $akunInfo['nama'] ?? $kodeAkun;

        $ringkasan = $this->formatRingkasanAkun(['kode' => $kodeAkun, 'nama' => $nama, 'jumlah' => $jumlah], $totalBeban, $totalPendapatan);

        // REUSE BukuBesarService — daftar transaksi mentah + running balance,
        // TIDAK ditulis ulang resolusi/agregasinya di sini.
        $ledger = $this->bukuBesarService->getTransaksiPerAkun($kodeAkun, $mulai, $akhir, $cabangId);

        return array_merge($ringkasan, [
            'trend_6_bulan' => $this->hitungTrend6Bulan($kodeAkun, $akhir, $cabangId),
            'breakdown_karyawan' => $kodeAkun === self::KODE_AKUN_GAJI
                ? $this->getBreakdownGajiPerKaryawan($mulai, $akhir, $cabangId)
                : null,
            'ledger' => $ledger,
        ]);
    }

    private function formatRingkasanAkun(array $d, float $totalBeban, float $totalPendapatan): array
    {
        $persenDariTotalBeban = $totalBeban > 0 ? round($d['jumlah'] / $totalBeban * 100, 2) : 0.0;
        $rasioVsPendapatan = $totalPendapatan > 0 ? round($d['jumlah'] / $totalPendapatan * 100, 2) : null;

        return [
            'kode_akun' => $d['kode'],
            'nama' => $d['nama'],
            'jumlah' => (float) $d['jumlah'],
            'persen_dari_total_beban' => $persenDariTotalBeban,
            'rasio_vs_pendapatan' => $rasioVsPendapatan,
            'alert' => $this->tentukanAlert($rasioVsPendapatan),
        ];
    }

    private function tentukanAlert(?float $rasioVsPendapatan): string
    {
        if ($rasioVsPendapatan === null) {
            return 'tidak_ada_data';
        }
        if ($rasioVsPendapatan >= self::THRESHOLD_KRITIS_PERSEN) {
            return 'kritis';
        }
        if ($rasioVsPendapatan >= self::THRESHOLD_PERHATIAN_PERSEN) {
            return 'perhatian';
        }
        return 'sehat';
    }

    private function cariAkunDariLabaRugi(array $labaRugi, string $kodeAkun): ?array
    {
        $semuaDetail = array_merge(
            $labaRugi['pendapatan']['detail'],
            $labaRugi['hpp']['detail'],
            $labaRugi['beban_operasional']['detail'],
            $labaRugi['pendapatan_lain']['detail'],
            $labaRugi['beban_lain']['detail']
        );

        foreach ($semuaDetail as $d) {
            if ($d['kode'] === $kodeAkun) {
                return $d;
            }
        }

        return null;
    }

    private function hitungTrend6Bulan(string $kodeAkun, Carbon $akhirAcuan, ?int $cabangId): array
    {
        $labels = [];
        $nilai = [];

        for ($i = 5; $i >= 0; $i--) {
            $bulan = $akhirAcuan->copy()->startOfMonth()->subMonths($i);
            $mulaiBulan = $bulan->copy()->startOfMonth();
            $akhirBulan = $bulan->copy()->endOfMonth();
            if ($akhirBulan->gt($akhirAcuan)) {
                $akhirBulan = $akhirAcuan->copy()->endOfDay();
            }

            $lr = $this->labaRugiService->hitungLabaRugi($mulaiBulan, $akhirBulan, $cabangId);
            $akun = $this->cariAkunDariLabaRugi($lr, $kodeAkun);

            $labels[] = $bulan->translatedFormat('M Y');
            $nilai[] = round($akun['jumlah'] ?? 0.0, 2);
        }

        return ['labels' => $labels, 'nilai' => $nilai];
    }

    /**
     * Breakdown INFORMASIONAL per karyawan untuk akun Gaji — dari tabel
     * `penggajians`, TIDAK terhubung ke transaksi_keuangans (lihat
     * disclaimer class-level). Return total_penggajian dipisah dari
     * total_transaksi_kas supaya UI wajib tampilkan keduanya + selisih.
     */
    private function getBreakdownGajiPerKaryawan(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $periodeList = [];
        $cursor = $mulai->copy()->startOfMonth();
        while ($cursor->lte($akhir)) {
            $periodeList[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $query = Penggajian::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->whereIn('periode', $periodeList)
            ->with('karyawan');
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $perKaryawan = $query->get()
            ->groupBy('karyawan_id')
            ->map(function ($rows) {
                $karyawan = $rows->first()->karyawan;
                return [
                    'nama_karyawan' => $karyawan?->nama_lengkap ?? '(karyawan tidak ditemukan)',
                    'total_gaji' => (float) $rows->sum('total_gaji'),
                ];
            })
            ->sortByDesc('total_gaji')
            ->values()
            ->toArray();

        $totalPenggajian = array_sum(array_column($perKaryawan, 'total_gaji'));

        $totalTransaksiKas = TransaksiKeuangan::query()
            ->join('kategori_transaksis', 'kategori_transaksis.id', '=', 'transaksi_keuangans.kategori_id')
            ->where('kategori_transaksis.kode_akun_coa', self::KODE_AKUN_GAJI)
            ->whereBetween('transaksi_keuangans.tanggal_transaksi', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('transaksi_keuangans.deleted_at')
            ->when($cabangId, fn ($q) => $q->where('transaksi_keuangans.cabang_id', $cabangId))
            ->sum('transaksi_keuangans.jumlah');

        return [
            'per_karyawan' => $perKaryawan,
            'total_penggajian' => $totalPenggajian,
            'total_transaksi_kas' => (float) $totalTransaksiKas,
            'selisih' => round($totalPenggajian - (float) $totalTransaksiKas, 2),
        ];
    }
}
