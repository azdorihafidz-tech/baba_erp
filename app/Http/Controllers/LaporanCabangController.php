<?php

namespace App\Http\Controllers;

use App\Exports\LaporanCabangExport;
use App\Models\Cabang;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LaporanCabangController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.cabang_vs_cabang.view') && auth()->user()->canAccessAllBranches(), 403);

        $dari    = $request->filled('dari')   ? Carbon::parse($request->dari)->startOfDay()   : Carbon::now()->startOfMonth();
        $sampai  = $request->filled('sampai') ? Carbon::parse($request->sampai)->endOfDay()   : Carbon::now()->endOfDay();
        $showHO  = $request->boolean('show_ho', false);

        $fromStr = $dari->toDateString();
        $toStr   = $sampai->toDateString();

        // Pemasukan & pengeluaran per cabang (semua tipe)
        $trxPerCabang = DB::table('transaksi_keuangans as tk')
            ->join('cabangs as c', 'c.id', '=', 'tk.cabang_id')
            ->whereNull('tk.deleted_at')
            ->whereBetween('tk.tanggal_transaksi', [$fromStr, $toStr])
            ->selectRaw("
                tk.cabang_id,
                c.nama_cabang,
                c.tipe,
                SUM(CASE WHEN tk.tipe='pemasukan' THEN tk.jumlah ELSE 0 END)   as pemasukan,
                SUM(CASE WHEN tk.tipe='pengeluaran' THEN tk.jumlah ELSE 0 END) as pengeluaran
            ")
            ->groupBy('tk.cabang_id', 'c.nama_cabang', 'c.tipe')
            ->get()
            ->keyBy('cabang_id');

        // Setoran masuk & keluar per cabang
        $setoranPerCabang = DB::table('transaksi_keuangans as tk')
            ->join('kategori_transaksis as kt', 'kt.id', '=', 'tk.kategori_id')
            ->join('cabangs as c', 'c.id', '=', 'tk.cabang_id')
            ->whereNull('tk.deleted_at')
            ->whereBetween('tk.tanggal_transaksi', [$fromStr, $toStr])
            ->whereIn('kt.kode', ['SETOR-OUT', 'SETOR-IN'])
            ->selectRaw("
                tk.cabang_id,
                SUM(CASE WHEN kt.kode='SETOR-OUT' THEN tk.jumlah ELSE 0 END) as setoran_keluar,
                SUM(CASE WHEN kt.kode='SETOR-IN'  THEN tk.jumlah ELSE 0 END) as setoran_masuk
            ")
            ->groupBy('tk.cabang_id')
            ->get()
            ->keyBy('cabang_id');

        // Gabungkan per cabang — pisahkan HO dari operasional
        $allCabangIds = $trxPerCabang->keys()->merge($setoranPerCabang->keys())->unique();

        $allRows = $allCabangIds->map(function ($cabangId) use ($trxPerCabang, $setoranPerCabang) {
            $trx    = $trxPerCabang->get($cabangId);
            $setor  = $setoranPerCabang->get($cabangId);
            $pemasukan   = (float) ($trx?->pemasukan   ?? 0);
            $pengeluaran = (float) ($trx?->pengeluaran ?? 0);
            return [
                'cabang_id'      => $cabangId,
                'nama_cabang'    => $trx?->nama_cabang ?? 'Cabang ' . $cabangId,
                'tipe'           => $trx?->tipe ?? 'cabang',
                'pemasukan'      => $pemasukan,
                'pengeluaran'    => $pengeluaran,
                'setoran_keluar' => (float) ($setor?->setoran_keluar ?? 0),
                'setoran_masuk'  => (float) ($setor?->setoran_masuk  ?? 0),
                'net'            => $pemasukan - $pengeluaran,
            ];
        });

        // Pisahkan HO vs operasional
        $hoRows    = $allRows->filter(fn($r) => $r['tipe'] === 'head_office')->values();
        $rankingOp = $allRows->filter(fn($r) => $r['tipe'] !== 'head_office')->sortByDesc('net')->values();

        // Jika toggle show_ho aktif, gabungkan HO ke ranking
        $ranking = $showHO ? $allRows->sortByDesc('net')->values() : $rankingOp;

        $totalPemasukan   = $rankingOp->sum('pemasukan');
        $totalPengeluaran = $rankingOp->sum('pengeluaran');
        $totalNet         = $rankingOp->sum('net');

        // Ringkasan HO
        $hoSummary = $hoRows->isNotEmpty() ? [
            'pemasukan'      => $hoRows->sum('pemasukan'),
            'pengeluaran'    => $hoRows->sum('pengeluaran'),
            'setoran_masuk'  => $hoRows->sum('setoran_masuk'),
            'setoran_keluar' => $hoRows->sum('setoran_keluar'),
            'net'            => $hoRows->sum('net'),
            'nama'           => $hoRows->pluck('nama_cabang')->implode(', '),
        ] : null;

        // Grafik bar: hanya operasional (bukan HO)
        $grafikLabels      = $rankingOp->pluck('nama_cabang')->toArray();
        $grafikPemasukan   = $rankingOp->pluck('pemasukan')->map(fn($v) => (float)$v)->toArray();
        $grafikPengeluaran = $rankingOp->pluck('pengeluaran')->map(fn($v) => (float)$v)->toArray();

        // Pie chart: kontribusi revenue operasional
        $pieLabels = $rankingOp->where('pemasukan', '>', 0)->pluck('nama_cabang')->toArray();
        $pieValues = $rankingOp->where('pemasukan', '>', 0)->pluck('pemasukan')->map(fn($v) => (float)$v)->toArray();

        if ($request->export === 'excel') {
            $filename = 'Laporan-Cabang-vs-Cabang-' . now()->format('Y-m-d') . '.xlsx';
            return Excel::download(new LaporanCabangExport($ranking, $totalPemasukan, $totalPengeluaran, $totalNet, auth()->user()->name), $filename);
        }
        if ($request->export === 'pdf') {
            $filename = 'Laporan-Cabang-vs-Cabang-' . now()->format('Y-m-d') . '.pdf';
            $pdf = Pdf::loadView('laporan.pdf.cabang-vs-cabang', [
                'ranking' => $ranking, 'totalPemasukan' => $totalPemasukan, 'totalPengeluaran' => $totalPengeluaran, 'totalNet' => $totalNet,
                'judulLaporan' => 'Laporan Cabang vs Cabang',
                'filterInfo' => ['Periode' => $dari->format('d/m/Y') . ' — ' . $sampai->format('d/m/Y')],
                'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'landscape');
            return $pdf->download($filename);
        }

        return view('laporan.cabang-vs-cabang', compact(
            'ranking', 'dari', 'sampai', 'showHO', 'hoSummary',
            'totalPemasukan', 'totalPengeluaran', 'totalNet',
            'grafikLabels', 'grafikPemasukan', 'grafikPengeluaran',
            'pieLabels', 'pieValues'
        ));
    }

}
