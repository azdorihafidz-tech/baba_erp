<?php

namespace App\Http\Controllers;

use App\Enums\StatusOrder;
use App\Models\BepProduct;
use App\Models\BepReport;
use App\Models\BepSetting;
use App\Models\Cabang;
use App\Models\Order;
use App\Exports\LaporanBepExport;
use App\Exports\LaporanBepCabangExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanBepController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.bep.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $periode = $request->periode ?? Carbon::now()->format('Y-m');

        // BEP Setting untuk cabang & periode ini
        $bepSetting = BepSetting::withoutGlobalScopes()
            ->with(['fixedCostItems', 'products'])
            ->where('periode', $periode)
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->first();

        $products = $bepSetting ? $bepSetting->products : collect();
        $totalBiayaTetap = $bepSetting?->total_biaya_tetap ?? 0;

        // Pendapatan aktual periode ini
        $pendapatanAktual = Order::withoutGlobalScopes()
            ->where('tanggal_order', 'like', $periode . '%')
            ->penjualanSah()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->sum('total_bayar');

        // BEP total = sum BEP rupiah semua produk
        $totalBepRupiah = $products->sum('bep_rupiah');

        // Persentase pencapaian BEP
        $pctBep = $totalBepRupiah > 0
            ? min(100, ($pendapatanAktual / $totalBepRupiah) * 100)
            : 0;
        $bepTercapai = $pendapatanAktual >= $totalBepRupiah && $totalBepRupiah > 0;

        // Bug6 FIX: bangun chart data untuk SEMUA produk, bukan hanya produk pertama
        $allProductsChartData = [];
        $grafikLabels         = [];
        $grafikBiayaTetap     = [];
        $grafikBiayaTotal     = [];
        $grafikPendapatan     = [];

        if ($products->isNotEmpty() && $totalBiayaTetap > 0) {
            foreach ($products as $product) {
                $biayaVar  = (float) ($product->biaya_variabel_per_unit ?? 0);
                $hargaJual = (float) ($product->harga_jual_per_unit ?? 0);
                if ($hargaJual <= 0) continue;

                $bepUnit = (float) ($product->bep_unit ?? 0);
                $maxUnit = $bepUnit > 0 ? $bepUnit * 2 : 100;
                $step    = max(1, round($maxUnit / 10));

                $lblArr = $btArr = $btotArr = $pdArr = [];
                for ($u = 0; $u <= $maxUnit; $u += $step) {
                    $lblArr[]   = number_format($u, 0, ',', '.');
                    $btArr[]    = (float) $totalBiayaTetap;
                    $btotArr[]  = (float) ($totalBiayaTetap + ($biayaVar * $u));
                    $pdArr[]    = (float) ($hargaJual * $u);
                }

                $allProductsChartData[(string) $product->id] = [
                    'name'       => $product->nama_produk,
                    'labels'     => $lblArr,
                    'biayaTetap' => $btArr,
                    'biayaTotal' => $btotArr,
                    'pendapatan' => $pdArr,
                    'bepUnit'    => (float) ($product->bep_unit ?? 0),
                    'bepRupiah'  => (float) ($product->bep_rupiah ?? 0),
                ];
            }

            // Vars legacy untuk per_cabang view (produk pertama)
            if (!empty($allProductsChartData)) {
                $first            = reset($allProductsChartData);
                $grafikLabels     = $first['labels'];
                $grafikBiayaTetap = $first['biayaTetap'];
                $grafikBiayaTotal = $first['biayaTotal'];
                $grafikPendapatan = $first['pendapatan'];
            }
        }

        if ($request->export === 'excel') {
            return Excel::download(new LaporanBepExport($products, auth()->user()->name), 'laporan-bep-' . $periode . '.xlsx');
        }

        if ($request->export === 'pdf') {
            $filterInfo = [
                'Periode' => $periode,
                'Cabang' => optional($cabangs->firstWhere('id', $cabangId))->nama_cabang ?? 'Semua Cabang',
                'Total Biaya Tetap' => 'Rp ' . number_format($totalBiayaTetap, 0, ',', '.'),
                'Pendapatan Aktual' => 'Rp ' . number_format($pendapatanAktual, 0, ',', '.'),
            ];
            $pdf = Pdf::loadView('laporan.pdf.bep', [
                'products' => $products,
                'judulLaporan' => 'Laporan BEP per Produk',
                'filterInfo' => $filterInfo,
                'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'portrait');
            return $pdf->download('laporan-bep-' . $periode . '.pdf');
        }

        // Riwayat BEP laporan
        $reports = BepReport::withoutGlobalScopes()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->orderByDesc('periode')
            ->limit(12)
            ->get();

        return view('laporan.bep.index', compact(
            'cabangs', 'cabangId', 'periode', 'bepSetting', 'products',
            'totalBiayaTetap', 'pendapatanAktual', 'totalBepRupiah',
            'pctBep', 'bepTercapai',
            'grafikLabels', 'grafikBiayaTetap', 'grafikBiayaTotal', 'grafikPendapatan',
            'allProductsChartData',
            'reports'
        ));
    }

    public function perCabang(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.bep.view'), 403);

        $periode = $request->periode ?? Carbon::now()->format('Y-m');
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        $dataCabang = [];
        foreach ($cabangs as $cabang) {
            $setting = BepSetting::withoutGlobalScopes()
                ->with('products')
                ->where('cabang_id', $cabang->id)
                ->where('periode', $periode)
                ->first();

            $pendapatan = Order::withoutGlobalScopes()
                ->where('cabang_id', $cabang->id)
                ->where('tanggal_order', 'like', $periode . '%')
                ->penjualanSah()
                ->sum('total_bayar');

            $bepRupiah = $setting?->products->sum('bep_rupiah') ?? 0;
            $pct = $bepRupiah > 0 ? min(100, ($pendapatan / $bepRupiah) * 100) : 0;

            $dataCabang[] = [
                'cabang' => $cabang,
                'pendapatan' => $pendapatan,
                'bep_rupiah' => $bepRupiah,
                'biaya_tetap' => $setting?->total_biaya_tetap ?? 0,
                'pct_bep' => $pct,
                'tercapai' => $pendapatan >= $bepRupiah && $bepRupiah > 0,
            ];
        }

        $grafikLabels = collect($dataCabang)->pluck('cabang')->pluck('nama_cabang')->toArray();
        $grafikPendapatan = collect($dataCabang)->pluck('pendapatan')->toArray();
        $grafikBepTarget = collect($dataCabang)->pluck('bep_rupiah')->toArray();

        if ($request->export === 'excel') {
            return Excel::download(new LaporanBepCabangExport(collect($dataCabang), auth()->user()->name), 'laporan-bep-cabang-' . $periode . '.xlsx');
        }

        if ($request->export === 'pdf') {
            $filterInfo = ['Periode' => $periode, 'Total Cabang' => count($dataCabang)];
            $pdf = Pdf::loadView('laporan.pdf.bep-cabang', [
                'dataCabang' => $dataCabang,
                'judulLaporan' => 'Laporan BEP per Cabang',
                'filterInfo' => $filterInfo,
                'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'landscape');
            return $pdf->download('laporan-bep-cabang-' . $periode . '.pdf');
        }

        return view('laporan.bep.index', compact(
            'cabangs', 'dataCabang', 'periode',
            'grafikLabels', 'grafikPendapatan', 'grafikBepTarget'
        ))->with('viewMode', 'per_cabang');
    }

}

