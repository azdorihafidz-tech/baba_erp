<?php

/**
 * 4.50 Kamus Menu — kelompok SDM & Karyawan + Aset.
 * Fakta diambil dari kode. Kalau perilaku menu berubah, ubah juga teks di sini.
 */
return [

    [
        'slug' => 'karyawan', 'judul' => 'Karyawan', 'kelompok' => 'SDM & Karyawan', 'urutan' => 10,
        'letak' => 'SDM & Karyawan → Karyawan',
        'ringkasan' => 'Data karyawan per lokasi: jabatan, gaji pokok, shift, atasan, rekening — dasar untuk absensi, penggajian, penilaian, dan ganti rugi stok.',
        'izin' => ['karyawan.view'],
        'route_names' => ['karyawan.*'],
        'terkait' => ['registrasi-wajah', 'penggajian', 'shift', 'evaluasi', 'manajemen-user', 'ganti-rugi-karyawan'],
        'kata_kunci' => 'karyawan pegawai staf data karyawan gaji pokok jabatan atasan akun login karyawan',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan orang yang bekerja. Data ini dipakai oleh: Scan/Absensi, Penggajian (gaji pokok, BPJS), Penilaian 360° (atasan), Ganti Rugi Karyawan (barang hilang), dan Absensi Saya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Karyawan**. Data mengikuti cabang di pojok kanan atas.
- **Shift** yang bisa dipilih = shift milik cabang karyawan (buat dulu di Kelola Shift).
- **Akun login** (opsional) dibuat dari form ini hanya kalau role punya centangan **Tambah User**; mengubah email/role akun yang sudah terhubung butuh **Edit User**. Akun Owner hanya diubah lewat Manajemen User.

## Isian penting & akibatnya
| Isian | Dipakai untuk |
|---|---|
| Cabang | Lokasi absensi & penggajian |
| Gaji pokok, % BPJS | Dasar perhitungan slip gaji |
| Shift | Jam masuk & toleransi telat saat Scan Absensi (tanpa shift → jam masuk cabang) |
| Atasan | Penilai utama di Penilaian 360° (atasan harus punya akun login) |
| Status Aktif | Hanya karyawan aktif yang ikut Generate Gaji, Penilaian, dan pilihan karyawan di Ganti Rugi |
| Buat akun login | User baru dengan password awal **password123** |

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Hapus | Karyawan + absensi + log wajah + slip gajinya disembunyikan (Data Terhapus). Akun login **tidak** dihapus, hanya diputus |

## Wajib
- Setelah membuat akun login dari sini, minta karyawan **mengganti password** (password awal sama untuk semua).

## Jangan
- Karyawan yang keluar: ubah statusnya (non-aktif), jangan dihapus — riwayat absensi & gaji ikut tersembunyi.

## Menu yang mirip & bedanya
- **Manajemen User** — akun untuk login & hak akses. Karyawan = data kepegawaian. Satu karyawan boleh tanpa akun.
MD,
    ],

    [
        'slug' => 'dashboard-absensi', 'judul' => 'Dashboard Absensi', 'kelompok' => 'SDM & Karyawan', 'urutan' => 20,
        'letak' => 'SDM & Karyawan → Absensi → Dashboard Absensi',
        'ringkasan' => 'Ringkasan kehadiran hari ini/periode: hadir, terlambat, belum absen, per cabang.',
        'izin' => ['dashboard_absensi'],
        'route_names' => ['dashboard.absensi'],
        'terkait' => ['scan-absensi', 'absensi-manual', 'rekap-absensi'],
        'kata_kunci' => 'dashboard absensi kehadiran hadir terlambat belum absen',
        'konten' => <<<'MD'
## Fungsi
Melihat siapa yang sudah/belum absen dan siapa yang terlambat.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Dashboard Absensi**. Data mengikuti cabang di pojok kanan atas.
- **Terlambat** dihitung dari jam masuk **shift** karyawan (+ toleransi), atau jam masuk **cabang** kalau karyawan tidak punya shift.

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'scan-absensi', 'judul' => 'Scan Absensi (Wajah)', 'kelompok' => 'SDM & Karyawan', 'urutan' => 30,
        'letak' => 'SDM & Karyawan → Absensi → Scan Absensi',
        'ringkasan' => 'Absen masuk/pulang/lembur dengan kamera wajah + lokasi GPS, di tablet toko atau HP.',
        'izin' => ['scan_absensi'],
        'route_names' => ['face-attendance.scan', 'absen-device.*'],
        'terkait' => ['registrasi-wajah', 'shift', 'cabang', 'log-absensi-wajah', 'absensi-saya'],
        'kata_kunci' => 'scan absensi absen wajah face recognition absen masuk absen pulang lembur kamera gps lokasi device absensi tablet absen',
        'konten' => <<<'MD'
## Fungsi
Mencatat jam masuk, pulang, dan lembur dari wajah karyawan.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Scan Absensi Wajah**. Pilih **cabang** di pojok kanan atas (wajib).
- Wajah karyawan harus sudah didaftarkan di **Registrasi Wajah**.
- **Lokasi**: kalau cabang punya titik GPS (Cabang & Gudang → Edit: Latitude/Longitude + Radius), absen di luar radius ditolak. Cabang tanpa GPS = lokasi tidak dicek.
- **Jam masuk** untuk status terlambat: shift karyawan (Kelola Shift) → kalau tidak ada, jam masuk cabang.
- Tablet khusus absensi diatur di **Device Absensi** (centangan Kelola Device Absensi): membuat alamat scan khusus perangkat, bisa dinonaktifkan atau reset token.

## Apa yang terjadi
| Kejadian | Hasil |
|---|---|
| Scan pertama hari itu | Jam **masuk** tercatat (status Hadir, tanda terlambat kalau lewat jam masuk + toleransi) |
| Scan berikutnya | Jam **pulang** (atau pilih Lembur Masuk/Keluar) |
| Sudah absen tipe yang sama hari itu | Ditolak "sudah absen" |
| Gagal gerakan kepala (liveness) / wajah tidak dikenali / di luar radius | Ditolak, percobaan tercatat di Log |
| Terlalu banyak percobaan | Ditahan sebentar |

Absensi dipakai Penggajian (hadir, lembur).

## Menu yang mirip & bedanya
- **Absensi Manual** — admin mengisi/memperbaiki absensi tanpa wajah (izin, sakit, alpha).
MD,
    ],

    [
        'slug' => 'absensi-manual', 'judul' => 'Absensi Manual', 'kelompok' => 'SDM & Karyawan', 'urutan' => 40,
        'letak' => 'SDM & Karyawan → Absensi → Absensi Manual',
        'ringkasan' => 'Mengisi atau memperbaiki absensi karyawan secara manual: hadir, izin, sakit, alpha, libur, cuti.',
        'izin' => ['absensi.manage'],
        'route_names' => ['absensi.index', 'absensi.store', 'absensi.edit', 'absensi.update'],
        'terkait' => ['scan-absensi', 'rekap-absensi', 'penggajian', 'cuti'],
        'kata_kunci' => 'absensi manual input absensi izin sakit alpha bolos koreksi absensi lupa absen',
        'konten' => <<<'MD'
## Fungsi
Mencatat kehadiran yang tidak lewat Scan (lupa absen, izin, sakit, alpha), atau memperbaiki jam.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Absensi**. Menghapus absensi: **Hapus Absensi**.

## Akibat ke Penggajian
| Status | Pengaruh di slip gaji |
|---|---|
| Hadir | Dihitung hadir; jam lembur masuk uang lembur |
| **Alpha** | Dipotong sesuai "Potongan alpa per hari" (Pengaturan Penggajian) |
| Izin / Sakit / Libur / Cuti | Tidak dipotong |

⚠️ Hari **tanpa catatan absensi sama sekali** tidak dihitung Alpha. Kalau karyawan bolos dan ingin dipotong, isi status **Alpha** di sini.

## Menu yang mirip & bedanya
- **Cuti & Izin** — pengajuan + persetujuan; setelah disetujui absensi tanggal itu otomatis berstatus Cuti.
MD,
    ],

    [
        'slug' => 'absensi-saya', 'judul' => 'Absensi Saya', 'kelompok' => 'SDM & Karyawan', 'urutan' => 50,
        'letak' => 'SDM & Karyawan → Absensi → Absensi Saya',
        'ringkasan' => 'Karyawan melihat riwayat absensinya sendiri.',
        'izin' => [],
        'route_names' => ['absensi-saya.*'],
        'terkait' => ['scan-absensi', 'karyawan'],
        'kata_kunci' => 'absensi saya riwayat absen sendiri jam masuk saya',
        'konten' => <<<'MD'
## Fungsi
Melihat absensi milik diri sendiri.

## Supaya menu ini muncul & bisa dipakai
- Tampil di bagian Absensi untuk semua user yang melihat bagian itu.
- Akun login harus **terhubung ke data Karyawan** (dibuat dari menu Karyawan → Buat akun login). Kalau tidak terhubung, halaman kosong.

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'log-absensi-wajah', 'judul' => 'Log Absensi Wajah', 'kelompok' => 'SDM & Karyawan', 'urutan' => 60,
        'letak' => 'SDM & Karyawan → Absensi → Log Absensi Wajah',
        'ringkasan' => 'Semua percobaan scan wajah (berhasil maupun ditolak) beserta jarak GPS dan alasannya.',
        'izin' => ['absensi.manage'],
        'route_names' => ['face-attendance.today', 'face-attendance.log', 'face-attendance.log.*'],
        'terkait' => ['scan-absensi', 'laporan-absensi'],
        'kata_kunci' => 'log absensi wajah riwayat scan ditolak di luar radius liveness gagal',
        'konten' => <<<'MD'
## Fungsi
Memeriksa kenapa absensi seseorang ditolak, atau melihat absensi hari ini.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Absensi**. Menghapus log: **Hapus Log Absensi Wajah**.

## Hasil
Hanya catatan. Menghapus log tidak menghapus absensi yang sudah tercatat.
MD,
    ],

    [
        'slug' => 'rekap-absensi', 'judul' => 'Rekap Absensi Bulanan', 'kelompok' => 'SDM & Karyawan', 'urutan' => 70,
        'letak' => 'SDM & Karyawan → Absensi → Rekap Bulanan',
        'ringkasan' => 'Tabel kehadiran per karyawan per bulan (hadir, izin, sakit, alpha, cuti, lembur).',
        'izin' => ['absensi.manage'],
        'route_names' => ['absensi.rekap'],
        'terkait' => ['absensi-manual', 'penggajian'],
        'kata_kunci' => 'rekap absensi bulanan rekap kehadiran per karyawan',
        'konten' => <<<'MD'
## Fungsi
Memeriksa kehadiran sebulan sebelum Generate Gaji.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Absensi**.

## Hasil
Hanya menampilkan data. Angka di sini sama dengan yang dipakai Penggajian.
MD,
    ],

    [
        'slug' => 'laporan-absensi', 'judul' => 'Laporan Absensi (Wajah)', 'kelompok' => 'SDM & Karyawan', 'urutan' => 80,
        'letak' => 'SDM & Karyawan → Absensi → Laporan Absensi',
        'ringkasan' => 'Laporan absensi wajah per periode, bisa diekspor Excel/PDF.',
        'izin' => ['laporan_absensi'],
        'route_names' => ['laporan.absensi', 'laporan.absensi.*'],
        'terkait' => ['log-absensi-wajah', 'laporan-hr'],
        'kata_kunci' => 'laporan absensi wajah export absensi',
        'konten' => <<<'MD'
## Fungsi
Laporan absensi dari Scan Wajah untuk periode tertentu.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Laporan Absensi**.

## Hasil
Hanya menampilkan data; ekspor Excel/PDF.

## Menu yang mirip & bedanya
- **Laporan → HR / SDM → Absensi** — rekap absensi (semua sumber: scan + manual) per karyawan.
MD,
    ],

    [
        'slug' => 'registrasi-wajah', 'judul' => 'Registrasi Wajah', 'kelompok' => 'SDM & Karyawan', 'urutan' => 90,
        'letak' => 'SDM & Karyawan → Absensi → Registrasi Wajah',
        'ringkasan' => 'Mendaftarkan wajah karyawan supaya bisa absen lewat Scan Absensi.',
        'izin' => ['registrasi_wajah'],
        'route_names' => ['face-registration.*'],
        'terkait' => ['scan-absensi', 'karyawan'],
        'kata_kunci' => 'registrasi wajah daftar wajah rekam wajah face register reset wajah',
        'konten' => <<<'MD'
## Fungsi
Merekam wajah karyawan. Tanpa ini, karyawan tidak bisa absen dengan Scan.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Registrasi Wajah Karyawan**. Karyawan harus sudah ada di menu Karyawan.
- Wajah dikenali per **cabang karyawan** — scan di cabang lain tidak mengenali.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Daftar | Data wajah tersimpan, karyawan bisa Scan |
| Reset | Data wajah dihapus — harus daftar ulang |

## Wajib
- Rekam di tempat terang, wajah menghadap kamera. Kalau sering "tidak dikenali", Reset lalu daftar ulang.
MD,
    ],

    [
        'slug' => 'shift', 'judul' => 'Kelola Shift', 'kelompok' => 'SDM & Karyawan', 'urutan' => 100,
        'letak' => 'SDM & Karyawan → Absensi → Kelola Shift',
        'ringkasan' => 'Jam kerja per cabang (jam masuk, pulang, toleransi telat) yang dipasang ke karyawan.',
        'izin' => ['kelola_shift'],
        'route_names' => ['shift.*'],
        'terkait' => ['karyawan', 'scan-absensi'],
        'kata_kunci' => 'shift jam kerja jam masuk toleransi telat pagi siang malam',
        'konten' => <<<'MD'
## Fungsi
Menentukan jam masuk yang dipakai untuk menandai **terlambat** saat Scan Absensi.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Shift**. Shift dibuat per cabang, lalu dipilih di **Karyawan → Edit → Shift**.

## Akibat
- Karyawan dengan shift: terlambat kalau masuk lewat **jam masuk shift + toleransi**.
- Karyawan tanpa shift: memakai **jam masuk cabang** (Cabang & Gudang → Edit).
- Shift nonaktif tidak bisa dipilih lagi.
MD,
    ],

    [
        'slug' => 'hari-libur', 'judul' => 'Hari Libur', 'kelompok' => 'SDM & Karyawan', 'urutan' => 110,
        'letak' => 'SDM & Karyawan → Absensi → Hari Libur',
        'ringkasan' => 'Daftar libur nasional / cabang yang mengurangi jumlah hari kerja di slip gaji.',
        'izin' => ['kelola_hari_libur'],
        'route_names' => ['hari-libur.*'],
        'terkait' => ['penggajian', 'pengaturan-penggajian'],
        'kata_kunci' => 'hari libur libur nasional tanggal merah cuti bersama',
        'konten' => <<<'MD'
## Fungsi
Mencatat tanggal libur. Tipe **Nasional** berlaku untuk semua cabang; libur **cabang** hanya cabang itu.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Hari Libur**.

## Akibat
- **Jumlah hari kerja** di slip gaji = hari dalam bulan − Minggu (dan Sabtu kalau 5 hari kerja, lihat Pengaturan Penggajian) − hari libur.
- Tidak membuat catatan absensi otomatis.
MD,
    ],

    [
        'slug' => 'penggajian', 'judul' => 'Penggajian', 'kelompok' => 'SDM & Karyawan', 'urutan' => 120,
        'letak' => 'SDM & Karyawan → Penggajian',
        'ringkasan' => 'Membuat slip gaji bulanan dari absensi (hadir, alpha, lembur), gaji pokok, BPJS, PPh21, tunjangan/potongan.',
        'izin' => ['penggajian.view'],
        'route_names' => ['penggajian.*'],
        'terkait' => ['karyawan', 'absensi-manual', 'hari-libur', 'pengaturan-penggajian', 'kas-transaksi'],
        'kata_kunci' => 'penggajian gaji slip gaji payroll generate gaji lembur potongan alpha bpjs pph21 kasbon bayar gaji',
        'konten' => <<<'MD'
## Fungsi
Menghitung gaji karyawan per bulan dan mencetak slip.

## Supaya menu ini muncul & bisa dipakai
- Melihat & cetak: **Lihat Penggajian**. Generate, edit, approve, bayar, hapus: **Kelola Penggajian**.
- Data yang harus ada dulu: **Karyawan aktif** (gaji pokok, % BPJS), **absensi** bulan itu, **Hari Libur**, **Pengaturan Penggajian** (tarif lembur, potongan alpa, potongan telat per menit, hari kerja per minggu).
- Untuk **Tandai Dibayar**: kas aktif milik **lokasi slip** (bawaan Kas Transfer; dibuat otomatis kalau belum ada).

## Cara menghitung (per karyawan)
- Hari kerja = hari dalam bulan − Minggu (− Sabtu kalau 5 hari kerja) − hari libur.
- Hadir, Alpha, jam lembur, dan **menit telat** diambil dari absensi bulan itu.
- Uang lembur = jam lembur × tarif (Pengaturan, atau gaji ÷ 173 × 1,5).
- Potongan absensi = jumlah Alpha × potongan alpa per hari.
- **Potongan telat** = total menit telat × potongan telat per menit.
- **Potongan Ganti Rugi Stok** = cicilan ganti rugi barang hilang dari Stok Opname (Stok & Gudang → Ganti Rugi Karyawan), otomatis, maksimal sebesar gaji bersih; generate ulang slip draft tidak membuat potongan dobel. Saat slip **Dibayar** potongan ini juga dicatat sebagai Beban Gaji + Pemulihan Kerugian Stok (tanpa kas).
- BPJS dari % di data karyawan; PPh21 dihitung otomatis; tunjangan/bonus/kasbon/potongan lain diisi saat generate.

## Alur
**Generate** (1 lokasi, 1 bulan) → slip **Draft** → **Approve** → **Tandai Dibayar** (pilih kas).

## Apa yang terjadi kalau tombol diklik
| Tombol | Slip | Kas |
|---|---|---|
| Generate | Slip Draft dibuat/dihitung ulang; slip **Disetujui/Dibayar dilewati** (namanya disebutkan); karyawan ber-akun dapat notifikasi | — |
| Approve | Disetujui (tidak bisa diedit/dihitung ulang lagi) | — |
| **Tandai Dibayar** (pilih kas lokasi slip) | Dibayar | Kas terpilih **berkurang** sebesar gaji bersih; tercatat otomatis di Kas & Transaksi (kategori Gaji, nomor `TRX-GJ-…`), sekali per slip. Gaji 0 = status saja. Kas lokasi lain ditolak |

Slip yang sudah Dibayar tidak bisa dibatalkan dari menu ini; transaksi gajinya tidak bisa diedit dari Kas & Transaksi.

## Jangan
- Jangan mencatat gaji lagi secara manual di Kas & Transaksi — Tandai Dibayar sudah mencatatnya.
- Hari bolos yang tidak dicatat tidak dipotong — isi **Alpha** di Absensi Manual sebelum Generate.
MD,
    ],

    [
        'slug' => 'cuti', 'judul' => 'Cuti & Izin', 'kelompok' => 'SDM & Karyawan', 'urutan' => 130,
        'letak' => 'SDM & Karyawan → Cuti & Izin',
        'ringkasan' => 'Pengajuan cuti/izin karyawan dan persetujuannya. Disetujui → absensi tanggal itu jadi Cuti, saldo cuti tahunan berkurang.',
        'izin' => ['cuti.view'],
        'route_names' => ['cuti.*'],
        'terkait' => ['absensi-manual', 'penggajian'],
        'kata_kunci' => 'cuti izin ajukan cuti cuti tahunan sakit persetujuan cuti saldo cuti',
        'konten' => <<<'MD'
## Fungsi
Mencatat pengajuan cuti/izin dan keputusan atasan.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Pengajuan Cuti**, **Ajukan/Edit Cuti**, **Setujui/Tolak Cuti**, **Hapus Pengajuan Cuti**. Data mengikuti cabang di pojok kanan atas.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Ajukan | Status menunggu, belum mengubah absensi |
| **Setujui** | Absensi setiap tanggal cuti dibuat/diubah jadi **Cuti** (tidak dipotong gaji). Cuti tahunan mengurangi **saldo cuti** tahun itu. Karyawan dapat notifikasi |
| Tolak | Status ditolak, absensi tidak berubah |

## Jangan
- Menyetujui cuti menimpa absensi yang sudah ada di tanggal itu (mis. Hadir) menjadi Cuti.
MD,
    ],

    [
        'slug' => 'evaluasi', 'judul' => 'Penilaian 360°', 'kelompok' => 'SDM & Karyawan', 'urutan' => 140,
        'letak' => 'SDM & Karyawan → Penilaian 360° / Penilaian Saya',
        'ringkasan' => 'Penilaian kinerja per periode oleh atasan (50%), diri sendiri (20%), dan rekan kerja (30%), lalu ranking.',
        'izin' => ['evaluasi.view', 'evaluasi.fill'],
        'route_names' => ['evaluasi.*'],
        'terkait' => ['karyawan', 'laporan-hr'],
        'kata_kunci' => 'penilaian 360 evaluasi kinerja penilaian karyawan ranking nilai atasan rekan kerja penilaian saya',
        'konten' => <<<'MD'
## Fungsi
Menilai karyawan dari 3 sudut pandang, lalu menghitung nilai akhir & ranking.

## Supaya menu ini muncul & bisa dipakai
- **Penilaian 360°** (daftar periode, ranking, detail): **Lihat Evaluasi**. Membuat & mengelola periode: **Kelola Evaluasi**.
- **Penilaian Saya** (tugas menilai orang lain): semua yang mendapat tugas; mengisi form: **Isi Form Penilaian**.
- Penilai harus punya **akun login yang terhubung ke Karyawan**: atasan diambil dari kolom Atasan di data Karyawan, diri sendiri dari akun karyawan itu.

## Alur
Buat periode (**Draft**) → **Buka** (dibuat penilaian untuk semua karyawan aktif: atasan 50%, diri sendiri 20%) → tambah **Rekan Kerja** (sisa bobot) → penilai mengisi → **Tutup** → **Finalize** (nilai akhir & ranking dikunci).

## Akibat ke data lain
Tidak mengubah gaji, absensi, stok, atau kas.

## Jangan
- Finalize hanya menghitung penilaian yang sudah diisi minimal 1 penilai — tunggu semua mengisi.
MD,
    ],

    // ===================== ASET =====================

    [
        'slug' => 'aset', 'judul' => 'Manajemen Aset', 'kelompok' => 'Aset', 'urutan' => 10,
        'letak' => 'Aset → Manajemen Aset',
        'ringkasan' => 'Daftar aset (peralatan, kendaraan, mesin) per lokasi, nilai buku, penyusutan bulanan, perawatan, dan pelepasan.',
        'izin' => ['aset.view'],
        'route_names' => ['aset.*'],
        'terkait' => ['laporan-aset', 'kas-transaksi', 'jadwal-otomatis'],
        'kata_kunci' => 'aset inventaris peralatan mesin kendaraan penyusutan depresiasi nilai buku maintenance perawatan disposal jual aset',
        'konten' => <<<'MD'
## Fungsi
Mencatat barang modal usaha dan menghitung penyusutannya. Nilai buku aset masuk ke **Neraca**.

## Supaya menu ini muncul & bisa dipakai
- Melihat: **Lihat Aset**. Tambah/ubah, hitung penyusutan per aset, perawatan, pelepasan: **Kelola Aset**. Penyusutan semua aset sekaligus: **Generate Depresiasi Otomatis (Bulk)**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Tambah aset | Nilai buku = harga perolehan. ⚠️ **Kas tidak berkurang** — catat pembayarannya di Kas & Transaksi (kategori Pembelian Aset) |
| **Hitung Penyusutan** (per aset, per bulan) | Nilai buku turun + transaksi **beban penyusutan tanpa kas** (saldo kas tidak berubah, masuk laporan beban). Tidak dobel untuk bulan yang sama |
| Generate penyusutan bulan ini (semua aset) | Sama, untuk semua aset aktif |
| Perawatan | Riwayat perawatan. ⚠️ Biayanya tidak otomatis masuk kas — catat di Kas & Transaksi |
| Pelepasan (dijual/rusak) | Status aset berubah. Uang penjualan aset dicatat sendiri di Kas & Transaksi |

Penyusutan otomatis tiap **tanggal 1** diatur di **Pengaturan → Jadwal Otomatis → Penyusutan aset** (bawaan **mati**). Selama mati, klik Generate setiap bulan.

## Wajib
- Catat uang keluar/masuk terkait aset di **Kas & Transaksi** — menu Aset tidak menyentuh kas.

## Menu yang mirip & bedanya
- **Bahan Baku & Kemasan / Perlengkapan** — barang habis pakai (stok). Aset = barang modal (disusutkan).
MD,
    ],
];
