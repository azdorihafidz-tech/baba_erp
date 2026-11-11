<?php

namespace App\Enums;

enum StatusOrder: string
{
    case Pending = 'pending';
    case Proses = 'proses';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    /**
     * Status yang BUKAN penjualan sah — tidak dihitung omzet/HPP/jumlah order
     * di laporan & dashboard: bill tersimpan yang belum dibayar (pending),
     * order dibatalkan, dan kiriman ke cabang yang belum diterima (proses,
     * 4.49 Tahap B2). Sama dengan aturan Closing (4.37).
     *
     * @return string[]
     */
    public static function bukanPenjualan(): array
    {
        return [self::Pending->value, self::Proses->value, self::Dibatalkan->value];
    }

    public function label(): string
    {
        return match($this) {
            StatusOrder::Pending => 'Menunggu',
            StatusOrder::Proses => 'Dikirim (menunggu diterima)', // 4.49 — hanya dipakai Kirim ke Cabang
            StatusOrder::Selesai => 'Selesai',
            StatusOrder::Dibatalkan => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            StatusOrder::Pending => 'bg-warning',
            StatusOrder::Proses => 'bg-info',
            StatusOrder::Selesai => 'bg-success',
            StatusOrder::Dibatalkan => 'bg-danger',
        };
    }
}
