<?php

namespace App\Notifications;

use App\Models\StockTransfer;
use Illuminate\Notifications\Notification;

class StockTransferNotification extends Notification
{
    /**
     * @param string $event  dikirim|diterima
     */
    public function __construct(
        private StockTransfer $transfer,
        private string $event
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $no      = $this->transfer->nomor_transfer;
        $dari    = $this->transfer->dariLokasi?->nama_cabang ?? '-';
        $tujuan  = $this->transfer->keLokasi?->nama_cabang ?? '-';

        return match ($this->event) {
            'dikirim' => [
                'title'    => 'Transfer Stok Dikirim',
                'message'  => "Transfer #{$no} dari {$dari} sedang dalam perjalanan ke {$tujuan}.",
                'icon'     => 'bi-truck',
                'color'    => 'info',
                'url'      => route('stock-transfer.show', $this->transfer),
                'kategori' => 'stok',
            ],
            'diterima' => [
                'title'    => 'Transfer Stok Diterima',
                'message'  => "Transfer #{$no} telah diterima oleh {$tujuan}.",
                'icon'     => 'bi-box-seam-fill',
                'color'    => 'success',
                'url'      => route('stock-transfer.show', $this->transfer),
                'kategori' => 'stok',
            ],
            default => [
                'title'    => 'Update Transfer Stok',
                'message'  => "Status transfer #{$no} telah diperbarui.",
                'icon'     => 'bi-arrow-repeat',
                'color'    => 'info',
                'url'      => route('stock-transfer.show', $this->transfer),
                'kategori' => 'stok',
            ],
        };
    }
}
