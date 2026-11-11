<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePwaIcons extends Command
{
    protected $signature = 'generate:pwa-icons {--source= : Path sumber logo (default: resources/logo-source/Logo_Dmentai.png)}';
    protected $description = 'Generate 8 ukuran PWA icon (72-512px) dari logo sumber ke public/images/icons/';

    /** Ukuran icon PWA yang wajib ada — sesuai config/laravelpwa.php */
    private const SIZES = [72, 96, 128, 144, 152, 192, 384, 512];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Extension GD tidak aktif. Aktifkan dulu di php.ini (extension=gd).');
            return Command::FAILURE;
        }

        $sourcePath = $this->option('source') ?? resource_path('logo-source/Logo_Dmentai.png');

        if (! file_exists($sourcePath)) {
            $this->error("File sumber tidak ditemukan: {$sourcePath}");
            return Command::FAILURE;
        }

        $outputDir = public_path('images/icons');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->info("Sumber: {$sourcePath}");
        $this->info("Output: {$outputDir}");

        $sourceInfo = getimagesize($sourcePath);
        if ($sourceInfo === false) {
            $this->error('Gagal membaca dimensi gambar sumber.');
            return Command::FAILURE;
        }
        [$srcWidth, $srcHeight] = $sourceInfo;

        // Naikkan memory_limit sementara — gambar sumber resolusi tinggi (misal 11590x11590)
        // butuh ~500MB+ hanya untuk buffer pixel RGBA.
        $originalLimit = ini_get('memory_limit');
        ini_set('memory_limit', '2048M');

        $src = imagecreatefrompng($sourcePath);
        if ($src === false) {
            $this->error('Gagal membuka file PNG sumber (pastikan format PNG valid).');
            ini_set('memory_limit', $originalLimit);
            return Command::FAILURE;
        }

        $berhasil = 0;
        $gagal = 0;

        foreach (self::SIZES as $size) {
            $dst = imagecreatetruecolor($size, $size);
            imagesavealpha($dst, true);
            imagealphablending($dst, false);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
            imagealphablending($dst, true);

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, $srcWidth, $srcHeight);

            $outputPath = "{$outputDir}/icon-{$size}x{$size}.png";
            $ok = imagepng($dst, $outputPath, 6);
            imagedestroy($dst);

            if ($ok) {
                $this->line("  ✓ icon-{$size}x{$size}.png (" . number_format(filesize($outputPath) / 1024, 1) . " KB)");
                $berhasil++;
            } else {
                $this->error("  ✗ Gagal generate icon-{$size}x{$size}.png");
                $gagal++;
            }
        }

        imagedestroy($src);
        // Tidak perlu restore memory_limit ke nilai semula — proses artisan
        // ini akan langsung selesai, dan mencoba menurunkan limit ke bawah
        // usage saat ini (sisa alokasi GD yang belum di-reclaim OS) akan
        // selalu gagal walau memory sudah di-imagedestroy().
        unset($originalLimit);

        $this->info("Selesai. Berhasil: {$berhasil}, Gagal: {$gagal}.");

        return $gagal > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
