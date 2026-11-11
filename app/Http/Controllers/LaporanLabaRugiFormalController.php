<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\LabaRugiFormalService;
use App\Exports\LaporanLabaRugiFormalExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan Laba Rugi Formal berstandar SAK ETAP (dikelompokkan per Chart of
 * Accounts) — menu BARU, terpisah dari Laporan Laba Rugi existing (analisis
 * gross profit per item/kategori/jenis olahan/order) yang TIDAK disentuh
 * sama sekali. Murni READ dari LabaRugiFormalService.
 */
class LaporanLabaRugiFormalController extends Controller
{
    public function __construct(private LabaRugiFormalService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.laba_rugi_formal.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.laba-rugi-formal.index', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.laba_rugi_formal.export'), 403);

        $data = $this->buildData($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('laporan.pdf.laba-rugi-formal', $data)
            ->setPaper('a4', 'portrait');

        // Patch preventif (lihat CLAUDE.md Rule #48): page_text() WAJIB
        // dipanggil SETELAH render() eksplisit, bukan sebelumnya, supaya
        // {PAGE_COUNT} akurat kalau dokumen ini suatu saat overflow ke
        // halaman ke-2+. Untuk kasus 1-halaman saat ini, hasilnya identik
        // (diverifikasi via regression test).
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(270, 815, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 8, [0.5, 0.5, 0.5]);

        $filename = 'LabaRugiFormal_' . ($data['cabangNama'] ?? 'Konsolidasi') . '_'
            . $data['mulai']->format('Ymd') . '-' . $data['akhir']->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.laba_rugi_formal.export'), 403);

        $data = $this->buildData($request);

        return Excel::download(
            new LaporanLabaRugiFormalExport($data['labaRugi'], $data['cabangNama'], auth()->user()->name),
            'laporan-laba-rugi-formal-' . $data['mulai']->format('Ymd') . '-' . $data['akhir']->format('Ymd') . '.xlsx'
        );
    }

    private function buildData(Request $request): array
    {
        $user = auth()->user();
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        $mulai = $request->filled('mulai') ? Carbon::parse($request->mulai) : Carbon::now()->startOfMonth();
        $akhir = $request->filled('akhir') ? Carbon::parse($request->akhir)->endOfDay() : Carbon::now()->endOfDay();

        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        if (!$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $labaRugi = $this->service->hitungLabaRugi($mulai, $akhir, $cabangId);
        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        return compact('labaRugi', 'cabangs', 'cabangId', 'mulai', 'akhir', 'cabangNama');
    }
}
