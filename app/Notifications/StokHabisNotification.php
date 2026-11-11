<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class StokHabisNotification extends Notification
{
    public function __construct(
        private string $namaItem,
        private string $namaCabang,
        private int $cabangId
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'    => 'Stok HABIS!',
            'message'  => "Stok {$this->namaItem} di {$this->namaCabang} sudah HABIS (0)!",
            'icon'     => 'bi-exclamation-circle-fill',
            'color'    => 'danger',
            'url'      => route('stok.index'),
            'kategori' => 'stok',
        ];
    }
}
