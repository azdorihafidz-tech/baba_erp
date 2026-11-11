<?php

namespace App\Notifications;

use App\Models\PesananOnline;
use Illuminate\Notifications\Notification;

/** Lonceng ERP untuk staf Pesanan Online: pesanan baru, bukti transfer diunggah, dibatalkan pembeli. */
class PesananOnlineNotification extends Notification
{
    /** @param string $kejadian baru|bukti|batal */
    public function __construct(private PesananOnline $pesanan, private string $kejadian)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $p = $this->pesanan;
        $nama = $p->member?->nama ?? ('member #' . $p->member_id);
        $total = 'Rp ' . number_format((float) $p->total, 0, ',', '.');
        $url = route('toko-online.pesanan.show', $p);

        return match ($this->kejadian) {
            'bukti' => ['title' => 'Bukti Transfer Pesanan Online', 'message' => "{$nama} mengunggah bukti transfer pesanan {$p->nomor} ({$total}). Silakan dicek.",
                'icon' => 'bi-receipt', 'color' => 'info', 'url' => $url, 'kategori' => 'toko_online'],
            'batal' => ['title' => 'Pesanan Online Dibatalkan', 'message' => "Pesanan {$p->nomor} dibatalkan oleh {$nama}.",
                'icon' => 'bi-x-circle-fill', 'color' => 'secondary', 'url' => $url, 'kategori' => 'toko_online'],
            default => ['title' => 'Pesanan Online Baru', 'message' => "Pesanan {$p->nomor} dari {$nama}: {$p->item()->sum('qty')} barang, "
                . ($p->status === 'menunggu_ongkir' ? 'dikirim — isi ongkirnya.' : ($p->cara_bayar === 'tagihan' ? 'tagihan — siapkan barang.' : 'menunggu transfer.')),
                'icon' => 'bi-bag-check-fill', 'color' => 'success', 'url' => $url, 'kategori' => 'toko_online'],
        };
    }
}
