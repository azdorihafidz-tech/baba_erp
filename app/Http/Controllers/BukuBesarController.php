<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\BukuBesarService;
use App\Exports\LaporanBukuBesarExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Buku Besar (General Ledger) per akun COA — menu BARU (Fase 3), scope
 * terbatas ke akun Pendapatan/HPP/Beban (lihat catatan class-level
 * BukuBesarService). Murni READ.
 */
class BukuBesarController extends Controller
{
    public function __construct(private BukuBesarService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.buku_besar.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.buku-besar.index', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.buku_besar.export'), 403);

        $request->validate(['kode_akun' => 'required|string']);

        $data = $this->buildData($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('laporan.pdf.buku-besar', $data)
            ->setPaper('a4', 'portrait');

        // Patch preventif (lihat CLAUDE.md Rule #48): page_text() WAJIB
        // dipanggil SETELAH render() eksplisit, bukan sebelumnya, supaya
        // {PAGE_COUNT} akurat kalau dokumen ini overflow ke halaman ke-2+
        // (Buku Besar adalah kandidat PALING mungkin di antara ke-4 PDF
        // lama, karena daftar transaksinya bisa sangat panjang). Untuk
        // kasus 1-halaman, hasilnya identik (diverifikasi via regression).
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(270, 815, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 8, [0.5, 0.5, 0.5]);

        $filename = 'BukuBesar_' . ($data['ledger']['akun']->kode ?? 'akun') . '_'
            . $data['mulai']->format('Ymd') . '-' . $data['akhir']->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.buku_besar.export'), 403);

        $request->validate(['kode_akun' => 'required|string']);

        $data = $this->buildData($request);

        return Excel::download(
            new LaporanBukuBesarExport($data['ledger'], $data['cabangNama'], auth()->user()->name),
            'laporan-buku-besar-' . ($data['ledger']['akun']->kode ?? 'akun') . '-' . $data['mulai']->format('Ymd') . '-' . $data['akhir']->format('Ymd') . '.xlsx'
        );
    }

    private function buildData(Request $request): array
    {
        $user = auth()->user();
        $cabangs = Cabang::aktif()->cabangSaja()->get();
        $akunTersedia = $this->service->getAkunTersedia();

        $mulai = $request->filled('mulai') ? Carbon::parse($request->mulai) : Carbon::now()->startOfMonth();
        $akhir = $request->filled('akhir') ? Carbon::parse($request->akhir)->endOfDay() : Carbon::now()->endOfDay();

        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        if (!$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $kodeAkun = $request->filled('kode_akun') ? $request->kode_akun : $akunTersedia->first()?->kode;

        $ledger = $kodeAkun
            ? $this->service->getTransaksiPerAkun($kodeAkun, $mulai, $akhir, $cabangId)
            : null;

        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        return compact('ledger', 'akunTersedia', 'cabangs', 'cabangId', 'mulai', 'akhir', 'cabangNama', 'kodeAkun');
    }
}
