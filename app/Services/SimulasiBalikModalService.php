<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Laporan Eksekutif — Halaman 9 (Simulasi 5 Skenario Balik Modal). Compose
 * murni dari NeracaService (modal awal) dan BepOtomatisService (harga jual,
 * biaya variabel, biaya tetap, BEP unit — semua dari HPP FIFO real) — nol
 * kalkulasi harga/biaya baru. Yang genuinely baru: proyeksi omzet/laba per
 * skenario volume (aljabar dasar: omzet = volume x hari kerja x harga) dan
 * estimasi waktu balik modal / modal habis (Modal Awal / |laba bulanan|).
 *
 * CATATAN PENTING (disetujui Owner setelah audit): bisnis masih SANGAT BARU
 * (data produksi cuma ~1.5 minggu, belum menjalankan marketing aktif) —
 * angka volume "Sedang" & "Optimis" TIDAK BISA disimpulkan dari histori
 * (histori cuma cerminan fase pra-marketing, bukan kapasitas riil). Karena
 * itu kedua skenario ini pakai default PROPORSIONAL terhadap BEP (1x dan 2x
 * BEP unit harian) yang BISA DI-ADJUST manual oleh Owner sebelum generate —
 * bukan angka tetap yang diklaim sebagai proyeksi data historis.
 */
class SimulasiBalikModalService
{
    /** Hari kerja per bulan untuk konversi volume harian -> bulanan (konvensi 26 hari, konsisten brief). */
    private const HARI_KERJA_PER_BULAN = 26;

    public function __construct(
        private NeracaService $neracaService,
        private BepOtomatisService $bepOtomatisService,
    ) {
    }

    /**
     * @param float|null $volumeSedangKgPerHari Default: 1x BEP unit harian kalau null.
     * @param float|null $volumeOptimisKgPerHari Default: 2x BEP unit harian kalau null.
     * @param float $pangkasBebanPersen Persentase pemangkasan biaya tetap untuk skenario "Kombinasi Efisiensi".
     */
    public function simulasikanBalikModal(
        Carbon $tanggalAcuan,
        ?int $cabangId = null,
        ?float $volumeSedangKgPerHari = null,
        ?float $volumeOptimisKgPerHari = null,
        float $pangkasBebanPersen = 20.0
    ): array {
        $mulaiBulan = $tanggalAcuan->copy()->startOfMonth();
        $akhirTanggal = $tanggalAcuan->copy()->endOfDay();

        $neraca = $this->neracaService->hitungNeraca($tanggalAcuan, $cabangId);
        $modalAwal = (float) $neraca['modal']['modal_owner'];

        $bep = $this->bepOtomatisService->hitungBepOtomatis($mulaiBulan, $akhirTanggal, $cabangId);
        $hargaJualPerKg = $bep['harga_jual_per_unit'];
        $biayaVariabelPerKg = $bep['biaya_variabel_per_unit'];
        $biayaTetapBulanan = $bep['biaya_tetap'];

        $hariBerjalan = max(1, $mulaiBulan->diffInDays($akhirTanggal) + 1);
        $volumeTrenAktualPerHari = $bep['volume_aktual'] / $hariBerjalan;

        $bepUnitPerHari = ($bep['bisa_bep'] && $bep['bep_unit'] !== null)
            ? $bep['bep_unit'] / self::HARI_KERJA_PER_BULAN
            : 0.0;

        $volumeSedangKgPerHari ??= round($bepUnitPerHari, 2);
        $volumeOptimisKgPerHari ??= round($bepUnitPerHari * 2, 2);

        $skenarioList = [
            [
                'nama' => 'Tren Aktual',
                'deskripsi' => 'Volume harian rata-rata sesuai data bulan berjalan.',
                'volume_harian_kg' => $volumeTrenAktualPerHari,
                'biaya_tetap_bulanan' => $biayaTetapBulanan,
                'sumber' => 'data',
            ],
            [
                'nama' => 'Volume Sedang',
                'deskripsi' => 'Target volume yang bisa Anda sesuaikan (default: 1x BEP harian).',
                'volume_harian_kg' => $volumeSedangKgPerHari,
                'biaya_tetap_bulanan' => $biayaTetapBulanan,
                'sumber' => 'asumsi_owner',
            ],
            [
                'nama' => 'Volume BEP',
                'deskripsi' => 'Volume tepat di titik impas — laba seharusnya mendekati Rp0.',
                'volume_harian_kg' => $bepUnitPerHari,
                'biaya_tetap_bulanan' => $biayaTetapBulanan,
                'sumber' => 'data',
            ],
            [
                'nama' => 'Volume Optimis',
                'deskripsi' => 'Target volume yang bisa Anda sesuaikan (default: 2x BEP harian).',
                'volume_harian_kg' => $volumeOptimisKgPerHari,
                'biaya_tetap_bulanan' => $biayaTetapBulanan,
                'sumber' => 'asumsi_owner',
            ],
            [
                'nama' => 'Kombinasi Efisiensi',
                'deskripsi' => "Volume tren aktual + pangkas {$pangkasBebanPersen}% dari total Biaya Tetap.",
                'volume_harian_kg' => $volumeTrenAktualPerHari,
                'biaya_tetap_bulanan' => $biayaTetapBulanan * (1 - $pangkasBebanPersen / 100),
                'sumber' => 'data',
            ],
        ];

        $hasil = array_map(
            fn ($s) => $this->hitungSkenario($s, $hargaJualPerKg, $biayaVariabelPerKg, $modalAwal),
            $skenarioList
        );

        return [
            'modal_awal' => $modalAwal,
            'harga_jual_per_kg' => $hargaJualPerKg,
            'biaya_variabel_per_kg' => $biayaVariabelPerKg,
            'biaya_tetap_bulanan_asal' => $biayaTetapBulanan,
            'pangkas_beban_persen' => $pangkasBebanPersen,
            'volume_sedang_kg_per_hari' => $volumeSedangKgPerHari,
            'volume_optimis_kg_per_hari' => $volumeOptimisKgPerHari,
            'skenario' => $hasil,
        ];
    }

    private function hitungSkenario(array $s, float $hargaJualPerKg, float $biayaVariabelPerKg, float $modalAwal): array
    {
        $volumeBulanan = $s['volume_harian_kg'] * self::HARI_KERJA_PER_BULAN;
        $omzetBulanan = $volumeBulanan * $hargaJualPerKg;
        $hppBulanan = $volumeBulanan * $biayaVariabelPerKg;
        $labaBulanan = $omzetBulanan - $hppBulanan - $s['biaya_tetap_bulanan'];

        $waktuBalikModalBulan = null;
        $modalHabisDalamBulan = null;

        // Epsilon Rp1.000 — tepat di titik BEP, floating-point arithmetic
        // bisa menghasilkan laba mendekati 0 tapi tidak PERSIS 0 (mis.
        // Rp0,0000001), yang kalau tidak di-guard bikin pembagian modal
        // awal / laba-mendekati-nol menghasilkan angka astronomis tidak
        // masuk akal. Di bawah epsilon ini dianggap "impas", bukan untung/rugi.
        $epsilon = 1000.0;

        if ($labaBulanan > $epsilon && $modalAwal > 0) {
            $waktuBalikModalBulan = round($modalAwal / $labaBulanan, 1);
        } elseif ($labaBulanan < -$epsilon && $modalAwal > 0) {
            $modalHabisDalamBulan = round($modalAwal / abs($labaBulanan), 1);
        }

        return array_merge($s, [
            'volume_bulanan_kg' => round($volumeBulanan, 2),
            'omzet_bulanan' => round($omzetBulanan, 2),
            'hpp_bulanan' => round($hppBulanan, 2),
            'laba_bulanan' => round($labaBulanan, 2),
            'waktu_balik_modal_bulan' => $waktuBalikModalBulan,
            'modal_habis_dalam_bulan' => $modalHabisDalamBulan,
        ]);
    }
}
