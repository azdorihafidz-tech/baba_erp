<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\NeracaSetting;
use App\Services\NeracaService;
use App\Exports\LaporanNeracaExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan Neraca (Balance Sheet) formal berstandar SAK ETAP — menu BARU,
 * terpisah dari Laporan Laba Rugi/Penjualan/Konsumsi Bahan existing yang
 * TIDAK disentuh sama sekali. Murni READ dari NeracaService.
 */
class LaporanNeracaController extends Controller
{
    public function __construct(private NeracaService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.neraca.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.neraca.index', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.neraca.export'), 403);

        $data = $this->buildData($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('laporan.pdf.neraca', $data)
            ->setPaper('a4', 'portrait');

        // Patch preventif (lihat CLAUDE.md Rule #48): page_text() WAJIB
        // dipanggil SETELAH render() eksplisit, bukan sebelumnya, supaya
        // {PAGE_COUNT} akurat kalau dokumen ini suatu saat overflow ke
        // halaman ke-2+. Untuk kasus 1-halaman saat ini, hasilnya identik
        // (diverifikasi via regression test).
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(270, 815, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 8, [0.5, 0.5, 0.5]);

        $filename = 'Neraca_' . ($data['cabangNama'] ?? 'Konsolidasi') . '_' . $data['tanggal']->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.neraca.export'), 403);

        $data = $this->buildData($request);

        return Excel::download(
            new LaporanNeracaExport($data['neraca'], $data['cabangNama'], auth()->user()->name),
            'laporan-neraca-' . $data['tanggal']->format('Ymd') . '.xlsx'
        );
    }

    public function updateSetting(Request $request)
    {
        $data = $request->validate([
            'modal_owner' => 'required|numeric|min:0',
            'catatan' => 'nullable|string|max:1000',
        ]);

        $setting = NeracaSetting::getSetting();
        $setting->update($data);

        return back()->with('success', 'Modal Owner berhasil diperbarui.');
    }

    private function buildData(Request $request): array
    {
        $user = auth()->user();
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        $tanggal = $request->filled('tanggal') ? Carbon::parse($request->tanggal) : Carbon::now();

        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        if (!$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $neraca = $this->service->hitungNeraca($tanggal, $cabangId);
        $cabangNama = $neraca['cabang']?->nama_cabang ?? 'Semua Cabang (Konsolidasi)';

        return compact('neraca', 'cabangs', 'cabangId', 'tanggal', 'cabangNama');
    }
}
