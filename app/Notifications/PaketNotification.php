<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Lonceng ERP untuk Tim IT tentang Paket & Langganan (mis. segel lisensi tidak cocok). Porting Dimsum 4.53. */
class PaketNotification extends Notification
{
    public function __construct(private string $judul, private string $pesan, private string $url, private string $warna = 'danger')
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->judul, 'message' => $this->pesan, 'icon' => 'bi-shield-exclamation', 'color' => $this->warna, 'url' => $this->url, 'kategori' => 'paket'];
    }
}
