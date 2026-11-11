<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\LaporanPerlengkapanService;
use App\Exports\LaporanPerlengkapanExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Fase 5 — Modul Perlengkapan (Rule #66). Controller BARU.
 */
class LaporanPerlengkapanController extends Controller
{
    public function __construct(private LaporanPerlengkapanService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.perlengkapan.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.perlengkapan.index', $data);
    }

    public function print(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.perlengkapan.print'), 403);

        $data = $this->buildData($request);

        return view('laporan.perlengkapan.print', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.perlengkapan.export'), 403);

        $data = $this->buildData($request);

        return Excel::download(
            new LaporanPerlengkapanExport($data['detail'], $data['ringkasan'], auth()->user()->name),
            'laporan-pemakaian-perlengkapan-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.perlengkapan.export'), 403);

        $data = $this->buildData($request);

        $filterInfo = [
            'Periode' => $data['mulai']->format('d/m/Y') . ' s.d ' . $data['akhir']->format('d/m/Y'),
            'Cabang' => optional($data['cabangs']->firstWhere('id', $data['cabangId']))->nama_cabang ?? 'Semua Cabang',
        ];
        $pdf = Pdf::loadView('laporan.pdf.perlengkapan', [
            'ringkasan' => $data['ringkasan'],
            'detail' => $data['detail'],
            'judulLaporan' => 'Laporan Pemakaian Perlengkapan',
            'filterInfo' => $filterInfo,
            'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
        ])->setPaper('a4', 'portrait');

        return $pdf->download('laporan-pemakaian-perlengkapan-' . now()->format('Ymd-His') . '.pdf');
    }

    private function buildData(Request $request): array
    {
        $mulai = $request->filled('dari') ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $akhir = $request->filled('sampai') ? Carbon::parse($request->sampai) : Carbon::now()->endOfMonth();
        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;

        $user = auth()->user();
        $cabangs = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        return [
            'mulai'     => $mulai,
            'akhir'     => $akhir,
            'cabangId'  => $cabangId,
            'cabangs'   => $cabangs,
            'ringkasan' => $this->service->getRingkasan($mulai, $akhir, $cabangId),
            'breakdown' => $this->service->getBreakdownPerItem($mulai, $akhir, $cabangId),
            'detail'    => $this->service->getDetailPemakaian($mulai, $akhir, $cabangId),
            'trend'     => $this->service->getTrendBulanan($cabangId),
        ];
    }
}
