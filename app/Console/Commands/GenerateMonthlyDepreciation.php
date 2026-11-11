<?php

namespace App\Console\Commands;

use App\Services\AssetDepreciationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyDepreciation extends Command
{
    protected $signature = 'aset:generate-depresiasi {--periode= : Periode spesifik (Y-m), default bulan berjalan}';
    protected $description = 'Generate penyusutan bulanan untuk semua aset aktif (idempotent)';

    public function handle(AssetDepreciationService $service): int
    {
        $periode = $this->option('periode') ?? now()->format('Y-m');

        $this->info("Generate penyusutan periode {$periode}...");

        $hasil = $service->generateUntukPeriode($periode);

        foreach ($hasil['detail'] as $line) {
            $this->line("  {$line}");
        }

        $this->info("Selesai. Berhasil: {$hasil['berhasil']}, Dilewati: {$hasil['dilewati']}, Gagal: {$hasil['gagal']}.");

        Log::info("Auto depresiasi periode {$periode}: {$hasil['berhasil']} berhasil, {$hasil['dilewati']} dilewati, {$hasil['gagal']} gagal.");

        return Command::SUCCESS;
    }
}
