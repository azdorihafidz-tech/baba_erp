<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\JamRamaiService;
use App\Exports\LaporanJamRamaiExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan Analisa Jam Ramai (Peak Hours) — menu BARU. Murni compose dari
 * JamRamaiService (Orders-only, lihat catatan class-level di service itu
 * untuk alasan transaksi_keuangans TIDAK dipakai). Read-only.
 */
class LaporanJamRamaiController extends Controller
{
    public function __construct(private JamRamaiService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.jam_ramai.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        $mulai = $request->filled('mulai') ? Carbon::parse($request->mulai) : Carbon::now()->startOfMonth();
        $akhir = $request->filled('akhir') ? Carbon::parse($request->akhir)->endOfDay() : Carbon::now()->endOfDay();

        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        if (!$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        $analisa = $this->service->getAnalisaJamRamai($mulai, $akhir, $cabangId);

        return view('laporan.jam-ramai.index', compact('cabangs', 'cabangId', 'cabangNama', 'mulai', 'akhir', 'analisa'));
    }

    private function buildAnalisa(Request $request): array
    {
        $user = auth()->user();

        $mulai = $request->filled('mulai') ? Carbon::parse($request->mulai) : Carbon::now()->startOfMonth();
        $akhir = $request->filled('akhir') ? Carbon::parse($request->akhir)->endOfDay() : Carbon::now()->endOfDay();

        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        if (!$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';
        $analisa = $this->service->getAnalisaJamRamai($mulai, $akhir, $cabangId);

        return compact('mulai', 'akhir', 'cabangNama', 'analisa');
    }

    public function exportExcel(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.jam_ramai.export'), 403);

        $data = $this->buildAnalisa($request);

        return Excel::download(
            new LaporanJamRamaiExport($data['analisa'], auth()->user()->name),
            'laporan-jam-ramai-' . $data['mulai']->format('Ymd') . '-' . $data['akhir']->format('Ymd') . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.jam_ramai.export'), 403);

        $data = $this->buildAnalisa($request);
        $filterInfo = [
            'Periode' => $data['mulai']->format('d/m/Y') . ' s.d ' . $data['akhir']->format('d/m/Y'),
            'Cabang' => $data['cabangNama'],
            'Total Transaksi' => $data['analisa']['total_transaksi'],
        ];
        $pdf = Pdf::loadView('laporan.pdf.jam-ramai', [
            'analisa' => $data['analisa'],
            'judulLaporan' => 'Laporan Analisa Jam Ramai',
            'filterInfo' => $filterInfo,
            'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
        ])->setPaper('a4', 'portrait');

        return $pdf->download('laporan-jam-ramai-' . $data['mulai']->format('Ymd') . '-' . $data['akhir']->format('Ymd') . '.pdf');
    }
}
