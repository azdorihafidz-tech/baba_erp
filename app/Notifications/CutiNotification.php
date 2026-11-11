<?php

namespace App\Notifications;

use App\Models\Cuti;
use Illuminate\Notifications\Notification;

class CutiNotification extends Notification
{
    /**
     * @param string $event  pengajuan|disetujui|ditolak
     */
    public function __construct(
        private Cuti $cuti,
        private string $event
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $nama       = $this->cuti->karyawan?->nama_lengkap ?? '-';
        $cabang     = $this->cuti->karyawan?->cabang?->nama_cabang ?? '-';
        $tglMulai   = $this->cuti->tanggal_mulai?->format('d M Y') ?? '-';
        $tglSelesai = $this->cuti->tanggal_selesai?->format('d M Y') ?? '-';

        return match ($this->event) {
            'pengajuan' => [
                'title'    => 'Pengajuan Cuti Baru',
                'message'  => "Pengajuan cuti dari {$nama} ({$cabang}) tanggal {$tglMulai} - {$tglSelesai}.",
                'icon'     => 'bi-calendar-plus-fill',
                'color'    => 'info',
                'url'      => route('cuti.index'),
                'kategori' => 'hr',
            ],
            'disetujui' => [
                'title'    => 'Cuti Disetujui',
                'message'  => "Cuti Anda tanggal {$tglMulai} - {$tglSelesai} telah disetujui.",
                'icon'     => 'bi-calendar-check-fill',
                'color'    => 'success',
                'url'      => route('cuti.index'),
                'kategori' => 'hr',
            ],
            'ditolak' => [
                'title'    => 'Cuti Ditolak',
                'message'  => "Cuti Anda tanggal {$tglMulai} - {$tglSelesai} ditolak.",
                'icon'     => 'bi-calendar-x-fill',
                'color'    => 'danger',
                'url'      => route('cuti.index'),
                'kategori' => 'hr',
            ],
            default => [
                'title'    => 'Update Pengajuan Cuti',
                'message'  => "Status cuti {$nama} telah diperbarui.",
                'icon'     => 'bi-calendar-event-fill',
                'color'    => 'info',
                'url'      => route('cuti.index'),
                'kategori' => 'hr',
            ],
        };
    }
}
