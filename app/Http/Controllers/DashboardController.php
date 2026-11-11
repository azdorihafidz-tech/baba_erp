<?php

namespace App\Http\Controllers;

use App\Enums\StatusOrder;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusStockRequest;
use App\Enums\TipeCabang;
use App\Models\Cabang;
use App\Models\Karyawan;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Services\AnalisisPenjualanService;
use App\Services\AssetDepreciationService;
use App\Services\DashboardAnalyticsService;
use App\Services\JamRamaiService;
use App\Services\LoyaltyKlaimService;
use App\Services\LoyaltyService;
use App\Services\NeracaService;
use App\Services\PoDashboardService;
use App\Models\Kas;
use App\Models\Setoran;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private PoDashboardService $poDashboardService,
        private AssetDepreciationService $assetDepreciationService,
        private NeracaService $neracaService,
        private DashboardAnalyticsService $dashboardAnalyticsService,
        private JamRamaiService $jamRamaiService,
        private LoyaltyService $loyaltyService,
        private LoyaltyKlaimService $loyaltyKlaimService,
        private AnalisisPenjualanService $analisisPenjualanService,
    ) {
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        // Redirect berdasarkan role/kondisi
        if ($user->canAccessAllBranches()) {
            $activeCabangId = session('active_cabang_id');
            if (!$activeCabangId) {
                return redirect()->route('dashboard.pusat');
            }
            // Cek tipe cabang aktif
            $cabang = Cabang::find($activeCabangId);
            if ($cabang && $cabang->tipe === TipeCabang::GudangPusat) {
                return redirect()->route('dashboard.gudang');
            }
            return redirect()->route('dashboard.cabang');
        }

        // Cek apakah user di gudang pusat
        $activeCabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        if ($activeCabangId) {
            $cabang = Cabang::find($activeCabangId);
            if ($cabang && $cabang->tipe === TipeCabang::GudangPusat) {
                return redirect()->route('dashboard.gudang');
            }
        }

        return redirect()->route('dashboard.cabang');
    }

    public function cabang(Request $request)
    {
        $user = auth()->user();
        $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        $cabang = $cabangId ? Cabang::find($cabangId) : null;

        $today = Carbon::today();
        $bulanIni = Carbon::now()->format('Y-m');

        // Omzet hari ini
        $omzetHariIni = Order::withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal_order', $today)
            ->penjualanSah()
            ->sum('total_bayar');

        // Jumlah order hari ini
        $jumlahOrderHariIni = Order::withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal_order', $today)
            ->penjualanSah()
            ->count();

        // Tahap 6 D'mentai — 'orderJasaGilingHariIni' (hardcode tipe_order=
        // 'jasa_giling', bug lama CLAUDE.md 4.1, selalu 0 untuk D'mentai)
        // diganti breakdown per Tipe Transaksi (Dine-in/Takeaway/Frozen) —
        // relevan untuk bisnis retail food, data dari kolom yang benar-benar
        // dipakai (orders.tipe_transaksi, Tahap 3).
        $orderPerTipeTransaksi = Order::withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal_order', $today)
            ->penjualanSah()
            ->selectRaw('tipe_transaksi, COUNT(*) as jumlah')
            ->groupBy('tipe_transaksi')
            ->pluck('jumlah', 'tipe_transaksi');

        // Stok rendah
        $stokRendah = Stock::with('item')
            ->where('lokasi_id', $cabangId)
            ->whereColumn('qty', '<=', 'qty_minimum')
            ->where('qty_minimum', '>', 0)
            ->get();

        // Order terbaru
        $orderTerbaru = Order::withoutGlobalScopes()
            ->with(['pelanggan'])
            ->where('cabang_id', $cabangId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Omzet bulan ini
        $omzetBulanIni = Order::withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->where('tanggal_order', 'like', $bulanIni . '%')
            ->penjualanSah()
            ->sum('total_bayar');

        // Karyawan aktif
        $jumlahKaryawan = Karyawan::withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->where('status', 'aktif')
            ->count();

        // Grafik 7 hari terakhir
        $grafik7HariLabels = [];
        $grafik7HariData = [];
        for ($i = 6; $i >= 0; $i--) {
            $tgl = Carbon::today()->subDays($i);
            $grafik7HariLabels[] = $tgl->format('d/m');
            $grafik7HariData[] = (float) Order::withoutGlobalScopes()
                ->where('cabang_id', $cabangId)
                ->whereDate('tanggal_order', $tgl)
                ->penjualanSah()
                ->sum('total_bayar');
        }

        // Widget Status PO — murni tambahan, tidak mengubah widget lain di atas
        $poRingkasan = auth()->user()->can('po_dashboard.view')
            ? $this->poDashboardService->getRingkasanStatus($cabangId)
            : null;

        // Widget Snapshot Aset — murni tambahan, tidak mengubah widget lain
        $asetSnapshot = auth()->user()->can('aset.view')
            ? $this->assetDepreciationService->getSnapshotDashboard($cabangId)
            : null;

        // Widget Snapshot Neraca (FASE 2 Akuntansi) — murni tambahan, tidak mengubah widget lain
        $neracaSnapshot = auth()->user()->can('laporan.neraca.view')
            ? $this->neracaService->hitungNeraca(Carbon::now(), $cabangId)
            : null;

        // Widget Dashboard Analytics (FASE 3) — murni tambahan, tidak mengubah widget lain
        $analyticsSnapshot = auth()->user()->can('dashboard.analytics.view')
            ? $this->dashboardAnalyticsService->getSnapshot($cabangId)
            : null;

        // Widget Jam Ramai Hari Ini — murni tambahan, reuse JamRamaiService
        // (Orders-only) yang sama dengan Laporan Analisa Jam Ramai
        $jamRamaiSnapshot = auth()->user()->can('laporan.jam_ramai.view')
            ? $this->jamRamaiService->getAnalisaJamRamai(Carbon::today(), Carbon::now()->endOfDay(), $cabangId)
            : null;

        // Widget Loyalty — murni tambahan, TIDAK di-scope cabang (kumulatif
        // pelanggan lintas cabang by design, sama seperti LoyaltyService)
        $loyaltyWidget = auth()->user()->can('loyalty.view')
            ? $this->loyaltyService->getWidgetData()
            : null;

        // Widget Klaim Menunggu Approval (Fase 2, event-based) — cuma utk
        // yang bisa approve, bukan sekadar bisa lihat (loyalty.klaim.view
        // juga dipegang kasir, tapi mereka tidak actionable atas widget ini).
        $loyaltyKlaimWidget = auth()->user()->can('loyalty.klaim.approve')
            ? $this->loyaltyKlaimService->getWidgetData()
            : null;

        // Sprint 4.32 — Analisis Penjualan scoped ke cabang aktif.
        $apPeriode = $request->get('ap_periode', 'bulan_ini');
        $apResolved = AnalisisPenjualanService::presetPeriode($apPeriode);
        $analisisPenjualan = $this->analisisPenjualanService->hitung(
            $cabangId,
            $apResolved['dari'],
            $apResolved['sampai'],
        );
        $analisisPenjualanMeta = [
            'periode_aktif' => $apPeriode,
            'label_periode' => $apResolved['label'],
            'preset_list'   => AnalisisPenjualanService::daftarPreset(),
            'cabang_id'     => $cabangId,
        ];

        return view('dashboard.cabang', compact(
            'analisisPenjualan',
            'analisisPenjualanMeta',
            'cabang',
            'omzetHariIni',
            'jumlahOrderHariIni',
            'orderPerTipeTransaksi',
            'stokRendah',
            'orderTerbaru',
            'omzetBulanIni',
            'jumlahKaryawan',
            'grafik7HariLabels',
            'grafik7HariData',
            'poRingkasan',
            'asetSnapshot',
            'neracaSnapshot',
            'analyticsSnapshot',
            'jamRamaiSnapshot',
            'loyaltyWidget',
            'loyaltyKlaimWidget'
        ));
    }

    public function gudang(Request $request)
    {
        $gudangPusat = Cabang::gudangPusat()->first();
        $gudangId = $gudangPusat?->id;

        // Total stok gudang
        $totalStokGudang = Stock::where('lokasi_id', $gudangId)->count();

        // Permintaan pending
        $permintaanPending = StockRequest::withoutGlobalScopes()
            ->with('cabang')
            ->where('status', StatusStockRequest::Pending)
            ->orderByDesc('created_at')
            ->get();
        $stockRequestPending = $permintaanPending->count();

        // PO aktif
        $poAktif = PurchaseOrder::withoutGlobalScopes()
            ->whereNotIn('status', [StatusPurchaseOrder::Diterima, StatusPurchaseOrder::Dibatalkan])
            ->count();

        // Stok rendah di gudang
        $stokRendah = Stock::with('item')
            ->where('lokasi_id', $gudangId)
            ->whereColumn('qty', '<=', 'qty_minimum')
            ->where('qty_minimum', '>', 0)
            ->get();

        // Transfer terbaru
        $transferTerakhir = StockTransfer::with(['dariLokasi', 'keLokasi'])
            ->where(function ($q) use ($gudangId) {
                $q->where('dari_lokasi_id', $gudangId)
                  ->orWhere('ke_lokasi_id', $gudangId);
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('dashboard.gudang', compact(
            'gudangPusat',
            'totalStokGudang',
            'permintaanPending',
            'stockRequestPending',
            'poAktif',
            'stokRendah',
            'transferTerakhir'
        ));
    }

    public function pusat(Request $request)
    {
        $bulanIni = Carbon::now()->format('Y-m');
        $today = Carbon::today();

        // Tahap 6 D'mentai — widget khusus dashboard Owner (setoran + kas HO).
        // Semua di-gate 1 permission `dashboard.owner.view` (bukan Owner-only
        // hardcode) supaya Admin Pusat (role HO) juga bisa lihat.
        $dashboardOwner = null;
        if (auth()->user()->can('dashboard.owner.view')) {
            $totalOmzetHariIni = (float) Order::withoutGlobalScopes()
                ->whereDate('tanggal_order', $today)
                ->penjualanSah()
                ->sum('total_bayar');

            // "Uang belum disetor" = setoran yang sudah disubmit tapi belum
            // approved (menunggu/rejected). Hari yang BELUM ADA setoran sama
            // sekali (kasir belum submit) TIDAK ikut terhitung di sini — itu
            // soal kepatuhan submit, bukan angka yang bisa dihitung dari data
            // yang belum ada.
            $uangBelumDisetor = (float) Setoran::whereIn('status', ['menunggu', 'rejected'])->sum('total_disetor');

            $gudangPusatIds = Cabang::gudangPusat()->pluck('id');
            $kasHoSaldo = (float) Kas::aktif()->whereIn('cabang_id', $gudangPusatIds)->sum('saldo_sekarang');

            $setoranPending = Setoran::with('cabang')->where('status', 'menunggu')->orderBy('tanggal')->limit(10)->get();

            // Selisih setoran signifikan (>= Rp5.000, dua arah — kurang maupun lebih)
            $setoranSelisih = Setoran::with('cabang')
                ->where(function ($q) { $q->where('selisih', '>=', 5000)->orWhere('selisih', '<=', -5000); })
                ->orderByDesc('tanggal')->limit(10)->get();

            $stokMinimumList = Stock::with(['item', 'lokasi'])
                ->whereColumn('qty', '<=', 'qty_minimum')
                ->where('qty_minimum', '>', 0)
                ->whereHas('item', fn ($q) => $q->whereIn('tipe', ['bahan_baku', 'kemasan', 'produk_jual']))
                ->orderBy('qty')
                ->limit(10)
                ->get();

            // Trend penjualan harian 7 hari terakhir, semua outlet
            $grafik7HariLabels = [];
            $grafik7HariData = [];
            for ($i = 6; $i >= 0; $i--) {
                $tgl = Carbon::today()->subDays($i);
                $grafik7HariLabels[] = $tgl->format('d/m');
                $grafik7HariData[] = (float) Order::withoutGlobalScopes()
                    ->whereDate('tanggal_order', $tgl)
                    ->penjualanSah()
                    ->sum('total_bayar');
            }

            $dashboardOwner = compact(
                'totalOmzetHariIni', 'uangBelumDisetor', 'kasHoSaldo',
                'setoranPending', 'setoranSelisih', 'stokMinimumList',
                'grafik7HariLabels', 'grafik7HariData'
            );
        }

        // Total omzet bulan ini semua cabang
        $totalOmzetBulanIni = Order::withoutGlobalScopes()
            ->where('tanggal_order', 'like', $bulanIni . '%')
            ->penjualanSah()
            ->sum('total_bayar');

        // Total order bulan ini
        $totalOrderBulanIni = Order::withoutGlobalScopes()
            ->where('tanggal_order', 'like', $bulanIni . '%')
            ->penjualanSah()
            ->count();

        // Total karyawan aktif
        $totalKaryawan = Karyawan::withoutGlobalScopes()
            ->where('status', 'aktif')
            ->count();

        // Alert stok rendah
        $alertStokRendah = Stock::whereColumn('qty', '<=', 'qty_minimum')->where('qty_minimum', '>', 0)->count();

        // Alert pembelian mendesak
        $alertPembelianMendesak = PurchaseOrder::withoutGlobalScopes()
            ->where('pembelian_langsung', true)
            ->whereNotIn('status', [StatusPurchaseOrder::Diterima, StatusPurchaseOrder::Dibatalkan])
            ->count();

        // Cabang-cabang aktif (bukan gudang pusat)
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        // Omzet per cabang bulan ini
        $grafikPerCabangLabels = [];
        $grafikPerCabangData = [];
        $rankingCabang = [];

        foreach ($cabangs as $cb) {
            $omzet = (float) Order::withoutGlobalScopes()
                ->where('cabang_id', $cb->id)
                ->where('tanggal_order', 'like', $bulanIni . '%')
                ->penjualanSah()
                ->sum('total_bayar');
            $grafikPerCabangLabels[] = $cb->nama_cabang;
            $grafikPerCabangData[] = $omzet;
            $rankingCabang[] = ['cabang' => $cb, 'omzet' => $omzet];
        }

        // Sort ranking desc
        usort($rankingCabang, fn($a, $b) => $b['omzet'] <=> $a['omzet']);

        // Grafik bulanan 6 bulan terakhir (multi-series per cabang)
        $grafikBulananLabels = [];
        $grafikBulananDatasets = [];

        for ($i = 5; $i >= 0; $i--) {
            $bln = Carbon::now()->subMonths($i)->format('Y-m');
            $grafikBulananLabels[] = Carbon::now()->subMonths($i)->translatedFormat('M Y');
        }

        $colors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
        foreach ($cabangs as $idx => $cb) {
            $dataPerBulan = [];
            for ($i = 5; $i >= 0; $i--) {
                $bln = Carbon::now()->subMonths($i)->format('Y-m');
                $dataPerBulan[] = (float) Order::withoutGlobalScopes()
                    ->where('cabang_id', $cb->id)
                    ->where('tanggal_order', 'like', $bln . '%')
                    ->penjualanSah()
                    ->sum('total_bayar');
            }
            $grafikBulananDatasets[] = [
                'label' => $cb->nama_cabang,
                'data' => $dataPerBulan,
                'borderColor' => $colors[$idx % count($colors)],
                'backgroundColor' => $colors[$idx % count($colors)] . '20',
                'borderWidth' => 2,
                'fill' => false,
                'tension' => 0.4,
            ];
        }

        // Widget Status PO (semua cabang, konsolidasi) — murni tambahan
        $poRingkasan = auth()->user()->can('po_dashboard.view')
            ? $this->poDashboardService->getRingkasanStatus(null)
            : null;

        // Widget Snapshot Aset (semua cabang, konsolidasi) — murni tambahan
        $asetSnapshot = auth()->user()->can('aset.view')
            ? $this->assetDepreciationService->getSnapshotDashboard(null)
            : null;

        // Widget Snapshot Neraca (FASE 2 Akuntansi, konsolidasi) — murni tambahan
        $neracaSnapshot = auth()->user()->can('laporan.neraca.view')
            ? $this->neracaService->hitungNeraca(Carbon::now(), null)
            : null;

        // Widget Dashboard Analytics (FASE 3, konsolidasi) — murni tambahan
        $analyticsSnapshot = auth()->user()->can('dashboard.analytics.view')
            ? $this->dashboardAnalyticsService->getSnapshot(null)
            : null;

        // Widget Jam Ramai Hari Ini (konsolidasi) — murni tambahan
        $jamRamaiSnapshot = auth()->user()->can('laporan.jam_ramai.view')
            ? $this->jamRamaiService->getAnalisaJamRamai(Carbon::today(), Carbon::now()->endOfDay(), null)
            : null;

        // Widget Loyalty — murni tambahan, TIDAK di-scope cabang (sama
        // dengan versi di cabang(), datanya memang identik/konsolidasi)
        $loyaltyWidget = auth()->user()->can('loyalty.view')
            ? $this->loyaltyService->getWidgetData()
            : null;

        $loyaltyKlaimWidget = auth()->user()->can('loyalty.klaim.approve')
            ? $this->loyaltyKlaimService->getWidgetData()
            : null;

        // Sprint 4.32 — Analisis Penjualan (scope all cabang, filter periode terpisah
        // dari widget existing — section baru, tidak mempengaruhi 4 card snapshot di atas)
        $apPeriode = $request->get('ap_periode', 'bulan_ini');
        $apResolved = AnalisisPenjualanService::presetPeriode($apPeriode);
        $analisisPenjualan = $this->analisisPenjualanService->hitung(
            null,
            $apResolved['dari'],
            $apResolved['sampai'],
        );
        $analisisPenjualanMeta = [
            'periode_aktif' => $apPeriode,
            'label_periode' => $apResolved['label'],
            'preset_list'   => AnalisisPenjualanService::daftarPreset(),
            'cabang_id'     => null,
        ];

        return view('dashboard.pusat', compact(
            'dashboardOwner',
            'totalOmzetBulanIni',
            'totalOrderBulanIni',
            'totalKaryawan',
            'alertStokRendah',
            'alertPembelianMendesak',
            'analisisPenjualan',
            'analisisPenjualanMeta',
            'cabangs',
            'grafikPerCabangLabels',
            'grafikPerCabangData',
            'rankingCabang',
            'grafikBulananLabels',
            'grafikBulananDatasets',
            'poRingkasan',
            'asetSnapshot',
            'neracaSnapshot',
            'analyticsSnapshot',
            'jamRamaiSnapshot',
            'loyaltyWidget',
            'loyaltyKlaimWidget'
        ));
    }
}
