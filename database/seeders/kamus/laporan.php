<?php

/**
 * Kamus Menu ERP BABA — kelompok Laporan (porting Dimsum 4.50) (semua menu di bagian Laporan sidebar).
 * Semua laporan hanya MEMBACA data (tidak mengubah stok/kas) kecuali disebut lain.
 * Fakta diambil dari kode. Kalau perilaku laporan berubah, ubah juga teks di sini.
 */
$umum = "Data mengikuti cabang di pojok kanan atas (role yang hanya boleh cabangnya sendiri selalu melihat cabangnya). Tampilan gabungan (tanpa memilih toko) tidak memuat **toko mitra** (Toko Kemitraan / Toko Refill Member). Hanya menampilkan data — tidak mengubah stok, kas, atau transaksi.";

return [

    [
        'slug' => 'laporan-penjualan', 'judul' => 'Laporan → Penjualan', 'kelompok' => 'Laporan', 'urutan' => 10,
        'letak' => 'Laporan → Penjualan',
        'ringkasan' => 'Daftar & ringkasan penjualan per periode/cabang/status dengan Analisis Periode, ekspor Excel/PDF.',
        'izin' => ['laporan.penjualan.view'],
        'route_names' => ['laporan.penjualan'],
        'terkait' => ['laporan-transaksi-kasir', 'laporan-laba-rugi', 'riwayat-order', 'cara-melihat-untung'],
        'kata_kunci' => 'laporan penjualan omzet order per periode export penjualan',
        'konten' => <<<MD
## Fungsi
Melihat semua order penjualan dalam rentang tanggal: jumlah, omzet, rata-rata, hari tertinggi (Analisis Periode), dan daftar order.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Penjualan**. {$umum}

## Yang dihitung
Order sah saja: bill Pending, order Dibatalkan, dan order yang dihapus tidak dihitung. Kotak Analisis Periode disimpan sementara 5 menit.

## Menu yang mirip & bedanya
- **Penjualan → Laporan Transaksi/Produk/Kategori** — versi untuk kasir, bertingkat per waktu/produk, keuntungan bisa disembunyikan.
- **Laporan → Laba Rugi** — untung kotor (omzet − HPP).
MD,
    ],

    [
        'slug' => 'laporan-stok', 'judul' => 'Laporan → Stok', 'kelompok' => 'Laporan', 'urutan' => 20,
        'letak' => 'Laporan → Stok (Stok, Pergerakan, Minimum)',
        'ringkasan' => '3 laporan: stok saat ini per barang, riwayat pergerakan stok, dan barang di bawah stok minimum dengan rekomendasi beli.',
        'izin' => ['laporan.stok.view'],
        'route_names' => ['laporan.stok', 'laporan.stok.*'],
        'terkait' => ['stok-barang', 'dashboard-stok', 'purchase-order'],
        'kata_kunci' => 'laporan stok pergerakan stok stok minimum rekomendasi beli setara pack',
        'konten' => <<<MD
## Fungsi
- **Stok** — sisa stok tiap barang + kolom "Setara Pack" untuk barang yang punya Unit Beli.
- **Pergerakan** — semua stok keluar/masuk (jual, PO, transfer, penyesuaian, produksi, kiriman) beserta catatannya.
- **Minimum** — barang di bawah Qty Minimum + **Rekomendasi Beli** ((minimum − sisa) × 1,2, dibulatkan ke atas).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Stok**. Barang yang dihitung: Bahan Baku, Kemasan, Tambahan Gratis. {$umum}
- Laporan Minimum hanya memuat barang yang Qty Minimum-nya diisi (Stok Barang → Set Qty Minimum).

## Perlu diketahui
- Pergerakan belum punya kolom "sisa stok sesudah" — untuk sisa saat ini pakai laporan Stok.
MD,
    ],

    [
        'slug' => 'laporan-keuangan', 'judul' => 'Laporan → Keuangan', 'kelompok' => 'Laporan', 'urutan' => 30,
        'letak' => 'Laporan → Keuangan (Laba Rugi, Arus Kas, Harian)',
        'ringkasan' => 'Rekap pemasukan/pengeluaran per kategori dan arus kas dari semua transaksi Kas & Transaksi.',
        'izin' => ['laporan.keuangan.view'],
        'route_names' => ['laporan.keuangan.*'],
        'terkait' => ['kas-transaksi', 'laporan-laba-rugi-formal', 'cara-melihat-untung'],
        'kata_kunci' => 'laporan keuangan laba rugi arus kas harian pemasukan pengeluaran',
        'konten' => <<<MD
## Fungsi
Menjumlah transaksi **Kas & Transaksi** per kategori (Laba Rugi) dan per kas (Arus Kas) untuk periode tertentu.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Keuangan (Laba Rugi & Arus Kas)**. {$umum}

## Yang dihitung
- **Laba Rugi**: transfer antar kas, setoran kasir, transfer dana, dan saldo awal kas **tidak dihitung** (ditampilkan sebagai keterangan). Berbasis kas — pembelian bahan = pengeluaran saat dibayar, belum memakai HPP.
- **Arus Kas**: semua uang yang bergerak tetap dihitung (memang arus kas), dengan rincian berapa yang berasal dari transfer internal & saldo awal.
- Untuk laba dengan modal barang pakai **Laba Rugi Formal** atau **Closing**.
MD,
    ],

    [
        'slug' => 'laporan-hr', 'judul' => 'Laporan → HR / SDM', 'kelompok' => 'Laporan', 'urutan' => 40,
        'letak' => 'Laporan → HR / SDM (Absensi, Penggajian, Evaluasi)',
        'ringkasan' => 'Rekap absensi per karyawan, rekap gaji per periode, dan hasil Penilaian 360°.',
        'izin' => ['laporan.hr_absensi.view', 'laporan.hr_penggajian.view', 'laporan.hr_evaluasi.view'],
        'route_names' => ['laporan.hr.*'],
        'terkait' => ['absensi-manual', 'penggajian', 'evaluasi'],
        'kata_kunci' => 'laporan hr sdm absensi karyawan rekap gaji laporan penggajian laporan evaluasi',
        'konten' => <<<MD
## Fungsi
3 laporan dengan centangan sendiri-sendiri: **Absensi**, **Penggajian**, **Evaluasi**. Tombol pindah antar laporan hanya tampil untuk yang dicentang.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan HR — Absensi / Penggajian / Evaluasi**. {$umum}

## Perlu diketahui
- Laporan Penggajian menampilkan slip apa adanya (status Draft/Disetujui/Dibayar). Slip yang dibayar lewat tombol Bayar sudah tercatat di kas; slip yang ditandai Dibayar sebelum 16 Nov 2026 belum tentu.
MD,
    ],

    [
        'slug' => 'laporan-aset', 'judul' => 'Laporan → Aset', 'kelompok' => 'Laporan', 'urutan' => 50,
        'letak' => 'Laporan → Aset (Daftar, Penyusutan)',
        'ringkasan' => 'Daftar aset dengan nilai buku, dan riwayat penyusutan per periode.',
        'izin' => ['laporan.aset.view'],
        'route_names' => ['laporan.aset', 'laporan.aset.*'],
        'terkait' => ['aset', 'laporan-neraca'],
        'kata_kunci' => 'laporan aset penyusutan depresiasi nilai buku',
        'konten' => <<<MD
## Fungsi
Melihat aset dan nilai bukunya, serta penyusutan yang sudah dicatat.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Aset**. {$umum}

## Perlu diketahui
- Sub-halaman **Maintenance** belum menampilkan daftar (masih placeholder) — lihat riwayat perawatan di detail aset.
MD,
    ],

    [
        'slug' => 'laporan-bep', 'judul' => 'Laporan → BEP', 'kelompok' => 'Laporan', 'urutan' => 60,
        'letak' => 'Laporan → BEP (per produk, per cabang)',
        'ringkasan' => 'Hasil Analisis BEP manual (dari isian di Keuangan → Analisis BEP), per produk dan per cabang.',
        'izin' => ['laporan.bep.view'],
        'route_names' => ['laporan.bep', 'laporan.bep.*'],
        'terkait' => ['analisis-bep', 'simulator-bep'],
        'kata_kunci' => 'laporan bep break even titik impas per cabang',
        'konten' => <<<MD
## Fungsi
Menampilkan hasil BEP yang disusun di **Keuangan → Analisis BEP**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan BEP**. Data baru ada kalau Analisis BEP cabang & bulan itu sudah diisi. {$umum}
MD,
    ],

    [
        'slug' => 'laporan-transfer-dana', 'judul' => 'Laporan → Transfer / Perpindahan Dana', 'kelompok' => 'Laporan', 'urutan' => 80,
        'letak' => 'Laporan → Transfer / Perpindahan Dana',
        'ringkasan' => 'Daftar Transfer Dana antar cabang dengan statusnya (menunggu, diterima, ditolak, dibatalkan).',
        'izin' => ['laporan.transfer_dana.view'],
        'route_names' => ['laporan.setoran'],
        'terkait' => ['transfer-dana', 'laporan-setoran-kasir'],
        'kata_kunci' => 'laporan transfer dana laporan perpindahan dana setoran ke pusat',
        'konten' => <<<MD
## Fungsi
Rekap pengiriman uang lewat menu **Transfer Dana**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Transfer Dana**. {$umum}

## Menu yang mirip & bedanya
- **Laporan Setoran Kasir** — khusus Setoran Kasir (uang tunai penjualan harian). Dua laporan ini sumbernya berbeda.
MD,
    ],

    [
        'slug' => 'laporan-per-kategori', 'judul' => 'Laporan → Per Kategori', 'kelompok' => 'Laporan', 'urutan' => 90,
        'letak' => 'Laporan → Per Kategori',
        'ringkasan' => 'Total transaksi Kas & Transaksi per Kategori Transaksi untuk periode tertentu.',
        'izin' => ['laporan.kategori.view'],
        'route_names' => ['laporan.kategori'],
        'terkait' => ['kategori-transaksi', 'kas-transaksi'],
        'kata_kunci' => 'laporan per kategori pengeluaran per kategori biaya per kategori',
        'konten' => <<<MD
## Fungsi
Melihat ke mana uang paling banyak keluar/masuk, dikelompokkan per Kategori Transaksi.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Per Kategori**. {$umum}

## Menu yang mirip & bedanya
- **Penjualan → Laporan Kategori** — kategori **produk** yang terjual, bukan kategori transaksi kas.
MD,
    ],

    [
        'slug' => 'laporan-audit-bukti', 'judul' => 'Laporan → Audit Bukti', 'kelompok' => 'Laporan', 'urutan' => 100,
        'letak' => 'Laporan → Audit Bukti',
        'ringkasan' => 'Daftar pengeluaran di atas batas nominal, ditandai merah kalau tidak ada foto bukti.',
        'izin' => ['laporan.audit_bukti.view'],
        'route_names' => ['laporan.audit-bukti'],
        'terkait' => ['kas-transaksi'],
        'kata_kunci' => 'audit bukti nota tanpa bukti pengeluaran besar cek bukti',
        'konten' => <<<MD
## Fungsi
Mengecek pengeluaran besar yang belum dilengkapi bukti (foto nota/kwitansi).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Audit Bukti**. {$umum}
- Bukti diunggah saat Tambah/Edit transaksi di **Kas & Transaksi**.
MD,
    ],

    [
        'slug' => 'laporan-saldo-kas', 'judul' => 'Laporan → Saldo Kas', 'kelompok' => 'Laporan', 'urutan' => 110,
        'letak' => 'Laporan → Saldo Kas',
        'ringkasan' => 'Saldo setiap kas aktif per cabang.',
        'izin' => ['laporan.saldo_kas.view'],
        'route_names' => ['laporan.saldo-kas'],
        'terkait' => ['kas-transaksi', 'closing'],
        'kata_kunci' => 'saldo kas uang di kas posisi kas',
        'konten' => <<<MD
## Fungsi
Melihat posisi uang di setiap kas (tunai, bank, QRIS).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Saldo Kas**. {$umum}

## Perlu diketahui
- Saldo = saldo yang tercatat sekarang. Kalau tidak cocok dengan uang nyata, cek transaksi yang lupa dicatat (gaji, pembelian aset) atau pakai **Sinkron Saldo** di Kelola Kas.
MD,
    ],

    [
        'slug' => 'laporan-setoran-harian', 'judul' => 'Laporan → Setoran Harian', 'kelompok' => 'Laporan', 'urutan' => 120,
        'letak' => 'Laporan → Setoran Harian',
        'ringkasan' => 'Ringkasan 1 hari 1 cabang: pemasukan per metode, pengeluaran, dan kas bersih — untuk closing kasir; ekspor rekap per tanggal.',
        'izin' => ['laporan.setoran_harian.view'],
        'route_names' => ['laporan.setoran-harian.*'],
        'terkait' => ['setoran-kasir', 'closing'],
        'kata_kunci' => 'setoran harian rekap harian kas bersih tutup kasir',
        'konten' => <<<MD
## Fungsi
Melihat uang masuk/keluar satu hari di satu cabang (dulu dicek di 3 halaman terpisah). Tombol **Export Rekap** membuat 1 baris per tanggal + cabang (Total Order, Pemasukan, Pengeluaran, Kas Bersih).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Setoran Harian**; cetak: **Print Laporan Setoran Harian**. {$umum}

## Perlu diketahui
- Pemasukan dari order tidak dihitung dua kali (transaksi kas otomatis dari order dikeluarkan dari hitungan).
- "Kas Bersih" bukan jumlah Setoran Kasir — untuk setoran pakai menu/Laporan Setoran Kasir.
MD,
    ],

    [
        'slug' => 'laporan-konsumsi-bahan', 'judul' => 'Laporan → Konsumsi Bahan Baku', 'kelompok' => 'Laporan', 'urutan' => 130,
        'letak' => 'Laporan → Konsumsi Bahan Baku',
        'ringkasan' => 'Jumlah dan nilai (HPP FIFO) bahan yang terpakai karena penjualan dalam periode.',
        'izin' => ['laporan.konsumsi_bahan.view'],
        'route_names' => ['laporan.konsumsi-bahan.*'],
        'terkait' => ['produk-jual', 'laporan-laba-rugi'],
        'kata_kunci' => 'konsumsi bahan pemakaian bahan bahan terpakai hpp bahan',
        'konten' => <<<MD
## Fungsi
Melihat bahan apa saja yang habis dipakai untuk penjualan dan berapa nilainya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Konsumsi Bahan Baku**; cetak: **Print …**. {$umum}

## Perlu diketahui
- Hanya pemakaian dari **penjualan POS** (komposisi produk). Pemakaian Perlengkapan, Penyesuaian Stok, Stok Opname, Produksi Parfum, dan pesanan online tidak termasuk.
MD,
    ],

    [
        'slug' => 'laporan-laba-rugi', 'judul' => 'Laporan → Laba Rugi', 'kelompok' => 'Laporan', 'urutan' => 140,
        'letak' => 'Laporan → Laba Rugi',
        'ringkasan' => 'Untung kotor dari penjualan POS (omzet − HPP) per produk, kategori, Jenis Produk, atau per order.',
        'izin' => ['laporan.laba_rugi.view'],
        'route_names' => ['laporan.laba-rugi.*'],
        'terkait' => ['laporan-laba-rugi-formal', 'jenis-produk', 'cara-melihat-untung'],
        'kata_kunci' => 'laba rugi untung kotor margin hpp per produk per kategori per jenis produk gross profit',
        'konten' => <<<MD
## Fungsi
Melihat untung kotor dari penjualan: omzet dikurangi modal bahan (HPP FIFO) per baris order.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Laba Rugi**; cetak: **Print Laporan Laba Rugi**. {$umum}
- Pilihan **Per Jenis Produk** terisi kalau produk diberi **Jenis Produk** (Produk Jual → Edit; daftarnya di Penjualan → Jenis Produk).

## Perlu diketahui
- **Belum dikurangi** biaya operasional (gaji, sewa, listrik). Untuk laba bersih pakai **Laba Rugi Formal** atau **Closing**.
- Omzet dijumlah dari baris order (sebelum diskon order).
MD,
    ],

    [
        'slug' => 'laporan-laba-rugi-formal', 'judul' => 'Laporan → Laba Rugi Formal', 'kelompok' => 'Laporan', 'urutan' => 150,
        'letak' => 'Laporan → Laba Rugi Formal',
        'ringkasan' => 'Laba rugi standar akuntansi (SAK ETAP) per akun COA: pendapatan, HPP, beban operasional, laba bersih.',
        'izin' => ['laporan.laba_rugi_formal.view'],
        'route_names' => ['laporan.laba-rugi-formal.*'],
        'terkait' => ['coa', 'kategori-transaksi', 'laporan-neraca', 'cara-melihat-untung'],
        'kata_kunci' => 'laba rugi formal laba bersih sak etap akuntansi beban operasional',
        'konten' => <<<MD
## Fungsi
Laporan laba rugi resmi yang dikelompokkan per akun COA, sampai laba bersih.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Laba Rugi Formal**. {$umum}
- Setiap **Kategori Transaksi** harus ditautkan ke **akun COA** yang benar — kategori tanpa akun tidak masuk baris yang tepat.

## Perlu diketahui (BABA)
- Pendapatan memuat penjualan POS **dan** pembayaran pesanan online gudang; HPP online (barang keluar − retur kembali) masuk akun HPP Barang Terjual.
- Kerugian stok opname tampil per penyebab (Kerugian Stok – Rusak/Hilang/Kedaluwarsa, Penyusutan Stok, Promosi, Selisih Persediaan); pemulihan (stok rusak, ganti rugi) sebagai pendapatan lain.
MD,
    ],

    [
        'slug' => 'laporan-neraca', 'judul' => 'Laporan → Neraca', 'kelompok' => 'Laporan', 'urutan' => 160,
        'letak' => 'Laporan → Neraca',
        'ringkasan' => 'Posisi keuangan: kas, persediaan (FIFO), aset (nilai buku), kewajiban, dan modal.',
        'izin' => ['laporan.neraca.view'],
        'route_names' => ['laporan.neraca.*'],
        'terkait' => ['laporan-aset', 'kas-transaksi', 'stok-barang'],
        'kata_kunci' => 'neraca balance sheet aset kewajiban modal owner persediaan posisi keuangan',
        'konten' => <<<MD
## Fungsi
Melihat kekayaan usaha pada saat ini.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Neraca**. {$umum}
- **Modal Owner** diisi manual di halaman ini (**khusus Owner**) supaya neraca seimbang.

## Sumber angka
| Baris | Dari |
|---|---|
| Kas | Saldo semua kas aktif |
| Persediaan | Sisa batch stok × harga batch (FIFO) |
| Aset tetap | Nilai buku di Manajemen Aset |

## Perlu diketahui
- Persediaan **tidak** menghitung sisa stok milik barang yang sudah dihapus (sama dengan Dashboard & Laporan Stok). Kalau barang seperti itu dipulihkan, stoknya ikut terhitung lagi.
- **Stok member** bukan persediaan BABA. **Tagihan member** yang belum dibayar tampil sebagai baris informasi (tidak dijumlahkan).
MD,
    ],

    [
        'slug' => 'laporan-buku-besar', 'judul' => 'Laporan → Buku Besar', 'kelompok' => 'Laporan', 'urutan' => 170,
        'letak' => 'Laporan → Buku Besar',
        'ringkasan' => 'Mutasi debit/kredit dan saldo berjalan untuk 1 akun COA.',
        'izin' => ['laporan.buku_besar.view'],
        'route_names' => ['laporan.buku-besar.*'],
        'terkait' => ['coa', 'laporan-laba-rugi-formal'],
        'kata_kunci' => 'buku besar ledger mutasi akun debit kredit',
        'konten' => <<<MD
## Fungsi
Melihat semua transaksi yang masuk ke 1 akun COA.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Buku Besar**. Wajib memilih akun. {$umum}
- Akun hanya terisi kalau Kategori Transaksi sudah ditautkan ke akun COA.
MD,
    ],

    [
        'slug' => 'laporan-eksekutif', 'judul' => 'Laporan → Laporan Eksekutif', 'kelompok' => 'Laporan', 'urutan' => 180,
        'letak' => 'Laporan → Laporan Eksekutif',
        'ringkasan' => 'Dokumen 9 halaman untuk Owner: ringkasan, neraca, laba rugi, BEP, arus kas, temuan, simulasi balik modal (PDF).',
        'izin' => ['laporan.eksekutif.view'],
        'route_names' => ['laporan.eksekutif.*'],
        'terkait' => ['laporan-neraca', 'laporan-laba-rugi-formal'],
        'kata_kunci' => 'laporan eksekutif ringkasan owner presentasi pdf kesehatan usaha',
        'konten' => <<<MD
## Fungsi
Gabungan beberapa laporan dalam bentuk dokumen presentasi, beserta skor kesehatan usaha dan temuan.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Eksekutif Keuangan**. {$umum}
- Hanya PDF (bukan tabel untuk Excel).
MD,
    ],

    [
        'slug' => 'simulator-bep', 'judul' => 'Laporan → Simulator BEP', 'kelompok' => 'Laporan', 'urutan' => 190,
        'letak' => 'Laporan → Simulator BEP',
        'ringkasan' => 'Kalkulator "bagaimana kalau": geser volume, harga, biaya untuk melihat titik impas; bisa diekspor sebagai catatan.',
        'izin' => ['laporan.simulator.view'],
        'route_names' => ['laporan.simulator-bep.*'],
        'terkait' => ['analisis-bep'],
        'kata_kunci' => 'simulator bep simulasi kalkulator harga jual volume balik modal',
        'konten' => <<<MD
## Fungsi
Mencoba-coba angka (volume harian, harga jual, biaya variabel, beban tetap) dan langsung melihat BEP, margin, dan tabel sensitivitas.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Simulator BEP**; ekspor perlu centangan ekspornya sendiri. Nilai awal diambil dari data bulan berjalan.

## Hasil
Tidak menyimpan apa pun. Ekspor Excel/PDF = foto hasil simulasi (dihitung ulang di server).
MD,
    ],

    [
        'slug' => 'laporan-jam-ramai', 'judul' => 'Laporan → Analisa Jam Ramai', 'kelompok' => 'Laporan', 'urutan' => 200,
        'letak' => 'Laporan → Analisa Jam Ramai',
        'ringkasan' => 'Jumlah order & omzet per jam (24 jam) untuk menentukan jam sibuk dan jadwal karyawan.',
        'izin' => ['laporan.jam_ramai.view'],
        'route_names' => ['laporan.jam-ramai.*'],
        'terkait' => ['shift', 'laporan-transaksi-kasir'],
        'kata_kunci' => 'jam ramai jam sibuk peak hour per jam',
        'konten' => <<<MD
## Fungsi
Melihat jam berapa toko paling ramai.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Jam Ramai**. {$umum}
- Dihitung dari order penjualan saja.
MD,
    ],

    [
        'slug' => 'laporan-perlengkapan', 'judul' => 'Laporan → Pemakaian Perlengkapan', 'kelompok' => 'Laporan', 'urutan' => 210,
        'letak' => 'Laporan → Pemakaian Perlengkapan',
        'ringkasan' => 'Rekap barang habis pakai yang dicatat di menu Pemakaian Perlengkapan beserta nilainya.',
        'izin' => ['laporan.perlengkapan.view'],
        'route_names' => ['laporan.perlengkapan.*'],
        'terkait' => ['pemakaian-perlengkapan'],
        'kata_kunci' => 'laporan perlengkapan pemakaian gas sabun atk',
        'konten' => <<<MD
## Fungsi
Melihat pemakaian perlengkapan per periode.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Pemakaian Perlengkapan**; cetak: **Print …**. {$umum}
- Hanya berisi yang dicatat lewat menu **Pemakaian Perlengkapan**.
MD,
    ],

    [
        'slug' => 'laporan-setoran-kasir', 'judul' => 'Laporan → Setoran Kasir', 'kelompok' => 'Laporan', 'urutan' => 220,
        'letak' => 'Laporan → Laporan Setoran Kasir',
        'ringkasan' => 'Rekap Setoran Kasir per tanggal & cabang: total sistem, disetor, selisih, status.',
        'izin' => ['laporan.setoran_kasir.view'],
        'route_names' => ['laporan.setoran-kasir.*'],
        'terkait' => ['setoran-kasir', 'laporan-transfer-dana'],
        'kata_kunci' => 'laporan setoran kasir selisih setoran rekap setoran',
        'konten' => <<<MD
## Fungsi
Memantau kedisiplinan & selisih setoran kasir.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Setoran Kasir**. {$umum}
- Hari yang belum disubmit kasir tidak muncul.
MD,
    ],

    [
        'slug' => 'laporan-cabang-vs-cabang', 'judul' => 'Laporan → Cabang vs Cabang', 'kelompok' => 'Laporan', 'urutan' => 230,
        'letak' => 'Laporan → Cabang vs Cabang',
        'ringkasan' => 'Membandingkan pemasukan, pengeluaran, dan setoran antar cabang dalam satu tabel.',
        'izin' => ['laporan.cabang_vs_cabang.view'],
        'route_names' => ['laporan.cabang-vs-cabang'],
        'terkait' => ['dashboard-semua-cabang', 'laporan-transaksi-kasir'],
        'kata_kunci' => 'cabang vs cabang perbandingan cabang ranking cabang',
        'konten' => <<<MD
## Fungsi
Melihat cabang mana yang paling besar pemasukan/pengeluarannya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Cabang vs Cabang** **dan** role boleh melihat semua cabang.

## Yang dihitung
- Pemasukan/pengeluaran **usaha** dari Kas & Transaksi — tanpa transfer antar kas, setoran kasir, transfer dana, dan saldo awal. Setoran ditampilkan di kolom setoran sendiri.
- Untuk membandingkan penjualan murni, pakai **Penjualan → Laporan Transaksi** per cabang.
MD,
    ],
];
