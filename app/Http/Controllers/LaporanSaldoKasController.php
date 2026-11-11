<?php

namespace App\Http\Controllers;

use App\Exports\LaporanSaldoKasExport;
use App\Models\Cabang;
use App\Models\Kas;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LaporanSaldoKasController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.saldo_kas.view'), 403);

        $user    = auth()->user();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        $dari   = $request->filled('dari')   ? Carbon::parse($request->dari)->startOfDay()   : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai)->endOfDay()   : Carbon::now()->endOfDay();

        $cabangId = $request->cabang_id ?: null;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        // Daftar kas yang bisa dipilih
        $kasOptions = DB::table('kas')
            ->leftJoin('cabangs', 'cabangs.id', '=', 'kas.cabang_id')
            ->whereNull('kas.deleted_at')
            ->where('kas.is_active', true)
            ->when($cabangId, fn($q) => $q->where('kas.cabang_id', $cabangId))
            ->select('kas.id', 'kas.nama_kas', 'cabangs.nama_cabang')
            ->orderBy('cabangs.nama_cabang')
            ->orderBy('kas.nama_kas')
            ->get();

        $kasId = $request->kas_id ?: null;

        // Pilih kas yang akan ditampilkan
        $kasQuery = Kas::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->whereNull('deleted_at')
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->when($kasId, fn($q) => $q->where('id', $kasId))
            ->with('cabang:id,nama_cabang')
            ->orderBy('id')
            ->get();

        $kasReports = $kasQuery->map(function ($kas) use ($dari, $sampai) {
            return $this->buildKasReport($kas, $dari, $sampai);
        });

        // Grafik line: saldo per hari (untuk kas pertama atau single kas)
        $grafikLabels = $grafikSaldo = [];
        if ($kasReports->isNotEmpty()) {
            $firstReport = $kasReports->first();
            foreach ($firstReport['mutasi'] as $m) {
                $grafikLabels[] = Carbon::parse($m['tanggal'])->format('d/m');
                $grafikSaldo[]  = (float) $m['saldo_running'];
            }
        }

        if ($request->export === 'excel') {
            $filename = 'Laporan-Saldo-Kas-' . now()->format('Y-m-d') . '.xlsx';
            return Excel::download(new LaporanSaldoKasExport($kasReports, $user->name), $filename);
        }
        if ($request->export === 'pdf') {
            $cabangNamaFilter = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang';
            $filename = 'Laporan-Saldo-Kas-' . now()->format('Y-m-d') . '.pdf';
            $pdf = Pdf::loadView('laporan.pdf.saldo-kas', [
                'kasReports' => $kasReports,
                'judulLaporan' => 'Laporan Saldo Kas',
                'filterInfo' => [
                    'Periode' => $dari->format('d/m/Y') . ' — ' . $sampai->format('d/m/Y'),
                    'Cabang' => $cabangNamaFilter,
                ],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'portrait');
            return $pdf->download($filename);
        }

        return view('laporan.saldo-kas', compact(
            'kasReports', 'kasOptions', 'kasId', 'cabangs', 'cabangId', 'dari', 'sampai',
            'grafikLabels', 'grafikSaldo'
        ));
    }

    private function buildKasReport(Kas $kas, Carbon $dari, Carbon $sampai): array
    {
        $fromStr = $dari->toDateString();
        $toStr   = $sampai->toDateString();

        // Saldo awal periode = saldo_awal kas + net transaksi sebelum periode
        // Exclude kategori 'saldo_awal' — sudah tercermin di kas.saldo_awal, bukan transaksi operasional
        $netSebelum = (float) DB::table('transaksi_keuangans')
            ->whereNull('deleted_at')
            ->where('kas_id', $kas->id)
            ->where('tanggal_transaksi', '<', $fromStr)
            ->where('kategori', '!=', 'saldo_awal')
            ->selectRaw("SUM(CASE WHEN tipe='pemasukan' THEN jumlah ELSE -jumlah END) as net")
            ->value('net');

        $saldoAwalPeriode = (float) $kas->saldo_awal + $netSebelum;

        // Mutasi dalam periode (exclude transaksi saldo_awal — bukan mutasi operasional)
        $mutasiRows = DB::table('transaksi_keuangans')
            ->whereNull('deleted_at')
            ->where('kas_id', $kas->id)
            ->whereBetween('tanggal_transaksi', [$fromStr, $toStr])
            ->where('kategori', '!=', 'saldo_awal')
            ->orderBy('tanggal_transaksi')
            ->orderBy('id')
            ->get(['id', 'tanggal_transaksi', 'nomor_transaksi', 'keterangan', 'tipe', 'jumlah']);

        $saldoRunning = $saldoAwalPeriode;
        $mutasi = [];
        $totalPemasukan = 0;
        $totalPengeluaran = 0;

        foreach ($mutasiRows as $row) {
            if ($row->tipe === 'pemasukan') {
                $saldoRunning += (float) $row->jumlah;
                $totalPemasukan += (float) $row->jumlah;
                $debit  = null;
                $kredit = (float) $row->jumlah;
            } else {
                $saldoRunning -= (float) $row->jumlah;
                $totalPengeluaran += (float) $row->jumlah;
                $debit  = (float) $row->jumlah;
                $kredit = null;
            }
            $mutasi[] = [
                'tanggal'       => $row->tanggal_transaksi,
                'nomor'         => $row->nomor_transaksi,
                'keterangan'    => $row->keterangan,
                'tipe'          => $row->tipe,
                'debit'         => $debit,
                'kredit'        => $kredit,
                'saldo_running' => $saldoRunning,
            ];
        }

        return [
            'kas'              => $kas,
            'saldo_awal'       => $saldoAwalPeriode,
            'total_pemasukan'  => $totalPemasukan,
            'total_pengeluaran' => $totalPengeluaran,
            'saldo_akhir'      => $saldoRunning,
            'mutasi'           => $mutasi,
        ];
    }

}
