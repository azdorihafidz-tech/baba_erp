<?php

namespace App\Http\Controllers;

use App\Exports\LaporanLabaRugiProduksiExport;
use App\Models\Cabang;
use App\Services\LaporanLabaRugiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan Laba Rugi — analisis gross profit (omzet vs HPP bahan) per item,
 * kategori, jenis olahan, atau order. BUKAN cash flow (itu Laporan
 * Keuangan) — murni profit produksi dari order_items. Murni READ.
 */
class LaporanLabaRugiController extends Controller
{
    public function __construct(private LaporanLabaRugiService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.laba_rugi.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.laba-rugi.index', $data);
    }

    public function print(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.laba_rugi.print'), 403);

        $data = $this->buildData($request);

        return view('laporan.laba-rugi.print', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.laba_rugi.export'), 403);

        $user = auth()->user();
        $data = $this->buildData($request);
        $labelLevel = [
            'item' => 'Item', 'kategori' => 'Kategori', 'jenis_olahan' => 'Jenis Menu', 'order' => 'Order',
        ][$data['level']] ?? 'Item';
        $cabangNamaFilter = $data['cabangTerpilih']->nama_cabang ?? 'Semua Cabang';

        if ($request->format === 'pdf') {
            $varian = $request->get('varian') === 'ringkas' ? 'ringkas' : 'detail';
            $filename = 'Laporan-Laba-Rugi-Produksi-' . $varian . '-' . now()->format('Y-m-d') . '.pdf';

            $pdf = Pdf::loadView('laporan.laba-rugi.pdf-' . $varian, [
                'ringkasan' => $data['ringkasan'],
                'breakdownPenuh' => $data['breakdownPenuh'],
                'detail' => $data['detail'],
                'level' => $data['level'],
                'labelLevel' => $labelLevel,
                'judulLaporan' => 'Laporan Laba Rugi Produksi' . ($varian === 'ringkas' ? ' (Ringkas)' : ' (Detail)'),
                'filterInfo' => [
                    'Periode' => $data['dari']->format('d/m/Y') . ' — ' . $data['sampai']->format('d/m/Y'),
                    'Cabang' => $cabangNamaFilter,
                    'Level Breakdown' => $labelLevel,
                ],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', $varian === 'ringkas' ? 'portrait' : 'landscape');

            return $pdf->download($filename);
        }

        $filename = 'Laporan-Laba-Rugi-Produksi-' . $data['level'] . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new LaporanLabaRugiProduksiExport($data['ringkasan'], $data['breakdownPenuh'], $data['level'], $labelLevel, $data['detail'], $user->name),
            $filename
        );
    }

    /**
     * Resolve filter (dari/sampai/cabang/level/sort) + ambil semua data
     * dari service. Default periode: BULAN INI, level: Per Item.
     */
    private function buildData(Request $request): array
    {
        $user = auth()->user();

        $dari = $request->filled('dari')
            ? Carbon::parse($request->dari)->startOfDay()
            : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai')
            ? Carbon::parse($request->sampai)->endOfDay()
            : Carbon::now()->endOfDay();

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }
        $cabangId = $cabangId ? (int) $cabangId : null;

        $level = in_array($request->get('level'), ['item', 'kategori', 'jenis_olahan', 'order'])
            ? $request->get('level')
            : 'item';
        $sort = $request->get('sort');
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $ringkasan = $this->service->getRingkasan($dari, $sampai, $cabangId);

        $breakdownPenuh = match ($level) {
            'kategori'     => $this->service->getBreakdownPerKategori($dari, $sampai, $cabangId, $sort, $dir),
            'jenis_olahan' => $this->service->getBreakdownPerJenisOlahan($dari, $sampai, $cabangId, $sort, $dir),
            'order'        => $this->service->getBreakdownPerOrder($dari, $sampai, $cabangId, $sort, $dir),
            default        => $this->service->getBreakdownPerItem($dari, $sampai, $cabangId, $sort, $dir),
        };

        $breakdown = $this->paginate($breakdownPenuh, $request);

        $trend     = $this->service->getTrendUntungBulanan($cabangId, $sampai);
        $topUntung = $this->service->getTopUntung($dari, $sampai, $cabangId, 10);
        $perKategoriPie = $level === 'kategori'
            ? $breakdownPenuh
            : $this->service->getBreakdownPerKategori($dari, $sampai, $cabangId);
        $detail = $this->service->getDetailTransaksi($dari, $sampai, $cabangId);

        $cabangs = Cabang::aktif()->get();
        $cabangTerpilih = $cabangId ? $cabangs->firstWhere('id', $cabangId) : null;

        return compact(
            'ringkasan',
            'breakdown',
            'breakdownPenuh',
            'trend',
            'topUntung',
            'perKategoriPie',
            'detail',
            'cabangs',
            'cabangId',
            'cabangTerpilih',
            'dari',
            'sampai',
            'level',
            'sort',
            'dir'
        );
    }

    /** Paginate manual (50/halaman) — dataset breakdown sudah Collection hasil agregasi. */
    private function paginate($rows, Request $request)
    {
        $perPage = 50;
        $page    = max(1, (int) $request->get('page', 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->except('page'),
            ]
        );
    }
}
