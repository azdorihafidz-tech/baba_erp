<?php

namespace App\Enums;

/**
 * Tipe transaksi POS (Tahap 3, D'mentai) — terpisah dari TipeOrder (yang
 * menandakan lini bisnis). Bisa diaktifkan/nonaktifkan per outlet via
 * `cabangs.{tipe}_aktif` (CLAUDE.md 1.4 — jangan hardcode).
 */
enum TipeTransaksi: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case Frozen = 'frozen';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::Takeaway => 'Takeaway',
            self::Frozen => 'Frozen',
        };
    }

    /** Nama kolom config aktif/nonaktif di tabel cabangs, mis. 'dine_in_aktif'. */
    public function kolomAktif(): string
    {
        return "{$this->value}_aktif";
    }
}
