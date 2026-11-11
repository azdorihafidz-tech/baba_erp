<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class KeuanganNotification extends Notification
{
    /**
     * @param string $event  kas_belum_tutup|selisih_kas|bep_tercapai|bep_warning
     */
    public function __construct(
        private string $event,
        private string $namaCabang,
        private int $cabangId,
        private ?string $keterangan = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $cabang = $this->namaCabang;
        $ket    = $this->keterangan ?? '';

        return match ($this->event) {
            'kas_belum_tutup' => [
                'title'    => 'Kas Harian Belum Ditutup',
                'message'  => "Kas harian {$cabang} belum ditutup/rekonsiliasi.",
                'icon'     => 'bi-cash-coin',
                'color'    => 'warning',
                'url'      => route('keuangan.index'),
                'kategori' => 'keuangan',
            ],
            'selisih_kas' => [
                'title'    => 'Selisih Kas Ditemukan',
                'message'  => "Selisih kas ditemukan di {$cabang}." . ($ket ? " {$ket}" : ''),
                'icon'     => 'bi-exclamation-diamond-fill',
                'color'    => 'danger',
                'url'      => route('keuangan.index'),
                'kategori' => 'keuangan',
            ],
            'bep_tercapai' => [
                'title'    => 'BEP Tercapai Bulan Ini!',
                'message'  => "BEP {$cabang} tercapai bulan ini!" . ($ket ? " {$ket}" : ''),
                'icon'     => 'bi-graph-up-arrow',
                'color'    => 'success',
                'url'      => route('bep.index'),
                'kategori' => 'keuangan',
            ],
            'bep_warning' => [
                'title'    => 'BEP Belum Tercapai',
                'message'  => "BEP {$cabang} belum tercapai mendekati akhir bulan." . ($ket ? " {$ket}" : ''),
                'icon'     => 'bi-graph-down-arrow',
                'color'    => 'warning',
                'url'      => route('bep.index'),
                'kategori' => 'keuangan',
            ],
            default => [
                'title'    => 'Update Keuangan',
                'message'  => "Ada pembaruan keuangan di {$cabang}." . ($ket ? " {$ket}" : ''),
                'icon'     => 'bi-cash-stack',
                'color'    => 'info',
                'url'      => route('keuangan.index'),
                'kategori' => 'keuangan',
            ],
        };
    }
}
