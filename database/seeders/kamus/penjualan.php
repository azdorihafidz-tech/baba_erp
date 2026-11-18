<?php

/**
 * Kamus Menu ERP BABA — kelompok Penjualan (porting Dimsum 4.50, ditulis ulang dari kode BABA).
 * Setiap fakta di sini diambil dari kode (controller/service/view), bukan ingatan.
 * Kalau perilaku menu berubah, UBAH JUGA teks di sini (aturan baba_erp/CLAUDE.md).
 */
return [

    [
        'slug' => 'pos', 'judul' => 'POS / Kasir', 'kelompok' => 'Penjualan', 'urutan' => 10,
        'letak' => 'Penjualan → POS / Kasir',
        'ringkasan' => 'Mencatat penjualan parfum di toko: scan barcode atau ketuk produk, terima pembayaran, cetak struk. Stok toko langsung terpotong dan kas toko bertambah.',
        'izin' => ['order.create'],
        'route_names' => ['penjualan.pos'],
        'terkait' => ['riwayat-order', 'produk-toko', 'cabang', 'kas-transaksi', 'setoran-kasir', 'cara-produk-tidak-muncul-di-pos'],
        'kata_kunci' => 'kasir jualan jual transaksi bayar struk order checkout tunai qris transfer split bill simpan bill uang pas kembalian diskon laci printer barcode scan scanner isi ulang refill botol per ml apk tablet agen printer',
        'konten' => <<<'MD'
## Fungsi
Tempat kasir toko BABA mencatat penjualan parfum (botol atau isi ulang per ml). Begitu **PROSES ORDER** diklik dan dikonfirmasi, stok toko terpotong dan uang masuk ke kas toko.

## Supaya menu ini muncul & bisa dipakai
- Pilih **toko** di pojok kanan atas (judul POS menampilkan "Toko: <nama>"). Kalau sedang "Semua Cabang", POS meminta memilih toko dulu.
- Toko wajib punya **kas aktif "Default untuk" Tunai, Transfer, dan QRIS**. Toko yang dibuat lewat **Cabang & Gudang** (jenis toko diisi) otomatis dibuatkan Kas Tunai/Transfer/QRIS. Kalau kurang, saat bayar muncul *"Cabang belum punya Kas default untuk pembayaran …"*.
- **Produk tampil** kalau parfumnya dicentang **Jual** di **Produk Parfum Toko** untuk toko ini: botol (`JL-…`, per pcs) dan isi ulang (`RF-…`, per ml).
- **Produk bertanda "Habis"** kalau stok toko kurang. Stok toko diisi dari gudang lewat **Permintaan / Transfer Stok** (atau Kirim ke Cabang) — bukan dari POS.
- **Barcode**: diisi di Produk Parfum Toko (baris botol). Scan di kotak *Cari produk / scan barcode* atau scan di mana saja (scanner mengetik cepat + Enter) → produk masuk keranjang. Barcode tak dikenal → peringatan.

### Pengaturan toko yang mengubah POS
Diatur di **Pengaturan → Cabang & Gudang → Edit**:

| Pengaturan | Akibat di POS |
|---|---|
| Tipe transaksi aktif | Toko BABA bawaan **Takeaway saja** (pilihan tipe lain adalah sisa fitur lama) |
| Wajib bukti Transfer / QRIS | Foto bukti wajib diunggah sebelum bayar |
| Service Charge % / Take Away Fee | Ditambahkan ke total (bawaan 0) |
| Footer Struk | Teks di bagian bawah struk |
| Sistem Antrian Produksi | Bawaan **mati** — tombol Cek Antrian & pilihan antrian hanya muncul kalau dinyalakan |
| Aktifkan Kirim ke Cabang (hanya Gudang Pusat) | Muncul kotak "Jenis Pembeli: Kirim ke Cabang" |

### Mencetak struk
- **Komputer kasir**: pasang **BABA Printer Agent** (unduh dari jendela struk, wajib login) → **🖥️ Pakai Agen Printer PC**, cetak otomatis & **Buka Laci**.
- **Tablet/HP kasir**: pakai aplikasi **BABA Kasir** (APK, `…/unduh/baba-kasir.apk`) → printer Bluetooth lewat aplikasi.
- Tanpa keduanya: jendela cetak browser.

### Centangan izin tambahan (Role & Hak Akses)
| Centangan | Tanpa centangan ini |
|---|---|
| Ubah Harga Jual di POS (kolom Harga/Unit) | Harga terkunci (ikon gembok) = harga produk di toko ini |
| Batalkan Bill Tersimpan (POS) | Tombol Batalkan di daftar Bill Tersimpan tidak ada |
| Buka Laci Kasir Manual | Tombol Buka Laci tidak ada |

## Alur
Scan barcode / ketuk produk → atur jumlah (isi ulang: jumlah ml) & diskon → pilih pelanggan (boleh kosong) → pilih metode bayar (boleh dibagi) → **PROSES ORDER** → konfirmasi → struk tampil.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas | Laporan |
|---|---|---|---|
| **PROSES ORDER** | Stok toko dipotong (botol −1 pcs, isi ulang −N ml, FIFO). Modal (HPP) tercatat di order | Kas "default" metode bayar bertambah sebesar **nilai order** | Masuk laporan kasir, Laba Rugi, Setoran Kasir (bagian tunai) |
| **Save Bill** | Belum dipotong | Belum | Belum dihitung (Pending) |
| Bayar bill tersimpan | Dipotong saat dibayar | Bertambah | Masuk laporan |
| **Kirim ke Cabang** (POS gudang) | Stok gudang dipotong, menunggu diterima toko | Saat toko klik Terima | Setelah diterima |

Kalau stok kurang, order **ditolak** dan pesan menyebut barangnya.

⚠️ **Penjualan POS tidak menambah RO member.** RO hanya dari belanja online di aplikasi member (lihat Kamus "Catatan RO").

⚠️ **Toko mitra** (Toko Kemitraan / Toko Refill Member): penjualannya tercatat di kas toko itu dan **tidak** masuk keuangan gabungan BABA.

## Jangan
- **Jangan Closing Harian sebelum toko tutup** — tanggal itu terkunci untuk transaksi baru.
- Jangan memakai Save Bill sebagai catatan utang lama — bill Pending tidak masuk laporan dan stoknya belum terpotong.
- Jangan memberi potongan dengan mengubah kolom Harga — pakai kolom **Diskon**.

## Menu yang mirip & bedanya
- **Riwayat Order** — melihat, membatalkan, mencetak ulang order.
- **Toko Saya › Kasir** di aplikasi member — kasir untuk **member** (stok member sendiri), bukan toko BABA.
MD,
    ],

    [
        'slug' => 'riwayat-order', 'judul' => 'Riwayat Order', 'kelompok' => 'Penjualan', 'urutan' => 20,
        'letak' => 'Penjualan → Riwayat Order',
        'ringkasan' => 'Daftar order toko yang dipilih di header: lihat detail, cetak ulang struk, batalkan, kembalikan, edit, atau hapus order.',
        'izin' => ['order.view'],
        'route_names' => ['penjualan.index', 'penjualan.show', 'penjualan.edit'],
        'terkait' => ['pos', 'kiriman-cabang', 'retur-cabang', 'closing', 'cara-membatalkan-transaksi'],
        'kata_kunci' => 'riwayat order histori transaksi batal batalkan cancel hapus order kembalikan edit order cetak ulang struk order pengganti salah input refund',
        'konten' => <<<'MD'
## Fungsi
Melihat order yang sudah dibuat, lalu memperbaikinya kalau ada yang salah.

## Supaya menu ini muncul & bisa dipakai
- Data mengikuti **cabang/toko di pojok kanan atas**. Owner/Admin Pusat yang memilih "Semua Cabang" melihat semua order dengan kolom **Cabang**; role toko selalu melihat tokonya sendiri.
- Setiap tombol punya centangan sendiri: **Batalkan Order**, **Kembalikan Order Dibatalkan**, **Edit Order**, **Hapus Order**.

## Status order
**Pending** (bill tersimpan) → **Selesai** (dibayar) → bisa jadi **Dibatalkan**. Kiriman ke cabang: Dikirim → Diterima / Ditolak.

## Apa yang terjadi kalau tombol diklik
| Tombol | Syarat | Stok | Kas | Laporan |
|---|---|---|---|---|
| **Batalkan** | Alasan wajib. Selain Owner, hanya order **hari ini** | Barang **kembali** ke stok toko | Uang order **dikeluarkan** dari kas | Keluar dari semua laporan |
| **Kembalikan** | Order Dibatalkan | Dipotong lagi (dicek cukup) | Tercatat lagi | Masuk lagi |
| **Tandai Pengganti** | Order lama Dibatalkan, toko sama | — | — | Menautkan order baru sebagai pengganti |
| **Edit** | Order Selesai/Dibatalkan: hanya nama/telepon & catatan — **diskon & metode bayar dikunci**. Pending: diskon & metode bayar bisa diubah | — | Tidak berubah | — |
| **Hapus** | Hanya order **Pending/Dibatalkan** | — | — | Masuk Data Terhapus |

## Jangan
- Diskon/metode bayar salah pada order yang sudah dibayar: **Batalkan**, buat order baru, lalu **Tandai Pengganti**. Uangnya sudah tercatat di kas & Setoran Kasir.
- Tanggal yang sudah **Closing** terkunci sampai dibuka Owner.

## Menu yang mirip & bedanya
- **POS** — membuat order baru. **Pesanan Online** (Toko Online) — pesanan member dari aplikasi member, bukan order POS.
MD,
    ],

    [
        'slug' => 'laporan-transaksi-kasir', 'judul' => 'Laporan Transaksi', 'kelompok' => 'Penjualan', 'urutan' => 30,
        'letak' => 'Penjualan → Laporan Transaksi',
        'ringkasan' => 'Pendapatan dan jumlah transaksi POS per tahun → bulan → hari → per transaksi, bisa dibuka kasir.',
        'izin' => ['penjualan.laporan_transaksi.view'],
        'route_names' => ['penjualan.laporan.transaksi'],
        'terkait' => ['laporan-produk-kasir', 'laporan-kategori-kasir', 'laporan-penjualan', 'riwayat-order'],
        'kata_kunci' => 'laporan transaksi omzet pendapatan harian bulanan tahunan grafik penjualan hari ini untung keuntungan kasir toko',
        'konten' => <<<'MD'
## Fungsi
Melihat pendapatan dan jumlah transaksi POS bertingkat: **Selama ini → Tahun → Bulan → Hari → transaksi → struk**, atau rentang tanggal sendiri.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Laporan Transaksi (menu Penjualan)**. Kolom **Keuntungan** butuh centangan **Lihat Keuntungan di Laporan Penjualan** (tanpa itu angkanya tidak dikirim ke layar).
- Data mengikuti toko di pojok kanan atas (role toko selalu tokonya sendiri). Pemilik Toko Mitra memakai laporan ini untuk tokonya.

## Yang dihitung
- **Pendapatan** = total bayar order (setelah diskon, termasuk biaya tambahan). **Keuntungan** = pendapatan − modal (HPP).
- Tidak dihitung: bill Pending, order Dibatalkan/dihapus, kiriman ke cabang yang belum diterima, dan **pesanan online member** (ada di Toko Online › Laporan Belanja Online).

## Hasil
Hanya menampilkan data. Bisa diekspor ke Excel/PDF.
MD,
    ],

    [
        'slug' => 'laporan-produk-kasir', 'judul' => 'Laporan Produk', 'kelompok' => 'Penjualan', 'urutan' => 40,
        'letak' => 'Penjualan → Laporan Produk',
        'ringkasan' => 'Jumlah terjual, penjualan, diskon, dan pendapatan per produk POS (botol & isi ulang) dalam periode tertentu.',
        'izin' => ['penjualan.laporan_produk.view'],
        'route_names' => ['penjualan.laporan.produk'],
        'terkait' => ['laporan-transaksi-kasir', 'laporan-kategori-kasir', 'produk-toko'],
        'kata_kunci' => 'laporan produk terlaris best seller parfum laku penjualan per produk qty terjual ml isi ulang',
        'konten' => <<<'MD'
## Fungsi
Melihat parfum mana yang paling banyak terjual di POS dan menghasilkan berapa (Hari ini / Bulan ini / Tahun ini / Selama ini / rentang tanggal).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Laporan Penjualan Produk (menu Penjualan)**. Keuntungan butuh **Lihat Keuntungan di Laporan Penjualan**.

## Cara menghitung
- **Penjualan** = harga × jumlah; **Diskon** order dibagi ke setiap produk sesuai porsi harga; **Pendapatan** = penjualan − diskon.
- Isi ulang dihitung per **ml**, botol per **pcs**.
- Produk dikelompokkan per **Kode Item** — parfum yang sama di beberapa toko (kode `JL-…`/`RF-…` sama) dijumlah jadi satu.

## Hasil
Hanya menampilkan data. Bisa diekspor ke Excel/PDF.
MD,
    ],

    [
        'slug' => 'laporan-kategori-kasir', 'judul' => 'Laporan Kategori', 'kelompok' => 'Penjualan', 'urutan' => 50,
        'letak' => 'Penjualan → Laporan Kategori',
        'ringkasan' => 'Penjualan, diskon, dan pendapatan POS dijumlah per kategori produk, bisa dibuka rincian produknya.',
        'izin' => ['penjualan.laporan_kategori.view'],
        'route_names' => ['penjualan.laporan.kategori'],
        'terkait' => ['laporan-produk-kasir', 'laporan-transaksi-kasir'],
        'kata_kunci' => 'laporan kategori penjualan per kategori parfum botol isi ulang refill',
        'konten' => <<<'MD'
## Fungsi
Melihat kategori produk mana (mis. Parfum Botol, Isi Ulang) yang paling besar pendapatannya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Laporan Penjualan Kategori (menu Penjualan)**. Keuntungan butuh **Lihat Keuntungan di Laporan Penjualan**.

## Cara menghitung
Sama dengan Laporan Produk, lalu dijumlah per kategori — angkanya selalu cocok. Produk tanpa kategori masuk "Tanpa kategori".

## Hasil
Hanya menampilkan data. Bisa diekspor ke Excel/PDF.
MD,
    ],

    [
        'slug' => 'pelanggan', 'judul' => 'Pelanggan', 'kelompok' => 'Penjualan', 'urutan' => 60,
        'letak' => 'Penjualan → Pelanggan',
        'ringkasan' => 'Data pembeli tetap toko (nama, telepon, alamat) yang bisa dipilih di POS, dipakai untuk riwayat belanja dan Program Loyalty.',
        'izin' => ['pelanggan.view'],
        'route_names' => ['pelanggan.*'],
        'terkait' => ['pos', 'program-loyalty'],
        'kata_kunci' => 'pelanggan customer pembeli langganan data pembeli telepon riwayat belanja total pembelian nonaktif',
        'konten' => <<<'MD'
## Fungsi
Menyimpan data pembeli tetap **toko** supaya bisa dipilih di POS. Riwayat belanja dan progres Program Loyalty dihitung dari order yang memakai pelanggan ini.

⚠️ Pelanggan di sini **bukan** member BABA. Member dan strukturnya ada di aplikasi member; belanja member tercatat di Toko Online.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Pelanggan**; Tambah/Edit perlu **Tambah/Edit Pelanggan**.
- Data pelanggan **sama untuk semua toko**. Hanya pelanggan **Aktif** yang muncul di pilihan POS.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Tambah / Edit | Hanya data pelanggan. Order lama tidak berubah |
| Saklar **Aktif** dimatikan (Edit) | Tidak muncul di pilihan POS. Riwayat belanja, laporan, progres loyalty tetap |

Tidak ada tombol Hapus — pakai saklar Aktif. Kalaupun data dihapus, order-nya tetap tercatat dan tetap dihitung di laporan.

## Jangan
- Jangan membuat data dobel untuk orang yang sama — progres loyalty terpecah.
MD,
    ],

    [
        'slug' => 'jenis-produk', 'judul' => 'Jenis Produk', 'kelompok' => 'Penjualan', 'urutan' => 70,
        'letak' => 'Penjualan → Jenis Produk',
        'ringkasan' => 'Kelompok produk jual (mis. Parfum Botol, Refill, Aksesori) untuk Laporan Laba Rugi "Per Jenis Produk".',
        'izin' => ['jenis-olahan.view'],
        'route_names' => ['master.jenis-olahan.*'],
        'terkait' => ['produk-jual', 'laporan-laba-rugi'],
        'kata_kunci' => 'jenis produk kelompok produk parfum botol refill isi ulang aksesori laba rugi per jenis',
        'konten' => <<<'MD'
## Fungsi
Nama-nama kelompok produk. Dipilih di form **Produk Jual** (kolom **Jenis Produk**, opsional), lalu dipakai **Laporan → Laba Rugi → Per Jenis Produk** untuk melihat untung per kelompok.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Master Jenis Produk**; tambah/ubah perlu **Kelola Master Jenis Produk**.
- Supaya laporan terisi, setiap Produk Jual perlu dipilih Jenis Produk-nya (Produk Jual → Edit).
- Data awal bisa masih berisi nama contoh dari aplikasi asal — ubah namanya atau nonaktifkan.

## Akibat ke data lain
- **Tidak** mengubah stok, kas, harga, atau tampilan POS.
- Order baru menyimpan nama Jenis Produk saat dijual; order lama memakai Jenis Produk produknya yang sekarang. Produk tanpa Jenis Produk masuk "-".

## Menu yang mirip & bedanya
- **Kategori barang** (Parfum Botol / Cairan Parfum, dst.) — dipakai chip filter POS dan Laporan Kategori. Jenis Produk hanya untuk Laba Rugi Per Jenis Produk.
MD,
    ],

    [
        'slug' => 'program-loyalty', 'judul' => 'Program Loyalty', 'kelompok' => 'Penjualan', 'urutan' => 90,
        'letak' => 'Penjualan → Program Loyalty',
        'ringkasan' => 'Program hadiah untuk pelanggan tetap toko: otomatis dari total belanja/jumlah transaksi POS, atau klaim manual yang disetujui.',
        'izin' => ['loyalty.view'],
        'route_names' => ['loyalty-program.*', 'loyalty-klaim.*'],
        'terkait' => ['pelanggan', 'pos', 'jadwal-otomatis'],
        'kata_kunci' => 'loyalty hadiah reward poin voucher klaim pelanggan setia promo',
        'konten' => <<<'MD'
## Fungsi
Memberi hadiah ke pelanggan toko yang belanja terus. Dua jenis program:
- **Auto-Track** — progres dari order POS pelanggan: total belanja (Rp) atau jumlah transaksi. (Pilihan "kg" adalah sisa fitur lama dan tidak dipakai untuk parfum.)
- **Event-based (klaim manual)** — klaim + bukti, lalu disetujui.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Program Loyalty**; buat/ubah perlu **Kelola Program Loyalty**. Klaim punya centangan sendiri (Lihat/Buat/Approve/Reject, Tandai Voucher Diberikan).
- Progres hanya bertambah kalau di POS **pelanggan dipilih** (bukan hanya ketik nama). Program berlaku untuk **semua toko**.
- Pemeriksaan pencapaian otomatis ada di **Jadwal Otomatis → Cek program loyalty** (bawaan mati).

## Alur
- Auto-Track: belanja → progres naik → target tercapai → **Tandai Hadiah Diberikan**.
- Klaim: Buat → **Approve/Reject** → **Tandai voucher diberikan**.

## Akibat ke data lain
**Tidak** mengubah stok atau kas. Hadiah barang/uang dicatat sendiri (Penyesuaian Stok / Kas & Transaksi). Tidak berhubungan dengan RO member.
MD,
    ],
];
