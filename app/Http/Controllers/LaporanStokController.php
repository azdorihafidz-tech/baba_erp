<?php

namespace App\Http\Controllers;

use App\Exports\LaporanStokExport;
use App\Exports\LaporanStokMinimumExport;
use App\Exports\LaporanStokPergerakanExport;
use App\Models\Cabang;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanStokController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.stok.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        // Sprint 4.34 — exclude produk_jual/produk_tambahan (resep based).
        $query = Stock::with(['item', 'item.category', 'lokasi'])
            ->whereHas('item', fn($q) => $q->whereIn('tipe', \App\Models\Item::TIPE_DAPAT_DIBELI));
        if ($lokasiId) {
            $query->where('lokasi_id', $lokasiId);
        }
        if ($request->kategori_id) {
            $query->whereHas('item', fn($q) => $q->where('item_category_id', $request->kategori_id));
        }

        // Export
        if ($request->export === 'excel') {
            $stocks = $query->get();
            $filename = 'Laporan-Stok-' . now()->format('Y-m-d') . '.xlsx';

            return Excel::download(new LaporanStokExport($stocks, $user->name), $filename);
        }
        if ($request->export === 'pdf') {
            $stocks = $query->get();
            $cabangNamaFilter = $lokasiId ? (Cabang::find($lokasiId)?->nama_cabang ?? '-') : 'Semua Cabang';
            $filename = 'Laporan-Stok-' . now()->format('Y-m-d') . '.pdf';

            $pdf = Pdf::loadView('laporan.stok.pdf-index', [
                'stocks' => $stocks,
                'judulLaporan' => 'Laporan Stok',
                'filterInfo' => ['Cabang' => $cabangNamaFilter],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'portrait');

            return $pdf->download($filename);
        }

        $stocks = $query->paginate(30)->withQueryString();

        // Statistik
        $totalItem = Stock::when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))->count();
        $stokRendah = Stock::whereColumn('qty', '<=', 'qty_minimum')->where('qty_minimum', '>', 0)
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->count();
        $stokKosong = Stock::where('qty', '<=', 0)
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->count();

        return view('laporan.stok.index', compact(
            'stocks', 'cabangs', 'lokasiId', 'totalItem', 'stokRendah', 'stokKosong'
        ));
    }

    public function pergerakan(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.stok.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $dari = $request->dari ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->sampai ? Carbon::parse($request->sampai) : Carbon::now();
        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = StockMovement::with(['item', 'lokasiAsal', 'lokasiTujuan', 'user'])
            ->whereBetween('created_at', [$dari->startOfDay(), $sampai->endOfDay()]);

        if ($lokasiId) {
            $query->where(function ($q) use ($lokasiId) {
                $q->where('lokasi_asal_id', $lokasiId)
                  ->orWhere('lokasi_tujuan_id', $lokasiId);
            });
        }

        if ($request->tipe) {
            $query->where('tipe', $request->tipe);
        }

        $totalMasuk = (clone $query)->where('tipe', 'masuk')->sum('qty');
        $totalKeluar = (clone $query)->where('tipe', 'keluar')->sum('qty');
        $totalTransfer = (clone $query)->where('tipe', 'transfer')->count();

        if ($request->export === 'excel') {
            $movements = $query->orderByDesc('created_at')->get();
            $filename = 'Laporan-Pergerakan-Stok-' . now()->format('Y-m-d') . '.xlsx';

            return Excel::download(new LaporanStokPergerakanExport($movements, $user->name), $filename);
        }
        if ($request->export === 'pdf') {
            $movements = $query->orderByDesc('created_at')->get();
            $cabangNamaFilter = $lokasiId ? (Cabang::find($lokasiId)?->nama_cabang ?? '-') : 'Semua Cabang';
            $filename = 'Laporan-Pergerakan-Stok-' . now()->format('Y-m-d') . '.pdf';

            $pdf = Pdf::loadView('laporan.stok.pdf-pergerakan', [
                'movements' => $movements,
                'judulLaporan' => 'Laporan Pergerakan Stok',
                'filterInfo' => [
                    'Periode' => $dari->format('d/m/Y') . ' — ' . $sampai->format('d/m/Y'),
                    'Cabang' => $cabangNamaFilter,
                ],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename);
        }

        $movements = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        return view('laporan.stok.pergerakan', compact(
            'movements', 'cabangs', 'lokasiId', 'dari', 'sampai',
            'totalMasuk', 'totalKeluar', 'totalTransfer'
        ));
    }

    public function minimum(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.stok.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $baseQuery = Stock::with(['item', 'item.category', 'lokasi'])
            ->whereHas('item', fn($q) => $q->whereIn('tipe', \App\Models\Item::TIPE_DAPAT_DIBELI))
            ->whereColumn('qty', '<=', 'qty_minimum')
            ->where('qty_minimum', '>', 0)
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->orderByRaw('qty / NULLIF(qty_minimum, 0) ASC');

        if ($request->export === 'excel') {
            $stocks = (clone $baseQuery)->get();
            $filename = 'Laporan-Stok-Minimum-' . now()->format('Y-m-d') . '.xlsx';

            return Excel::download(new LaporanStokMinimumExport($stocks, $user->name), $filename);
        }
        if ($request->export === 'pdf') {
            $stocks = (clone $baseQuery)->get();
            $cabangNamaFilter = $lokasiId ? (Cabang::find($lokasiId)?->nama_cabang ?? '-') : 'Semua Cabang';
            $filename = 'Laporan-Stok-Minimum-' . now()->format('Y-m-d') . '.pdf';

            $pdf = Pdf::loadView('laporan.stok.pdf-minimum', [
                'stocks' => $stocks,
                'judulLaporan' => 'Laporan Stok Minimum',
                'filterInfo' => ['Cabang' => $cabangNamaFilter],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'portrait');

            return $pdf->download($filename);
        }

        $stocks = $baseQuery->paginate(30)->withQueryString();

        $totalStokRendah = Stock::whereColumn('qty', '<=', 'qty_minimum')->where('qty_minimum', '>', 0)
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->count();

        $totalKosong = Stock::where('qty', '<=', 0)
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->count();

        return view('laporan.stok.minimum', compact(
            'stocks', 'cabangs', 'lokasiId', 'totalStokRendah', 'totalKosong'
        ));
    }

}
