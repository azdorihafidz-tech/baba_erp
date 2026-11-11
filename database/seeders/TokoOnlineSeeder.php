<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 2 — Toko Online: izin, panduan, tooltip. Idempotent.
 * Centangan role bawaan hanya dipasang saat izin pertama kali dibuat (pola Porting449Seeder).
 */
class TokoOnlineSeeder extends Seeder
{
    public const PERMISSIONS = [
        ['name' => 'toko_online.kelola', 'display_name' => 'Kelola Toko Online (produk, harga, rekening, aturan tagihan)', 'group' => 'toko_online'],
        ['name' => 'toko_online.pesanan', 'display_name' => 'Proses Pesanan Online (ongkir, cek bukti transfer, kirim, batal)', 'group' => 'toko_online'],
    ];

    public const ROLE_DEFAULTS = [
        'admin_pusat'  => ['toko_online.kelola', 'toko_online.pesanan'],
        'admin_gudang' => ['toko_online.pesanan'],
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
        Cache::forget('all_permission_names');

        Panduan::updateOrCreate(['slug' => 'produk-online'], [
            'judul'  => 'Produk Online',
            'konten' => self::PANDUAN,
            'modul'  => 'toko_online',
            'urutan' => 60,
            'aktif'  => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'pesanan-online'], [
            'judul' => 'Pesanan Online', 'konten' => self::PANDUAN_PESANAN, 'modul' => 'toko_online', 'urutan' => 61, 'aktif' => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'pengaturan-toko-online'], [
            'judul' => 'Pengaturan Toko Online', 'konten' => self::PANDUAN_PENGATURAN, 'modul' => 'toko_online', 'urutan' => 62, 'aktif' => true,
        ]);

        foreach ([
            ['key' => 'toko_online.terima_otomatis', 'title' => 'Diterima otomatis',
                'content' => 'Pesanan yang sudah dikirim / siap diambil dianggap diterima bila member tidak menekan Diterima selama sekian hari. 0 = tidak otomatis.'],
            ['key' => 'toko_online.batas_bayar', 'title' => 'Batas waktu bayar',
                'content' => 'Pesanan transfer yang belum diunggah buktinya sampai batas ini dibatalkan otomatis, supaya stok yang dipesan kembali bisa dibeli member lain. 0 = tidak otomatis.'],
            ['key' => 'toko_online.rekening', 'title' => 'Rekening tujuan',
                'content' => 'Rekening yang ditampilkan ke member saat bayar transfer, per gudang. Tanpa rekening aktif, member tidak bisa memilih transfer untuk gudang itu.'],
            ['key' => 'toko_online.tagihan', 'title' => 'Bayar dengan tagihan',
                'content' => 'Member boleh mengambil barang dulu dan membayar sebelum jatuh tempo. Pesanan baru ditolak bila total tagihan yang belum lunas melewati batas.'],
        ] as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'], 'modul' => 'toko_online', 'aktif' => true, 'urutan' => 20 + $i,
            ]);
        }

        foreach ([
            ['key' => 'produk_online.barang', 'title' => 'Barang yang dijual',
                'content' => 'Parfum botol milik gudang (dibuat di Produksi › Buat Parfum). Satu barang hanya bisa didaftarkan sekali. Stok yang dijual = stok barang ini di gudangnya.'],
            ['key' => 'produk_online.harga_tetap', 'title' => 'Harga tetap',
                'content' => 'Harga dasar untuk semua member bila tidak ada harga khusus akun, harga jabatan, atau harga gudang.'],
            ['key' => 'produk_online.harga_gudang', 'title' => 'Harga gudang',
                'content' => 'Harga bila member membeli langsung ke gudang (bukan lewat upline). Dipakai sebelum harga tetap.'],
            ['key' => 'produk_online.harga_jabatan', 'title' => 'Harga per jabatan',
                'content' => 'Harga untuk jabatan tertentu (mis. Distributor lebih murah dari Reseller). Kosongkan bila jabatan itu memakai harga gudang/tetap. Daftar jabatan diambil dari aplikasi member.'],
            ['key' => 'produk_online.harga_akun', 'title' => 'Harga khusus akun',
                'content' => 'Harga untuk akun tertentu saja, paling diutamakan. Cari akun dengan nama, No ID, atau No Member (BP-xxxxx). Hapus barisnya untuk mengembalikan ke harga biasa.'],
        ] as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'], 'modul' => 'toko_online', 'aktif' => true, 'urutan' => 10 + $i,
            ]);
        }
    }

    public const PANDUAN_PESANAN = <<<'MARKDOWN'
## Tujuan
Memproses pesanan member dari menu **Belanja** aplikasi member: isi ongkir, cek bukti transfer, kirim atau siapkan untuk diambil.

## Alur status
1. **Menunggu ongkir** — member memilih *dikirim*. Isi **ongkir** (0 bila gratis), tekan **Simpan ongkir**.
2. **Menunggu pembayaran** — member transfer ke rekening gudang lalu mengunggah bukti di aplikasinya.
3. **Bukti bayar dicek** — buka pesanan, cocokkan bukti dengan mutasi rekening, tekan **Terima pembayaran** atau **Tolak…** (tulis alasan; member diminta unggah ulang).
4. **Disiapkan** — kemas barang. Untuk *dikirim*: isi **kurir** dan **nomor resi**, tekan **Kirim**. Untuk *ambil sendiri*: tekan **Siap diambil**. **Stok gudang dikurangi saat tombol ini ditekan.**
5. **Dikirim / Siap diambil** — member menekan **Diterima** di aplikasinya. Bila diambil di gudang, admin boleh menekan **Sudah diambil**. Tanpa tindakan, pesanan diterima otomatis setelah beberapa hari (Pengaturan Toko Online).

## Pesanan tagihan
- Pesanan **tagihan** langsung masuk **Disiapkan** (setelah ongkir diisi bila dikirim). Member membayar belakangan dengan mengunggah bukti; bukti itu muncul di tab **Perlu tindakan**.
- Jatuh tempo tampil di rincian pesanan, merah bila sudah lewat.

## Catatan Penting
- Tab **Perlu tindakan** = isi ongkir, cek bukti, siapkan barang. Angka merah di menu = jumlahnya.
- Stok yang sudah dipesan tetapi belum dikirim tidak bisa dibeli member lain.
- **Batalkan pesanan…** hanya selama barang belum keluar dari gudang. Bila sudah dibayar, kembalikan dananya ke member.
- Satu bukti transfer tidak bisa dipakai dua kali.
- Setiap perubahan status dikabarkan ke lonceng aplikasi member.

## Troubleshooting
- **Tombol Kirim mati**: stok gudang kurang (angka merah di kolom Stok gudang). Tambah stok dulu lewat Produksi atau penyesuaian stok.
MARKDOWN;

    public const PANDUAN_PENGATURAN = <<<'MARKDOWN'
## Tujuan
Mengatur rekening tujuan transfer, siapa yang boleh bayar dengan tagihan, serta waktu otomatis pesanan online.

## Langkah-langkah
1. **Rekening tujuan transfer**: pilih gudang, isi bank, nomor rekening, atas nama, tekan **Tambah rekening**. Rekening yang tidak dipakai bisa **Sembunyikan**.
2. **Bayar dengan tagihan**: pilih **Untuk** (Semua member / Satu jabatan / Satu akun), isi **Batas nominal** dan **Tempo (hari)**, tekan **Simpan aturan**.
3. **Waktu otomatis**: isi **Diterima otomatis setelah (hari)** dan **Batas waktu bayar transfer (jam)**, tekan **Simpan**.

## Catatan Penting
- Aturan tagihan yang berlaku: aturan **akun** → aturan **jabatan** → **semua member**. Tanpa aturan = member hanya bisa transfer.
- Batas nominal = total tagihan yang belum lunas. Pesanan baru yang membuat total melewati batas ditolak.
- Menyimpan aturan baru untuk sasaran yang sama menonaktifkan aturan lama.
- Gudang tanpa rekening aktif tidak bisa menerima pesanan transfer.
MARKDOWN;

    public const PANDUAN = <<<'MARKDOWN'
## Tujuan
Mengatur parfum yang dijual di menu **Belanja** aplikasi member beserta harganya.

## Langkah-langkah
1. Buka **Toko Online › Produk Online**, tekan **Tambah Produk Online**.
2. Pilih **Barang yang dijual** (parfum botol dari Produksi › Buat Parfum). Nama di aplikasi member terisi otomatis, boleh diganti.
3. Isi kategori (Pria / Wanita / Unisex), deskripsi, foto, dan **Harga tetap** dan/atau **Harga gudang**. Tekan **Simpan**.
4. Di halaman ubah, atur **Harga per jabatan** (mis. Distributor, Reseller) dan **Harga khusus akun** bila perlu.
5. Matikan **Tampil di aplikasi** untuk menyembunyikan produk tanpa menghapusnya.

## Catatan Penting
- Urutan harga untuk seorang member: **harga khusus akun → harga jabatan → harga gudang** (beli langsung ke gudang) **→ harga tetap**.
- Produk yang tidak punya harga yang berlaku untuk seorang member tidak tampil untuk member itu.
- Stok yang terlihat member = stok barang itu di gudangnya.
- Setiap perubahan harga tercatat di Log Aktivitas.

## Troubleshooting
- **Daftar jabatan belum bisa diambil**: sambungan ke aplikasi member belum diatur atau sedang terputus. Harga per jabatan yang sudah tersimpan tetap berlaku.
- **Barang tidak ada di pilihan**: barang harus milik gudang Anda, aktif, dan belum didaftarkan sebagai produk online.
MARKDOWN;
}
