<?php

namespace Database\Seeders;

use App\Models\CustomRole;
use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 4 — keuangan & laporan: izin Laporan Belanja Online, peran "Pemilik Toko Mitra"
 * (kasir + 3 laporan toko sendiri, tanpa keuangan), panduan & tooltip. Idempotent; centangan bawaan
 * hanya dipasang saat izin / peran pertama kali dibuat (pilihan Owner sesudahnya tidak ditimpa).
 */
class KeuanganBabaSeeder extends Seeder
{
    public const PERMISSIONS = [
        ['name' => 'toko_online.laporan', 'display_name' => 'Lihat Laporan Belanja Online (penjualan, RO, tagihan member)', 'group' => 'toko_online'],
    ];

    public const ROLE_DEFAULTS = [
        'admin_pusat' => ['toko_online.laporan'],
    ];

    /** Peran pemilik Toko Kemitraan / Toko Refill Member: hanya kasir & laporan tokonya (rancangan §8, §10). */
    public const PEMILIK_TOKO = [
        'order.view', 'order.create', 'pelanggan.view', 'pelanggan.create', 'stok.view',
        'penjualan.laporan_transaksi.view', 'penjualan.laporan_produk.view', 'penjualan.laporan_kategori.view',
    ];

    public const NAMA_COA = [
        '4-1101' => ['Penjualan Jasa Giling', 'Pendapatan Jasa'],
        '4-1102' => ['Penjualan Produk Jadi', 'Penjualan Parfum'],
        '5-1101' => ['HPP Bahan Baku', 'HPP Barang Terjual'],
        '5-1102' => ['HPP Bumbu & Kemasan', 'HPP Kemasan'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $p) {
            $perm = Permission::firstOrCreate(['name' => $p['name']], ['display_name' => $p['display_name'], 'group' => $p['group']]);
            if ($perm->wasRecentlyCreated) {
                $rows = [];
                foreach (self::ROLE_DEFAULTS as $role => $names) {
                    if (in_array($p['name'], $names, true)) {
                        $rows[] = ['role' => $role, 'permission_id' => $perm->id];
                    }
                }
                DB::table('role_permissions')->insertOrIgnore($rows);
                foreach (array_keys(self::ROLE_DEFAULTS) as $role) {
                    Cache::forget("role_permissions_{$role}");
                }
            }
        }

        $peran = CustomRole::firstOrCreate(['kode' => 'pemilik_toko'], [
            'nama' => 'Pemilik Toko Mitra', 'akses_semua_cabang' => false,
            'deskripsi' => 'Toko Kemitraan / Toko Refill Member: kasir dan laporan tokonya sendiri (tanpa keuangan BABA).',
        ]);
        if ($peran->wasRecentlyCreated) {
            $ids = Permission::whereIn('name', self::PEMILIK_TOKO)->pluck('id');
            DB::table('role_permissions')->insertOrIgnore($ids->map(fn ($id) => ['role' => 'pemilik_toko', 'permission_id' => $id])->all());
            Cache::forget('role_permissions_pemilik_toko');
        }
        Cache::forget('all_permission_names');

        // Nama akun COA bawaan dimsum → istilah BABA (hanya bila belum diubah Owner).
        foreach (self::NAMA_COA as $kode => [$lama, $baru]) {
            DB::table('chart_of_accounts')->where('kode', $kode)->where('nama', $lama)->update(['nama' => $baru, 'updated_at' => now()]);
        }

        Panduan::updateOrCreate(['slug' => 'laporan-belanja-online'], [
            'judul' => 'Laporan Belanja Online', 'konten' => self::PANDUAN_LAPORAN, 'modul' => 'toko_online', 'urutan' => 67, 'aktif' => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'keuangan-toko-online'], [
            'judul' => 'Keuangan Toko Online & Toko Mitra', 'konten' => self::PANDUAN_KEUANGAN, 'modul' => 'toko_online', 'urutan' => 68, 'aktif' => true,
        ]);
        Tooltip::updateOrCreate(['key' => 'laporan_online.tim'], [
            'title' => 'Tim', 'modul' => 'toko_online', 'aktif' => true, 'urutan' => 40,
            'content' => 'Penjualan / RO dikelompokkan menurut upline ber-jabatan ini (struktur aplikasi member saat ini). Member yang jabatannya sama dengan pilihan ini dihitung sebagai ketua timnya sendiri.',
        ]);
    }

    public const PANDUAN_LAPORAN = <<<'MARKDOWN'
## Tujuan
Melihat hasil belanja online member: penjualan gudang (omzet, HPP, laba), RO, dan tagihan yang belum dibayar.

## Langkah-langkah
1. Buka **Toko Online › Laporan Belanja Online**.
2. Pilih jenis laporan: **Per Produk**, **Per Cabang**, **Per Jabatan**, **Per Tim**, **RO per Member**, **RO per Tim**, atau **Tagihan Member**.
3. Atur tanggal **Dari** dan **Sampai**, lalu tekan **Tampilkan**. Untuk laporan tim, pilih **Tim menurut** (MVO, Regional, Founder, GM, Supplier, Distributor).
4. Tekan **Excel** untuk mengunduh tabel yang sedang dibuka.

## Catatan Penting
- Penjualan dihitung saat barang **keluar dari gudang** (dikirim / siap diambil), sudah dikurangi retur yang disetujui. Ongkir ditampilkan terpisah.
- HPP = modal FIFO barang saat keluar. Laba kotor = omzet − HPP.
- RO = jumlah botol (gudang & upline) sesuai Mode RO; retur / batal mengurangi.
- Tagihan berwarna merah = sudah lewat jatuh tempo.
- Laporan per tim mengambil struktur terbaru dari aplikasi member. Bila aplikasi member tidak bisa dihubungi, muncul pesan kuning — coba lagi nanti.
MARKDOWN;

    public const PANDUAN_KEUANGAN = <<<'MARKDOWN'
## Tujuan
Menjelaskan bagaimana belanja online dan toko mitra masuk (atau tidak masuk) ke keuangan BABA.

## Pesanan Online gudang
1. Saat **bukti transfer diterima**, uang masuk ke **Kas Transfer** gudang dan tercatat sebagai **Penjualan** (Laba Rugi, Buku Besar, Neraca).
2. Saat barang **keluar**, stok gudang berkurang dan **HPP** tercatat di Laba Rugi.
3. **Retur** yang disetujui: barang kembali ke stok (HPP berkurang). Bila pesanan sudah lunas, dana yang dikembalikan mengurangi Penjualan dan Kas (Transfer / Tunai sesuai pilihan).
4. Pesanan lunas yang **dibatalkan** gudang: dana yang dikembalikan mengurangi Penjualan dan Kas Transfer.
5. **Tagihan** yang belum dibayar belum menjadi pendapatan. Jumlahnya tampil di Neraca sebagai baris informasi *Tagihan member belum dibayar* dan di **Laporan Belanja Online › Tagihan Member**.
6. Pesanan member ke **upline** adalah urusan antar member — tidak masuk keuangan BABA.

## Toko Kemitraan & Toko Refill Member
- Toko dengan **Jenis toko** Kemitraan / Refill Member memakai kasir ERP, tetapi uang dan barangnya milik mitra: **tidak dihitung** di Laba Rugi, Neraca, Buku Besar, Saldo Kas, Dashboard Keuangan, dan Tutup Buku gabungan.
- Laporan untuk toko itu sendiri tetap ada: pilih tokonya di filter cabang, atau buka **Penjualan › Laporan Transaksi / Produk / Kategori**.
- Akun pemilik toko: buat user dengan peran **Pemilik Toko Mitra** dan hubungkan hanya ke tokonya — ia hanya bisa memakai kasir dan melihat 3 laporan tokonya.
- Barang ke toko mitra sebaiknya lewat **Kirim ke Cabang** (gudang menjual ke toko); di Tutup Buku gabungan kiriman ini dihitung sebagai penjualan, bukan kiriman internal.
MARKDOWN;
}
