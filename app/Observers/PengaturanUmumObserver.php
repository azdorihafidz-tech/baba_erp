<?php

namespace App\Observers;

use App\Models\PengaturanUmum;
use App\Support\Branding;

/**
 * 4.44 Poin 3 (Identitas Usaha) — setiap Pengaturan Umum disimpan, cache
 * identitas usaha (App\Support\Branding) dihapus supaya login, sidebar, struk,
 * PDF & PWA langsung memakai nama/logo baru.
 *
 * Dulu (Tahap 7, 4.12 B3) observer ini menyalin logo ke public/images/logo.png
 * — file itu ikut git, jadi update program bisa menimpa logo & git pull di
 * server bentrok. Sekarang logo dibaca langsung dari tempat upload-nya.
 */
class PengaturanUmumObserver
{
    public function saved(PengaturanUmum $setting): void
    {
        app(Branding::class)->lupakan();
    }

    public function deleted(PengaturanUmum $setting): void
    {
        app(Branding::class)->lupakan();
    }
}
