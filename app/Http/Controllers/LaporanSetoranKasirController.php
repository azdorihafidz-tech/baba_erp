<?php

namespace App\Http\Controllers;

use App\Exports\LaporanSetoranKasirExport;
use App\Models\Cabang;
use App\Models\Setoran;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Tahap 6 D'mentai — Laporan Setoran Kasir (BEDA dari LaporanSetoranController
 * existing yang melaporkan Transfer Dana/SETOR-OUT generik).
 */
class LaporanSetoranKasirController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_kasir.view'), 403);

        $user = auth()->user();
        $dari = $request->filled('dari') ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai) : Carbon::now();

        $query = Setoran::with(['cabang', 'disubmitOleh', 'disetujuiOleh'])
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);

        $cabangId = $request->cabang_id ?: null;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $stats = [
            'total_sistem' => (clone $query)->sum('total_penjualan_sistem'),
            'total_disetor' => (clone $query)->sum('total_disetor'),
            'total_selisih' => (clone $query)->sum('selisih'),
            'jumlah' => (clone $query)->count(),
        ];

        $setorans = $query->orderByDesc('tanggal')->paginate(20)->withQueryString();
        $cabangOptions = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        return view('laporan.setoran-kasir', compact('setorans', 'stats', 'cabangOptions', 'dari', 'sampai'));
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_kasir.export'), 403);

        $user = auth()->user();
        $dari = $request->filled('dari') ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai) : Carbon::now();

        $query = Setoran::with('cabang')->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);
        $cabangId = $request->cabang_id ?: (!$user->canAccessAllBranches() ? (session('active_cabang_id') ?? $user->defaultCabangId()) : null);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->orderByDesc('tanggal')->get();

        if ($request->format === 'pdf') {
            $stats = [
                'total_sistem' => $rows->sum('total_penjualan_sistem'),
                'total_disetor' => $rows->sum('total_disetor'),
                'total_selisih' => $rows->sum('selisih'),
            ];
            $cabangNamaFilter = $cabangId ? Cabang::find($cabangId)?->nama_cabang : 'Semua Cabang';
            $filename = 'Laporan-Setoran-Kasir-' . now()->format('Y-m-d') . '.pdf';

            $pdf = Pdf::loadView('laporan.setoran-kasir-pdf', [
                'setorans' => $rows,
                'stats' => $stats,
                'judulLaporan' => 'Laporan Setoran Kasir',
                'filterInfo' => [
                    'Periode' => $dari->format('d/m/Y') . ' — ' . $sampai->format('d/m/Y'),
                    'Cabang' => $cabangNamaFilter,
                    'Status' => $request->filled('status') ? ucfirst($request->status) : 'Semua Status',
                ],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'portrait');

            return $pdf->download($filename);
        }

        $filename = 'Laporan-Setoran-Kasir-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new LaporanSetoranKasirExport($rows, $user->name), $filename);
    }
}
