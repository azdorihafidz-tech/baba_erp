<?php

namespace App\Notifications;

use App\Models\Karyawan;
use Illuminate\Notifications\Notification;

class KaryawanAbsenNotification extends Notification
{
    public function __construct(
        private Karyawan $karyawan,
        private string $tanggal
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $nama   = $this->karyawan->nama_lengkap;
        $jabatan = $this->karyawan->jabatan ?? 'Karyawan';

        return [
            'title'    => 'Karyawan Tidak Hadir Tanpa Keterangan',
            'message'  => "{$nama} ({$jabatan}) tidak hadir tanpa keterangan pada {$this->tanggal}.",
            'icon'     => 'bi-person-x-fill',
            'color'    => 'warning',
            'url'      => route('absensi.index'),
            'kategori' => 'hr',
        ];
    }
}
