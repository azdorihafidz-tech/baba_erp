<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\BebanBreakdownService;
use App\Services\BusinessOverviewService;
use App\Services\CrossCheckValidator;
use App\Services\EvidenceBasedFindingsService;
use App\Services\LabaRugiFormalService;
use App\Services\NeracaService;
use App\Services\RangkumanFinalService;
use App\Services\SimulasiBalikModalService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Laporan Eksekutif Keuangan — 9 halaman (Sesi A: Cover Overview, Neraca,
 * Laba Rugi, BEP, Arus Kas, Drill-Down & Verifikasi. Sesi B: Evidence-Based
 * Findings, Rangkuman Final, Simulasi Balik Modal). Murni compose dari
 * service yang SUDAH ADA — nol kalkulasi baru di controller ini.
 */
class LaporanEksekutifController extends Controller
{
    public function __construct(
        private BusinessOverviewService $overviewService,
        private BebanBreakdownService $bebanBreakdownService,
        private CrossCheckValidator $crossCheckValidator,
        private NeracaService $neracaService,
        private LabaRugiFormalService $labaRugiService,
        private EvidenceBasedFindingsService $findingsService,
        private RangkumanFinalService $rangkumanFinalService,
        private SimulasiBalikModalService $simulasiBalikModalService,
    ) {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.eksekutif.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        $tanggal = $request->filled('tanggal') ? Carbon::parse($request->tanggal) : Carbon::now();
        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        if (!$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        return view('laporan.eksekutif.index', compact('cabangs', 'cabangId', 'tanggal', 'cabangNama'));
    }

    public function generate(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.eksekutif.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.eksekutif.pdf.master', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.eksekutif.export'), 403);

        $data = array_merge($this->buildData($request), ['isPdf' => true]);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('laporan.eksekutif.pdf.master', $data)
            ->setPaper('a4', 'portrait');

        // PENTING (dokumen 6 halaman, temuan baru sesi ini): page_text() HARUS
        // dipanggil SETELAH render() eksplisit, BUKAN sebelumnya — kalau
        // dipanggil sebelum render() (pola yang dipakai Fase 2/3 untuk PDF
        // 1-halaman, tidak pernah ketahuan bug-nya), {PAGE_COUNT} ke-bake jadi 1
        // (nilai default sebelum layout selesai) dan overlay cuma muncul di
        // halaman 1 — dibuktikan lewat test eksplisit get_page_count() sebelum
        // vs sesudah render(). Lihat CLAUDE.md Rule #49.
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(270, 815, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 8, [0.5, 0.5, 0.5]);

        $filename = 'Laporan_Eksekutif_' . ($data['cabangNama'] ?? 'Konsolidasi') . '_' . $data['tanggal']->format('Ymd') . '.pdf';

        return $pdf->download($filename);
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

        $mulaiBulan = $tanggal->copy()->startOfMonth();
        $akhirTanggal = $tanggal->copy()->endOfDay();

        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        // Halaman 1: Cover Overview
        $overview = $this->overviewService->getCoverOverview($tanggal, $cabangId);

        // Halaman 2: Neraca — REUSE array penuh yang sudah di-embed di $overview,
        // tidak panggil NeracaService lagi.
        $neraca = $overview['neraca'];

        // Halaman 3: Laba Rugi — reuse langsung, panggilan baru (bulan berjalan,
        // beda kebutuhan dari "Laba Ditahan kumulatif" yang dipakai Neraca).
        $labaRugi = $this->labaRugiService->hitungLabaRugi($mulaiBulan, $akhirTanggal, $cabangId);

        // Halaman 4: BEP — REUSE dari $overview (sudah dilengkapi kekurangan/estimasi).
        $bep = $overview['bep_vs_realisasi'];

        // Halaman 5: Arus Kas — reuse BusinessOverviewService + trend dari analytics.
        $arusKas = $this->overviewService->getArusKasSummary($mulaiBulan, $akhirTanggal, $cabangId);

        // Halaman 6: Drill-Down & Verifikasi
        $bebanRingkasan = $this->bebanBreakdownService->getRingkasanSemuaKategori($mulaiBulan, $akhirTanggal, $cabangId);
        $crossCheck = $this->crossCheckValidator->validateConsistency($tanggal, $cabangId);

        // Halaman 7: Evidence-Based Findings
        $findings = $this->findingsService->getFindings($tanggal, $cabangId);

        // Halaman 8: Rangkuman Final
        $rangkumanFinal = $this->rangkumanFinalService->getRangkumanFinal($tanggal, $cabangId);

        // Halaman 9: Simulasi 5 Skenario Balik Modal — volume Sedang/Optimis
        // & persentase pangkas beban BISA di-override lewat request (Owner
        // adjust manual di form sebelum generate), default null -> service
        // pakai 1x/2x BEP unit harian (lihat SimulasiBalikModalService).
        $volumeSedang = $request->filled('volume_sedang') ? (float) $request->volume_sedang : null;
        $volumeOptimis = $request->filled('volume_optimis') ? (float) $request->volume_optimis : null;
        $pangkasBebanPersen = $request->filled('pangkas_beban_persen') ? (float) $request->pangkas_beban_persen : 20.0;
        $simulasi = $this->simulasiBalikModalService->simulasikanBalikModal(
            $tanggal, $cabangId, $volumeSedang, $volumeOptimis, $pangkasBebanPersen
        );

        return compact(
            'tanggal', 'cabangs', 'cabangId', 'cabangNama',
            'mulaiBulan', 'akhirTanggal',
            'overview', 'neraca', 'labaRugi', 'bep', 'arusKas',
            'bebanRingkasan', 'crossCheck',
            'findings', 'rangkumanFinal', 'simulasi'
        );
    }
}
