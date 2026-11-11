<?php

namespace App\Services;

use App\Enums\KategoriPengeluaran;
use App\Enums\StatusOrder;
use App\Enums\TipePembayaran;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Order;
use App\Models\Scopes\CabangScope;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Konsolidasi pemasukan/pengeluaran/net per periode untuk kebutuhan setoran
 * ke pusat (Laporan Setoran Harian). Murni READ, tidak menulis data apapun,
 * dan sengaja dipisah dari service existing (PenjualanService/StokService)
 * supaya nol risiko ke fitur lain.
 *
 * Sumber angka pemasukan/pengeluaran/net = transaksi_keuangans, dengan pola
 * query (withoutGlobalScope(CabangScope::class) + filter cabang manual) PERSIS
 * seperti LaporanKeuanganController::arusKas() — supaya Net di sini selalu cocok
 * dengan Laporan Arus Kas untuk periode yang sama. Breakdown metode bayar
 * diambil dari orders karena kolom tipe_pembayaran cuma ada di situ.
 *
 * PENTING: pakai withoutGlobalScope(CabangScope::class) — BUKAN withoutGlobalScopes()
 * tanpa argumen. Bentuk tanpa argumen mematikan SEMUA global scope termasuk
 * SoftDeletingScope, sehingga transaksi dari order yang sudah dibatalkan
 * (soft-deleted) ikut ke-SUM sebagai omzet — bug nyata yang ditemukan di audit
 * produksi 2026-07-26 (Bug 1).
 */
class SetoranHarianService
{
    public function getRingkasan(Carbon $start, Carbon $end, ?int $cabangId = null): array
    {
        $pemasukan   = $this->hitungPemasukan($start, $end, $cabangId);
        $pengeluaran = $this->hitungPengeluaran($start, $end, $cabangId);

        return [
            'pemasukan'   => $pemasukan,
            'pengeluaran' => $pengeluaran,
            'net'         => $pemasukan - $pengeluaran,
            'total_order' => $this->countOrder($start, $end, $cabangId),
        ];
    }

    public function hitungPemasukan(Carbon $start, Carbon $end, ?int $cabangId = null): float
    {
        return (float) $this->baseTransaksiQuery($start, $end, $cabangId)
            ->where('tipe', TipeTransaksiKeuangan::Pemasukan)
            ->sum('jumlah');
    }

    public function hitungPengeluaran(Carbon $start, Carbon $end, ?int $cabangId = null): float
    {
        return (float) $this->baseTransaksiQuery($start, $end, $cabangId)
            ->where('tipe', TipeTransaksiKeuangan::Pengeluaran)
            ->sum('jumlah');
    }

    public function countOrder(Carbon $start, Carbon $end, ?int $cabangId = null): int
    {
        return $this->baseOrderQuery($start, $end, $cabangId)->count();
    }

    /**
     * Breakdown pemasukan per metode bayar (tunai/qris/transfer) — dari
     * orders (bukan transaksi_keuangans), karena kolom tipe_pembayaran cuma
     * ada di orders. Bisa sedikit beda dari Total Pemasukan kalau ada
     * pemasukan manual non-order di periode yang sama (jarang terjadi).
     */
    public function getBreakdownPemasukan(Carbon $start, Carbon $end, ?int $cabangId = null): array
    {
        $query = DB::table('orders')
            ->whereBetween('tanggal_order', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('deleted_at');
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $rows = $query->selectRaw('tipe_pembayaran, SUM(total_bayar) as total')
            ->groupBy('tipe_pembayaran')
            ->pluck('total', 'tipe_pembayaran');

        $result = [];
        foreach (TipePembayaran::cases() as $tp) {
            $result[$tp->value] = [
                'label' => $tp->label(),
                'total' => (float) ($rows[$tp->value] ?? 0),
            ];
        }
        return $result;
    }

    /**
     * Breakdown pengeluaran per kategori_pengeluaran. Data lama (NULL,
     * sebelum kolom ini ada) dikelompokkan terpisah sebagai
     * "Lain-lain (belum dikategorikan)" — bukan disamakan dengan 'lain_lain'
     * yang dipilih user secara eksplisit.
     */
    public function getBreakdownPengeluaran(Carbon $start, Carbon $end, ?int $cabangId = null): array
    {
        $query = DB::table('transaksi_keuangans')
            ->whereBetween('tanggal_transaksi', [$start->toDateString(), $end->toDateString()])
            ->where('tipe', TipeTransaksiKeuangan::Pengeluaran->value);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $rows = $query->selectRaw('kategori_pengeluaran, SUM(jumlah) as total')
            ->groupBy('kategori_pengeluaran')
            ->get();

        $result = [];
        foreach (KategoriPengeluaran::cases() as $kp) {
            $result[$kp->value] = ['label' => $kp->label(), 'total' => 0.0];
        }
        $result['_belum_dikategorikan'] = ['label' => 'Lain-lain (belum dikategorikan)', 'total' => 0.0];

        foreach ($rows as $row) {
            $key = $row->kategori_pengeluaran !== null ? $row->kategori_pengeluaran : '_belum_dikategorikan';
            if (!isset($result[$key])) {
                $key = '_belum_dikategorikan';
            }
            $result[$key]['total'] += (float) $row->total;
        }

        // Buang kategori yang totalnya nihil supaya tabel tidak penuh baris kosong
        return array_filter($result, fn ($r) => $r['total'] > 0);
    }

    /** Detail order untuk tab "Order" — dikelompokkan per tanggal (support range panjang) */
    public function getDetailOrder(Carbon $start, Carbon $end, ?int $cabangId = null)
    {
        return $this->baseOrderQuery($start, $end, $cabangId)
            ->with(['kasir', 'pelanggan'])
            ->orderBy('tanggal_order')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($order) => $order->tanggal_order->toDateString());
    }

    /** Detail transaksi pengeluaran untuk tab "Pengeluaran" — dikelompokkan per tanggal */
    public function getDetailPengeluaran(Carbon $start, Carbon $end, ?int $cabangId = null)
    {
        return $this->baseTransaksiQuery($start, $end, $cabangId)
            ->where('tipe', TipeTransaksiKeuangan::Pengeluaran)
            ->orderBy('tanggal_transaksi')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($t) => $t->tanggal_transaksi->toDateString());
    }

    private function baseTransaksiQuery(Carbon $start, Carbon $end, ?int $cabangId)
    {
        $query = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->whereBetween('tanggal_transaksi', [$start->toDateString(), $end->toDateString()]);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }
        return $query;
    }

    private function baseOrderQuery(Carbon $start, Carbon $end, ?int $cabangId)
    {
        $query = Order::withoutGlobalScopes()
            ->whereBetween('tanggal_order', [$start->toDateString(), $end->toDateString()])
            ->penjualanSah();
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }
        return $query;
    }
}
