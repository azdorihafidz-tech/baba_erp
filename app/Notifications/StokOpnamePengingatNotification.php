<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Lonceng ERP akhir bulan (H-3): lokasi yang belum stok opname bulan ini. Satu kali per orang per hari. */
class StokOpnamePengingatNotification extends Notification
{
    /** @param array<int, string> $lokasi nama lokasi yang belum opname */
    public function __construct(private array $lokasi, private string $bulan)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $n = count($this->lokasi);
        $daftar = implode(', ', array_slice($this->lokasi, 0, 5)) . ($n > 5 ? ' dan ' . ($n - 5) . ' lainnya' : '');

        return ['title' => 'Pengingat Stok Opname Akhir Bulan', 'message' => "{$n} lokasi belum stok opname {$this->bulan}: {$daftar}.",
            'icon' => 'bi-alarm', 'color' => 'warning', 'url' => route('stok-opname.dashboard'), 'kategori' => 'stok', 'pengingat_bulan' => $this->bulan];
    }
}
