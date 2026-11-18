<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * Porting Dimsum 4.53 ke BABA (_rancangan/RANCANGAN-KAMUS-TIMIT-PAKET.md) — panduan & tooltip
 * Tim IT, Aktivitas Tim IT (Tahap A) dan Paket & Langganan (Tahap B). Idempotent (updateOrCreate / penanda).
 * Dijalankan sebelum IstilahBabaSeeder.
 */
class PaketLanggananSeeder extends Seeder
{
    public function run(): void
    {
        Panduan::updateOrCreate(['slug' => 'tim-it'], [
            'judul' => 'Tim IT (akun penyedia aplikasi)', 'konten' => self::PANDUAN_TIM_IT, 'modul' => 'pengaturan', 'urutan' => 95, 'aktif' => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'aktivitas-tim-it'], [
            'judul' => 'Aktivitas Tim IT', 'konten' => self::PANDUAN_AKTIVITAS, 'modul' => 'keamanan', 'urutan' => 5, 'aktif' => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'paket-langganan'], [
            'judul' => 'Paket & Langganan', 'konten' => self::PANDUAN_PAKET, 'modul' => 'pengaturan', 'urutan' => 96, 'aktif' => true,
        ]);
        foreach ([
            'role-hak-akses' => "## Izin yang tidak tampil\nCentangan untuk modul yang **tidak termasuk paket** ERP BABA tidak ditampilkan di halaman ini, dan menunya tidak muncul untuk siapa pun (termasuk Owner). Centangan lama tetap tersimpan dan muncul lagi kalau modul itu diaktifkan oleh tim IT penyedia aplikasi.\n",
            'jadwal-otomatis' => "## Tugas yang dilewati\nTugas milik modul yang **tidak termasuk paket**, atau saat aplikasi dalam **mode baca-saja** (langganan berakhir), tidak dijalankan: hasil terakhirnya tertulis **Dilewati** beserta alasannya, juga bila diklik **Jalankan sekarang**. **Kirim RO ke aplikasi member tetap jalan** — hanya mengirim RO dari transaksi yang sudah terjadi.\n",
            'pengaturan-toko-online' => "## Paket & langganan\nKalau modul **Toko Online** tidak termasuk paket, menu Toko Online hilang dan aplikasi member memperlakukannya seperti toko ditutup: menu **Belanja** menampilkan *Segera hadir*, **Pesanan Saya** dan **Toko Saya** tetap bisa dibuka untuk **melihat**, tetapi memesan, membayar, retur, kasir member, dan stok opname member ditolak. Saat **mode baca-saja** aturannya sama. RO dari pesanan yang sudah lunas tetap terkirim.\n",
        ] as $slug => $tambah) {
            $p = Panduan::where('slug', $slug)->first();
            if ($p && ! str_contains((string) $p->konten, '<!-- PAKET-453 -->')) {
                $p->update(['konten' => rtrim((string) $p->konten) . "\n\n<!-- PAKET-453 -->\n" . $tambah]);
            }
        }
        foreach ([
            ['paket.modul', 'Modul aktif', 'Modul yang boleh dipakai di ERP BABA ini. Modul mati: menu & izin hilang untuk semua user (termasuk Owner), data tetap aman.'],
            ['paket.aktif_sampai', 'Aktif sampai', 'Hari terakhir langganan berlaku. Kosong = tanpa batas. Sesudahnya masa tenggang, lalu baca-saja. Tim IT tidak terkena baca-saja.'],
        ] as $i => [$key, $title, $content]) {
            Tooltip::updateOrCreate(['key' => $key], ['title' => $title, 'content' => $content, 'modul' => 'pengaturan', 'aktif' => true, 'urutan' => 200 + $i]);
        }
        $this->command?->info('✅ Tim IT, Paket & Langganan: panduan & tooltip (idempotent).');
    }

    public const PANDUAN_PAKET = <<<'MARKDOWN'
## Tujuan
Menentukan **modul** ERP BABA yang aktif sesuai kesepakatan pembayaran, dan **masa langganan**. Hanya akun Tim IT yang bisa membuka halaman ini.

## Langkah-langkah
1. Buka **Pengaturan → Paket & Langganan**.
2. Klik **Dasar**, **Menengah**, atau **Lengkap** untuk mengisi centangan cepat, lalu sesuaikan per modul kalau perlu (tampil **Kustom**). Modul yang dibutuhkan ikut menyala otomatis; mematikan modul ikut mematikan modul yang membutuhkannya.
3. Isi **Aktif sampai** (kosong = tanpa batas) dan **Masa tenggang** (bawaan 7 hari).
4. Klik **Simpan**.

## Akibat
- **Modul mati**: menu, tombol, entri Kamus, dan centangannya di Role & Hak Akses hilang untuk semua user, termasuk Owner. Alamatnya langsung menampilkan "Fitur … tidak termasuk paket Anda". Pekerjaan otomatis modul itu **dilewati** (lihat Jadwal Otomatis). **Data tidak dihapus.**
- **Modul Toko Online mati**: di aplikasi member, Belanja menjadi *Segera hadir*; Pesanan Saya & Toko Saya hanya untuk melihat.
- **Masa langganan**: 14 hari sebelum berakhir → pita kuning untuk Owner. Lewat tanggal → pita merah untuk semua user, aplikasi masih normal selama masa tenggang. Lewat masa tenggang → **baca-saja**: login, melihat data, dan mengunduh laporan tetap bisa; simpan/ubah/hapus dan transaksi kasir ditolak; di aplikasi member memesan, membayar, retur, kasir & opname member ditolak. **Kirim RO tetap jalan.** Tim IT tidak terkena baca-saja.
- Semua perubahan tercatat di **Aktivitas Tim IT**.

## Catatan Penting
- Isi paket diberi **segel**. Kalau diubah langsung di database, segel tidak cocok: aplikasi tetap berjalan, tetapi Tim IT mendapat pita merah & lonceng (sekali sehari) sampai paket disimpan ulang dari halaman ini.
- Setelah dipasang, ERP BABA otomatis **Lengkap tanpa batas tanggal** sampai diatur.
- Jangan mengganti `APP_KEY` di `.env` — segel ikut rusak.
MARKDOWN;

    public const PANDUAN_TIM_IT = <<<'MARKDOWN'
## Tujuan
Akun **Tim IT** dipakai tim penyedia aplikasi untuk merawat dan memantau ERP BABA. Hak aksesnya penuh seperti Owner. Akun ini **tidak terlihat oleh pengguna BABA** (tidak ada di Manajemen User, Role & Hak Akses, pilihan role, pilihan kepala lokasi, penghitung/penyetuju opname) dan tidak bisa diubah Owner. Tim IT tidak ada di aplikasi member.

## Langkah-langkah
1. Akun Tim IT pertama dibuat di server lewat perintah `php artisan tim-it:buat`, yang membaca isian `.env` (`TEKNISI_NAMA`, `TEKNISI_EMAIL`, `TEKNISI_PASSWORD`). Hapus baris `TEKNISI_PASSWORD` dari `.env` setelah akun dibuat.
2. Login dengan akun itu, buka **Pengaturan → Tim IT**.
3. **Tambah** satu akun untuk setiap orang IT (nama, email, kata sandi minimal 12 karakter huruf + angka).
4. **Ganti kata sandi** atau **Nonaktifkan** akun orang yang sudah tidak bertugas.

## Catatan Penting
- Gunakan kata sandi **berbeda di setiap instalasi**. Batas salah kata sandi tetap berlaku.
- Toko mitra (Toko Kemitraan / Toko Refill Member): Tim IT sama seperti Owner BABA — hanya **melihat** opname & stok rusak toko mitra; yang mengerjakan tetap akun toko itu sendiri.
- Tim IT tidak menerima lonceng pekerjaan pengguna (pesanan online, persetujuan opname, pengingat).
- Semua login dan tindakan Tim IT tercatat di **Keamanan → Aktivitas Tim IT**. Hanya Tim IT yang bisa melihatnya — Owner tidak, dan catatan Tim IT juga disembunyikan dari Audit Log.
- Akun sendiri dan akun Tim IT aktif terakhir tidak bisa dinonaktifkan.
- Verifikasi dua langkah belum ada (ditunda) — jaga kata sandi baik-baik.
MARKDOWN;

    public const PANDUAN_AKTIVITAS = <<<'MARKDOWN'
## Tujuan
Catatan internal tim IT: kapan akun Tim IT masuk ke ERP BABA dan apa yang dilakukan.

## Langkah-langkah
1. Buka **Keamanan → Aktivitas Tim IT**.
2. Pilih tanggal **Dari** – **Sampai** (bawaan 30 hari terakhir), klik **Tampilkan**.

## Isi catatan
- **Login**: waktu dan alamat IP.
- **Tindakan**: setiap simpan/ubah/hapus — nama menu, alamat halaman, metode, alamat IP. Isi formulir (mis. kata sandi) tidak disimpan.

## Catatan Penting
- **Khusus Tim IT.** Owner tidak melihat menu ini. Catatan tidak bisa dihapus dari halaman ini.
- Di **Audit Log**, pengguna BABA tidak melihat catatan Tim IT maupun perubahan data yang dilakukan akun Tim IT; Tim IT melihat semuanya.
MARKDOWN;
}
