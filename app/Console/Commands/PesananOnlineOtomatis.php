<?php

namespace App\Console\Commands;

use App\Services\PesananOnlineService;
use Illuminate\Console\Command;

/** Pesanan online: diterima otomatis N hari setelah dikirim; batal otomatis bila tidak dibayar sampai batas waktu. */
class PesananOnlineOtomatis extends Command
{
    protected $signature = 'pesanan-online:otomatis';

    protected $description = 'Terima otomatis pesanan yang sudah lama dikirim & batalkan pesanan yang tidak dibayar';

    public function handle(PesananOnlineService $svc): int
    {
        $hasil = $svc->jalankanOtomatis();
        $this->info("Diterima otomatis: {$hasil['diterima']}, dibatalkan: {$hasil['batal']}.");

        return self::SUCCESS;
    }
}
