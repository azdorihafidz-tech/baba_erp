<?php

/**
 * Kamus Menu ERP BABA — kelompok Stok & Gudang (porting Dimsum 4.50, ditulis ulang dari kode BABA:
 * Produksi Parfum Tahap 1, Produk Parfum Toko Tahap 3, Stok Opname Tahap 7).
 * Kalau perilaku menu berubah, ubah juga teks di sini.
 */
return [

    [
        'slug' => 'dashboard-stok', 'judul' => 'Dashboard Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 10,
        'letak' => 'Stok & Gudang → Dashboard Stok',
        'ringkasan' => 'Ringkasan kondisi stok: barang hampir habis, nilai stok per lokasi, barang jarang bergerak, umur stok, tren harga.',
        'izin' => ['stok.view'],
        'route_names' => ['stok.dashboard', 'stok.dashboard.*', 'stok.aging', 'stok.top-movement', 'stok.trend-harga', 'stok.stok-mati'],
        'terkait' => ['stok-barang', 'bahan-baku', 'laporan-stok'],
        'kata_kunci' => 'dashboard stok stok menipis stok habis nilai stok persediaan slow moving stok mati aging umur stok tren harga',
        'konten' => <<<'MD'
## Fungsi
Melihat kondisi stok gudang/toko dengan cepat sebelum belanja bahan atau memeriksa gudang.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Stok**. Data mengikuti cabang/toko di pojok kanan atas.
- Yang dihitung: barang ber-stok (cairan parfum, parfum botol, botol & kemasan, bahan). Produk kasir (`JL-…`, `RF-…`) tidak punya stok sendiri — stoknya ada di barang padanannya.
- Peringatan "hampir habis" hanya untuk barang yang **Qty Minimum-nya diisi** di Stok Barang.

## Yang dihitung
- **Nilai stok** = harga FIFO setiap batch yang tersisa (sama dengan Neraca/Closing).
- Stok member **tidak** termasuk (ada di Toko Online → Stok Member).

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'stok-barang', 'judul' => 'Stok Barang', 'kelompok' => 'Stok & Gudang', 'urutan' => 20,
        'letak' => 'Stok & Gudang → Stok Barang',
        'ringkasan' => 'Jumlah stok setiap barang per lokasi, kartu stok (riwayat keluar-masuk), atur Qty Minimum, dan reset stok.',
        'izin' => ['stok.view'],
        'route_names' => ['stok.index', 'stok.kartu'],
        'terkait' => ['penyesuaian-stok', 'stok-opname', 'dashboard-stok', 'bahan-baku', 'cara-menambah-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'stok barang jumlah stok sisa stok kartu stok riwayat stok qty minimum batas minimum reset stok ml botol',
        'konten' => <<<'MD'
## Fungsi
Melihat sisa stok setiap barang di setiap lokasi (gudang, toko BABA, toko mitra) dan riwayatnya (**Kartu Stok**).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Stok**. Tombol lain: **Set Threshold Stok Minimum per Lokasi** (Qty Minimum), **Hapus/Reset Stok ke 0** (Reset), **Penyesuaian Stok**.
- Barang baru muncul setelah **pertama kali punya stok** (PO diterima, Penyesuaian, Transfer/Kiriman diterima, Produksi, retur online).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Kartu Stok | Melihat riwayat. Nomor SO (stok opname), pesanan online, dan retur bisa diklik |
| Set Qty Minimum | Batas "hampir habis" → peringatan di Dashboard Stok & Laporan Stok Minimum |
| **Reset ke 0** | Wajib ketik nama barang. Semua batch di lokasi ini dihapus. **Tidak** mencatat rugi di keuangan |

Barang yang sedang ada di **Stok Opname** berjalan tidak bisa di-reset atau disesuaikan.

## Jangan
- Jangan pakai Reset untuk hitung fisik — pakai **Stok Opname** (ada rincian penyebab, foto, persetujuan, dan kerugian masuk Laba Rugi).
MD,
    ],

    [
        'slug' => 'penyesuaian-stok', 'judul' => 'Penyesuaian Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 25,
        'letak' => 'Stok & Gudang → Stok Barang → tombol Penyesuaian Stok',
        'ringkasan' => 'Koreksi cepat stok satu barang ke jumlah fisik. Selisihnya dicatat dengan alasan; rusak/hilang/salah hitung turun dicatat sebagai beban.',
        'izin' => ['stok.adjustment'],
        'route_names' => ['stok.adjustment', 'stok.adjustment.*'],
        'terkait' => ['stok-opname', 'stok-barang', 'pemakaian-perlengkapan', 'cara-menambah-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'penyesuaian stok adjustment koreksi stok selisih stok rusak hilang susut kadaluarsa stok awal',
        'konten' => <<<'MD'
## Fungsi
Mengisi **jumlah stok fisik** satu barang; sistem menghitung selisih dan mencatatnya. Langsung berlaku, tanpa persetujuan.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Penyesuaian Stok**. Barang yang bisa dipilih: barang aktif **milik lokasi** di pojok kanan atas.
- Barang yang sedang ada di **Stok Opname** berjalan di lokasi itu ditolak.

## Alasan & akibatnya ke keuangan
| Alasan | Catatan keuangan (tanpa kas) |
|---|---|
| Susut, Rusak/Kadaluarsa, Hilang/Dicuri | Turun → **Beban Kerugian Stok**; naik → **Koreksi Stok Masuk** |
| Salah Hitung, Audit | Turun → **Selisih Persediaan** (masuk Laba Rugi); naik → tidak dicatat (dipakai untuk stok awal) |
| Lainnya | Hanya dicatat kalau "Catat sebagai biaya rugi" dicentang |

Stok turun = FIFO (atau pilih batch); stok naik = batch baru dengan harga diisi / Harga Standar.

## Wajib
- Untuk **stok awal**: isi harga (atau Harga Standar dulu) supaya modal penjualan tidak 0.

## Jangan
- Hitung fisik rutin akhir bulan → **Stok Opname**. Pemakaian sehari-hari → **Pemakaian Perlengkapan**. Pindah lokasi → **Transfer Stok**.
MD,
    ],

    [
        'slug' => 'stok-opname', 'judul' => 'Stok Opname', 'kelompok' => 'Stok & Gudang', 'urutan' => 26,
        'letak' => 'Stok & Gudang → Stok Opname',
        'ringkasan' => 'Hitung fisik stok per lokasi (gudang, toko BABA, toko mitra) dengan hitung buta, rincian penyebab, foto, dan persetujuan. Kerugian masuk Laba Rugi tanpa mengurangi kas.',
        'izin' => ['stok_opname.lihat', 'stok_opname.hitung', 'stok_opname.ajukan', 'stok_opname.setujui'],
        'route_names' => ['stok-opname.index', 'stok-opname.show', 'stok-opname.mulai', 'stok-opname.mulai.*', 'stok-opname.hitung', 'stok-opname.hitung-ulang',
            'stok-opname.barang', 'stok-opname.rincian', 'stok-opname.foto', 'stok-opname.ajukan', 'stok-opname.setujui', 'stok-opname.tolak', 'stok-opname.batal', 'stok-opname.berita-acara*'],
        'terkait' => ['stok-rusak', 'ganti-rugi-karyawan', 'dashboard-opname', 'laporan-kerugian-stok', 'penyesuaian-stok', 'stok-member'],
        'kata_kunci' => 'stok opname stock opname hitung fisik hitung stok cek stok selisih barang hilang barang rusak pecah bocor kedaluwarsa susut menguap tester salah catat lebih berita acara akhir bulan audit stok hitung buta scan barcode',
        'konten' => <<<'MD'
## Fungsi
Mencocokkan stok sistem dengan hitungan fisik di satu lokasi. Selisih wajib dirinci penyebabnya, lalu disetujui. Saat disetujui, stok disesuaikan sebesar **selisih** dan kerugiannya dicatat sebagai **beban tanpa kas** (masuk Laba Rugi, saldo kas tidak berubah).

## Supaya menu ini muncul & bisa dipakai
- Centangan (Role & Hak Akses): **Lihat**, **Hitung**, **Ajukan** (mulai & periksa), **Setujui**, **Batal**. Bawaan: Admin Pusat semua; Admin Gudang & Manajer Cabang lihat/hitung/ajukan; Kasir & Helper lihat/hitung; Pemilik Toko Mitra semua (tokonya).
- Hanya lokasi yang menjadi akses user. Penghitung tidak boleh menyetujui opname yang ia hitung sendiri (kecuali Owner/Tim IT dan toko mitra).
- **Toko Kemitraan / Toko Refill Member**: hanya akun toko itu sendiri yang mengerjakan; Owner BABA hanya melihat; kerugiannya di luar keuangan BABA.
- **Hitung buta** (bawaan nyala) & **batas nilai wajib foto barang hilang** (bawaan Rp 200.000) diatur di **Pengaturan Umum → Stok Opname**.

## Alur
Mulai (lokasi, cakupan: semua barang / per kategori / barang tertentu; nomor `SO-…`) → **Hitung** (ketik, atau scan barcode = +1; simpan otomatis; ml boleh desimal) → **Selisih**: minta hitung ulang, **Isi penyebab** + foto → **Ajukan** → **Setujui** atau **Tolak** (alasan, kembali dihitung). **Batal** sebelum disetujui.

## Apa yang terjadi saat disetujui
| Penyebab | Stok | Keuangan (tanpa kas) |
|---|---|---|
| Rusak / pecah / bocor | Keluar ke **Stok Rusak** (ditahan) | Beban Kerugian Stok – Rusak |
| Hilang | Keluar | Beban Kerugian Stok – Hilang (bisa dibebankan ke karyawan) |
| Susut / menguap | Keluar | Beban Penyusutan Stok |
| Tester / promosi | Keluar | Beban Promosi |
| Kedaluwarsa | Keluar | Beban Kerugian Stok – Kedaluwarsa |
| Salah catat | Keluar | Selisih Persediaan |
| Fisik lebih | Batch baru (harga pokok rata-rata) | Koreksi Stok Masuk |

Yang diterapkan adalah **selisih** (fisik − stok sistem saat barang dihitung), jadi toko tetap boleh berjualan selama opname. Kalau stok keburu terjual sehingga tidak cukup dikurangi, persetujuan ditolak.

## Wajib
- Jumlah rincian penyebab = selisih (boleh dipecah). Foto wajib untuk Rusak, dan untuk Hilang bila nilainya ≥ batas.

## Menu yang mirip & bedanya
- **Penyesuaian Stok** — koreksi cepat tanpa persetujuan. **Stok opname member** (Toko Saya di aplikasi member) — stok milik member, tanpa persetujuan, hanya berkurang.
MD,
    ],

    [
        'slug' => 'stok-rusak', 'judul' => 'Stok Rusak', 'kelompok' => 'Stok & Gudang', 'urutan' => 27,
        'letak' => 'Stok & Gudang → Stok Rusak',
        'ringkasan' => 'Barang rusak dari Stok Opname ditahan di sini. Tindak lanjut: dipakai lagi, kembali ke pemasok (ganti barang / uang / potong tagihan), atau dimusnahkan.',
        'izin' => ['stok_opname.stok_rusak', 'stok_opname.lihat'],
        'route_names' => ['stok-opname.rusak', 'stok-opname.rusak.*'],
        'terkait' => ['stok-opname', 'laporan-kerugian-stok', 'supplier'],
        'kata_kunci' => 'stok rusak barang rusak pecah bocor musnahkan berita acara pemusnahan kembali ke pemasok supplier ganti barang potong tagihan dipakai lagi',
        'konten' => <<<'MD'
## Fungsi
Menahan barang yang dicatat **Rusak** saat Stok Opname disetujui, lalu mencatat tindak lanjutnya. Barang di sini bukan stok jual. Kerugiannya sudah dicatat saat opname disetujui. Angka kuning di menu = barang masih ditahan.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Stok Opname**; tombol **Tindak lanjut** butuh **Kelola stok rusak** (bawaan Admin Pusat, Admin Gudang, Pemilik Toko Mitra).

## Apa yang terjadi kalau tombol diklik
| Tindakan | Stok | Keuangan | Kas |
|---|---|---|---|
| Dipakai lagi | Masuk lagi ke stok jual (harga pokok asal) | Kerugian dibatalkan (baris minus) | — |
| Pemasok ganti barang | Masuk lagi ke stok jual | Kerugian dibatalkan | — |
| Pemasok bayar uang | — | **Pemulihan Kerugian Stok** (pendapatan lain) | Kas lokasi **bertambah** |
| Pemasok potong tagihan | — | — (catatan PO/faktur wajib) | — |
| Musnahkan | — | — | — (foto wajib, **Berita Acara Pemusnahan** PDF) |

Jumlah boleh sebagian; selesai saat sisa 0.

## Jangan
- Jangan membuang barang rusak lagi lewat Penyesuaian Stok — kerugiannya jadi dobel.
MD,
    ],

    [
        'slug' => 'dashboard-opname', 'judul' => 'Dashboard Opname', 'kelompok' => 'Stok & Gudang', 'urutan' => 28,
        'letak' => 'Stok & Gudang → Dashboard Opname',
        'ringkasan' => 'Status stok opname bulan ini per lokasi, menunggu persetujuan, kerugian vs bulan lalu, akurasi 6 bulan, barang paling sering selisih, stok rusak, ganti rugi.',
        'izin' => ['stok_opname.laporan'],
        'route_names' => ['stok-opname.dashboard'],
        'terkait' => ['stok-opname', 'laporan-kerugian-stok', 'jadwal-otomatis'],
        'kata_kunci' => 'dashboard opname status opname belum opname akurasi stok kerugian bulan ini pengingat akhir bulan',
        'konten' => <<<'MD'
## Fungsi
Memantau disiplin dan hasil stok opname semua lokasi.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Kerugian Stok** (bawaan Admin Pusat, Pemilik Toko Mitra). Lokasi bawaan = lokasi BABA; toko mitra bila dipilih.
- Angka merah di menu = lokasi **belum** opname bulan ini, hanya selama masa pengingat (H-3 akhir bulan, bisa diatur).
- Lonceng pengingat dikirim tugas **Pengingat stok opname akhir bulan** di **Jadwal Otomatis** (bawaan **mati**; H-x & jam diatur di sana).

## Isi
Status per lokasi (sudah / menunggu persetujuan / sedang dihitung / **belum**), daftar menunggu persetujuan Anda, kerugian per penyebab vs bulan lalu, grafik akurasi 6 bulan, 10 barang paling sering kurang, stok rusak tertahan, ganti rugi karyawan, dan (akun semua cabang) stok opname member bulan ini.

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'ganti-rugi-karyawan', 'judul' => 'Ganti Rugi Karyawan', 'kelompok' => 'Stok & Gudang', 'urutan' => 29,
        'letak' => 'Stok & Gudang → Ganti Rugi Karyawan',
        'ringkasan' => 'Barang hilang dari Stok Opname dibebankan ke karyawan: dibayar tunai (kas masuk) atau potong gaji dicicil 1–12 bulan otomatis di slip.',
        'izin' => ['stok_opname.ganti_rugi'],
        'route_names' => ['stok-opname.ganti-rugi', 'stok-opname.ganti-rugi.*'],
        'terkait' => ['stok-opname', 'penggajian', 'laporan-kerugian-stok'],
        'kata_kunci' => 'ganti rugi karyawan barang hilang dibebankan potong gaji cicilan tunai bayar ganti rugi',
        'konten' => <<<'MD'
## Fungsi
Mencatat ganti rugi karyawan atas barang **Hilang** di opname yang sudah disetujui.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Bebankan & terima ganti rugi karyawan** (bawaan Admin Pusat, Pemilik Toko Mitra).
- Dibebankan dari halaman opname **selesai** → tab Selisih → **Bebankan ke karyawan**. Potong gaji butuh modul **Penggajian** termasuk paket.

## Apa yang terjadi kalau tombol diklik
| Tombol | Kas | Keuangan | Gaji |
|---|---|---|---|
| Bebankan (nilai bawaan = harga pokok; nilai lain wajib alasan; boleh dibagi ke beberapa karyawan) | — | — | — |
| **Tunai** / Bayar tunai | Kas lokasi **bertambah** | Pemulihan Kerugian Stok | — |
| **Potong gaji** (1–12 bulan, mulai bulan depan) | — | Dicatat saat slip **dibayar** | Cicilan masuk potongan slip otomatis, maks. gaji bersih, tidak dobel saat generate ulang |
| Batalkan | Hanya bila belum ada pembayaran | — | Potongan menunggu dikeluarkan dari slip belum dibayar |
MD,
    ],

    [
        'slug' => 'laporan-kerugian-stok', 'judul' => 'Laporan Kerugian Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 30,
        'letak' => 'Stok & Gudang → Laporan Kerugian Stok',
        'ringkasan' => 'Kerugian stok dari opname yang disetujui per penyebab, lokasi, dan barang; riwayat opname; stok rusak & tindak lanjut; ganti rugi; penyesuaian manual. Excel per tab.',
        'izin' => ['stok_opname.laporan'],
        'route_names' => ['stok-opname.laporan'],
        'terkait' => ['stok-opname', 'stok-rusak', 'ganti-rugi-karyawan', 'laporan-laba-rugi-formal'],
        'kata_kunci' => 'laporan kerugian stok rugi barang hilang rusak susut akurasi opname per penyebab per lokasi pemulihan',
        'konten' => <<<'MD'
## Fungsi
Melihat berapa kerugian stok per periode dan dari mana asalnya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Kerugian Stok**. Lokasi bawaan = lokasi BABA yang terlihat; toko mitra hanya bila dipilih. Pemilik Toko Mitra melihat tokonya.

## Yang dihitung
- Opname **disetujui** dalam periode (tanggal persetujuan): per penyebab (nilai & pos COA), per lokasi (akurasi, kurang/lebih/bersih), per barang.
- Kerugian **dipulihkan** = tindak lanjut stok rusak + ganti rugi karyawan.
- Penyesuaian Stok manual yang dicatat ke keuangan.

## Hasil
Hanya menampilkan data; Excel per tab. Tanggal salah / > 370 hari → bulan ini.
MD,
    ],

    [
        'slug' => 'permintaan-stok', 'judul' => 'Permintaan Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 40,
        'letak' => 'Stok & Gudang → Permintaan Stok',
        'ringkasan' => 'Toko meminta barang (parfum botol, cairan isi ulang) ke gudang. Setelah disetujui, gudang memprosesnya lewat Transfer Stok (tanpa jual-beli).',
        'izin' => ['stok.request'],
        'route_names' => ['stock-request.*'],
        'terkait' => ['transfer-stok', 'kiriman-cabang', 'produk-toko', 'cara-memindahkan-barang'],
        'kata_kunci' => 'permintaan stok request stok minta barang toko minta ke gudang isi stok toko',
        'konten' => <<<'MD'
## Fungsi
Toko mengajukan daftar barang yang dibutuhkan. Gudang menyetujui jumlahnya, lalu mengirim lewat **Transfer Stok**.

## Supaya menu ini muncul & bisa dipakai
- Membuat: centangan **Buat Permintaan Stok** + **Lihat Stok**. Menyetujui/menolak: **Transfer Stok**.
- Pilih **toko peminta** di pojok kanan atas. Barang yang diminta = barang **milik toko** itu (padanan yang dibuat **Produk Parfum Toko**).

## Alur
Pending → **Disetujui** (jumlah boleh diubah) / **Ditolak** → gudang "Buat Transfer" → Kirim → toko Terima → permintaan **Diterima**.

Tidak ada uang yang bergerak.

## Wajib
- **Kode Item barang di toko dan di gudang harus sama persis** (Produk Parfum Toko otomatis menyamakannya). Barang tanpa padanan di gudang dilewati.
MD,
    ],

    [
        'slug' => 'transfer-stok', 'judul' => 'Transfer Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 50,
        'letak' => 'Stok & Gudang → Transfer Stok',
        'ringkasan' => 'Memindahkan barang antar lokasi sendiri tanpa jual-beli: stok asal berkurang saat Kirim, stok tujuan bertambah saat Terima.',
        'izin' => ['stok.transfer'],
        'route_names' => ['stock-transfer.*'],
        'terkait' => ['permintaan-stok', 'kiriman-cabang', 'stok-barang', 'cara-memindahkan-barang'],
        'kata_kunci' => 'transfer stok pindah barang mutasi stok kirim barang gudang ke toko antar toko isi stok toko',
        'konten' => <<<'MD'
## Fungsi
Memindahkan barang dari satu lokasi BABA ke lokasi lain. **Tidak ada uang yang bergerak**. Cara utama mengisi stok toko BABA dari gudang.

## Supaya menu ini muncul & bisa dipakai
- Membuat & mengirim: **Transfer Stok** + **Lihat Stok**. Menerima di tujuan: **Transfer Stok** atau **Buat Permintaan Stok**, dan boleh mengakses lokasi itu.
- Barang: barang ber-stok milik lokasi asal (bukan produk kasir `JL-`/`RF-`).

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok asal | Stok tujuan |
|---|---|---|
| **Kirim** | Berkurang (FIFO) | — |
| **Terima** / sebagian | — | Bertambah; modal = modal rata-rata yang keluar |
| **Batalkan** (Draft/Dikirim) | Kembali dengan modal yang sama | — |

Tercatat di Kartu Stok kedua lokasi. Tidak masuk laporan penjualan.

## Wajib
- Kode Item asal & tujuan sama persis — kalau tidak ada, dibuat barang baru otomatis (bisa dobel).

## Jangan
- Toko **mitra** (kemitraan / refill member) membeli barang: pakai **Kirim ke Cabang** (ada harga), bukan Transfer.
MD,
    ],

    [
        'slug' => 'produksi', 'judul' => 'Produksi Parfum', 'kelompok' => 'Stok & Gudang', 'urutan' => 60,
        'letak' => 'Stok & Gudang → Produksi',
        'ringkasan' => 'Gudang meracik cairan parfum dari bibit, fixative, dan alkohol (Konsentrasi, Buat Parfum, Resep Produksi), memeram, lalu mengisi ke botol.',
        'izin' => ['produksi.view'],
        'route_names' => ['produksi.*'],
        'terkait' => ['bahan-baku', 'produk-online', 'produk-toko', 'cara-membuat-resep'],
        'kata_kunci' => 'produksi parfum racik racikan bibit fixative alkohol konsentrasi edp edt edc extrait peram diperam saring buat parfum resep produksi isi botol modal per ml susut',
        'konten' => <<<'MD'
## Fungsi
Mencatat pembuatan parfum di gudang: bahan berkurang, hasil (cairan parfum per ml atau parfum botol) bertambah dengan modal dari harga bahannya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Produksi**; **Catat Produksi**; **Batalkan Produksi**; **Kelola Resep Produksi** (Konsentrasi, Buat Parfum, Resep).
- Hanya di **Gudang Pusat** — pilih gudang di pojok kanan atas.

## Tab & isinya
| Tab | Isi |
|---|---|
| **Konsentrasi** | Nama (EDP, EDT, EDC, Extrait…), % bibit, % fixative, sisanya alkohol, **lama peram** (hari) |
| **Buat Parfum** | Sekali isi: membuat barang `Cairan <Aroma> <Konsentrasi>` (ml, kategori Cairan Parfum) + resep racik per 1.000 ml, dan untuk setiap ukuran `<Aroma> <Konsentrasi> <N> ml` (pcs, kategori Parfum Botol) + resep isi botol (N ml cairan + botol + kemasan) |
| **Resep Produksi** | Resep per barang hasil (1 barang = 1 resep) |
| Riwayat | Batch produksi & statusnya |

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Modal |
|---|---|---|
| **Catat Produksi** (resep tanpa peram) | Bahan dipotong FIFO; hasil masuk stok | Biaya bahan ÷ hasil nyata |
| Catat Produksi (resep **dengan peram**) | Bahan dipotong; hasil **belum** masuk stok (status **Diperam**, ada tanggal siap) | — |
| **Selesai Peram & Saring** | Hasil akhir masuk stok (≤ yang diracik); **susut** tercatat | Biaya ÷ hasil akhir |
| **Batalkan** | Bahan kembali (saat diperam: bahan kembali saja); hasil keluar bila masih cukup | — |

Kalau bahan kurang, ditolak dan pesan menyebut bahan yang kurang.

## Wajib
- Isi **hasil nyata** sesuai yang jadi — modal per ml/botol dihitung dari angka ini.

## Menu yang mirip & bedanya
- **Produk Parfum Toko** — menjual botol/isi ulang di POS toko dari barang hasil produksi.
- **Produk Online** — menjual parfum botol ke member.
MD,
    ],

    [
        'slug' => 'kiriman-cabang', 'judul' => 'Kiriman Cabang (Kirim ke Cabang)', 'kelompok' => 'Stok & Gudang', 'urutan' => 70,
        'letak' => 'Stok & Gudang → Kiriman Cabang (dikirim dari POS Gudang Pusat)',
        'ringkasan' => 'Gudang menjual barang ke toko lewat POS gudang. Toko menerima: stok toko bertambah, kas toko berkurang, gudang dapat pendapatan.',
        'izin' => ['kiriman.view'],
        'route_names' => ['kiriman.*'],
        'terkait' => ['pos', 'retur-cabang', 'transfer-stok', 'cabang', 'cara-memindahkan-barang'],
        'kata_kunci' => 'kiriman cabang kirim ke cabang jual ke toko mitra gudang jual terima kiriman tolak kiriman internal',
        'konten' => <<<'MD'
## Fungsi
Gudang **menjual** barang ke toko dengan harga jual gudang. Cocok untuk toko mitra yang membeli barang dari BABA.

## Supaya menu ini muncul & bisa dipakai
- Saklar **"Aktifkan Kirim ke Cabang di POS"** di Cabang & Gudang → Edit (Gudang Pusat). Bawaan mati.
- Gudang Pusat wajib punya **Kas Tunai aktif**. Mengirim: **Buat Order** (POS gudang). Menerima/menolak di toko: **Terima / Tolak Kiriman dari Gudang**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok gudang | Stok toko | Kas | Laporan |
|---|---|---|---|---|
| **Kirim** | Dipotong | — | — | Belum dihitung |
| **Terima** (boleh sebagian) | Sisa kembali | Bertambah; modal = harga kirim | Kas toko **berkurang**; Kas Tunai gudang **bertambah** | Penjualan gudang pada tanggal Terima |
| **Tolak** / Batalkan | Semua kembali | — | — | — |

⚠️ Kiriman ke **toko mitra tidak dieliminasi** sebagai transaksi internal (toko mitra di luar keuangan BABA).

## Jangan
- Kiriman yang sudah diterima tidak bisa dibatalkan — pakai **Retur Cabang**.
MD,
    ],

    [
        'slug' => 'retur-cabang', 'judul' => 'Retur Cabang', 'kelompok' => 'Stok & Gudang', 'urutan' => 80,
        'letak' => 'Stok & Gudang → Retur Cabang (dibuat dari kiriman yang sudah diterima)',
        'ringkasan' => 'Toko mengembalikan barang kiriman gudang (rusak, kelebihan). Uangnya bisa dikembalikan atau tidak.',
        'izin' => ['retur.view'],
        'route_names' => ['retur-cabang.*'],
        'terkait' => ['kiriman-cabang', 'riwayat-order'],
        'kata_kunci' => 'retur cabang kembalikan barang rusak kelebihan kirim refund kiriman toko ke gudang',
        'konten' => <<<'MD'
## Fungsi
Mengembalikan barang dari **kiriman gudang yang sudah diterima** toko.

## Supaya menu ini muncul & bisa dipakai
- Membuat/membatalkan (toko): **Buat / Batalkan Retur ke Gudang**. Menerima/menolak (gudang): **Terima / Tolak Retur dari Cabang**.
- Maksimal sejumlah yang diterima dan belum diretur.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok toko | Stok gudang | Kas |
|---|---|---|---|
| Kirim Retur | Berkurang | — | — |
| **Terima**, uang kembali Ya | — | Bertambah (modal asli) | Kas gudang → **kas toko yang dulu membayar** |
| **Terima**, uang kembali Tidak | — | Bertambah (modal Rp0) | — |
| Tolak / Batalkan | Kembali | — | — |

## Menu yang mirip & bedanya
- **Retur Online** (Toko Online) — retur dari member, bukan dari toko.
MD,
    ],

    [
        'slug' => 'bahan-baku', 'judul' => 'Bahan Baku & Kemasan', 'kelompok' => 'Stok & Gudang', 'urutan' => 90,
        'letak' => 'Stok & Gudang → Bahan Baku & Kemasan',
        'ringkasan' => 'Data semua barang ber-stok: bibit, alkohol, fixative, cairan parfum (ml), parfum botol (pcs), botol & kemasan, perlengkapan. Termasuk Harga Standar dan unit beli.',
        'izin' => ['master.bahan_baku.view'],
        'route_names' => ['master.bahan-baku.*'],
        'terkait' => ['produksi', 'produk-toko', 'stok-barang', 'purchase-order', 'pemakaian-perlengkapan', 'cara-mengatur-harga'],
        'kata_kunci' => 'bahan baku kemasan botol bibit alkohol fixative cairan parfum parfum botol perlengkapan master barang harga standar unit beli kode item satuan ml kategori',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan barang yang dibeli, diproduksi, dan disimpan stoknya. Dipakai di PO, Produksi Parfum, Produk Parfum Toko, Produk Online, Transfer, dan Stok Opname.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Bahan Baku & Kemasan**.
- Barang **milik lokasi** di pojok kanan atas (bawaan Gudang Pusat). **Kode Item unik per lokasi** — kode sama di lokasi lain = barang yang sama saat Transfer/Kiriman.
- Barang hasil **Buat Parfum** (cairan & parfum botol) dibuat otomatis di sini.

## Isian penting & akibatnya
| Isian | Akibat |
|---|---|
| **Satuan** | Satuan stok & harga. Cairan parfum = **ml** (boleh desimal), botol = pcs |
| **Barcode** (parfum botol) | Diisi lewat Produk Parfum Toko; dipakai scan POS & stok opname |
| Jenis **Perlengkapan** | Bisa dicatat di Pemakaian Perlengkapan |
| Lacak Stok | Tidak dicentang: tidak bisa dipakai di Pemakaian Perlengkapan / Stok Opname |
| **Unit Beli** + isi | Di PO & Penyesuaian bisa isi per pack/dus |
| **Harga Standar** | Dipakai saat stok habis & belum pernah PO, dan saat stok naik lewat Penyesuaian. PO tidak menimpanya |

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Simpan | Barang siap dipakai; stok 0 sampai ada PO/Penyesuaian/Transfer/Produksi |
| **Hapus** | Hanya barang yang **belum dipakai** — ditolak kalau masih ada stok, dipakai di Resep Produksi/komposisi, atau ada di PO yang belum selesai. Riwayat & foto tetap |

## Wajib
- Satuan harga = satuan stok (cairan: harga per ml).

## Jangan
- Barang yang tidak dipakai lagi cukup **dinonaktifkan**.
MD,
    ],

    [
        'slug' => 'produk-jual', 'judul' => 'Produk Jual', 'kelompok' => 'Stok & Gudang', 'urutan' => 100,
        'letak' => 'Stok & Gudang → Produk Jual',
        'ringkasan' => 'Produk yang dijual di POS: harga jual, foto, kategori, Jenis Produk, dan komposisi barang yang dipotong saat terjual. Parfum toko dibuat otomatis lewat Produk Parfum Toko.',
        'izin' => ['master.produk_jual.view'],
        'route_names' => ['master.produk-jual.*'],
        'terkait' => ['produk-toko', 'pos', 'bahan-baku', 'jenis-produk', 'cara-produk-tidak-muncul-di-pos', 'cara-mengatur-harga'],
        'kata_kunci' => 'produk jual menu kasir harga jual komposisi varian foto produk kategori jenis produk hpp modal aksesori',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan produk yang dijual di POS toko. Produk jual **tidak punya stok sendiri** — saat terjual, barang di **komposisinya** yang dipotong.

⚠️ Parfum botol (`JL-…`) dan isi ulang per ml (`RF-…`) **dibuat otomatis** oleh **Produk Parfum Toko** — ubah harganya di sana. Menu ini untuk produk lain (mis. aksesori) atau pengaturan lanjutan.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Produk Jual**. Produk **milik toko** di pojok kanan atas.
- Barang di komposisi harus milik **toko yang sama**.

## Isian penting & akibatnya
| Isian | Akibat |
|---|---|
| Harga Jual | Harga di POS (kasir mengubah hanya dengan centangan Ubah Harga) |
| Status Aktif | Tidak aktif = hilang dari POS |
| Kategori | Chip filter POS & Laporan Kategori |
| **Jenis Produk** (opsional) | Untuk Laba Rugi "Per Jenis Produk"; namanya ikut tersimpan di order |
| **Komposisi** | Barang + takaran per 1 produk: stok yang dipotong, tanda "Habis", HPP |
| Tipe Produk Tambahan | Tampil di "Item Tambahan" POS; komposisinya dipotong saat terjual |

## Tombol khusus
| Tombol | Akibat |
|---|---|
| Ambil dari Cabang Lain / Buat juga di cabang | Menyalin produk ke toko lain (barang dicocokkan per Kode + Nama) |
| Hapus | Masuk Data Terhapus (foto tetap). Riwayat order utuh |

## Wajib
- Setiap produk jual **harus punya komposisi**, kalau tidak selalu "Habis".
MD,
    ],

    [
        'slug' => 'produk-toko', 'judul' => 'Produk Parfum Toko', 'kelompok' => 'Stok & Gudang', 'urutan' => 105,
        'letak' => 'Stok & Gudang → Produk Parfum Toko',
        'ringkasan' => 'Memilih parfum gudang yang dijual di satu toko (botol & isi ulang per ml), harga jualnya, dan barcode. Membuat produk kasir dan padanan stok toko otomatis.',
        'izin' => ['master.produk_jual.create'],
        'route_names' => ['produk-toko.*'],
        'terkait' => ['pos', 'produksi', 'transfer-stok', 'permintaan-stok', 'produk-jual', 'cara-produk-tidak-muncul-di-pos'],
        'kata_kunci' => 'produk parfum toko jual parfum di toko harga toko isi ulang refill per ml botol barcode scan produk kasir JL RF',
        'konten' => <<<'MD'
## Fungsi
Cara cepat membuat parfum bisa dijual di POS sebuah toko.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Tambah Produk Jual** (Owner/Admin Pusat).
- Pilih **toko** (Cabang bertipe cabang dengan Jenis toko). Daftar = **parfum botol** (kategori Parfum Botol) & **cairan parfum** (kategori Cairan Parfum) milik gudang.

## Apa yang terjadi kalau tombol diklik
| Aksi | Akibat |
|---|---|
| Centang **Jual** + harga (botol) | Dibuat padanan barang di toko (Kode Item sama) + produk kasir `JL-<kode>` (pcs) dengan komposisi 1 botol |
| Centang Jual + harga per ml (cairan) | Produk kasir `RF-<kode>` "Isi Ulang … (per ml)" dengan komposisi 1 ml |
| Isi **Barcode** (baris botol) | Melekat di parfum botol gudang dan disalin ke produk kasir **semua toko**; huruf/angka/titik/strip 3–64, tidak boleh dipakai barang lain |
| Hapus centang | Produk kasir dinonaktifkan (riwayat tetap) |

⚠️ Halaman ini **tidak mengisi stok**. Stok toko diisi dari gudang lewat **Permintaan / Transfer Stok** (atau Kirim ke Cabang untuk toko mitra).

## Menu yang mirip & bedanya
- **Produk Online** — harga untuk member di aplikasi member, bukan harga POS toko.
MD,
    ],

    [
        'slug' => 'semua-barang', 'judul' => 'Semua Barang (Overview)', 'kelompok' => 'Stok & Gudang', 'urutan' => 110,
        'letak' => 'Stok & Gudang → Semua Barang (Overview)',
        'ringkasan' => 'Daftar semua barang (bahan, cairan, botol, produk kasir) dalam satu tabel. Hanya untuk melihat — tambah/ubah lewat Bahan Baku atau Produk Jual.',
        'izin' => ['item.view'],
        'route_names' => ['item.*'],
        'terkait' => ['bahan-baku', 'produk-jual'],
        'kata_kunci' => 'semua barang master barang lengkap overview daftar barang item',
        'konten' => <<<'MD'
## Fungsi
Melihat semua barang sekaligus.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Master Barang**. Data mengikuti lokasi di pojok kanan atas.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| + Bahan/Kemasan / + Produk Jual | Membuka form masing-masing |
| Edit | Diarahkan ke form sesuai tipenya |

Tidak ada tombol hapus di sini.
MD,
    ],

    [
        'slug' => 'pemakaian-perlengkapan', 'judul' => 'Pemakaian Perlengkapan', 'kelompok' => 'Stok & Gudang', 'urutan' => 120,
        'letak' => 'Stok & Gudang → Pemakaian Perlengkapan',
        'ringkasan' => 'Mencatat pemakaian barang habis pakai (tisu, sabun, plastik, ATK) supaya stoknya berkurang dan nilainya tercatat.',
        'izin' => ['pemakaian_perlengkapan.view'],
        'route_names' => ['pemakaian-perlengkapan.*'],
        'terkait' => ['bahan-baku', 'penyesuaian-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'pemakaian perlengkapan barang habis pakai sabun tisu plastik atk pakai barang',
        'konten' => <<<'MD'
## Fungsi
Mencatat barang yang dipakai untuk operasional (bukan dijual).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Catat Pemakaian Perlengkapan**. Barang: **Jenis = Perlengkapan** dan **Lacak Stok** dicentang, milik lokasi aktif.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Laporan |
|---|---|---|
| **Simpan** | Berkurang (FIFO); nilai tercatat | Laporan Pemakaian Perlengkapan |

Tidak ada tombol batal — salah catat dikoreksi lewat **Penyesuaian Stok** (Salah Hitung).

## Menu yang mirip & bedanya
- **Stok Opname / Penyesuaian Stok** — selisih hitung, bukan pemakaian rutin.
MD,
    ],
];
