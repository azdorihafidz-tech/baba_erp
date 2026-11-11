<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaksi;
use App\Notifications\RecurringNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateRecurringTransaksiCommand extends Command
{
    protected $signature = 'recurring:generate {--date= : Tanggal spesifik (Y-m-d)}';
    protected $description = 'Generate recurring transactions yang jatuh tempo hari ini';

    public function handle(): int
    {
        $date      = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $templates = RecurringTransaksi::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->jatuhTempo($date)
            ->get();

        if ($templates->isEmpty()) {
            $this->info('Tidak ada recurring yang jatuh tempo pada ' . $date->format('d/m/Y'));
            return Command::SUCCESS;
        }

        $generated = 0;
        foreach ($templates as $template) {
            try {
                DB::transaction(function () use ($template, $date) {
                    $template->generateTransaksi($date);
                    $template->update(['tanggal_terakhir_generate' => $date->toDateString()]);
                });
                $this->info("  ✓ {$template->nama_template} (Rp " . number_format($template->jumlah, 0, ',', '.') . ')');
                $generated++;
            } catch (\Exception $e) {
                $this->error("  ✗ {$template->nama_template}: " . $e->getMessage());
                Log::error("Recurring generate gagal [{$template->id}]: " . $e->getMessage());
            }
        }

        $this->info("Selesai. {$generated}/{$templates->count()} transaksi di-generate.");

        // Notifikasi ke Owner/Admin Pusat jika ada draft yang berhasil dibuat
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
