<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SlipGajiNotification extends Notification
{
    public function __construct(
        private string $periode,
        private int $penggajianId
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'    => 'Slip Gaji Tersedia',
            'message'  => "Slip gaji bulan {$this->periode} sudah tersedia. Silakan cek.",
            'icon'     => 'bi-receipt-cutoff',
            'color'    => 'success',
            'url'      => route('penggajian.show', $this->penggajianId),
            'kategori' => 'hr',
        ];
    }
}
