<?php

namespace App\Http\Controllers;

use App\Exports\LaporanRekapHarianExport;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Scopes\CabangScope;
use App\Models\TransaksiKeuangan;
use App\Services\SetoranHarianService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan Setoran Harian — konsolidasi pemasukan/pengeluaran/net 1 halaman
 * untuk kebutuhan setoran ke pusat (menggantikan cek manual 3 halaman
 * terpisah). Murni READ, tidak mengubah data apapun.
 */
class SetoranHarianController extends Controller
{
    public function __construct(private SetoranHarianService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_harian.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.setoran-harian.index', $data);
    }

    public function print(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_harian.print'), 403);

        $data = $this->buildData($request);

        return view('laporan.setoran-harian.print', $data);
    }

    /**
     * Bug fix Sprint 3 Batch 1b/1c (2026-09-21): export DULU meniru persis
     * struktur multi-section index (Ringkasan+Breakdown+Detail per 1
     * cabang/1 periode) via fputcsv() manual. Owner minta format export
     * BARU yang lebih ringkas -- 1 baris per Tanggal+Cabang (Total Order,
     * Total Pemasukan, Total Pengeluaran, Kas Bersih), TIDAK dinamai
     * "Total Setoran Kasir" spy tidak rancu dgn menu Laporan Setoran Kasir
     * yang beda sumber data ([[4.24]]). Halaman index()/print() (live view
     * harian utk closing kas) SENGAJA TIDAK diubah -- cuma export yang
     * diganti strukturnya, karena kebutuhan export (rekap ringkas lintas
     * hari/cabang) beda dari kebutuhan tampilan harian (detail 1 hari).
     */
    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_harian.export'), 403);

        $user = auth()->user();
        $dari = $request->filled('dari') ? Carbon::parse($request->dari)->startOfDay() : Carbon::today();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai)->endOfDay() : Carbon::today()->endOfDay();

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }
        $cabangId = $cabangId ? (int) $cabangId : null;

        $rows = $this->buildRekapHarian($dari, $sampai, $cabangId);
        $cabangNamaFilter = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang';

        if ($request->format === 'pdf') {
            $filename = 'Laporan-Rekap-Harian-' . now()->format('Y-m-d') . '.pdf';

            $pdf = Pdf::loadView('laporan.setoran-harian.pdf', [
                'rows' => $rows,
                'judulLaporan' => 'Laporan Rekap Harian',
                'filterInfo' => [
                    'Periode' => $dari->format('d/m/Y') . ' — ' . $sampai->format('d/m/Y'),
                    'Cabang' => $cabangNamaFilter,
                ],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'portrait');

            return $pdf->download($filename);
        }

        $filename = 'Laporan-Rekap-Harian-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new LaporanRekapHarianExport($rows, $user->name), $filename);
    }

    /**
     * Agregasi 1 baris per (Tanggal, Cabang) yang genuinely ada aktivitas
     * (order ATAU transaksi keuangan) -- BUKAN padding semua kombinasi
     * tanggal x cabang (bisa jadi ratusan baris kosong utk range panjang).
     * Pemasukan = SUM(orders.total_bayar) + pemasukan manual NON-order
     * (referensi_type != 'order', spy tidak double-count dgn TransaksiKeuangan
     * auto-generate per order -- lihat PenjualanService::buatOrder()).
     */
    private function buildRekapHarian(Carbon $dari, Carbon $sampai, ?int $cabangId)
    {
        $ordersAgg = Order::withoutGlobalScopes()
            ->selectRaw('tanggal_order as tgl, cabang_id, COUNT(*) as jumlah_order, SUM(total_bayar) as total_omzet')
            ->whereBetween('tanggal_order', [$dari->toDateString(), $sampai->toDateString()])
            ->penjualanSah()
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->groupBy('tanggal_order', 'cabang_id')
            ->get()
            ->keyBy(fn ($r) => $r->tgl . '|' . $r->cabang_id);

        $pemasukanLainAgg = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->selectRaw('tanggal_transaksi as tgl, cabang_id, SUM(jumlah) as total')
            ->where('tipe', 'pemasukan')
            ->where(function ($q) {
                $q->whereNull('referensi_type')->orWhere('referensi_type', '!=', 'order');
            })
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->groupBy('tanggal_transaksi', 'cabang_id')
            ->get()
            ->keyBy(fn ($r) => $r->tgl . '|' . $r->cabang_id);

        $pengeluaranAgg = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->selectRaw('tanggal_transaksi as tgl, cabang_id, SUM(jumlah) as total')
            ->where('tipe', 'pengeluaran')
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->groupBy('tanggal_transaksi', 'cabang_id')
            ->get()
            ->keyBy(fn ($r) => $r->tgl . '|' . $r->cabang_id);

        $semuaKunci = collect()
            ->merge($ordersAgg->keys())
            ->merge($pemasukanLainAgg->keys())
            ->merge($pengeluaranAgg->keys())
            ->unique();

        $cabangNama = Cabang::pluck('nama_cabang', 'id');

        return $semuaKunci->map(function ($kunci) use ($ordersAgg, $pemasukanLainAgg, $pengeluaranAgg, $cabangNama) {
            [$tgl, $cabangIdRow] = explode('|', $kunci);
            $omzet = (float) ($ordersAgg[$kunci]->total_omzet ?? 0);
            $pemasukanLain = (float) ($pemasukanLainAgg[$kunci]->total ?? 0);
            $pengeluaran = (float) ($pengeluaranAgg[$kunci]->total ?? 0);

            return (object) [
                'tanggal' => $tgl,
                'cabang_nama' => $cabangNama[$cabangIdRow] ?? '-',
                'jumlah_order' => (int) ($ordersAgg[$kunci]->jumlah_order ?? 0),
                'total_pemasukan' => $omzet + $pemasukanLain,
                'total_pengeluaran' => $pengeluaran,
                'kas_bersih' => ($omzet + $pemasukanLain) - $pengeluaran,
            ];
        })->sortByDesc('tanggal')->values();
    }

    /**
     * Resolve filter (dari/sampai/cabang) + ambil semua data dari service.
     * Default periode: HARI INI (beda dari kebanyakan laporan lain yang
     * default bulan ini — setoran harian butuh kecepatan cek hari berjalan).
     */
    private function buildData(Request $request): array
    {
        $user = auth()->user();

        $dari = $request->filled('dari')
            ? Carbon::parse($request->dari)->startOfDay()
            : Carbon::today();
        $sampai = $request->filled('sampai')
            ? Carbon::parse($request->sampai)->endOfDay()
            : Carbon::today()->endOfDay();

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }
        $cabangId = $cabangId ? (int) $cabangId : null;

        $ringkasan          = $this->service->getRingkasan($dari, $sampai, $cabangId);
        $breakdownPemasukan = $this->service->getBreakdownPemasukan($dari, $sampai, $cabangId);
        $breakdownPengeluaran = $this->service->getBreakdownPengeluaran($dari, $sampai, $cabangId);
        $detailOrder        = $this->service->getDetailOrder($dari, $sampai, $cabangId);
        $detailPengeluaran  = $this->service->getDetailPengeluaran($dari, $sampai, $cabangId);

        $cabangs = Cabang::aktif()->get();
        $cabangTerpilih = $cabangId ? $cabangs->firstWhere('id', $cabangId) : null;

        return compact(
            'ringkasan',
            'breakdownPemasukan',
            'breakdownPengeluaran',
            'detailOrder',
            'detailPengeluaran',
            'cabangs',
            'cabangId',
            'cabangTerpilih',
            'dari',
            'sampai'
        );
    }

}
