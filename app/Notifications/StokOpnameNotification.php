<?php

namespace App\Notifications;

use App\Models\StokOpname;
use Illuminate\Notifications\Notification;

/** Lonceng ERP stok opname: diajukan (ke penyetuju), disetujui / ditolak (ke pengaju, pembuat & penghitung). */
class StokOpnameNotification extends Notification
{
    /** @param string $kejadian diajukan|disetujui|ditolak */
    public function __construct(private StokOpname $o, private string $kejadian, private string $pelaku)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $o = $this->o;
        $lokasi = $o->cabang?->nama_cabang ?? 'lokasi';
        $url = route('stok-opname.show', $o);

        return match ($this->kejadian) {
            'disetujui' => ['title' => 'Stok Opname Disetujui', 'message' => "Opname {$o->nomor} ({$lokasi}) disetujui {$this->pelaku}. Stok sudah disesuaikan.",
                'icon' => 'bi-clipboard-check-fill', 'color' => 'success', 'url' => $url, 'kategori' => 'stok'],
            'ditolak' => ['title' => 'Stok Opname Ditolak', 'message' => "Opname {$o->nomor} ({$lokasi}) ditolak {$this->pelaku}: {$o->alasan_tolak}",
                'icon' => 'bi-clipboard-x-fill', 'color' => 'danger', 'url' => $url, 'kategori' => 'stok'],
            default => ['title' => 'Stok Opname Menunggu Persetujuan', 'message' => "{$this->pelaku} mengajukan opname {$o->nomor} ({$lokasi}). Periksa selisihnya lalu setujui atau tolak.",
                'icon' => 'bi-clipboard-data-fill', 'color' => 'warning', 'url' => $url, 'kategori' => 'stok'],
        };
    }
}
