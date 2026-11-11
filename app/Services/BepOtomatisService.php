<?php

namespace App\Services;

use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan BEP Otomatis — hitung Break Even Point langsung dari data transaksi
 * real (tanpa perlu setup manual BepSetting/BepProduct/BepFixedCostItem
 * seperti alur BEP existing di BepController/BepCalculationService, yang
 * TIDAK disentuh sama sekali oleh service ini). Murni READ.
 *
 * Tahap 7 D'mentai (2026-09-17): awalnya fokus ke lini bisnis Jasa Giling
 * (per kg) — SEKARANG basis ganda, otomatis fallback ke order 'penjualan'
 * (per pcs/unit) kalau tidak ada order 'jasa_giling' di periode itu (kasus
 * normal untuk D'mentai). Lihat docblock `hitungDataJasaGiling()` untuk
 * detail lengkap kenapa nama key return TETAP pakai suffix `_kg`.
 *
 * Sumber data:
 * - Biaya Tetap: SUM(transaksi_keuangans) untuk kategori dengan
 *   kategori_transaksis.tipe_biaya='tetap' (kolom dari Fase 1 — GAJI, SEWA,
 *   PNYS/Penyusutan, LISTRIK saat ini) di periode+cabang terpilih.
 * - Biaya Variabel & Volume Aktual: dari order_items.hpp/(berat_daging ATAU
 *   qty) untuk order ber-tipe_order='jasa_giling'/'penjualan' (BUKAN dari
 *   kategori tipe_biaya='variabel' — itu murni transaksi pembelian/beban,
 *   bukan HPP barang yang benar-benar terjual, jadi tidak representatif
 *   untuk biaya variabel per unit produksi).
 * - Harga Jual rata-rata: rata-rata tertimbang (total omzet / total volume),
 *   BUKAN AVG(harga_satuan) sederhana, supaya proporsional terhadap volume.
 */
class BepOtomatisService
{
    /**
     * @return array{
     *     periode: array{mulai: string, akhir: string},
     *     biaya_tetap: float,
     *     biaya_tetap_detail: array<int, array{nama_kategori: string, jumlah: float}>,
     *     biaya_variabel_per_unit: float,
     *     harga_jual_per_unit: float,
     *     margin_kontribusi_per_unit: float,
     *     volume_aktual: float,
     *     total_hpp_jasa_giling: float,
     *     total_omzet_jasa_giling: float,
     *     bep_unit: float|null,
     *     bep_rupiah: float|null,
     *     persentase_tercapai: float|null,
     *     margin_of_safety_persen: float|null,
     *     estimasi_laba: float,
     *     bisa_bep: bool,
     * }
     */
    public function hitungBepOtomatis(Carbon $mulai, Carbon $akhir, ?int $cabangId = null): array
    {
        $biayaTetap = $this->hitungBiayaTetap($mulai, $akhir, $cabangId);
        $dataJasaGiling = $this->hitungDataJasaGiling($mulai, $akhir, $cabangId);

        $volumeAktual = $dataJasaGiling['volume_kg'];
        $biayaVariabelPerUnit = $dataJasaGiling['biaya_variabel_per_kg'];
        $hargaJualPerUnit = $dataJasaGiling['harga_jual_rata2_per_kg'];
        $marginKontribusi = $hargaJualPerUnit - $biayaVariabelPerUnit;

        $bepUnit = $marginKontribusi > 0 ? $biayaTetap['total'] / $marginKontribusi : null;
        $bepRupiah = ($marginKontribusi > 0 && $hargaJualPerUnit > 0)
            ? $biayaTetap['total'] / ($marginKontribusi / $hargaJualPerUnit)
            : null;

        $persentaseTercapai = ($bepUnit !== null && $bepUnit > 0)
            ? round(($volumeAktual / $bepUnit) * 100, 2)
            : null;

        $marginOfSafetyPersen = ($volumeAktual > 0 && $bepUnit !== null)
            ? round((($volumeAktual - $bepUnit) / $volumeAktual) * 100, 2)
            : null;

        $estimasiLaba = ($marginKontribusi * $volumeAktual) - $biayaTetap['total'];

        return [
            'periode' => ['mulai' => $mulai->toDateString(), 'akhir' => $akhir->toDateString()],
            'biaya_tetap' => $biayaTetap['total'],
            'biaya_tetap_detail' => $biayaTetap['detail'],
            'biaya_variabel_per_unit' => $biayaVariabelPerUnit,
            'harga_jual_per_unit' => $hargaJualPerUnit,
            'margin_kontribusi_per_unit' => $marginKontribusi,
            'volume_aktual' => $volumeAktual,
            'total_hpp_jasa_giling' => $dataJasaGiling['total_hpp'],
            'total_omzet_jasa_giling' => $dataJasaGiling['total_omzet'],
            'bep_unit' => $bepUnit,
            'bep_rupiah' => $bepRupiah,
            'persentase_tercapai' => $persentaseTercapai,
            'margin_of_safety_persen' => $marginOfSafetyPersen,
            'estimasi_laba' => $estimasiLaba,
            'bisa_bep' => $marginKontribusi > 0,
        ];
    }

    /**
     * Biaya Tetap dari kategori_transaksis.tipe_biaya='tetap' — reuse kolom
     * yang sudah ada dari Fase 1, bukan hardcode daftar kategori seperti
     * BepController::autoFill() existing (gaji/depresiasi/sewa/ops hardcode).
     */
    private function hitungBiayaTetap(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $query = TransaksiKeuangan::query()
            ->join('kategori_transaksis', 'kategori_transaksis.id', '=', 'transaksi_keuangans.kategori_id')
            ->where('kategori_transaksis.tipe_biaya', 'tetap')
            ->whereBetween('transaksi_keuangans.tanggal_transaksi', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('transaksi_keuangans.deleted_at');

        if ($cabangId) {
            $query->where('transaksi_keuangans.cabang_id', $cabangId);
        }

        $rows = $query->selectRaw('kategori_transaksis.nama as nama_kategori, SUM(transaksi_keuangans.jumlah) as total')
            ->groupBy('kategori_transaksis.nama')
            ->orderByDesc('total')
            ->get();

        return [
            'total' => (float) $rows->sum('total'),
            'detail' => $rows->map(fn ($r) => [
                'nama_kategori' => $r->nama_kategori,
                'jumlah' => (float) $r->total,
            ])->values()->toArray(),
        ];
    }

    /**
     * Tahap 7 D'mentai (2026-09-17) — Volume/HPP/Omzet, sekarang basis GANDA:
     *  - Kalau ADA order 'jasa_giling' di periode itu (data lama/legacy,
     *    kemungkinan besar tidak akan pernah muncul lagi di D'mentai murni
     *    retail) -> pakai logic LAMA APA ADANYA (backward compat, tidak
     *    diubah sedikit pun): volume = SUM(berat_daging) dalam KG.
     *  - Kalau TIDAK ADA (kasus NORMAL untuk D'mentai) -> hitung dari order
     *    'penjualan': volume = SUM(order_items.qty) dalam PCS/UNIT (bukan kg,
     *    dimsum/gyoza tidak ditimbang), HPP & omzet tetap dari order_items
     *    yang sama seperti sebelumnya.
     *
     * PENTING — nama key return TETAP `volume_kg`/`biaya_variabel_per_kg`/
     * `harga_jual_rata2_per_kg` walau isinya sekarang PCS untuk D'mentai.
     * Ini SENGAJA (bukan lupa rename) — method ini dikonsumsi 6+ file lain
     * (BusinessOverviewService, CrossCheckValidator, DashboardAnalyticsService,
     * SimulasiBalikModalService, LaporanBepOtomatisController,
     * SimulatorBepController) yang semuanya membaca key ini apa adanya;
     * rename key akan menaikkan blast radius jauh lebih besar daripada
     * manfaatnya. "kg" di nama key dibaca sebagai label historis warisan
     * Berkah Mulyo, bukan satuan literal — nilai sebenarnya mengikuti data
     * (kg utk jasa_giling lama, pcs utk penjualan D'mentai).
     *
     * @return array{
     *     volume_kg: float,  // unit: KG untuk order 'jasa_giling' (legacy), PCS/unit untuk order 'penjualan' (D'mentai). Nama key dipertahankan utk backward compat 6+ konsumen (lihat di atas).
     *     total_hpp: float,
     *     total_omzet: float,
     *     biaya_variabel_per_kg: float,  // per KG (legacy) atau per PCS (D'mentai), sesuai volume_kg
     *     harga_jual_rata2_per_kg: float,  // idem
     * }
     */
    private function hitungDataJasaGiling(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $adaJasaGiling = DB::table('orders')
            ->where('tipe_order', 'jasa_giling')
            ->whereNotIn('status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('deleted_at')
            ->whereBetween('tanggal_order', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('deleted_at')
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->exists();

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tipe_order', $adaJasaGiling ? 'jasa_giling' : 'penjualan')
            ->whereNotIn('orders.status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('orders.deleted_at')
            ->whereBetween('orders.tanggal_order', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at');

        if ($cabangId) {
            $query->where('orders.cabang_id', $cabangId);
        }

        $kolomVolume = $adaJasaGiling ? 'order_items.berat_daging' : 'order_items.qty';

        $row = $query->selectRaw("
                SUM({$kolomVolume}) as total_volume,
                SUM(order_items.hpp) as total_hpp,
                SUM(order_items.total_harga) as total_omzet
            ")
            ->first();

        $totalVolume = (float) ($row->total_volume ?? 0);
        $totalHpp = (float) ($row->total_hpp ?? 0);
        $totalOmzet = (float) ($row->total_omzet ?? 0);

        return [
            'volume_kg' => $totalVolume,
            'total_hpp' => $totalHpp,
            'total_omzet' => $totalOmzet,
            'biaya_variabel_per_kg' => $totalVolume > 0 ? $totalHpp / $totalVolume : 0.0,
            'harga_jual_rata2_per_kg' => $totalVolume > 0 ? $totalOmzet / $totalVolume : 0.0,
        ];
    }
}
