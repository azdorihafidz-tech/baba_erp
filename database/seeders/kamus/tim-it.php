<?php

/**
 * Kamus Menu — Tim IT, Aktivitas Tim IT, Paket & Langganan
 * (RANCANGAN_PAKET_LANGGANAN.md). Entri ber-izin '@teknisi' hanya terlihat oleh Tim IT.
 */
return [
    [
        'slug' => 'tim-it', 'judul' => 'Tim IT', 'kelompok' => 'Pengaturan', 'urutan' => 90,
        'letak' => 'Pengaturan → Tim IT (hanya terlihat oleh akun Tim IT)',
        'ringkasan' => 'Mengelola akun tim IT penyedia aplikasi: tambah, ganti kata sandi, aktif/nonaktif. Akun ini tersembunyi dari pengguna BABA dan tidak ada di aplikasi member.',
        'izin' => ['@teknisi'],
        'route_names' => ['tim-it.*'],
        'terkait' => ['aktivitas-tim-it', 'paket-langganan', 'manajemen-user'],
        'kata_kunci' => 'tim it teknisi akun teknisi admin aplikasi penyedia aplikasi vendor maintenance support akun rahasia',
        'konten' => <<<'MD'
## Fungsi
Akun **Tim IT** (role Teknisi) dipakai tim penyedia aplikasi untuk perawatan, pemantauan, dan mengatur paket. Hak aksesnya penuh seperti Owner, ditambah menu **Tim IT** dan **Paket & Langganan**.

## Supaya menu ini muncul & bisa dipakai
- Hanya untuk akun ber-role **Tim IT**. Owner tidak melihat menu ini dan tidak melihat akun Tim IT di Manajemen User.
- Akun Tim IT pertama dibuat di server lewat perintah `php artisan tim-it:buat` (dari isian `.env`). Akun berikutnya ditambah di halaman ini.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Tambah | Akun Tim IT baru, langsung aktif. Kata sandi minimal 12 karakter (huruf + angka) |
| Ganti kata sandi | Kata sandi akun itu diganti |
| Nonaktifkan / Aktifkan | Akun tidak bisa / bisa login lagi. Akun sendiri dan akun aktif terakhir tidak bisa dinonaktifkan |

Semua tindakan di atas (dan semua tindakan Tim IT lain) tercatat di **Aktivitas Tim IT** — hanya Tim IT yang bisa melihatnya. Owner tidak melihat catatan Tim IT, juga tidak di Audit Log.

## Wajib
- Satu akun per orang IT, kata sandi **berbeda di setiap instalasi**.

## Jangan
- Jangan memakai satu akun bersama untuk beberapa orang — catatan aktivitas jadi tidak jelas siapa yang melakukan.
MD,
    ],

    [
        'slug' => 'aktivitas-tim-it', 'judul' => 'Aktivitas Tim IT', 'kelompok' => 'Keamanan', 'urutan' => 5,
        'letak' => 'Keamanan → Aktivitas Tim IT',
        'ringkasan' => 'Catatan setiap login dan setiap simpan/ubah/hapus yang dilakukan akun Tim IT di instalasi ini. Khusus Tim IT, tidak bisa dihapus.',
        'izin' => ['@teknisi'],
        'route_names' => ['aktivitas-tim-it.*'],
        'terkait' => ['audit-log', 'tim-it'],
        'kata_kunci' => 'aktivitas tim it catatan teknisi siapa yang mengubah vendor login tim it jejak tim it keamanan',
        'konten' => <<<'MD'
## Fungsi
Menunjukkan kapan akun Tim IT masuk ke ERP BABA ini dan apa yang dilakukan (menu, waktu, alamat IP) — untuk catatan internal tim IT. Isi formulir (mis. kata sandi) tidak disimpan.

## Supaya menu ini muncul & bisa dipakai
- **Khusus Tim IT.** Owner tidak melihat menu ini, dan catatan Tim IT juga disembunyikan dari Audit Log untuk pengguna BABA.
- Catatan baru ada kalau tim IT pernah login atau melakukan perubahan.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Tampilkan (dari–sampai) | Hanya menyaring tampilan. Tidak ada tombol hapus |

## Menu yang mirip & bedanya
- **Audit Log**: perubahan data oleh pengguna BABA (data lama → baru). Perubahan yang dilakukan akun Tim IT tidak tampil di Audit Log untuk pengguna BABA — lihat di sini.
MD,
    ],

    [
        'slug' => 'paket-langganan', 'judul' => 'Paket & Langganan', 'kelompok' => 'Pengaturan', 'urutan' => 91,
        'letak' => 'Pengaturan → Paket & Langganan (hanya terlihat oleh akun Tim IT)',
        'ringkasan' => 'Menentukan modul yang aktif di ERP BABA ini dan sampai kapan langganan berlaku (masa tenggang lalu baca-saja).',
        'izin' => ['@teknisi'],
        'route_names' => ['paket.*'],
        'terkait' => ['tim-it', 'cara-fitur-tidak-ada', 'role-hak-akses'],
        'kata_kunci' => 'paket modul langganan lisensi aktif sampai masa tenggang baca saja matikan fitur nyalakan fitur paket dasar menengah lengkap kustom',
        'konten' => <<<'MD'
## Fungsi
Mengatur **modul** apa saja yang boleh dipakai (sesuai kesepakatan pembayaran) dan **masa langganan**. Berlaku untuk semua user di instalasi ini, termasuk Owner.

## Supaya menu ini muncul & bisa dipakai
- Hanya akun **Tim IT**. Owner tidak melihat menu ini.

## Apa yang terjadi kalau tombol diklik
| Tombol / isian | Akibat |
|---|---|
| Dasar / Menengah / Lengkap | Hanya mengisi centangan cepat; modul tetap bisa diubah satu-satu (tampil "Kustom") |
| Centang / hilangkan modul | Modul yang dibutuhkan ikut menyala; modul yang membutuhkan ikut mati |
| **Simpan** | Modul mati: menu, tombol, entri Kamus, centangan di Role & Hak Akses hilang; alamatnya langsung menampilkan "tidak termasuk paket". **Data tidak dihapus** — menyalakan lagi mengembalikan semuanya. Pekerjaan otomatis modul itu berhenti. Tercatat di Aktivitas Tim IT |
| Aktif sampai + masa tenggang | Mulai 14 hari sebelum berakhir: pita kuning untuk Owner. Lewat tanggal: pita merah untuk semua user (masih normal). Lewat masa tenggang: **baca-saja** (lihat & unduh laporan boleh, simpan/ubah/hapus & transaksi ditolak). Tim IT tidak terkena baca-saja |

**Aplikasi member ikut paket ini:** modul **Toko Online** mati = menu Belanja "Segera hadir", Pesanan Saya & Toko Saya hanya untuk melihat. Saat baca-saja, memesan, membayar, retur, kasir & opname member ditolak. **Kirim RO tetap jalan** (keputusan Owner). Tugas Jadwal Otomatis milik modul mati / saat baca-saja ditandai **Dilewati**.

## Wajib
- Simpan dari halaman ini saja. Isi yang diubah langsung di database membuat **segel tidak cocok** — aplikasi tetap jalan, tapi Tim IT mendapat peringatan sampai disimpan ulang.

## Jangan
- Jangan mematikan modul yang sedang dipakai tanpa pemberitahuan — menu langsung hilang dari semua user.
MD,
    ],
];
