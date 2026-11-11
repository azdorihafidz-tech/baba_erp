<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Notifications\Notification;

class PurchaseOrderNotification extends Notification
{
    /**
     * @param string $event  baru|disetujui|diterima|mendesak|mendesak_disetujui|mendesak_ditolak
     */
    public function __construct(
        private PurchaseOrder $po,
        private string $event
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $no       = $this->po->nomor_po;
        $supplier = $this->po->supplier?->nama_supplier ?? '-';
        $cabang   = $this->po->cabang?->nama_cabang ?? '-';

        return match ($this->event) {
            'baru' => [
                'title'    => 'PO Baru Menunggu Persetujuan',
                'message'  => "PO baru #{$no} dari {$supplier} menunggu persetujuan.",
                'icon'     => 'bi-cart-plus-fill',
                'color'    => 'info',
                'url'      => route('pembelian.show', $this->po),
                'kategori' => 'pembelian',
            ],
            'disetujui' => [
                'title'    => 'PO Disetujui',
                'message'  => "PO #{$no} dari {$supplier} telah disetujui.",
                'icon'     => 'bi-check-circle-fill',
                'color'    => 'success',
                'url'      => route('pembelian.show', $this->po),
                'kategori' => 'pembelian',
            ],
            'diterima' => [
                'title'    => 'Barang PO Diterima',
                'message'  => "Barang PO #{$no} dari {$supplier} telah diterima di Gudang Pusat.",
                'icon'     => 'bi-box-seam-fill',
                'color'    => 'success',
                'url'      => route('pembelian.show', $this->po),
                'kategori' => 'pembelian',
            ],
            'mendesak' => [
                'title'    => 'Pembelian Mendesak Perlu Approval',
                'message'  => "Pembelian mendesak #{$no} oleh {$cabang} menunggu approval.",
                'icon'     => 'bi-exclamation-triangle-fill',
                'color'    => 'warning',
                'url'      => route('pembelian.show', $this->po),
                'kategori' => 'pembelian',
            ],
            'mendesak_disetujui' => [
                'title'    => 'Pembelian Mendesak Disetujui',
                'message'  => "Pembelian mendesak #{$no} telah disetujui.",
                'icon'     => 'bi-check-circle-fill',
                'color'    => 'success',
                'url'      => route('pembelian.show', $this->po),
                'kategori' => 'pembelian',
            ],
            'mendesak_ditolak' => [
                'title'    => 'Pembelian Mendesak Ditolak',
                'message'  => "Pembelian mendesak #{$no} ditolak.",
                'icon'     => 'bi-x-circle-fill',
                'color'    => 'danger',
                'url'      => route('pembelian.show', $this->po),
                'kategori' => 'pembelian',
            ],
            default => [
                'title'    => 'Update Purchase Order',
                'message'  => "Status PO #{$no} telah diperbarui.",
                'icon'     => 'bi-arrow-repeat',
                'color'    => 'info',
                'url'      => route('pembelian.show', $this->po),
                'kategori' => 'pembelian',
            ],
        };
    }
}
