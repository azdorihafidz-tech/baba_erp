<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\TransaksiKeuangan;
use App\Exports\LaporanAuditBuktiExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LaporanAuditBuktiController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.audit_bukti.view'), 403);

        $user    = auth()->user();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        $dari      = $request->filled('dari')      ? Carbon::parse($request->dari)->startOfDay()   : Carbon::now()->startOfMonth();
        $sampai    = $request->filled('sampai')    ? Carbon::parse($request->sampai)->endOfDay()   : Carbon::now()->endOfDay();
        $threshold = (int) ($request->threshold ?? 500000);
        $hanyaTanpaBukti = $request->boolean('tanpa_bukti', false);

        $cabangId = $request->cabang_id ?: null;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $base = fn() => TransaksiKeuangan::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tipe', 'pengeluaran')
            ->where('jumlah', '>', $threshold)
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId));

        $totalAtas       = $base()->count();
        $sudahUpload     = $base()->whereNotNull('bukti_path')->count();
        $belumUpload     = $base()->whereNull('bukti_path')->count();
        $totalNominal    = $base()->sum('jumlah');
        $pctUpload       = $totalAtas > 0 ? round(($sudahUpload / $totalAtas) * 100, 1) : 0;

        $query = $base()
            ->with(['cabang:id,nama_cabang', 'kategoriDinamis:id,nama'])
            ->when($hanyaTanpaBukti, fn($q) => $q->whereNull('bukti_path'))
            ->orderByDesc('jumlah');

        if ($request->export === 'excel') {
            return Excel::download(
                new LaporanAuditBuktiExport($query->get(), $threshold, auth()->user()->name),
                'laporan-audit-bukti-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xlsx'
            );
        }

        if ($request->export === 'pdf') {
            $transaksisPdf = $query->get();
            $filterInfo = [
                'Periode' => $dari->format('d/m/Y') . ' s.d ' . $sampai->format('d/m/Y'),
                'Threshold' => 'Rp ' . number_format($threshold, 0, ',', '.'),
                'Cabang' => optional($cabangs->firstWhere('id', $cabangId))->nama_cabang ?? 'Semua Cabang',
            ];
            $pdf = Pdf::loadView('laporan.pdf.audit-bukti', [
                'transaksis' => $transaksisPdf,
                'judulLaporan' => 'Laporan Audit Bukti Transaksi',
                'filterInfo' => $filterInfo,
                'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'landscape');
            return $pdf->download('laporan-audit-bukti-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.pdf');
        }

        $transaksis = $query->paginate(25)->withQueryString();

        return view('laporan.audit-bukti', compact(
            'transaksis', 'cabangs', 'cabangId', 'dari', 'sampai', 'threshold', 'hanyaTanpaBukti',
            'totalAtas', 'sudahUpload', 'belumUpload', 'totalNominal', 'pctUpload'
        ));
    }

}
