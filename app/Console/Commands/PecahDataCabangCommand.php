<?php

namespace App\Console\Commands;

use App\Services\PemecahanDataCabangService;
use Illuminate\Console\Command;

/**
 * Pemecahan data barang per cabang (CLAUDE.md 4.39).
 *   php artisan erp:pecah-cabang --simulasi   → tampilkan rencana, tidak mengubah data
 *   php artisan erp:pecah-cabang              → jalankan
 *   php artisan erp:pecah-cabang --batalkan   → kembalikan seperti sebelum dipecah
 */
class PecahDataCabangCommand extends Command
{
    protected $signature = 'erp:pecah-cabang {--simulasi : Hanya tampilkan rencana} {--batalkan : Batalkan pemecahan}';

    protected $description = 'Pecah data barang, resep & Bumbu Pusat lama menjadi milik masing-masing cabang';

    public function handle(PemecahanDataCabangService $service): int
    {
        if ($this->option('batalkan')) {
            $hasil = $service->batalkan();
            $this->info("Pemecahan dibatalkan. Salinan dihapus: {$hasil['salinan_dihapus']}.");
            return self::SUCCESS;
        }

        if (! $service->perluDipecah()) {
            $this->info('Semua barang sudah punya cabang — tidak ada yang perlu dipecah.');
            return self::SUCCESS;
        }

        $this->line($service->laporanRencana());

        if ($this->option('simulasi')) {
            $this->warn('SIMULASI — tidak ada data yang diubah.');
            return self::SUCCESS;
        }

        $hasil = $service->jalankan();
        $this->info("Selesai. Barang: {$hasil['barang']}, Bumbu Pusat: {$hasil['bumbu']}, salinan baru: {$hasil['salinan']}, baris riwayat dipindah: {$hasil['baris_dipindah']}.");

        return self::SUCCESS;
    }
}
