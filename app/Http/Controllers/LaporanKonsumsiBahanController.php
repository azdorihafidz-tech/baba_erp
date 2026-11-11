<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\LaporanKonsumsiBahanService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Laporan Konsumsi Bahan Baku — qty + nilai HPP bahan yang terpakai per
 * periode, dari order_items.hpp (FIFO cost). Murni READ, tidak mengubah
 * data apapun.
 */
class LaporanKonsumsiBahanController extends Controller
{
    public function __construct(private LaporanKonsumsiBahanService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.konsumsi_bahan.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.konsumsi-bahan.index', $data);
    }

    public function print(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.konsumsi_bahan.print'), 403);

        $data = $this->buildData($request);

        return view('laporan.konsumsi-bahan.print', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.konsumsi_bahan.export'), 403);

        $data = $this->buildData($request);

        return $this->exportCsv($data);
    }

    /**
     * Resolve filter (dari/sampai/cabang/tipe) + ambil semua data dari
     * service. Default periode: BULAN INI (beda dari Setoran Harian yang
     * default hari ini — laporan ini untuk overview jangka menengah).
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

        $tipe = $request->filled('tipe') ? $request->tipe : null;

        $ringkasan       = $this->service->getRingkasan($dari, $sampai, $cabangId, $tipe);
        $breakdownPenuh  = $this->service->getBreakdownPerItem($dari, $sampai, $cabangId, $tipe);
        $trend           = $this->service->getTrendBulanan($cabangId, $tipe, $sampai);
        $detail          = $this->service->getDetailPerOrder($dari, $sampai, $cabangId, $tipe);

        $breakdown = $this->sortDanPaginate($breakdownPenuh, $request);

        $cabangs = Cabang::aktif()->get();
        $cabangTerpilih = $cabangId ? $cabangs->firstWhere('id', $cabangId) : null;

        return compact(
            'ringkasan',
            'breakdown',
            'breakdownPenuh',
            'trend',
            'detail',
            'cabangs',
            'cabangId',
            'cabangTerpilih',
            'dari',
            'sampai',
            'tipe'
        );
    }

    /**
     * Sort (server-side, kolom: nama_item/total_qty/total_hpp/persentase/
     * total_omzet/total_untung/margin) lalu paginate manual (50/halaman) —
     * dataset breakdown sudah berupa Collection hasil agregasi, bukan query
     * builder, jadi tidak bisa pakai ->paginate() bawaan Eloquent.
     */
    private function sortDanPaginate($breakdown, Request $request)
    {
        $sortableKolom = ['nama_item', 'total_qty', 'total_hpp', 'persentase', 'total_omzet', 'total_untung', 'margin'];
        $sort = in_array($request->get('sort'), $sortableKolom) ? $request->get('sort') : 'total_hpp';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $sorted = $dir === 'asc' ? $breakdown->sortBy($sort) : $breakdown->sortByDesc($sort);
        $sorted = $sorted->values();

        $perPage = 50;
        $page    = max(1, (int) $request->get('page', 1));

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
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
        $dari   = $data['dari'];
        $sampai = $data['sampai'];
        $filename = 'konsumsi-bahan-' . $dari->toDateString() . '_' . $sampai->toDateString() . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($data, $dari, $sampai) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($file, ['LAPORAN KONSUMSI BAHAN BAKU'], ';');
            fputcsv($file, ['Periode', $dari->format('d/m/Y') . ' - ' . $sampai->format('d/m/Y')], ';');
            fputcsv($file, ['Cabang', $data['cabangTerpilih']->nama_cabang ?? 'Semua Cabang'], ';');
            fputcsv($file, [], ';');

            fputcsv($file, ['RINGKASAN'], ';');
            fputcsv($file, ['Total Item Unik', $data['ringkasan']['total_item_unik']], ';');
            fputcsv($file, ['Total Omzet', $data['ringkasan']['total_omzet']], ';');
            fputcsv($file, ['Total Nilai HPP', $data['ringkasan']['total_hpp']], ';');
            fputcsv($file, ['Total Untung', $data['ringkasan']['total_untung']], ';');
            fputcsv($file, ['Margin', $data['ringkasan']['margin'] !== null ? $data['ringkasan']['margin'] . '%' : '-'], ';');
            fputcsv($file, [], ';');

            fputcsv($file, ['BREAKDOWN PER ITEM'], ';');
            fputcsv($file, ['Item', 'Tipe', 'Qty Terpakai', 'Satuan', 'Omzet', 'Nilai HPP', 'Untung', 'Margin', '% HPP dari Total'], ';');
            foreach ($data['breakdownPenuh'] as $b) {
                fputcsv($file, [
                    $b->nama_item, $b->tipe, $b->total_qty, $b->satuan,
                    $b->total_omzet, $b->total_hpp, $b->total_untung,
                    $b->margin !== null ? $b->margin . '%' : '-',
                    $b->persentase . '%',
                ], ';');
            }
            fputcsv($file, [], ';');

            fputcsv($file, ['DETAIL TRANSAKSI'], ';');
            fputcsv($file, ['Tanggal', 'Waktu', 'No Order', 'Item', 'Qty', 'Satuan', 'HPP', 'Kasir'], ';');
            foreach ($data['detail'] as $tanggal => $rows) {
                foreach ($rows as $r) {
                    fputcsv($file, [
                        $tanggal,
                        \Carbon\Carbon::parse($r->order_created_at)->format('H:i'),
                        $r->nomor_order,
                        $r->nama_item,
                        $r->qty,
                        $r->satuan,
                        $r->hpp,
                        $r->kasir_nama ?? '-',
                    ], ';');
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
