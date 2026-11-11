<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Seed Chart of Accounts (kode akun) standar SAK ETAP untuk UMKM jasa
 * penggilingan daging & produksi olahan (Berkah Mulyo). Idempotent —
 * aman dijalankan berkali-kali (updateOrCreate by kode).
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $urutan = 0;
        foreach ($this->akunList() as $akun) {
            $urutan += 10;
            ChartOfAccount::updateOrCreate(
                ['kode' => $akun['kode']],
                array_merge($akun, ['urutan' => $urutan])
            );
        }

        $this->command->info('  ChartOfAccountsSeeder: ' . count($this->akunList()) . ' kode akun ter-seed (idempotent).');
    }

    private function akunList(): array
    {
        return [
            // ============ ASET (1-XXXX) ============
            ['kode' => '1-0000', 'nama' => 'ASET',                       'tipe' => 'aset', 'parent_kode' => null,    'saldo_normal' => 'debet',  'level' => 1, 'is_leaf' => false],
            ['kode' => '1-1000', 'nama' => 'Aset Lancar',                 'tipe' => 'aset', 'parent_kode' => '1-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => false],
            ['kode' => '1-1101', 'nama' => 'Kas Tunai',                   'tipe' => 'aset', 'parent_kode' => '1-1000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],
            ['kode' => '1-1102', 'nama' => 'Kas Bank',                   'tipe' => 'aset', 'parent_kode' => '1-1000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],
            ['kode' => '1-1103', 'nama' => 'Kas QRIS',                   'tipe' => 'aset', 'parent_kode' => '1-1000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],
            ['kode' => '1-1201', 'nama' => 'Piutang Usaha',               'tipe' => 'aset', 'parent_kode' => '1-1000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],
            ['kode' => '1-1301', 'nama' => 'Persediaan Bahan Baku',       'tipe' => 'aset', 'parent_kode' => '1-1000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],
            ['kode' => '1-1302', 'nama' => 'Persediaan Barang Jadi',      'tipe' => 'aset', 'parent_kode' => '1-1000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],
            ['kode' => '1-1303', 'nama' => 'Persediaan Kemasan',          'tipe' => 'aset', 'parent_kode' => '1-1000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],

            ['kode' => '1-2000', 'nama' => 'Aset Tetap',                  'tipe' => 'aset', 'parent_kode' => '1-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => false],
            ['kode' => '1-2101', 'nama' => 'Mesin Produksi',              'tipe' => 'aset', 'parent_kode' => '1-2000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true, 'keterangan' => 'Mesin giling bakso, freezer, dll'],
            ['kode' => '1-2102', 'nama' => 'Kendaraan',                   'tipe' => 'aset', 'parent_kode' => '1-2000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],
            ['kode' => '1-2103', 'nama' => 'Peralatan Kantor',            'tipe' => 'aset', 'parent_kode' => '1-2000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],
            ['kode' => '1-2104', 'nama' => 'Peralatan Gudang',            'tipe' => 'aset', 'parent_kode' => '1-2000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],
            ['kode' => '1-2105', 'nama' => 'Furniture',                   'tipe' => 'aset', 'parent_kode' => '1-2000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true, 'keterangan' => 'Meja, kursi, dll'],
            ['kode' => '1-2199', 'nama' => 'Aset Tetap Lainnya',          'tipe' => 'aset', 'parent_kode' => '1-2000', 'saldo_normal' => 'debet', 'level' => 3, 'is_leaf' => true],

            ['kode' => '1-3000', 'nama' => 'Akumulasi Depresiasi',        'tipe' => 'aset', 'parent_kode' => '1-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => false, 'subtipe' => 'kontra_aset', 'keterangan' => 'Kontra-aset (saldo normal kredit meski di bawah tipe Aset)'],
            ['kode' => '1-3101', 'nama' => 'Akumulasi Depresiasi Mesin',      'tipe' => 'aset', 'parent_kode' => '1-3000', 'saldo_normal' => 'kredit', 'level' => 3, 'is_leaf' => true, 'subtipe' => 'kontra_aset'],
            ['kode' => '1-3102', 'nama' => 'Akumulasi Depresiasi Kendaraan', 'tipe' => 'aset', 'parent_kode' => '1-3000', 'saldo_normal' => 'kredit', 'level' => 3, 'is_leaf' => true, 'subtipe' => 'kontra_aset'],
            ['kode' => '1-3103', 'nama' => 'Akumulasi Depresiasi Peralatan', 'tipe' => 'aset', 'parent_kode' => '1-3000', 'saldo_normal' => 'kredit', 'level' => 3, 'is_leaf' => true, 'subtipe' => 'kontra_aset'],

            // ============ KEWAJIBAN (2-XXXX) ============
            ['kode' => '2-0000', 'nama' => 'KEWAJIBAN',                   'tipe' => 'kewajiban', 'parent_kode' => null, 'saldo_normal' => 'kredit', 'level' => 1, 'is_leaf' => false],
            ['kode' => '2-1000', 'nama' => 'Kewajiban Jangka Pendek',     'tipe' => 'kewajiban', 'parent_kode' => '2-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => false],
            ['kode' => '2-1101', 'nama' => 'Hutang Usaha',                'tipe' => 'kewajiban', 'parent_kode' => '2-1000', 'saldo_normal' => 'kredit', 'level' => 3, 'is_leaf' => true, 'keterangan' => 'PO yang belum dibayar'],
            ['kode' => '2-1102', 'nama' => 'Hutang Gaji',                 'tipe' => 'kewajiban', 'parent_kode' => '2-1000', 'saldo_normal' => 'kredit', 'level' => 3, 'is_leaf' => true],
            ['kode' => '2-1103', 'nama' => 'Hutang Pajak',                'tipe' => 'kewajiban', 'parent_kode' => '2-1000', 'saldo_normal' => 'kredit', 'level' => 3, 'is_leaf' => true],

            ['kode' => '2-2000', 'nama' => 'Kewajiban Jangka Panjang',    'tipe' => 'kewajiban', 'parent_kode' => '2-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => false],
            ['kode' => '2-2101', 'nama' => 'Hutang Bank',                 'tipe' => 'kewajiban', 'parent_kode' => '2-2000', 'saldo_normal' => 'kredit', 'level' => 3, 'is_leaf' => true],
            ['kode' => '2-2102', 'nama' => 'Hutang Leasing',              'tipe' => 'kewajiban', 'parent_kode' => '2-2000', 'saldo_normal' => 'kredit', 'level' => 3, 'is_leaf' => true],

            // ============ MODAL (3-XXXX) ============
            ['kode' => '3-0000', 'nama' => 'MODAL',                      'tipe' => 'modal', 'parent_kode' => null, 'saldo_normal' => 'kredit', 'level' => 1, 'is_leaf' => false],
            ['kode' => '3-1101', 'nama' => 'Modal Owner',                 'tipe' => 'modal', 'parent_kode' => '3-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => true],
            ['kode' => '3-1102', 'nama' => 'Prive Owner',                 'tipe' => 'modal', 'parent_kode' => '3-0000', 'saldo_normal' => 'debet',  'level' => 2, 'is_leaf' => true, 'subtipe' => 'kontra_modal', 'keterangan' => 'Kontra-modal — pengambilan pribadi Owner, mengurangi modal (saldo normal debet)'],
            ['kode' => '3-2101', 'nama' => 'Laba Ditahan',                'tipe' => 'modal', 'parent_kode' => '3-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => true],

            // ============ PENDAPATAN (4-XXXX) ============
            ['kode' => '4-0000', 'nama' => 'PENDAPATAN',                 'tipe' => 'pendapatan', 'parent_kode' => null, 'saldo_normal' => 'kredit', 'level' => 1, 'is_leaf' => false],
            ['kode' => '4-1101', 'nama' => 'Penjualan Jasa Giling',       'tipe' => 'pendapatan', 'parent_kode' => '4-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => true],
            ['kode' => '4-1102', 'nama' => 'Penjualan Produk Jadi',       'tipe' => 'pendapatan', 'parent_kode' => '4-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => true],
            ['kode' => '4-1199', 'nama' => 'Pendapatan Usaha Lainnya',    'tipe' => 'pendapatan', 'parent_kode' => '4-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => true],

            // ============ HPP (5-XXXX) ============
            ['kode' => '5-0000', 'nama' => 'HARGA POKOK PENJUALAN',      'tipe' => 'hpp', 'parent_kode' => null, 'saldo_normal' => 'debet', 'level' => 1, 'is_leaf' => false],
            ['kode' => '5-1101', 'nama' => 'HPP Bahan Baku',              'tipe' => 'hpp', 'parent_kode' => '5-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '5-1102', 'nama' => 'HPP Bumbu & Kemasan',         'tipe' => 'hpp', 'parent_kode' => '5-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '5-1103', 'nama' => 'HPP Tenaga Kerja Langsung',   'tipe' => 'hpp', 'parent_kode' => '5-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],

            // ============ BEBAN OPERASIONAL (6-XXXX) ============
            ['kode' => '6-0000', 'nama' => 'BEBAN OPERASIONAL',          'tipe' => 'beban_operasional', 'parent_kode' => null, 'saldo_normal' => 'debet', 'level' => 1, 'is_leaf' => false],
            ['kode' => '6-1101', 'nama' => 'Beban Gaji',                  'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1102', 'nama' => 'Beban Sewa Gedung',           'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1103', 'nama' => 'Beban Listrik & Air',         'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1104', 'nama' => 'Beban Depresiasi',            'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1105', 'nama' => 'Beban Perawatan Aset',        'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1106', 'nama' => 'Beban Perlengkapan Kantor',   'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1201', 'nama' => 'Beban Bahan Baku Non-produksi', 'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1301', 'nama' => 'Beban Operasional Umum',      'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1302', 'nama' => 'Beban Transportasi',         'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1303', 'nama' => 'Beban Konsumsi',             'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '6-1401', 'nama' => 'Beban Kerugian Stok',        'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true, 'keterangan' => 'Susut/rusak/hilang'],
            ['kode' => '6-1999', 'nama' => 'Beban Lain-lain',            'tipe' => 'beban_operasional', 'parent_kode' => '6-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],

            // ============ PENDAPATAN LAIN (7-XXXX) ============
            ['kode' => '7-0000', 'nama' => 'PENDAPATAN LAIN-LAIN',       'tipe' => 'pendapatan_lain', 'parent_kode' => null, 'saldo_normal' => 'kredit', 'level' => 1, 'is_leaf' => false],
            ['kode' => '7-1101', 'nama' => 'Bunga Bank',                 'tipe' => 'pendapatan_lain', 'parent_kode' => '7-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => true],
            ['kode' => '7-1102', 'nama' => 'Keuntungan Penjualan Aset',  'tipe' => 'pendapatan_lain', 'parent_kode' => '7-0000', 'saldo_normal' => 'kredit', 'level' => 2, 'is_leaf' => true],

            // ============ BEBAN LAIN (8-XXXX) ============
            ['kode' => '8-0000', 'nama' => 'BEBAN LAIN-LAIN',            'tipe' => 'beban_lain', 'parent_kode' => null, 'saldo_normal' => 'debet', 'level' => 1, 'is_leaf' => false],
            ['kode' => '8-1101', 'nama' => 'Kerugian Penjualan Aset',    'tipe' => 'beban_lain', 'parent_kode' => '8-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
            ['kode' => '8-1102', 'nama' => 'Bunga Pinjaman',             'tipe' => 'beban_lain', 'parent_kode' => '8-0000', 'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true],
        ];
    }
}
