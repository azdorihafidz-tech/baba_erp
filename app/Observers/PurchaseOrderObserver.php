<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderHistory;
use App\Models\TransaksiKeuangan;

class PurchaseOrderObserver
{
    public function updating(PurchaseOrder $po): void
    {
        PurchaseOrderHistory::create([
            'purchase_order_id' => $po->id,
            'action'            => 'updated',
            'data_lama'         => $po->getOriginal(),
            'changed_by'        => auth()->id(),
            'changed_by_name'   => auth()->user()?->name,
            'changed_at'        => now(),
        ]);
    }

    /**
     * Sinkronisasi total_harga ke TransaksiKeuangan terkait.
     * Menggunakan query builder (mass update) untuk mencegah loop balik dari TransaksiKeuanganObserver.
     */
    public function updated(PurchaseOrder $po): void
    {
        if (!$po->wasChanged(['total_harga', 'nomor_po'])) return;

        TransaksiKeuangan::withoutEvents(function () use ($po) {
            TransaksiKeuangan::where('referensi_type', 'purchase_order')
                ->where('referensi_id', $po->id)
                ->update([
                    'jumlah'     => $po->total_harga,
                    'keterangan' => 'PO ' . $po->nomor_po
                        . ($po->supplier?->nama_supplier ? ' — ' . $po->supplier->nama_supplier : ''),
                ]);
        });
    }

    public function deleting(PurchaseOrder $po): void
    {
        PurchaseOrderHistory::create([
            'purchase_order_id' => $po->id,
            'action'            => 'deleted',
            'data_lama'         => $po->toArray(),
            'changed_by'        => auth()->id(),
            'changed_by_name'   => auth()->user()?->name,
            'changed_at'        => now(),
        ]);
    }
}
