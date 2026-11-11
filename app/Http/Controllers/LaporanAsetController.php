<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use App\Models\AssetMaintenance;
use App\Models\AssetMutation;
use App\Models\Cabang;
use App\Exports\LaporanAsetExport;
use App\Exports\LaporanPenyusutanExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanAsetController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.aset.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();
        $kategories = AssetCategory::orderBy('nama_kategori')->get();

        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = Asset::with(['kategori', 'lokasi'])
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->when($request->kategori_id, fn($q) => $q->where('kategori_aset_id', $request->kategori_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        $totalAset = (clone $query)->count();
        $totalNilaiBuku = (clone $query)->sum('nilai_buku');
        $totalHargaPerolehan = (clone $query)->sum('harga_perolehan');
        $totalPenyusutan = $totalHargaPerolehan - $totalNilaiBuku;

        if ($request->export === 'excel') {
            $assets = $query->orderBy('nama_aset')->get();
            return Excel::download(new LaporanAsetExport($assets, auth()->user()->name), 'laporan-aset-' . now()->format('Y-m-d') . '.xlsx');
        }

        if ($request->export === 'pdf') {
            $assets = $query->orderBy('nama_aset')->get();
            $filterInfo = [
                'Kategori' => optional($kategories->firstWhere('id', $request->kategori_id))->nama_kategori ?? 'Semua Kategori',
                'Status' => $request->status ?: 'Semua Status',
                'Total Aset' => $assets->count(),
            ];
            $pdf = Pdf::loadView('laporan.pdf.aset', [
                'assets' => $assets,
                'judulLaporan' => 'Laporan Aset',
                'filterInfo' => $filterInfo,
                'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'landscape');
            return $pdf->download('laporan-aset-' . now()->format('Y-m-d') . '.pdf');
        }

        $assets = $query->orderBy('nama_aset')->paginate(30)->withQueryString();

        return view('laporan.aset.index', compact(
            'assets', 'cabangs', 'kategories', 'lokasiId',
            'totalAset', 'totalNilaiBuku', 'totalHargaPerolehan', 'totalPenyusutan'
        ));
    }

    public function penyusutan(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.aset.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $periode = $request->periode ?? Carbon::now()->format('Y-m');

        $query = AssetDepreciation::with(['asset', 'asset.kategori', 'asset.lokasi'])
            ->where('periode', $periode)
            ->when($lokasiId, function ($q) use ($lokasiId) {
                $q->whereHas('asset', fn($q2) => $q2->where('lokasi_id', $lokasiId));
            });

        $totalPenyusutanPeriode = (clone $query)->sum('jumlah_penyusutan');
        $totalAsetTerdepresiasi = (clone $query)->count();

        if ($request->export === 'excel') {
            $depreciations = $query->get();
            return Excel::download(new LaporanPenyusutanExport($depreciations, auth()->user()->name), 'laporan-penyusutan-' . $periode . '.xlsx');
        }

        if ($request->export === 'pdf') {
            $depreciations = $query->get();
            $filterInfo = [
                'Periode' => $periode,
                'Total Aset Terdepresiasi' => $totalAsetTerdepresiasi,
                'Total Penyusutan' => 'Rp ' . number_format($totalPenyusutanPeriode, 0, ',', '.'),
            ];
            $pdf = Pdf::loadView('laporan.pdf.aset-penyusutan', [
                'depreciations' => $depreciations,
                'judulLaporan' => 'Laporan Penyusutan Aset',
                'filterInfo' => $filterInfo,
                'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'landscape');
            return $pdf->download('laporan-penyusutan-' . $periode . '.pdf');
        }

        $depreciations = $query->paginate(30)->withQueryString();

        return view('laporan.aset.penyusutan', compact(
            'depreciations', 'cabangs', 'lokasiId', 'periode',
            'totalPenyusutanPeriode', 'totalAsetTerdepresiasi'
        ));
    }

    public function maintenance(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.aset.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $dari = $request->dari ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->sampai ? Carbon::parse($request->sampai) : Carbon::now();
        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = AssetMaintenance::with(['asset', 'asset.lokasi'])
            ->whereBetween('tanggal_maintenance', [$dari->toDateString(), $sampai->toDateString()])
            ->when($lokasiId, function ($q) use ($lokasiId) {
                $q->whereHas('asset', fn($q2) => $q2->where('lokasi_id', $lokasiId));
            });

        $totalBiaya = (clone $query)->sum('biaya');
        $totalKejadian = (clone $query)->count();

        $maintenances = $query->orderByDesc('tanggal_maintenance')->paginate(30)->withQueryString();

        return view('laporan.aset.index', compact(
            'cabangs', 'lokasiId', 'dari', 'sampai', 'totalBiaya', 'totalKejadian'
        ))->with('maintenances', $maintenances)->with('viewMode', 'maintenance');
    }

    public function mutasi(Request $request)
    {
        return $this->index($request);
    }

}
