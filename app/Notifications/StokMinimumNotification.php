<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class StokMinimumNotification extends Notification
{
    public function __construct(
        private string $namaItem,
        private string $namaCabang,
        private float $qtySaat,
        private float $qtyMinimum,
        private int $cabangId
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'    => 'Stok Mendekati Minimum',
            'message'  => "Stok {$this->namaItem} di {$this->namaCabang} tinggal {$this->qtySaat} (minimum: {$this->qtyMinimum})",
            'icon'     => 'bi-exclamation-triangle-fill',
            'color'    => 'warning',
            'url'      => route('stok.index'),
            'kategori' => 'stok',
        ];
    }
}
