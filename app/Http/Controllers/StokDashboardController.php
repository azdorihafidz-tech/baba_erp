<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StokDashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $user     = auth()->user();
        $cabangId = null;

        if ($user->canAccessAllBranches()) {
            // Owner bisa pilih cabang via query string, atau lihat semua
            $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        } else {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $cabangList = $user->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : collect();

        // Sprint 4.34 (2026-10-08) — Dashboard Stok EXCLUDE produk_jual/produk_tambahan.
        // Alasan (keputusan Owner): produk jual = POS display only, dibuat on-demand
        // lewat resep dari bahan baku. Stok fisik real cuma di bahan_baku/kemasan/
        // tambahan_gratis. 3 tipe itu = \App\Models\Item::TIPE_DAPAT_DIBELI, konsisten
        // dgn [[4.33]] filter PO/Transfer/Request.
        $tipe = in_array($request->input('tipe'), \App\Models\Item::TIPE_DAPAT_DIBELI, true)
            ? $request->input('tipe')
            : null;

        // ── Alert Stok ──────────────────────────────────────────────────
        $alertStok = DB::table('stocks')
            ->join('items', 'stocks.item_id', '=', 'items.id')
            ->leftJoin('cabangs', 'stocks.lokasi_id', '=', 'cabangs.id')
            ->where('stocks.qty', '<=', DB::raw('stocks.qty_minimum'))
            ->where('stocks.qty_minimum', '>', 0)
            ->whereNull('stocks.deleted_at')
            ->whereNull('items.deleted_at')
            ->where('items.is_active', true)
            ->whereIn('items.tipe', \App\Models\Item::TIPE_DAPAT_DIBELI)
            ->when($cabangId, fn($q) => $q->where('stocks.lokasi_id', $cabangId))
            ->when($tipe, fn($q) => $q->where('items.tipe', $tipe))
            ->select(
                'items.id',
                'items.nama_item',
                'items.satuan',
                'stocks.qty',
                'stocks.qty_minimum',
                'stocks.lokasi_id',
                'cabangs.nama_cabang'
            )
            ->orderBy('stocks.qty', 'asc')
            ->get();

        $habis   = $alertStok->where('qty', 0);
        $kritis  = $alertStok->where('qty', '>', 0);

        // ── Nilai Stok per Cabang (FIFO actual cost) ────────────────────
        $nilaiStokPerCabang = DB::table('stock_batches')
            ->join('cabangs', 'stock_batches.lokasi_id', '=', 'cabangs.id')
            ->when($tipe, fn($q) => $q->join('items', 'stock_batches.item_id', '=', 'items.id')->where('items.tipe', $tipe))
            ->where('stock_batches.qty_sisa', '>', 0)
            ->whereNull('stock_batches.deleted_at')
            ->when($cabangId, fn($q) => $q->where('stock_batches.lokasi_id', $cabangId))
            ->select(
                'cabangs.id as cabang_id',
                'cabangs.nama_cabang',
                DB::raw('SUM(stock_batches.qty_sisa * stock_batches.harga_beli_per_unit) as nilai_stok')
            )
            ->groupBy('cabangs.id', 'cabangs.nama_cabang')
            ->orderBy('cabangs.nama_cabang')
            ->get();

        $totalNilai = $nilaiStokPerCabang->sum('nilai_stok');

        // ── List Item Stok ───────────────────────────────────────────────
        $query = DB::table('items')
            ->leftJoin('stocks', function ($join) use ($cabangId) {
                $join->on('items.id', '=', 'stocks.item_id')
                     ->whereNull('stocks.deleted_at');
                if ($cabangId) {
                    $join->where('stocks.lokasi_id', $cabangId);
                }
            })
            ->where('items.is_active', true)
            ->whereIn('items.tipe', \App\Models\Item::TIPE_DAPAT_DIBELI)
            ->when($tipe, fn($q) => $q->where('items.tipe', $tipe))
            ->whereNull('items.deleted_at')
            ->select(
                'items.id',
                'items.nama_item',
                'items.kode_item',
                'items.tipe',
                'items.satuan',
                DB::raw('COALESCE(SUM(stocks.qty), 0) as total_qty'),
                DB::raw('COALESCE(SUM(stocks.qty_minimum), 0) as qty_minimum')
            )
            ->groupBy('items.id', 'items.nama_item', 'items.kode_item', 'items.tipe', 'items.satuan')
            ->orderBy('items.nama_item');

        $stokItems = $query->get();

        // Hitung HPP avg & nilai stok FIFO per item, plus status alert
        $today = today()->toDateString();

        foreach ($stokItems as $item) {
            $batchQuery = DB::table('stock_batches')
                ->where('item_id', $item->id)
                ->where('qty_sisa', '>', 0)
                ->whereNull('deleted_at');

            if ($cabangId) {
                $batchQuery->where('lokasi_id', $cabangId);
            }

            $batches = $batchQuery->get();

            if ($batches->isNotEmpty()) {
                $batchNilai      = $batches->sum(fn($b) => $b->qty_sisa * $b->harga_beli_per_unit);
                $batchQty        = $batches->sum('qty_sisa');
                $item->hpp_avg   = $batchQty > 0 ? $batchNilai / $batchQty : 0;
                $item->nilai_stok = $batchNilai;
                $item->jumlah_batch = $batches->count();
            } else {
                $item->hpp_avg      = 0;
                $item->nilai_stok   = 0;
                $item->jumlah_batch = 0;
            }

            // Klasifikasi alert
            $qty = (float) $item->total_qty;
            $min = (float) ($item->qty_minimum ?? 0);

            if ($qty == 0) {
                $item->alert = 'habis';
            } elseif ($min > 0 && $qty <= $min) {
                $item->alert = 'kritis';
            } elseif ($min > 0 && $qty <= ($min * 1.2)) {
                $item->alert = 'menipis';
            } else {
                $item->alert = 'aman';
            }
        }

        // Stat ringkasan
        $statKritis  = $stokItems->whereIn('alert', ['habis', 'kritis'])->count();
        $statMenipis = $stokItems->where('alert', 'menipis')->count();

        // Phase C — Smart Insights (4 widgets)
        $agingStok   = $this->buildAgingStok($cabangId, $tipe);
        $topMovement = $this->buildTopMovement($cabangId, $tipe);
        $trendHarga  = $this->buildTrendHarga($cabangId, $tipe);
        $stokMati    = $this->buildStokMati($cabangId, $tipe);

        return view('stok.dashboard', compact(
            'stokItems',
            'habis',
            'kritis',
            'nilaiStokPerCabang',
            'totalNilai',
            'cabangList',
            'cabangId',
            'tipe',
            'statKritis',
            'statMenipis',
            'agingStok',
            'topMovement',
            'trendHarga',
            'stokMati'
        ));
    }

    // ── Lihat Semua — 4 halaman detail ─────────────────────────────────────

    public function aging(Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $cabangId  = $this->resolveCabangFilter($request);
        $threshold = max(1, min((int) $request->input('threshold', 30), 730));
        $today     = today();
        $cutoff    = today()->subDays($threshold);

        $baseQuery = DB::table('stock_batches')
            ->join('items',   'stock_batches.item_id',   '=', 'items.id')
            ->join('cabangs', 'stock_batches.lokasi_id', '=', 'cabangs.id')
            ->where('stock_batches.qty_sisa', '>', 0)
            ->whereNull('stock_batches.deleted_at')
            ->whereNull('items.deleted_at')
            ->where('items.is_active', true)
            ->whereIn('items.tipe', \App\Models\Item::TIPE_DAPAT_DIBELI)
            ->where('stock_batches.tanggal_masuk', '<=', $cutoff->toDateString())
            ->when($cabangId, fn($q) => $q->where('stock_batches.lokasi_id', $cabangId))
            ->select(
                'stock_batches.id',
                'items.nama_item',
                'items.satuan',
                'items.tipe',
                'stock_batches.qty_sisa',
                'stock_batches.harga_beli_per_unit',
                'stock_batches.tanggal_masuk',
                'cabangs.nama_cabang as lokasi'
            )
            ->orderBy('stock_batches.tanggal_masuk', 'asc');

        $totalNilai = (clone $baseQuery)->sum(DB::raw('stock_batches.qty_sisa * stock_batches.harga_beli_per_unit'));
        $totalItem  = (clone $baseQuery)->count();

        $batches = $baseQuery->paginate(30)->withQueryString();

        foreach ($batches as $b) {
            $b->umur_hari = Carbon::parse($b->tanggal_masuk)->diffInDays($today);
            $b->nilai     = $b->qty_sisa * $b->harga_beli_per_unit;
            $b->severity  = $b->umur_hari > 60 ? 'danger' : 'warning';
        }

        $cabangList = $this->getCabangList();

        return view('stok.aging', compact('batches', 'cabangList', 'cabangId', 'threshold', 'totalNilai', 'totalItem'));
    }

    public function topMovement(Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $cabangId = $this->resolveCabangFilter($request);
        $hari     = max(7, min((int) $request->input('hari', 90), 365));
        $since    = now()->subDays($hari);

        $items = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->where('stock_movements.tipe', 'keluar')
            ->where(fn($q) => $q->where('stock_movements.created_at', '>=', $since)
                                ->orWhereNull('stock_movements.created_at'))
            ->whereNull('items.deleted_at')
            ->whereIn('items.tipe', \App\Models\Item::TIPE_DAPAT_DIBELI)
            ->when($cabangId, fn($q) => $q->where('stock_movements.lokasi_asal_id', $cabangId))
            ->select(
                'items.id',
                'items.nama_item',
                'items.satuan',
                'items.tipe',
                'items.harga_beli_terakhir',
                DB::raw('SUM(stock_movements.qty) as total_keluar'),
                DB::raw('COUNT(stock_movements.id) as jumlah_transaksi')
            )
            ->groupBy('items.id', 'items.nama_item', 'items.satuan', 'items.tipe', 'items.harga_beli_terakhir')
            ->orderByDesc('total_keluar')
            ->paginate(30)
            ->withQueryString();

        foreach ($items as $item) {
            $item->nilai = $item->total_keluar * ($item->harga_beli_terakhir ?? 0);
        }

        $totalKeluar = DB::table('stock_movements')
            ->where('tipe', 'keluar')
            ->where(fn($q) => $q->where('created_at', '>=', $since)->orWhereNull('created_at'))
            ->when($cabangId, fn($q) => $q->where('lokasi_asal_id', $cabangId))
            ->sum('qty');

        $cabangList = $this->getCabangList();

        return view('stok.top-movement', compact('items', 'cabangList', 'cabangId', 'hari', 'totalKeluar'));
    }

    public function trendHarga(Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $cabangId  = $this->resolveCabangFilter($request);
        $hari      = max(7, min((int) $request->input('hari', 30), 365));
        $threshold = max(1, min((int) $request->input('threshold', 5), 100));
        $cutoff    = now()->subDays($hari);

        $allItems = DB::table('items')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereIn('tipe', \App\Models\Item::TIPE_DAPAT_DIBELI)
            ->select('id', 'nama_item', 'satuan')
            ->orderBy('nama_item')
            ->get();

        $trends = collect();
        foreach ($allItems as $item) {
            $qLama = DB::table('stock_batches')
                ->where('item_id', $item->id)
                ->where('tanggal_masuk', '<', $cutoff->toDateString())
                ->whereNull('deleted_at')
                ->when($cabangId, fn($q) => $q->where('lokasi_id', $cabangId))
                ->avg('harga_beli_per_unit');

            $qBaru = DB::table('stock_batches')
                ->where('item_id', $item->id)
                ->where('tanggal_masuk', '>=', $cutoff->toDateString())
                ->whereNull('deleted_at')
                ->when($cabangId, fn($q) => $q->where('lokasi_id', $cabangId))
                ->avg('harga_beli_per_unit');

            if (!$qLama || !$qBaru) continue;

            $perubahan = $qBaru - $qLama;
            $persen    = ($perubahan / $qLama) * 100;

            if (abs($persen) < $threshold) continue;

            $trends->push((object) [
                'nama_item'  => $item->nama_item,
                'satuan'     => $item->satuan,
                'harga_lama' => $qLama,
                'harga_baru' => $qBaru,
                'perubahan'  => $perubahan,
                'persen'     => $persen,
                'severity'   => abs($persen) >= 15 ? 'danger' : 'warning',
                'arah'       => $persen > 0 ? 'naik' : 'turun',
            ]);
        }

        $trends = $trends->sortByDesc(fn($t) => abs($t->persen))->values();

        $page      = $request->get('page', 1);
        $trendsPage = new LengthAwarePaginator(
            $trends->forPage($page, 30),
            $trends->count(),
            30,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $cabangList = $this->getCabangList();

        return view('stok.trend-harga', compact('trendsPage', 'cabangList', 'cabangId', 'hari', 'threshold'));
    }

    public function stokMati(Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $cabangId  = $this->resolveCabangFilter($request);
        $threshold = max(1, min((int) $request->input('threshold', 30), 365));
        $cutoff    = now()->subDays($threshold);

        $itemsDenganStok = DB::table('stocks')
            ->join('items',   'stocks.item_id',   '=', 'items.id')
            ->leftJoin('cabangs', 'stocks.lokasi_id', '=', 'cabangs.id')
            ->where('stocks.qty', '>', 0)
            ->whereNull('stocks.deleted_at')
            ->whereNull('items.deleted_at')
            ->where('items.is_active', true)
            ->when($cabangId, fn($q) => $q->where('stocks.lokasi_id', $cabangId))
            ->select(
                'items.id',
                'items.nama_item',
                'items.satuan',
                'items.tipe',
                'items.harga_beli_terakhir',
                DB::raw('SUM(stocks.qty) as total_qty'),
                'stocks.lokasi_id',
                'cabangs.nama_cabang as lokasi'
            )
            ->groupBy('items.id', 'items.nama_item', 'items.satuan', 'items.tipe',
                      'items.harga_beli_terakhir', 'stocks.lokasi_id', 'cabangs.nama_cabang')
            ->get();

        $stokMati = collect();
        foreach ($itemsDenganStok as $item) {
            $lastMovement = DB::table('stock_movements')
                ->where('item_id', $item->id)
                ->where('tipe', 'keluar')
                ->when($cabangId, fn($q) => $q->where('lokasi_asal_id', $cabangId))
                ->orderByDesc('created_at')
                ->value('created_at');

            if (!$lastMovement || Carbon::parse($lastMovement)->lt($cutoff)) {
                $hari = $lastMovement ? Carbon::parse($lastMovement)->diffInDays(now()) : 999;
                $stokMati->push((object) [
                    'nama_item'        => $item->nama_item,
                    'satuan'           => $item->satuan,
                    'tipe'             => $item->tipe,
                    'qty'              => $item->total_qty,
                    'nilai'            => $this->nilaiFifo($item->id, (int) $item->lokasi_id, (float) $item->total_qty, (float) ($item->harga_beli_terakhir ?? 0)),
                    'lokasi'           => $item->lokasi,
                    'hari_sejak_keluar' => $hari,
                    'pernah_keluar'    => $lastMovement !== null,
                    'last_keluar'      => $lastMovement,
                ]);
            }
        }

        $stokMati = $stokMati->sortByDesc('hari_sejak_keluar')->values();
        $totalNilai = $stokMati->sum('nilai');

        $page         = $request->get('page', 1);
        $stokMatiPage = new LengthAwarePaginator(
            $stokMati->forPage($page, 30),
            $stokMati->count(),
            30,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $cabangList = $this->getCabangList();

        return view('stok.stok-mati', compact('stokMatiPage', 'cabangList', 'cabangId', 'threshold', 'totalNilai'));
    }

    // ── Helper methods ──────────────────────────────────────────────────────

    private function resolveCabangFilter(Request $request): ?int
    {
        $user = auth()->user();
        if ($user->canAccessAllBranches()) {
            return $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        }
        return (int) (session('active_cabang_id') ?? $user->defaultCabangId());
    }

    private function getCabangList()
    {
        return auth()->user()->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : collect();
    }

    // ── Phase C: Smart Insights ─────────────────────────────────────────────

    /**
     * Widget 1 — Aging Stok: batch masuk > 30 hari yang masih punya sisa.
     */
    private function buildAgingStok($cabangId, $tipe = null)
    {
        $today     = today();
        $threshold = today()->subDays(30); // instance terpisah, bukan mutasi $today

        $batches = DB::table('stock_batches')
            ->join('items',   'stock_batches.item_id',   '=', 'items.id')
            ->join('cabangs', 'stock_batches.lokasi_id', '=', 'cabangs.id')
            ->where('stock_batches.qty_sisa', '>', 0)
            ->whereNull('stock_batches.deleted_at')
            ->whereNull('items.deleted_at')
            ->where('items.is_active', true)
            ->whereIn('items.tipe', \App\Models\Item::TIPE_DAPAT_DIBELI)
            ->where('stock_batches.tanggal_masuk', '<=', $threshold->toDateString())
            ->when($cabangId, fn($q) => $q->where('stock_batches.lokasi_id', $cabangId))
            ->when($tipe, fn($q) => $q->where('items.tipe', $tipe))
            ->select(
                'stock_batches.id',
                'items.nama_item',
                'items.satuan',
                'stock_batches.qty_sisa',
                'stock_batches.harga_beli_per_unit',
                'stock_batches.tanggal_masuk',
                'cabangs.nama_cabang as lokasi'
            )
            ->orderBy('stock_batches.tanggal_masuk', 'asc')
            ->limit(10)
            ->get();

        $batches->each(function ($b) use ($today) {
            $b->umur_hari = Carbon::parse($b->tanggal_masuk)->diffInDays($today);
            $b->nilai     = $b->qty_sisa * $b->harga_beli_per_unit;
            $b->severity  = $b->umur_hari > 60 ? 'danger' : 'warning';
        });

        return $batches;
    }

    /**
     * Widget 2 — Top Movement: item paling banyak keluar dalam 90 hari terakhir.
     * Sertakan created_at IS NULL (movement lama tanpa timestamp, tetap valid).
     */
    private function buildTopMovement($cabangId, $tipe = null)
    {
        $since = now()->subDays(90);

        $topItems = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->where('stock_movements.tipe', 'keluar')
            ->where(fn($q) => $q->where('stock_movements.created_at', '>=', $since)
                                ->orWhereNull('stock_movements.created_at'))
            ->whereNull('items.deleted_at')
            ->whereIn('items.tipe', \App\Models\Item::TIPE_DAPAT_DIBELI)
            ->when($cabangId, fn($q) => $q->where('stock_movements.lokasi_asal_id', $cabangId))
            ->when($tipe, fn($q) => $q->where('items.tipe', $tipe))
            ->select(
                'items.id',
                'items.nama_item',
                'items.satuan',
                'items.harga_beli_terakhir',
                DB::raw('SUM(stock_movements.qty) as total_keluar'),
                DB::raw('COUNT(stock_movements.id) as jumlah_transaksi')
            )
            ->groupBy('items.id', 'items.nama_item', 'items.satuan', 'items.harga_beli_terakhir')
            ->orderByDesc('total_keluar')
            ->limit(5)
            ->get();

        $topItems->each(function ($item) {
            $item->nilai = $item->total_keluar * ($item->harga_beli_terakhir ?? 0);
        });

        return $topItems;
    }

    /**
     * Widget 3 — Trend Harga: perubahan harga beli > 5% antara batch lama vs baru (30 hari).
     */
    private function buildTrendHarga($cabangId, $tipe = null)
    {
        $cutoff = now()->subDays(30);

        $items = DB::table('items')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereIn('tipe', \App\Models\Item::TIPE_DAPAT_DIBELI)
            ->when($tipe, fn($q) => $q->where('tipe', $tipe))
            ->select('id', 'nama_item', 'satuan')
            ->get();

        $trends = collect();

        foreach ($items as $item) {
            $qLama = DB::table('stock_batches')
                ->where('item_id', $item->id)
                ->where('tanggal_masuk', '<', $cutoff->toDateString())
                ->whereNull('deleted_at')
                ->when($cabangId, fn($q) => $q->where('lokasi_id', $cabangId))
                ->avg('harga_beli_per_unit');

            $qBaru = DB::table('stock_batches')
                ->where('item_id', $item->id)
                ->where('tanggal_masuk', '>=', $cutoff->toDateString())
                ->whereNull('deleted_at')
                ->when($cabangId, fn($q) => $q->where('lokasi_id', $cabangId))
                ->avg('harga_beli_per_unit');

            if (!$qLama || !$qBaru) continue;

            $perubahan = $qBaru - $qLama;
            $persen    = ($perubahan / $qLama) * 100;

            if (abs($persen) < 5) continue;

            $trends->push((object) [
                'nama_item'  => $item->nama_item,
                'satuan'     => $item->satuan,
                'harga_lama' => $qLama,
                'harga_baru' => $qBaru,
                'perubahan'  => $perubahan,
                'persen'     => $persen,
                'severity'   => $persen >= 15 ? 'danger' : ($persen >= 5 ? 'warning' : 'info'),
                'arah'       => $persen > 0 ? 'naik' : 'turun',
            ]);
        }

        return $trends->sortByDesc(fn($t) => abs($t->persen))->take(5);
    }

    /**
     * Widget 4 — Stok Mati: stok > 0 tanpa movement keluar selama 30+ hari.
     */
    private function buildStokMati($cabangId, $tipe = null)
    {
        $tigapuluhHariLalu = now()->subDays(30);

        $itemsDenganStok = DB::table('stocks')
            ->join('items', 'stocks.item_id', '=', 'items.id')
            ->where('stocks.qty', '>', 0)
            ->whereNull('stocks.deleted_at')
            ->whereNull('items.deleted_at')
            ->where('items.is_active', true)
            ->whereIn('items.tipe', \App\Models\Item::TIPE_DAPAT_DIBELI)
            ->when($cabangId, fn($q) => $q->where('stocks.lokasi_id', $cabangId))
            ->when($tipe, fn($q) => $q->where('items.tipe', $tipe))
            ->select(
                'items.id',
                'items.nama_item',
                'items.satuan',
                'items.harga_beli_terakhir',
                'stocks.qty',
                'stocks.lokasi_id'
            )
            ->get();

        $stokMati = collect();

        foreach ($itemsDenganStok as $item) {
            $movementTerakhir = DB::table('stock_movements')
                ->where('item_id', $item->id)
                ->where('tipe', 'keluar')
                ->when($cabangId, fn($q) => $q->where('lokasi_asal_id', $cabangId))
                ->orderByDesc('created_at')
                ->value('created_at');

            if (!$movementTerakhir || Carbon::parse($movementTerakhir)->lt($tigapuluhHariLalu)) {
                $hariSejakKeluar = $movementTerakhir
                    ? Carbon::parse($movementTerakhir)->diffInDays(now())
                    : 999;

                $stokMati->push((object) [
                    'nama_item'       => $item->nama_item,
                    'satuan'          => $item->satuan,
                    'qty'             => $item->qty,
                    'nilai'           => $this->nilaiFifo($item->id, (int) $item->lokasi_id, (float) $item->qty, (float) ($item->harga_beli_terakhir ?? 0)),
                    'hari_sejak_keluar' => $hariSejakKeluar,
                    'pernah_keluar'   => $movementTerakhir !== null,
                ]);
            }
        }

        return $stokMati->sortByDesc('hari_sejak_keluar')->take(10);
    }

    /** @var array<string, array{qty: float, nilai: float}>|null */
    private ?array $petaNilaiBatch = null;

    /**
     * Nilai stok 1 item di 1 lokasi memakai batch FIFO (qty_sisa × harga
     * batch) — sama dgn Neraca/Closing. Qty tanpa batch (data lama) dinilai
     * dgn Harga Standar, sama dgn fallback StokService. CLAUDE.md 4.38.
     */
    private function nilaiFifo(int $itemId, int $lokasiId, float $qty, float $hargaStandar): float
    {
        if ($this->petaNilaiBatch === null) {
            $this->petaNilaiBatch = DB::table('stock_batches')
                ->where('qty_sisa', '>', 0)
                ->whereNull('deleted_at')
                ->selectRaw('item_id, lokasi_id, SUM(qty_sisa) as qty, SUM(qty_sisa * harga_beli_per_unit) as nilai')
                ->groupBy('item_id', 'lokasi_id')
                ->get()
                ->mapWithKeys(fn ($r) => [$r->item_id . '|' . $r->lokasi_id => ['qty' => (float) $r->qty, 'nilai' => (float) $r->nilai]])
                ->all();
        }

        $b = $this->petaNilaiBatch[$itemId . '|' . $lokasiId] ?? ['qty' => 0.0, 'nilai' => 0.0];
        if ($b['qty'] <= 0) {
            return $qty * $hargaStandar;
        }
        if ($qty <= $b['qty']) {
            return $b['nilai'] * ($qty / $b['qty']);
        }

        return $b['nilai'] + ($qty - $b['qty']) * $hargaStandar;
    }

    public function itemBatches(Item $item, Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;

        $batches = DB::table('stock_batches')
            ->leftJoin('cabangs', 'stock_batches.lokasi_id', '=', 'cabangs.id')
            ->where('stock_batches.item_id', $item->id)
            ->where('stock_batches.qty_sisa', '>', 0)
            ->whereNull('stock_batches.deleted_at')
            ->when($cabangId, fn($q) => $q->where('stock_batches.lokasi_id', $cabangId))
            ->select(
                'stock_batches.id',
                'stock_batches.tanggal_masuk',
                'stock_batches.qty_awal',
                'stock_batches.qty_sisa',
                'stock_batches.harga_beli_per_unit',
                'stock_batches.referensi_type',
                'cabangs.nama_cabang as lokasi'
            )
            ->orderBy('stock_batches.tanggal_masuk', 'asc')
            ->orderBy('stock_batches.id', 'asc')
            ->get();

        $today = today();

        $batches = $batches->map(function ($b) use ($today) {
            $b->umur_hari = Carbon::parse($b->tanggal_masuk)->diffInDays($today);
            $b->nilai     = round($b->qty_sisa * $b->harga_beli_per_unit, 2);
            return $b;
        });

        return response()->json(['batches' => $batches]);
    }
}
