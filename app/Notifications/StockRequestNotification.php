<?php

namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Notifications\Notification;

class StockRequestNotification extends Notification
{
    /**
     * @param string $event  baru|disetujui|ditolak|dikirim|diterima
     */
    public function __construct(
        private StockRequest $stockRequest,
        private string $event,
        private ?string $alasan = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $no      = $this->stockRequest->nomor_request;
        $cabang  = $this->stockRequest->cabang?->nama_cabang ?? '-';

        return match ($this->event) {
            'baru' => [
                'title'    => 'Permintaan Bahan Baru',
                'message'  => "Permintaan bahan baru #{$no} dari {$cabang} menunggu persetujuan.",
                'icon'     => 'bi-inbox-fill',
                'color'    => 'info',
                'url'      => route('stock-request.show', $this->stockRequest),
                'kategori' => 'stok',
            ],
            'disetujui' => [
                'title'    => 'Permintaan Bahan Disetujui',
                'message'  => "Permintaan bahan #{$no} telah disetujui oleh Gudang.",
                'icon'     => 'bi-check-circle-fill',
                'color'    => 'success',
                'url'      => route('stock-request.show', $this->stockRequest),
                'kategori' => 'stok',
            ],
            'ditolak' => [
                'title'    => 'Permintaan Bahan Ditolak',
                'message'  => "Permintaan bahan #{$no} ditolak." . ($this->alasan ? " Alasan: {$this->alasan}" : ''),
                'icon'     => 'bi-x-circle-fill',
                'color'    => 'danger',
                'url'      => route('stock-request.show', $this->stockRequest),
                'kategori' => 'stok',
            ],
            'dikirim' => [
                'title'    => 'Transfer Bahan Dikirim',
                'message'  => "Transfer bahan dari Gudang Pusat dalam perjalanan ke {$cabang}.",
                'icon'     => 'bi-truck',
                'color'    => 'info',
                'url'      => route('stock-request.show', $this->stockRequest),
                'kategori' => 'stok',
            ],
            default => [
                'title'    => 'Update Permintaan Bahan',
                'message'  => "Status permintaan bahan #{$no} telah diperbarui.",
                'icon'     => 'bi-arrow-repeat',
                'color'    => 'info',
                'url'      => route('stock-request.show', $this->stockRequest),
                'kategori' => 'stok',
            ],
        };
    }
}
