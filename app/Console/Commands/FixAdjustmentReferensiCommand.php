<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAdjustmentReferensiCommand extends Command
{
    protected $signature   = 'adjustment:fix-referensi';
    protected $description = 'Perbaiki referensi_type adjustment yang masih berisi nama class PHP mentah';

    public function handle(): int
    {
        $batchFixed = DB::table('stock_batches')
            ->where('referensi_type', 'App\\Models\\StockMovement')
            ->whereNull('deleted_at')
            ->update(['referensi_type' => 'adjustment_masuk']);

        $trxFixed = DB::table('transaksi_keuangans')
            ->where('referensi_type', 'App\\Models\\StockMovement')
            ->whereNull('deleted_at')
            ->update(['referensi_type' => 'stock_movement']);

        $this->info("Selesai: {$batchFixed} stock_batches → 'adjustment_masuk'");
        $this->info("         {$trxFixed} transaksi_keuangans → 'stock_movement'");

        return Command::SUCCESS;
    }
}
