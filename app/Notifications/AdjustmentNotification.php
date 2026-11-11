<?php

namespace App\Notifications;

use App\Models\Cabang;
use App\Models\Item;
use Illuminate\Notifications\Notification;

class AdjustmentNotification extends Notification
{
    /**
     * @param float  $selisih       positif = naik, negatif = turun
     * @param float  $nilaiTransaksi nilai rupiah perubahan (HPP turun atau nilai pasar naik)
     */
    public function __construct(
        private Item   $item,
        private float  $selisih,
        private float  $nilaiTransaksi,
        private string $alasan,
        private Cabang $cabang
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $nama   = $this->item->nama_item;
        $satuan = $this->item->satuan ?? '';
        $lokasi = $this->cabang->nama_cabang;
        $nilai  = 'Rp ' . number_format($this->nilaiTransaksi, 0, ',', '.');
        $qty    = number_format(abs($this->selisih), 2, ',', '.');

        if ($this->selisih < 0) {
            return [
                'title'    => 'Adjustment Stok Turun',
                'message'  => "📉 {$nama} di {$lokasi}: -{$qty} {$satuan} ({$nilai}). Alasan: {$this->alasan}",
                'icon'     => 'bi-arrow-down-circle-fill',
                'color'    => 'warning',
                'url'      => route('stok.index'),
                'kategori' => 'stok',
            ];
        }

        return [
            'title'    => 'Adjustment Stok Naik',
            'message'  => "📈 {$nama} di {$lokasi}: +{$qty} {$satuan} ({$nilai}). Alasan: {$this->alasan}",
            'icon'     => 'bi-arrow-up-circle-fill',
            'color'    => 'info',
            'url'      => route('stok.index'),
            'kategori' => 'stok',
        ];
    }
}
