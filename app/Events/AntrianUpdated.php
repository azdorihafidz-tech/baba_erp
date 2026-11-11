<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AntrianUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $aksi,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('antrian.' . $this->order->cabang_id),
            new Channel('tv-antrian.' . $this->order->cabang_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'antrian.updated';
    }

    public function broadcastWith(): array
    {
        $order = $this->order;
        $now   = now();

        return [
            'id'                    => $order->id,
            'nomor_antrian'         => $order->nomor_antrian,
            'nomor_antrian_pad'     => str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT),
            'nama_pelanggan'        => $order->nama_pelanggan ?: 'Walk-in',
            'berat_daging_kg'       => $order->berat_daging_kg
                ? number_format((float) $order->berat_daging_kg, 2) . ' kg'
                : null,
            'catatan_produksi'      => $order->catatan_produksi,
            'status'                => $order->status_produksi?->value,
            'dikerjakan_oleh_nama'  => $order->dikerjakanOleh?->nama_lengkap,
            'dikerjakan_oleh_id'    => $order->dikerjakan_oleh_id,
            'durasi_menunggu_menit' => $order->created_at
                ? (int) $order->created_at->diffInMinutes($now)
                : null,
            'durasi_kerja_menit'    => $order->waktu_mulai_kerja
                ? (int) $order->waktu_mulai_kerja->diffInMinutes($now)
                : null,
            'waktu_mulai_label'     => $order->waktu_mulai_kerja
                ? $order->waktu_mulai_kerja->setTimezone('Asia/Jakarta')->format('H:i')
                : null,
            'aksi'                  => $this->aksi,
        ];
    }
}
