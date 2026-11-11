<?php

namespace App\Listeners;

use App\Events\NewBellNotification;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;

class SendBellPusherSignal
{
    public function handle(NotificationSent $event): void
    {
        // Hanya broadcast saat notifikasi ke channel database untuk User model
        if ($event->channel !== 'database') {
            return;
        }

        if (!($event->notifiable instanceof User)) {
            return;
        }

        // Skip jika Pusher tidak dikonfigurasi (env lokal tanpa credentials)
        if (!config('broadcasting.connections.pusher.key')) {
            return;
        }

        try {
            NewBellNotification::dispatch($event->notifiable->id);
        } catch (\Exception $e) {
            // Gagal broadcast jangan sampai break notifikasi database yang sudah berhasil
            \Illuminate\Support\Facades\Log::warning(
                'Bell Pusher broadcast failed: ' . $e->getMessage(),
                ['user_id' => $event->notifiable->id]
            );
        }
    }
}
