<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaksi;
use App\Notifications\RecurringNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRecurringTransaksiCommand extends Command
{
    protected $signature = 'recurring:generate {--date= : Tanggal spesifik (Y-m-d)}';
    protected $description = 'Generate recurring transactions yang jatuh tempo (termasuk yang terlewat), 1x per periode';

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        // 4.51 — aturan bersama (RecurringTransaksi::jalankanYangJatuhTempo): frekuensi
        // harian/mingguan/bulanan/tahunan, 1 transaksi per periode, tidak dobel dgn pemicu on-request.
        $generated = RecurringTransaksi::jalankanYangJatuhTempo($date);

        $this->info("Selesai. {$generated} transaksi berulang dibuat untuk " . $date->format('d/m/Y') . '.');

        if ($generated > 0) {
            try {
                NotificationService::send(
                    NotificationService::getOwnerAndAdminPusat(),
                    new RecurringNotification($generated)
                );
            } catch (\Exception $e) {
                Log::warning('Gagal kirim notifikasi recurring: ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
