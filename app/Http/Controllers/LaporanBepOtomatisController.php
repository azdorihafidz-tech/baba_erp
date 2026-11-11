<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\BepOtomatisService;
use App\Exports\LaporanBepOtomatisExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan BEP Otomatis — menu BARU yang extend menu BEP existing
 * (BepController/LaporanBepController, TIDAK disentuh sama sekali). Beda
 * dari alur lama yang butuh setup manual BepSetting/BepProduct per cabang+
 * periode, laporan ini hitung BEP langsung dari transaksi real (tipe_biaya
 * COA + HPP FIFO jasa giling) tanpa setup apapun. Murni READ.
 */
class LaporanBepOtomatisController extends Controller
{
    public function __construct(private BepOtomatisService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.bep_otomatis.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.bep-otomatis.index', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.bep_otomatis.export'), 403);

        $data = $this->buildData($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('laporan.pdf.bep-otomatis', $data)
            ->setPaper('a4', 'portrait');

        // Patch preventif (lihat CLAUDE.md Rule #48): page_text() WAJIB
        // dipanggil SETELAH render() eksplisit, bukan sebelumnya, supaya
        // {PAGE_COUNT} akurat kalau dokumen ini suatu saat overflow ke
        // halaman ke-2+. Untuk kasus 1-halaman saat ini, hasilnya identik
        // (diverifikasi via regression test).
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(270, 815, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 8, [0.5, 0.5, 0.5]);

        $filename = 'BEP_Otomatis_' . ($data['cabangNama'] ?? 'Konsolidasi') . '_'
            . $data['mulai']->format('Ymd') . '-' . $data['akhir']->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.bep_otomatis.export'), 403);

        $data = $this->buildData($request);

        return Excel::download(
            new LaporanBepOtomatisExport($data['bep'], $data['cabangNama'], auth()->user()->name),
            'laporan-bep-otomatis-' . $data['mulai']->format('Ymd') . '-' . $data['akhir']->format('Ymd') . '.xlsx'
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

        $bep = $this->service->hitungBepOtomatis($mulai, $akhir, $cabangId);
        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        // Data untuk Break-Even Chart (Chart.js): sumbu-X volume 0..2x BEP unit
        $chartData = $this->buildChartData($bep);

        return compact('bep', 'cabangs', 'cabangId', 'mulai', 'akhir', 'cabangNama', 'chartData');
    }

    private function buildChartData(array $bep): array
    {
        if (!$bep['bisa_bep'] || $bep['bep_unit'] === null || $bep['bep_unit'] <= 0) {
            return ['labels' => [], 'biayaTetap' => [], 'biayaTotal' => [], 'pendapatan' => []];
        }

        $maxUnit = max($bep['bep_unit'] * 2, $bep['volume_aktual'] * 1.2, 1);
        $step = max(1, $maxUnit / 10);

        $labels = $biayaTetap = $biayaTotal = $pendapatan = [];
        for ($u = 0; $u <= $maxUnit; $u += $step) {
            $labels[] = round($u, 1);
            $biayaTetap[] = round($bep['biaya_tetap'], 2);
            $biayaTotal[] = round($bep['biaya_tetap'] + ($bep['biaya_variabel_per_unit'] * $u), 2);
            $pendapatan[] = round($bep['harga_jual_per_unit'] * $u, 2);
        }

        return compact('labels', 'biayaTetap', 'biayaTotal', 'pendapatan');
    }
}
