<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar saat ada perubahan data keuangan/penjualan pada tanggal yang
 * sudah di-closing (lihat App\Support\ClosingLock). Dirender jadi pesan
 * ramah di bootstrap/app.php — bukan halaman error 500.
 */
class PeriodeTerkunciException extends RuntimeException
{
}
