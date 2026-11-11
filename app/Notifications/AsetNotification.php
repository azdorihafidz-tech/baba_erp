<?php

namespace App\Notifications;

use App\Models\Asset;
use Illuminate\Notifications\Notification;

class AsetNotification extends Notification
{
    /**
     * @param string $event  pembelian|maintenance|rusak|mutasi
     */
    public function __construct(
        private Asset $asset,
        private string $event,
        private ?string $keterangan = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $nama   = $this->asset->nama_aset;
        $kode   = $this->asset->kode_aset;
        $lokasi = $this->asset->lokasi?->nama_cabang ?? '-';
        $ket    = $this->keterangan ? " {$this->keterangan}" : '';

        return match ($this->event) {
            'pembelian' => [
                'title'    => 'Pengajuan Pembelian Aset',
                'message'  => "Aset baru ditambahkan: {$nama} ({$kode}) di {$lokasi}.{$ket}",
                'icon'     => 'bi-building-add',
                'color'    => 'info',
                'url'      => route('aset.show', $this->asset),
                'kategori' => 'aset',
            ],
            'maintenance' => [
                'title'    => 'Aset Perlu Maintenance',
                'message'  => "Aset {$nama} ({$kode}) di {$lokasi} sudah waktunya maintenance rutin.{$ket}",
                'icon'     => 'bi-tools',
                'color'    => 'warning',
                'url'      => route('aset.show', $this->asset),
                'kategori' => 'aset',
            ],
            'rusak' => [
                'title'    => 'Aset Dilaporkan Rusak',
                'message'  => "Aset {$nama} ({$kode}) di {$lokasi} dilaporkan rusak.{$ket}",
                'icon'     => 'bi-exclamation-triangle-fill',
                'color'    => 'danger',
                'url'      => route('aset.show', $this->asset),
                'kategori' => 'aset',
            ],
            'mutasi' => [
                'title'    => 'Pengajuan Mutasi Aset',
                'message'  => "Pengajuan mutasi aset {$nama} ({$kode}) dari {$lokasi}.{$ket}",
                'icon'     => 'bi-arrow-left-right',
                'color'    => 'info',
                'url'      => route('aset.show', $this->asset),
                'kategori' => 'aset',
            ],
            default => [
                'title'    => 'Update Aset',
                'message'  => "Ada pembaruan pada aset {$nama} ({$kode}).{$ket}",
                'icon'     => 'bi-building-gear',
                'color'    => 'info',
                'url'      => route('aset.show', $this->asset),
                'kategori' => 'aset',
            ],
        };
    }
}
