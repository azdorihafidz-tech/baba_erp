<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\TransaksiKeuangan;

class OrderObserver
{
    public function updating(Order $order): void
    {
        OrderHistory::create([
            'order_id'        => $order->id,
            'action'          => 'updated',
            'data_lama'       => $order->getOriginal(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }

    /**
     * Sinkronisasi KETERANGAN Transaksi Keuangan order ini (nomor order / nama pelanggan).
     *
     * 4.50 — JUMLAH sengaja TIDAK disentuh lagi. Dulu jumlah setiap baris ditimpa
     * total_bayar lewat withoutEvents: saldo kas & order_payments (Setoran Kasir) tidak
     * ikut berubah, dan pada split payment setiap baris jadi bernilai total penuh.
     * Total order yang sudah dibayar sekarang tidak bisa diubah (PenjualanController::update);
     * order Pending belum punya transaksi keuangan.
     */
    public function updated(Order $order): void
    {
        if (!$order->wasChanged(['nomor_order', 'nama_pelanggan'])) return;

        $dasar = 'Order ' . $order->nomor_order . ($order->nama_pelanggan ? ' — ' . $order->nama_pelanggan : '');

        TransaksiKeuangan::withoutEvents(function () use ($order, $dasar) {
            TransaksiKeuangan::where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->get(['id', 'keterangan'])
                ->each(function ($t) use ($dasar) {
                    // split payment: pertahankan akhiran metode, mis. " (Tunai)"
                    $akhiran = preg_match('/ \((Tunai|Transfer Bank|Transfer|QRIS)\)$/u', (string) $t->keterangan, $m) ? $m[0] : '';
                    TransaksiKeuangan::whereKey($t->id)->update(['keterangan' => $dasar . $akhiran]);
                });
        });
    }

    public function deleting(Order $order): void
    {
        OrderHistory::create([
            'order_id'        => $order->id,
            'action'          => 'deleted',
            'data_lama'       => $order->toArray(),
            'changed_by'      => auth()->id(),
            'changed_by_name' => auth()->user()?->name,
            'changed_at'      => now(),
        ]);
    }
}
