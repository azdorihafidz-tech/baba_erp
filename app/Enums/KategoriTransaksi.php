<?php

namespace App\Enums;

enum KategoriTransaksi: string
{
    case Penjualan = 'penjualan';
    case JasaGiling = 'jasa_giling';
    case PembelianBahan = 'pembelian_bahan';
    case Gaji = 'gaji';
    case SewaGedung = 'sewa_gedung';
    case Penyusutan = 'penyusutan';
    case Operasional = 'operasional';
    case PembelianAset = 'pembelian_aset';
    case Lainnya   = 'lainnya';
    case SaldoAwal = 'saldo_awal';

    public function label(): string
    {
        return match($this) {
            KategoriTransaksi::Penjualan     => 'Penjualan',
            KategoriTransaksi::JasaGiling    => 'Jasa Giling',
            KategoriTransaksi::PembelianBahan => 'Pembelian Bahan Baku',
            KategoriTransaksi::Gaji          => 'Gaji Karyawan',
            KategoriTransaksi::SewaGedung    => 'Sewa Gedung',
            KategoriTransaksi::Penyusutan    => 'Penyusutan Aset',
            KategoriTransaksi::Operasional   => 'Biaya Operasional',
            KategoriTransaksi::PembelianAset => 'Pembelian Aset',
            KategoriTransaksi::Lainnya       => 'Lainnya',
            KategoriTransaksi::SaldoAwal     => 'Saldo Awal',
        };
    }
}
