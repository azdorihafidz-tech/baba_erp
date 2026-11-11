<?php

namespace App\Support;

use App\Exceptions\PeriodeTerkunciException;
use App\Models\Closing;
use Carbon\Carbon;

/**
 * Kunci periode closing. Tanggal D di cabang C terkunci kalau ada closing
 * berstatus 'final' milik cabang C yang periodenya memuat D (closing harian
 * maupun tutup buku bulanan). Closing keseluruhan (cabang_id NULL) tidak
 * mengunci apa pun.
 *
 * Sengaja TANPA cache statis: test memakai DatabaseTransactions (rollback
 * tidak memicu event model), dan query ini ber-index murah.
 */
class ClosingLock
{
    public static function closingYangMengunci(?int $cabangId, $tanggal): ?Closing
    {
        if (! $cabangId || ! $tanggal) {
            return null;
        }

        $tgl = $tanggal instanceof Carbon ? $tanggal->toDateString() : Carbon::parse($tanggal)->toDateString();

        return Closing::final()
            ->where('cabang_id', $cabangId)
            ->whereDate('periode_mulai', '<=', $tgl)
            ->whereDate('periode_selesai', '>=', $tgl)
            ->orderByDesc('id')
            ->first();
    }

    public static function terkunci(?int $cabangId, $tanggal): bool
    {
        return self::closingYangMengunci($cabangId, $tanggal) !== null;
    }

    /**
     * @throws PeriodeTerkunciException
     */
    public static function pastikanTerbuka(?int $cabangId, $tanggal, string $konteks = 'Data'): void
    {
        $closing = self::closingYangMengunci($cabangId, $tanggal);
        if (! $closing) {
            return;
        }

        $tgl = Carbon::parse($tanggal)->format('d/m/Y');
        $cabang = $closing->cabang?->nama_cabang ?? '-';

        throw new PeriodeTerkunciException(
            "{$konteks} tanggal {$tgl} di {$cabang} tidak bisa ditambah, diubah, atau dihapus karena periode itu sudah "
            . "di-closing ({$closing->jenis_label} {$closing->label_periode}). "
            . 'Minta Owner membuka kunci periode lewat menu Closing / Tutup Buku bila memang perlu koreksi.'
        );
    }
}
