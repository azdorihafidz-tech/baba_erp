<?php

/**
 * Kamus Menu ERP BABA — kelompok Toko Online (khusus BABA: belanja member di aplikasi member, diproses di ERP).
 * Fakta dari kode: PesananOnlineService, ReturOnlineService, HargaOnlineService, RoService, StokMemberService,
 * LaporanOnlineService, TokoOnlinePengaturan, middleware toko.buka & CekPaketMember.
 */
return [

    [
        'slug' => 'pesanan-online', 'judul' => 'Pesanan Online', 'kelompok' => 'Toko Online', 'urutan' => 10,
        'letak' => 'Toko Online → Pesanan Online',
        'ringkasan' => 'Pesanan member dari menu Belanja aplikasi member ke gudang: isi ongkir, cek bukti transfer, kirim / siap diambil, batal.',
        'izin' => ['toko_online.pesanan'],
        'route_names' => ['toko-online.pesanan.*'],
        'terkait' => ['retur-online', 'pengaturan-toko-online', 'catatan-ro', 'produk-online', 'stok-member', 'cara-memproses-pesanan-online'],
        'kata_kunci' => 'pesanan online order member belanja aplikasi member ongkir bukti transfer cek bukti kirim resi kurir siap diambil tagihan batal pesanan gudang',
        'konten' => <<<'MD'
## Fungsi
Gudang memproses pesanan yang dibuat member di **aplikasi member › Belanja**. Member tidak login ke ERP — pesanan masuk lewat sambungan antar-server berkunci.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Proses Pesanan Online** (bawaan Admin Pusat, Admin Gudang). Hanya pesanan **gudang yang boleh Anda akses** yang tampil.
- Member hanya bisa belanja kalau **status toko** di Pengaturan Toko Online = *Buka* (atau *Uji coba* untuk akunnya), produknya ada di **Produk Online** dengan harga untuk jabatannya, dan stok gudang tersedia.
- Modul **Toko Online** harus termasuk paket aplikasi.
- Angka merah di menu = pesanan **perlu tindakan**.

## Alur status
Dikirim member → **Menunggu ongkir** (bila dikirim kurir) → **Menunggu bayar** (transfer, ada batas waktu) → member unggah bukti → **Menunggu cek** → Terima bukti → **Disiapkan** → **Kirim** (kurir + resi) / **Siap diambil** → **Diterima** (member, admin *Sudah diambil*, atau otomatis setelah N hari).
Bayar **tagihan** (bila aturan tagihan mengizinkan): langsung Disiapkan, bayar menyusul sebelum jatuh tempo.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas & keuangan | Aplikasi member |
|---|---|---|---|
| Isi ongkir | — | — | Total berubah, member diminta bayar (lonceng) |
| **Terima bukti** | — | Pemasukan Penjualan + saldo **Kas Transfer gudang** | Lonceng; RO dikirim bila Mode RO = saat lunas |
| Tolak bukti | — | — | Kembali Menunggu bayar (lonceng) |
| **Kirim / Siap diambil** | Stok gudang **keluar** (FIFO), HPP tercatat | HPP online masuk Laba Rugi | Lonceng |
| Sudah diambil | Stok member **bertambah** | — | Pesanan Diterima; RO dikirim bila Mode RO = saat diterima |
| **Batalkan** | Hanya selama stok belum keluar | Sudah lunas → Penjualan negatif (kembalikan dana) & −RO | Lonceng |

Stok yang sudah **dipesan** mengurangi stok tersedia di katalog member, jadi gudang tidak menjual melebihi stok.

## Wajib
- Cek nominal & keaslian bukti transfer sebelum **Terima bukti**. Satu file bukti tidak bisa dipakai dua kali.
- Isi kurir & resi saat Kirim.

## Jangan
- Jangan memproses pesanan online lewat POS — stok dan RO-nya jadi tidak tercatat ke member.

## Menu yang mirip & bedanya
- Tab **Antar member** (Owner/Admin Pusat) — pesanan member ke upline-nya; ERP hanya memantau dan boleh membatalkan yang macet.
- **Retur Online** — pengembalian barang dari pesanan yang sudah diterima.
MD,
    ],

    [
        'slug' => 'retur-online', 'judul' => 'Retur Online', 'kelompok' => 'Toko Online', 'urutan' => 20,
        'letak' => 'Toko Online → Retur Online',
        'ringkasan' => 'Pengajuan retur member atas pesanan gudang yang sudah diterima: lihat foto, setujui (barang kembali, dana/tagihan) atau tolak.',
        'izin' => ['toko_online.pesanan'],
        'route_names' => ['toko-online.retur.*'],
        'terkait' => ['pesanan-online', 'catatan-ro', 'pengaturan-toko-online'],
        'kata_kunci' => 'retur online pengembalian barang refund member tukar barang rusak setujui tolak retur potong tagihan',
        'konten' => <<<'MD'
## Fungsi
Memutuskan retur yang diajukan member dari **Pesanan Saya › Ajukan retur** di aplikasi member.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Proses Pesanan Online**. Angka merah = retur **diajukan**.
- Member hanya bisa mengajukan retur untuk pesanan **Diterima**, dalam **batas hari retur** (Pengaturan Toko Online, bawaan 7; 0 = tanpa batas), dan barangnya masih ada di **stok member** pembeli.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Uang | RO |
|---|---|---|---|
| **Setujui retur** (= barang sudah kembali) | Stok member pembeli berkurang, stok gudang bertambah (HPP asal) | Belum lunas → **potong tagihan**; sudah lunas → pilih **Transfer/Tunai** (Penjualan negatif dari kas itu) | **−RO** dikirim ke aplikasi member bila RO pesanan sudah tercatat |
| **Tolak** | — | — | — (alasan wajib) |

## Jangan
- Jangan menyetujui sebelum barang benar-benar kembali — stok gudang langsung bertambah.

## Menu yang mirip & bedanya
- Tab **Antar member** — retur ke upline diputuskan upline di Toko Saya (aplikasi member), bukan oleh admin.
- **Retur Cabang** (Stok & Gudang) — retur dari toko BABA ke gudang, bukan dari member.
MD,
    ],

    [
        'slug' => 'produk-online', 'judul' => 'Produk Online', 'kelompok' => 'Toko Online', 'urutan' => 30,
        'letak' => 'Toko Online → Produk Online',
        'ringkasan' => 'Daftar parfum botol gudang yang dijual ke member di menu Belanja, beserta harga tetap, harga gudang, harga per jabatan, dan harga khusus akun.',
        'izin' => ['toko_online.kelola'],
        'route_names' => ['toko-online.produk.*', 'toko-online.cari-akun'],
        'terkait' => ['pesanan-online', 'pengaturan-toko-online', 'cara-mengatur-harga'],
        'kata_kunci' => 'produk online katalog belanja member harga member harga jabatan harga distributor harga reseller harga khusus akun foto produk',
        'konten' => <<<'MD'
## Fungsi
Menentukan parfum apa saja yang tampil di **Belanja** aplikasi member dan berapa harganya untuk setiap member.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Toko Online** (bawaan Admin Pusat).
- Barang yang bisa dipilih = **parfum botol gudang** (hasil Produksi Parfum / Bahan Baku). Satu barang = satu produk online.
- Daftar **jabatan** diambil langsung dari aplikasi member; harga khusus akun dicari lewat nama / No ID / No Member (BP-xxxxx). Kalau aplikasi member tidak bisa dihubungi, muncul pesan dan harga yang sudah tersimpan tetap aman.

## Urutan harga (HargaOnlineService)
**Harga khusus akun → harga per jabatan → harga gudang** (hanya bila beli ke gudang) **→ harga tetap**. Produk tanpa harga untuk seorang member **tidak tampil** bagi member itu.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Simpan | Katalog member berubah untuk belanja berikutnya. Pesanan lama memakai harga saat dipesan |
| Isian harga dikosongkan | Harga itu dihapus |
| Nonaktifkan produk | Tidak tampil di katalog; pesanan berjalan tetap diproses |

Semua perubahan tercatat di Log Aktivitas. Tidak mengubah stok.

## Menu yang mirip & bedanya
- **Produk Jual / Produk Parfum Toko** — harga jual di POS toko, bukan harga untuk member.
MD,
    ],

    [
        'slug' => 'stok-member', 'judul' => 'Stok Member', 'kelompok' => 'Toko Online', 'urutan' => 40,
        'letak' => 'Toko Online → Stok Member',
        'ringkasan' => 'Stok parfum yang dipegang setiap member (dari belanja), riwayat keluar-masuknya, dan rekap stok opname member per member / per tim.',
        'izin' => ['toko_online.kelola'],
        'route_names' => ['toko-online.stok-member.*'],
        'terkait' => ['pesanan-online', 'retur-online', 'stok-opname', 'laporan-belanja-online'],
        'kata_kunci' => 'stok member stok saya toko saya persediaan member mutasi stok member opname member kerugian member per tim',
        'konten' => <<<'MD'
## Fungsi
Memantau barang yang dipegang member. Stok member adalah milik member sendiri — **bukan** persediaan BABA dan tidak masuk Neraca BABA.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Toko Online**. Rekap **per tim** butuh sambungan ke aplikasi member (struktur upline); kalau gagal, muncul pesan dan rekap per member tetap tampil.

## Dari mana stok member berubah
| Kejadian | Stok member |
|---|---|
| Pesanan **diterima** (dari gudang atau upline) | Pembeli + |
| Member mengirim pesanan ke downline (Toko Saya) | Penjual − |
| Retur disetujui | Pembeli −, penjual + |
| Kasir Toko Saya | − (tidak menambah RO) |
| **Stok opname member** (Toko Saya › Stok Saya) | Hanya berkurang, tanpa persetujuan; fisik lebih → member menghubungi admin gudang |

Halaman ini hanya melihat; tidak ada tombol yang mengubah stok member.

## Menu yang mirip & bedanya
- **Stok Barang** (Stok & Gudang) — stok gudang & toko BABA.
- **Stok Opname** (Stok & Gudang) — opname lokasi BABA dengan persetujuan; opname member tanpa persetujuan.
MD,
    ],

    [
        'slug' => 'catatan-ro', 'judul' => 'Catatan RO', 'kelompok' => 'Toko Online', 'urutan' => 50,
        'letak' => 'Toko Online → Catatan RO',
        'ringkasan' => 'Buku RO (jumlah botol) dari belanja online yang dikirim ke aplikasi member sebagai dokumen RO, beserta status kirimnya.',
        'izin' => ['toko_online.kelola'],
        'route_names' => ['toko-online.ro.*'],
        'terkait' => ['pesanan-online', 'retur-online', 'pengaturan-umum', 'jadwal-otomatis', 'cara-ro-belum-masuk'],
        'kata_kunci' => 'catatan ro repeat order ro member botol kirim ro gagal kirim ulang dokumen ro input data aplikasi member mode ro',
        'konten' => <<<'MD'
## Fungsi
Setiap pesanan online yang memenuhi **Mode RO** menghasilkan catatan **RO = jumlah botol** (bersih setelah retur) untuk pembelinya. Catatan ini dikirim ke aplikasi member dan menjadi **dokumen RO biasa** di Input Data (bisa langsung approved atau ikut alur Document Belum Approve, tergantung Mode RO).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Toko Online**. Angka merah = catatan **gagal** terkirim.
- **Mode RO** diatur di **Pengaturan Umum → RO dari Belanja Online**: saat lunas / lunas + disetujui / hanya saat diterima-disetujui.
- Pengiriman dilakukan tugas **Kirim RO ke aplikasi member** di **Jadwal Otomatis** (tiap menit, selalu hidup, tetap jalan walau langganan baca-saja). Tanpa cron di hosting, RO tidak terkirim.

## Apa yang terjadi
| Kejadian | Catatan RO |
|---|---|
| Pesanan lunas / diterima (sesuai Mode RO) | +RO pembeli |
| Retur disetujui | −RO (bila +RO sudah ada) |
| Admin membatalkan pesanan ber-RO | −RO |
| **Kirim ulang** | Mengirim lagi catatan menunggu/gagal sekarang |

Dokumen RO dari ERP **tidak bisa diubah/dihapus** di aplikasi member. Penjualan POS dan kasir member **tidak** menambah RO.

## Menu yang mirip & bedanya
- **Laporan Belanja Online → RO per Member / per Tim** — rekap buku RO ini.
MD,
    ],

    [
        'slug' => 'pengaturan-toko-online', 'judul' => 'Pengaturan Toko Online', 'kelompok' => 'Toko Online', 'urutan' => 60,
        'letak' => 'Toko Online → Pengaturan Toko Online',
        'ringkasan' => 'Status toko (tutup / uji coba / buka), waktu otomatis, batas retur, rekening gudang, aturan tagihan, dan sumber barang (gudang / upline).',
        'izin' => ['toko_online.kelola'],
        'route_names' => ['toko-online.pengaturan', 'toko-online.pengaturan.*'],
        'terkait' => ['pesanan-online', 'produk-online', 'paket-langganan', 'cara-membuka-toko-online'],
        'kata_kunci' => 'pengaturan toko online buka toko tutup toko uji coba saklar segera hadir rekening gudang tagihan batas tagihan tempo sumber barang belanja ke upline batas retur terima otomatis batas bayar',
        'konten' => <<<'MD'
## Fungsi
Semua aturan belanja member di satu halaman.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Toko Online**. Perubahan dicatat di Log Aktivitas.

## Isi & akibatnya
| Bagian | Akibat |
|---|---|
| **Status toko online untuk member** | *Tutup* (bawaan) → menu Belanja di aplikasi member "Segera hadir"; *Uji coba* → hanya akun yang dipilih; *Buka* → semua member. Pesanan/retur yang sudah berjalan tetap bisa diproses walau tutup |
| Waktu otomatis | Hari terima otomatis (7) & jam batas bayar (48); 0 = tidak otomatis. Dijalankan tugas **Pesanan online otomatis** di Jadwal Otomatis (bawaan **mati**) |
| Batas pengajuan retur | Hari sejak diterima (7; 0 = tanpa batas) |
| Rekening | Rekening transfer gudang yang tampil di Pesanan Saya |
| Aturan tagihan | Semua / per jabatan / per akun: boleh bayar belakangan, batas nominal, tempo hari. Aturan akun → jabatan → semua |
| Sumber barang | Semua / jabatan / akun: boleh beli ke **gudang** dan/atau **upline**. Tanpa aturan = gudang saja |

⚠️ Kalau modul **Toko Online** tidak termasuk paket, atau langganan aplikasi dalam **mode baca-saja**, member tidak bisa belanja walau status *Buka*.

## Menu yang mirip & bedanya
- **Pengaturan Umum → RO dari Belanja Online** — kapan RO dicatat.
MD,
    ],

    [
        'slug' => 'laporan-belanja-online', 'judul' => 'Laporan Belanja Online', 'kelompok' => 'Toko Online', 'urutan' => 70,
        'letak' => 'Toko Online → Laporan Belanja Online',
        'ringkasan' => 'Penjualan online gudang per produk / cabang / jabatan / tim, RO per member / tim, dan tagihan member. Bisa ke Excel.',
        'izin' => ['toko_online.laporan'],
        'route_names' => ['toko-online.laporan'],
        'terkait' => ['pesanan-online', 'catatan-ro', 'stok-member'],
        'kata_kunci' => 'laporan belanja online omzet online laba online margin per produk per jabatan per tim upline ro per member ro per tim tagihan member piutang jatuh tempo',
        'konten' => <<<'MD'
## Fungsi
Melihat hasil belanja online member.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Laporan Belanja Online** (bawaan Admin Pusat).
- **Per Tim** memakai struktur upline dari aplikasi member (level jabatan dipilih). Kalau aplikasi member tidak bisa dihubungi, muncul pesan kuning.

## Yang dihitung
- **Penjualan** = pesanan gudang yang **sudah keluar** dalam periode, bersih setelah retur: omzet, HPP, laba, margin, ongkir.
- **RO** = dari buku Catatan RO.
- **Tagihan member** = sisa tagihan belum lunas, jatuh tempo, yang terlambat merah. Tagihan **bukan pendapatan** sampai dibayar.
- Pesanan antar member (ke upline) **tidak** masuk penjualan BABA.

## Hasil
Hanya menampilkan data. Bisa diekspor ke Excel. Tanggal salah → bulan ini.
MD,
    ],
];
