<?php

namespace App\Http\Controllers;

use App\Exports\LaporanAbsensiExport;
use App\Exports\LaporanEvaluasiExport;
use App\Exports\LaporanPenggajianExport;
use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\Karyawan;
use App\Models\Penggajian;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanHRController extends Controller
{
    public function absensi(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.hr_absensi.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $dari = $request->dari ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->sampai ? Carbon::parse($request->sampai) : Carbon::now();
        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = Absensi::withoutGlobalScopes()
            ->with(['karyawan', 'cabang'])
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);
        if ($cabangId) $query->where('cabang_id', $cabangId);
        if ($request->karyawan_id) $query->where('karyawan_id', $request->karyawan_id);
        if ($request->status) $query->where('status', $request->status);

        $totalHadir = (clone $query)->where('status', 'hadir')->count();
        $totalAlpha = (clone $query)->where('status', 'alpha')->count();
        $totalIzin = (clone $query)->where('status', 'izin')->count();
        $totalSakit = (clone $query)->where('status', 'sakit')->count();

        if ($request->export === 'excel') {
            $absensi = $query->orderBy('tanggal')->get();
            $filename = 'Laporan-Absensi-' . now()->format('Y-m-d') . '.xlsx';
            return Excel::download(new LaporanAbsensiExport($absensi, $user->name), $filename);
        }
        if ($request->export === 'pdf') {
            $absensi = $query->orderBy('tanggal')->get();
            $cabangNamaFilter = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang';
            $filename = 'Laporan-Absensi-' . now()->format('Y-m-d') . '.pdf';
            $pdf = Pdf::loadView('laporan.pdf.hr-absensi', [
                'absensis' => $absensi,
                'judulLaporan' => 'Laporan Absensi',
                'filterInfo' => [
                    'Periode' => $dari->format('d/m/Y') . ' — ' . $sampai->format('d/m/Y'),
                    'Cabang' => $cabangNamaFilter,
                ],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'landscape');
            return $pdf->download($filename);
        }

        $absensis = $query->orderByDesc('tanggal')->paginate(30)->withQueryString();

        // Rekap per karyawan
        $rekapKaryawan = Absensi::withoutGlobalScopes()
            ->selectRaw('karyawan_id,
                SUM(CASE WHEN status="hadir" THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status="alpha" THEN 1 ELSE 0 END) as alpha,
                SUM(CASE WHEN status="izin" THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status="sakit" THEN 1 ELSE 0 END) as sakit,
                SUM(jam_lembur) as total_lembur')
            ->with('karyawan')
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->groupBy('karyawan_id')
            ->get();

        $karyawans = Karyawan::withoutGlobalScopes()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->where('status', 'aktif')
            ->get();

        return view('laporan.hr.absensi', compact(
            'absensis', 'cabangs', 'cabangId', 'dari', 'sampai',
            'totalHadir', 'totalAlpha', 'totalIzin', 'totalSakit',
            'rekapKaryawan', 'karyawans'
        ));
    }

    public function penggajian(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.hr_penggajian.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $periode = $request->periode ?? Carbon::now()->format('Y-m');
        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = Penggajian::withoutGlobalScopes()
            ->with(['karyawan', 'cabang'])
            ->where('periode', $periode);
        if ($cabangId) $query->where('cabang_id', $cabangId);

        $totalGaji = (clone $query)->sum('total_gaji');
        $totalKaryawan = (clone $query)->count();
        $sudahBayar = (clone $query)->where('status', 'dibayar')->count();
        $belumBayar = (clone $query)->where('status', '!=', 'dibayar')->count();

        if ($request->export === 'excel') {
            $penggajians = $query->orderBy('karyawan_id')->get();
            $filename = 'Laporan-Penggajian-' . now()->format('Y-m-d') . '.xlsx';
            return Excel::download(new LaporanPenggajianExport($penggajians, $user->name), $filename);
        }
        if ($request->export === 'pdf') {
            $penggajians = $query->orderBy('karyawan_id')->get();
            $cabangNamaFilter = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang';
            $filename = 'Laporan-Penggajian-' . now()->format('Y-m-d') . '.pdf';
            $pdf = Pdf::loadView('laporan.pdf.hr-penggajian', [
                'penggajians' => $penggajians, 'totalGaji' => $totalGaji,
                'judulLaporan' => 'Laporan Penggajian',
                'filterInfo' => ['Periode' => $periode, 'Cabang' => $cabangNamaFilter],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'landscape');
            return $pdf->download($filename);
        }

        $penggajians = $query->paginate(30)->withQueryString();

        return view('laporan.hr.penggajian', compact(
            'penggajians', 'cabangs', 'cabangId', 'periode',
            'totalGaji', 'totalKaryawan', 'sudahBayar', 'belumBayar'
        ));
    }

    public function evaluasi(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.hr_evaluasi.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $periodId = $request->period_id;

        // Periode evaluasi yang tersedia
        $periods = EvaluationPeriod::withoutGlobalScopes()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->orderByDesc('tanggal_mulai')
            ->get();

        $evaluations = collect();
        $selectedPeriod = null;

        if ($periodId) {
            $selectedPeriod = EvaluationPeriod::find($periodId);
            $evaluations = Evaluation::withoutGlobalScopes()
                ->with(['karyawan', 'cabang'])
                ->where('evaluation_period_id', $periodId)
                ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
                ->orderByDesc('skor_akhir')
                ->get();
        } elseif ($periods->isNotEmpty()) {
            $selectedPeriod = $periods->first();
            $evaluations = Evaluation::withoutGlobalScopes()
                ->with(['karyawan', 'cabang'])
                ->where('evaluation_period_id', $selectedPeriod->id)
                ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
                ->orderByDesc('skor_akhir')
                ->get();
        }

        $rataRataSkor = $evaluations->avg('skor_akhir');
        $totalEvaluasi = $evaluations->count();
        $sangat_baik = $evaluations->filter(fn($e) => $e->predikat?->value === 'sangat_baik')->count();
        $baik = $evaluations->filter(fn($e) => $e->predikat?->value === 'baik')->count();

        // Bug fix Sprint 3 Batch 2 (2026-09-21): sub-menu ini SEBELUMNYA tidak
        // punya export sama sekali (bukan cuma "belum rapi") -- ditambah baru.
        if ($request->export === 'excel') {
            $filename = 'Laporan-Evaluasi-' . now()->format('Y-m-d') . '.xlsx';
            return Excel::download(new LaporanEvaluasiExport($evaluations, $user->name), $filename);
        }
        if ($request->export === 'pdf') {
            $cabangNamaFilter = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang';
            $filename = 'Laporan-Evaluasi-' . now()->format('Y-m-d') . '.pdf';
            $pdf = Pdf::loadView('laporan.pdf.hr-evaluasi', [
                'evaluations' => $evaluations,
                'judulLaporan' => 'Laporan Evaluasi',
                'filterInfo' => [
                    'Periode' => $selectedPeriod?->nama_periode ?? '-',
                    'Cabang' => $cabangNamaFilter,
                ],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'portrait');
            return $pdf->download($filename);
        }

        return view('laporan.hr.evaluasi', compact(
            'evaluations', 'cabangs', 'cabangId', 'periods', 'selectedPeriod',
            'rataRataSkor', 'totalEvaluasi', 'sangat_baik', 'baik'
        ));
    }

}
