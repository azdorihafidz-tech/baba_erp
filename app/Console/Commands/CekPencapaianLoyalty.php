<?php

namespace App\Console\Commands;

use App\Services\LoyaltyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CekPencapaianLoyalty extends Command
{
    protected $signature = 'loyalty:cek-pencapaian';
    protected $description = 'Cek pelanggan yang baru mencapai target program loyalty aktif (idempotent)';

    public function handle(LoyaltyService $service): int
    {
        $this->info('Cek pencapaian program loyalty...');

        $baru = $service->cekPencapaianBaru();

        foreach ($baru as $pencapaian) {
            $this->line("  Pelanggan #{$pencapaian->pelanggan_id} tercapai program #{$pencapaian->loyalty_program_id} — {$pencapaian->progress_kg} kg");
        }

        $this->info('Selesai. Pencapaian baru: ' . count($baru) . '.');
        Log::info('Cek pencapaian loyalty: ' . count($baru) . ' pencapaian baru ditemukan.');

        return Command::SUCCESS;
    }
}
