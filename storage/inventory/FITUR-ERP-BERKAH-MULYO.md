# ERP Berkah Mulyo — Daftar Fitur Lengkap

> Aplikasi ERP terintegrasi untuk usaha F&B multi-cabang (UMKM sampai enterprise skala kecil-menengah).

## 🎯 Highlight Utama

- Kelola **kasir, stok bahan, pembelian, keuangan, karyawan, aset, dan laporan** dari 1 aplikasi — tidak perlu buku catatan terpisah atau banyak aplikasi berbeda.
- **Multi-cabang** dari awal: setiap cabang punya data stok dan kas sendiri-sendiri, tapi Owner tetap bisa lihat semua cabang sekaligus dari 1 layar.
- **Hak akses per jabatan** — kasir hanya bisa buka menu kasir, admin gudang hanya urus stok, sampai ke pengaturan detail siapa boleh apa.
- Bisa dibuka dari **laptop, tablet, maupun HP** — tampilan otomatis menyesuaikan ukuran layar.
- Sudah dilengkapi **absen wajah otomatis**, **laporan keuangan tingkat akuntan**, **hitung untung-rugi**, dan **jejak audit** setiap perubahan data — bukan sekadar catat transaksi.
- Bisa dipasang di **hosting biasa (cPanel)**, tidak perlu server mahal.

---

## 📊 Modul yang Tersedia

### 1. Kasir / Penjualan (POS)
**Untuk siapa:** Kasir dan Operator di cabang

**Manfaat:**
- Transaksi tercatat rapi dan otomatis masuk ke laporan keuangan — tidak ada uang yang "nyelip" tanpa catatan.
- Struk langsung tercetak, kasir tidak perlu hitung manual atau tulis nota tangan.
- Kasir bisa tetap kerja meski internet sempat putus sebentar (mode aplikasi offline sederhana).

**Fitur:**
- Layar kasir cepat untuk 2 jenis transaksi: **jasa giling** (pelanggan bawa daging sendiri, dihitung per kg) dan **penjualan produk jadi** (bakso/sosis/tempura buatan sendiri).
- Cetak struk otomatis ke printer thermal Bluetooth (tablet/HP), termasuk laci kasir yang otomatis terbuka saat bayar tunai.
- Struk bisa dicetak 2 salinan sekaligus, dan Owner bisa atur agar harga per barang disembunyikan di struk (hanya tampil total).
- Input berat bisa dalam kg, ons, atau gram — sistem otomatis mengonversi.
- Bantuan "resep bumbu" — kasir tinggal pilih resep, sistem otomatis isi bahan dan takaran, tidak perlu ketik manual satu-satu.
- Pembayaran tunai, transfer, atau QRIS.
- Pembatalan order dengan alasan wajib dicatat (jejak audit), dan bisa dibuatkan "order pengganti" kalau pelanggan ganti pesanan.
- Riwayat semua transaksi bisa dicari (nama pelanggan, nomor order, catatan) dan difilter per tanggal/cabang.
- Cetak ulang struk kapan saja dari riwayat.

---

### 2. Antrian Produksi
**Untuk siapa:** Operator produksi, kasir, dan pelanggan (lewat layar TV)

**Manfaat:**
- Pelanggan tahu progres pesanannya tanpa harus tanya-tanya ke kasir.
- Operator produksi tahu urutan kerja yang jelas, tidak ada pesanan yang terlewat.

**Fitur:**
- Nomor antrian otomatis untuk order jasa giling/produksi.
- Layar TV besar untuk menampilkan status antrian ke pelanggan (siapa sedang dikerjakan, siapa sudah selesai).
- Mode kerja khusus untuk operator (tablet) — tandai mulai kerja, selesai, simpan di rak, sampai diambil pelanggan.
- Menu "Cek Antrian" untuk kasir — cari status pesanan pelanggan dengan cepat, tandai "sudah diambil".
- Order tambahan bisa disembunyikan dari antrian (misalnya kalau dikerjakan bareng pesanan utama).

---

### 3. Pembelian Bahan Baku (Purchase Order)
**Untuk siapa:** Admin gudang, Manajer cabang, Owner

**Manfaat:**
- Semua pembelian bahan dari supplier tercatat rapi, bisa dilacak dari mulai order sampai diterima.
- Cabang bisa beli bahan mendesak langsung ke supplier kalau kondisi darurat, dengan tetap butuh persetujuan.
- Owner bisa pantau semua PO yang sedang berjalan (menunggu approval, sedang dikirim, belum dibayar) dari 1 layar tanpa buka satu-satu.

**Fitur:**
- Alur pembelian lengkap: buat PO → disetujui → dikirim supplier → barang diterima.
- Pembelian mendesak langsung oleh cabang (di luar alur normal gudang pusat), ditandai khusus dan perlu persetujuan.
- **Dashboard PO** — ringkasan berapa banyak PO yang masih menunggu approval, perlu dikirim, dalam perjalanan, atau belum diterima, lengkap dengan lampu peringatan (hijau/kuning/merah) berdasarkan berapa lama sudah menunggu.
- Kolom dan filter status pembayaran di daftar PO — langsung kelihatan PO mana yang sudah dibayar dan mana yang belum, tanpa perlu buka detail satu-satu.
- Saat mencatat pembayaran PO di menu Kas Keluar, tinggal pilih PO-nya dan sistem otomatis mengisi kategori, keterangan, dan nominal — nominal dikunci sesuai total PO supaya tidak salah ketik.
- Tombol pencarian transaksi lama yang belum tersambung ke PO tertentu (berguna kalau pembayaran sempat dicatat manual).
- Tombol "Batal Bayar PO" — kalau ternyata pembayaran salah kas/salah PO/PO dibatalkan, Owner/Admin Pusat bisa membatalkan pencatatan pembayaran itu dengan aman (uangnya otomatis kembali ke kas, PO-nya sendiri tidak hilang), lengkap dengan konfirmasi detail sebelum diproses.
- Manajemen daftar supplier/vendor.

---

### 4. Stok & Gudang
**Untuk siapa:** Admin gudang, Manajer cabang, Owner

**Manfaat:**
- Stok bahan baku dan produk jadi tiap cabang terpantau real-time, tidak perlu hitung manual di gudang.
- Sistem otomatis memberi peringatan kalau stok mau habis, jadi tidak sampai kehabisan bahan di tengah produksi.
- Nilai stok dan harga pokok dihitung otomatis pakai metode akuntansi standar (FIFO — bahan yang masuk duluan dipakai duluan), jadi laporan untung-rugi lebih akurat.

**Fitur:**
- Dashboard stok — nilai stok per cabang, daftar barang, peringatan stok kritis/habis.
- Insight otomatis: barang yang sudah lama tidak bergerak, barang paling laris, tren perubahan harga beli, dan barang yang stoknya "mati" (tidak keluar-masuk).
- Kartu stok — riwayat keluar-masuk tiap barang.
- Penyesuaian stok (adjustment) kalau ada barang susut, rusak, hilang, atau cuma koreksi hitungan — sistem otomatis membedakan mana yang perlu dicatat sebagai kerugian keuangan dan mana yang tidak.
- Tombol reset stok ke 0 untuk pembersihan data (khusus yang punya izin, harus konfirmasi ketik ulang nama barang).
- **Permintaan bahan antar cabang** — cabang minta bahan ke gudang pusat, gudang setujui, kirim, cabang terima.
- **Transfer stok** — pengiriman barang dari gudang pusat ke cabang (atau sebaliknya), status jelas: draft, dikirim, diterima.
- Manajemen master data barang (nama, satuan, kategori, harga, stok minimum).
- Master resep bumbu standar untuk mempercepat input di kasir.

---

### 5. Kas & Keuangan
**Untuk siapa:** Kasir, Manajer cabang, Owner

**Manfaat:**
- Uang masuk dan keluar tercatat rapi per kas (kas tunai, kas bank, dll), jadi Owner tahu persis saldo tiap kas tanpa harus hitung manual.
- Bisa punya banyak "kas" sekaligus per cabang (kas tunai, rekening bank, dompet digital) dan mudah pindah dana antar kas.
- Ada alat bantu khusus untuk membenahi data lama yang tercatat kurang rapi (misalnya transaksi lama yang belum ketahuan sumber kasnya).

**Fitur:**
- Catat transaksi kas masuk dan kas keluar manual, dengan kategori yang bisa disesuaikan sendiri.
- Kelola banyak akun kas per cabang (tunai, bank, QRIS, dll), lengkap saldo berjalan.
- **Transfer Antar Kas** — pindahkan dana antar kas dalam 1 cabang (misalnya dari kas tunai ke rekening bank) secara instan.
- **Transfer / Perpindahan Dana antar cabang** — setor uang dari kas cabang ke kas pusat, dengan alur konfirmasi terima/tolak supaya kedua sisi tercatat benar.
- **Transaksi berulang (recurring)** — untuk biaya rutin bulanan (sewa, gaji, dll) supaya tidak perlu input ulang tiap bulan.
- Buka laci kasir otomatis saat pembayaran tunai, atau manual dengan jejak audit.
- Kategori transaksi bisa dikustomisasi sendiri sesuai kebutuhan usaha.
- **Chart of Accounts (Bagan Akun)** — struktur akun akuntansi standar untuk kebutuhan laporan keuangan formal.
- Alat bantu "beres-beres data": menyambungkan transaksi lama ke PO yang sesuai, menetapkan kas sumber untuk transaksi yang belum jelas asalnya, dan menyamakan saldo kas dengan riwayat transaksi (dengan pratinjau sebelum diterapkan, tidak asal ubah).
- Semua transaksi bisa dicari (nomor transaksi, keterangan, nama kas, kategori) dan difilter per tanggal.

---

### 6. Laporan Keuangan & Akuntansi
**Untuk siapa:** Owner, Admin Pusat

**Manfaat:**
- Bukan cuma catat transaksi, tapi sudah bisa menghasilkan laporan keuangan setara standar akuntansi resmi (Neraca, Laba Rugi) — berguna kalau butuh laporan untuk investor, bank, atau pajak.
- Owner bisa lihat untung-rugi bisnis secara detail: per produk, per kategori, per cabang, bahkan per jam ramai/sepi.
- Ada "simulasi" hitung-hitungan — coba-coba skenario naik/turun harga atau biaya untuk lihat dampaknya ke untung, tanpa perlu mengubah data asli.

**Fitur:**
- **Neraca (Balance Sheet)** — potret kekayaan usaha (aset, hutang, modal) di 1 tanggal tertentu, format resmi bisa diprint PDF.
- **Laba Rugi Formal** — untung-rugi dikelompokkan per akun akuntansi standar, format resmi PDF.
- **Buku Besar** — rincian mutasi tiap akun keuangan (untuk kebutuhan audit/pembukuan detail).
- **Laporan Laba Rugi (analisis produk)** — untung/rugi per barang, per kategori, per jenis olahan, per pesanan — supaya kelihatan produk mana yang paling menguntungkan.
- **Laporan Konsumsi Bahan Baku** — berapa banyak bahan terpakai dan berapa untungnya, per periode.
- **BEP Otomatis** — hitung titik impas (balik modal) langsung dari data transaksi asli, tanpa perlu isi form manual.
- **Simulator BEP interaktif** — geser-geser angka (harga jual, biaya, volume) dan langsung lihat titik impasnya berubah.
- **Simulasi Balik Modal** — proyeksi berapa lama modal usaha kembali dalam beberapa skenario penjualan.
- **Laporan Eksekutif Keuangan** — laporan lengkap multi-halaman siap cetak untuk rapat direksi/investor: ringkasan bisnis, neraca, laba-rugi, BEP, arus kas, sampai rekomendasi berbasis data.
- **Laporan Arus Kas** dan **Laporan Setoran Harian** — ringkasan kas masuk/keluar per hari, cocok untuk laporan setoran ke pusat.
- **Laporan Jam Ramai** — jam-jam mana yang paling ramai/sepi transaksi, untuk membantu atur jadwal kerja atau promo.
- **Ranking Kasir** — bandingkan performa penjualan antar kasir.
- Semua laporan bisa diprint/export dan difilter per rentang tanggal serta per cabang atau gabungan semua cabang.

---

### 7. Manajemen Karyawan & Absensi (HR)
**Untuk siapa:** Manajer cabang, Owner, dan seluruh karyawan (untuk absen & lihat data sendiri)

**Manfaat:**
- Absen pakai wajah, bukan kartu atau tanda tangan — susah dimanipulasi, otomatis tercatat jam masuk/keluar/lembur.
- Sistem otomatis hitung siapa yang telat, siapa yang lembur, dan langsung terhubung ke perhitungan gaji — tidak perlu hitung manual.
- Absen cuma bisa dilakukan dari lokasi cabang (dicek lewat GPS), jadi tidak bisa "titip absen" dari rumah.

**Fitur:**
- **Absensi wajah otomatis** (kamera HP/tablet) — deteksi wajah karyawan, verifikasi bahwa itu orang asli (bukan foto), dan cek lokasi GPS sebelum absen tercatat sah.
- 4 jenis absen: Masuk, Keluar, Lembur Masuk, Lembur Keluar.
- Absen manual (input oleh admin) untuk kasus lupa absen atau device bermasalah.
- Rekap absensi bulanan dengan status hadir/alpha/izin/sakit/libur/cuti.
- Pengaturan shift kerja dan hari libur (nasional + khusus per cabang).
- **Penggajian otomatis** dari rekap absensi — gaji pokok, lembur, potongan telat/alpha dihitung otomatis, dengan opsi koreksi manual kalau perlu, lalu bisa dicetak slip gajinya.
- **Pengajuan Cuti/Izin** — karyawan ajukan, atasan setujui/tolak.
- **Penilaian Karyawan 360°** — setiap 3 bulan, karyawan dinilai oleh atasan, rekan kerja, dan diri sendiri (5 aspek: kedisiplinan, kinerja, kerjasama, kebersihan, inisiatif), hasilnya otomatis dihitung jadi skor dan predikat (Sangat Baik s/d Sangat Kurang), lengkap grafik perkembangan dan ranking antar karyawan.
- Data karyawan lengkap per cabang.

---

### 8. Aset & Penyusutan
**Untuk siapa:** Owner, Admin Pusat

**Manfaat:**
- Semua peralatan dan mesin usaha (mesin giling, freezer, kendaraan, dll) tercatat rapi, termasuk nilai yang terus berkurang seiring waktu (penyusutan) — otomatis dihitung, tidak perlu itung manual tiap bulan.
- Kalau alat rusak atau dijual, untung/ruginya otomatis terhitung dan masuk ke laporan keuangan.

**Fitur:**
- Data lengkap tiap aset (kategori, lokasi, harga beli, kondisi, status).
- Penyusutan otomatis bulanan (bisa juga generate manual), langsung tercatat sebagai biaya di laporan keuangan.
- Riwayat perawatan/perbaikan aset beserta biayanya.
- Pemindahan aset antar cabang.
- Penghapusan/penjualan aset dengan hitungan untung-rugi otomatis.
- Laporan aset — nilai buku, jadwal penyusutan, riwayat perawatan.

---

### 9. Analisis Titik Impas (BEP)
**Untuk siapa:** Owner, Manajer cabang

**Manfaat:**
- Tahu persis berapa target penjualan minimal supaya usaha tidak rugi (balik modal), tanpa perlu hitung manual pakai kalkulator/Excel.

**Fitur:**
- Setup biaya tetap, biaya variabel, dan harga jual per produk (manual atau bantuan isi otomatis dari data penjualan aktual).
- Hitung titik impas per produk, per cabang, dan keseluruhan.
- Grafik visual titik impas.
- Versi otomatis yang langsung menghitung dari data transaksi asli, tanpa setup.
- Simulator interaktif untuk coba-coba skenario.

---

### 10. Program Loyalitas Pelanggan
**Untuk siapa:** Owner, Kasir

**Manfaat:**
- Pelanggan setia otomatis terpantau kg gilingannya untuk hadiah loyalitas — tidak perlu kartu stempel manual yang gampang hilang.
- Ada juga cara klaim manual pakai bukti (misalnya pelanggan posting di media sosial) untuk hadiah event tertentu.

**Fitur:**
- Pelacakan otomatis akumulasi kg gilingan per pelanggan, hadiah otomatis terdeteksi saat target tercapai.
- Klaim loyalitas berbasis event (upload bukti, admin approve/reject, tandai hadiah sudah diberikan).
- Riwayat klaim loyalitas per pelanggan bisa dilihat di halaman detail pelanggan.

---

### 11. Manajemen Pelanggan
**Untuk siapa:** Kasir, Manajer cabang

**Manfaat:**
- Data pelanggan tersimpan rapi, riwayat transaksi dan progres loyalitas mereka bisa dilihat kapan saja.

**Fitur:**
- Data pelanggan (nama, telepon, dll).
- Riwayat order dan progres program loyalitas per pelanggan.

---

### 12. Cabang & Gudang Pusat
**Untuk siapa:** Owner, Admin Pusat

**Manfaat:**
- Tambah cabang baru semudah isi form — semua modul (stok, kasir, keuangan) otomatis mengikuti cabang baru itu.
- Owner bisa switch tampilan antar cabang atau lihat semua cabang sekaligus, tanpa perlu login ulang.

**Fitur:**
- Kelola data cabang dan gudang pusat (alamat, telepon, kepala cabang, status aktif/nonaktif).
- Assign karyawan/user ke cabang tertentu (bisa lebih dari 1 cabang).
- Ganti "cabang aktif" lewat menu pindah cabang di navbar.
- Mode "Semua Cabang" khusus Owner/Admin Pusat untuk lihat data gabungan.
- Laporan perbandingan performa antar cabang.

---

### 13. Dashboard
**Untuk siapa:** Semua role (isi berbeda per role)

**Manfaat:**
- Begitu login, langsung lihat ringkasan penting hari ini tanpa perlu buka menu satu-satu.

**Fitur:**
- Dashboard khusus per cabang, per gudang pusat, dan dashboard gabungan pusat untuk Owner.
- Widget: status PO (menunggu approval/dikirim/belum dibayar), snapshot aset & penyusutan, snapshot neraca, "kesehatan finansial" (rasio keuangan, tren untung 6 bulan), jam ramai hari ini, dan status absensi karyawan hari ini.

---

### 14. User & Hak Akses
**Untuk siapa:** Owner

**Manfaat:**
- Owner bebas atur siapa boleh buka menu apa dan lakukan aksi apa — tidak semua orang bisa lihat/ubah data sensitif seperti gaji atau keuangan.
- Kalau ada kebutuhan khusus (misalnya Manajer Cabang tertentu perlu akses laporan tambahan), tinggal centang lewat menu, tidak perlu request ke developer.

**Fitur:**
- 7 jenis peran (role) siap pakai:
  - **Owner** — akses penuh ke semua cabang, semua menu, tanpa batasan.
  - **Admin Pusat** — akses hampir setara Owner untuk operasional harian semua cabang.
  - **Admin Gudang Pusat** — kelola stok gudang pusat, terima PO dari vendor, proses permintaan cabang.
  - **Manajer Cabang** — kelola operasional 1 cabang (approve pembelian mendesak, kelola karyawan, lihat laporan cabangnya).
  - **Kasir** — fokus transaksi penjualan & pelanggan di cabang sendiri.
  - **Operator Produksi** — fokus input produksi & antrian.
  - **Helper** — akses terbatas untuk tugas bantu-bantu.
- Ratusan hak akses (permission) granular per fitur — bisa diatur detail lewat menu "Role & Hak Akses" tanpa perlu ubah kode program.
- Beberapa fitur sensitif (misalnya hapus data, laporan akuntansi lanjutan) sengaja tidak diberikan otomatis ke siapa pun kecuali Owner — harus dinyalakan manual kalau memang dibutuhkan.
- Tiap user bisa diatur aktif/nonaktif dan reset password oleh admin.

---

### 15. Notifikasi
**Untuk siapa:** Semua user (isi beda per role & cabang)

**Manfaat:**
- Kejadian penting (stok mau habis, PO menunggu persetujuan, cuti diajukan, dll) langsung memberi tahu orang yang tepat — tidak perlu cek satu-satu setiap menu.

**Fitur:**
- Ikon lonceng notifikasi di navbar dengan tanda jumlah belum dibaca.
- Notifikasi otomatis dikirim ke orang yang relevan sesuai jabatan dan cabang.
- Daftar lengkap notifikasi dengan filter dan tandai sudah dibaca.

---

### 16. Bantuan & Panduan Pemakaian
**Untuk siapa:** Semua user

**Manfaat:**
- User baru tidak bingung — ada panduan cara pakai langsung di dalam aplikasi, tidak perlu training terpisah atau baca manual PDF tebal.

**Fitur:**
- Tombol bantuan "?" di banyak halaman, membuka panduan singkat khusus halaman itu.
- Pusat panduan lengkap yang bisa dicari, dikelola oleh Owner/Admin Pusat (bisa ditambah/diedit kapan saja tanpa perlu update aplikasi).
- Tooltip penjelasan di kolom-kolom form yang mungkin membingungkan.

---

## 🔐 Fitur Keamanan

- **Jejak Audit (Activity Log)** — setiap perubahan data penting (siapa mengubah, kapan, data sebelum vs sesudah) otomatis tercatat dan bisa ditelusuri kembali.
- **Data Terhapus (bukan hilang beneran)** — data yang dihapus tidak langsung lenyap, tersimpan di menu "Data Terhapus" dan bisa dikembalikan kalau ternyata salah hapus. Hapus permanen butuh konfirmasi ekstra (ketik ulang kata konfirmasi).
- **Hapus otomatis terkait** — kalau data induk dihapus (misalnya 1 cabang ditutup), data anak yang terkait (karyawan, stok, transaksi cabang itu) ikut rapi ter-nonaktifkan bersamaan, dan bisa dikembalikan bersamaan juga kalau dibatalkan.
- **Backup database otomatis** — dijadwalkan tiap hari, bisa juga backup manual dan download kapan saja.
- **Hak akses berlapis** — dicek baik di menu yang tampil maupun di tombol aksinya, jadi walau seseorang tahu alamat halaman tertentu, tetap ditolak kalau tidak punya izin.
- **Kunci nominal saat bayar PO** — mencegah kasir salah ketik jumlah pembayaran yang tidak sesuai tagihan PO, dengan pengecekan ganda (tampilan dan sistem).

---

## 💾 Teknologi & Infrastruktur

- Dibangun di atas **Laravel 12** (framework aplikasi web yang matang dan banyak dipakai industri) + **MySQL** (database yang andal).
- **Bisa dipasang di hosting cPanel biasa** — tidak butuh server khusus/VPS mahal, cocok untuk usaha kecil-menengah yang ingin hemat biaya infrastruktur.
- **Multi-perangkat** — bisa dibuka lancar dari laptop, tablet, maupun HP, tampilan otomatis menyesuaikan ukuran layar.
- Mendukung **mode aplikasi terpasang (PWA)** di HP/tablet — bisa ditambahkan ke layar utama seperti aplikasi biasa, dan tetap bisa dipakai sesaat walau koneksi internet putus-putus.
- Terhubung ke printer struk thermal via **Bluetooth** langsung dari tablet/HP, tanpa kabel.
- Absen wajah berjalan langsung di HP/tablet (tidak butuh server kamera khusus).
- Peta lokasi cabang pakai peta gratis (OpenStreetMap) untuk setting titik GPS cabang.

---

## 🎁 Fitur Bonus / Terbaru

- **Lock Nominal saat Bayar PO** — begitu kasir memilih PO yang mau dibayar, kolom nominal otomatis terkunci sesuai total tagihan PO, mencegah salah ketik jumlah.
- **Auto-Fill Data Kas saat Pilih PO** — pilih PO di form Kas Keluar, sistem langsung isi otomatis jenis transaksi, kategori, dan keterangan — kasir tinggal pilih kas dan simpan.
- **Batal Bayar PO 1 Klik** — kalau ternyata salah catat pembayaran PO, Owner/Admin Pusat bisa membatalkannya dengan aman (uang otomatis kembali ke kas, PO tetap utuh), lengkap pop-up konfirmasi rincian sebelum diproses.
- **Kolom & Filter Status Pembayaran di Daftar PO** — langsung kelihatan PO mana yang sudah/belum dibayar, bisa difilter tanpa buka detail satu-satu.
- **Pencarian Cerdas di Berbagai Daftar** — hampir semua daftar transaksi/data (order, kas, PO, karyawan, aset, dll) sudah bisa dicari langsung dari kotak pencarian, tidak perlu scroll manual.
- **Laporan Keuangan Setara Akuntan Profesional** — Neraca, Laba Rugi Formal, Buku Besar, Chart of Accounts, sampai Laporan Eksekutif siap cetak untuk rapat direksi/investor.

---

## 📞 Info Tambahan

- **Cocok untuk:** Cafe, Restoran, UMKM Kuliner, Jasa Penggilingan Daging/Bakso, Bisnis Multi-cabang F&B, Pabrik makanan skala kecil-menengah.
- **Cabang:** Mendukung banyak cabang sekaligus, masing-masing dengan data stok dan kas yang terpisah rapi tapi tetap bisa dipantau gabungan dari pusat.
- **Skalabilitas:** Struktur aplikasi sudah terbukti menampung puluhan modul berbeda (kasir, stok, keuangan, HR, akuntansi, aset) dalam 1 sistem terintegrasi — siap dikembangkan lebih lanjut sesuai kebutuhan usaha yang terus tumbuh.
