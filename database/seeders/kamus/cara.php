<?php

/**
 * Kamus Menu ERP BABA — kelompok "Cara Melakukan": pertanyaan yang punya LEBIH DARI 1 cara atau sering ditanyakan.
 * Porting Dimsum 4.50, ditulis ulang untuk BABA (parfum, toko BABA & mitra, toko online member, RO).
 */
return [

    [
        'slug' => 'cara-fitur-tidak-ada', 'judul' => 'Kenapa menu atau fitur tidak ada?', 'kelompok' => 'Cara Melakukan', 'urutan' => 5,
        'letak' => 'Semua menu',
        'ringkasan' => 'Tiga penyebab: tidak termasuk paket aplikasi, belum dicentang di Role & Hak Akses, atau aplikasi sedang baca-saja karena langganan habis.',
        'izin' => [],
        'route_names' => [],
        'terkait' => ['role-hak-akses', 'manajemen-user'],
        'kata_kunci' => 'menu tidak ada fitur hilang tidak bisa dibuka tidak termasuk paket akses ditolak 403 tombol tidak muncul tidak bisa simpan baca saja langganan habis',
        'konten' => <<<'MD'
## Ada 3 penyebab

| Penyebab | Tandanya | Cara mengatasi |
|---|---|---|
| **Tidak termasuk paket** aplikasi | Menu tidak ada untuk **siapa pun**, termasuk Owner. Alamatnya menampilkan "Fitur … tidak termasuk paket Anda" | Hubungi tim IT penyedia aplikasi. Data lama tetap aman |
| **Belum dicentang** untuk role Anda | Owner melihat menunya, Anda tidak | Minta Owner mencentang di **Pengaturan → Role & Hak Akses** |
| **Baca-saja** (langganan habis) | Pita di atas halaman; tombol Simpan/transaksi ditolak | Hubungi tim IT untuk memperpanjang |

## Perlu diketahui
- Beberapa menu juga butuh **saklar di menu lain** — lihat bagian "Supaya menu ini muncul" di Kamus menu itu (mis. Kirim ke Cabang di Edit Cabang, status toko online di Pengaturan Toko Online).
- Saat baca-saja Anda tetap bisa login, melihat data, dan mengunduh laporan. Di aplikasi member, belanja/retur/kasir/opname member ikut dibatasi; Kirim RO tetap jalan.
MD,
    ],

    [
        'slug' => 'cara-memindahkan-barang', 'judul' => 'Bagaimana memindahkan barang dari gudang ke toko?', 'kelompok' => 'Cara Melakukan', 'urutan' => 10,
        'letak' => 'Stok & Gudang / Pembelian / POS Gudang',
        'ringkasan' => 'Ada 3 cara: Permintaan Stok → Transfer (toko BABA, tanpa uang), Kirim ke Cabang (dijual, mis. ke toko mitra), PO ke Gudang. Bedanya di harga, untung gudang, dan kas toko.',
        'izin' => ['stok.request', 'stok.transfer', 'pembelian.view', 'kiriman.view'],
        'route_names' => [],
        'terkait' => ['transfer-stok', 'permintaan-stok', 'purchase-order', 'kiriman-cabang', 'produk-toko'],
        'kata_kunci' => 'pindah barang kirim barang gudang ke toko isi stok toko distribusi toko mitra mutasi parfum botol cairan isi ulang',
        'konten' => <<<'MD'
## Ada 3 cara

| | ① Permintaan Stok → Transfer | ② Kirim ke Cabang | ③ PO ke Gudang |
|---|---|---|---|
| Cocok untuk | **Toko BABA** (milik sendiri) | **Toko mitra** yang membeli, atau penjualan ke toko | Toko memesan seperti ke supplier |
| Dimulai oleh | Toko minta / gudang langsung Transfer | Gudang, dari POS gudang | Toko membuat PO |
| Harga | Modal (tanpa untung) | Harga jual gudang | Harga di PO |
| Kas toko berkurang | ❌ | ✅ saat Terima | Saat Catat Pembayaran PO |
| Stok toko bertambah | Saat Terima | Saat Terima | Saat Terima PO |
| Perlu diaktifkan | — | Saklar "Aktifkan Kirim ke Cabang" (Edit Gudang Pusat) | Supplier "Gudang" = Supplier Internal |

## Wajib (semua cara)
- Parfum yang dijual di toko sudah dicentang di **Produk Parfum Toko** — padanan barang toko dengan **Kode Item sama** dibuat otomatis, sehingga barang dicocokkan dengan benar.

## Jangan
- Jangan memakai 2 cara untuk barang yang sama — stok toko jadi dobel.
- Barang untuk **member** tidak dipindahkan lewat sini — member belanja lewat aplikasi member (Pesanan Online).
MD,
    ],

    [
        'slug' => 'cara-menambah-stok', 'judul' => 'Bagaimana menambah stok?', 'kelompok' => 'Cara Melakukan', 'urutan' => 20,
        'letak' => 'Pembelian / Stok & Gudang',
        'ringkasan' => 'PO diterima, Produksi Parfum, Penyesuaian Stok, Transfer/Kiriman diterima, retur online/cabang diterima, Stok Opname (fisik lebih). Harga (modal) batch berbeda-beda.',
        'izin' => ['stok.view', 'pembelian.view', 'stok.adjustment', 'produksi.view'],
        'route_names' => [],
        'terkait' => ['purchase-order', 'produksi', 'penyesuaian-stok', 'transfer-stok', 'stok-opname'],
        'kata_kunci' => 'tambah stok isi stok stok masuk stok awal barang masuk restock produksi',
        'konten' => <<<'MD'
## Caranya

| Cara | Kapan dipakai | Modal batch | Kas |
|---|---|---|---|
| **PO → Terima** | Beli bibit, alkohol, botol, kemasan dari supplier | Harga di PO | Berkurang saat Catat Pembayaran |
| **Produksi Parfum** | Meracik cairan / mengisi botol | Biaya bahan ÷ hasil nyata (peram: saat Selesai Peram) | — |
| **Penyesuaian Stok** (naik) | Stok awal | Harga diisi / Harga Standar | — |
| **Transfer / Kiriman → Terima** | Isi stok toko dari gudang | Modal asal / harga kirim | Kiriman: kas toko berkurang |
| **Retur online disetujui** | Barang member kembali ke gudang | HPP asal | Sesuai pilihan dana |
| **Stok Opname** (fisik lebih) | Hasil hitung lebih banyak | Harga pokok rata-rata | — (Koreksi Stok Masuk) |

## Wajib
- Stok awal **dengan harga** supaya modal penjualan tidak 0.
MD,
    ],

    [
        'slug' => 'cara-mengurangi-stok', 'judul' => 'Bagaimana mengurangi stok?', 'kelompok' => 'Cara Melakukan', 'urutan' => 30,
        'letak' => 'Stok & Gudang / Penjualan / Toko Online',
        'ringkasan' => 'Terjual (POS, pesanan online), dipakai produksi, pemakaian perlengkapan, dipindah, atau selisih hitung (Stok Opname / Penyesuaian). Pilih sesuai penyebabnya.',
        'izin' => ['stok.view', 'stok.adjustment', 'stok_opname.lihat', 'pemakaian_perlengkapan.view'],
        'route_names' => [],
        'terkait' => ['stok-opname', 'penyesuaian-stok', 'pemakaian-perlengkapan', 'pos', 'pesanan-online'],
        'kata_kunci' => 'kurangi stok stok keluar barang rusak hilang pecah bocor susut tester pakai barang buang barang',
        'konten' => <<<'MD'
## Pilih sesuai penyebab

| Penyebab | Pakai | Keuangan |
|---|---|---|
| Dijual di toko | **POS** | Penjualan + HPP |
| Dijual ke member | **Pesanan Online → Kirim / Siap diambil** | Penjualan (saat bukti diterima) + HPP online |
| Dipakai meracik / mengisi botol | **Produksi Parfum** (otomatis) | Jadi modal hasil produksi |
| Barang habis pakai (tisu, plastik) | **Pemakaian Perlengkapan** | Nilai pemakaian |
| Rusak, hilang, susut, tester, kedaluwarsa (hitung fisik) | **Stok Opname** (dengan persetujuan & bukti) | Beban per penyebab tanpa kas |
| Koreksi cepat satu barang | **Penyesuaian Stok** | Beban/selisih sesuai alasan |
| Dipindah ke lokasi lain | **Transfer Stok** | — |

## Jangan
- Jangan pakai **Reset Stok** untuk barang rusak/hilang — kerugiannya tidak tercatat.
MD,
    ],

    [
        'slug' => 'cara-membatalkan-transaksi', 'judul' => 'Bagaimana membatalkan atau memperbaiki transaksi?', 'kelompok' => 'Cara Melakukan', 'urutan' => 40,
        'letak' => 'Penjualan / Toko Online / Stok & Gudang',
        'ringkasan' => 'Setiap jenis transaksi punya cara batal sendiri: order POS, pesanan online, transfer, produksi, stok opname. Yang sudah final dikoreksi dengan transaksi baru.',
        'izin' => ['order.view', 'toko_online.pesanan', 'stok.transfer', 'produksi.view', 'stok_opname.lihat'],
        'route_names' => [],
        'terkait' => ['riwayat-order', 'pesanan-online', 'retur-online', 'transfer-stok', 'produksi', 'stok-opname'],
        'kata_kunci' => 'batal transaksi batalkan order salah input koreksi refund hapus transaksi salah harga salah diskon batal pesanan',
        'konten' => <<<'MD'
## Per jenis transaksi

| Transaksi | Cara batal | Syarat |
|---|---|---|
| Order POS | Riwayat Order → **Batalkan** (stok & kas kembali) → buat baru → **Tandai Pengganti** | Selain Owner: order hari ini; tanggal belum Closing |
| Pesanan online | Pesanan Online → **Batalkan** | Selama stok belum keluar; lunas → dana dikembalikan & −RO |
| Pesanan online yang sudah diterima | **Retur Online** (diajukan member) | Dalam batas hari retur |
| Transfer Stok | **Batalkan** saat Draft/Dikirim | Belum diterima |
| Kiriman Cabang diterima | **Retur Cabang** | — |
| Produksi | **Batalkan** | Hasil masih ada di gudang |
| Stok Opname | **Batal** | Belum disetujui |
| Kasir member (aplikasi member) | Toko Saya › Kasir → Batalkan | Hari yang sama |

## Jangan
- Jangan mengedit/menghapus transaksi otomatis di Kas & Transaksi — memang dikunci; batalkan dari menu asalnya.
MD,
    ],

    [
        'slug' => 'cara-produk-di-banyak-cabang', 'judul' => 'Bagaimana menjual parfum yang sama di banyak toko?', 'kelompok' => 'Cara Melakukan', 'urutan' => 50,
        'letak' => 'Stok & Gudang → Produk Parfum Toko',
        'ringkasan' => 'Pilih toko di Produk Parfum Toko, centang Jual + harga per toko. Barcode cukup diisi sekali dan berlaku di semua toko.',
        'izin' => ['master.produk_jual.create'],
        'route_names' => [],
        'terkait' => ['produk-toko', 'produk-jual', 'transfer-stok'],
        'kata_kunci' => 'produk di banyak toko salin produk harga beda per toko barcode semua toko jual parfum di toko baru',
        'konten' => <<<'MD'
## Caranya
1. **Produk Parfum Toko** → pilih toko → centang **Jual** + isi harga botol / harga per ml. Ulangi untuk toko lain (harga boleh berbeda per toko).
2. **Barcode** diisi sekali di baris botol → otomatis dipakai di semua toko.
3. Isi stok toko dari gudang (Transfer / Kirim ke Cabang).

## Cara lain
- **Produk Jual → Ambil dari Cabang Lain / Buat juga di cabang** — untuk produk selain parfum (mis. aksesori) yang dibuat manual.

## Perlu diketahui
- Laporan Produk "Semua Cabang" menjumlah parfum yang sama di semua toko karena Kode Item-nya sama.
MD,
    ],

    [
        'slug' => 'cara-produk-tidak-muncul-di-pos', 'judul' => 'Kenapa parfum tidak muncul atau "Habis" di POS?', 'kelompok' => 'Cara Melakukan', 'urutan' => 60,
        'letak' => 'Penjualan → POS',
        'ringkasan' => 'Periksa: dicentang di Produk Parfum Toko untuk toko ini, produk aktif, stok toko cukup, toko yang dipilih benar, dan kas default toko.',
        'izin' => ['order.create'],
        'route_names' => [],
        'terkait' => ['pos', 'produk-toko', 'transfer-stok', 'cabang'],
        'kata_kunci' => 'produk tidak muncul parfum habis di pos stok habis pos kosong barcode tidak dikenal tidak bisa bayar kas default',
        'konten' => <<<'MD'
## Periksa berurutan

| Gejala | Penyebab | Perbaiki di |
|---|---|---|
| Parfum tidak ada di grid | Belum dicentang **Jual** untuk toko ini | Produk Parfum Toko |
| Tidak ada di grid | Produk dinonaktifkan | Produk Jual → Aktif |
| Bertanda **Habis** | Stok toko kurang (botol / ml) | Isi lewat Permintaan/Transfer Stok |
| Scan barcode "tidak dikenal" | Barcode belum diisi / beda | Produk Parfum Toko → kolom Barcode |
| Semua produk toko lain yang tampil | Toko di pojok kanan atas salah | Pilih toko yang benar |
| Bayar ditolak "belum punya Kas default" | Kas Tunai/Transfer/QRIS toko tidak ada | Kas & Transaksi → Kelola Kas |
MD,
    ],

    [
        'slug' => 'cara-membuat-resep', 'judul' => 'Bagaimana membuat resep parfum?', 'kelompok' => 'Cara Melakukan', 'urutan' => 70,
        'letak' => 'Stok & Gudang → Produksi',
        'ringkasan' => 'Cara cepat: Buat Parfum (dari Konsentrasi) membuat cairan + parfum botol + resepnya sekaligus. Cara manual: Resep Produksi per barang hasil.',
        'izin' => ['produksi.view'],
        'route_names' => [],
        'terkait' => ['produksi', 'bahan-baku', 'produk-toko'],
        'kata_kunci' => 'resep parfum buat parfum racik konsentrasi edp edt bibit fixative alkohol takaran resep produksi botol ukuran',
        'konten' => <<<'MD'
## Ada 2 cara

| | Buat Parfum | Resep Produksi |
|---|---|---|
| Untuk | Aroma baru dengan konsentrasi standar | Resep khusus / mengubah resep yang ada |
| Yang dibuat | Barang cairan (ml) + resep racik per 1.000 ml + parfum botol per ukuran + resep isi botol | 1 resep untuk 1 barang hasil |
| Takaran | Dari % bibit & fixative di **Konsentrasi**, sisanya alkohol | Diisi sendiri |
| Lama peram | Dari Konsentrasi | Diisi sendiri |

## Wajib
- Bibit, fixative, alkohol, botol, dan kemasan sudah ada di **Bahan Baku & Kemasan** gudang.

## Perlu diketahui
- Resep tidak mengubah stok. Stok berubah saat **Catat Produksi**.
MD,
    ],

    [
        'slug' => 'cara-mengatur-harga', 'judul' => 'Bagaimana mengatur harga?', 'kelompok' => 'Cara Melakukan', 'urutan' => 80,
        'letak' => 'Stok & Gudang / Toko Online',
        'ringkasan' => 'Harga toko (POS) di Produk Parfum Toko; harga member di Produk Online (akun → jabatan → gudang → tetap); modal dari harga batch / Harga Standar.',
        'izin' => ['master.produk_jual.view', 'toko_online.kelola', 'master.bahan_baku.view'],
        'route_names' => [],
        'terkait' => ['produk-toko', 'produk-online', 'bahan-baku', 'produk-jual'],
        'kata_kunci' => 'atur harga harga jual harga toko harga member harga distributor harga reseller harga jabatan harga khusus harga upline modal harga standar',
        'konten' => <<<'MD'
## Ada 3 jenis harga

| Harga | Untuk | Diatur di |
|---|---|---|
| **Harga POS toko** | Pembeli di toko BABA / mitra | Produk Parfum Toko (per toko, botol & per ml) |
| **Harga member** | Member belanja di aplikasi member | Produk Online: **khusus akun → per jabatan → harga gudang** (beli ke gudang) **→ harga tetap** |
| **Modal (HPP)** | Hitung untung | Otomatis dari batch (PO, produksi); Harga Standar di Bahan Baku bila belum ada batch |

## Perlu diketahui
- Member yang beli **ke upline** memakai harga akun/jabatan/tetap (harga gudang dilewati).
- Pesanan & order lama tidak berubah saat harga diubah.
- Kasir POS hanya bisa mengubah harga dengan centangan **Ubah Harga Jual di POS**.
MD,
    ],

    [
        'slug' => 'cara-mengirim-uang-ke-pusat', 'judul' => 'Bagaimana memindahkan uang (toko ke pusat, antar kas)?', 'kelompok' => 'Cara Melakukan', 'urutan' => 90,
        'letak' => 'Keuangan',
        'ringkasan' => 'Ada 3 cara: Setoran Kasir, Transfer Dana, Transfer Antar Kas. Bedanya di tujuan, persetujuan, dan pengecekan selisih.',
        'izin' => ['setoran_kasir.view', 'setoran.view', 'transfer_antar_kas.view'],
        'route_names' => [],
        'terkait' => ['setoran-kasir', 'transfer-dana', 'transfer-antar-kas', 'kas-transaksi'],
        'kata_kunci' => 'setor uang kirim uang ke pusat pindah uang uang kasir setoran harian transfer bank',
        'konten' => <<<'MD'
## Ada 3 cara

| | Setoran Kasir | Transfer Dana | Transfer Antar Kas |
|---|---|---|---|
| Untuk | Uang tunai penjualan harian kasir toko | Uang bebas antar lokasi | Antar kas di lokasi yang sama |
| Jumlah | Dihitung sistem dari POS, kasir isi uang nyata | Diisi sendiri | Diisi sendiri |
| Cek selisih | ✅ | ❌ | ❌ |
| Persetujuan | Pusat Approve/Reject | Penerima Terima/Tolak | Tidak perlu |
| Kas asal berkurang | Saat **Approve** | Saat **Kirim** | Saat Simpan |

## Jangan
- Jangan memakai 2 cara untuk uang yang sama — kas pusat jadi dobel.
- Uang **toko mitra** milik mitra — tidak disetor ke pusat lewat cara ini.
MD,
    ],

    [
        'slug' => 'cara-mencatat-uang', 'judul' => 'Bagaimana mencatat uang masuk/keluar?', 'kelompok' => 'Cara Melakukan', 'urutan' => 100,
        'letak' => 'Keuangan / POS / Pembelian / Toko Online',
        'ringkasan' => 'Sebagian otomatis (POS, pesanan online, PO, gaji, setoran), sebagian manual (Kas & Transaksi, Transaksi Berulang). Ini daftarnya.',
        'izin' => ['keuangan.view'],
        'route_names' => [],
        'terkait' => ['kas-transaksi', 'transaksi-berulang', 'purchase-order', 'pos', 'pesanan-online'],
        'kata_kunci' => 'catat pengeluaran catat pemasukan biaya operasional bayar listrik bayar gaji bayar supplier uang keluar uang masuk',
        'konten' => <<<'MD'
## Yang tercatat otomatis (jangan dicatat lagi manual)
| Kejadian | Dicatat oleh |
|---|---|
| Penjualan di toko | POS (saat order dibayar) |
| Pembayaran pesanan online member | Pesanan Online → **Terima bukti** / Uang tunai diterima (Kas Transfer gudang) |
| Retur / pembatalan pesanan online lunas | Retur Online / Batalkan (Penjualan negatif) |
| Bayar supplier | Purchase Order → Catat Pembayaran |
| Gaji | Penggajian → **Tandai Dibayar** |
| Setor/kirim uang | Setoran Kasir / Transfer Dana / Transfer Antar Kas |
| Barang rusak/hilang | Stok Opname / Penyesuaian Stok (beban tanpa kas) |

## Yang dicatat manual
| Cara | Untuk |
|---|---|
| **Kas & Transaksi → Tambah Transaksi** | Biaya sekali-sekali, pemasukan lain, pembelian aset |
| **Transaksi Berulang** | Biaya rutin (sewa, langganan) |

## Jangan
- Jangan mencatat pembelian bahan lewat Tambah Transaksi saja — buat **PO** supaya stok & modal ikut tercatat.
MD,
    ],

    [
        'slug' => 'cara-melihat-untung', 'judul' => 'Bagaimana melihat untung?', 'kelompok' => 'Cara Melakukan', 'urutan' => 110,
        'letak' => 'Penjualan / Toko Online / Laporan / Keuangan',
        'ringkasan' => 'Ada beberapa laporan "untung" dengan hitungan berbeda: laporan kasir, Laporan Belanja Online, Laba Rugi, Laba Rugi Formal, Tutup Buku, laporan kas.',
        'izin' => ['penjualan.laporan.keuntungan', 'toko_online.laporan', 'laporan.laba_rugi.view', 'laporan.laba_rugi_formal.view', 'closing.view', 'laporan.keuangan.view'],
        'route_names' => [],
        'terkait' => ['laporan-transaksi-kasir', 'laporan-belanja-online', 'laporan-laba-rugi', 'laporan-laba-rugi-formal', 'closing', 'laporan-keuangan'],
        'kata_kunci' => 'untung laba keuntungan rugi profit margin laba bersih laba kotor online toko',
        'konten' => <<<'MD'
## Laporannya

| Laporan | Untung = | Biaya operasional dikurangi? |
|---|---|---|
| **Penjualan → Laporan Transaksi/Produk/Kategori** | Pendapatan POS − HPP | ❌ |
| **Toko Online → Laporan Belanja Online** | Omzet online gudang (bersih retur) − HPP | ❌ |
| **Laporan → Laba Rugi** (per item/kategori/jenis produk) | Omzet baris order − HPP | ❌ |
| **Laporan → Laba Rugi Formal** | Pendapatan (POS + online) − HPP − beban per akun COA (termasuk kerugian stok opname) | ✅ |
| **Closing / Tutup Buku** | Sama, dibekukan saat closing | ✅ |
| Laporan kas | Uang masuk − keluar usaha (tanpa transfer internal & saldo awal) | ✅ (yang dicatat di kas) |

## Perlu diketahui
- Keuangan **toko mitra** tidak masuk laporan gabungan BABA (bisa dilihat dengan memilih toko itu).
- Tagihan member yang belum dibayar **bukan** pendapatan.

## Wajib (supaya untung akurat)
- Setiap bahan punya **harga batch > 0**; biaya operasional dicatat di Kas & Transaksi.
MD,
    ],

    [
        'slug' => 'cara-memproses-pesanan-online', 'judul' => 'Bagaimana memproses pesanan member dari aplikasi member?', 'kelompok' => 'Cara Melakukan', 'urutan' => 120,
        'letak' => 'Toko Online → Pesanan Online',
        'ringkasan' => 'Urutan kerja gudang: isi ongkir → cek bukti transfer → Kirim / Siap diambil → Sudah diambil. Tagihan: langsung disiapkan, bayar menyusul.',
        'izin' => ['toko_online.pesanan'],
        'route_names' => [],
        'terkait' => ['pesanan-online', 'retur-online', 'pengaturan-toko-online', 'catatan-ro'],
        'kata_kunci' => 'proses pesanan member pesanan online masuk cek bukti kirim pesanan ongkir resi siap diambil tagihan member',
        'konten' => <<<'MD'
## Urutan kerja
1. Buka **Pesanan Online** tab **Perlu tindakan** (angka merah di menu).
2. Pesanan dikirim kurir: **Isi ongkir** → member membayar di aplikasi member.
3. Bukti masuk: buka rincian → **Cek bukti** → **Terima** (uang masuk Kas Transfer gudang) atau **Tolak**.
4. **Kirim** (isi kurir & resi) atau **Siap diambil** → stok gudang keluar.
5. Barang diambil di gudang: **Sudah diambil**. Dikirim: member menekan *Pesanan Diterima* (atau otomatis setelah N hari bila tugas otomatisnya dihidupkan).

## Perlu diketahui
- Member dengan aturan **tagihan** langsung masuk Disiapkan; tagihannya terlihat di Laporan Belanja Online.
- Lonceng ke aplikasi member dikirim otomatis di setiap langkah admin.
- RO pembeli tercatat sesuai **Mode RO** (Pengaturan Umum).
MD,
    ],

    [
        'slug' => 'cara-ro-belum-masuk', 'judul' => 'Kenapa RO belanja online belum masuk di aplikasi member?', 'kelompok' => 'Cara Melakukan', 'urutan' => 130,
        'letak' => 'Toko Online → Catatan RO',
        'ringkasan' => 'Periksa: Mode RO (lunas/diterima), status pesanan, Catatan RO (menunggu/gagal), cron Jadwal Otomatis, dan alur approve dokumen di aplikasi member.',
        'izin' => ['toko_online.kelola'],
        'route_names' => [],
        'terkait' => ['catatan-ro', 'jadwal-otomatis', 'pengaturan-umum', 'pesanan-online'],
        'kata_kunci' => 'ro belum masuk ro tidak muncul repeat order belum tercatat input data member dokumen ro gagal kirim',
        'konten' => <<<'MD'
## Periksa berurutan

| Gejala | Penyebab | Perbaiki |
|---|---|---|
| Tidak ada di Catatan RO | Pesanan belum memenuhi **Mode RO** (mis. belum lunas, atau mode "saat diterima" dan pesanan belum diterima) | Selesaikan pesanan; cek Pengaturan Umum → RO dari Belanja Online |
| Status **menunggu** lama | Cron server tidak berjalan | Pengaturan → Jadwal Otomatis: harus "Cron berjalan" |
| Status **gagal** (angka merah) | Aplikasi member tidak bisa dihubungi / kunci sambungan salah | Klik **Kirim ulang**; bila tetap gagal hubungi tim IT |
| Sudah terkirim tapi belum dihitung di aplikasi member | Mode "lunas + disetujui": dokumen RO menunggu approve (Regional → MVO → superadmin) | Approve di aplikasi member (Document Belum Approve) |

## Perlu diketahui
- Penjualan **POS** dan **kasir member** tidak pernah menambah RO.
MD,
    ],

    [
        'slug' => 'cara-membuka-toko-online', 'judul' => 'Bagaimana membuka toko online untuk member?', 'kelompok' => 'Cara Melakukan', 'urutan' => 140,
        'letak' => 'Toko Online → Pengaturan Toko Online',
        'ringkasan' => 'Siapkan produk & harga, stok gudang, rekening, aturan tagihan/sumber, Mode RO; uji coba dengan beberapa akun; lalu Buka untuk semua member.',
        'izin' => ['toko_online.kelola'],
        'route_names' => [],
        'terkait' => ['pengaturan-toko-online', 'produk-online', 'pesanan-online', 'pengaturan-umum'],
        'kata_kunci' => 'buka toko online segera hadir uji coba belanja member mulai jualan online tutup toko',
        'konten' => <<<'MD'
## Langkah
1. **Produk Online**: pilih parfum botol, isi harga (tetap, gudang, per jabatan, khusus akun) + foto.
2. Pastikan **stok gudang** tersedia.
3. **Pengaturan Toko Online**: rekening gudang, aturan tagihan, sumber barang (gudang / upline), batas retur.
4. **Pengaturan Umum → RO dari Belanja Online**: pilih Mode RO.
5. Status toko → **Uji coba**, pilih akun penguji → coba belanja dari aplikasi member sampai pesanan diterima & RO masuk.
6. Status toko → **Buka**.

## Perlu diketahui
- Selama *Tutup*, menu Belanja di aplikasi member menampilkan "Segera hadir".
- Modul **Toko Online** harus termasuk paket, dan aplikasi tidak dalam mode baca-saja.
- **Jadwal Otomatis → Pesanan online otomatis** (terima otomatis & batal lewat batas bayar) bawaannya mati — hidupkan bila diperlukan.
MD,
    ],
];
