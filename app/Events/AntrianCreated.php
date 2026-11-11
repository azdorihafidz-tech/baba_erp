<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AntrianCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('antrian.' . $this->order->cabang_id),
            new Channel('tv-antrian.' . $this->order->cabang_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'antrian.created';
    }

    public function broadcastWith(): array
    {
        $order = $this->order;

        $itemsSummary = $order->relationLoaded('items')
            ? $order->items->map(fn ($i) => $i->nama_item . ' ' . $i->qty . ' ' . $i->satuan)->implode(', ')
            : '';

        return [
            'id'                    => $order->id,
            'nomor_antrian'         => $order->nomor_antrian,
            'nomor_antrian_pad'     => str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT),
            'nama_pelanggan'        => $order->nama_pelanggan ?: 'Walk-in',
            'berat_daging_kg'       => $order->berat_daging_kg
                ? number_format((float) $order->berat_daging_kg, 2) . ' kg'
                : null,
            'catatan_produksi'      => $order->catatan_produksi,
            'items_summary'         => $itemsSummary,
            'status'                => 'menunggu',
            'dikerjakan_oleh_nama'  => null,
            'dikerjakan_oleh_id'    => null,
            'durasi_menunggu_menit' => 0,
            'durasi_kerja_menit'    => null,
            'waktu_mulai_label'     => null,
        ];
    }
}
