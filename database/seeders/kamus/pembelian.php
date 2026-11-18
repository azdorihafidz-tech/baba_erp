<?php

/**
 * 4.50 Kamus Menu — kelompok Pembelian.
 * Fakta diambil dari kode. Kalau perilaku menu berubah, ubah juga teks di sini.
 */
return [

    [
        'slug' => 'purchase-order', 'judul' => 'Purchase Order (PO)', 'kelompok' => 'Pembelian', 'urutan' => 10,
        'letak' => 'Pembelian → Purchase Order',
        'ringkasan' => 'Mencatat pembelian ke supplier: buat PO, setujui, kirim, terima barang (stok bertambah), lalu catat pembayarannya.',
        'izin' => ['pembelian.view'],
        'route_names' => ['pembelian.index', 'pembelian.create', 'pembelian.show', 'pembelian.cari-transaksi'],
        'terkait' => ['supplier', 'dashboard-po', 'bahan-baku', 'kas-transaksi', 'cara-menambah-stok', 'cara-memindahkan-barang'],
        'kata_kunci' => 'purchase order po beli belanja pembelian supplier terima barang barang datang bayar supplier hutang pembelian mendesak pembelian langsung harga po',
        'konten' => <<<'MD'
## Fungsi
Mencatat pembelian barang ke supplier sampai barangnya masuk stok. PO yang sudah diterima menjadi **batch stok berharga** — harga inilah yang menjadi modal (HPP) saat barang dipakai.

## Supaya menu ini muncul & bisa dipakai
- Setiap langkah punya centangan sendiri: **Lihat Pembelian**, **Buat PO**, **Approve PO**, **Kirim PO ke Supplier**, **Terima Barang dari PO**, **Hapus Purchase Order** (juga untuk Batalkan).
- **Lokasi Tujuan** = cabang yang menerima barang. Barang yang bisa dipilih: Bahan Baku, Kemasan, Tambahan Gratis milik lokasi tujuan.
- **Supplier** harus dibuat dulu di menu Supplier. Kalau supplier diberi **Supplier Internal** (lokasi kita, mis. Gudang Pusat), produk jual lokasi itu juga bisa dipesan — lihat bagian "PO ke Gudang".
- Harga awal di form = harga barang di lokasi tujuan / Harga Standar. Kalau barang punya Unit Beli (pack/dus), pilih unitnya — harga ikut menyesuaikan.

## Alur
Draft → **Disetujui** → **Dikirim Supplier** → **Diterima** (atau Dibatalkan sebelum dikirim).
Centang "Pembelian Langsung / Mendesak" hanya memberi tanda dan notifikasi khusus — alurnya tetap sama.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas | Lainnya |
|---|---|---|---|
| Simpan / Approve / Kirim ke Supplier | — | — | Notifikasi ke yang berwenang |
| **Terima** (boleh isi jumlah diterima per barang) | Lokasi tujuan **bertambah**, batch baru dengan harga PO | — | "Harga PO terakhir" barang diperbarui. Harga Standar hanya diisi kalau masih kosong |
| **Catat Pembayaran** (setelah Diterima) | — | Membuka form Kas & Transaksi yang sudah tertaut ke PO → kas yang dipilih **berkurang** | Status PO jadi "Sudah Dibayar" |
| Batal Bayar | — | Uang kembali ke kas, transaksi pembayaran dihapus | Perlu centangan Batal Bayar PO |
| Batalkan | — | — | Hanya saat Draft/Disetujui |

**Terima barang tidak mengurangi kas.** Uang baru keluar saat Catat Pembayaran.

## PO ke Gudang (supplier internal)
Kalau supplier = Supplier Internal Gudang Pusat, PO ini muncul di **POS Gudang** sebagai "Pesanan PO dari Cabang". Gudang klik "Isi Keranjang" lalu bayar di POS → barulah gudang mendapat pemasukan dan stok gudang terpotong. Cabang tetap klik **Terima** di PO-nya sendiri supaya stok cabang bertambah.

## Wajib
- Isi harga sesuai satuan yang dipilih (per pack kalau unit pack).
- Setelah barang datang, **klik Terima** — tanpa itu stok tidak bertambah.

## Jangan
- PO tidak bisa diedit. Kalau salah sebelum dikirim, **Batalkan** lalu buat ulang. PO yang sudah Diterima tidak bisa dibatalkan — koreksi stoknya lewat Penyesuaian Stok.
- Transaksi pembayaran PO tidak bisa diedit/dihapus dari Kas & Transaksi (ditolak) — pakai **Batal Bayar** di detail PO, lalu catat ulang.

## Menu yang mirip & bedanya
- **Permintaan Stok / Transfer** — memindahkan bahan antar lokasi sendiri tanpa harga beli.
- **Kiriman Cabang** — gudang menjual produk ke cabang lewat POS gudang.
MD,
    ],

    [
        'slug' => 'supplier', 'judul' => 'Supplier', 'kelompok' => 'Pembelian', 'urutan' => 20,
        'letak' => 'Pembelian → Supplier',
        'ringkasan' => 'Daftar pemasok. Supplier bisa ditandai "Supplier Internal" (lokasi sendiri) supaya PO-nya muncul di POS lokasi itu.',
        'izin' => ['supplier.view'],
        'route_names' => ['supplier.*'],
        'terkait' => ['purchase-order', 'pos'],
        'kata_kunci' => 'supplier pemasok vendor toko langganan supplier internal gudang',
        'konten' => <<<'MD'
## Fungsi
Menyimpan data pemasok yang dipilih saat membuat PO.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Supplier**; tambah/ubah/nonaktifkan perlu **Kelola Supplier**.

## Isian penting & akibatnya
| Isian | Akibat |
|---|---|
| Nama, kontak, alamat | Hanya informasi di PO |
| **Supplier Internal** (pilih lokasi) | Supplier ini adalah lokasi usaha sendiri (mis. Gudang Pusat). PO ke supplier ini: boleh berisi produk jual lokasi itu, dan muncul di **POS lokasi itu** sebagai "Pesanan PO dari Cabang" |

## Jangan
- Jangan menandai supplier luar sebagai Supplier Internal — PO-nya akan muncul di POS lokasi kita.
MD,
    ],

    [
        'slug' => 'dashboard-po', 'judul' => 'Dashboard PO', 'kelompok' => 'Pembelian', 'urutan' => 30,
        'letak' => 'Pembelian → Dashboard PO',
        'ringkasan' => 'Memantau semua PO: yang menunggu approve, belum dikirim, belum diterima, dan belum dibayar.',
        'izin' => ['po_dashboard.view'],
        'route_names' => ['pembelian.po-dashboard.*'],
        'terkait' => ['purchase-order'],
        'kata_kunci' => 'dashboard po monitoring pembelian po tertunda belum diterima belum dibayar',
        'konten' => <<<'MD'
## Fungsi
Melihat PO yang masih tertunda di setiap tahap, supaya tidak ada yang terlupa. Bisa dicetak dan diekspor.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Dashboard PO**. Tombol pintas ke detail PO perlu **Quick Action Dashboard PO**.

## Hasil
Hanya menampilkan data. Approve/Terima/Bayar tetap dilakukan di halaman detail PO.
MD,
    ],
];
