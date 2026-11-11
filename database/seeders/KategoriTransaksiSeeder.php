<?php

namespace Database\Seeders;

use App\Models\KategoriTransaksi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KategoriTransaksiSeeder extends Seeder
{
    private array $kategoriList = [
        // Pemasukan
        ['kode' => 'PENJ',     'nama' => 'Penjualan Produk',             'tipe' => 'pemasukan',   'is_system' => true,  'urutan' => 1],
        ['kode' => 'JASA',     'nama' => 'Jasa Giling',                  'tipe' => 'pemasukan',   'is_system' => true,  'urutan' => 2],
        ['kode' => 'SALDO',    'nama' => 'Saldo Awal',                   'tipe' => 'pemasukan',   'is_system' => true,  'urutan' => 3],
        ['kode' => 'SETOR-IN', 'nama' => 'Perpindahan Dana Masuk',   'tipe' => 'pemasukan',   'is_system' => true,  'urutan' => 4],
        // Tahap 5 D'mentai — Setoran Kasir (rekonsiliasi harian cabang -> HO),
        // BEDA dari SETOR-IN/OUT di atas (itu transfer manual lump-sum antar
        // cabang, generik). 2 kategori satu-arah, sama alasan MUTASI-IN/OUT:
        // kode_akun_coa NULL harus dihormati apa adanya (transfer internal,
        // bukan pendapatan/beban riil) supaya tidak di-override paksa ke akun
        // 4-1199 oleh LabaRugiFormalService::resolveKodeAkun().
        ['kode' => 'SETORKSR-IN', 'nama' => 'Setoran Kasir Masuk (HO)',   'tipe' => 'pemasukan',   'is_system' => true,  'urutan' => 20],
        ['kode' => 'LNY-IN',   'nama' => 'Pemasukan Lainnya',            'tipe' => 'pemasukan',   'is_system' => false, 'urutan' => 5],
        ['kode' => 'KOR-STOK', 'nama' => 'Koreksi Stok Masuk',           'tipe' => 'pemasukan',   'is_system' => true,  'urutan' => 6],
        // Mutasi Kas Internal (dalam 1 cabang, mis. Tunai <-> Bank) -- 2 kategori
        // satu-arah (BUKAN 1 kategori tipe='keduanya') supaya kode_akun_coa NULL
        // benar-benar terhormati oleh LabaRugiFormalService::resolveKodeAkun():
        // kategori tipe='keduanya' + transaksi sisi pemasukan di-override paksa ke
        // akun 4-1199 walau kode_akun_coa-nya NULL -- lihat Rule bisnis terkait
        // Transfer Antar Kas. Pola persis SETOR-IN/SETOR-OUT.
        ['kode' => 'MUTASI-IN', 'nama' => 'Mutasi Kas Masuk',             'tipe' => 'pemasukan',   'is_system' => true,  'urutan' => 7],
        // Retur Pembelian (uang kembali dari supplier krn tukar/kembalikan barang) --
        // satu-arah (tipe='pemasukan', BUKAN 'keduanya') dengan alasan SAMA PERSIS
        // seperti MUTASI-IN di atas: retur BUKAN pendapatan riil (murni pembatalan
        // sebagian pembelian sebelumnya, yang sisi pembeliannya sendiri sudah
        // dikecualikan dari Laba Rugi lewat kategori PBB/hpp) -- kalau dibuat
        // 'keduanya', sisi pemasukannya akan di-override paksa ke akun 4-1199
        // (Pendapatan Usaha Lainnya) oleh LabaRugiFormalService::resolveKodeAkun(),
        // persis bug yang ditemukan di transaksi retur Miwon (kategori Lainnya).
        ['kode' => 'RETUR-BELI', 'nama' => 'Retur Pembelian',            'tipe' => 'pemasukan',   'is_system' => true,  'urutan' => 8],
        // Pengeluaran
        ['kode' => 'PBB',      'nama' => 'Pembelian Bahan Baku',         'tipe' => 'pengeluaran', 'is_system' => true,  'urutan' => 10],
        ['kode' => 'GAJI',     'nama' => 'Gaji Karyawan',                'tipe' => 'pengeluaran', 'is_system' => true,  'urutan' => 11],
        ['kode' => 'SEWA',     'nama' => 'Sewa Gedung',                  'tipe' => 'pengeluaran', 'is_system' => true,  'urutan' => 12],
        ['kode' => 'PNYS',     'nama' => 'Penyusutan Aset',              'tipe' => 'pengeluaran', 'is_system' => true,  'urutan' => 13],
        ['kode' => 'OPS',      'nama' => 'Biaya Operasional',            'tipe' => 'pengeluaran', 'is_system' => true,  'urutan' => 14],
        ['kode' => 'ASET',     'nama' => 'Pembelian Aset',               'tipe' => 'pengeluaran', 'is_system' => true,  'urutan' => 15],
        ['kode' => 'SETOR-OUT', 'nama' => 'Perpindahan Dana Keluar', 'tipe' => 'pengeluaran', 'is_system' => true,  'urutan' => 16],
        ['kode' => 'BEBAN-STOK','nama' => 'Beban Kerugian Stok',         'tipe' => 'pengeluaran', 'is_system' => true,  'urutan' => 17],
        ['kode' => 'MUTASI-OUT','nama' => 'Mutasi Kas Keluar',            'tipe' => 'pengeluaran', 'is_system' => true,  'urutan' => 18],
        ['kode' => 'SETORKSR-OUT', 'nama' => 'Setoran Kasir Keluar (Cabang)', 'tipe' => 'pengeluaran', 'is_system' => true, 'urutan' => 21],
        // Keduanya
        ['kode' => 'LNY',      'nama' => 'Lainnya',                      'tipe' => 'keduanya',    'is_system' => true,  'urutan' => 99],
    ];

    /** Mapping enum lama → kode baru */
    private array $enumToKode = [
        'penjualan'      => 'PENJ',
        'jasa_giling'    => 'JASA',
        'pembelian_bahan'=> 'PBB',
        'gaji'           => 'GAJI',
        'sewa_gedung'    => 'SEWA',
        'penyusutan'     => 'PNYS',
        'operasional'    => 'OPS',
        'pembelian_aset' => 'ASET',
        'lainnya'        => 'LNY',
        'saldo_awal'     => 'SALDO',
    ];

    public function run(): void
    {
        // Insert / update setiap kategori
        foreach ($this->kategoriList as $data) {
            KategoriTransaksi::withTrashed()->firstOrCreate(
                ['kode' => $data['kode']],
                array_merge($data, ['parent_id' => null, 'is_active' => true])
            );
        }

        // Data migration: isi kategori_id di transaksi lama berdasarkan enum
        $kodeToId = KategoriTransaksi::withTrashed()
            ->pluck('id', 'kode')
            ->toArray();

        foreach ($this->enumToKode as $enumVal => $kode) {
            $kategoriId = $kodeToId[$kode] ?? null;
            if (!$kategoriId) {
                continue;
            }
            DB::table('transaksi_keuangans')
                ->where('kategori', $enumVal)
                ->whereNull('kategori_id')
                ->update(['kategori_id' => $kategoriId]);
        }

        $this->command->info('  KategoriTransaksi seeded & data migration done.');
    }
}
