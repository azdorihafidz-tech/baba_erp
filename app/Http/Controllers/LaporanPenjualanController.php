<?php

namespace App\Http\Controllers;

use App\Enums\StatusOrder;
use App\Exports\LaporanPenjualanExport;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AnalisisPenjualanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanPenjualanController extends Controller
{
    public function index(Request $request, AnalisisPenjualanService $analisisPenjualanService)
    {
        abort_unless(auth()->user()->can('laporan.penjualan.view'), 403);

        $user = auth()->user();

        // Sprint 4.32 — dropdown preset periode. Kalau user pilih preset,
        // override `dari`/`sampai` dari preset itu (user masih bisa custom
        // via 2 input date terpisah → preset otomatis jadi "custom" di UI).
        $apPeriode = $request->get('ap_periode');
        if ($apPeriode && !$request->filled('dari') && !$request->filled('sampai')) {
            $resolved = AnalisisPenjualanService::presetPeriode($apPeriode);
            $dari = $resolved['dari'];
            $sampai = $resolved['sampai'];
        } else {
            // Default periode: bulan ini
            $dari = $request->dari
                ? Carbon::parse($request->dari)->startOfDay()
                : Carbon::now()->startOfMonth();
            $sampai = $request->sampai
                ? Carbon::parse($request->sampai)->endOfDay()
                : Carbon::now()->endOfDay();
        }

        $query = Order::withoutGlobalScopes()
            ->with(['cabang', 'pelanggan'])
            ->whereBetween('tanggal_order', [$dari->toDateString(), $sampai->toDateString()])
            ->penjualanSah();

        // Filter cabang
        $cabangId = $request->cabang_id;
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        } elseif (!$user->canAccessAllBranches()) {
            $activeCabangId = session('active_cabang_id') ?? $user->defaultCabangId();
            $query->where('cabang_id', $activeCabangId);
        }

        // Filter tipe order
        if ($request->tipe_order) {
            $query->where('tipe_order', $request->tipe_order);
        }

        // Search multi-field — TAMBAHAN, tidak mengganti filter cabang/tipe/tanggal di atas
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_order', 'like', "%{$search}%")
                  ->orWhere('nama_pelanggan', 'like', "%{$search}%")
                  ->orWhereHas('cabang', fn ($c) => $c->where('nama_cabang', 'like', "%{$search}%"));
            });
        }

        // Clone for stats sebelum paginate
        $statsQuery = clone $query;
        $totalOmzet = $statsQuery->sum('total_bayar');
        $totalTransaksi = (clone $query)->count();
        $rataRata = $totalTransaksi > 0 ? $totalOmzet / $totalTransaksi : 0;

        // Export Excel
        if ($request->export === 'excel') {
            $orders = $query->orderByDesc('tanggal_order')->get();
            $filename = 'Laporan-Penjualan-' . now()->format('Y-m-d') . '.xlsx';

            return Excel::download(new LaporanPenjualanExport($orders, $user->name), $filename);
        }

        // Export PDF
        if ($request->export === 'pdf') {
            $orders = $query->orderByDesc('tanggal_order')->get();
            $cabangNamaFilter = $cabangId ? Cabang::find($cabangId)?->nama_cabang : 'Semua Cabang';
            $filename = 'Laporan-Penjualan-' . now()->format('Y-m-d') . '.pdf';

            $pdf = Pdf::loadView('laporan.penjualan.pdf', [
                'orders' => $orders,
                'totalOmzet' => $totalOmzet,
                'judulLaporan' => 'Laporan Penjualan',
                'filterInfo' => [
                    'Periode' => $dari->format('d/m/Y') . ' — ' . $sampai->format('d/m/Y'),
                    'Cabang' => $cabangNamaFilter,
                ],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'portrait');

            return $pdf->download($filename);
        }

        $orders = $query->orderByDesc('tanggal_order')->paginate(20)->withQueryString();

        // Produk terlaris
        $produkTerlaris = OrderItem::withoutGlobalScopes()
            ->selectRaw('item_id, nama_item, SUM(qty) as total_qty, SUM(total_harga) as total_omzet')
            ->whereHas('order', function ($q) use ($dari, $sampai, $cabangId, $user) {
                $q->whereBetween('tanggal_order', [$dari->toDateString(), $sampai->toDateString()])
                  ->penjualanSah();
                if ($cabangId) {
                    $q->where('cabang_id', $cabangId);
                } elseif (!$user->canAccessAllBranches()) {
                    $activeCabangId = session('active_cabang_id') ?? $user->defaultCabangId();
                    $q->where('cabang_id', $activeCabangId);
                }
            })
            ->groupBy('item_id', 'nama_item')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // Ranking Kasir — mirror pola produk terlaris, join users (kasir_id -> users.id)
        $rankingKasir = collect();
        if ($user->can('laporan.ranking_kasir.view')) {
            $rankingKasirQuery = Order::withoutGlobalScopes()
                ->join('users', 'orders.kasir_id', '=', 'users.id')
                ->leftJoin('cabangs', 'orders.cabang_id', '=', 'cabangs.id')
                ->whereBetween('orders.tanggal_order', [$dari->toDateString(), $sampai->toDateString()])
                ->penjualanSah();
            if ($cabangId) {
                $rankingKasirQuery->where('orders.cabang_id', $cabangId);
            } elseif (!$user->canAccessAllBranches()) {
                $activeCabangId = session('active_cabang_id') ?? $user->defaultCabangId();
                $rankingKasirQuery->where('orders.cabang_id', $activeCabangId);
            }

            $rankingKasir = $rankingKasirQuery
                ->selectRaw('orders.kasir_id, users.name as nama_kasir, cabangs.nama_cabang, COUNT(orders.id) as jumlah_order, SUM(orders.total_bayar) as total_omzet')
                ->groupBy('orders.kasir_id', 'users.name', 'cabangs.nama_cabang')
                ->orderByDesc('total_omzet')
                ->limit(10)
                ->get();
        }

        // Grafik trend harian (max 31 hari)
        $grafikLabels = [];
        $grafikData = [];
        $diffDays = $dari->diffInDays($sampai);
        if ($diffDays <= 31) {
            // Harian
            for ($d = clone $dari; $d <= $sampai; $d->addDay()) {
                $tgl = $d->toDateString();
                $grafikLabels[] = $d->format('d/m');
                $grafikData[] = (float) Order::withoutGlobalScopes()
                    ->where('tanggal_order', $tgl)
                    ->penjualanSah()
                    ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
                    ->when(!$user->canAccessAllBranches() && !$cabangId, function ($q) use ($user) {
                        $activeCabangId = session('active_cabang_id') ?? $user->defaultCabangId();
                        $q->where('cabang_id', $activeCabangId);
                    })
                    ->sum('total_bayar');
            }
        } else {
            // Mingguan atau bulanan
            $current = clone $dari->startOfMonth();
            while ($current <= $sampai) {
                $bulan = $current->format('Y-m');
                $grafikLabels[] = $current->translatedFormat('M Y');
                $grafikData[] = (float) Order::withoutGlobalScopes()
                    ->where('tanggal_order', 'like', $bulan . '%')
                    ->penjualanSah()
                    ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
                    ->when(!$user->canAccessAllBranches() && !$cabangId, function ($q) use ($user) {
                        $activeCabangId = session('active_cabang_id') ?? $user->defaultCabangId();
                        $q->where('cabang_id', $activeCabangId);
                    })
                    ->sum('total_bayar');
                $current->addMonth();
            }
        }

        $cabangs = Cabang::aktif()->get();

        // Sprint 4.32 — Analisis Penjualan utk section "Analisis Periode" di halaman
        // Laporan Penjualan. Scope sama dgn filter cabang+periode halaman (konsisten
        // dgn KPI + tabel di bawah).
        $cabangIdAnalisis = $cabangId;
        if (!$cabangIdAnalisis && !$user->canAccessAllBranches()) {
            $cabangIdAnalisis = session('active_cabang_id') ?? $user->defaultCabangId();
        }
        $analisisPenjualan = $analisisPenjualanService->hitung($cabangIdAnalisis, $dari, $sampai);
        $analisisPenjualanMeta = [
            'periode_aktif' => $apPeriode ?: 'custom',
            'label_periode' => $dari->format('d/m/Y') . ' — ' . $sampai->format('d/m/Y'),
            'preset_list'   => AnalisisPenjualanService::daftarPreset(),
            'cabang_id'     => $cabangId,
        ];

        return view('laporan.penjualan.index', compact(
            'orders',
            'totalOmzet',
            'totalTransaksi',
            'rataRata',
            'produkTerlaris',
            'rankingKasir',
            'grafikLabels',
            'grafikData',
            'cabangs',
            'dari',
            'sampai',
            'cabangId',
            'analisisPenjualan',
            'analisisPenjualanMeta'
        ));
    }

}
