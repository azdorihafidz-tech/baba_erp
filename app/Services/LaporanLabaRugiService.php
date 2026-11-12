<?php

namespace App\Services;

use App\Enums\StatusOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Analisis profit produksi (gross profit): omzet vs HPP dari order_items,
 * per item / kategori / jenis olahan / order. Murni READ.
 *
 * Beda dengan LaporanKonsumsiBahanService: base query di sini pakai
 * LEFT JOIN items (BUKAN INNER JOIN) — baris "Jasa Giling" murni tanpa
 * item_id (kasir ketik nama bebas, tidak pilih dari master Item) TETAP
 * ikut terhitung, supaya profit yang ditampilkan benar-benar utuh
 * (termasuk margin dari jasa giling, salah satu dari 2 lini bisnis inti
 * Berkah Mulyo). Baris tanpa item_id dikategorikan sebagai 'jasa' di
 * breakdown per kategori (lihat kategoriExpr()).
 *
 * Omzet = SUM(order_items.total_harga) — SEBELUM diskon order (diskon
 * adalah field di level `orders`, bukan per baris item, jadi tidak
 * diprorate ke tiap baris). Untuk periode dengan diskon, jumlah Omzet di
 * sini bisa sedikit lebih tinggi dari Total Bayar aktual di Laporan
 * Penjualan — pola yang sama seperti "Produk Terlaris" di
 * LaporanPenjualanController (juga pakai SUM(order_items.total_harga)
 * tanpa proration diskon).
 */
class LaporanLabaRugiService
{
    public function getRingkasan(Carbon $start, Carbon $end, ?int $cabangId): array
    {
        $row = $this->baseQuery($start, $end, $cabangId)
            ->selectRaw('SUM(order_items.total_harga) as total_omzet, SUM(order_items.hpp) as total_hpp, COUNT(DISTINCT order_items.order_id) as total_order')
            ->first();

        $totalOmzet  = (float) ($row->total_omzet ?? 0);
        $totalHpp    = (float) ($row->total_hpp ?? 0);
        $totalUntung = $totalOmzet - $totalHpp;
        $margin      = $totalOmzet > 0 ? round(($totalUntung / $totalOmzet) * 100, 1) : null;

        return [
            'total_omzet'  => $totalOmzet,
            'total_hpp'    => $totalHpp,
            'total_untung' => $totalUntung,
            'margin'       => $margin,
            'total_order'  => (int) ($row->total_order ?? 0),
        ];
    }

    /**
     * Breakdown per item — item_id NULL di-exclude (tidak mungkin tampilkan
     * baris "item" untuk sesuatu yang bukan item, sama seperti
     * LaporanKonsumsiBahanService::getBreakdownPerItem()).
     */
    public function getBreakdownPerItem(Carbon $start, Carbon $end, ?int $cabangId, ?string $sort = null, ?string $dir = null)
    {
        $rows = $this->baseQuery($start, $end, $cabangId)
            ->whereNotNull('order_items.item_id')
            ->select(
                'items.id as item_id',
                'items.nama_item',
                'items.satuan',
                'items.tipe',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('SUM(order_items.total_harga) as total_omzet'),
                DB::raw('SUM(order_items.hpp) as total_hpp')
            )
            ->groupBy('items.id', 'items.nama_item', 'items.satuan', 'items.tipe')
            ->get();

        return $this->hitungUntungMargin($rows, $sort, $dir);
    }

    /**
     * Breakdown per kategori: bahan_baku/kemasan/produk_jadi dari items.tipe,
     * 'jasa' untuk baris tanpa item_id ATAU item bertipe 'lainnya' (service
     * fee). Tidak ada kolom qty — item dalam 1 kategori bisa beda satuan
     * (kg vs pcs), menjumlahkannya jadi angka tanpa arti.
     */
    public function getBreakdownPerKategori(Carbon $start, Carbon $end, ?int $cabangId, ?string $sort = null, ?string $dir = null)
    {
        // Group by kolom asli items.tipe (bukan ekspresi CASE) — MySQL
        // ONLY_FULL_GROUP_BY strict mode menolak GROUP BY pada ekspresi CASE
        // walau identik dengan SELECT. Remap 'lainnya'/NULL -> 'jasa' di PHP,
        // lalu gabungkan (merge) baris yang jadi satu kategori 'jasa'.
        $rows = $this->baseQuery($start, $end, $cabangId)
            ->select(
                'items.tipe',
                DB::raw('SUM(order_items.total_harga) as total_omzet'),
                DB::raw('SUM(order_items.hpp) as total_hpp')
            )
            ->groupBy('items.tipe')
            ->get();

        $grouped = $rows->groupBy(function ($row) {
            return ($row->tipe === null || $row->tipe === 'lainnya') ? 'jasa' : $row->tipe;
        })->map(function ($group, $kategori) {
            return (object) [
                'kategori'    => $kategori,
                'total_omzet' => (float) $group->sum('total_omzet'),
                'total_hpp'   => (float) $group->sum('total_hpp'),
            ];
        })->values();

        return $this->hitungUntungMargin($grouped, $sort, $dir);
    }

    /**
     * Breakdown per jenis olahan (Bakso/Sosis/Tempura dari order_items.jenis_olahan,
     * slug). Baris tanpa jenis_olahan (produk jadi, dll) dikelompokkan sebagai '-'.
     * Tidak ada kolom qty (alasan sama seperti per kategori).
     */
    public function getBreakdownPerJenisOlahan(Carbon $start, Carbon $end, ?int $cabangId, ?string $sort = null, ?string $dir = null)
    {
        $expr = "COALESCE(order_items.jenis_olahan, '-')";
        $rows = $this->baseQuery($start, $end, $cabangId)
            ->select(
                DB::raw($expr . ' as jenis_olahan'),
                DB::raw('SUM(order_items.total_harga) as total_omzet'),
                DB::raw('SUM(order_items.hpp) as total_hpp')
            )
            ->groupBy(DB::raw($expr))
            ->get();

        return $this->hitungUntungMargin($rows, $sort, $dir);
    }

    /**
     * Breakdown per order (transaksi individual). Omzet dijumlah dari
     * SEMUA baris order_items di order itu (termasuk jasa giling tanpa
     * item_id) — TIDAK pakai orders.total_bayar supaya formula Omzet
     * konsisten di semua level breakdown (lihat catatan class-level soal
     * diskon). Tidak ada kolom qty (order bisa punya campuran satuan kg+pcs).
     */
    public function getBreakdownPerOrder(Carbon $start, Carbon $end, ?int $cabangId, ?string $sort = null, ?string $dir = null)
    {
        $rows = $this->baseQuery($start, $end, $cabangId)
            ->leftJoin('users', 'users.id', '=', 'orders.kasir_id')
            ->select(
                'orders.id as order_id',
                'orders.nomor_order',
                'orders.tanggal_order',
                'orders.nama_pelanggan',
                'users.name as kasir_nama',
                DB::raw('SUM(order_items.total_harga) as total_omzet'),
                DB::raw('SUM(order_items.hpp) as total_hpp')
            )
            ->groupBy('orders.id', 'orders.nomor_order', 'orders.tanggal_order', 'orders.nama_pelanggan', 'users.name')
            ->get();

        return $this->hitungUntungMargin($rows, $sort, $dir);
    }

    /**
     * Trend untung 6 bulan terakhir (rolling window berakhir di bulan $end).
     * Pola subMonths() dari startOfMonth() — lihat catatan overflow bug di
     * LaporanKonsumsiBahanService::getTrendBulanan().
     */
    public function getTrendUntungBulanan(?int $cabangId, ?Carbon $end = null): array
    {
        $end   = ($end ?? Carbon::now())->copy()->endOfMonth();
        $start = $end->copy()->startOfMonth()->subMonths(5);

        $rows = $this->baseQuery($start, $end, $cabangId)
            ->select(
                DB::raw('YEAR(orders.tanggal_order) as tahun'),
                DB::raw('MONTH(orders.tanggal_order) as bulan'),
                DB::raw('SUM(order_items.total_harga) as total_omzet'),
                DB::raw('SUM(order_items.hpp) as total_hpp')
            )
            ->groupBy('tahun', 'bulan')
            ->get()
            ->keyBy(fn ($r) => $r->tahun . '-' . str_pad((string) $r->bulan, 2, '0', STR_PAD_LEFT));

        $labels = [];
        $data   = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 6; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->translatedFormat('M Y');
            $r = $rows[$key] ?? null;
            $untung = $r ? ((float) $r->total_omzet - (float) $r->total_hpp) : 0.0;
            $data[] = round($untung, 2);
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /** Top N item dengan untung terbesar (untuk chart bar) — reuse getBreakdownPerItem(). */
    public function getTopUntung(Carbon $start, Carbon $end, ?int $cabangId, int $limit = 10)
    {
        return $this->getBreakdownPerItem($start, $end, $cabangId, 'total_untung', 'desc')->take($limit)->values();
    }

    /**
     * Detail transaksi row-level (SEMUA order_items, termasuk baris jasa
     * giling tanpa item_id), dikelompokkan per tanggal — untuk section
     * "Detail Transaksi" collapsible, terlepas dari level breakdown yang
     * sedang dipilih di tabel utama.
     */
    public function getDetailTransaksi(Carbon $start, Carbon $end, ?int $cabangId)
    {
        $rows = $this->baseQuery($start, $end, $cabangId)
            ->leftJoin('users', 'users.id', '=', 'orders.kasir_id')
            ->select(
                'orders.id as order_id',
                'orders.nomor_order',
                'orders.tanggal_order',
                'orders.created_at as order_created_at',
                'users.name as kasir_nama',
                DB::raw($this->kategoriExpr() . ' as kategori'),
                'order_items.nama_item',
                'order_items.qty',
                'order_items.satuan',
                'order_items.total_harga as omzet',
                'order_items.hpp'
            )
            ->orderBy('orders.tanggal_order')
            ->orderBy('orders.created_at')
            ->get()
            ->map(function ($row) {
                $row->untung = (float) $row->omzet - (float) $row->hpp;
                return $row;
            });

        return $rows->groupBy(fn ($row) => Carbon::parse($row->tanggal_order)->toDateString());
    }

    /** Ekspresi SQL kategori: 'jasa' untuk item_id NULL atau tipe 'lainnya', selain itu ikut items.tipe. */
    private function kategoriExpr(): string
    {
        return "CASE WHEN items.tipe IS NULL OR items.tipe = 'lainnya' THEN 'jasa' ELSE items.tipe END";
    }

    /** Hitung untung + margin per baris, lalu sort (whitelist kolom finansial). */
    private function hitungUntungMargin($rows, ?string $sort, ?string $dir)
    {
        $rows = $rows->map(function ($row) {
            $row->total_omzet  = (float) $row->total_omzet;
            $row->total_hpp    = (float) $row->total_hpp;
            $row->total_untung = $row->total_omzet - $row->total_hpp;
            $row->margin       = $row->total_omzet > 0 ? round(($row->total_untung / $row->total_omzet) * 100, 1) : null;
            return $row;
        });

        $sortableKolom = ['total_omzet', 'total_hpp', 'total_untung', 'margin'];
        $sortKey = in_array($sort, $sortableKolom) ? $sort : 'total_untung';
        $dirKey  = $dir === 'asc' ? 'asc' : 'desc';

        $sorted = $dirKey === 'asc' ? $rows->sortBy($sortKey) : $rows->sortByDesc($sortKey);

        return $sorted->values();
    }

    private function baseQuery(Carbon $start, Carbon $end, ?int $cabangId)
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('items', 'items.id', '=', 'order_items.item_id')
            ->whereBetween('orders.tanggal_order', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('orders.status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('orders.deleted_at');

        \App\Support\KeuanganBaba::saring($query, 'orders.cabang_id', $cabangId); // Tahap 4: tanpa toko mitra

        return $query;
    }
}
