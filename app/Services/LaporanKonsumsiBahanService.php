<?php

namespace App\Services;

use App\Enums\StatusOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregasi konsumsi bahan baku (qty + HPP) dari order_items per periode.
 * Murni READ, tidak menulis data apapun — sengaja dipisah dari service
 * existing (PenjualanService/StokService) supaya nol risiko ke fitur lain.
 *
 * Sumber HPP = order_items.hpp (FIFO cost yang sudah dihitung otomatis saat
 * order dibuat, lihat StokService::consumeBatchesFifo). Pola query
 * (DB::table raw join, bukan Eloquent) PERSIS seperti SetoranHarianService —
 * supaya global scope cabang di model Order tidak diam-diam ikut membatasi
 * hasil, filter cabang selalu eksplisit lewat parameter $cabangId.
 */
class LaporanKonsumsiBahanService
{
    /**
     * Total Omzet dihitung dari total_hpp + total_untung, dan total_untung
     * bergantung total_omzet — tapi total_omzet SENGAJA dihitung dari scope
     * LEBIH LUAS (hitungTotalOmzet(), LEFT JOIN items) daripada breakdown
     * per item (INNER JOIN, exclude item_id NULL). Alasan: baris "Jasa
     * Giling" murni (biaya jasa itu sendiri, bukan bumbu) biasanya punya
     * item_id NULL karena kasir ketik nama bebas tanpa pilih dari master
     * Item — breakdown per-item TIDAK BISA menampilkan baris tanpa item,
     * tapi omzet dari baris itu tetap harus terhitung di ringkasan supaya
     * "Total Untung" merepresentasikan profit riil (termasuk jasa giling,
     * salah satu dari 2 lini bisnis inti). HPP tidak perlu ikut query luas
     * ini karena baris item_id NULL SELALU hpp=0 (PenjualanService hanya
     * isi HPP kalau item_id di-set & tipe bahan_baku/kemasan/produk_jadi).
     */
    public function getRingkasan(Carbon $start, Carbon $end, ?int $cabangId, ?string $tipe): array
    {
        $breakdown = $this->getBreakdownPerItem($start, $end, $cabangId, $tipe);
        $totalHpp  = (float) $breakdown->sum('total_hpp');

        $totalOmzet  = $this->hitungTotalOmzet($start, $end, $cabangId, $tipe);
        $totalUntung = $totalOmzet - $totalHpp;
        $margin      = $totalOmzet > 0 ? round(($totalUntung / $totalOmzet) * 100, 1) : null;

        return [
            'total_item_unik'    => $breakdown->count(),
            'total_hpp'          => $totalHpp,
            'total_omzet'        => $totalOmzet,
            'total_untung'       => $totalUntung,
            'margin'             => $margin,
            'item_terbanyak_qty' => $breakdown->sortByDesc('total_qty')->first(),
            'item_termahal_hpp'  => $breakdown->sortByDesc('total_hpp')->first(),
        ];
    }

    /**
     * Breakdown per item: qty + HPP + Omzet + Untung + Margin + persentase
     * dari total HPP. item_id NULL (item manual/terhapus, termasuk baris
     * jasa giling murni tanpa item) di-exclude via INNER JOIN ke items —
     * beda scope dari getRingkasan()/hitungTotalOmzet() yang lebih luas,
     * lihat catatan di getRingkasan(). Kalau SUM(total_omzet) di sini <
     * total_omzet ringkasan, selisihnya = omzet dari baris tanpa item.
     */
    public function getBreakdownPerItem(Carbon $start, Carbon $end, ?int $cabangId, ?string $tipe)
    {
        $rows = $this->baseQuery($start, $end, $cabangId, $tipe)
            ->select(
                'items.id as item_id',
                'items.nama_item',
                'items.satuan',
                'items.tipe',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('SUM(order_items.hpp) as total_hpp'),
                DB::raw('SUM(order_items.total_harga) as total_omzet')
            )
            ->groupBy('items.id', 'items.nama_item', 'items.satuan', 'items.tipe')
            ->orderByDesc('total_hpp')
            ->get();

        $grandTotal = (float) $rows->sum('total_hpp');

        return $rows->map(function ($row) use ($grandTotal) {
            $row->total_qty    = (float) $row->total_qty;
            $row->total_hpp    = (float) $row->total_hpp;
            $row->total_omzet  = (float) $row->total_omzet;
            $row->total_untung = $row->total_omzet - $row->total_hpp;
            $row->margin       = $row->total_omzet > 0 ? round(($row->total_untung / $row->total_omzet) * 100, 1) : null;
            $row->persentase   = $grandTotal > 0 ? round(($row->total_hpp / $grandTotal) * 100, 1) : 0.0;
            return $row;
        });
    }

    /** Total omzet scope LUAS (LEFT JOIN, termasuk item_id NULL) — lihat catatan getRingkasan(). */
    private function hitungTotalOmzet(Carbon $start, Carbon $end, ?int $cabangId, ?string $tipe): float
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('items', 'items.id', '=', 'order_items.item_id')
            ->whereBetween('orders.tanggal_order', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('orders.status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('orders.deleted_at');

        if ($cabangId) {
            $query->where('orders.cabang_id', $cabangId);
        }
        if ($tipe) {
            // Baris item_id NULL punya items.tipe NULL (LEFT JOIN) — otomatis
            // ke-exclude kalau user filter ke tipe spesifik (Bahan Baku dst),
            // konsisten: filter "Bahan Baku" ya tidak boleh ikutkan jasa giling.
            $query->where('items.tipe', $tipe);
        }

        return (float) $query->sum('order_items.total_harga');
    }

    /**
     * Trend 6 bulan terakhir (rolling window berakhir di bulan $end), untuk
     * Chart.js line chart. Bulan tanpa data tetap muncul dengan nilai 0
     * supaya sumbu X selalu genap 6 titik.
     */
    public function getTrendBulanan(?int $cabangId, ?string $tipe, ?Carbon $end = null): array
    {
        // subMonths() dari startOfMonth() dulu (bukan dari endOfMonth() yang
        // bisa tanggal 31) — subtraksi bulan dari tanggal 31 overflow kalau
        // bulan tujuan lebih pendek (mis. 31 Jul -5bulan jadi awal Mar,
        // bukan akhir Feb, karena Carbon overflow 31 Feb -> awal Mar).
        $end   = ($end ?? Carbon::now())->copy()->endOfMonth();
        $start = $end->copy()->startOfMonth()->subMonths(5);

        $rows = $this->baseQuery($start, $end, $cabangId, $tipe)
            ->select(
                DB::raw('YEAR(orders.tanggal_order) as tahun'),
                DB::raw('MONTH(orders.tanggal_order) as bulan'),
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
            $data[]   = round((float) ($rows[$key]->total_hpp ?? 0), 2);
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /** Detail per order_item, dikelompokkan per tanggal (support range panjang). */
    public function getDetailPerOrder(Carbon $start, Carbon $end, ?int $cabangId, ?string $tipe)
    {
        $rows = $this->baseQuery($start, $end, $cabangId, $tipe)
            ->leftJoin('users', 'users.id', '=', 'orders.kasir_id')
            ->select(
                'orders.id as order_id',
                'orders.nomor_order',
                'orders.tanggal_order',
                'orders.created_at as order_created_at',
                'users.name as kasir_nama',
                'items.tipe as item_tipe',
                'order_items.nama_item',
                'order_items.qty',
                'order_items.satuan',
                'order_items.hpp'
            )
            ->orderBy('orders.tanggal_order')
            ->orderBy('orders.created_at')
            ->get();

        return $rows->groupBy(fn ($row) => Carbon::parse($row->tanggal_order)->toDateString());
    }

    private function baseQuery(Carbon $start, Carbon $end, ?int $cabangId, ?string $tipe)
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('items', 'items.id', '=', 'order_items.item_id')
            ->whereBetween('orders.tanggal_order', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('orders.status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('orders.deleted_at')
            ->whereNotNull('order_items.item_id');

        if ($cabangId) {
            $query->where('orders.cabang_id', $cabangId);
        }
        if ($tipe) {
            $query->where('items.tipe', $tipe);
        }

        return $query;
    }
}
