<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class PenjualanNotification extends Notification
{
    /**
     * @param string $event  target_tercapai|transaksi_besar
     */
    public function __construct(
        private Order $order,
        private string $event,
        private ?string $keterangan = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $cabang  = $this->order->cabang?->nama_cabang ?? '-';
        $nominal = 'Rp ' . number_format((float) $this->order->total_bayar, 0, ',', '.');
        $ket     = $this->keterangan ?? '';

        return match ($this->event) {
            'target_tercapai' => [
                'title'    => 'Target Penjualan Harian Tercapai!',
                'message'  => "Target penjualan harian {$cabang} tercapai! Omzet: {$nominal}.",
                'icon'     => 'bi-trophy-fill',
                'color'    => 'success',
                'url'      => route('penjualan.index'),
                'kategori' => 'penjualan',
            ],
            'transaksi_besar' => [
                'title'    => 'Transaksi Besar Masuk',
                'message'  => "Transaksi besar {$nominal} di {$cabang}." . ($ket ? " {$ket}" : ''),
                'icon'     => 'bi-currency-dollar',
                'color'    => 'info',
                'url'      => route('penjualan.show', $this->order),
                'kategori' => 'penjualan',
            ],
            default => [
                'title'    => 'Update Penjualan',
                'message'  => "Ada update penjualan di {$cabang}.",
                'icon'     => 'bi-cart-check-fill',
                'color'    => 'info',
                'url'      => route('penjualan.index'),
                'kategori' => 'penjualan',
            ],
        };
    }
}
