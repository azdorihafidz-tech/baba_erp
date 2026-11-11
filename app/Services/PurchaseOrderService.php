<?php

namespace App\Services;

use App\Enums\StatusPurchaseOrder;
use App\Models\Item;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private StokService $stokService) {}

    public function approve(PurchaseOrder $po): void
    {
        $po->update([
            'status'      => StatusPurchaseOrder::Disetujui,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    public function kirimSupplier(PurchaseOrder $po): void
    {
        // tanggal_kirim: murni tambahan timestamp untuk hitung umur PO di
        // Dashboard PO — tidak mengubah validasi/behavior transisi status apapun.
        $po->update([
            'status'        => StatusPurchaseOrder::DikirimSupplier,
            'tanggal_kirim' => now(),
        ]);
    }

    public function terima(PurchaseOrder $po, array $qtyTerimaPerItem): void
    {
        DB::transaction(function () use ($po, $qtyTerimaPerItem) {
            foreach ($po->items as $poItem) {
                $qty = (float) ($qtyTerimaPerItem[$poItem->id] ?? $poItem->qty_pesan);
                $poItem->update(['qty_terima' => $qty]);

                // Tambah stok di lokasi tujuan (cabang_id PO) + buat batch FIFO
                $this->stokService->masuk(
                    $poItem->item_id,
                    $po->cabang_id,
                    $qty,
                    'Penerimaan PO ' . $po->nomor_po,
                    'purchase_order',
                    $po->id,
                    (float) $poItem->harga_satuan, // FIFO: harga beli aktual per unit
                );

                // Harga dari PO dicatat sbg INFO "Harga PO Terakhir". Harga
                // Standar (`harga_beli_terakhir`) TIDAK ditimpa lagi — hanya
                // diisi kalau masih kosong (pembelian pertama). HPP tetap FIFO
                // per batch di atas (CLAUDE.md 4.38).
                $master = Item::find($poItem->item_id);
                if ($master) {
                    $update = [
                        'harga_po_terakhir'    => $poItem->harga_satuan,
                        'harga_po_terakhir_at' => now(),
                    ];
                    if ((float) ($master->harga_beli_terakhir ?? 0) <= 0) {
                        $update['harga_beli_terakhir'] = $poItem->harga_satuan;
                    }
                    $master->update($update);
                }
            }

            $po->update([
                'status'         => StatusPurchaseOrder::Diterima,
                'tanggal_terima' => now()->toDateString(),
            ]);
        });
    }

    public function batalkan(PurchaseOrder $po): void
    {
        $po->update(['status' => StatusPurchaseOrder::Dibatalkan]);
    }

    public function generateNomorPo(): string
    {
        $prefix    = 'PO-' . date('Ymd');
        $lastNomor = PurchaseOrder::withTrashed()
            ->where('nomor_po', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('nomor_po');
        $seq = 1;
        if ($lastNomor && preg_match('/(\d+)$/', $lastNomor, $m)) {
            $seq = ((int) $m[1]) + 1;
        }
        return $prefix . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
