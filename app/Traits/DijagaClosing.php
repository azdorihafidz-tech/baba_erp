<?php

namespace App\Traits;

use App\Support\ClosingLock;

/**
 * Penjaga kunci closing di level model — berlaku dari menu mana pun,
 * termasuk proses otomatis. Model pemakai mendefinisikan:
 * - closingKolomTanggal(): string — kolom tanggal transaksi
 * - closingKonteks(): string — nama data untuk pesan error
 * - closingKolomDijaga(): ?array — kolom yang bila berubah memicu cek
 *   (null = semua kolom kecuali closingKolomBebas())
 * - closingKolomBebas(): array — kolom yang boleh berubah walau terkunci
 *
 * Catatan: delete lewat query builder massal (Model::where()->delete())
 * tidak memicu event model; alur yang memakainya selalu juga mengubah
 * model lain yang dijaga dalam DB::transaction yang sama.
 */
trait DijagaClosing
{
    public static function bootDijagaClosing(): void
    {
        static::saving(fn ($model) => $model->cekKunciClosing(false));
        static::deleting(fn ($model) => $model->cekKunciClosing(true));
        if (method_exists(static::class, 'restoring')) {
            static::restoring(fn ($model) => $model->cekKunciClosing(true));
        }
    }

    protected function closingKolomDijaga(): ?array
    {
        return null;
    }

    protected function closingKolomBebas(): array
    {
        return ['updated_at'];
    }

    /** true = tanggal/cabang LAMA tidak perlu dicek (data lama tidak dihitung laporan). */
    protected function closingAbaikanTanggalLama(): bool
    {
        return false;
    }

    protected function cekKunciClosing(bool $selaluCek): void
    {
        $kolomTanggal = $this->closingKolomTanggal();

        if (! $selaluCek && $this->exists) {
            $berubah = array_keys($this->getDirty());
            $dijaga = $this->closingKolomDijaga();
            $relevan = $dijaga === null
                ? array_diff($berubah, $this->closingKolomBebas())
                : array_intersect($berubah, $dijaga);
            if (empty($relevan)) {
                return;
            }
        }

        ClosingLock::pastikanTerbuka($this->cabang_id ? (int) $this->cabang_id : null, $this->{$kolomTanggal}, $this->closingKonteks());

        if ($this->exists && ! $this->closingAbaikanTanggalLama()) {
            $cabangLama = $this->getOriginal('cabang_id');
            $tanggalLama = $this->getOriginal($kolomTanggal);
            if ($cabangLama != $this->cabang_id || (string) $tanggalLama !== (string) $this->{$kolomTanggal}) {
                ClosingLock::pastikanTerbuka($cabangLama ? (int) $cabangLama : null, $tanggalLama, $this->closingKonteks());
            }
        }
    }
}
