<?php

namespace App\Notifications;

use App\Models\TransaksiKeuangan;
use Illuminate\Notifications\Notification;

class SetoranNotification extends Notification
{
    /**
     * @param string $tipe  baru|diterima|ditolak
     */
    public function __construct(
        private TransaksiKeuangan $setoran,
        private string $tipe
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $jumlah  = 'Rp ' . number_format((float) $this->setoran->jumlah, 0, ',', '.');
        $cabang  = $this->setoran->cabang?->nama_cabang ?? 'Cabang';
        $alasan  = $this->setoran->alasan_tolak_setoran ?? '-';
        $url     = route('setoran.show', $this->setoran->id);

        return match ($this->tipe) {
            'baru' => [
                'title'    => 'Setoran Baru Menunggu Konfirmasi',
                'message'  => "Setoran {$jumlah} dari {$cabang} menunggu konfirmasi penerimaan.",
                'icon'     => 'bi-cash-stack',
                'color'    => 'warning',
                'url'      => $url,
                'kategori' => 'keuangan',
            ],
            'diterima' => [
                'title'    => 'Setoran Diterima',
                'message'  => "Setoran {$jumlah} dari {$cabang} telah dikonfirmasi diterima.",
                'icon'     => 'bi-check-circle-fill',
                'color'    => 'success',
                'url'      => $url,
                'kategori' => 'keuangan',
            ],
            'ditolak' => [
                'title'    => 'Setoran Ditolak',
                'message'  => "Setoran {$jumlah} dari {$cabang} ditolak. Alasan: {$alasan}",
                'icon'     => 'bi-x-circle-fill',
                'color'    => 'danger',
                'url'      => $url,
                'kategori' => 'keuangan',
            ],
            default => [
                'title'    => 'Update Setoran',
                'message'  => "Status setoran {$jumlah} dari {$cabang} telah diperbarui.",
                'icon'     => 'bi-arrow-repeat',
                'color'    => 'info',
                'url'      => $url,
                'kategori' => 'keuangan',
            ],
        };
    }
}
