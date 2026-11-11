<?php

namespace App\Services;

use App\Enums\StatusOrder;
use App\Models\BepFixedCostItem;
use App\Models\BepProduct;
use App\Models\BepReport;
use App\Models\BepSetting;
use App\Models\Cabang;
use App\Models\Order;

class BepCalculationService
{
    /**
     * Hitung ulang BEP untuk semua produk dalam setting
     */
    public function hitungAtauUpdateBep(BepSetting $setting): void
    {
        // Update total biaya tetap dari sum item biaya tetap
        $totalBiayaTetap = $setting->fixedCostItems()->sum('jumlah');
        $setting->update(['total_biaya_tetap' => $totalBiayaTetap]);

        // Reload setting dengan total terbaru
        $setting->refresh();

        // Hitung ulang setiap produk
        foreach ($setting->products as $product) {
            $product->margin_kontribusi = $product->harga_jual_per_unit - $product->biaya_variabel_per_unit;

            if ($product->margin_kontribusi > 0) {
                $product->bep_unit = $totalBiayaTetap / $product->margin_kontribusi;
                $rasioMc = $product->harga_jual_per_unit > 0
                    ? $product->margin_kontribusi / $product->harga_jual_per_unit
                    : 0;
                $product->bep_rupiah = $rasioMc > 0 ? $totalBiayaTetap / $rasioMc : 0;
            } else {
                $product->bep_unit   = null;
                $product->bep_rupiah = null;
            }

            $product->save();
        }
    }

    /**
     * Generate atau update laporan BEP untuk cabang & periode tertentu
     */
    public function generateLaporan(int $cabangId, string $periode): BepReport
    {
        $setting = BepSetting::where('cabang_id', $cabangId)
            ->where('periode', $periode)
            ->with(['products', 'fixedCostItems'])
            ->first();

        $totalBiayaTetap = $setting ? (float) $setting->total_biaya_tetap : 0;

        // BUG1 FIX: pakai total_bayar (pendapatan aktual yg diterima, bukan total_harga sebelum diskon)
        // BUG1 FIX: pakai enum StatusOrder bukan hardcode string
        $totalPendapatan = 0;
        if (class_exists(Order::class)) {
            $totalPendapatan = Order::where('cabang_id', $cabangId)
                ->penjualanSah()
                ->where('tanggal_order', 'like', $periode . '%')
                ->sum('total_bayar');
        }

        // Estimasi total biaya variabel dari target penjualan produk
        $totalBiayaVariabel = 0;
        if ($setting) {
            foreach ($setting->products as $p) {
                if ($p->target_penjualan_unit > 0) {
                    $totalBiayaVariabel += (float) $p->biaya_variabel_per_unit * (float) $p->target_penjualan_unit;
                }
            }
        }

        // BUG2 FIX: sum() bukan avg() — BEP total = jumlah seluruh BEP per produk
        $bepRupiah = 0;
        if ($setting && $setting->products->count() > 0) {
            $bepRupiah = (float) $setting->products->whereNotNull('bep_rupiah')->sum('bep_rupiah');
        }

        $bepTercapai  = $totalPendapatan >= $bepRupiah && $bepRupiah > 0;
        $selisih      = $totalPendapatan - $bepRupiah;
        $persentase   = $bepRupiah > 0 ? ($totalPendapatan / $bepRupiah) * 100 : 0;

        return BepReport::updateOrCreate(
            ['cabang_id' => $cabangId, 'periode' => $periode],
            [
                'total_biaya_tetap'    => $totalBiayaTetap,
                'total_biaya_variabel' => $totalBiayaVariabel,
                'total_pendapatan'     => $totalPendapatan,
                'bep_tercapai'         => $bepTercapai,
                'selisih_dari_bep'     => $selisih,
                'persentase_bep'       => min(999.99, $persentase),
            ]
        );
    }
}
