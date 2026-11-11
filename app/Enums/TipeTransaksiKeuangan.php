<?php

namespace App\Enums;

enum TipeTransaksiKeuangan: string
{
    case Pemasukan = 'pemasukan';
    case Pengeluaran = 'pengeluaran';

    public function label(): string
    {
        return match($this) {
            TipeTransaksiKeuangan::Pemasukan => 'Pemasukan',
            TipeTransaksiKeuangan::Pengeluaran => 'Pengeluaran',
        };
    }
}
