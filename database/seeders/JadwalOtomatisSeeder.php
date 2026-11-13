<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Services\JadwalOtomatisService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * ERP BABA Tahap 8 — Jadwal Otomatis: baris bawaan tiap tugas (keputusan Owner 13 Nov 2026: hidup = Kirim RO, cadangan,
 * transaksi berulang; mati = pesanan online otomatis, pengingat stok opname, penyusutan aset, loyalty) + panduan.
 * Idempotent: baris yang sudah ada (pilihan Owner) tidak ditimpa.
 */
class JadwalOtomatisSeeder extends Seeder
{
    public function run(): void
    {
        app(JadwalOtomatisService::class)->pastikan();
        Panduan::updateOrCreate(['slug' => 'jadwal-otomatis'], [
            'judul' => 'Jadwal Otomatis', 'konten' => self::PANDUAN, 'modul' => 'pengaturan', 'urutan' => 3, 'aktif' => true,
        ]);
        Cache::forget('panduan:jadwal-otomatis');
    }

    public const PANDUAN = <<<'MD'
## Untuk apa?
Beberapa pekerjaan ERP berjalan **sendiri tanpa ada yang membuka halaman**: mengirim RO belanja online ke aplikasi member, mencatat biaya rutin, pengingat stok opname, penyusutan aset, dan cadangan database. Menu ini menampilkan apakah semuanya berjalan, dan tempat Owner menyalakan / mematikan tugasnya.

## Status cron
- Pekerjaan otomatis dibangunkan oleh **cron** di cPanel setiap menit. Kotak hijau **Cron berjalan** = normal.
- Kotak merah **Cron berhenti / belum terpasang** (dan pita merah di semua halaman Owner) = cron di cPanel hilang atau salah. Salin baris yang tertera (tombol **Salin**), lalu di cPanel › **Cron Jobs** pilih **Once Per Minute (\* \* \* \* \*)** dan tempel di **Command**.

## Tugas otomatis
1. Nyalakan / matikan dengan saklar di kiri tiap tugas, ubah jam bila tersedia, lalu tekan **Simpan**.
2. **Kirim RO ke aplikasi member** selalu hidup (tidak bisa dimatikan).
3. **Jalankan sekarang** menjalankan tugas saat itu juga — juga untuk tugas yang dimatikan.
4. Di bawah tiap tugas tampil hasil terakhir: **Berhasil / Gagal**, kapan, dan pesannya.

| Tugas | Bawaan | Jam bisa diubah |
|---|---|---|
| Kirim RO ke aplikasi member | Hidup, tiap menit | – |
| Pesanan online otomatis | Mati, tiap jam | – |
| Transaksi berulang | Hidup, 00.01 | – |
| Pengingat stok opname | Mati, 08.00 mulai H-3 | jam & H-berapa |
| Penyusutan aset | Mati, tanggal 1 jam 01.00 | – |
| Cek program loyalty | Mati, 03.00 | jam |

## Cadangan database
- Dibuat setiap malam oleh cron cPanel kedua (lihat **Cara memasang cadangan otomatis**) dan disimpan 14 hari. Label **Aman** = ada cadangan dalam 26 jam terakhir.
- Tekan **Unduh** untuk menyimpan salinan di komputer / Google Drive — sebaiknya sesekali, supaya data tetap ada walau server bermasalah.
- Pita kuning di halaman Owner = cadangan belum dibuat dalam 26 jam terakhir.
MD;
}
