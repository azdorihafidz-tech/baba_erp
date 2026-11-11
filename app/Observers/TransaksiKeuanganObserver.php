<?php

namespace App\Observers;

use App\Enums\TipeTransaksiKeuangan;
use App\Models\Kas;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransaksiKeuanganObserver
{
    /**
     * Sinkronisasi balik: jika jumlah berubah dan punya referensi ke Order/PO,
     * update total di parent agar konsisten (bidirectional sync).
     * Menggunakan withoutEvents() + query builder untuk mencegah infinite loop.
     */
    public function updated(TransaksiKeuangan $transaksi): void
    {
        if (!$transaksi->wasChanged('jumlah')) return;

        if ($transaksi->referensi_type === 'order' && $transaksi->referensi_id) {
            Order::withoutEvents(function () use ($transaksi) {
                Order::where('id', $transaksi->referensi_id)
                    ->update(['total_bayar' => $transaksi->jumlah]);
            });
        }

        if ($transaksi->referensi_type === 'purchase_order' && $transaksi->referensi_id) {
            PurchaseOrder::withoutEvents(function () use ($transaksi) {
                PurchaseOrder::where('id', $transaksi->referensi_id)
                    ->update(['total_harga' => $transaksi->jumlah]);
            });
        }
    }

    /**
     * Jika TransaksiKeuangan ber-referensi ke Order/PO dihapus via Eloquent,
     * cascade-hapus parent via raw DB (bukan Eloquent) agar tidak rekursif.
     * Catatan: KeuanganController sudah guard — hanya transaksi manual yang bisa
     * dihapus dari UI. Ini safety net untuk kode yang bypass controller.
     */
    public function deleted(TransaksiKeuangan $transaksi): void
    {
        if ($transaksi->referensi_type === 'order' && $transaksi->referensi_id) {
            $now = Carbon::now();
            DB::table('order_items')
                ->where('order_id', $transaksi->referensi_id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);
            DB::table('orders')
                ->where('id', $transaksi->referensi_id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);
        }

        if ($transaksi->referensi_type === 'purchase_order' && $transaksi->referensi_id) {
            $now = Carbon::now();
            DB::table('purchase_order_items')
                ->where('purchase_order_id', $transaksi->referensi_id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);
            DB::table('purchase_orders')
                ->where('id', $transaksi->referensi_id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);
        }
    }

    /**
     * Mirror dari deleted(): restore parent Order/PO ketika TransaksiKeuangan di-restore.
     * Untuk transaksi manual: kembalikan saldo kas yang sudah dibalik saat dihapus.
     */
    public function restored(TransaksiKeuangan $transaksi): void
    {
        // Transaksi manual: reapply saldo kas (kebalikan dari destroy())
        if (!$transaksi->referensi_type && $transaksi->kas_id) {
            $kas = Kas::find($transaksi->kas_id);
            if ($kas) {
                if ($transaksi->tipe === TipeTransaksiKeuangan::Pemasukan) {
                    $kas->increment('saldo_sekarang', (float) $transaksi->jumlah);
                } else {
                    $kas->decrement('saldo_sekarang', (float) $transaksi->jumlah);
                }
            }
        }

        if ($transaksi->referensi_type === 'order' && $transaksi->referensi_id) {
            DB::table('order_items')
                ->where('order_id', $transaksi->referensi_id)
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);
            DB::table('orders')
                ->where('id', $transaksi->referensi_id)
                ->update(['deleted_at' => null]);
        }

        if ($transaksi->referensi_type === 'purchase_order' && $transaksi->referensi_id) {
            DB::table('purchase_order_items')
                ->where('purchase_order_id', $transaksi->referensi_id)
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);
            DB::table('purchase_orders')
                ->where('id', $transaksi->referensi_id)
                ->update(['deleted_at' => null]);
        }
    }
}
