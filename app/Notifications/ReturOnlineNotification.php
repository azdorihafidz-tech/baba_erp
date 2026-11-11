<?php

namespace App\Notifications;

use App\Models\ReturOnline;
use Illuminate\Notifications\Notification;

/** Lonceng ERP: member mengajukan retur pesanan gudang. */
class ReturOnlineNotification extends Notification
{
    public function __construct(private ReturOnline $retur)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->retur;

        return [
            'title' => 'Pengajuan Retur Online',
            'message' => ($r->member?->nama ?? 'Member') . " mengajukan retur {$r->nomor} (pesanan {$r->pesanan?->nomor}, Rp " . number_format((float) $r->nilai, 0, ',', '.') . ').',
            'icon' => 'bi-arrow-return-left', 'color' => 'warning', 'url' => route('toko-online.retur.show', $r), 'kategori' => 'toko_online',
        ];
    }
}
