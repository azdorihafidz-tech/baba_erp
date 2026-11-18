<?php

/**
 * 4.50 Kamus Menu — kelompok Dashboard & Keuangan.
 * Fakta diambil dari kode. Kalau perilaku menu berubah, ubah juga teks di sini.
 */
return [

    // ===================== DASHBOARD =====================

    [
        'slug' => 'dashboard', 'judul' => 'Dashboard', 'kelompok' => 'Dashboard', 'urutan' => 10,
        'letak' => 'Dashboard (menu paling atas)',
        'ringkasan' => 'Halaman pertama setelah login. Isinya otomatis menyesuaikan: Dashboard Cabang, Dashboard Gudang, atau Semua Cabang.',
        'izin' => [],
        'route_names' => ['dashboard', 'dashboard.cabang', 'dashboard.gudang'],
        'terkait' => ['dashboard-semua-cabang', 'dashboard-stok', 'dashboard-keuangan'],
        'kata_kunci' => 'dashboard beranda halaman awal ringkasan hari ini omzet hari ini',
        'konten' => <<<'MD'
## Fungsi
Ringkasan cepat kondisi lokasi yang sedang dipilih: penjualan hari ini, order per tipe transaksi, stok menipis, setoran, dan pengingat Closing.

## Dashboard mana yang tampil
| Kondisi | Yang tampil |
|---|---|
| Role boleh melihat semua cabang + pojok kanan atas "Semua Cabang" | **Dashboard Semua Cabang** |
| Cabang yang dipilih bertipe **Gudang Pusat** | **Dashboard Gudang** (stok gudang, permintaan stok dari cabang, transfer) |
| Cabang biasa | **Dashboard Cabang** |

## Supaya isinya muncul
- Bisa dibuka semua user yang login. Kotak-kotak tambahan (pintasan PO, Aset, Neraca, Analitik, Jam Ramai, Loyalty) hanya tampil kalau role punya centangan menu itu.
- **Pengingat Closing** hanya tampil untuk role yang punya centangan Closing Harian.
- Dashboard Gudang hanya bisa dibuka user yang punya akses ke gudang itu.

## Hasil
Hanya menampilkan data — tidak mengubah apa pun.
MD,
    ],

    [
        'slug' => 'dashboard-semua-cabang', 'judul' => 'Dashboard Semua Cabang', 'kelompok' => 'Dashboard', 'urutan' => 20,
        'letak' => 'Dashboard → Semua Cabang',
        'ringkasan' => 'Ringkasan seluruh usaha: penjualan hari ini & bulan ini, uang belum disetor, kas pusat, tren, setoran menunggu, stok minimum.',
        'izin' => ['@semua_cabang'],
        'route_names' => ['dashboard.pusat'],
        'terkait' => ['dashboard', 'setoran-kasir', 'laporan-transaksi-kasir'],
        'kata_kunci' => 'dashboard pusat owner semua cabang ringkasan usaha uang belum disetor kas pusat analisis penjualan',
        'konten' => <<<'MD'
## Fungsi
Melihat kondisi semua cabang sekaligus.

## Supaya menu ini muncul & bisa dipakai
- Hanya role yang boleh melihat **semua cabang** (Owner, Admin Pusat, atau role buatan dengan akses semua cabang). User lain yang membuka alamatnya diarahkan ke Dashboard biasa.
- Bagian **Ringkasan Owner** (Penjualan Hari Ini, Uang Belum Disetor, Kas HO, Setoran Menunggu, Selisih Setoran) butuh centangan **Lihat Widget Dashboard Owner (Setoran)**.

## Angka yang perlu dipahami
- **Uang Belum Disetor** = total Setoran Kasir yang masih Menunggu atau Ditolak. Hari yang kasirnya **belum submit** setoran tidak ikut terhitung.
- **Kas HO** = saldo semua kas aktif milik cabang bertipe Gudang Pusat.
- **Analisis Penjualan** (5 kotak) punya pilihan periode sendiri dan disimpan sementara 5 menit — angka 4 kotak di atasnya tetap "hari ini/bulan ini".
- Kiriman gudang → cabang yang sudah diterima ikut dihitung sebagai penjualan gudang (angka "semua cabang" bisa lebih besar dari jumlah penjualan ke pelanggan).

## Hasil
Hanya menampilkan data.
MD,
    ],

    // ===================== KEUANGAN =====================

    [
        'slug' => 'dashboard-keuangan', 'judul' => 'Dashboard Keuangan', 'kelompok' => 'Keuangan', 'urutan' => 10,
        'letak' => 'Keuangan → Dashboard Keuangan',
        'ringkasan' => 'Grafik & ringkasan uang masuk/keluar dan saldo kas per periode (hari ini, kemarin, bulan ini, rentang sendiri).',
        'izin' => ['keuangan.view'],
        'route_names' => ['keuangan.dashboard', 'keuangan.dashboard.*'],
        'terkait' => ['kas-transaksi', 'laporan-keuangan-kas'],
        'kata_kunci' => 'dashboard keuangan grafik uang masuk keluar saldo kas arus kas',
        'konten' => <<<'MD'
## Fungsi
Melihat pergerakan uang dari **Kas & Transaksi** dalam bentuk grafik dan ringkasan, dibandingkan dengan periode sebelumnya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Keuangan**. Data mengikuti cabang di pojok kanan atas.

## Yang dihitung
Semua transaksi di Kas & Transaksi (penjualan otomatis, pembayaran PO, pengeluaran manual, transfer antar kas, setoran, saldo awal). Ini **arus uang**, bukan laba — untuk untung pakai Laporan Laba Rugi.

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'kas-transaksi', 'judul' => 'Kas & Transaksi', 'kelompok' => 'Keuangan', 'urutan' => 20,
        'letak' => 'Keuangan → Kas & Transaksi (Kelola Kas ada di halaman ini)',
        'ringkasan' => 'Buku kas: semua uang masuk/keluar per kas. Tempat mencatat pengeluaran/pemasukan manual dan mengelola daftar kas tiap cabang.',
        'izin' => ['keuangan.view'],
        'route_names' => ['keuangan.index', 'keuangan.create', 'keuangan.edit', 'keuangan.show', 'keuangan.export', 'keuangan.kas', 'keuangan.kas.*', 'keuangan.po-pending-list'],
        'terkait' => ['transfer-antar-kas', 'transfer-dana', 'setoran-kasir', 'transaksi-berulang', 'kategori-transaksi', 'closing', 'pos', 'purchase-order', 'cara-mencatat-uang'],
        'kata_kunci' => 'kas transaksi buku kas pengeluaran pemasukan catat biaya bayar listrik beli bahan bayar supplier saldo kas kas baru kas default saldo awal bukti nota sinkron saldo',
        'konten' => <<<'MD'
## Fungsi
Semua uang yang masuk/keluar dicatat di sini, per **Kas** (laci tunai, rekening bank, QRIS). Sebagian dibuat otomatis oleh menu lain, sebagian dicatat manual.

## Supaya menu ini muncul & bisa dipakai
- Melihat: **Lihat Keuangan**. Tambah: **Tambah Transaksi**; edit/hapus: **Edit / Hapus Transaksi Manual**. Hubungkan ke PO: **Pilih PO di Form Kas Keluar**. Assign Kas: **Assign Kas Sumber ke Transaksi Lama**.
- Kelola Kas: **Lihat / Tambah / Edit / Hapus Kas**. Tombol Sinkron Saldo: **Sinkron Saldo Kas dari Riwayat**.
- Data mengikuti cabang di pojok kanan atas.
- Setiap cabang **wajib** punya kas aktif dengan **Default untuk** Tunai, Transfer, dan QRIS (1 kas per jenis per cabang). Tanpa itu POS tidak bisa bayar. Gudang Pusat wajib punya kas Default Tunai untuk menerima Setoran Kasir & pendapatan Kiriman Cabang.

## Transaksi yang dibuat OTOMATIS oleh menu lain
| Dari | Transaksi |
|---|---|
| POS (order dibayar) | Pemasukan penjualan ke kas default metode bayar |
| Catat Pembayaran PO | Pengeluaran dari kas yang dipilih |
| Transfer Antar Kas / Transfer Dana / Setoran Kasir | Pasangan keluar-masuk antar kas |
| Kiriman/Retur Cabang | Pengeluaran cabang & pemasukan gudang |
| Penggajian → Bayar | Pengeluaran Gaji Karyawan dari kas yang dipilih |
| Transaksi Berulang | Sesuai template |
| Kas baru dengan saldo awal | Pemasukan "Saldo Awal" |
| Penyesuaian Stok (rusak/hilang/susut) | Beban/koreksi **tanpa kas** (saldo tidak berubah) |

Transaksi otomatis **tidak bisa diedit atau dihapus di sini** — ubah dari menu asalnya (mis. Batalkan order, Batal Bayar PO).

## Apa yang terjadi kalau tombol diklik
| Tombol | Kas | Catatan |
|---|---|---|
| **Tambah Transaksi** | Pemasukan → saldo kas **naik**; pengeluaran → **turun** | Wajib pilih kas, kategori, dan (pengeluaran) jenis pengeluaran. Bukti foto/PDF opsional. Pilih PO → nominal wajib sama persis dengan total PO, lalu PO jadi "Sudah Dibayar" |
| Edit | Saldo kas lama dikembalikan, saldo kas baru dihitung ulang | Hanya transaksi manual |
| Hapus | Saldo kas dikembalikan | Hanya transaksi manual |
| Assign Kas | Saldo kas terpilih berubah | Untuk transaksi lama yang belum punya kas |
| Tambah Kas (saldo awal > 0) | Kas baru + transaksi "Saldo Awal" | |
| Hapus Kas | — | Hanya kalau saldonya 0 |
| **Sinkron Saldo** | Saldo kas dihitung ulang dari semua transaksinya | Pratinjau dulu sebelum disimpan |

## Wajib
- Catat pengeluaran di **kas yang benar-benar mengeluarkan uangnya** — saldo kas harus sama dengan uang nyata.

## Jangan
- Tanggal yang sudah **Closing/Tutup Buku** terkunci: transaksi di tanggal itu tidak bisa ditambah/diubah/dihapus sampai Owner membuka kuncinya.
- Jangan mencatat pemindahan uang antar kas sebagai pengeluaran + pemasukan manual — pakai **Transfer Antar Kas** / **Transfer Dana**.

## Menu yang mirip & bedanya
- **Transfer Antar Kas** — pindah uang antar kas di cabang yang sama.
- **Transfer Dana** — pindah uang ke kas cabang lain (perlu diterima).
- **Setoran Kasir** — kasir menyetor uang tunai penjualan harian ke pusat.
MD,
    ],

    [
        'slug' => 'laporan-keuangan-kas', 'judul' => 'Laporan Keuangan (menu Keuangan)', 'kelompok' => 'Keuangan', 'urutan' => 30,
        'letak' => 'Keuangan → Laporan Keuangan',
        'ringkasan' => 'Pemasukan & pengeluaran usaha per kategori dalam 1 bulan (berbasis kas), tanpa transfer internal & saldo awal.',
        'izin' => ['laporan.keuangan.view'],
        'route_names' => ['keuangan.laporan'],
        'terkait' => ['kas-transaksi', 'cara-melihat-untung'],
        'kata_kunci' => 'laporan keuangan rekap pemasukan pengeluaran per kategori bulanan',
        'konten' => <<<'MD'
## Fungsi
Menjumlah transaksi **Kas & Transaksi** dalam 1 bulan per kategori (judul halaman: "Pemasukan & Pengeluaran Usaha").

## Supaya menu ini muncul & bisa dipakai
- Centangan **Laporan Keuangan**. Data mengikuti cabang di pojok kanan atas (atau pilihan cabang saat "Semua Cabang"). Role yang hanya boleh cabangnya sendiri selalu melihat cabangnya.

## Yang dihitung
- **Tidak dihitung**: transfer antar kas, setoran kasir, transfer dana, dan saldo awal kas (uang yang hanya pindah tempat) — jumlahnya ditampilkan sebagai keterangan.
- Berbasis **kas**: pembelian bahan dihitung sebagai pengeluaran saat dibayar (belum memakai HPP).
- Untuk laba dengan modal barang pakai **Laba Rugi Formal**; untuk angka yang dikunci pakai **Closing / Tutup Buku**.

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'transfer-antar-kas', 'judul' => 'Transfer Antar Kas', 'kelompok' => 'Keuangan', 'urutan' => 40,
        'letak' => 'Keuangan → Transfer Antar Kas',
        'ringkasan' => 'Memindahkan uang antar kas di cabang yang sama (mis. laci tunai → rekening bank). Langsung berlaku, tanpa persetujuan.',
        'izin' => ['transfer_antar_kas.view'],
        'route_names' => ['transfer-antar-kas.*'],
        'terkait' => ['kas-transaksi', 'transfer-dana', 'cara-mengirim-uang-ke-pusat'],
        'kata_kunci' => 'transfer antar kas pindah uang setor bank tarik tunai mutasi kas laci ke bank',
        'konten' => <<<'MD'
## Fungsi
Mencatat uang yang dipindahkan dari satu kas ke kas lain **di cabang yang sama**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Buat / Hapus Transfer Antar Kas**.
- Pilih cabang di pojok kanan atas. Cabang minimal punya **2 kas aktif**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Kas asal | Kas tujuan |
|---|---|---|
| **Simpan** | Turun (ditolak kalau saldo tidak cukup) | Naik — langsung, tanpa persetujuan |
| Hapus | Kembali naik | Kembali turun |

Tercatat sebagai pasangan transaksi mutasi. Tidak dihitung sebagai pemasukan/pengeluaran usaha di Closing (saling meniadakan).

## Menu yang mirip & bedanya
- **Transfer Dana** — ke kas **cabang lain**, harus diterima dulu oleh tujuan.
- **Setoran Kasir** — setoran uang tunai penjualan harian kasir ke pusat (dengan cek selisih).
MD,
    ],

    [
        'slug' => 'transfer-dana', 'judul' => 'Transfer / Perpindahan Dana', 'kelompok' => 'Keuangan', 'urutan' => 50,
        'letak' => 'Keuangan → Transfer / Perpindahan Dana',
        'ringkasan' => 'Mengirim uang dari kas cabang ke kas cabang lain. Kas asal langsung berkurang; kas tujuan bertambah setelah diterima.',
        'izin' => ['setoran.view'],
        'route_names' => ['setoran.*'],
        'terkait' => ['transfer-antar-kas', 'setoran-kasir', 'kas-transaksi', 'cara-mengirim-uang-ke-pusat'],
        'kata_kunci' => 'transfer dana perpindahan dana kirim uang ke pusat setor ke pusat uang antar cabang terima uang tolak',
        'konten' => <<<'MD'
## Fungsi
Memindahkan uang antar cabang (mis. cabang mengirim uang ke pusat), dengan konfirmasi dari penerima.

## Supaya menu ini muncul & bisa dipakai
- Centangan sendiri-sendiri: **Lihat / Buat Transfer Dana**, **Terima/Tolak Transfer Dana**, **Batal Transfer Dana (Pengirim)**. Badge angka di menu = jumlah yang menunggu diterima (untuk pemegang centangan Terima).
- Kas asal = kas milik cabang yang dipilih di pojok kanan atas; kas tujuan = kas milik cabang tujuan.

## Alur
Menunggu Diterima → **Diterima** / **Ditolak**, atau **Dibatalkan** oleh pengirim.

## Apa yang terjadi kalau tombol diklik
| Tombol | Kas asal | Kas tujuan |
|---|---|---|
| **Kirim** | **Langsung turun** (ditolak kalau saldo tidak cukup) | Belum berubah |
| **Terima** | — | Naik |
| **Tolak** | Kembali naik | — |
| **Batalkan** (selama menunggu) | Kembali naik | — |

## Menu yang mirip & bedanya
- **Setoran Kasir** — khusus uang tunai penjualan harian, dihitung otomatis dari POS, dengan pengecekan selisih. Untuk setoran penjualan harian pakai Setoran Kasir, bukan menu ini.
- **Transfer Antar Kas** — antar kas di cabang yang sama, langsung berlaku.
MD,
    ],

    [
        'slug' => 'setoran-kasir', 'judul' => 'Setoran Kasir', 'kelompok' => 'Keuangan', 'urutan' => 60,
        'letak' => 'Keuangan → Setoran Kasir',
        'ringkasan' => 'Kasir menyetor uang tunai penjualan per hari ke Gudang Pusat. Sistem menghitung jumlah seharusnya; pusat menyetujui atau menolak.',
        'izin' => ['setoran_kasir.view'],
        'route_names' => ['setoran-kasir.*'],
        'terkait' => ['transfer-dana', 'pos', 'laporan-setoran-kasir', 'dashboard-semua-cabang', 'cara-mengirim-uang-ke-pusat'],
        'kata_kunci' => 'setoran kasir setor uang tunai setoran harian selisih kasir approve setoran tolak setoran uang belum disetor',
        'konten' => <<<'MD'
## Fungsi
Rekonsiliasi uang tunai penjualan **per cabang per hari**: berapa yang seharusnya disetor (dari POS) vs berapa yang benar-benar diserahkan.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Setoran Kasir** / **Submit Setoran Kasir**; menyetujui/menolak: **Approve / Reject Setoran Kasir (HO)**. Badge angka di menu = setoran menunggu (untuk pemegang Approve).
- Usaha harus punya cabang bertipe **Gudang Pusat** dengan kas aktif Default **Tunai** (tujuan setoran), dan cabang penyetor punya kas Default Tunai. Tanpa Gudang Pusat, Approve gagal — pakai **Transfer Antar Kas** untuk usaha 1 cabang.

## Alur
Kasir buka Setoran Kasir → sistem menghitung rincian per metode bayar hari itu → kasir isi **uang tunai yang diserahkan** (+ foto bukti, catatan) → **Menunggu** → pusat **Approve** atau **Reject** (alasan wajib) → kalau ditolak, kasir mengirim ulang untuk tanggal yang sama.

## Apa yang terjadi kalau tombol diklik
| Tombol | Kas cabang (tunai) | Kas pusat (tunai) |
|---|---|---|
| **Submit** | Belum berubah | Belum berubah |
| **Approve** | Turun sebesar yang disetor | Naik |
| **Reject** | — | — |

- Hanya **uang tunai** yang berpindah. Transfer/QRIS sudah masuk kas non-tunai cabang saat order dibayar — di setoran hanya ditampilkan sebagai informasi.
- 1 cabang hanya 1 setoran per tanggal.
- Kalau tanggal setoran sudah di-Closing, uang dicatat di **tanggal approve**.

## Wajib
- Isi jumlah sesuai uang yang benar-benar diserahkan — selisih ditampilkan di Dashboard & Laporan Setoran Kasir.

## Menu yang mirip & bedanya
- **Transfer Dana** — pindah uang bebas antar cabang (bukan rekonsiliasi penjualan).
MD,
    ],

    [
        'slug' => 'closing', 'judul' => 'Closing / Tutup Buku', 'kelompok' => 'Keuangan', 'urutan' => 70,
        'letak' => 'Keuangan → Closing / Tutup Buku',
        'ringkasan' => 'Membekukan laporan (penjualan, laba rugi, arus kas, saldo kas, stok) per hari atau per periode bulanan, lalu mengunci transaksinya.',
        'izin' => ['closing.view'],
        'route_names' => ['closing.*'],
        'terkait' => ['kas-transaksi', 'riwayat-order', 'pengaturan-umum', 'cara-melihat-untung'],
        'kata_kunci' => 'closing tutup buku kunci periode closing harian tutup buku bulanan buka kunci uang fisik selisih kas laporan dibekukan',
        'konten' => <<<'MD'
## Fungsi
Mengambil "foto" laporan pada akhir hari/periode dan **mengunci** tanggal itu, supaya angka laporan lama tidak berubah lagi.

## Supaya menu ini muncul & bisa dipakai
- Centangan: **Lihat Closing / Tutup Buku**, **Closing Harian**, **Tutup Buku Bulanan**, **Lihat Closing Keseluruhan (semua cabang)**, **Export Closing**. **Buka Kunci Periode** khusus Owner.
- **Tanggal mulai periode** bulanan diatur di **Pengaturan Umum** (1–28; mis. 26 → periode "Oktober" = 26 Sep–25 Okt).
- "Semua Cabang" hanya untuk role yang boleh melihat semua cabang + centangan Lihat Closing Keseluruhan.

## Apa yang terjadi kalau tombol diklik
| Tombol | Syarat | Akibat |
|---|---|---|
| **Closing Harian** | 1 hari, 1 cabang, bukan tanggal yang akan datang; wajib isi **uang fisik** tiap kas tunai | Laporan hari itu dibekukan + selisih kas tercatat. **Tanggal itu terkunci** |
| **Tutup Buku Bulanan** | Periode sudah berakhir (boleh di hari terakhirnya) | Seluruh periode cabang itu terkunci |
| **Tutup Buku Keseluruhan** | Semua cabang aktif sudah tutup buku bulanan periode itu | Rekap gabungan (tidak mengunci apa pun) |
| **Buka Kunci** (Owner, alasan wajib) | — | Kunci dilepas; arsip lama tetap ada. Closing ulang = versi baru |

## Apa yang terkunci
Di tanggal terkunci: order baru/batal/edit, transaksi kas baru/ubah/hapus **ditolak** dari menu mana pun. Setoran Kasir yang di-approve untuk tanggal terkunci dicatat di tanggal approve.

## Jangan
- **Jangan Closing Harian sebelum toko tutup** — penjualan setelahnya di hari itu akan ditolak.
- Closing Harian tidak wajib, tapi kalau dipakai lakukan setiap hari supaya pengingat di Dashboard bersih.

## Menu yang mirip & bedanya
- **Laporan Laba Rugi / Penjualan** — angka berjalan (bisa berubah kalau ada koreksi). Closing = angka yang dibekukan.
MD,
    ],

    [
        'slug' => 'kategori-transaksi', 'judul' => 'Kategori Transaksi', 'kelompok' => 'Keuangan', 'urutan' => 80,
        'letak' => 'Keuangan → Kategori Transaksi',
        'ringkasan' => 'Daftar kategori pemasukan/pengeluaran yang dipilih saat mencatat di Kas & Transaksi, beserta akun COA-nya.',
        'izin' => ['kategori.view'],
        'route_names' => ['kategori-transaksi.*'],
        'terkait' => ['kas-transaksi', 'coa', 'analisis-bep'],
        'kata_kunci' => 'kategori transaksi kategori pengeluaran kategori pemasukan biaya tetap biaya variabel akun coa',
        'konten' => <<<'MD'
## Fungsi
Mengelompokkan setiap uang masuk/keluar (mis. Gaji, Sewa, Listrik). Kategori menentukan baris di laporan keuangan, Laba Rugi Formal (lewat akun COA), dan BEP (biaya tetap/variabel).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Kategori**.
- Kategori berlaku untuk **semua cabang**.

## Isian penting & akibatnya
| Isian | Akibat |
|---|---|
| Tipe (pemasukan / pengeluaran / keduanya) | Kapan kategori muncul di form Kas & Transaksi |
| Induk (sub-kategori) | Pengelompokan di laporan |
| **Akun COA** | Baris kategori ini di Laba Rugi Formal, Neraca, Buku Besar |
| Tipe biaya (tetap/variabel) | Dipakai perhitungan BEP |
| Aktif | Nonaktif = tidak bisa dipilih lagi |

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Hapus | Ditolak untuk **kategori sistem**, kategori yang sudah dipakai transaksi (nonaktifkan saja), atau yang punya sub-kategori |

## Jangan
- Jangan mengubah kode kategori sistem (SALDO, MUTASI, SETOR, dll) — dipakai otomatis oleh menu lain.
MD,
    ],

    [
        'slug' => 'coa', 'judul' => 'Chart of Accounts (COA)', 'kelompok' => 'Keuangan', 'urutan' => 90,
        'letak' => 'Keuangan → Chart of Accounts',
        'ringkasan' => 'Daftar akun akuntansi (Pendapatan, HPP, Beban, Aset, Kewajiban, Modal) yang dipakai Laba Rugi Formal, Neraca, Buku Besar.',
        'izin' => ['coa.view'],
        'route_names' => ['coa.*'],
        'terkait' => ['kategori-transaksi'],
        'kata_kunci' => 'coa chart of accounts akun akuntansi kode akun',
        'konten' => <<<'MD'
## Fungsi
Melihat daftar akun akuntansi. Setiap Kategori Transaksi ditautkan ke salah satu akun di sini supaya laporan akuntansi (Laba Rugi Formal, Neraca, Buku Besar) bisa disusun.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Chart of Accounts**. Halaman ini hanya untuk melihat; penautan dilakukan di **Kategori Transaksi** (kolom Akun COA).

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'analisis-bep', 'judul' => 'Analisis BEP', 'kelompok' => 'Keuangan', 'urutan' => 100,
        'letak' => 'Keuangan → Analisis BEP',
        'ringkasan' => 'Menghitung titik impas (BEP) per cabang per bulan dari biaya tetap dan margin produk yang diisi/diambil otomatis.',
        'izin' => ['bep.view'],
        'route_names' => ['bep.*'],
        'terkait' => ['kategori-transaksi', 'cara-melihat-untung'],
        'kata_kunci' => 'bep break even point titik impas balik modal biaya tetap margin',
        'konten' => <<<'MD'
## Fungsi
Menghitung berapa penjualan yang dibutuhkan supaya tidak rugi (impas), per cabang per bulan.

## Supaya menu ini muncul & bisa dipakai
- Melihat: **Lihat BEP**. Mengisi pengaturan, biaya tetap, produk, Isi Otomatis, Hitung Ulang: **Kelola BEP**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Simpan pengaturan / biaya tetap / produk | Data BEP cabang & bulan itu |
| **Isi Otomatis** | Biaya tetap (sewa, operasional, dll) diambil dari transaksi Kas & Transaksi periode itu |
| Hitung Ulang | Angka BEP dihitung lagi dari data terbaru |

Tidak mengubah stok, kas, atau transaksi.

## Menu yang mirip & bedanya
- **Laporan → Simulator BEP** — kalkulator "bagaimana kalau", tidak menyimpan data.
MD,
    ],

    [
        'slug' => 'transaksi-berulang', 'judul' => 'Transaksi Berulang', 'kelompok' => 'Keuangan', 'urutan' => 110,
        'letak' => 'Keuangan → Transaksi Berulang',
        'ringkasan' => 'Template transaksi rutin (sewa, gaji tetap, langganan) yang dibuat otomatis setiap bulan pada tanggal jatuh tempo.',
        'izin' => ['recurring.view'],
        'route_names' => ['recurring.*'],
        'terkait' => ['kas-transaksi', 'cara-mencatat-uang'],
        'kata_kunci' => 'transaksi berulang recurring rutin bulanan sewa langganan otomatis template jatuh tempo',
        'konten' => <<<'MD'
## Fungsi
Membuat transaksi Kas & Transaksi secara otomatis dari template, supaya biaya rutin tidak lupa dicatat.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Buat / Edit / Hapus Transaksi Berulang** (tombol Generate & Aktif/Nonaktif memakai centangan Edit).

## Kapan transaksi dibuat
| Frekuensi | Jatuh tempo |
|---|---|
| Harian | Setiap hari |
| Mingguan | Setiap hari yang sama dengan **Tanggal Mulai** (mis. mulai Senin → tiap Senin) |
| Bulanan | Setiap **tanggal jatuh tempo**; 29–31 dipakai sebagai hari terakhir di bulan pendek |
| Tahunan | Tanggal jatuh tempo di **bulan Tanggal Mulai** |

- Dibuat **1 kali per periode**, otomatis oleh jadwal server (kalau ada) atau saat ada yang membuka aplikasi. Kalau hari jatuh tempo terlewat, transaksinya dibuat saat aplikasi dibuka berikutnya (bertanggal jatuh tempo; kalau tanggal itu sudah Closing → bertanggal hari ini).
- Berlaku antara Tanggal Mulai dan Tanggal Akhir (kosong = seterusnya). Daftar template menampilkan jatuh tempo berikutnya.
- Kalau kas diisi, saldo kas langsung berubah. Kalau kas kosong, transaksi tercatat tanpa mengubah saldo kas.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Simpan template | Belum ada transaksi |
| **Generate** | Membuat transaksi untuk jatuh tempo yang **belum** dibuat; ditolak kalau periode itu sudah dibuat (tidak bisa dobel) |
| Nonaktifkan | Tidak dibuat otomatis lagi |

## Jangan
- Jangan mencatat transaksi yang sama manual di Kas & Transaksi — jadi dobel.
MD,
    ],
];
