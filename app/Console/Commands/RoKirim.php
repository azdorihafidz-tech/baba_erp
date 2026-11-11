<?php

namespace App\Console\Commands;

use App\Services\RoService;
use Illuminate\Console\Command;

/** Kirim ulang catatan RO belanja online yang belum sampai ke aplikasi member (idempotent). */
class RoKirim extends Command
{
    protected $signature = 'ro:kirim';

    protected $description = 'Kirim catatan RO belanja online yang tertunda ke aplikasi member';

    public function handle(RoService $ro): int
    {
        $h = $ro->kirimTertunda();
        $this->info("RO terkirim: {$h['terkirim']}, masih gagal: {$h['gagal']}.");

        return self::SUCCESS;
    }
}
