<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 4.46 — Laporan di menu Penjualan yang bisa dibuka kasir (Transaksi, Produk,
 * Kategori) + izin terpisah untuk melihat KEUNTUNGAN (kasir tidak boleh).
 *
 * ADDITIVE & idempotent — aman di production: hanya menambah permission dan
 * mencentangnya (insertOrIgnore). Izin lihat laporan diberikan ke role yang
 * SUDAH bisa membuka Riwayat Order (`order.view`, termasuk role buatan) +
 * default; izin keuntungan HANYA ke admin_pusat & manajer_cabang (Owner lewat
 * Gate::before). Mapping role lain tidak disentuh.
 */
class LaporanPenjualanKasirSeeder extends Seeder
{
    public const PERMISSIONS = [
        ['name' => 'penjualan.laporan_transaksi.view', 'display_name' => 'Laporan Transaksi (menu Penjualan)', 'group' => 'penjualan'],
        ['name' => 'penjualan.laporan_produk.view',    'display_name' => 'Laporan Penjualan Produk (menu Penjualan)', 'group' => 'penjualan'],
        ['name' => 'penjualan.laporan_kategori.view',  'display_name' => 'Laporan Penjualan Kategori (menu Penjualan)', 'group' => 'penjualan'],
        ['name' => 'penjualan.laporan.keuntungan',     'display_name' => 'Lihat Keuntungan di Laporan Penjualan (Transaksi/Produk/Kategori)', 'group' => 'penjualan'],
    ];

    public const IZIN_LIHAT = [
        'penjualan.laporan_transaksi.view',
        'penjualan.laporan_produk.view',
        'penjualan.laporan_kategori.view',
    ];

    public const IZIN_KEUNTUNGAN = 'penjualan.laporan.keuntungan';

    /** Default mapping instalasi baru — sama dengan entry di RolePermissionSeeder. */
    public const ROLE_DEFAULTS = [
        'admin_pusat'    => [...self::IZIN_LIHAT, self::IZIN_KEUNTUNGAN],
        'manajer_cabang' => [...self::IZIN_LIHAT, self::IZIN_KEUNTUNGAN],
        'kasir'          => self::IZIN_LIHAT,
    ];

    public function run(): void
    {
        $ids = [];
        foreach (self::PERMISSIONS as $p) {
            $ids[$p['name']] = Permission::firstOrCreate(
                ['name' => $p['name']],
                ['display_name' => $p['display_name'], 'group' => $p['group']]
            )->id;
        }

        // Role yang sudah bisa membuka Riwayat Order ikut mendapat izin lihat laporan
        $rolePunyaOrder = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.name', 'order.view')
            ->pluck('role_permissions.role');

        $baris = [];
        foreach ($rolePunyaOrder as $role) {
            foreach (self::IZIN_LIHAT as $izin) {
                $baris[] = ['role' => $role, 'permission_id' => $ids[$izin]];
            }
        }
        foreach (self::ROLE_DEFAULTS as $role => $izinList) {
            foreach ($izinList as $izin) {
                $baris[] = ['role' => $role, 'permission_id' => $ids[$izin]];
            }
        }
        DB::table('role_permissions')->insertOrIgnore($baris);

        $roles = collect($baris)->pluck('role')->unique()->values();
        Cache::forget('all_permission_names');
        foreach ($roles as $role) {
            Cache::forget("role_permissions_{$role}");
        }

        $this->panduanTransaksi();
        $this->panduanProduk();
        $this->panduanKategori();
        $this->tooltip();

        $this->command?->info('✅ Laporan Penjualan (kasir): izin lihat untuk ' . $roles->implode(', ')
            . '; izin keuntungan untuk ' . implode(', ', array_keys(array_filter(
                self::ROLE_DEFAULTS, fn ($l) => in_array(self::IZIN_KEUNTUNGAN, $l, true)
            ))) . ' (idempotent).');
    }

    private function panduanProduk(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-produk-kasir'], [
            'judul'  => 'Laporan Penjualan Produk (menu Penjualan)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Melihat produk apa saja yang terjual, berapa banyak (qty), dan berapa pendapatannya — per produk. Cocok untuk mengetahui produk terlaris. Menu ini ada di bagian **Penjualan** dan bisa dibuka kasir.

## Langkah-langkah

### 1. Pilih periode
- Tombol **Hari ini / Bulan ini / Tahun ini / Selama ini**, atau
- isi **Dari tanggal** dan **Sampai tanggal** lalu klik **Tampilkan**.

### 2. Saring (opsional)
- **Kategori** — tampilkan produk dari satu kategori saja.
- **Cari produk** — ketik nama atau kode produk.
- Klik nama kategori di bawah nama produk untuk langsung menyaring kategori itu. **Hapus filter** mengembalikan semua produk.

### 3. Baca grafik
Grafik **10 Produk Teratas** bisa diurutkan menurut **Qty** atau **Pendapatan** (dan **Keuntungan** untuk yang berhak) dengan tombol di pojok kanan atas grafik. Arahkan jari/mouse ke batang untuk melihat angka lengkapnya.

### 4. Baca tabel
- Klik judul kolom **Produk**, **Qty**, **Pendapatan** (atau **Untung**) untuk mengurutkan.
- Produk yang terjual dalam beberapa varian punya tombol **N varian** — klik untuk melihat rincian per varian.

### 5. Export
**Export Excel** / **Export PDF** menyimpan isi halaman sesuai periode dan filter yang sedang dipilih.

## Arti kolom
- **Qty** — jumlah yang terjual.
- **Penjualan** — harga × qty di keranjang (sebelum diskon order).
- **Diskon** — diskon order dibagi ke setiap produk di order itu sesuai porsi harganya. Contoh: order Rp 100.000 berisi produk A Rp 60.000 dan B Rp 40.000, diskon Rp 10.000 → A kena Rp 6.000, B kena Rp 4.000.
- **Pendapatan** — penjualan − diskon.
- **Transaksi** — banyaknya order yang berisi produk itu.
- **Modal / Untung / Margin** — hanya untuk role yang punya izin **Lihat Keuntungan di Laporan Penjualan**. Untung = pendapatan − modal bahan (HPP).

## Catatan Penting
- **Service charge & take away fee** tidak termasuk produk mana pun, jadi ditampilkan sebagai baris sendiri di bawah tabel. Total pendapatan produk + baris itu = pendapatan di **Laporan Transaksi**. Saat filter kategori/pencarian aktif, baris ini tidak ditampilkan.
- Data mengikuti **cabang yang dipilih di pojok kanan atas**; kasir selalu melihat cabangnya sendiri. Di **Semua Cabang**, produk dengan Kode Item yang sama di beberapa cabang digabung jadi 1 baris.
- Transaksi yang **dibatalkan**, **dihapus**, atau **bill tersimpan yang belum dibayar** tidak dihitung.

## Troubleshooting
- **Menu tidak ada di sidebar?** Minta Owner mencentang **Laporan Penjualan Produk (menu Penjualan)** di Role & Hak Akses.
- **Produk muncul 2 kali di Semua Cabang?** Kode Item produk itu berbeda antar cabang — samakan Kode Item-nya di menu Produk Jual.
- **Kolom Untung tidak ada?** Role Anda tidak punya izin melihat keuntungan.
MARKDOWN
            ,
            'modul'  => 'penjualan',
            'urutan' => 71,
            'aktif'  => true,
        ]);
    }

    private function panduanKategori(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-kategori-kasir'], [
            'judul'  => 'Laporan Penjualan Kategori (menu Penjualan)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Melihat penjualan per **kategori** (mis. Dimsum, Gyoza, Minuman, Frozen): berapa qty, pendapatan, dan porsinya dari total penjualan — lalu melihat produk apa saja di dalam setiap kategori. Menu ini ada di bagian **Penjualan** dan bisa dibuka kasir.

## Langkah-langkah

### 1. Pilih periode
Tombol **Hari ini / Bulan ini / Tahun ini / Selama ini**, atau isi **Dari tanggal** dan **Sampai tanggal** lalu klik **Tampilkan**.

### 2. Baca grafik porsi
Grafik donat menunjukkan bagian setiap kategori. Tombol di pojok kanan atas grafik mengganti ukurannya: **Pendapatan**, **Qty** (dan **Keuntungan** untuk yang berhak). Daftar di sebelahnya menampilkan nilai dan persentase setiap kategori.

### 3. Lihat produk di dalam kategori
Klik baris kategori di tabel → muncul produk-produk kategori itu beserta qty dan pendapatannya. Klik lagi untuk menutup.
Tautan **Buka kategori ini di Laporan Produk** membuka Laporan Produk yang sudah tersaring ke kategori itu (lengkap dengan grafik dan rincian varian).

### 4. Urutkan & export
- Klik judul kolom **Kategori**, **Qty**, **Pendapatan** (atau **Untung**) untuk mengurutkan.
- **Export Excel** / **Export PDF** menyimpan kategori beserta produk di dalamnya.

## Arti kolom
- **Produk** — banyaknya jenis produk yang terjual di kategori itu.
- **Penjualan / Diskon / Pendapatan** — sama seperti Laporan Produk: diskon order dibagi ke setiap produk sesuai porsi harganya.
- **Porsi** — bagian pendapatan kategori dari total pendapatan produk.
- **Modal / Untung / Margin** — hanya untuk role yang punya izin **Lihat Keuntungan di Laporan Penjualan**.

## Catatan Penting
- Angka setiap kategori = jumlah semua produk di kategori itu, jadi selalu sama dengan Laporan Produk.
- Produk yang belum diberi kategori dikumpulkan di **Tanpa kategori**.
- **Service charge & take away fee** ditampilkan sebagai baris sendiri di bawah tabel; total kategori + baris itu = pendapatan di **Laporan Transaksi**.
- Data mengikuti **cabang yang dipilih di pojok kanan atas**; kasir selalu melihat cabangnya sendiri.
- Transaksi yang **dibatalkan**, **dihapus**, atau **bill tersimpan yang belum dibayar** tidak dihitung.

## Troubleshooting
- **Menu tidak ada di sidebar?** Minta Owner mencentang **Laporan Penjualan Kategori (menu Penjualan)** di Role & Hak Akses.
- **Produk masuk kategori yang salah?** Ganti kategorinya di menu Produk Jual — laporan langsung mengikuti (termasuk untuk penjualan lama).
- **Tautan ke Laporan Produk tidak ada?** Role Anda belum punya izin Laporan Penjualan Produk.
MARKDOWN
            ,
            'modul'  => 'penjualan',
            'urutan' => 72,
            'aktif'  => true,
        ]);
    }

    private function panduanTransaksi(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-transaksi-kasir'], [
            'judul'  => 'Laporan Transaksi (menu Penjualan)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Melihat jumlah transaksi dan pendapatan toko — per tahun, per bulan, per hari, sampai ke setiap transaksi dan struknya. Menu ini ada di bagian **Penjualan** dan bisa dibuka kasir.

## Langkah-langkah

### 1. Pilih periode
Di bagian atas ada 4 tombol:
- **Hari ini** → langsung daftar transaksi hari ini + grafik per jam.
- **Bulan ini** → grafik dan rincian per hari di bulan ini.
- **Tahun ini** → grafik dan rincian per bulan di tahun ini.
- **Selama ini** → grafik dan rincian per tahun sejak transaksi pertama.

Untuk periode lain, isi **Dari tanggal** dan **Sampai tanggal** di sebelah kanan lalu klik **Tampilkan**:
- Rentang sampai 3 bulan → rincian **per hari**.
- Rentang lebih panjang → rincian **per bulan**; klik bulannya untuk melihat per hari (masih di dalam rentang yang dipilih).
- Dari dan sampai tanggal yang sama → langsung daftar transaksi hari itu.

### 2. Klik untuk melihat lebih rinci
Klik **batang grafik** atau **baris tabel** untuk turun satu tingkat:

**Tahun → Bulan → Hari → Daftar transaksi → Struk**

Contoh: pilih **Tahun ini** → klik **Oktober** → muncul rincian setiap hari di bulan Oktober → klik **15 Oktober** → muncul semua transaksi tanggal itu → klik salah satu transaksi → struknya tampil (bisa dicetak ulang).

### 3. Kembali ke tingkat sebelumnya
- Tombol **Kembali** naik satu tingkat (mis. dari 15 Oktober ke Oktober).
- Atau klik jejak halaman di sebelahnya (mis. **Selama ini › 2026 › Oktober › 15 Oktober**, atau **Rentang 1 Jul – 5 Nov 2026 › Oktober 2026 › 15 Oktober**) untuk langsung lompat ke tingkat mana pun.

### 4. Export
Tombol **Export Excel** dan **Export PDF** menyimpan isi halaman yang sedang dibuka. Contoh: sedang membuka Oktober → file berisi rincian per hari bulan Oktober.

## Grafik
- Tombol ikon di pojok kanan atas grafik mengganti tampilan **batang** atau **garis** (pilihan diingat di perangkat ini).
- Arahkan jari/mouse ke grafik untuk melihat angka lengkap; label **Tertinggi** dan **Rata-rata** di atas grafik merangkum periode yang dibuka.

## Arti angka
- **Jumlah Transaksi** — banyaknya order yang sudah dibayar.
- **Pendapatan** — uang yang dibayar pelanggan, sudah dikurangi diskon dan sudah termasuk service charge serta take away fee.
- **Keuntungan** — pendapatan dikurangi modal bahan (HPP) setiap order. Gaji, sewa, dan biaya operasional lain tidak ikut dikurangi (lihat Laporan Keuangan untuk itu).

## Catatan Penting
- Kotak, kolom, dan grafik **Keuntungan** hanya tampil untuk role yang punya izin **Lihat Keuntungan di Laporan Penjualan** (bawaan: Admin Pusat dan Manajer Cabang; Owner selalu bisa). Kasir hanya melihat jumlah transaksi dan pendapatan — termasuk di file Excel/PDF.
- Data mengikuti **cabang yang dipilih di pojok kanan atas**. Kasir selalu melihat cabangnya sendiri. Pilihan **Semua Cabang** menggabungkan semua cabang.
- Transaksi yang **dibatalkan**, **dihapus**, atau **bill tersimpan yang belum dibayar** tidak dihitung.
- Jam transaksi = jam order dibuat di POS.

## Troubleshooting
- **Menu Laporan Transaksi tidak ada di sidebar?** Minta Owner mencentang **Laporan Transaksi (menu Penjualan)** di Role & Hak Akses.
- **Angka beda dengan Laporan Laba Rugi?** Laba Rugi menghitung omzet dari harga barang sebelum diskon order dan tanpa service charge/take away fee; laporan ini memakai uang yang benar-benar dibayar pelanggan.
- **Transaksi tidak muncul?** Cek cabang di pojok kanan atas dan pastikan transaksinya sudah dibayar (bukan masih bill tersimpan).
MARKDOWN
            ,
            'modul'  => 'penjualan',
            'urutan' => 70,
            'aktif'  => true,
        ]);
    }

    private function tooltip(): void
    {
        $tooltips = [
            ['key' => 'laporan_transaksi_kasir.pendapatan', 'title' => 'Pendapatan',
                'content' => 'Uang yang dibayar pelanggan: sudah dikurangi diskon, termasuk service charge dan take away fee. Transaksi dibatalkan, dihapus, atau bill belum dibayar tidak dihitung.'],
            ['key' => 'laporan_transaksi_kasir.keuntungan', 'title' => 'Keuntungan',
                'content' => 'Pendapatan dikurangi modal bahan (HPP) setiap order. Gaji, sewa, dan biaya operasional lain tidak ikut dikurangi.'],
            ['key' => 'laporan_transaksi_kasir.rentang', 'title' => 'Rentang tanggal',
                'content' => 'Pilih tanggal awal dan akhir lalu klik Tampilkan. Rentang sampai 3 bulan tampil per hari, lebih panjang tampil per bulan. Klik bulan/hari untuk rincian.'],
            ['key' => 'laporan_produk_kasir.pendapatan', 'title' => 'Pendapatan produk',
                'content' => 'Penjualan produk dikurangi bagian diskon order. Belum termasuk service charge & take away fee (ditampilkan terpisah di bawah tabel).'],
            ['key' => 'laporan_produk_kasir.penjualan', 'title' => 'Penjualan',
                'content' => 'Harga × qty di keranjang, sebelum diskon order.'],
            ['key' => 'laporan_produk_kasir.diskon', 'title' => 'Diskon',
                'content' => 'Diskon order dibagi ke setiap produk di order itu sesuai porsi harganya.'],
            ['key' => 'laporan_kategori_kasir.rincian', 'title' => 'Rincian per kategori',
                'content' => 'Angka kategori = jumlah semua produk di kategori itu. Klik baris kategori untuk melihat produknya; produk tanpa kategori masuk "Tanpa kategori".'],
            ['key' => 'laporan_transaksi_kasir.tingkat', 'title' => 'Klik untuk rincian',
                'content' => 'Klik batang grafik atau baris tabel untuk turun: tahun → bulan → hari → transaksi → struk. Tombol Kembali naik satu tingkat.'],
        ];

        foreach ($tooltips as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title'   => $t['title'],
                'content' => $t['content'],
                'modul'   => 'penjualan',
                'aktif'   => true,
                'urutan'  => 70 + $i,
            ]);
        }
    }
}
