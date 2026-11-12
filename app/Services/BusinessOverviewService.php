<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Order;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Eksekutif — Cover Overview (Halaman 1). Compose data dari service
 * yang SUDAH ADA (NeracaService, AssetDepreciationService, BepOtomatisService,
 * DashboardAnalyticsService) — TIDAK ADA duplikasi kalkulasi akuntansi/BEP di
 * sini. Kalkulasi baru yang genuinely ditambahkan di file ini HANYA yang
 * belum pernah dihitung service manapun: sisa umur ekonomis aset (aritmatika
 * tanggal sederhana), ringkasan transaksi/produksi (count/sum/avg polos), dan
 * cash runway (pembagian sederhana dari angka yang sudah dihitung service
 * lain). Murni READ, nol tulis ke tabel manapun.
 */
class BusinessOverviewService
{
    public function __construct(
        private NeracaService $neracaService,
        private AssetDepreciationService $assetDepreciationService,
        private BepOtomatisService $bepOtomatisService,
        private DashboardAnalyticsService $dashboardAnalyticsService,
    ) {
    }

    public function getCoverOverview(Carbon $tanggal, ?int $cabangId = null): array
    {
        $mulaiBulan = $tanggal->copy()->startOfMonth();
        $akhirTanggal = $tanggal->copy()->endOfDay();

        // Reuse NeracaService — modal awal & aset tetap breakdown per-item
        // sudah tersedia persis di sana, TIDAK query Asset:: ulang di sini.
        $neraca = $this->neracaService->hitungNeraca($tanggal, $cabangId);

        // Reuse AssetDepreciationService — snapshot agregat penyusutan.
        $depresiasiSnapshot = $this->assetDepreciationService->getSnapshotDashboard($cabangId);

        // Reuse BepOtomatisService — target vs realisasi bulan berjalan.
        $bep = $this->bepOtomatisService->hitungBepOtomatis($mulaiBulan, $akhirTanggal, $cabangId);

        // Reuse DashboardAnalyticsService — trend 6 bulan, rasio, profitabilitas.
        $analytics = $this->dashboardAnalyticsService->getSnapshot($cabangId, $tanggal);

        return [
            'tanggal' => $tanggal->toDateString(),
            // Expose array Neraca penuh — supaya Halaman 2 (Neraca) di
            // Laporan Eksekutif tidak perlu panggil NeracaService lagi
            // secara terpisah (reuse, bukan panggilan duplikat).
            'neraca' => $neraca,
            'modal_awal' => (float) $neraca['modal']['modal_owner'],
            'aset_tetap_breakdown' => [
                'detail' => $neraca['aset']['tetap']['detail'],
                'aset_bruto' => $neraca['aset']['tetap']['aset_bruto'],
                'akumulasi_depresiasi' => $neraca['aset']['tetap']['akumulasi_depresiasi'],
                'nilai_buku' => $neraca['aset']['tetap']['nilai_buku'],
            ],
            'penyusutan_summary' => [
                'bulanan' => $depresiasiSnapshot['depresiasi_bulan_ini'],
                'akumulasi' => $depresiasiSnapshot['total_akumulasi_depresiasi'],
                'nilai_buku' => $depresiasiSnapshot['total_nilai_buku'],
                'sisa_umur_rata2_bulan' => $this->hitungSisaUmurRata2($cabangId),
            ],
            'transaksi_summary' => $this->hitungTransaksiSummary($mulaiBulan, $akhirTanggal, $cabangId),
            'produksi_summary' => $this->hitungProduksiSummary($mulaiBulan, $akhirTanggal, $cabangId),
            'bep_vs_realisasi' => $this->lengkapiBepProjeksi($bep, $mulaiBulan, $akhirTanggal),
            'kas_summary' => $this->hitungKasSummary($neraca, $analytics),
            'trend_6_bulan' => $analytics['trend'],
            'analytics' => $analytics,
            'kesimpulan' => $this->generateKesimpulan($neraca, $bep, $analytics),
        ];
    }

    /**
     * Halaman 5 (Arus Kas) — total kas masuk/keluar per periode. Query
     * dasarnya (total_masuk/total_keluar) PERSIS sama seperti
     * `LaporanKeuanganController::arusKas()` existing (yang TIDAK disentuh)
     * — SENGAJA tidak difilter kas_id supaya angkanya tetap cross-check
     * konsisten dengan Menu Laporan Keuangan → Arus Kas.
     *
     * CATATAN PENTING: total_masuk/total_keluar di atas TERMASUK entri
     * non-tunai (mis. beban penyusutan aset, kas_id=NULL — lihat Rule
     * bisnis #35/#44) yang TIDAK benar-benar memindahkan uang. Supaya
     * Halaman 5 tidak menyesatkan, ditambahkan breakdown `tunai_only`
     * (exclude kas_id NULL) sebagai info TAMBAHAN — bukan pengganti angka
     * utama, supaya tetap konsisten dengan laporan existing yang sama.
     */
    public function getArusKasSummary(Carbon $mulai, Carbon $akhir, ?int $cabangId = null): array
    {
        $query = TransaksiKeuangan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->whereBetween('tanggal_transaksi', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('deleted_at');
        \App\Support\KeuanganBaba::saring($query, 'cabang_id', $cabangId); // Tahap 4: tanpa toko mitra

        $totalMasuk = (float) (clone $query)->where('tipe', \App\Enums\TipeTransaksiKeuangan::Pemasukan)->sum('jumlah');
        $totalKeluar = (float) (clone $query)->where('tipe', \App\Enums\TipeTransaksiKeuangan::Pengeluaran)->sum('jumlah');

        $totalKeluarTunai = (float) (clone $query)
            ->where('tipe', \App\Enums\TipeTransaksiKeuangan::Pengeluaran)
            ->whereNotNull('kas_id')
            ->sum('jumlah');
        $totalMasukTunai = (float) (clone $query)
            ->where('tipe', \App\Enums\TipeTransaksiKeuangan::Pemasukan)
            ->whereNotNull('kas_id')
            ->sum('jumlah');

        return [
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'net_kas' => $totalMasuk - $totalKeluar,
            'tunai_only' => [
                'total_masuk' => $totalMasukTunai,
                'total_keluar' => $totalKeluarTunai,
                'net_kas' => $totalMasukTunai - $totalKeluarTunai,
            ],
        ];
    }

    /**
     * Sisa umur ekonomis rata-rata (bulan) dari aset AKTIF — aritmatika
     * tanggal sederhana (umur_ekonomis_bulan - bulan berjalan sejak
     * tanggal_perolehan), BUKAN kalkulasi penyusutan (itu tetap dari
     * AssetDepreciationService). Tidak ada service existing yang menghitung
     * metrik ini.
     */
    private function hitungSisaUmurRata2(?int $cabangId): ?float
    {
        $query = Asset::where('status', \App\Enums\StatusAset::Aktif)
            ->whereNotNull('umur_ekonomis_bulan')
            ->whereNotNull('tanggal_perolehan');
        \App\Support\KeuanganBaba::saring($query, 'lokasi_id', $cabangId);

        $assets = $query->get(['tanggal_perolehan', 'umur_ekonomis_bulan']);
        if ($assets->isEmpty()) {
            return null;
        }

        $now = Carbon::now();
        $sisaUmurList = $assets->map(function ($a) use ($now) {
            $bulanBerjalan = Carbon::parse($a->tanggal_perolehan)->diffInMonths($now);
            return max(0, (int) $a->umur_ekonomis_bulan - $bulanBerjalan);
        });

        return round($sisaUmurList->avg(), 1);
    }

    /**
     * Ringkasan transaksi (order) bulan berjalan — count/sum/avg polos,
     * bukan kalkulasi akuntansi, tidak ada service existing untuk ini.
     */
    private function hitungTransaksiSummary(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $query = Order::withoutGlobalScopes()
            ->penjualanSah()
            ->whereBetween('tanggal_order', [$mulai->toDateString(), $akhir->toDateString()]);
        \App\Support\KeuanganBaba::saring($query, 'cabang_id', $cabangId); // Tahap 4: tanpa toko mitra

        $totalOrder = (clone $query)->count();
        $totalNominal = (float) (clone $query)->sum('total_bayar');

        return [
            'total_order' => $totalOrder,
            'total_nominal' => $totalNominal,
            'rata_rata_per_order' => $totalOrder > 0 ? round($totalNominal / $totalOrder, 2) : 0.0,
        ];
    }

    /**
     * Ringkasan produksi bulan berjalan — count/sum/avg polos dari
     * order_items, sumber sama yang dipakai BepOtomatisService tapi tidak
     * lewat method-nya (BepOtomatisService fokus ke BEP, bukan ringkasan
     * produksi harian/peak day yang genuinely beda kebutuhan).
     *
     * Tahap 7 D'mentai (2026-09-17) — fallback basis SAMA PERSIS seperti
     * BepOtomatisService::hitungDataJasaGiling(): kalau ada order
     * 'jasa_giling' di periode itu pakai berat_daging (kg, legacy), kalau
     * tidak (kasus normal D'mentai) pakai qty (pcs) dari order 'penjualan'.
     * Nama key TETAP `total_kg`/`rata_rata_harian_kg`/`kg` (1 konsumen saja,
     * `laporan/eksekutif/pdf/halaman-1-cover.blade.php`, tapi tetap ikuti
     * konvensi yang sama: label historis, bukan satuan literal).
     */
    private function hitungProduksiSummary(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $adaJasaGiling = DB::table('orders')
            ->where('tipe_order', 'jasa_giling')
            ->whereNotIn('status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('deleted_at')
            ->whereBetween('tanggal_order', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('deleted_at')
            ->tap(fn ($q) => \App\Support\KeuanganBaba::saring($q, 'cabang_id', $cabangId))
            ->exists();
        $kolomVolume = $adaJasaGiling ? 'order_items.berat_daging' : 'order_items.qty';

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tipe_order', $adaJasaGiling ? 'jasa_giling' : 'penjualan')
            ->whereNotIn('orders.status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('orders.deleted_at')
            ->whereBetween('orders.tanggal_order', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at');
        \App\Support\KeuanganBaba::saring($query, 'orders.cabang_id', $cabangId);

        $totalKg = (float) (clone $query)->sum($kolomVolume);

        $perHari = (clone $query)
            ->selectRaw("orders.tanggal_order as tanggal, SUM({$kolomVolume}) as kg")
            ->groupBy('orders.tanggal_order')
            ->orderByDesc('kg')
            ->get();

        $jumlahHari = max(1, $mulai->diffInDays($akhir) + 1);

        return [
            'total_kg' => $totalKg,
            'rata_rata_harian_kg' => round($totalKg / $jumlahHari, 2),
            'peak_day' => $perHari->first() ? [
                'tanggal' => $perHari->first()->tanggal,
                'kg' => (float) $perHari->first()->kg,
            ] : null,
        ];
    }

    /**
     * Lengkapi output BepOtomatisService dengan "kekurangan" dan "estimasi
     * waktu balik modal" — turunan sederhana dari angka yang SUDAH dihitung
     * BepOtomatisService, bukan re-derive formula BEP itu sendiri.
     */
    private function lengkapiBepProjeksi(array $bep, Carbon $mulai, Carbon $akhir): array
    {
        $kekurangan = null;
        $estimasiHari = null;

        if ($bep['bisa_bep'] && $bep['bep_unit'] !== null) {
            $kekurangan = max(0, $bep['bep_unit'] - $bep['volume_aktual']);

            if ($kekurangan > 0) {
                $hariBerjalan = max(1, $mulai->diffInDays($akhir) + 1);
                $rataRataHarianKg = $bep['volume_aktual'] / $hariBerjalan;
                $estimasiHari = $rataRataHarianKg > 0 ? (int) ceil($kekurangan / $rataRataHarianKg) : null;
            }
        }

        return array_merge($bep, [
            'kekurangan_kg' => $kekurangan,
            'estimasi_hari_menuju_bep' => $estimasiHari,
        ]);
    }

    /**
     * Kas Summary — kas_setara REUSE persis dari NeracaService (bukan query
     * Kas:: baru), cash runway = kas_setara / rata-rata beban bulanan 6
     * bulan terakhir (REUSE trend dari DashboardAnalyticsService, bukan
     * hitung ulang).
     */
    private function hitungKasSummary(array $neraca, array $analytics): array
    {
        $kasSetara = (float) $neraca['aset']['lancar']['kas_setara'];
        $rataRataBebanBulanan = collect($analytics['trend']['beban'])->avg();

        $cashRunwayBulan = ($rataRataBebanBulanan > 0)
            ? round($kasSetara / $rataRataBebanBulanan, 1)
            : null;

        return [
            'posisi_kas' => $kasSetara,
            'rata_rata_beban_bulanan' => round($rataRataBebanBulanan, 2),
            'cash_runway_bulan' => $cashRunwayBulan,
        ];
    }

    /**
     * Kesimpulan data-driven (bukan template statis) — dibangun dari
     * kondisi if/else atas angka yang SUDAH dihitung di atas, bukan
     * generative text. Setiap poin punya kategori (positif/perhatian/kunci)
     * supaya UI bisa render dengan warna/ikon yang sesuai.
     */
    private function generateKesimpulan(array $neraca, array $bep, array $analytics): array
    {
        $poin = [];

        if ($analytics['laba_bersih_bulan_ini'] >= 0) {
            $poin[] = [
                'kategori' => 'positif',
                'teks' => 'Laba bersih bulan berjalan positif: Rp ' . number_format($analytics['laba_bersih_bulan_ini'], 0, ',', '.')
                    . ($analytics['margin_persen'] !== null ? ' (margin ' . number_format($analytics['margin_persen'], 1, ',', '.') . '%)' : ''),
            ];
        } else {
            $poin[] = [
                'kategori' => 'perhatian',
                'teks' => 'Laba bersih bulan berjalan masih negatif: Rp ' . number_format($analytics['laba_bersih_bulan_ini'], 0, ',', '.')
                    . ' — perlu evaluasi biaya atau volume penjualan.',
            ];
        }

        if ($bep['bisa_bep']) {
            $pct = $bep['persentase_tercapai'] ?? 0;
            if ($pct >= 100) {
                $poin[] = ['kategori' => 'positif', 'teks' => 'BEP sudah tercapai bulan ini (' . number_format($pct, 1, ',', '.') . '%).'];
            } else {
                $poin[] = ['kategori' => 'perhatian', 'teks' => 'BEP baru tercapai ' . number_format($pct, 1, ',', '.') . '% — volume perlu ditingkatkan.'];
            }
        } else {
            $poin[] = ['kategori' => 'perhatian', 'teks' => 'Margin kontribusi jasa giling negatif/nol — BEP tidak bisa dicapai pada harga/biaya saat ini.'];
        }

        if (!$neraca['balance_check']) {
            $poin[] = ['kategori' => 'perhatian', 'teks' => 'Neraca belum balance (selisih Rp ' . number_format($neraca['selisih'], 0, ',', '.') . ') — wajar untuk data historis pra-COA, lihat Halaman 2.'];
        }

        if ($analytics['rasio_lancar'] === null) {
            $poin[] = ['kategori' => 'positif', 'teks' => 'Tidak ada kewajiban jangka pendek tercatat saat ini — posisi likuiditas aman.'];
        } elseif ($analytics['rasio_lancar'] < 1) {
            $poin[] = ['kategori' => 'perhatian', 'teks' => 'Rasio Lancar di bawah 1x (' . number_format($analytics['rasio_lancar'], 2, ',', '.') . 'x) — aset lancar belum cukup menutup kewajiban jangka pendek.'];
        } else {
            $poin[] = ['kategori' => 'kunci', 'teks' => 'Rasio Lancar sehat: ' . number_format($analytics['rasio_lancar'], 2, ',', '.') . 'x.'];
        }

        return $poin;
    }
}
