<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeuanganDashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('keuangan.view'), 403);

        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $isAllBranches  = $user->canAccessAllBranches();

        $cabangOptions = $isAllBranches
            ? Cabang::aktif()->orderBy('nama_cabang')->get(['id', 'nama_cabang'])
            : collect();

        $cabangId = $this->resolveCabangId($request, $user, $activeCabangId);
        $periode  = $request->input('periode', 'bulan_ini');
        $dari     = $request->input('dari');
        $sampai   = $request->input('sampai');

        [$dateFrom, $dateTo, $prevFrom, $prevTo, $periodeLabel] = $this->resolvePeriode($periode, $dari, $sampai);
        $data = $this->buildData($dateFrom, $dateTo, $prevFrom, $prevTo, $cabangId);

        return view('keuangan.dashboard', array_merge([
            'cabangOptions' => $cabangOptions,
            'periode'       => $periode,
            'cabangId'      => $cabangId,
            'dari'          => $dari,
            'sampai'        => $sampai,
            'periodeLabel'  => $periodeLabel,
            'isAllBranches' => $isAllBranches,
        ], $data));
    }

    public function data(Request $request)
    {
        abort_unless(auth()->user()->can('keuangan.view'), 403);

        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $cabangId = $this->resolveCabangId($request, $user, $activeCabangId);

        $periode = $request->input('periode', 'bulan_ini');
        $dari    = $request->input('dari');
        $sampai  = $request->input('sampai');

        [$dateFrom, $dateTo, $prevFrom, $prevTo, $periodeLabel] = $this->resolvePeriode($periode, $dari, $sampai);
        $data = $this->buildData($dateFrom, $dateTo, $prevFrom, $prevTo, $cabangId);
        $data['periode_label'] = $periodeLabel;

        return response()->json($data);
    }

    private function resolveCabangId(Request $request, $user, ?int $activeCabangId): ?int
    {
        if (!$user->canAccessAllBranches()) {
            return $activeCabangId ?? $user->defaultCabangId();
        }
        if ($request->filled('cabang_id') && $request->cabang_id !== '0') {
            return (int) $request->cabang_id;
        }
        return $activeCabangId;
    }

    private function resolvePeriode(string $periode, ?string $dari = null, ?string $sampai = null): array
    {
        $today = Carbon::today();

        return match ($periode) {
            'hari_ini' => [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->subDay()->endOfDay(),
                'Hari Ini (' . $today->format('d/m/Y') . ')',
            ],
            'kemarin' => [
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->subDay()->endOfDay(),
                $today->copy()->subDays(2)->startOfDay(),
                $today->copy()->subDays(2)->endOfDay(),
                'Kemarin (' . $today->copy()->subDay()->format('d/m/Y') . ')',
            ],
            '7_hari' => [
                $today->copy()->subDays(6)->startOfDay(),
                $today->copy()->endOfDay(),
                $today->copy()->subDays(13)->startOfDay(),
                $today->copy()->subDays(7)->endOfDay(),
                '7 Hari Terakhir',
            ],
            'bulan_lalu' => [
                $today->copy()->subMonth()->startOfMonth()->startOfDay(),
                $today->copy()->subMonth()->endOfMonth()->endOfDay(),
                $today->copy()->subMonths(2)->startOfMonth()->startOfDay(),
                $today->copy()->subMonths(2)->endOfMonth()->endOfDay(),
                'Bulan Lalu (' . $today->copy()->subMonth()->format('M Y') . ')',
            ],
            'custom' => [
                $dari ? Carbon::parse($dari)->startOfDay() : $today->copy()->startOfMonth(),
                $sampai ? Carbon::parse($sampai)->endOfDay() : $today->copy()->endOfDay(),
                $dari ? Carbon::parse($dari)->subMonth()->startOfDay() : $today->copy()->subMonth()->startOfMonth(),
                $sampai ? Carbon::parse($sampai)->subMonth()->endOfDay() : $today->copy()->subMonth()->endOfDay(),
                'Custom: ' . ($dari ? Carbon::parse($dari)->format('d/m') : '?') . ' – ' . ($sampai ? Carbon::parse($sampai)->format('d/m/Y') : '?'),
            ],
            default => [
                $today->copy()->startOfMonth()->startOfDay(),
                $today->copy()->endOfDay(),
                $today->copy()->subMonth()->startOfMonth()->startOfDay(),
                $today->copy()->subMonth()->endOfMonth()->endOfDay(),
                'Bulan Ini (' . $today->format('M Y') . ')',
            ],
        };
    }

    private function buildData(Carbon $dateFrom, Carbon $dateTo, Carbon $prevFrom, Carbon $prevTo, ?int $cabangId): array
    {
        // Factory: fresh query builder per call, bypasses all Eloquent global scopes
        $tbl = fn() => DB::table('transaksi_keuangans')
            ->whereNull('deleted_at')
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId));

        $from  = $dateFrom->toDateString();
        $to    = $dateTo->toDateString();
        $pFrom = $prevFrom->toDateString();
        $pTo   = $prevTo->toDateString();

        // Stat cards
        $pemasukan   = (float) $tbl()->where('tipe', 'pemasukan')->whereBetween('tanggal_transaksi', [$from, $to])->sum('jumlah');
        $pengeluaran = (float) $tbl()->where('tipe', 'pengeluaran')->whereBetween('tanggal_transaksi', [$from, $to])->sum('jumlah');

        $prevPemasukan   = (float) $tbl()->where('tipe', 'pemasukan')->whereBetween('tanggal_transaksi', [$pFrom, $pTo])->sum('jumlah');
        $prevPengeluaran = (float) $tbl()->where('tipe', 'pengeluaran')->whereBetween('tanggal_transaksi', [$pFrom, $pTo])->sum('jumlah');

        $selisih     = $pemasukan - $pengeluaran;
        $prevSelisih = $prevPemasukan - $prevPengeluaran;

        $calcTrend = fn($curr, $prev) => $prev > 0
            ? round((($curr - $prev) / $prev) * 100, 1)
            : ($curr > 0 ? 100.0 : 0.0);

        // Chart 7 hari — single aggregated query
        $sevenDaysAgo = Carbon::today()->subDays(6)->toDateString();
        $chartRows = $tbl()
            ->selectRaw("DATE(tanggal_transaksi) as tgl,
                SUM(CASE WHEN tipe='pemasukan' THEN jumlah ELSE 0 END) as p,
                SUM(CASE WHEN tipe='pengeluaran' THEN jumlah ELSE 0 END) as k")
            ->where('tanggal_transaksi', '>=', $sevenDaysAgo)
            ->groupByRaw('DATE(tanggal_transaksi)')
            ->orderBy('tgl')
            ->get()
            ->keyBy('tgl');

        $hariId = ['Sun' => 'Min', 'Mon' => 'Sen', 'Tue' => 'Sel', 'Wed' => 'Rab', 'Thu' => 'Kam', 'Fri' => 'Jum', 'Sat' => 'Sab'];
        $chartLabels = $chartPemasukan = $chartPengeluaran = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $k = $d->toDateString();
            $chartLabels[]      = ($hariId[$d->format('D')] ?? $d->format('D')) . ' ' . $d->format('d/m');
            $chartPemasukan[]   = $chartRows->has($k) ? (float) $chartRows[$k]->p : 0;
            $chartPengeluaran[] = $chartRows->has($k) ? (float) $chartRows[$k]->k : 0;
        }

        // Saldo kas (real-time)
        $kasRows = DB::table('kas')
            ->leftJoin('cabangs', 'cabangs.id', '=', 'kas.cabang_id')
            ->whereNull('kas.deleted_at')
            ->where('kas.is_active', true)
            ->when($cabangId, fn($q) => $q->where('kas.cabang_id', $cabangId))
            ->selectRaw('kas.id, kas.nama_kas, kas.tipe_kas, kas.saldo_sekarang, kas.saldo_minimum, cabangs.nama_cabang')
            ->orderBy('cabangs.nama_cabang')
            ->orderBy('kas.nama_kas')
            ->get()
            ->map(fn($r) => [
                'nama'          => $r->nama_kas,
                'tipe'          => $r->tipe_kas,
                'saldo'         => (float) $r->saldo_sekarang,
                'saldo_minimum' => (float) ($r->saldo_minimum ?? 0),
                'cabang'        => $r->nama_cabang,
                'low'           => (float)($r->saldo_minimum ?? 0) > 0
                    && (float)$r->saldo_sekarang < (float)($r->saldo_minimum ?? 0),
            ]);

        // Top 5 pengeluaran bulan berjalan per kategori
        $bulanFrom = Carbon::now()->startOfMonth()->toDateString();
        $bulanTo   = Carbon::now()->endOfMonth()->toDateString();
        $topRows = DB::table('transaksi_keuangans as tk')
            ->leftJoin('kategori_transaksis as kt', 'kt.id', '=', 'tk.kategori_id')
            ->whereNull('tk.deleted_at')
            ->where('tk.tipe', 'pengeluaran')
            ->whereBetween('tk.tanggal_transaksi', [$bulanFrom, $bulanTo])
            ->when($cabangId, fn($q) => $q->where('tk.cabang_id', $cabangId))
            ->selectRaw("COALESCE(kt.nama, 'Lainnya') as nama, SUM(tk.jumlah) as total")
            ->groupBy('kt.nama', 'tk.kategori_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $totalTop = $topRows->sum('total');
        $topPengeluaran = $topRows->map(fn($r) => [
            'nama'   => $r->nama,
            'total'  => (float) $r->total,
            'persen' => $totalTop > 0 ? round(($r->total / $totalTop) * 100, 1) : 0,
        ])->values()->toArray();

        // Alerts
        $alerts = [];

        foreach ($kasRows as $kas) {
            if ($kas['low']) {
                $alerts[] = [
                    'tipe'  => 'warning',
                    'pesan' => "Saldo kas <strong>{$kas['nama']}</strong>"
                        . ($kas['cabang'] ? " ({$kas['cabang']})" : '')
                        . " hanya <strong>Rp " . number_format($kas['saldo'], 0, ',', '.') . "</strong>"
                        . " (min: Rp " . number_format($kas['saldo_minimum'], 0, ',', '.') . ")",
                ];
            }
        }

        $tanpaBuktiCount = $tbl()
            ->where('tipe', 'pengeluaran')
            ->whereNull('bukti_path')
            ->where('jumlah', '>', 500000)
            ->where('tanggal_transaksi', '>=', Carbon::now()->subDays(30)->toDateString())
            ->count();

        if ($tanpaBuktiCount > 0) {
            $urlKeuangan = route('keuangan.index');
            $alerts[] = [
                'tipe'  => 'info',
                'pesan' => "<strong>{$tanpaBuktiCount} pengeluaran</strong> di atas Rp 500.000 dalam 30 hari terakhir belum memiliki bukti. "
                    . "<a href='{$urlKeuangan}' class='alert-link'>Lihat →</a>",
            ];
        }

        if (auth()->user()->can('setoran.terima')) {
            $pendingSetoran = DB::table('transaksi_keuangans as tk')
                ->join('kategori_transaksis as kt', 'kt.id', '=', 'tk.kategori_id')
                ->whereNull('tk.deleted_at')
                ->where('kt.kode', 'SETOR-OUT')
                ->where('tk.status_setoran', 'menunggu_diterima')
                ->count();

            if ($pendingSetoran > 0) {
                $urlSetoran = route('setoran.index');
                $alerts[] = [
                    'tipe'  => 'danger',
                    'pesan' => "<strong>{$pendingSetoran} setoran</strong> menunggu konfirmasi penerimaan. "
                        . "<a href='{$urlSetoran}' class='alert-link'>Lihat →</a>",
                ];
            }
        }

        return [
            'pemasukan'         => $pemasukan,
            'pengeluaran'       => $pengeluaran,
            'selisih'           => $selisih,
            'trend_pemasukan'   => $calcTrend($pemasukan, $prevPemasukan),
            'trend_pengeluaran' => $calcTrend($pengeluaran, $prevPengeluaran),
            'trend_selisih'     => $calcTrend($selisih, $prevSelisih),
            'chart'             => [
                'labels'      => $chartLabels,
                'pemasukan'   => $chartPemasukan,
                'pengeluaran' => $chartPengeluaran,
            ],
            'kas'               => $kasRows->values()->toArray(),
            'top_pengeluaran'   => $topPengeluaran,
            'alerts'            => $alerts,
            'updated_at'        => now()->format('H:i:s'),
        ];
    }
}
