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
        Panduan::updateOrCreate(['slug' => 'retur-online'], [
            'judul' => 'Retur Online', 'konten' => self::PANDUAN_RETUR, 'modul' => 'toko_online', 'urutan' => 64, 'aktif' => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'catatan-ro'], [
            'judul' => 'Catatan RO Belanja Online', 'konten' => self::PANDUAN_RO, 'modul' => 'toko_online', 'urutan' => 65, 'aktif' => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'stok-member'], [
            'judul' => 'Stok Member', 'konten' => self::PANDUAN_STOK_MEMBER, 'modul' => 'toko_online', 'urutan' => 63, 'aktif' => true,
        ]);

        foreach ([
            ['key' => 'toko_online.terima_otomatis', 'title' => 'Diterima otomatis',
                'content' => 'Pesanan yang sudah dikirim / siap diambil dianggap diterima bila member tidak menekan Diterima selama sekian hari. 0 = tidak otomatis.'],
            ['key' => 'toko_online.batas_bayar', 'title' => 'Batas waktu bayar',
                'content' => 'Pesanan transfer yang belum diunggah buktinya sampai batas ini dibatalkan otomatis, supaya stok yang dipesan kembali bisa dibeli member lain. 0 = tidak otomatis.'],
            ['key' => 'toko_online.rekening', 'title' => 'Rekening tujuan',
                'content' => 'Rekening yang ditampilkan ke member saat bayar transfer, per gudang. Tanpa rekening aktif, member tidak bisa memilih transfer untuk gudang itu.'],
            ['key' => 'toko_online.status_toko', 'title' => 'Status toko online',
                'content' => 'Tutup = menu Belanja di aplikasi member tampil Segera hadir. Uji coba = hanya akun yang dipilih yang bisa belanja. Buka = semua member. Pesanan yang sudah berjalan tetap bisa diproses walau toko ditutup.'],
            ['key' => 'pengaturan_umum.mode_ro', 'title' => 'Mode RO belanja online',
                'content' => 'Saat lunas = RO langsung dihitung begitu pesanan lunas. Saat lunas + disetujui = RO dibuat saat lunas lalu menunggu approve dokumen (Regional, MVO, superadmin) di aplikasi member. Hanya disetujui = RO dibuat saat barang diterima tanpa menunggu lunas, lalu menunggu approve.'],
            ['key' => 'toko_online.batas_retur', 'title' => 'Batas pengajuan retur',
                'content' => 'Member hanya bisa mengajukan retur sampai sekian hari setelah pesanannya diterima. 0 = tanpa batas.'],
            ['key' => 'toko_online.sumber', 'title' => 'Sumber barang',
                'content' => 'Mengatur member boleh belanja langsung ke gudang, ke upline-nya, atau keduanya. Tanpa aturan = gudang saja. Pesanan ke upline memakai stok upline dan diproses upline di aplikasi member.'],
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

## Pesanan antar member
- Tab **Antar member** (Owner / admin pusat) memuat pesanan member ke **upline**-nya. Pesanan ini diproses upline di menu **Toko Saya** aplikasi member, memakai stok upline. Di ERP hanya dipantau; **Batalkan pesanan…** bisa dipakai bila pesanan macet.

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
1. **Status toko online untuk member**: **Tutup** (menu Belanja di aplikasi member tampil “Segera hadir”), **Uji coba** (hanya akun yang dipilih — tambahkan lewat kotak cari), atau **Buka** (semua member). Tekan **Simpan status**.
1. **Sumber barang**: pilih **Untuk** (Semua member / Satu jabatan / Satu akun), centang **Boleh ke gudang** dan/atau **Boleh ke upline**, tekan **Simpan aturan**. Tanpa aturan = member hanya belanja ke gudang.
1. **Rekening tujuan transfer**: pilih gudang, isi bank, nomor rekening, atas nama, tekan **Tambah rekening**. Rekening yang tidak dipakai bisa **Sembunyikan**.
2. **Bayar dengan tagihan**: pilih **Untuk** (Semua member / Satu jabatan / Satu akun), isi **Batas nominal** dan **Tempo (hari)**, tekan **Simpan aturan**.
3. **Waktu otomatis**: isi **Diterima otomatis setelah (hari)** dan **Batas waktu bayar transfer (jam)**, tekan **Simpan**.

## Catatan Penting
- Aturan tagihan yang berlaku: aturan **akun** → aturan **jabatan** → **semua member**. Tanpa aturan = member hanya bisa transfer.
- Batas nominal = total tagihan yang belum lunas. Pesanan baru yang membuat total melewati batas ditolak.
- Menyimpan aturan baru untuk sasaran yang sama menonaktifkan aturan lama.
- Gudang tanpa rekening aktif tidak bisa menerima pesanan transfer.
- Status **Tutup** hanya menghentikan belanja & retur baru. Pesanan yang sudah berjalan tetap bisa dibayar, dikirim, dan diterima; menu Pesanan Saya & Toko Saya tetap terbuka bagi member yang sudah punya pesanan.
- Pakai **Uji coba** untuk mencoba toko dengan beberapa akun sebelum dibuka untuk semua member.
MARKDOWN;

    public const PANDUAN_RETUR = <<<'MARKDOWN'
## Tujuan
Memutuskan retur yang diajukan member atas pesanan gudang yang sudah diterima (mis. botol bocor, aroma tidak sesuai).

## Langkah-langkah
1. Buka **Toko Online › Retur Online**. Tab **Diajukan** berisi retur yang menunggu keputusan (angka merah di menu).
2. Buka retur: periksa barang, jumlah, alasan, dan foto dari member.
3. Setelah barang **sudah kembali ke gudang** dan diperiksa, tekan **Setujui retur**. Bila pesanan sudah lunas, pilih cara mengembalikan dana (transfer / tunai).
4. Bila retur tidak bisa diterima, tulis alasan lalu tekan **Tolak**.

## Yang terjadi saat disetujui
- Stok gudang bertambah (dengan modal asal barang), stok member berkurang.
- Pesanan belum lunas: nilai retur **memotong** yang harus dibayar member. Bila sisa menjadi 0, pesanan otomatis lunas.
- Pesanan sudah lunas: kembalikan dananya sesuai pilihan (dicatat di retur).
- RO member berkurang sesuai jumlah botol yang diretur.

## Catatan Penting
- Member hanya bisa mengajukan retur dalam batas hari di **Pengaturan Toko Online** dan selama barangnya masih ada di stoknya.
- Tab **Antar member** (Owner / admin pusat) memuat retur ke upline — diputuskan upline di aplikasi member.
MARKDOWN;

    public const PANDUAN_RO = <<<'MARKDOWN'
## Tujuan
Memantau RO (jumlah botol) dari belanja online yang dikirim otomatis ke aplikasi member.

## Cara kerja
- Pesanan member (ke gudang atau ke upline) menambah **RO pembeli** sebesar jumlah botolnya. Kapan RO dihitung diatur di **Pengaturan Umum › RO dari Belanja Online** (Mode RO).
- Setiap catatan menjadi **dokumen RO** di aplikasi member, sehingga ikut Dashboard, ranking, dan Saran RO. Pada mode yang memakai persetujuan, dokumen menunggu approve di menu Document Belum Approve aplikasi member.
- **Retur disetujui** dan **pesanan dibatalkan setelah RO tercatat** membuat catatan RO negatif (pengurang).
- Catatan yang dipicu dari aplikasi member (mis. upline mencatat bayar tunai, pembeli menekan Diterima) dan yang gagal dikirim (aplikasi member sedang tidak bisa dihubungi) dikirim otomatis **tiap menit**; bisa juga tekan **Kirim ulang yang tertunda**. Catatan yang sama tidak pernah tercatat dua kali.

## Catatan Penting
- Mengubah Mode RO hanya berlaku untuk kejadian berikutnya.
- Penjualan kasir ke pelanggan dan isi ulang per ml tidak menambah RO.
MARKDOWN;

    public const PANDUAN_STOK_MEMBER = <<<'MARKDOWN'
## Tujuan
Memantau stok parfum yang dipegang member (hasil belanja di aplikasi member).

## Cara kerja stok member
- Stok member **bertambah otomatis** saat pesanannya **diterima** (dari gudang atau dari upline), dengan modal = harga belinya.
- Stok **berkurang** saat member (sebagai upline) mengirim / menyiapkan pesanan downline-nya.
- Halaman ini hanya untuk dilihat: buka **Rinci** untuk melihat stok per parfum dan riwayat keluar-masuknya.

## Catatan Penting
- Nilai (modal) = jumlah botol × modal rata-rata.
- Member juga melihat stoknya sendiri di menu **Toko Saya › Stok Saya** aplikasi member.
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
