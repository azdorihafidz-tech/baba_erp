<?php

namespace App\Services;

use App\Enums\StatusOrder;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 4.46 — Laporan Penjualan Produk & Kategori di menu Penjualan (bisa dibuka
 * kasir). Murni READ, dari order_items order sah (Order::penjualanSah, 4.45).
 *
 * Per baris item:
 *   penjualan  = order_items.total_harga (harga × qty di keranjang)
 *   diskon     = diskon order dibagi ke item sesuai porsi harganya
 *                (orders.diskon × item.total_harga / orders.total_harga)
 *   pendapatan = penjualan − diskon
 *   keuntungan = pendapatan − order_items.hpp
 * Service charge & take away fee bukan milik produk mana pun → biayaTambahan(),
 * supaya Σ pendapatan + biaya tambahan = pendapatan Laporan Transaksi.
 *
 * Produk dikelompokkan per Kode Item (bukan item_id): sejak data barang
 * terpisah per cabang (4.39) produk yang sama di cabang berbeda punya item_id
 * berbeda tapi Kode Item sama — di mode "Semua Cabang" tetap jadi 1 baris.
 */
class LaporanBarangKasirService
{
    private const DISKON = 'CASE WHEN orders.total_harga > 0 THEN oi.total_harga * orders.diskon / orders.total_harga ELSE 0 END';
    private const KUNCI_PRODUK = 'COALESCE(i.kode_item, oi.nama_item)';

    /** order_items dari order sah dalam periode & cabang + join barang & kategori. */
    public function base(Carbon $dari, Carbon $sampai, ?int $cabangId): Builder
    {
        return DB::table('order_items as oi')
            ->join('orders', 'orders.id', '=', 'oi.order_id')
            ->leftJoin('items as i', 'i.id', '=', 'oi.item_id')
            ->leftJoin('item_categories as ic', 'ic.id', '=', 'i.item_category_id')
            ->whereNull('oi.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', StatusOrder::bukanPenjualan())
            ->whereBetween('orders.tanggal_order', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn ($q) => $q->where('orders.cabang_id', $cabangId))
            ->when(! $cabangId, fn ($q) => LaporanTransaksiKasirService::tanpaInternal($q)); // 4.49
    }

    /** Kolom angka yang sama untuk semua pengelompokan. */
    private function kolomAngka(Builder $q): Builder
    {
        return $q->selectRaw('SUM(oi.qty) as qty, SUM(oi.total_harga) as penjualan, SUM(' . self::DISKON . ') as diskon, SUM(oi.hpp) as hpp, COUNT(DISTINCT oi.order_id) as transaksi');
    }

    private function angka(object $r): array
    {
        $penjualan = (float) $r->penjualan;
        $diskon = round((float) $r->diskon, 2);
        $pendapatan = $penjualan - $diskon;
        $hpp = (float) $r->hpp;

        return [
            'qty'        => (float) $r->qty,
            'transaksi'  => (int) $r->transaksi,
            'penjualan'  => $penjualan,
            'diskon'     => $diskon,
            'pendapatan' => $pendapatan,
            'hpp'        => $hpp,
            'keuntungan' => $pendapatan - $hpp,
            'margin'     => $pendapatan > 0 ? round(($pendapatan - $hpp) / $pendapatan * 100, 1) : null,
        ];
    }

    /**
     * Rekap per produk (+ rincian varian kalau ada lebih dari 1 varian terjual).
     *
     * @param  array{kategori:?int,cari:?string}  $filter
     */
    public function produk(Carbon $dari, Carbon $sampai, ?int $cabangId, array $filter = []): Collection
    {
        $q = $this->base($dari, $sampai, $cabangId);
        $this->terapkanFilter($q, $filter);

        $rows = $this->kolomAngka(
            (clone $q)->groupBy(DB::raw(self::KUNCI_PRODUK))
                ->selectRaw(self::KUNCI_PRODUK . ' as kunci, MAX(i.kode_item) as kode, MAX(COALESCE(i.nama_item, oi.nama_item)) as nama,'
                    . ' MAX(ic.id) as kategori_id, MAX(ic.nama_kategori) as kategori, MAX(oi.satuan) as satuan')
        )->get();

        $varian = $this->kolomAngka(
            (clone $q)->whereNotNull('oi.item_variant_id')
                ->groupBy(DB::raw(self::KUNCI_PRODUK), 'oi.nama_item')
                ->selectRaw(self::KUNCI_PRODUK . ' as kunci, oi.nama_item as nama')
        )->get()->groupBy('kunci');

        return $rows->map(function ($r) use ($varian) {
            $rincian = $varian->get($r->kunci, collect());

            return [
                'kunci'       => $r->kunci,
                'kode'        => $r->kode,
                'nama'        => $r->nama,
                'kategori_id' => $r->kategori_id ? (int) $r->kategori_id : null,
                'kategori'    => $r->kategori ?: 'Tanpa kategori',
                'satuan'      => $r->satuan,
                'varian'      => $rincian->count() > 1
                    ? $rincian->map(fn ($v) => ['nama' => $v->nama] + $this->angka($v))->sortByDesc('qty')->values()
                    : collect(),
            ] + $this->angka($r);
        })->values();
    }

    /**
     * Rekap per kategori + daftar produk di dalamnya (dari produk(), jadi
     * angkanya selalu sama dengan Laporan Produk). Produk tanpa kategori
     * dikumpulkan di "Tanpa kategori" (kategori_id null; filter = -1).
     */
    public function kategori(Carbon $dari, Carbon $sampai, ?int $cabangId): Collection
    {
        $transaksi = $this->base($dari, $sampai, $cabangId)
            ->groupBy('ic.id')
            ->selectRaw('ic.id as kategori_id, COUNT(DISTINCT oi.order_id) as transaksi')
            ->get()->keyBy(fn ($r) => (int) $r->kategori_id);

        return $this->produk($dari, $sampai, $cabangId)
            ->groupBy(fn ($p) => (int) $p['kategori_id'])
            ->map(function (Collection $produk, int $id) use ($transaksi) {
                $t = self::total($produk);

                return [
                    'kategori_id'   => $id ?: null,
                    'kategori'      => $produk->first()['kategori'],
                    'jumlah_produk' => $produk->count(),
                    'transaksi'     => (int) ($transaksi->get($id)->transaksi ?? 0),
                    'margin'        => $t['pendapatan'] > 0 ? round($t['keuntungan'] / $t['pendapatan'] * 100, 1) : null,
                    'produk'        => self::urutkan($produk, 'qty')->map(fn ($p) => array_diff_key($p, ['varian' => 1]))->values(),
                ] + $t;
            })->values();
    }

    /** Service charge + take away fee order sah di periode (tidak dibagi ke produk). */
    public function biayaTambahan(Carbon $dari, Carbon $sampai, ?int $cabangId): float
    {
        return (float) DB::table('orders')
            ->whereNull('deleted_at')
            ->whereNotIn('status', StatusOrder::bukanPenjualan())
            ->whereBetween('tanggal_order', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->when(! $cabangId, fn ($q) => LaporanTransaksiKasirService::tanpaInternal($q)) // 4.49
            ->sum(DB::raw('COALESCE(service_charge,0) + COALESCE(take_away_fee,0)'));
    }

    /** Kategori yang punya penjualan di periode (untuk dropdown filter). */
    public function daftarKategori(Carbon $dari, Carbon $sampai, ?int $cabangId): Collection
    {
        return $this->base($dari, $sampai, $cabangId)->whereNotNull('ic.id')
            ->groupBy('ic.id', 'ic.nama_kategori')->orderBy('ic.nama_kategori')
            ->get(['ic.id', 'ic.nama_kategori']);
    }

    /** Urutkan baris: qty / pendapatan / keuntungan / nama (kolom nama bisa 'kategori'). */
    public static function urutkan(Collection $rows, string $urut, string $kolomNama = 'nama'): Collection
    {
        return ($urut === 'nama' ? $rows->sortBy(fn ($r) => mb_strtolower($r[$kolomNama])) : $rows->sortByDesc($urut))->values();
    }

    /** @return array{qty:float,transaksi:int,penjualan:float,diskon:float,pendapatan:float,hpp:float,keuntungan:float} */
    public static function total(Collection $rows): array
    {
        $t = [];
        foreach (['qty', 'penjualan', 'diskon', 'pendapatan', 'hpp', 'keuntungan'] as $k) {
            $t[$k] = (float) $rows->sum($k);
        }

        return $t;
    }

    private function terapkanFilter(Builder $q, array $filter): void
    {
        if (! empty($filter['kategori'])) {
            $filter['kategori'] === -1
                ? $q->whereNull('ic.id')
                : $q->where('ic.id', $filter['kategori']);
        }
        if (filled($filter['cari'] ?? null)) {
            $cari = '%' . trim($filter['cari']) . '%';
            $q->where(fn ($w) => $w->where('oi.nama_item', 'like', $cari)->orWhere('i.nama_item', 'like', $cari)->orWhere('i.kode_item', 'like', $cari));
        }
    }
}
