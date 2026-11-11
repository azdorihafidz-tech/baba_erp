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
     * Sinkronisasi total_bayar dan keterangan ke TransaksiKeuangan terkait.
     * Menggunakan query builder (mass update) untuk mencegah loop balik dari TransaksiKeuanganObserver.
     */
    public function updated(Order $order): void
    {
        if (!$order->wasChanged(['total_bayar', 'nomor_order', 'nama_pelanggan'])) return;

        TransaksiKeuangan::withoutEvents(function () use ($order) {
            TransaksiKeuangan::where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->update([
                    'jumlah'     => $order->total_bayar,
                    'keterangan' => 'Order ' . $order->nomor_order
                        . ($order->nama_pelanggan ? ' — ' . $order->nama_pelanggan : ''),
                ]);
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
