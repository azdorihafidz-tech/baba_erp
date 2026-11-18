<?php

namespace Database\Seeders;

use App\Models\Panduan;
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
        $this->command?->info('✅ Tim IT: panduan (idempotent).');
    }

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
