<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Supplier;
use App\Services\PoDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Dashboard PO Full — monitoring end-to-end status Purchase Order (draft
 * s/d dibayar). Murni READ, tidak mengubah alur approve/kirim/terima
 * existing — quick action cuma navigate ke halaman detail PO / Kas Keluar.
 */
class PoDashboardController extends Controller
{
    public function __construct(private PoDashboardService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('po_dashboard.view'), 403);

        $data = $this->buildData($request);

        return view('pembelian.po-dashboard.index', $data);
    }

    public function print(Request $request)
    {
        abort_unless(auth()->user()->can('po_dashboard.view'), 403);

        $data = $this->buildData($request);

        return view('pembelian.po-dashboard.print', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('po_dashboard.view'), 403);

        $data = $this->buildData($request);

        return $this->exportCsv($data);
    }

    private function buildData(Request $request): array
    {
        $user = auth()->user();

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }
        $cabangId = $cabangId ? (int) $cabangId : null;

        $status     = $request->filled('status') ? $request->status : null;
        $supplierId = $request->filled('supplier_id') ? (int) $request->supplier_id : null;
        $umurMin    = $request->filled('umur_min') ? (int) $request->umur_min : null;
        $sort       = $request->get('sort');
        $dir        = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $dari   = $request->filled('dari') ? Carbon::parse($request->dari)->startOfDay() : null;
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai)->endOfDay() : null;

        $ringkasan      = $this->service->getRingkasanStatus($cabangId);
        $breakdownPenuh = $this->service->getBreakdownAktif($cabangId, $status, $supplierId, $umurMin, $dari, $sampai, $sort, $dir);
        $breakdown      = $this->paginate($breakdownPenuh, $request);
        $chartStatus    = $this->service->getChartPerStatus($cabangId);
        $trend          = $this->service->getTrendBulanan($cabangId, $sampai);

        $cabangs   = Cabang::aktif()->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('nama_supplier')->get();
        $cabangTerpilih = $cabangId ? $cabangs->firstWhere('id', $cabangId) : null;

        return compact(
            'ringkasan',
            'breakdown',
            'breakdownPenuh',
            'chartStatus',
            'trend',
            'cabangs',
            'suppliers',
            'cabangId',
            'cabangTerpilih',
            'status',
            'supplierId',
            'umurMin',
            'dari',
            'sampai',
            'sort',
            'dir'
        );
    }

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

    private function exportCsv(array $data)
    {
        $filename = 'dashboard-po-' . now()->format('Ymd-His') . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($file, ['DASHBOARD PO'], ';');
            fputcsv($file, ['Cabang', $data['cabangTerpilih']->nama_cabang ?? 'Semua Cabang'], ';');
            fputcsv($file, [], ';');

            fputcsv($file, ['RINGKASAN PER STATUS'], ';');
            fputcsv($file, ['Status', 'Jumlah PO', 'Total Nilai', 'Umur Maks (hari)'], ';');
            $labelBucket = [
                'menunggu_approval' => 'Menunggu Approval',
                'perlu_dikirim'     => 'Perlu Dikirim',
                'dalam_perjalanan'  => 'Dalam Perjalanan',
                'belum_diterima'    => 'Belum Diterima (total)',
                'belum_dibayar'     => 'Belum Dibayar',
            ];
            foreach ($labelBucket as $key => $label) {
                $r = $data['ringkasan'][$key];
                fputcsv($file, [$label, $r['count'], $r['total_nilai'], $r['umur_maks_hari']], ';');
            }
            fputcsv($file, [], ';');

            fputcsv($file, ['DAFTAR PO AKTIF'], ';');
            fputcsv($file, ['Nomor PO', 'Supplier', 'Cabang', 'Status', 'Total', 'Umur (hari)', 'Sudah Dibayar'], ';');
            foreach ($data['breakdownPenuh'] as $b) {
                fputcsv($file, [
                    $b->nomor_po,
                    $b->nama_supplier ?? '-',
                    $b->nama_cabang ?? '-',
                    $b->status_label,
                    $b->total_harga,
                    $b->umur_hari,
                    $b->sudah_dibayar === null ? '-' : ($b->sudah_dibayar ? 'Ya' : 'Belum'),
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
