<?php

/**
 * 4.50 Kamus Menu — kelompok Pengaturan, Bantuan, Keamanan.
 * Fakta diambil dari kode. Kalau perilaku menu berubah, ubah juga teks di sini.
 */
return [

    // ===================== PENGATURAN =====================

    [
        'slug' => 'cabang', 'judul' => 'Cabang & Gudang', 'kelompok' => 'Pengaturan', 'urutan' => 10,
        'letak' => 'Pengaturan → Cabang & Gudang',
        'ringkasan' => 'Daftar lokasi (Gudang Pusat, Toko BABA, Toko Kemitraan, Toko Refill Member, kantor) beserta pengaturan per lokasi: jenis toko, POS, struk, absensi, Kirim ke Cabang.',
        'izin' => ['cabang.view'],
        'route_names' => ['cabang.index', 'cabang.create', 'cabang.edit', 'cabang.show', 'cabang.toggle-aktif', 'cabang.assign-user'],
        'terkait' => ['pos', 'produk-toko', 'kiriman-cabang', 'scan-absensi', 'kas-transaksi', 'manajemen-user', 'stok-opname'],
        'kata_kunci' => 'cabang gudang toko outlet lokasi pemegang stok jenis toko toko baba toko kemitraan toko mitra toko refill member tambah toko pengaturan cabang pos struk gps radius absen jam masuk kirim ke cabang wajib bukti',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan lokasi yang memegang stok (**Pemegang Stok** BABA): Gudang Pusat, toko-toko, dan kantor. Banyak fitur di menu lain **hanya muncul kalau diatur di sini**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Cabang**.
- **Gudang Pusat** dibutuhkan untuk Produksi Parfum, Kirim ke Cabang, Pesanan Online (gudang pengirim), dan tujuan Setoran Kasir.

## Jenis toko (lokasi bertipe Cabang)
| Jenis toko | Akibat |
|---|---|
| **Toko BABA** | Toko milik BABA; keuangannya masuk laporan gabungan BABA |
| **Toko Kemitraan** / **Toko Refill Member** | Stok & pembukuan milik mitra: **tidak** masuk keuangan gabungan BABA; stok opname dikerjakan akun toko itu sendiri (Owner BABA hanya melihat) |

Toko baru otomatis dibuatkan **Kas Tunai, Transfer, QRIS** (syarat POS) dan kasirnya **Takeaway saja**.

## Pengaturan di Edit & menu yang terpengaruh
| Pengaturan | Mengubah menu |
|---|---|
| GPS + **Radius absen**, **Jam masuk** | Scan Absensi (tolak di luar radius, batas terlambat) |
| Struk: footer, sembunyi harga per item | Struk POS |
| Config POS: tipe transaksi, service charge, take away fee | Tampilan & total POS |
| Wajib bukti Transfer / QRIS | Unggah bukti saat bayar |
| Sistem Antrian Produksi (bawaan mati) | Tombol & nomor antrian di POS |
| **Aktifkan Kirim ke Cabang** (Gudang Pusat, bawaan mati) | Jenis Pembeli di POS gudang |
| Kepala cabang, user ditugaskan | Siapa yang bisa memilih lokasi ini; penerima pengingat stok opname |

## Wajib setelah membuat toko
1. Pilih parfum yang dijual di **Produk Parfum Toko** (+ harga, barcode).
2. Isi stok dari gudang (**Transfer Stok**, atau **Kirim ke Cabang** untuk toko mitra).
3. Tugaskan user ke toko (Manajemen User / Edit Cabang).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Nonaktifkan | Hilang dari pilihan, data tetap |
| **Hapus** | Hanya lokasi yang **masih kosong**. Ditolak kalau sudah punya order, PO, transaksi kas, pergerakan stok, absensi, atau slip gaji |

## Jangan
- Toko tutup: **Nonaktifkan**, bukan hapus.
- Jangan mengubah Jenis toko yang sudah berjalan tanpa alasan — laporan keuangan gabungan ikut berubah.
MD,
    ],

    [
        'slug' => 'manajemen-user', 'judul' => 'Manajemen User', 'kelompok' => 'Pengaturan', 'urutan' => 20,
        'letak' => 'Pengaturan → Manajemen User',
        'ringkasan' => 'Akun login: email, password, role (hak akses), cabang yang boleh dibuka, aktif/nonaktif.',
        'izin' => ['user.view'],
        'route_names' => ['user.*'],
        'terkait' => ['role-hak-akses', 'karyawan', 'cabang', 'aktivitas-tim-it'],
        'kata_kunci' => 'user akun login password reset password role cabang user nonaktif tambah user kasir baru',
        'konten' => <<<'MD'
## Fungsi
Membuat dan mengelola akun yang bisa login.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus User** (reset password & aktif/nonaktif ikut centangan Edit).

## Aturan penting
- Role **Owner** hanya bisa diberikan, diubah, dinonaktifkan, atau dihapus **oleh Owner**.
- Role yang tidak boleh melihat semua cabang hanya mengelola user yang berbagi cabang dengannya, dan hanya bisa menugaskan ke cabangnya sendiri.
- Tidak bisa menghapus/menonaktifkan akun sendiri, atau menghapus satu-satunya Owner.
- **Cabang default** = cabang yang terpilih otomatis saat login.
- Akun **Tim IT** penyedia aplikasi tidak tampil di daftar ini dan tidak bisa diubah dari sini. Catatan login & tindakan Tim IT hanya bisa dilihat oleh Tim IT sendiri.

## Apa yang terjadi
| Tombol | Akibat |
|---|---|
| Ganti role | Menu & tombol yang terlihat langsung berubah sesuai centangan role baru |
| Nonaktifkan | Tidak bisa login, data tetap |
| Reset password | Password baru minimal 8 karakter |

## Menu yang mirip & bedanya
- **Karyawan** — data kepegawaian; bisa membuat akun login juga (dengan izin Tambah User).
- **Role & Hak Akses** — mengatur centangan untuk tiap role.
MD,
    ],

    [
        'slug' => 'role-hak-akses', 'judul' => 'Role & Hak Akses', 'kelompok' => 'Pengaturan', 'urutan' => 30,
        'letak' => 'Pengaturan → Role & Hak Akses',
        'ringkasan' => 'Mengatur centangan izin untuk setiap role, dan membuat role sendiri (mis. "Kru").',
        'izin' => ['@owner'],
        'route_names' => ['role.*'],
        'terkait' => ['manajemen-user', 'cara-fitur-tidak-ada'],
        'kata_kunci' => 'role hak akses izin permission centang menu tidak muncul akses ditolak 403 role baru paket modul',
        'konten' => <<<'MD'
## Fungsi
Menentukan menu & tombol apa saja yang bisa dipakai setiap role.

## Supaya menu ini muncul & bisa dipakai
- **Khusus Owner** (Owner selalu bisa semua menu **yang termasuk paket** aplikasi usaha Anda).
- Centangan untuk modul yang **tidak termasuk paket** tidak ditampilkan di sini dan menunya tidak muncul untuk siapa pun; centangan lamanya tetap tersimpan dan kembali kalau modul diaktifkan tim IT.

## Cara kerjanya
- Setiap menu punya centangan sendiri; tombol penting (hapus, batalkan, approve) juga punya centangan sendiri. **Centangan selalu berlaku** — kalau tidak dicentang, menu tidak tampil dan alamatnya ditolak.
- Centangan berlabel "(tidak dipakai — centangan tidak berpengaruh)" boleh diabaikan.
- **Role buatan** bisa ditambah/diubah/dihapus (hapus ditolak kalau masih dipakai user). Role buatan bisa diberi akses semua cabang atau tidak.
- Yang **tidak bisa** diberikan ke role lain: Role & Hak Akses dan Pengaturan Umum (khusus Owner).

## Tips kalau ada yang bilang "menu tidak muncul"
1. Cek apakah menunya termasuk paket — kalau Owner pun tidak melihatnya, hubungi tim IT (lihat Kamus "Kenapa menu atau fitur tidak ada?").
2. Cek centangan menu itu di role user tersebut.
3. Cek **Kamus Menu** menu itu → bagian "Supaya menu ini muncul" (sering ada saklar di menu lain, mis. Antrian/Label/Kirim ke Cabang di Edit Cabang).
4. Cek cabang di pojok kanan atas.
5. Kalau tombol Simpan selalu ditolak dan ada pita "Mode baca-saja": langganan sudah habis — hubungi tim IT.
MD,
    ],

    [
        'slug' => 'pengaturan-umum', 'judul' => 'Pengaturan Umum', 'kelompok' => 'Pengaturan', 'urutan' => 40,
        'letak' => 'Pengaturan → Pengaturan Umum',
        'ringkasan' => 'Identitas usaha, logo, warna aplikasi, Mode RO belanja online, aturan Stok Opname, dan tanggal mulai periode Tutup Buku.',
        'izin' => ['@owner'],
        'route_names' => ['pengaturan.umum', 'pengaturan.umum.*'],
        'terkait' => ['closing', 'catatan-ro', 'stok-opname', 'jadwal-otomatis'],
        'kata_kunci' => 'pengaturan umum logo nama usaha tagline warna aplikasi tema mode ro ro lunas ro disetujui hitung buta foto barang hilang periode tutup buku',
        'konten' => <<<'MD'
## Fungsi
Pengaturan yang berlaku untuk **seluruh ERP BABA**.

## Supaya menu ini muncul & bisa dipakai
- **Khusus Owner** (dan Tim IT).

## Isian & akibatnya
| Bagian | Akibat |
|---|---|
| Identitas Usaha (nama, nama singkat, tagline, telepon) & **Logo** | Sidebar, login, struk, PDF, slip gaji, aplikasi kasir |
| **RO dari Belanja Online** (saat lunas / lunas + disetujui / hanya saat diterima-disetujui) | Kapan RO pesanan online dicatat & apakah dokumen RO di aplikasi member perlu approve. Berlaku untuk kejadian berikutnya; tercatat di Log Aktivitas |
| **Stok Opname**: hitung buta bawaan, batas nilai barang hilang wajib foto | Opname **baru**, aturan foto saat rincian. Tercatat di Log Aktivitas |
| **Warna Aplikasi** | Sidebar, tombol, grafik, PDF & Excel |
| Tanggal mulai periode | Periode Tutup Buku Bulanan |

Pengingat stok opname akhir bulan (nyala/mati, H-x, jam) diatur di **Jadwal Otomatis**.

## Jangan
- Jangan mengubah tanggal mulai periode setelah ada Tutup Buku.
MD,
    ],

    [
        'slug' => 'jadwal-otomatis', 'judul' => 'Jadwal Otomatis', 'kelompok' => 'Pengaturan', 'urutan' => 45,
        'letak' => 'Pengaturan → Jadwal Otomatis',
        'ringkasan' => 'Status cron server, saklar & jam tugas otomatis (Kirim RO, pesanan online, transaksi berulang, pengingat opname, penyusutan, loyalty), dan cadangan database harian.',
        'izin' => ['@owner'],
        'route_names' => ['pengaturan.jadwal', 'pengaturan.jadwal.*'],
        'terkait' => ['catatan-ro', 'backup', 'transaksi-berulang', 'dashboard-opname', 'paket-langganan'],
        'kata_kunci' => 'jadwal otomatis cron schedule tugas otomatis kirim ro pesanan online otomatis transaksi berulang pengingat opname penyusutan aset loyalty cadangan database backup jalankan sekarang dilewati',
        'konten' => <<<'MD'
## Fungsi
Memastikan pekerjaan yang harus berjalan sendiri benar-benar berjalan di server hosting.

## Supaya menu ini muncul & bisa dipakai
- **Khusus Owner** (dan Tim IT).
- Server wajib punya **cron tiap menit** (baris siap salin ada di halaman). Kotak hijau **Cron berjalan** = detak terakhir < 5 menit. Kalau berhenti, Owner melihat pita merah di semua halaman (hanya di hosting).

## Tugas & bawaannya
| Tugas | Bawaan | Akibat |
|---|---|---|
| **Kirim RO ke aplikasi member** | Selalu hidup (tidak bisa dimatikan) | Catatan RO terkirim tiap menit; yang gagal diulang |
| Pesanan online otomatis | Mati | Pesanan diterima otomatis setelah N hari, batal otomatis lewat batas bayar |
| Transaksi berulang | Hidup 00:01 | Transaksi berulang yang jatuh tempo dibuat |
| Pengingat stok opname | Mati (08:00, H-3) | Lonceng lokasi yang belum opname |
| Penyusutan aset | Mati (tgl 1) | Penyusutan bulanan semua aset |
| Cek program loyalty | Mati (03:00) | Pencapaian loyalty |

**Jalankan sekarang** menjalankan tugas saat itu juga (tugas yang mati pun bisa). Tugas milik modul yang **tidak termasuk paket**, atau saat **baca-saja**, tertulis **Dilewati** beserta alasannya — kecuali Kirim RO.

## Cadangan database
Dibuat **cron kedua** tiap hari jam 02.00, disimpan 14 hari, label **Aman** bila ≤ 26 jam. Unduh dari sini atau dari Keamanan → Backup Database.

## Jangan
- Jangan mematikan cron di hosting — RO belanja online tidak akan sampai ke aplikasi member.
MD,
    ],

    [
        'slug' => 'pengaturan-penggajian', 'judul' => 'Pengaturan Penggajian', 'kelompok' => 'Pengaturan', 'urutan' => 50,
        'letak' => 'Pengaturan → Pengaturan Penggajian',
        'ringkasan' => 'Tarif dasar perhitungan slip gaji: tarif lembur, potongan alpa per hari, hari kerja per minggu.',
        'izin' => ['pengaturan_penggajian'],
        'route_names' => ['pengaturan.penggajian', 'pengaturan.penggajian.*'],
        'terkait' => ['penggajian', 'hari-libur'],
        'kata_kunci' => 'pengaturan penggajian tarif lembur potongan alpa hari kerja per minggu potongan telat',
        'konten' => <<<'MD'
## Fungsi
Angka dasar yang dipakai **Generate Gaji**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Pengaturan Tarif Penggajian**.

## Isian & akibatnya
| Isian | Akibat di slip gaji |
|---|---|
| Tarif lembur per jam | Uang lembur = jam lembur × tarif (0 = gaji pokok ÷ 173 × 1,5) |
| Potongan alpa per hari | Dikalikan jumlah Alpha |
| Hari kerja per minggu (5/6) | 5 = Sabtu ikut libur saat menghitung hari kerja |
| Potongan telat per menit | Dikalikan total menit telat (dari Scan Absensi) → baris "Potongan Telat" di slip. 0 = tidak dipotong |

Perubahan berlaku untuk slip yang di-Generate **setelahnya**.
MD,
    ],

    [
        'slug' => 'tooltip-helper', 'judul' => 'Tooltip Helper', 'kelompok' => 'Pengaturan', 'urutan' => 60,
        'letak' => 'Pengaturan → Tooltip Helper',
        'ringkasan' => 'Mengubah teks bantuan kecil (ikon ?) yang muncul di samping isian form.',
        'izin' => ['panduan.kelola'],
        'route_names' => ['admin.tooltips.*'],
        'terkait' => ['panduan-helper'],
        'kata_kunci' => 'tooltip bantuan ikon tanda tanya teks bantuan',
        'konten' => <<<'MD'
## Fungsi
Mengedit isi tooltip (ikon ? di form).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Panduan & Tooltip**.

## Perlu diketahui
- Pembaruan aplikasi bisa menulis ulang sebagian tooltip bawaan. Catat perubahan penting Anda.
MD,
    ],

    [
        'slug' => 'panduan-helper', 'judul' => 'Panduan Helper', 'kelompok' => 'Pengaturan', 'urutan' => 70,
        'letak' => 'Pengaturan → Panduan Helper',
        'ringkasan' => 'Mengubah isi panduan (tombol "Cara Pakai") setiap menu.',
        'izin' => ['panduan.kelola'],
        'route_names' => ['admin.panduan.*'],
        'terkait' => ['panduan', 'tooltip-helper'],
        'kata_kunci' => 'panduan helper edit panduan cara pakai',
        'konten' => <<<'MD'
## Fungsi
Menambah/mengedit/menonaktifkan panduan langkah-langkah.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Panduan & Tooltip**.

## Perlu diketahui
- Pembaruan aplikasi bisa menambah bagian baru atau menulis ulang panduan tertentu. Kamus Menu **tidak** bisa diedit di sini — isinya ikut pembaruan aplikasi.
MD,
    ],

    // ===================== BANTUAN =====================

    [
        'slug' => 'panduan', 'judul' => 'Panduan', 'kelompok' => 'Bantuan', 'urutan' => 10,
        'letak' => 'Bantuan → Panduan (juga tombol "Cara Pakai" di setiap halaman)',
        'ringkasan' => 'Langkah-langkah cara memakai setiap menu.',
        'izin' => [],
        'route_names' => ['panduan.*'],
        'terkait' => ['kamus-menu', 'panduan-helper'],
        'kata_kunci' => 'panduan cara pakai langkah tutorial bantuan',
        'konten' => <<<'MD'
## Fungsi
Panduan langkah demi langkah ("klik ini, isi itu").

## Supaya menu ini muncul & bisa dipakai
- Bisa dibuka semua user yang login.

## Menu yang mirip & bedanya
- **Kamus Menu** — fungsi, syarat muncul, alur, dan **akibat** setiap tombol ke stok/kas/laporan, plus perbandingan beberapa cara. Panduan = cara klik.
MD,
    ],

    [
        'slug' => 'kamus-menu', 'judul' => 'Kamus Menu', 'kelompok' => 'Bantuan', 'urutan' => 20,
        'letak' => 'Bantuan → Kamus Menu (juga tombol "Kamus" di atas setiap halaman)',
        'ringkasan' => 'Penjelasan setiap menu: fungsi, syarat supaya muncul, alur, akibat tiap tombol, wajib, jangan, dan menu yang mirip.',
        'izin' => [],
        'route_names' => ['kamus-menu.*'],
        'terkait' => ['panduan', 'role-hak-akses'],
        'kata_kunci' => 'kamus menu penjelasan menu fungsi menu cari menu bantuan',
        'konten' => <<<'MD'
## Fungsi
Tempat mencari penjelasan menu dengan kata sehari-hari, mis. "pindah barang", "batal order", "untung".

## Supaya menu ini muncul & bisa dipakai
- Bisa dibuka semua user; yang tampil hanya penjelasan menu yang **boleh Anda buka**.

## Cara memakai
- Ketik pertanyaan di kotak cari → klik hasil.
- Bagian **Cara Melakukan** membandingkan beberapa cara untuk tujuan yang sama.
- Tombol **Kamus** di bagian atas halaman langsung membuka penjelasan halaman yang sedang dibuka.
MD,
    ],

    // ===================== KEAMANAN =====================

    [
        'slug' => 'audit-log', 'judul' => 'Audit Log', 'kelompok' => 'Keamanan', 'urutan' => 10,
        'letak' => 'Keamanan → Audit Log',
        'ringkasan' => 'Catatan siapa melakukan apa dan kapan (membuat, mengubah, menghapus data).',
        'izin' => ['lihat_audit_log'],
        'route_names' => ['audit-log.*'],
        'terkait' => ['data-terhapus'],
        'kata_kunci' => 'audit log riwayat aktivitas siapa yang mengubah jejak perubahan',
        'konten' => <<<'MD'
## Fungsi
Melacak perubahan data: siapa, kapan, data lama → data baru.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Audit Log**.

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'data-terhapus', 'judul' => 'Data Terhapus', 'kelompok' => 'Keamanan', 'urutan' => 20,
        'letak' => 'Keamanan → Data Terhapus',
        'ringkasan' => 'Tempat data yang dihapus (order, barang, kas, karyawan, cabang, dll). Bisa dipulihkan atau dihapus permanen.',
        'izin' => ['lihat_data_terhapus'],
        'route_names' => ['trash.*'],
        'terkait' => ['audit-log', 'riwayat-order', 'bahan-baku', 'backup'],
        'kata_kunci' => 'data terhapus tempat sampah restore pulihkan hapus permanen trash',
        'konten' => <<<'MD'
## Fungsi
Hampir semua tombol Hapus di aplikasi hanya **menyembunyikan** data. Di sini data itu bisa dilihat dan dipulihkan.

## Supaya menu ini muncul & bisa dipakai
- Melihat: **Lihat Data Terhapus**. Pulihkan: centangan restore. Hapus permanen: **Hapus Permanen Data**.
- Pilih jenis data di tab (Orders, Items, Kas, Karyawan, …).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| **Pulihkan** | Data kembali beserta data yang dulu ikut terhapus bersamanya (mis. pulihkan barang → baris stok/PO lamanya ikut kembali). Order yang dipulihkan kembali masuk laporan apa adanya |
| **Hapus Permanen** | Hilang selamanya, tidak bisa dibatalkan |

## Jangan
- Jangan memulihkan barang dobel yang sudah punya pengganti — stoknya bisa jadi dobel.
- Order yang dulu dihapus dalam keadaan Selesai (cara lama): pulihkan lalu **Batalkan** supaya stok & kas ikut kembali.
MD,
    ],

    [
        'slug' => 'backup', 'judul' => 'Backup Database', 'kelompok' => 'Keamanan', 'urutan' => 30,
        'letak' => 'Keamanan → Backup Database',
        'ringkasan' => 'Melihat, mengunduh, dan menghapus file cadangan database yang dibuat otomatis setiap hari oleh cron server.',
        'izin' => ['lihat_backup'],
        'route_names' => ['backup.*'],
        'terkait' => ['jadwal-otomatis', 'data-terhapus'],
        'kata_kunci' => 'backup cadangan database unduh backup restore simpan data',
        'konten' => <<<'MD'
## Fungsi
Menyimpan salinan seluruh database ERP.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Backup**; **Download Backup** untuk Unduh; **Hapus File Backup** untuk hapus.
- File dibuat **cron server** tiap hari jam 02.00 (dipasang sekali; caranya di Pengaturan → Jadwal Otomatis) dan disimpan 14 hari.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Unduh | File `erp-TanggalJam.sql.gz` tersimpan di komputer Anda |
| Hapus | File itu hilang dari server |

Tidak ada lagi tombol "Buat backup": cara lama itu tidak bisa berjalan di server hosting.

## Wajib
- **Unduh** secara rutin dan simpan di luar server.

## Perlu diketahui
- Mengembalikan (restore) dilakukan lewat hosting, bukan dari menu ini.
MD,
    ],
];
