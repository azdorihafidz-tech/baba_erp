<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RecurringNotification extends Notification
{
    public function __construct(
        private int $jumlahDraft
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->jumlahDraft === 1 ? '1 transaksi' : "{$this->jumlahDraft} transaksi";

        return [
            'title'    => 'Draft Transaksi Recurring Dibuat',
            'message'  => "{$label} recurring otomatis telah dibuat dan menunggu review.",
            'icon'     => 'bi-arrow-repeat',
            'color'    => 'info',
            'url'      => route('recurring.index'),
            'kategori' => 'keuangan',
        ];
    }
}
