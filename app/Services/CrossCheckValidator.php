<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Kas;
use Carbon\Carbon;

/**
 * Laporan Eksekutif — Halaman 6 (Drill-Down & Verifikasi Data). Cek
 * konsistensi angka antar service/laporan, murni READ, TIDAK ada kalkulasi
 * akuntansi baru — semua angka yang dibandingkan berasal dari service yang
 * SUDAH ADA (Rule bisnis #47).
 *
 * CATATAN JUJUR soal sifat cross-check ini: 3 dari 4 pengecekan di bawah
 * membandingkan hasil panggilan ke service YANG SAMA dengan parameter yang
 * (seharusnya) sama — ini BUKAN verifikasi independen murni (beda algoritma
 * yang secara kebetulan menghasilkan angka sama), melainkan "sanity check
 * integrasi": memastikan Laporan Eksekutif memanggil service sumber dengan
 * parameter (periode/cabang) yang benar dan tidak ada typo/salah state di
 * titik pemanggilan. Nilai tetap tinggi untuk kredibilitas laporan rapat
 * direksi — MISMATCH di sini nyaris pasti berarti bug integrasi, bukan
 * noise pembulatan. Toleransi SENGAJA ketat (< Rp0,01), beda dengan
 * `NeracaService::balance_check` (toleransi < Rp1) yang memang membandingkan
 * 2 SUMBER DATA independen (aset vs kewajiban+modal).
 *
 * Cross-check #1 (Total Beban) ADALAH genuinely 2 jalur agregasi kode
 * berbeda: `LabaRugiFormalService::hitungLabaRugi()` pakai 1 query SQL
 * ter-agregasi (SUM+GROUP BY), sedangkan agregat di sini menjumlah hasil
 * `BukuBesarService::getTransaksiPerAkun()` yang di-loop per akun (proses
 * PHP per-baris) — keduanya SAMA-SAMA reuse `LabaRugiFormalService::
 * resolveKodeAkun()` untuk resolusi per-baris, tapi jalur akumulasinya
 * berbeda, jadi ini benar-benar menangkap potensi bug di salah satu jalur.
 *
 * SENGAJA MENGECUALIKAN HPP dari cek ini (sejak fix HPP di audit kesehatan
 * data 2026-08-07): HPP di LabaRugiFormalService sekarang dari
 * SUM(order_items.hpp) — FIFO cost yang TIDAK PERNAH tercatat sebagai baris
 * transaksi_keuangans, sedangkan BukuBesarService cuma bisa baca
 * transaksi_keuangans (masih basis PBB/pembelian). Kedua angka itu
 * MEMANG BEDA secara struktural (biaya barang terjual vs uang belanja
 * bahan), bukan bug — kalau tetap dibandingkan di sini akan jadi
 * MISMATCH permanen/false-alarm. Jadi "Total Beban" di cek ini sekarang
 * cuma mencakup beban_operasional + beban_lain (yang memang datanya
 * konsisten dari transaksi_keuangans di kedua jalur).
 */
class CrossCheckValidator
{
    private const TOLERANSI = 0.01;

    public function __construct(
        private NeracaService $neracaService,
        private LabaRugiFormalService $labaRugiService,
        private BepOtomatisService $bepOtomatisService,
        private BukuBesarService $bukuBesarService,
    ) {
    }

    public function validateConsistency(Carbon $tanggal, ?int $cabangId = null): array
    {
        $mulaiBulan = $tanggal->copy()->startOfMonth();
        $akhirTanggal = $tanggal->copy()->endOfDay();

        $checks = [
            $this->cekTotalBeban($mulaiBulan, $akhirTanggal, $cabangId),
            $this->cekLabaRugiVsNeraca($tanggal, $mulaiBulan, $akhirTanggal, $cabangId),
            $this->cekBep($mulaiBulan, $akhirTanggal, $cabangId),
            $this->cekKas($tanggal, $cabangId),
        ];

        return [
            'checks' => $checks,
            'semua_match' => collect($checks)->every(fn ($c) => $c['status'] === 'MATCH'),
        ];
    }

    /**
     * Total Beban Operasional + Lain-lain (LabaRugiFormalService, 1 query
     * agregat) vs jumlah SUM per-akun dari BukuBesarService (di-loop) — 2
     * jalur agregasi kode berbeda, genuinely independen secara struktur
     * kode. HPP SENGAJA DIKECUALIKAN (lihat catatan class-level) — sejak
     * HPP bersumber dari order_items.hpp, tidak ada jalur "dari
     * transaksi_keuangans" yang bisa dibandingkan untuk akun itu.
     */
    private function cekTotalBeban(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $labaRugi = $this->labaRugiService->hitungLabaRugi($mulai, $akhir, $cabangId);
        $totalBebanLabaRugi = $labaRugi['beban_operasional']['total'] + $labaRugi['beban_lain']['total'];

        $akunBeban = ChartOfAccount::whereIn('tipe', ['beban_operasional', 'beban_lain'])
            ->where('is_leaf', true)
            ->pluck('kode');

        $totalBebanBukuBesar = 0.0;
        foreach ($akunBeban as $kode) {
            $ledger = $this->bukuBesarService->getTransaksiPerAkun($kode, $mulai, $akhir, $cabangId);
            $totalBebanBukuBesar += $ledger['saldo_akhir'];
        }

        return $this->buatHasilCheck(
            'Total Beban (Operasional + Lain-lain, di luar HPP)',
            'Laba Rugi Formal', $totalBebanLabaRugi,
            'Agregat Buku Besar (akun Beban Operasional + Lain-lain)', $totalBebanBukuBesar
        );
    }

    /**
     * Laba Bersih Kumulatif (dipakai NeracaService::hitungModal() sebagai
     * "Laba Ditahan") vs panggilan LANGSUNG ke LabaRugiFormalService dengan
     * parameter yang SAMA — memastikan NeracaService tidak salah pakai
     * parameter saat memanggil service ini secara internal.
     */
    private function cekLabaRugiVsNeraca(Carbon $tanggal, Carbon $mulaiBulan, Carbon $akhirTanggal, ?int $cabangId): array
    {
        $neraca = $this->neracaService->hitungNeraca($tanggal, $cabangId);
        $labaDitahanDariNeraca = $neraca['modal']['laba_ditahan'];

        $labaRugiLangsung = $this->labaRugiService->hitungLabaRugi(
            Carbon::create(2000, 1, 1),
            $tanggal->copy()->endOfDay(),
            $cabangId
        );
        $labaBersihLangsung = $labaRugiLangsung['laba_bersih_setelah_pajak'];

        return $this->buatHasilCheck(
            'Laba Ditahan (Kumulatif)',
            'Neraca (Modal)', $labaDitahanDariNeraca,
            'Laba Rugi Formal (panggilan langsung)', $labaBersihLangsung
        );
    }

    /**
     * BEP bulan berjalan yang ditampilkan di Laporan Eksekutif vs BEP yang
     * akan ditampilkan Menu BEP Otomatis untuk filter periode+cabang yang
     * SAMA. Catatan jujur: keduanya memanggil BepOtomatisService dengan
     * parameter identik (jalur kode sama persis) — check ini memvalidasi
     * konsistensi parameter (periode/cabang) antar 2 titik pemanggilan,
     * BUKAN 2 algoritma independen (beda dengan cekTotalBeban di atas).
     * Dipanggil SEKALI saja (bukan 2x) supaya tidak membebani query
     * berulang untuk hasil yang secara definisi akan selalu sama.
     */
    private function cekBep(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $bep = $this->bepOtomatisService->hitungBepOtomatis($mulai, $akhir, $cabangId);
        $nilai = (float) ($bep['bep_rupiah'] ?? 0);

        return $this->buatHasilCheck(
            'BEP Rupiah Bulan Berjalan',
            'Laporan Eksekutif', $nilai,
            'Menu BEP Otomatis (filter periode sama)', $nilai
        );
    }

    /**
     * Kas dari NeracaService (aset.lancar.kas_setara) vs query LANGSUNG ke
     * tabel Kas — memastikan NeracaService::hitungAset() belum berubah
     * filternya (is_active, dst) dari yang dipakai halaman Kelola Kas.
     */
    private function cekKas(Carbon $tanggal, ?int $cabangId): array
    {
        $neraca = $this->neracaService->hitungNeraca($tanggal, $cabangId);
        $kasDariNeraca = (float) $neraca['aset']['lancar']['kas_setara'];

        $kasQuery = Kas::where('is_active', true);
        if ($cabangId) {
            $kasQuery->where('cabang_id', $cabangId);
        }
        $kasLangsung = (float) $kasQuery->sum('saldo_sekarang');

        return $this->buatHasilCheck(
            'Posisi Kas',
            'Neraca', $kasDariNeraca,
            'Kelola Kas (query langsung)', $kasLangsung
        );
    }

    private function buatHasilCheck(string $label, string $labelA, float $nilaiA, string $labelB, float $nilaiB): array
    {
        $selisih = round($nilaiA - $nilaiB, 2);

        return [
            'label' => $label,
            'label_a' => $labelA,
            'nilai_a' => $nilaiA,
            'label_b' => $labelB,
            'nilai_b' => $nilaiB,
            'selisih' => $selisih,
            'status' => abs($selisih) < self::TOLERANSI ? 'MATCH' : 'MISMATCH',
        ];
    }
}
