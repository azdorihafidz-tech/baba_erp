<?php

namespace Database\Seeders;

use App\Models\Panduan;
use Illuminate\Database\Seeder;

/**
 * Mengisi konten panduan helper dari stub "Belum diisi" menjadi panduan nyata.
 * Dijalankan setelah PanduanStubSeeder.
 */
class PanduanKontenSeeder extends Seeder
{
    public function run(): void
    {
        $this->kategori1Penjualan();
        $this->kategori2Antrian();
        $this->kategori3Stok();
        $this->kategori4Pembelian();
        $this->kategori5Keuangan();
        $this->kategori6Sdm();
        $this->kategori7Aset();
        $this->kategori8Laporan();
        $this->kategori9Lainnya();
        $this->posUpdate();
        $this->masterJenisOlahanUpdate();
        $this->masterResepBumbuUpdate();
        $this->laporanKonsumsiBahanUpdate();
        $this->laporanLabaRugiUpdate();
        $this->poDashboardUpdate();
        $this->cleanupToolUpdate();
        $this->dataTerhapusUpdate();
        $this->akuntansiPanduanUpdate();
        $this->laporanNeracaUpdate();
        $this->laporanLabaRugiFormalUpdate();
        $this->laporanBepOtomatisUpdate();
        $this->dashboardAnalyticsUpdate();
        $this->bukuBesarUpdate();
        $this->laporanEksekutifUpdate();
        $this->simulatorBepUpdate();
        $this->laporanJamRamaiUpdate();
        $this->searchBoxUpdate();
        $this->transferAntarKasUpdate();
        $this->programLoyaltyUpdate();
        $this->itemVarianUpdate();
        // Tahap 7 D'mentai (2026-09-16) — Backfill Panduan untuk fitur Tahap
        // 1-6 yang kelewat (CLAUDE.md 3.6 wajib Permission+Role+Panduan+Tooltip,
        // permission/role sudah lengkap dari awal, panduan/tooltip yang lolos).
        $this->masterBahanBakuUpdate();
        $this->masterProdukJualUpdate();
        $this->setoranKasirUpdate();
        $this->dashboardOwnerUpdate();
        $this->laporanSetoranKasirUpdate();
        $this->pengaturanUmumUpdate();
    }

    // =========================================================================
    // KATEGORI 1 — PENJUALAN
    // =========================================================================

    private function kategori1Penjualan(): void
    {
        Panduan::where('slug', 'riwayat-order')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat dan mengelola seluruh riwayat transaksi penjualan yang sudah dicatat di POS.

## Langkah-langkah

1. Buka menu **Penjualan → Riwayat Penjualan** dari sidebar
2. Gunakan filter untuk mempersempit pencarian:
   - **Cari nomor / pelanggan** — ketik nomor order atau nama pelanggan
   - **Semua Status** — filter berdasarkan status order (Menunggu, Proses, Selesai, Dibatalkan)
   - **Dari / Sampai** — filter rentang tanggal transaksi
3. Klik ikon **kaca pembesar** atau tekan Enter untuk menerapkan filter
4. Di tabel hasil, tersedia aksi per baris:
   - **Ikon mata** (Detail) — lihat detail lengkap order & item
   - **Ikon printer** (Struk) — cetak ulang struk pembayaran
   - **Ikon pensil** (Edit) — ubah data order *(hanya tersedia selama status belum Selesai/Dibatalkan)*
   - **Ikon tempat sampah** (Hapus) — hapus order *(membutuhkan konfirmasi, pengaruhi data keuangan)*
5. Untuk membuat transaksi baru, klik tombol **Buka POS**

## Catatan Penting

- Order dengan status **Selesai** atau **Dibatalkan** tidak bisa diedit kecuali oleh Owner
- Menghapus order akan **menghapus entri keuangan terkait secara otomatis** (cascade)
- Filter tanggal hanya berlaku untuk range yang diisi — bisa diisi satu sisi saja

## Troubleshooting

- **Order tidak muncul?** Pastikan filter Status diset ke "Semua", dan cek filter tanggal tidak terlalu sempit
- **Tombol Edit tidak ada?** Order sudah berstatus Selesai atau Dibatalkan; hubungi Owner untuk koreksi
- **Struk tidak muncul?** Klik tombol Struk lagi — bisa jadi browser memblokir pop-up baru
MARKDOWN]);

        Panduan::where('slug', 'pelanggan')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola data pelanggan yang digunakan dalam transaksi POS — tambah, lihat detail, edit, dan hapus data pelanggan.

## Langkah-langkah

1. Buka menu **Penjualan → Pelanggan** dari sidebar
2. Cari pelanggan yang diinginkan menggunakan kolom **Cari nama / kode / telepon...**
3. Klik tombol **Cari** atau tekan Enter; klik **Reset** untuk hapus filter
4. Dari tabel daftar, gunakan ikon aksi di kolom kanan:
   - **Ikon mata** (Detail) — lihat profil pelanggan & histori order
   - **Ikon pensil** (Edit) — ubah nama, telepon, kota, dll.
   - **Ikon tempat sampah** (Hapus) — hapus pelanggan *(membutuhkan konfirmasi)*
5. Untuk menambah pelanggan baru, klik tombol **Tambah Pelanggan** di pojok kanan atas
6. Isi form: Nama Pelanggan, Kode (opsional), Telepon, Kota, Keterangan

## Catatan Penting

- Kolom **Jml Order** menunjukkan total transaksi pelanggan — berguna untuk kenali pelanggan setia
- **Menghapus pelanggan** akan menghapus seluruh riwayat order dan detail item terkait (cascade) — **tidak bisa dipulihkan**
- Pelanggan bisa langsung ditambahkan dari layar POS tanpa perlu masuk menu ini terlebih dahulu
- Kode pelanggan wajib unik jika diisi

## Troubleshooting

- **Tidak bisa hapus pelanggan?** Cek apakah masih ada order aktif (belum selesai) atas nama pelanggan tersebut
- **Data duplikat?** Gunakan fitur Edit untuk gabungkan — sistem tidak auto-merge pelanggan
MARKDOWN]);
    }

    // =========================================================================
    // KATEGORI 2 — ANTRIAN
    // =========================================================================

    private function kategori2Antrian(): void
    {
        Panduan::where('slug', 'antrian-cek')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Memantau status antrian pelanggan secara real-time — mengetahui pesanan mana yang sudah selesai dan siap diambil.

## Langkah-langkah

1. Buka menu **Antrian → Cek Antrian** dari sidebar
2. Halaman otomatis menampilkan semua order aktif hari ini
3. Gunakan filter untuk mencari pesanan tertentu:
   - **Cari Nomor / Nama / Order** — ketik nomor antrian atau nama pelanggan
   - **Tanggal** — cari antrian dari tanggal tertentu
   - Klik **Cari** atau tekan Enter
4. Klik **Tampilkan Semua Hari Ini** untuk kembali ke tampilan default (semua order hari ini)
5. Status antrian ditampilkan dengan warna berbeda:
   - **Menunggu** — belum mulai dikerjakan
   - **Dikerjakan** — sedang dalam proses produksi
   - **Selesai** — produksi selesai, siap diambil
   - **Di Rak** — sudah selesai dan disimpan di rak
   - **Diambil** — sudah diserahkan ke pelanggan
6. Untuk order berstatus **Selesai** atau **Di Rak**, klik tombol **Diambil** setelah pelanggan mengambil pesanannya
7. Klik ikon **Cetak** untuk cetak struk, ikon **Detail** untuk lihat isi pesanan

## Catatan Penting

- Halaman ini bisa dibuka di TV/monitor besar sebagai papan antrian pelanggan
- Pencarian bersifat real-time dengan jeda 500ms — tidak perlu klik Cari setiap saat
- Tombol **Diambil** hanya muncul untuk pesanan berstatus Selesai atau Di Rak
- Untuk kelola proses produksi, gunakan menu **Kelola Antrian** (khusus Operator/Manajer)

## Troubleshooting

- **Pesanan tidak muncul?** Cek filter tanggal — default hanya tampil hari ini; klik "Tampilkan Semua Hari Ini"
- **Status tidak update?** Refresh halaman — halaman ini tidak auto-refresh, perlu reload manual
MARKDOWN]);

        Panduan::where('slug', 'antrian-operator')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola proses produksi antrian dari pesanan masuk hingga diserahkan ke pelanggan — khusus untuk Operator Produksi dan Manajer Cabang.

## Langkah-langkah

1. Buka menu **Antrian → Kelola Antrian** dari sidebar
2. Halaman memiliki 3 tab:
   - **Aktif Hari Ini** — pesanan yang masih dalam proses (auto-refresh tiap 10 detik)
   - **Sudah Diambil Hari Ini** — pesanan yang sudah diserahkan ke pelanggan
   - **Semua / History** — seluruh pesanan dengan filter tanggal
3. **Mulai Kerjakan** pesanan (status Menunggu):
   - Klik tombol **Mulai Kerjakan**
   - Pilih **Operator / Karyawan** yang mengerjakan dari dropdown
   - Klik **Mulai Kerjakan** di modal untuk konfirmasi
4. **Tandai Selesai** saat produksi selesai:
   - Klik tombol **Selesai** di baris pesanan yang berstatus Dikerjakan
5. **Simpan di Rak** jika pelanggan belum datang:
   - Klik tombol **Simpan di Rak**
   - Isi **Nomor / Lokasi Rak** (opsional, misal "Rak A-3")
   - Klik **Simpan di Rak** di modal
6. **Diambil** saat pelanggan datang:
   - Klik tombol **Diambil** atau **Sudah Diambil**
7. Gunakan **Cetak Ulang** untuk cetak ulang struk
8. Klik **Refresh** untuk perbarui tampilan manual, atau **Display TV** untuk mode papan antrian

## Catatan Penting

- Tab **Aktif Hari Ini** auto-refresh setiap 10 detik — tidak perlu refresh manual
- Urutan kerja: **Menunggu → Dikerjakan → Selesai → Di Rak (opsional) → Diambil**
- Tidak bisa lewati tahap — tidak bisa langsung dari Menunggu ke Selesai
- Badge ringkasan (Menunggu/Dikerjakan/Selesai/Di Rak) tampil di atas tabel sebagai ringkasan cepat

## Troubleshooting

- **Tombol "Mulai Kerjakan" tidak ada?** Pesanan belum berstatus Menunggu, atau sudah dikerjakan operator lain
- **Data pesanan tidak muncul?** Cek di tab "Semua / History" dengan filter tanggal yang sesuai
- **Status tidak berubah setelah aksi?** Tunggu 10 detik (auto-refresh) atau klik tombol **Refresh**
MARKDOWN]);
    }

    // =========================================================================
    // KATEGORI 3 — STOK
    // =========================================================================

    private function kategori3Stok(): void
    {
        Panduan::where('slug', 'dashboard-stok')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Memantau kondisi stok seluruh item secara menyeluruh — alert kritis, nilai stok FIFO, batch per item, dan insight otomatis.

## Langkah-langkah

1. Buka menu **Stok → Dashboard Stok** dari sidebar
2. Lihat 3 kartu ringkasan di bagian atas:
   - **Total Item** — jumlah item yang punya stok aktif
   - **Stok Kritis / Habis** — jumlah item yang perlu segera diisi
   - **Total Nilai Stok FIFO** — estimasi nilai stok berdasarkan harga beli per batch
3. Gunakan filter untuk mempersempit tampilan:
   - **Pilih Cabang** (Owner) — lihat stok per lokasi atau semua cabang
   - **Pilih Tipe** — Semua Tipe / Bahan Baku / Kemasan / Produk Jadi — semua widget di halaman ini (alert, nilai stok, 4 Smart Insights) ikut ter-filter
   - **Cari item...** — ketik nama item
   - **Semua Status** — filter: Habis / Kritis / Menipis / Aman
4. Di tabel item, klik baris atau ikon expand untuk lihat **detail batch FIFO**:
   - Batch tertua ditandai **↑ NEXT** — ini yang akan habis pertama kali saat ada penjualan
5. Scroll ke bawah untuk melihat 4 widget **Smart Insights**:
   - **Stok Lama** — item dengan batch masuk >30 hari lalu
   - **Top 5 Paling Sering Dipakai** — item paling banyak keluar dalam 90 hari
   - **Trend Harga Beli** — item dengan perubahan harga beli >5% dalam 30 hari
   - **Stok Tidak Bergerak** — item tidak ada keluar >30 hari
6. Klik **Lihat Semua →** di setiap widget untuk halaman detail dengan filter & pagination

## Catatan Penting

- Status warna: **Merah** = Habis (qty=0), **Kuning** = Kritis (≤ qty_minimum), **Biru** = Menipis, **Hijau** = Aman
- Nilai stok FIFO dihitung dari harga beli masing-masing batch yang masih ada
- Dashboard ini read-only — untuk ubah stok gunakan menu Adjustment atau Transfer

## Troubleshooting

- **Nilai stok 0 padahal ada barang?** Kemungkinan harga beli batch belum terisi; cek saat PO diterima
- **Widget "Stok Tidak Bergerak" penuh item?** Normal jika ada item bahan baku yang jarang terpakai; review berkala
MARKDOWN]);

        Panduan::where('slug', 'stok-barang')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat posisi stok terkini per item per lokasi (cabang/gudang), dengan akses cepat ke Kartu Stok dan Adjustment.

## Langkah-langkah

1. Buka menu **Stok → Stok Barang** dari sidebar
2. Lihat 3 kartu ringkasan di atas tabel:
   - **Item Berstock** — total item yang punya stok > 0
   - **Di Bawah Minimum** — item yang stoknya sudah di bawah batas minimum
   - **Nilai Stok** — estimasi total nilai stok
3. Gunakan filter:
   - **Cari Item** — ketik nama atau kode item
   - **Kategori** — filter berdasarkan kategori item
   - **Tipe** — pilih: Bahan Baku / Produk Jadi / Kemasan / Lainnya
   - **Lokasi** (Owner) — filter per cabang atau gudang pusat
   - **Stok Kritis** — centang "Di Bawah Minimum" untuk tampilkan item kritis saja
   - Klik **Filter**, klik **Reset** untuk hapus filter
4. Baris yang ditandai **merah** artinya qty saat ini ≤ qty minimum
5. Di setiap baris tersedia aksi:
   - **Ikon jurnal** (Kartu Stok) — lihat riwayat keluar-masuk item di lokasi tersebut
   - **Ikon sliders** (Adjustment) — lakukan penyesuaian stok langsung untuk item ini
   - **Ikon target** (Set Minimum) — atur threshold stok minimum khusus untuk item ini di lokasi ini (perlu izin `stok.minimum.set`)
   - **Ikon tempat sampah merah** (Hapus Stok) — reset qty ke 0 total, terpisah dari Adjustment (perlu izin `stok.hapus.reset`, default hanya Owner)

## Tombol Hapus Stok — Beda dari Adjustment

Dipakai untuk **reset total ke 0**, bukan koreksi sebagian. Cocok untuk:
- Bersihkan data test/percobaan sebelum go-live
- Reset stok yang datanya sudah kacau dan lebih gampang mulai dari nol
- Fresh start untuk item baru yang stok awalnya salah input

**Efeknya (baca dulu sebelum klik):**
- Qty langsung jadi **0**
- Semua batch stok item ini di lokasi itu **dihapus** (soft-delete — masih bisa dipulihkan lewat menu **Data Terhapus** kalau salah klik, tapi qty stok yang sudah 0 **tidak otomatis kembali**, harus di-adjust manual lagi kalau mau)
- Riwayat pergerakan stok **lama** (pembelian, penjualan, dst) **tidak dihapus** — cuma ditambah 1 catatan baru yang menandai kapan reset dilakukan
- **TIDAK** membuat transaksi keuangan apapun
- Wajib **ketik ulang nama item persis** sebagai konfirmasi sebelum tombol "Konfirmasi Hapus" aktif
- Hanya Owner secara default — role lain butuh izin `stok.hapus.reset` yang di-centang manual oleh Owner di Pengaturan → Role & Hak Akses

**Jangan pakai untuk koreksi normal** (susut, salah hitung dikit, dll) — itu tetap pakai **Adjustment** biasa supaya riwayat & alasannya tercatat rapi.

## Catatan Penting

- Badge lokasi **Gudang Pusat** tampil kuning, **Cabang** tampil biru — bedakan sumber stok
- Stok minimum ada di **2 level**: global (di master Item, berlaku sebagai default awal) dan per lokasi (bisa di-override lewat tombol **Set Minimum** di baris ini). Yang dipakai untuk alert & notifikasi otomatis adalah **threshold per lokasi**
- Stok baru di lokasi manapun otomatis mewarisi qty minimum dari master Item saat pertama kali dibuat — kalau perlu beda untuk lokasi tertentu, pakai **Set Minimum**
- Notifikasi otomatis (bell icon) dikirim ke Manajer/Admin Gudang saat stok di lokasi tersebut mencapai atau di bawah minimum
- Stok di halaman ini adalah **stok fisik saat ini** — angka real-time, bukan proyeksi

## Troubleshooting

- **Item tidak muncul?** Mungkin tipe/kategori berbeda dari filter yang dipilih — klik Reset
- **Qty 0 tapi item masih ada?** Stok mungkin sudah dipindah ke lokasi lain; cek filter Lokasi
MARKDOWN]);

        Panduan::where('slug', 'stok-adjustment')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Menyesuaikan stok fisik hasil hitung lapangan (stock opname) dengan data sistem — menambah atau mengurangi stok beserta pencatatan alasan dan dampak keuangan.

## Langkah-langkah

1. Buka menu **Stok → Adjustment Stok** dari sidebar
2. Pilih **Lokasi** (cabang atau gudang pusat) dari dropdown
3. Pilih **Item** yang akan disesuaikan (dikelompokkan per tipe)
4. Sistem otomatis menampilkan **Stok Saat Ini** (dari data sistem)
5. Isi **Qty Stok Fisik (Hasil Hitung)** — jumlah yang kamu hitung secara fisik
6. Sistem otomatis hitung selisih:
   - **Selisih TURUN** (stok fisik < sistem): pilih metode FIFO:
     - **FIFO Otomatis** — sistem kurangi batch terlama secara otomatis
     - **Manual** — pilih sendiri batch mana yang dikurangi dan berapa
   - **Selisih NAIK** (stok fisik > sistem): pilih metode penambahan:
     - **Buat Batch Baru** — tambahkan sebagai batch baru (isi Harga Beli per Unit, opsional)
     - **Tambah ke Batch Existing** — pilih batch yang sudah ada dari dropdown
7. Pilih **Alasan** — lihat tabel "Alasan & Dampak Keuangan" di bawah untuk tahu kapan pakai yang mana
8. Kalau alasan **"Lainnya"** dipilih, muncul checkbox **"Catat sebagai biaya rugi?"** — centang kalau memang mau adjustment ini masuk ke Keuangan (default TIDAK dicentang)
9. Isi **Catatan** (opsional) untuk keterangan tambahan
10. Klik **Preview & Simpan** — review ringkasan di modal konfirmasi (ada info jelas apakah adjustment ini akan masuk Keuangan atau tidak)
11. Klik **Konfirmasi & Simpan** untuk finalisasi

## Alasan & Dampak Keuangan

| Alasan | Kapan Dipakai | Masuk Keuangan? |
|---|---|---|
| **Susut** (evaporasi/penyusutan alami) | Bahan menyusut wajar karena proses (mis. daging kehilangan berat air saat disimpan) | ✅ Ya — Beban Kerugian Stok |
| **Rusak / Kadaluarsa** | Bahan/produk rusak fisik atau lewat tanggal pakai | ✅ Ya — Beban Kerugian Stok |
| **Hilang / Dicuri** | Barang hilang tanpa penjelasan jelas | ✅ Ya — Beban Kerugian Stok |
| **Salah Hitung Sebelumnya** | Kamu sadar input qty sebelumnya keliru (typo, salah baca) — stok fisik **sebenarnya tidak pernah berubah** | ❌ Tidak — murni perbaikan data |
| **Audit Berkala (Stock Opname)** | Hasil hitung fisik rutin berbeda dari sistem, dan itu murni rekonsiliasi data | ❌ Tidak — murni perbaikan data |
| **Lainnya** | Situasi di luar 5 kategori di atas | Kamu pilih sendiri lewat checkbox (default: **Tidak**) |

## Catatan Penting

- **Susut / Rusak / Hilang** → adjustment otomatis membuat entri keuangan (**Beban Kerugian Stok** untuk selisih turun, **Koreksi Stok Masuk** untuk selisih naik)
- **Salah Hitung Sebelumnya / Audit Berkala** → **TIDAK** membuat entri keuangan apapun — cuma catat penyesuaian stok fisik + riwayat pergerakan
- **Lainnya** → ikut pilihan checkbox "Catat sebagai biaya rugi?" (default tidak masuk keuangan)
- Butuh permission `stok.adjustment` — Kasir tidak bisa melakukan adjustment
- Seluruh history adjustment tercatat di Kartu Stok dan riwayat pergerakan stok — termasuk yang tidak masuk Keuangan

## Troubleshooting

- **Tombol "Preview & Simpan" masih abu-abu (disabled)?** Pastikan Lokasi, Item, dan Qty sudah terisi semua
- **Pilihan batch tidak muncul?** Item belum punya batch FIFO — belum pernah ada pembelian terdaftar; pilih "Buat Batch Baru"
- **Tidak ada permission?** Hubungi Manajer atau Owner untuk akses adjustment stok
- **Mau reset stok ke 0 total (bukan koreksi sebagian)?** Pakai tombol **Hapus Stok** di menu Stok Barang, bukan Adjustment — lihat panduan "Cara Pakai Menu Stok Barang"
MARKDOWN]);

        Panduan::where('slug', 'permintaan-stok')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengajukan permintaan bahan baku atau barang dari gudang pusat ke cabang — alur normal distribusi stok tanpa harus beli ke vendor langsung.

## Langkah-langkah

1. Buka menu **Stok → Permintaan Stok** dari sidebar
2. Lihat 4 kartu status: **Pending**, **Disetujui**, **Dikirim**, **Diterima**
3. Untuk mengajukan permintaan baru, klik **Buat Permintaan**
4. Isi form permintaan:
   - Tambah item yang dibutuhkan beserta jumlah yang diminta
   - Isi keterangan/catatan jika perlu
   - Klik Simpan
5. Permintaan masuk status **Pending** — menunggu persetujuan Gudang Pusat
6. Pantau status melalui tab di bagian atas tabel: **Semua / Pending / Disetujui / Dikirim / Diterima / Ditolak / Dibatalkan**
7. Klik **ikon mata** (Lihat Detail) untuk melihat isi permintaan dan perkembangan statusnya
8. Jika permintaan belum disetujui atau masih pending, bisa dibatalkan via **ikon x-circle** (Batalkan)
9. Permintaan yang sudah **Ditolak** atau **Dibatalkan** bisa dihapus permanen oleh Owner

## Catatan Penting

- Alur status: **Pending → Disetujui → Dikirim → Diterima** (atau Ditolak/Dibatalkan)
- Stok cabang **bertambah setelah status Diterima** — bukan saat Disetujui
- Notifikasi dikirim ke Admin Gudang saat permintaan baru masuk
- Permintaan yang sudah **Dikirim** tidak bisa dibatalkan — hubungi Admin Gudang

## Troubleshooting

- **Tombol "Buat Permintaan" tidak muncul?** Cek permission; Kasir tidak bisa buat permintaan stok
- **Status stuck di Pending?** Hubungi Admin Gudang Pusat untuk review permintaan
- **Stok tidak bertambah walau sudah Diterima?** Refresh halaman atau cek di menu Stok Barang
MARKDOWN]);

        Panduan::where('slug', 'transfer-stok')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola pengiriman dan penerimaan stok antar lokasi (gudang pusat ke cabang, atau antar cabang) — bisa dibuat dari permintaan stok atau langsung.

## Langkah-langkah

1. Buka menu **Stok → Transfer Stok** dari sidebar
2. Gunakan tab untuk filter status: **Semua / Draft / Dikirim / Diterima / Sebagian / Dibatalkan**
3. Untuk membuat transfer baru (Owner / Admin Gudang), klik **Buat Transfer**:
   - Pilih **Dari Lokasi** (asal stok) dan **Ke Lokasi** (tujuan)
   - Tambahkan item dan jumlah yang akan dikirim
   - Simpan sebagai Draft
4. Ubah status ke **Dikirim** saat barang fisik dikirim:
   - Stok lokasi asal **langsung berkurang** saat status Dikirim
5. Cabang tujuan **konfirmasi penerimaan** dari halaman Detail:
   - Isi qty yang benar-benar diterima (bisa berbeda dari qty kirim)
   - Status berubah ke **Diterima** atau **Diterima Sebagian**
   - Stok tujuan **bertambah** setelah konfirmasi penerimaan
6. Klik **ikon mata** (Lihat Detail) untuk lihat isi transfer dan update status
7. Untuk batalkan: klik **ikon x-circle** (tersedia di status Draft atau Dikirim untuk Owner/Admin Gudang)

## Catatan Penting

- **Stok asal berkurang saat Dikirim** — pastikan qty di sistem sudah benar sebelum ubah ke Dikirim
- **Stok tujuan bertambah saat Diterima** — kalau diterima sebagian, sisa qty tetap di tujuan
- Membatalkan transfer berstatus **Dikirim** akan mengembalikan stok ke lokasi asal
- Hanya Owner dan Admin Gudang yang bisa Buat Transfer

## Troubleshooting

- **Tombol "Buat Transfer" tidak muncul?** Role tidak memiliki akses; hubungi Owner/Admin Gudang
- **Stok tidak berkurang setelah Dikirim?** Refresh halaman Stok Barang
- **Tidak bisa Batalkan?** Transfer sudah berstatus Diterima — tidak bisa dibatalkan; buat adjustment manual
MARKDOWN]);

        Panduan::where('slug', 'master-barang')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola data master item/barang yang digunakan di seluruh modul — stok, pembelian, penjualan, dan produksi.

## Langkah-langkah

1. Buka menu **Master Data → Barang / Item** dari sidebar
2. Lihat 4 kartu ringkasan: **Total Item**, **Bahan Baku** (count), **Produk Jadi** (count), **Nonaktif** (count)
3. Gunakan filter untuk mencari item:
   - **Cari** — ketik nama atau kode item
   - **Tipe** — Bahan Baku / Produk Jadi / Kemasan / Lainnya
   - **Kategori** — pilih kategori item
   - **Status** — Aktif / Nonaktif
   - Klik **Filter** untuk terapkan, **Reset** untuk hapus
4. Klik **Tambah Item** untuk menambah item baru:
   - Isi: Kode Item, Nama Item, Kategori, Tipe, Satuan, Harga Beli, Harga Jual, Stok Minimum
   - Centang **Aktif** agar item bisa digunakan
5. Di tabel, tersedia aksi per item:
   - **Ikon mata** (Lihat Detail) — lihat detail item + stok per lokasi + riwayat pergerakan
   - **Ikon pensil** (Edit) — ubah data item
   - **Ikon tempat sampah** (Hapus) — hapus item *(akan menghapus stok semua lokasi, detail order, dan detail PO terkait)*

## Catatan Penting

- **Hapus item bersifat cascade** — stok semua lokasi, baris di order, dan baris di PO ikut terhapus
- Item yang **Nonaktif** tidak muncul di POS dan dropdown produksi — jangan hapus, cukup nonaktifkan
- **Harga Beli Terakhir** di tabel di-update otomatis setiap kali ada PO diterima
- Butuh permission `item.create`, `item.edit`, `item.delete` — Kasir dan Operator tidak bisa kelola item

## Troubleshooting

- **Item tidak muncul di POS?** Pastikan item berstatus Aktif dan tipenya Produk Jadi
- **Tidak bisa hapus item?** Mungkin masih ada stok di salah satu lokasi atau ada order aktif; nonaktifkan saja
- **Kode item sudah ada?** Kode item harus unik — ubah kode atau cek apakah ada item duplikat
MARKDOWN]);
    }

    // =========================================================================
    // KATEGORI 4 — PEMBELIAN
    // =========================================================================

    private function kategori4Pembelian(): void
    {
        Panduan::where('slug', 'pembelian')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Membuat dan memantau Purchase Order (PO) pembelian bahan baku — baik melalui jalur normal (gudang pusat ke vendor) maupun jalur mendesak (cabang langsung ke vendor).

## Langkah-langkah

1. Buka menu **Pembelian → Purchase Order** dari sidebar
2. Gunakan filter untuk mencari PO:
   - **Cari nomor PO / supplier...** — ketik pencarian
   - **Semua Status** — filter status PO
   - **Semua Jenis** — pilih Normal atau Mendesak Langsung
   - **Semua Lokasi** (Owner) — filter per cabang/gudang
   - Klik ikon **kaca pembesar** untuk terapkan; **Reset** untuk hapus
3. Buat PO baru:
   - **Buat PO Normal** — PO dari Gudang Pusat ke vendor (alur standar):
     1. Pilih Supplier, isi Tanggal PO
     2. Tambahkan item & qty yang dipesan, isi harga satuan
     3. Simpan → stok gudang bertambah setelah PO berstatus Diterima
   - **PO Mendesak** — cabang langsung beli ke vendor karena kebutuhan mendesak:
     1. Sama seperti PO Normal namun ditandai otomatis sebagai "Mendesak Langsung"
     2. Butuh approval dari Manajer atau Owner
     3. Stok masuk ke cabang (bukan gudang pusat)
4. Klik **ikon mata** (Detail) untuk lihat dan update status PO
5. Klik **ikon tempat sampah** (Hapus) untuk hapus PO yang masih Draft

## Catatan Penting

- Badge **Normal** (abu-abu) vs **Langsung** (kuning) — bedakan asal PO
- PO Mendesak muncul di laporan terpisah dan notifikasi dikirim ke Owner untuk monitoring
- Stok bertambah saat PO berstatus **Diterima** — bukan saat Disetujui
- Harga beli item di master barang otomatis diperbarui (harga beli terakhir) saat PO diterima

## Troubleshooting

- **Supplier tidak ada di dropdown?** Tambahkan dulu di menu Pembelian → Supplier
- **Stok tidak bertambah?** Pastikan PO sudah diubah statusnya ke Diterima di halaman Detail
- **PO tidak bisa dihapus?** PO mungkin sudah melewati status Draft; hubungi Owner
MARKDOWN]);

        Panduan::where('slug', 'supplier')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola data supplier/vendor bahan baku yang digunakan dalam Purchase Order — tambah, edit, aktifkan/nonaktifkan, dan hapus supplier.

## Langkah-langkah

1. Buka menu **Pembelian → Supplier** dari sidebar
2. Gunakan filter pencarian:
   - **Cari nama / kode...** — ketik nama atau kode supplier
   - **Semua Status** — pilih Aktif atau Non-Aktif
   - Klik **Cari**; klik **Reset** untuk hapus filter
3. Di tabel, tersedia aksi per baris:
   - **Ikon mata** (Lihat) — lihat profil lengkap supplier & histori PO
   - **Ikon pensil** (Edit) — ubah data supplier
   - **Ikon toggle** (Aktifkan / Nonaktifkan) — aktifkan atau nonaktifkan supplier
   - **Ikon tempat sampah** (Hapus) — hapus supplier *(hanya bisa jika tidak ada PO aktif)*
4. Untuk menambah supplier baru, klik **Tambah Supplier**:
   - Isi: Kode Supplier, Nama Supplier, Nama Kontak, Telepon, Kota, Keterangan
5. Kolom **Jml PO** menunjukkan total Purchase Order yang pernah dibuat ke supplier ini

## Catatan Penting

- Supplier **Non-Aktif** tidak muncul di dropdown saat buat PO baru — gunakan ini daripada hapus
- **Menghapus supplier** akan menghapus semua PO terkait (cascade) — sangat berbahaya; lebih baik nonaktifkan
- Kode supplier harus unik di seluruh sistem

## Troubleshooting

- **Supplier tidak muncul saat buat PO?** Pastikan status supplier Aktif; cek dengan filter "Aktif" di halaman ini
- **Tidak bisa hapus?** Ada PO terkait — nonaktifkan saja daripada hapus
MARKDOWN]);
    }

    // =========================================================================
    // KATEGORI 5 — KEUANGAN
    // =========================================================================

    private function kategori5Keuangan(): void
    {
        Panduan::where('slug', 'dashboard-keuangan')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Memantau ringkasan keuangan harian dan bulanan secara visual — total pemasukan, pengeluaran, laba, grafik tren, saldo kas, dan top pengeluaran.

## Langkah-langkah

1. Buka menu **Keuangan → Dashboard** dari sidebar
2. Pilih periode yang ingin dipantau:
   - **Hari Ini / Kemarin / 7 Hari Terakhir / Bulan Ini / Bulan Lalu** — pilih dari dropdown Periode
   - **Rentang Custom** — pilih "Rentang Custom", isi **Dari** dan **Sampai**, klik **Terapkan**
3. Jika punya akses multi-cabang, gunakan dropdown **Semua Cabang** untuk filter per lokasi
4. Baca 3 kartu ringkasan di atas:
   - **Total Pemasukan** — dengan % perubahan vs periode lalu (hijau = naik, merah = turun)
   - **Total Pengeluaran** — idem
   - **Selisih (Laba)** — pemasukan dikurangi pengeluaran
5. Lihat **Grafik Bar** (7 hari terakhir) — perbandingan pemasukan vs pengeluaran harian
6. Cek tabel **Saldo Kas** — saldo per rekening/kas saat ini
7. Lihat **Top Pengeluaran** bulan ini per kategori — bar progress menunjukkan proporsi

## Catatan Penting

- Dashboard **auto-refresh setiap 60 detik** — tidak perlu reload manual
- Trend % dihitung vs periode sama sebelumnya (misal: bulan ini vs bulan lalu)
- Dashboard ini **read-only** — untuk tambah/ubah transaksi gunakan menu Kas & Transaksi
- Selisih negatif (Rugi) akan ditampilkan dengan warna merah

## Troubleshooting

- **Grafik kosong?** Belum ada transaksi pada periode yang dipilih; coba ganti ke periode "Semua Data"
- **Saldo kas tidak sesuai?** Cek apakah ada transaksi manual yang belum diinput di Kas & Transaksi
MARKDOWN]);

        Panduan::where('slug', 'kas-transaksi')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mencatat, melihat, dan mengelola semua transaksi keuangan (pemasukan & pengeluaran) baik yang otomatis dari POS/PO maupun yang diinput manual.

## Langkah-langkah

1. Buka menu **Keuangan → Kas & Transaksi** dari sidebar
2. Lihat 3 kartu ringkasan: **Total Pemasukan**, **Total Pengeluaran**, **Selisih (Saldo)**
3. Gunakan filter untuk mempersempit tampilan:
   - **Pilih tanggal** via filter rentang tanggal (Hari Ini, Minggu Ini, Bulan Ini, dst.)
   - **Tipe** — pilih Pemasukan atau Pengeluaran
   - **Kategori** — filter per kategori transaksi
   - **Cabang** (multi-cabang) — filter per lokasi
4. Untuk **menambah transaksi manual**:
   - Klik **Tambah Transaksi**
   - Pilih Tipe (Pemasukan / Pengeluaran), Kategori, Rekening/Kas, Jumlah, Tanggal, Keterangan
   - Lampirkan bukti foto jika ada
   - Klik Simpan
5. Transaksi **manual** bisa diedit/dihapus via ikon **pensil** dan **tempat sampah**
6. Transaksi **otomatis** (dari POS, PO, atau Adj. Stok) bersifat read-only — edit dari modul asalnya:
   - Ikon **receipt** → arahkan ke detail order
   - Ikon **bag-plus** → arahkan ke detail PO
   - Ikon **sliders** → arahkan ke adjustment stok
7. Untuk **export ke Excel** (audit fisik, rekonsiliasi manual dengan bukti kwitansi):
   - Atur dulu filter yang diinginkan (tanggal, cabang, tipe, kategori, cari)
   - Klik tombol **Export Excel** di pojok kanan atas
   - File yang ke-download **PERSIS mengikuti filter yang sedang aktif** di layar — kalau filter "BM Tembung + Bulan Ini", isinya cuma transaksi BM Tembung bulan ini, bukan semua data
   - Kolom: Tanggal, No. Referensi, Tipe, Kategori, Kas, Cabang, Jumlah, Keterangan, Dibuat Oleh

## Catatan Penting

- Transaksi otomatis dari POS, PO, dan Adjustment Stok **tidak bisa dihapus/diedit di sini** — harus dari modul asalnya
- Owner dapat melihat **Ringkasan Per Cabang** di bagian atas tabel saat mode "Semua Cabang"
- Seluruh transaksi tercatat dengan Audit Trail — history perubahan bisa dilihat di menu Keamanan
- **Export Excel selalu ikut filter yang aktif** — kalau mau export SEMUA data, reset dulu semua filter sebelum klik Export

## Troubleshooting

- **Transaksi tidak bisa diedit?** Transaksi otomatis — edit dari sumber (Order/PO/Adjustment)
- **Total saldo tidak cocok?** Cek filter — mungkin filter tanggal membatasi rentang yang ditampilkan
- **Klik "Kelola Kas"** untuk cek rekening/kas yang terdaftar; klik "Laporan" untuk laporan laba rugi
- **Excel yang di-download isinya cuma sebagian?** Itu wajar kalau ada filter aktif (tanggal/cabang/tipe/kategori/cari) — cek bar filter di atas tabel, reset kalau mau export semua data
MARKDOWN]);

        Panduan::where('slug', 'keuangan-laporan')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat laporan Laba Rugi per periode — ringkasan semua pemasukan dan pengeluaran per kategori dalam format yang bisa dicetak.

## Langkah-langkah

1. Buka menu **Keuangan → Laporan Keuangan** dari sidebar *(atau klik tombol "Laporan" di halaman Kas & Transaksi)*
2. Pilih filter:
   - **Periode** — pilih bulan dan tahun (format bulan/tahun)
   - **Cabang** — pilih cabang tertentu atau semua cabang (Owner)
3. Klik **Tampilkan** untuk generate laporan
4. Laporan tampil dalam format Laba Rugi:
   - **PEMASUKAN** — daftar per kategori + subtotal Total Pemasukan
   - **PENGELUARAN** — daftar per kategori + subtotal Total Pengeluaran
   - **LABA BERSIH** (biru) jika pemasukan > pengeluaran
   - **RUGI BERSIH** (merah) jika pengeluaran > pemasukan
5. Klik **Cetak** untuk print laporan (header filter disembunyikan saat print)
6. Klik **Kembali** untuk balik ke halaman Kas & Transaksi

## Catatan Penting

- Laporan ini diambil dari **semua transaksi** pada periode tersebut, termasuk otomatis (POS, PO, Adj. Stok)
- Filter periode yang kosong akan menampilkan **semua data** tanpa batas waktu
- Laporan ini tidak bisa diedit di sini — ubah data dari sumber transaksi masing-masing

## Troubleshooting

- **Laporan kosong?** Belum ada transaksi di periode/cabang yang dipilih; coba ubah filter
- **Angka tidak cocok dengan ekspektasi?** Cek apakah ada transaksi yang salah kategori di menu Kas & Transaksi
MARKDOWN]);

        Panduan::where('slug', 'setoran')->update([
            'slug' => 'transfer-dana',
            'judul' => 'Cara Pakai Menu Transfer / Perpindahan Dana',
            'konten' => <<<'MARKDOWN'
## Tujuan
Mencatat perpindahan dana kas dari satu lokasi ke lokasi lain — cabang ke kantor pusat, kantor pusat ke cabang, atau antar cabang — dengan alur persetujuan (Menunggu → Diterima/Ditolak).

## Kenapa Nama Berubah dari "Setoran ke Pusat"?

Menu ini sebelumnya bernama "Setoran ke Pusat", tapi kenyataannya dipakai untuk 3 skenario berbeda:
- **Setoran Kasir → Pusat** — hasil penjualan harian dikirim ke kas pusat (searah)
- **Transfer Modal Operasional** — Kantor Pusat kirim dana ke Cabang untuk kebutuhan operasional (searah, arah sebaliknya)
- **Perpindahan antar Kas** — misalnya dari Kas Tunai ke Kas Bank di cabang yang sama

Nama "Transfer / Perpindahan Dana" lebih mewakili ketiganya. **Tidak ada perubahan fungsi** — alur, tombol, dan permission tetap sama persis, cuma istilahnya yang lebih akurat. URL lama `/setoran` otomatis redirect ke `/transfer-dana`.

## Cara Input di UI

1. Buka menu **Keuangan → Transfer / Perpindahan Dana** dari sidebar
2. Gunakan tab untuk filter status: **Semua / Menunggu / Diterima / Ditolak / Dibatalkan**
3. Untuk membuat transfer baru, klik **Buat Transfer Dana** *(butuh permission `setoran.create`)*:
   - Pilih **Dari Kas** (rekening asal) dan **Ke Kas** (rekening tujuan)
   - Isi **Jumlah Transfer**
   - Isi **Keterangan** (opsional)
   - Upload **Bukti Transfer** (opsional)
   - Klik Simpan → transfer masuk status **Menunggu**
4. Penerima (kantor pusat/cabang tujuan) melakukan tindakan:
   - **Ikon centang** (Terima) → modal konfirmasi, klik **Ya, Terima**
   - **Ikon silang** (Tolak) → modal tolak, isi **Alasan Penolakan**, klik Tolak
5. Pengirim bisa membatalkan transfer yang masih **Menunggu** via **ikon slash-circle** (Batalkan)
6. Transfer yang sudah **Ditolak** atau **Dibatalkan** bisa dihapus permanen via **ikon tempat sampah** (Owner)

## Catatan Penting

- **Terima** → saldo rekening tujuan bertambah otomatis
- **Tolak / Batalkan** → saldo rekening asal dikembalikan
- Transfer dana **BUKAN transaksi akuntansi** (tidak masuk Laporan Laba Rugi) — ini murni perpindahan kas internal, bukan pendapatan atau beban
- Notifikasi dikirim ke penerima saat transfer dibuat, dan ke pengirim saat diterima/ditolak
- Alasan penolakan wajib diisi minimal 5 karakter
- **Transfer yang sudah dihapus permanen otomatis hilang dari list ini** — kalau butuh lihat/pulihkan transfer lama yang terhapus, cek menu **Keamanan → Data Terhapus**

## Troubleshooting

- **Tombol "Buat Transfer Dana" tidak ada?** Cek permission `setoran.create` bersama Owner/Admin
- **Transfer tidak bisa dibatalkan?** Mungkin sudah diterima/ditolak — tidak bisa dibalik
- **Saldo tidak berubah setelah Terima?** Refresh halaman; cek di menu Kas & Transaksi
- **Muncul error "Pasangan setoran tidak ditemukan" saat Hapus?** Transfer ini sudah pernah diterima (kedua kas sudah terpengaruh) tapi pasangannya (sisi pengirim/penerima) tidak ketemu — biasanya karena data pasangannya sempat terhapus/rusak. **Jangan paksa hapus** — cek dulu ke menu **Data Terhapus**, kalau pasangannya ada di situ, pulihkan (restore) dulu supaya lengkap kembali sebelum dihapus permanen. Kalau ragu, hubungi admin/developer sebelum lanjut.
MARKDOWN
        ]);

        Panduan::where('slug', 'kategori-transaksi')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola daftar kategori transaksi keuangan yang digunakan sebagai label pengelompokan di modul Kas & Transaksi.

## Langkah-langkah

1. Buka menu **Keuangan → Kategori Transaksi** dari sidebar
2. Daftar kategori dikelompokkan menjadi 3 kartu:
   - **Pemasukan** — kategori untuk transaksi masuk (misal: Penjualan, Setoran Diterima)
   - **Pengeluaran** — kategori untuk transaksi keluar (misal: Pembelian Bahan, Gaji)
   - **Pemasukan & Pengeluaran** — kategori dua arah
3. Untuk menambah kategori baru, klik **Tambah Kategori**:
   - Isi: **Kode** (unik), **Nama Kategori**, **Tipe** (Pemasukan / Pengeluaran / Keduanya)
   - Opsional: tambahkan **Sub Kategori** (ditampilkan sebagai badge di dalam kategori induk)
   - Centang **Aktif** agar tampil di dropdown transaksi
4. Klik **ikon pensil** untuk edit kategori yang sudah ada
5. Klik **ikon tempat sampah** untuk hapus — **kategori sistem** (bertanda gembok) tidak bisa dihapus

## Catatan Penting

- Kategori dengan badge **SISTEM** adalah kategori bawaan yang digunakan oleh transaksi otomatis (POS, PO, Adj. Stok) — **jangan dihapus atau diubah tipenya**
- Menghapus kategori yang sudah dipakai transaksi bisa menyebabkan laporan tidak lengkap
- Sub-kategori tampil sebagai badge kecil di bawah nama kategori di tabel

## Troubleshooting

- **Kategori tidak muncul di dropdown saat tambah transaksi?** Pastikan status kategori Aktif dan tipenya cocok (Pemasukan/Pengeluaran)
- **Tidak bisa hapus kategori?** Kategori tersebut bertanda sistem — tidak bisa dihapus, hanya bisa dinonaktifkan
MARKDOWN]);

        Panduan::where('slug', 'bep')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Menghitung dan memantau titik Break Even Point (BEP) — berapa unit atau rupiah yang harus terjual agar cabang tidak rugi setiap bulannya.

## Langkah-langkah

1. Buka menu **Keuangan → Analisis BEP** dari sidebar
2. Lihat 3 kartu ringkasan: **Periode Setting** (jumlah setting aktif), **Total Produk/Jasa** (item yang di-BEP-kan), **BEP Bulan Ini** (status tercapai/belum)
3. Gunakan filter **Cabang** dan **Periode** (bulan/tahun) untuk melihat data BEP tertentu
4. Klik **Setting BEP Baru** untuk membuat setting BEP baru per cabang per periode:
   - Input **Biaya Tetap** (gaji, sewa, penyusutan, dll.)
   - Tambah tiap **Produk / Jasa** dengan: harga jual per unit, biaya variabel per unit
   - Sistem otomatis hitung BEP unit, BEP rupiah, dan Margin Kontribusi
   - Gunakan **Auto-fill** untuk tarik data otomatis dari penjualan bulan berjalan
5. Di tabel daftar BEP, tersedia aksi per baris:
   - **Ikon gear** (Setting) → edit setting biaya tetap & produk
   - **Ikon graph-up** (Laporan) → lihat laporan BEP detail + grafik pencapaian

## Catatan Penting

- BEP dihitung per cabang per bulan — **pastikan setting dibuat tiap bulan** atau salin dari bulan sebelumnya
- Fitur **Auto-fill** menarik data harga dari master item dan volume dari transaksi POS bulan berjalan
- Notifikasi otomatis dikirim ke Manajer/Owner jika BEP belum tercapai mendekati akhir bulan
- Formula BEP: *Unit = Biaya Tetap ÷ (Harga Jual − Biaya Variabel per Unit)*

## Troubleshooting

- **BEP Bulan Ini "Belum Dihitung"?** Belum ada laporan BEP — buka Setting dan klik Hitung/Generate Laporan
- **Auto-fill tidak ada data?** Belum ada transaksi POS di bulan yang dipilih; isi manual
MARKDOWN]);

        Panduan::where('slug', 'recurring')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Membuat template transaksi keuangan berulang (rutin) — sehingga pengeluaran atau pemasukan rutin tidak perlu diinput manual setiap periode.

## Langkah-langkah

1. Buka menu **Keuangan → Transaksi Berulang** dari sidebar
2. Gunakan filter untuk melihat template tertentu:
   - **Tipe** — semua / Pemasukan / Pengeluaran
   - **Status** — semua / Aktif / Nonaktif
3. Klik **Tambah Template** untuk buat template baru:
   - Isi **Nama Template** (misal: "Bayar Sewa Gedung"), **Tipe**, **Jumlah**, **Kategori**
   - Pilih **Frekuensi** (harian/mingguan/bulanan/tahunan)
   - Isi **Tanggal Jatuh Tempo** (tanggal dalam bulan, misal: 1 = setiap tanggal 1)
4. Template yang sudah dibuat tampil di tabel dengan kolom: Nama, Tipe, Jumlah, Frekuensi, Tgl Jatuh Tempo, Generate Terakhir, Status
5. Untuk **generate transaksi** dari template (saat jatuh tempo):
   - Klik **ikon play** (Generate sekarang) → konfirmasi dialog → transaksi keuangan otomatis dibuat
6. Untuk pause sementara, klik **ikon pause** (Nonaktifkan); untuk aktifkan kembali klik **ikon play-circle** (Aktifkan)
7. Edit template via **ikon pensil**; hapus via **ikon tempat sampah**

## Catatan Penting

- Template Nonaktif tidak bisa di-generate — aktifkan dulu sebelum klik Generate
- Generate **tidak otomatis** — harus diklik manual saat jatuh tempo (tidak ada scheduler otomatis per template)
- Transaksi hasil generate masuk ke modul **Kas & Transaksi** dengan referensi template

## Troubleshooting

- **Ikon Generate abu-abu (disabled)?** Template berstatus Nonaktif — aktifkan dulu
- **Transaksi double?** Sudah pernah generate di bulan ini; cek di Kas & Transaksi sebelum generate ulang
MARKDOWN]);
    }

    // =========================================================================
    // KATEGORI 6 — SDM (diisi setelah scan view)
    // =========================================================================

    private function kategori6Sdm(): void
    {
        Panduan::where('slug', 'karyawan')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola data karyawan di cabang — tambah, edit, lihat profil, dan kelola status aktif/keluar karyawan.

## Langkah-langkah

1. Buka menu **SDM → Karyawan** dari sidebar
2. Gunakan filter untuk mencari karyawan:
   - **Cari nama / NIK...** — ketik nama atau nomor induk karyawan
   - **Cabang** (Owner/Manajer multi-cabang) — filter per lokasi
   - **Status** — Aktif / Keluar
   - Klik **Filter**; klik **Reset** untuk hapus
3. Di tabel, tersedia aksi per karyawan:
   - **Ikon mata** (Detail) — lihat profil, foto, data gaji, shift, absensi
   - **Ikon pensil** (Edit) — ubah data karyawan
   - **Ikon tempat sampah** (Hapus) — hapus karyawan *(cascade: absensi, penggajian ikut terhapus)*
4. Klik **Tambah Karyawan** untuk mendaftarkan karyawan baru:
   - Isi: Nama Lengkap, NIK, Jabatan, Tanggal Masuk, Gaji Pokok, Shift, No. Telepon, Alamat
   - Upload Foto (opsional, digunakan di absensi wajah)
   - Assign ke Cabang

## Catatan Penting

- Karyawan **Keluar** (status non-aktif) tidak bisa melakukan absensi atau penggajian baru
- **Menghapus karyawan** menghapus seluruh riwayat absensi dan slip gaji — lebih aman ubah status ke "Keluar"
- Data karyawan digunakan di: Absensi Wajah, Penggajian, Penilaian 360°, dan Laporan HR

## Troubleshooting

- **Karyawan tidak muncul di scan absensi?** Pastikan status Aktif dan wajah sudah didaftarkan di menu Registrasi Wajah
- **Tidak bisa hapus karyawan?** Ada data terkait (absensi/penggajian) — ubah status ke Keluar saja
MARKDOWN]);

        Panduan::where('slug', 'dashboard-absensi')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Memantau kehadiran karyawan hari ini secara real-time — siapa sudah hadir, telat, belum masuk, atau sedang lembur.

## Langkah-langkah

1. Buka menu **SDM → Dashboard Absensi** dari sidebar
2. Halaman otomatis menampilkan data absensi **hari ini** untuk cabang aktif kamu
3. Gunakan filter untuk menyesuaikan tampilan:
   - **Cabang** (Owner/multi-cabang) — pilih cabang yang ingin dipantau
   - **Tanggal** — lihat absensi tanggal lain
   - **Status** — filter: Hadir Tepat / Telat / Sedang Bekerja / Sedang Lembur / Belum Masuk / Tidak Hadir
   - Klik **Tampilkan** untuk terapkan filter
4. Lihat **4 kartu statistik** di atas:
   - **Total Hadir**, **Telat**, **Belum Masuk**, **Sedang Lembur**
5. Tabel karyawan menampilkan: foto, nama, jabatan, shift (jam masuk-keluar), jam masuk aktual, jam keluar, jam lembur, dan status badge
6. Owner melihat **Overview Semua Cabang** di bawah — persentase kehadiran per cabang dengan progress bar
7. Klik **Refresh** untuk perbarui data; halaman **auto-refresh setiap 60 detik** saat hari ini

## Catatan Penting

- Dashboard ini **read-only** — untuk koreksi atau input manual gunakan menu Absensi Manual
- Status badge: ✅ Hadir Tepat, ⚠️ Telat [menit], 🟦 Sedang Bekerja, 🟪 Sedang Lembur, 🔴 Belum Masuk, ⚫ Tidak Hadir
- Karyawan yang tidak punya absensi di tanggal tersebut otomatis masuk kategori "Tidak Hadir"

## Troubleshooting

- **Karyawan tidak muncul di tabel?** Pastikan karyawan berstatus Aktif dan terdaftar di cabang yang dipilih
- **Data terlambat update?** Klik tombol **Refresh** atau tunggu auto-refresh 60 detik
MARKDOWN]);

        Panduan::where('slug', 'scan-absensi')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melakukan absensi harian menggunakan kamera dan pengenalan wajah — untuk Masuk, Keluar, Lembur Masuk, dan Lembur Keluar.

## Langkah-langkah

1. Buka halaman **Scan Absensi** di perangkat khusus absensi cabang (tablet/HP yang sudah terdaftar)
2. Pilih **tipe absensi** yang ingin dilakukan:
   - **Masuk** — absen datang/mulai kerja
   - **Keluar** — absen pulang
   - **Lembur Masuk** — mulai lembur
   - **Lembur Keluar** — selesai lembur
3. Sistem melakukan **Liveness Detection** untuk memastikan wajah nyata (maks 15 detik):
   - **Gelengkan kepala ke kiri atau kanan 1 kali**
4. Arahkan wajah ke kamera — sistem mencocokkan dengan data wajah terdaftar
5. Jika wajah dikenali (confidence > 60%), sistem memvalidasi **lokasi GPS**:
   - Perangkat harus berada dalam radius absensi cabang (default 100 meter)
6. Jika semua valid, tampil layar konfirmasi dengan foto, nama, jam, dan tipe absensi
7. Layar konfirmasi auto-confirm dalam **8 detik** (bisa dibatalkan jika salah)

## Catatan Penting

- Wajah karyawan **harus sudah didaftarkan** sebelum bisa absen — gunakan menu Registrasi Wajah
- Jika **sudah absen Masuk** hari ini, sistem otomatis menjadikan scan berikutnya sebagai Keluar (untuk tipe yg sesuai)
- **GPS di luar radius?** Pastikan perangkat berada di dalam area cabang; minta Admin set ulang koordinat GPS cabang
- Semua percobaan scan (berhasil maupun gagal) dicatat di log absensi

## Troubleshooting

- **Wajah tidak dikenali?** Pastikan pencahayaan cukup; daftarkan ulang wajah dari beberapa sudut di menu Registrasi Wajah
- **Liveness gagal?** Gelengkan kepala lebih tegas ke kiri atau kanan; pastikan wajah terlihat jelas di kamera; ulangi dalam 15 detik
- **Lokasi di luar radius?** Perangkat harus digunakan di dalam area cabang; hubungi Admin untuk cek setting GPS
MARKDOWN]);

        Panduan::where('slug', 'absensi-manual')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Menginput atau mengoreksi absensi karyawan secara manual — untuk kasus lupa absen, absensi tidak terekam, atau koreksi telat/lembur.

## Langkah-langkah

1. Buka menu **SDM → Absensi Manual** dari sidebar
2. Halaman menampilkan daftar karyawan dengan status absensi hari ini
3. Gunakan filter:
   - **Tanggal** — lihat/koreksi absensi tanggal tertentu
   - **Cabang** (Manajer/Owner) — filter per lokasi
4. Klik **Rekap Bulanan** untuk melihat rekap absensi per bulan
5. Untuk input atau koreksi absensi karyawan tertentu, klik **ikon pensil** (Edit) di baris karyawan:
   - Isi atau ubah: **Jam Masuk**, **Jam Keluar**, **Jam Lembur Masuk**, **Jam Lembur Keluar**
   - Pilih **Status** (Hadir / Alpha / Izin / Sakit / Libur / Cuti)
   - Isi **Keterangan** jika ada
   - Klik Simpan
6. Data absensi manual langsung tersimpan dan mempengaruhi rekap serta perhitungan gaji

## Catatan Penting

- Input manual dicatat sebagai **"Input Manual"** — tercatat siapa yang melakukan koreksi (audit trail)
- Karyawan yang **lupa absen keluar** secara otomatis ditandai "Lupa Absen Pulang" — Manajer perlu koreksi manual
- Absensi manual berdampak langsung ke **perhitungan gaji** (menit telat, jam lembur, hari alpha)
- Hanya Manajer Cabang dan Owner yang bisa input absensi manual

## Troubleshooting

- **Tidak bisa edit absensi?** Cek permission; Kasir dan Operator tidak bisa mengedit absensi orang lain
- **Jam masuk isian tidak cocok?** Pastikan format jam benar (HH:MM); cek shift karyawan untuk referensi
MARKDOWN]);

        Panduan::where('slug', 'absensi-saya')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat riwayat absensi pribadi karyawan — informasi kehadiran, jam masuk/keluar, dan status setiap hari.

## Langkah-langkah

1. Buka menu **SDM → Absensi Saya** dari sidebar
2. Halaman menampilkan riwayat kehadiran kamu secara otomatis (data pribadi saja)
3. Gunakan filter tanggal untuk melihat periode tertentu:
   - **Hari Ini**, **Minggu Ini**, **Bulan Ini**, atau rentang tanggal custom
4. Tabel menampilkan per hari: **Tanggal**, **Jam Masuk**, **Jam Keluar**, **Lembur**, **Menit Telat**, **Status**, **Keterangan**
5. Scroll ke bawah untuk melihat rekap bulanan: **Total Hadir**, **Total Alpha**, **Total Telat**, **Total Lembur**

## Catatan Penting

- Halaman ini **hanya bisa dibaca** — tidak bisa mengedit data absensi sendiri
- Jika ada data yang tidak sesuai (misal: jam keluar tidak terekam), hubungi Manajer Cabang untuk koreksi manual
- Status **"Lupa Absen Pulang"** muncul jika ada jam masuk tapi tidak ada jam keluar

## Troubleshooting

- **Absensi hari ini tidak muncul?** Cek apakah filter menampilkan periode hari ini; pastikan scan absensi berhasil tadi
- **Status alpha padahal hadir?** Kemungkinan scan wajah gagal — hubungi Manajer untuk koreksi manual
MARKDOWN]);

        Panduan::where('slug', 'rekap-absensi')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat rekap absensi bulanan seluruh karyawan — digunakan sebagai dasar perhitungan gaji dan evaluasi kedisiplinan.

## Langkah-langkah

1. Buka menu **SDM → Rekap Absensi** dari sidebar *(atau klik tombol "Rekap Bulanan" dari halaman Absensi Manual)*
2. Pilih filter:
   - **Bulan / Tahun** — pilih periode rekap
   - **Cabang** (Owner/multi-cabang) — filter per lokasi
   - Klik **Tampilkan**
3. Tabel rekap menampilkan per karyawan:
   - **Nama**, **Total Hadir**, **Total Alpha**, **Total Izin/Sakit**, **Total Telat (menit)**, **Total Lembur (menit/jam)**
4. Klik nama karyawan atau ikon detail untuk lihat rekap harian per karyawan (per tanggal)
5. Gunakan tombol **Export Excel** untuk unduh rekap ke file Excel
6. Gunakan tombol **Export PDF** untuk unduh atau cetak rekap ke PDF

## Catatan Penting

- Rekap ini adalah **sumber data penggajian** — pastikan absensi bulan ini sudah lengkap sebelum generate slip gaji
- Hari libur nasional dan hari libur cabang **tidak dihitung alpha** — diambil dari menu Hari Libur
- Karyawan dengan status **Cuti** yang sudah disetujui tidak dihitung alpha pada hari cuti

## Troubleshooting

- **Data rekap kosong?** Pastikan ada karyawan aktif di cabang tersebut dengan absensi di periode yang dipilih
- **Jumlah hari tidak cocok?** Cek apakah ada hari libur yang belum diinput di menu Hari Libur
MARKDOWN]);

        Panduan::where('slug', 'registrasi-wajah')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mendaftarkan data wajah karyawan agar bisa menggunakan sistem absensi wajah (face recognition) di perangkat scan absensi.

## Langkah-langkah

1. Buka menu **SDM → Registrasi Wajah** dari sidebar
2. Pilih **karyawan** yang akan didaftarkan wajahnya dari dropdown
3. Klik **Mulai Registrasi Wajah** — kamera akan aktif
4. Ikuti panduan di layar untuk mengambil **3-5 foto wajah** dari berbagai sudut:
   - Menghadap langsung ke kamera
   - Sedikit miring ke kiri
   - Sedikit miring ke kanan
5. Sistem otomatis menganalisis foto dan membuat **face descriptor** (data numerik wajah)
6. Klik **Simpan Data Wajah** setelah semua foto berhasil diambil
7. Karyawan siap menggunakan absensi wajah

## Catatan Penting

- Pendaftaran wajah dilakukan oleh **Admin atau Manajer Cabang** — karyawan tidak bisa mendaftarkan diri sendiri
- Proses pengenalan wajah berjalan **sepenuhnya di browser (client-side)** menggunakan face-api.js — tidak perlu koneksi AI server
- Pastikan **pencahayaan cukup** saat registrasi agar recognition rate lebih tinggi
- Jika karyawan memakai kacamata, daftarkan **dengan dan tanpa kacamata** untuk hasil terbaik
- Wajah yang sudah terdaftar bisa **didaftarkan ulang** kapan saja jika kualitas recognition buruk

## Troubleshooting

- **Kamera tidak aktif?** Berikan izin akses kamera di browser; gunakan HTTPS jika perlu
- **Recognition rate rendah (sering gagal)?** Daftarkan ulang wajah dengan pencahayaan lebih baik dan lebih banyak sudut
- **Karyawan tidak ada di dropdown?** Pastikan karyawan sudah terdaftar dan berstatus Aktif di menu Karyawan
MARKDOWN]);

        Panduan::where('slug', 'shift')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola jadwal shift kerja karyawan per cabang — nama shift, jam masuk, jam keluar, dan toleransi keterlambatan.

## Langkah-langkah

1. Buka menu **SDM → Kelola Shift** dari sidebar
2. Daftar shift yang sudah ada ditampilkan per cabang
3. Klik **Tambah Shift** untuk membuat shift baru:
   - Isi **Nama Shift** (misal: "Shift Pagi", "Shift Malam")
   - Isi **Jam Masuk** (format HH:MM)
   - Isi **Jam Keluar** (format HH:MM)
   - Isi **Toleransi Menit** — batas keterlambatan yang masih dianggap tepat waktu (misal: 10 menit)
   - Pilih **Cabang** tempat shift ini berlaku
4. Klik **ikon pensil** (Edit) untuk ubah shift; klik **ikon tempat sampah** (Hapus) untuk hapus
5. Setelah shift dibuat, **assign shift ke karyawan** di menu Karyawan → Edit → pilih Shift

## Catatan Penting

- Shift digunakan sistem untuk **deteksi keterlambatan otomatis**: `jam masuk aktual > jam masuk shift + toleransi` = menit terlambat
- Karyawan tanpa shift tidak akan terdeteksi telat di absensi
- Hapus shift hanya jika tidak ada karyawan yang masih di-assign ke shift tersebut

## Troubleshooting

- **Karyawan tidak terdeteksi telat padahal terlambat?** Cek apakah karyawan sudah di-assign ke shift dan toleransi menit sudah diset
- **Shift tidak muncul di dropdown Edit Karyawan?** Pastikan shift dibuat untuk cabang yang sama dengan karyawan tersebut
MARKDOWN]);

        Panduan::where('slug', 'hari-libur')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mendaftarkan hari libur nasional dan hari libur khusus per cabang agar sistem tidak menghitung karyawan sebagai alpha pada hari tersebut.

## Langkah-langkah

1. Buka menu **SDM → Hari Libur** dari sidebar
2. Daftar hari libur yang sudah terdaftar ditampilkan per bulan/tahun
3. Klik **Tambah Libur** untuk menambah hari libur baru:
   - Isi **Tanggal** hari libur
   - Isi **Keterangan** (misal: "Idul Fitri", "Tahun Baru", "HUT Kemerdekaan")
   - Pilih **Tipe**:
     - **Nasional** — berlaku untuk semua cabang
     - **Cabang** — berlaku hanya untuk satu cabang tertentu (pilih cabang)
   - Klik Simpan
4. Klik **ikon pensil** untuk edit; klik **ikon tempat sampah** untuk hapus hari libur

## Catatan Penting

- Hari libur **Nasional** berlaku di semua cabang — cukup input sekali
- Hari libur **Cabang** memungkinkan libur berbeda per lokasi (misal: hari jadi kota setempat)
- Sistem rekap absensi otomatis menandai hari libur sebagai status **Libur** — tidak dihitung alpha

## Troubleshooting

- **Karyawan alpha padahal hari libur?** Cek apakah hari tersebut sudah terdaftar dan tipenya sesuai (Nasional/Cabang yang tepat)
- **Hari libur terdaftar dua kali?** Cek dan hapus duplikat; bisa terjadi jika ada yang input libur Nasional dan Cabang di tanggal sama
MARKDOWN]);

        Panduan::where('slug', 'penggajian')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Menghitung dan mengelola slip gaji karyawan per bulan — berdasarkan rekap absensi, gaji pokok, lembur, dan potongan.

## Langkah-langkah

1. Buka menu **SDM → Penggajian** dari sidebar
2. Gunakan filter **Bulan / Tahun** dan **Cabang** untuk melihat data penggajian periode tertentu
3. Klik **Generate Massal** untuk membuat slip gaji semua karyawan aktif sekaligus:
   - Sistem otomatis hitung dari rekap absensi bulan tersebut
   - Komponen otomatis: gaji pokok proporsional, uang lembur, potongan alpha, potongan telat
4. Atau, klik **Generate per Karyawan** di baris karyawan tertentu
5. Setelah di-generate, klik **ikon mata** (Detail / Slip Gaji) untuk lihat rincian:
   - Gaji Pokok, Tunjangan, Uang Lembur, Potongan Alpha, Potongan Telat, **Total Gaji**
6. Untuk koreksi manual, klik **ikon pensil** (Edit):
   - Ubah nilai di kolom **Uang Lembur Manual** atau **Potongan Alpha Manual** untuk override
7. Klik **ikon printer** untuk cetak slip gaji

## Catatan Penting

- Tarif lembur, potongan alpha, dan potongan telat diset di **Pengaturan → Pengaturan Penggajian** (Owner saja)
- **Generate Massal** akan skip karyawan yang sudah ada slip di periode tersebut — aman dijalankan ulang
- Pastikan rekap absensi bulan tersebut sudah lengkap sebelum generate gaji
- Slip gaji otomatis terhitung masuk sebagai **Pengeluaran Gaji** di modul Keuangan

## Troubleshooting

- **Slip gaji kosong/nol?** Cek rekap absensi — mungkin tidak ada data absensi bulan tersebut
- **Nilai lembur tidak sesuai?** Cek Pengaturan Penggajian (tarif per jam) atau gunakan override manual
- **Karyawan tidak muncul di generate?** Pastikan karyawan berstatus Aktif di bulan tersebut
MARKDOWN]);

        Panduan::where('slug', 'cuti')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengajukan, menyetujui, dan memantau pengajuan cuti atau izin karyawan.

## Langkah-langkah

1. Buka menu **SDM → Cuti & Izin** dari sidebar
2. Daftar pengajuan cuti ditampilkan dengan filter tab: **Semua / Pending / Disetujui / Ditolak**
3. **Karyawan** yang ingin mengajukan cuti:
   - Klik **Ajukan Cuti**
   - Isi: **Tipe Cuti** (Cuti Tahunan / Izin / Sakit / dll.), **Dari Tanggal**, **Sampai Tanggal**, **Alasan**
   - Klik Simpan → pengajuan masuk status **Pending**
4. **Manajer Cabang** memproses pengajuan:
   - Klik **ikon centang** (Setujui) atau **ikon silang** (Tolak) di baris pengajuan
   - Untuk tolak: isi **Alasan Penolakan**
5. Setelah disetujui, hari-hari cuti otomatis tercatat di rekap absensi dengan status **Cuti** (tidak dihitung alpha)
6. Karyawan menerima **notifikasi** saat pengajuan disetujui atau ditolak

## Catatan Penting

- Cuti yang **Disetujui** otomatis mengisi status absensi pada tanggal terkait sebagai "Cuti"
- Saldo cuti tahunan (jika ada batasan) bisa dilihat di profil karyawan
- Pengajuan yang masih **Pending** bisa dibatalkan sendiri oleh karyawan pengaju

## Troubleshooting

- **Pengajuan tidak muncul di halaman Manajer?** Pastikan karyawan dan Manajer berada di cabang yang sama
- **Status cuti tidak berubah di absensi?** Cek apakah pengajuan sudah berstatus Disetujui (bukan masih Pending)
MARKDOWN]);

        Panduan::where('slug', 'evaluasi')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola periode penilaian karyawan 360° — membuka periode, memantau progress pengisian, dan finalisasi hasil penilaian.

## Langkah-langkah

1. Buka menu **SDM → Penilaian 360°** dari sidebar
2. Daftar periode penilaian ditampilkan (Q1/Q2/Q3/Q4 per tahun)
3. **Manajer / Owner** membuat periode baru:
   - Klik **Buka Periode Baru**
   - Isi: Nama Periode (misal "Q1 2026"), Tanggal Mulai, Tanggal Selesai, Deadline Pengisian
   - Klik Simpan → status periode: **Dibuka**
4. Sistem otomatis assign penilai untuk setiap karyawan:
   - Atasan langsung (bobot 50%)
   - 2-3 rekan kerja secara acak (bobot 30%)
   - Karyawan sendiri / self-assessment (bobot 20%)
5. Pantau progress pengisian di halaman detail periode:
   - Lihat siapa yang sudah/belum mengisi
6. Setelah deadline, klik **Finalisasi** untuk menghitung skor akhir otomatis:
   - Skor dihitung per aspek (Kedisiplinan 25%, Kinerja 30%, Kerjasama 20%, Kebersihan 10%, Inisiatif 15%)
   - Predikat: Sangat Baik / Baik / Cukup / Kurang / Sangat Kurang
7. Karyawan menerima notifikasi saat hasil sudah final

## Catatan Penting

- Penilai menerima notifikasi otomatis saat periode dibuka dan **reminder 3 hari sebelum deadline**
- Skor rekan kerja ditampilkan sebagai **rata-rata anonim** — karyawan tidak tahu siapa yang menilai
- Hasil penilaian bisa menjadi dasar **kenaikan gaji atau bonus** (diproses manual oleh Manajer)

## Troubleshooting

- **Penilai belum muncul?** Sistem butuh waktu assign; refresh halaman atau cek di detail evaluasi karyawan
- **Skor tidak terhitung?** Pastikan minimal satu penilai per tipe (atasan/rekan/self) sudah mengisi
MARKDOWN]);

        Panduan::where('slug', 'evaluasi-saya')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengisi form penilaian untuk rekan kerja atau self-assessment dalam periode penilaian 360° yang sedang berjalan.

## Langkah-langkah

1. Buka menu **SDM → Penilaian Saya** dari sidebar
2. Daftar tugas penilaian yang harus kamu isi ditampilkan (kamu sebagai penilai)
3. Klik **Isi Penilaian** di baris tugas yang belum diisi
4. Form penilaian menampilkan 5 aspek yang perlu dinilai dengan **skala 1-5**:
   - **Kedisiplinan** (1=Sangat Kurang, 5=Sangat Baik)
   - **Kinerja / Produktivitas**
   - **Kerjasama Tim**
   - **Kebersihan & Kerapian**
   - **Inisiatif / Kemandirian**
5. Isi **Komentar** per aspek (opsional tapi dianjurkan)
6. Klik **Simpan Penilaian** setelah semua aspek terisi
7. Lihat juga **Hasil Penilaian Saya** — penilaian yang diterima kamu dari periode yang sudah final

## Catatan Penting

- Setelah penilaian **disubmit, tidak bisa diubah** — pastikan sudah yakin sebelum simpan
- Batas waktu pengisian sesuai **deadline** yang ditetapkan di periode; lewat deadline tidak bisa isi
- Kamu tidak akan tahu siapa rekan yang menilaimu — skor rekan ditampilkan sebagai rata-rata anonim
- Self-assessment mengisi penilaian untuk **diri sendiri** — sebaiknya jujur dan objektif

## Troubleshooting

- **Tidak ada tugas penilaian?** Mungkin belum ada periode aktif, atau belum di-assign sebagai penilai; hubungi Manajer
- **Deadline lewat dan belum bisa isi?** Tidak bisa isi setelah deadline — hubungi Manajer untuk perpanjangan
MARKDOWN]);
    }

    // =========================================================================
    // KATEGORI 7 — ASET
    // =========================================================================

    private function kategori7Aset(): void
    {
        Panduan::where('slug', 'aset')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Mengelola data aset perusahaan (mesin, kendaraan, peralatan) per lokasi — termasuk pendataan, penyusutan otomatis, perawatan, mutasi, dan penghapusan aset.

## Langkah-langkah

1. Buka menu **Aset** dari sidebar
2. Gunakan filter untuk mencari aset:
   - **Cari nama / kode aset...**
   - **Kategori** (Mesin Produksi / Kendaraan / Peralatan Dapur / Elektronik / Furniture / Bangunan)
   - **Lokasi** — filter per cabang/gudang
   - **Status** (Aktif / Tidak Aktif / Dijual / Dihapuskan)
   - **Kondisi** (Baik / Rusak Ringan / Rusak Berat)
3. Klik **Tambah Aset** untuk mendaftarkan aset baru:
   - Isi: Kode Aset, Nama Aset, Kategori, Lokasi, Tanggal Perolehan, Harga Perolehan, **Nilai Residu** (input manual, estimasi nilai sisa/jual di akhir umur ekonomis — sesuai jenis asetnya, default 0 kalau tidak ada nilai sisa), Umur Ekonomis (bulan)
   - Pilih **Metode Penyusutan**: Garis Lurus / Saldo Menurun / Satuan Produksi
   - Ada **Kalkulator** kecil di section Metode Penyusutan kalau perlu bantu hitung angka apapun (murni alat bantu, hasilnya tidak otomatis masuk ke field manapun — salin manual)
4. Di tabel aset, tersedia aksi per baris:
   - **Ikon mata** (Detail) — lihat nilai buku saat ini, jadwal penyusutan, riwayat perawatan
   - **Ikon pensil** (Edit) — ubah data aset
   - **Ikon tools** (Perawatan) — catat maintenance rutin atau perbaikan
   - **Ikon panah** (Mutasi) — pindahkan aset ke lokasi lain
   - **Ikon tempat sampah / disposal** (Hapus / Jual) — proses penghapusan atau penjualan aset
5. Untuk **export ke Excel** (audit fisik, cocokkan dengan aset di lapangan):
   - Atur filter yang diinginkan (lokasi, kategori, status, kondisi, cari)
   - Klik tombol **Export Excel** — file yang di-download **mengikuti filter yang sedang aktif**
   - Kolom: Kode Aset, Nama, Kategori, Cabang, Tanggal Perolehan, Harga Perolehan, Nilai Residu, Umur Ekonomis, Akumulasi Penyusutan, Nilai Buku, Status

## Cara Menentukan Nilai Residu

**Nilai Residu diisi manual oleh user** (bukan dihitung otomatis oleh sistem) — sesuai standar akuntansi (SAK ETAP), nilai residu adalah **estimasi bisnis** (perkiraan nilai jual/scrap aset di akhir umur ekonomisnya), bukan angka yang bisa diturunkan murni dari rumus matematis. Estimasi ini tergantung jenis aset:
- Aset yang biasanya masih ada nilai jual bekas (kendaraan, mesin besar) → isi perkiraan nilai jualnya
- Aset yang habis pakai / tidak ada nilai sisa (peralatan kecil, elektronik murah) → isi **Rp 0**

Kalau tidak yakin, isi **Rp 0** — ini pilihan paling aman dan konservatif (paling umum dipakai kalau tidak ada estimasi nilai jual yang jelas).

## Kalkulator Bantuan

Ada widget kalkulator kecil di section **Metode Penyusutan** (form Tambah/Edit Aset) — murni alat bantu hitung angka (mis. cek estimasi nilai residu, kalkulasi cepat lainnya) tanpa perlu buka HP/kalkulator terpisah. Hasilnya **tidak otomatis mengisi field manapun** — salin manual angkanya ke field yang dituju kalau perlu.

## Catatan Penting

- **Penyusutan dihitung otomatis** setiap bulan berdasarkan metode yang dipilih — nilai buku berkurang secara berkala
- Beban penyusutan bulanan otomatis masuk sebagai **Pengeluaran** di modul Keuangan
- **Mutasi aset** antar cabang membutuhkan approval Owner/Admin Pusat
- Saat aset dijual, sistem menghitung **untung/rugi** dari selisih nilai jual vs nilai buku saat itu
- **Export Excel selalu ikut filter yang aktif** — reset filter dulu kalau mau export semua aset

## Troubleshooting

- **Nilai buku tidak berubah?** Pastikan penyusutan bulanan sudah di-trigger (manual atau scheduler aktif)
- **Aset tidak muncul?** Cek filter Status — mungkin aset sudah di-set ke status Dijual atau Dihapuskan
- **Tidak bisa mutasi aset?** Butuh permission khusus; hubungi Owner
MARKDOWN]);
    }

    // =========================================================================
    // KATEGORI 8 — LAPORAN
    // =========================================================================

    private function kategori8Laporan(): void
    {
        Panduan::where('slug', 'laporan-penjualan')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat laporan penjualan dalam periode tertentu — ringkasan omzet, jumlah order, breakdown per produk/jasa, ranking kasir, dan perbandingan antar cabang.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Penjualan** dari sidebar
2. Atur filter:
   - **Rentang Tanggal** — pilih Hari Ini / Minggu Ini / Bulan Ini / Custom (isi Dari–Sampai)
   - **Cabang** (Owner) — laporan per cabang atau semua cabang
   - **Tipe** — filter Jasa Giling / Produk Jadi
3. Klik **Filter** untuk tampilkan data; klik **Reset** untuk hapus filter
4. Laporan menampilkan: ringkasan omzet, jumlah order, top produk terlaris, dan **Ranking Kasir** (top 10 kasir berdasar total omzet di periode tersebut, ikon trofi untuk 3 teratas)
5. Klik **Print** untuk cetak laporan atau **Export Excel** untuk unduh

## Catatan Penting

- Hanya order berstatus **Selesai** yang dihitung dalam omzet laporan
- Owner bisa melihat **perbandingan antar cabang** dalam satu laporan
- Filter tanggal default: **Bulan Ini** — disimpan di session, tidak reset saat pindah halaman
- Section **Ranking Kasir** cuma tampil untuk user yang punya izin `laporan.ranking_kasir.view` — minta Owner mencentang izin ini di **Pengaturan → Role & Hak Akses** kalau belum muncul

## Troubleshooting

- **Data kosong?** Tidak ada order selesai di periode yang dipilih; coba perluas rentang tanggal
- **Jumlah tidak cocok dengan Kas & Transaksi?** Laporan penjualan hanya hitung order selesai; Kas & Transaksi hitung semua transaksi termasuk pengeluaran
MARKDOWN]);

        Panduan::where('slug', 'laporan-stok')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat laporan pergerakan stok (masuk-keluar) dan posisi stok per item per lokasi dalam periode tertentu.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Stok** dari sidebar
2. Atur filter:
   - **Rentang Tanggal** — pilih periode pergerakan stok
   - **Cabang / Lokasi** (Owner) — filter per gudang atau cabang
   - **Item** — filter per item tertentu
   - **Tipe Pergerakan** — Masuk / Keluar / Transfer / Adjustment
3. Klik **Filter** untuk tampilkan; klik **Stok Rendah** untuk filter cepat item kritis
4. Laporan menampilkan: history pergerakan per item, qty masuk/keluar, saldo stok
5. Klik **Export Excel** untuk unduh data

## Catatan Penting

- Pergerakan stok yang tampil mencakup: penjualan POS, penerimaan PO, transfer, dan adjustment
- Laporan ini berguna untuk **audit stok** dan memverifikasi selisih fisik vs sistem

## Troubleshooting

- **Tidak ada pergerakan?** Item belum pernah ada transaksi di periode tersebut; coba perluas tanggal
- **Saldo stok berbeda dengan halaman Stok Barang?** Kemungkinan ada pergerakan setelah tanggal filter; samakan periode ke "Hari Ini"
MARKDOWN]);

        Panduan::where('slug', 'laporan-keuangan')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat laporan Laba Rugi konsolidasi dalam format cetak — ringkasan per kategori pemasukan dan pengeluaran.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Keuangan** dari sidebar
2. Atur filter:
   - **Rentang Tanggal** — pilih periode laporan
   - **Cabang** (Owner) — laporan per cabang atau semua cabang
3. Laporan tampil dalam format Laba Rugi:
   - **PEMASUKAN** per kategori → Total Pemasukan
   - **PENGELUARAN** per kategori → Total Pengeluaran
   - **LABA / RUGI BERSIH**
4. Klik **Print** untuk cetak *(tombol filter tersembunyi saat print)*

## Catatan Penting

- Laporan ini sama dengan menu **Keuangan → Laporan Keuangan** namun bisa diakses dari menu Laporan
- Untuk laporan lebih detail per transaksi, gunakan menu **Kas & Transaksi**

## Troubleshooting

- **Laporan kosong?** Tidak ada transaksi di periode yang dipilih; coba gunakan filter "Semua Data"
MARKDOWN]);

        Panduan::where('slug', 'laporan-hr')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat laporan kehadiran karyawan per periode lengkap dengan rekap per karyawan dan detail absensi harian.

## Langkah-langkah

1. Buka menu **Laporan → Laporan HR/SDM** dari sidebar
2. Atur filter:
   - **Rentang Tanggal** — pilih periode laporan (Hari Ini / Minggu Ini / Bulan Ini / Custom)
   - **Cabang** (Owner/multi-cabang) — filter per lokasi
   - **Status Kehadiran** — filter: Semua Status / Hadir / Alpha / Izin / Sakit
3. Laporan menampilkan 4 kartu statistik: **Hadir**, **Alpha**, **Izin**, **Sakit**
4. Dua tabel data:
   - **Rekap per Karyawan** — total hadir, alpha, izin, sakit, lembur (jam), % kehadiran
   - **Detail Absensi** — baris per tanggal per karyawan, dengan jam masuk/keluar dan status
5. Klik **Export Excel** untuk unduh rekap
6. Klik **Penggajian** untuk melihat laporan penggajian periode tersebut

## Catatan Penting

- Laporan ini fokus pada **data absensi** — untuk laporan gaji gunakan tombol **Penggajian**
- Data diambil dari rekap absensi (termasuk yang diinput manual oleh Manajer)
- Filter Status Kehadiran mempengaruhi **Detail Absensi** — rekap per karyawan selalu tampil semua

## Troubleshooting

- **Data kosong?** Tidak ada absensi di periode yang dipilih; coba perluas rentang tanggal
- **Karyawan tidak muncul di rekap?** Karyawan belum punya absensi di periode tersebut; cek di menu Absensi Manual
MARKDOWN]);

        Panduan::where('slug', 'laporan-absensi')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat laporan log absensi wajah lengkap — termasuk foto scan, skor confidence pengenalan, status liveness, dan koordinat GPS setiap scan.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Absensi** dari sidebar
2. Atur filter:
   - **Dari Tanggal / Sampai Tanggal** — pilih periode
   - **Cabang** (Owner) — filter per lokasi
   - **Karyawan** — filter per karyawan tertentu
   - **Status** — filter: Semua / Hadir / Ada Lembur / Telat
   - Gunakan quick filter: **Hari Ini / Minggu Ini / Bulan Ini / Bulan Lalu / 30 Hari**
3. Laporan tampil dengan kolom: Tanggal, Karyawan, Jam Masuk, Jam Keluar, Lembur, Status
4. Klik **ikon mata** di setiap baris untuk detail lengkap per scan:
   - **Foto** scan (klik untuk perbesar)
   - **Confidence %** — akurasi pengenalan wajah
   - **Liveness** — ✅ Passed / ❌ Failed
   - **Device** — perangkat yang digunakan
   - **Jarak dari Cabang** (meter)
   - **Lokasi GPS** — link ke Google Maps
5. Klik **Export Excel** untuk unduh data; klik **Export PDF** untuk laporan formal (tab baru)
6. Admin bisa hapus log tertentu via **ikon tempat sampah** (permission: `hapus_log_absensi`)

## Catatan Penting

- Laporan ini adalah **log face scan** — berbeda dari rekap absensi harian (yang ada di Laporan HR)
- Absensi yang diinput manual menampilkan keterangan "Absen Manual" tanpa foto dan GPS
- Confidence di bawah 60% artinya scan tidak berhasil — tidak dicatat sebagai absensi valid

## Troubleshooting

- **Foto tidak muncul?** Absensi tersebut diinput manual oleh Manajer — tidak ada foto scan
- **Status tetap "Tidak Hadir" padahal sudah scan?** Confidence mungkin < 60% atau GPS di luar radius; cek detail di ikon mata
MARKDOWN]);

        Panduan::where('slug', 'laporan-aset')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat laporan nilai aset, jadwal penyusutan, dan history perawatan dalam satu halaman laporan.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Aset** dari sidebar
2. Atur filter:
   - **Rentang Tanggal** — periode perolehan atau penyusutan
   - **Lokasi / Cabang** — filter per lokasi aset
   - **Kategori Aset** — filter per jenis aset
3. Laporan menampilkan: daftar aset, harga perolehan, akumulasi penyusutan, nilai buku saat ini, kondisi
4. Klik **Kelola Aset** untuk masuk ke menu manajemen aset
5. Klik **Export** untuk unduh laporan

## Catatan Penting

- Nilai buku = Harga Perolehan − Akumulasi Penyusutan
- Laporan ini berguna untuk audit neraca — nilai aset dilaporkan ke laporan keuangan

## Troubleshooting

- **Nilai buku tidak berubah dari bulan ke bulan?** Penyusutan bulanan belum di-trigger; masuk menu Aset → Hitung Penyusutan
MARKDOWN]);

        Panduan::where('slug', 'laporan-bep')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat laporan pencapaian BEP (Break Even Point) per produk/jasa, per cabang, dibandingkan dengan target yang sudah disetting.

## Langkah-langkah

1. Buka menu **Laporan → Laporan BEP** dari sidebar
2. Atur filter:
   - **Cabang** — laporan per cabang atau semua cabang (Owner)
   - **Periode** — pilih bulan dan tahun
3. Laporan menampilkan per produk/jasa:
   - Target BEP (unit & rupiah), Penjualan Aktual, % Pencapaian BEP
   - Status: **Tercapai** (hijau) atau **Belum Tercapai** (merah/kuning)
4. Klik **Setting BEP** untuk kembali ke setting BEP jika data perlu diperbarui

## Catatan Penting

- BEP hanya bisa dihitung jika sudah ada **Setting BEP** untuk periode tersebut di menu Analisis BEP
- Laporan BEP digunakan untuk evaluasi bulanan apakah omzet sudah cukup menutup biaya tetap

## Troubleshooting

- **Laporan kosong?** Belum ada setting BEP di periode yang dipilih; buat di menu Keuangan → Analisis BEP
MARKDOWN]);

        Panduan::where('slug', 'laporan-setoran')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat riwayat setoran kas dari cabang ke pusat dalam periode tertentu — status, jumlah, dan bukti setiap setoran.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Setoran** dari sidebar
2. Atur filter:
   - **Rentang Tanggal** — periode setoran
   - **Cabang Asal** (Owner) — filter per cabang pengirim
   - **Status** — filter Diterima / Ditolak / Dibatalkan
3. Laporan menampilkan: Tanggal, Dari, Ke, Jumlah, Status, Bukti
4. Klik **Print** untuk cetak laporan

## Catatan Penting

- Hanya setoran berstatus **Diterima** yang masuk ke perhitungan saldo rekening tujuan

## Troubleshooting

- **Setoran tidak muncul?** Cek filter status — mungkin setoran masih Menunggu, belum Diterima
MARKDOWN]);

        Panduan::where('slug', 'laporan-kategori')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat laporan transaksi keuangan dikelompokkan per kategori dalam periode tertentu — untuk analisis pengeluaran terbesar atau sumber pemasukan utama.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Per Kategori** dari sidebar
2. Atur filter:
   - **Rentang Tanggal** — periode laporan
   - **Tipe** — Pemasukan / Pengeluaran / Semua
   - **Cabang** (Owner) — per cabang atau semua
3. Laporan menampilkan per kategori: jumlah transaksi, total nominal, persentase dari total
4. Klik **Print** untuk cetak laporan

## Catatan Penting

- Laporan ini berguna untuk evaluasi: kategori pengeluaran mana yang paling besar bulan ini?
- Bisa dibandingkan antar periode untuk analisis tren

## Troubleshooting

- **Kategori tidak muncul?** Belum ada transaksi berkategori tersebut di periode yang dipilih
MARKDOWN]);

        Panduan::where('slug', 'laporan-audit-bukti')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Memeriksa kepatuhan upload bukti transaksi pengeluaran — melihat mana yang sudah dan belum dilampiri bukti foto/file.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Audit Bukti** dari sidebar
2. Atur filter:
   - **Dari / Sampai** — periode transaksi
   - **Cabang** (Owner) — filter per lokasi
   - **Min. Jumlah (Rp)** — hanya tampilkan transaksi di atas nominal tertentu (langkah 50.000)
   - **Hanya belum upload** — centang checkbox ini untuk fokus ke transaksi yang belum ada buktinya
3. Klik **Filter** untuk terapkan
4. Lihat 4 kartu statistik: **Total Transaksi**, **Sudah Upload Bukti** (dengan %), **Belum Upload Bukti**, **Total Nominal**
5. Tabel menampilkan: Tanggal, No. Transaksi, Keterangan, Kategori, Cabang, Jumlah, Bukti
   - Kolom **Bukti**: ikon file-image jika ada, badge merah "Belum" jika kosong
6. Klik **Export Excel** untuk unduh; klik **Print** untuk cetak laporan audit

## Catatan Penting

- Laporan ini berguna untuk **audit internal**: pastikan semua pengeluaran besar ada buktinya
- Gunakan filter **"Hanya belum upload"** untuk memudahkan follow-up transaksi tanpa bukti
- Bukti bisa dilampirkan dengan cara edit transaksi di menu **Kas & Transaksi**

## Troubleshooting

- **Daftar kosong padahal ada pengeluaran?** Cek filter — mungkin filter "Hanya belum upload" aktif tapi semua sudah ada bukti
- **% bukti rendah?** Ingatkan tim untuk selalu upload foto struk/bukti saat input transaksi manual
MARKDOWN]);

        Panduan::where('slug', 'laporan-saldo-kas')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat mutasi dan saldo kas per rekening dalam periode tertentu — dengan grafik tren saldo dan detail setiap transaksi per kas.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Saldo Kas** dari sidebar
2. Atur filter:
   - **Dari / Sampai** — rentang tanggal laporan
   - **Cabang** (Owner) — filter per cabang (auto-submit saat pilih)
   - **Kas** — filter per rekening/kas tertentu, atau pilih "Semua Kas"
3. Klik **Tampilkan** untuk generate laporan
4. Grafik garis **Saldo Kas** menampilkan tren saldo selama periode yang dipilih
5. Per rekening/kas ditampilkan kartu ringkasan: **Saldo Awal**, **Pemasukan**, **Pengeluaran**, **Saldo Akhir**
6. Tabel **Mutasi** di bawah setiap kas: Tanggal, No. Transaksi, Keterangan, Keluar, Masuk, Saldo (running balance)
7. Klik **Export Excel** untuk unduh; klik **Print** untuk cetak

## Catatan Penting

- Saldo Akhir = Saldo Awal + Total Masuk − Total Keluar dalam periode yang dipilih
- Owner bisa melihat semua rekening dari semua cabang dalam satu laporan
- Laporan ini berguna untuk **rekonsiliasi kas** dan audit saldo

## Troubleshooting

- **Saldo tidak sesuai ekspektasi?** Pastikan filter tanggal mencakup semua transaksi yang dimaksud
- **Saldo minus?** Ada transaksi pengeluaran melebihi saldo — cek di menu Kas & Transaksi
MARKDOWN]);

        Panduan::where('slug', 'laporan-cabang')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Membandingkan performa keuangan antar cabang dalam satu tampilan — pemasukan, pengeluaran, net laba, ranking kontribusi revenue, dan data Head Office.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Cabang vs Cabang** dari sidebar *(hanya tersedia untuk Owner/Admin Pusat)*
2. Atur filter:
   - **Rentang Tanggal** — pilih periode perbandingan
   - Toggle **"Tampilkan Head Office di ranking"** — centang untuk ikutkan Head Office dalam tabel ranking
3. Lihat 3 kartu ringkasan: **Total Pemasukan**, **Total Pengeluaran**, **Net Laba/Rugi** (semua cabang gabungan)
4. Grafik **Pemasukan vs Pengeluaran per Cabang** — bar chart perbandingan tiap cabang
5. Grafik **Kontribusi Revenue** — pie chart proporsi omzet tiap cabang
6. **Kartu HO Summary** (Head Office) — Setoran Masuk, Pengeluaran HO, Pemasukan HO, Net HO
7. **Tabel Ranking Cabang** — urutan dari pemasukan tertinggi ke terendah:
   - Rank 1 (terbaik) ditandai 🏆 kuning; rank terakhir ditandai ↓ oranye
8. Klik **Export Excel** untuk unduh; klik **Print** untuk cetak

## Catatan Penting

- Laporan ini **hanya untuk Owner / Admin Pusat** — Manajer Cabang tidak bisa melihat data cabang lain
- Berguna untuk evaluasi bulanan: cabang mana yang berkontribusi terbesar dan mana yang perlu perhatian
- Head Office (tipe `head_office`) ditampilkan terpisah dari cabang biasa di kartu HO Summary

## Troubleshooting

- **Hanya satu cabang muncul?** Role akun bukan Owner/Admin Pusat — laporan ini terbatas untuk role tersebut
- **Head Office tidak muncul di ranking?** Toggle "Tampilkan Head Office di ranking" belum dicentang
MARKDOWN]);
    }

    // =========================================================================
    // KATEGORI 9 — LAINNYA
    // =========================================================================

    private function masterJenisOlahanUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'jenis-olahan'], [
            'judul'  => 'Master Jenis Menu',
            'konten' => <<<'MARKDOWN'
## Tujuan
Mengelola daftar Jenis Menu (mis. Dimsum, Gyoza) — kategorisasi referensi untuk mengelompokkan resep di **Master Bumbu Pusat**. Tambah, edit nama, dan nonaktifkan Jenis Menu.

## Langkah-langkah

1. Buka menu **Jenis Menu** dari sidebar (hanya muncul untuk Owner & Admin Pusat)
2. Daftar Jenis Menu yang sudah ada ditampilkan beserta slug dan status aktif
3. Untuk **menambah jenis baru:**
   - Klik tombol **Tambah Jenis Menu**
   - Isi nama (contoh: `Dimsum`, `Gyoza`, `Frozen Food`)
   - Slug di-generate otomatis dari nama (contoh: `dimsum`, `frozen_food`)
   - Centang **Aktif** agar langsung muncul di pilihan Master Bumbu Pusat
   - Klik **Simpan**
4. Untuk **mengedit nama:** Klik ikon pensil → ubah nama → Simpan. Slug tidak bisa diubah.
5. Untuk **nonaktifkan:** Klik ikon pause (⏸) di kolom Aksi → jenis hilang dari pilihan di Master Bumbu Pusat

## Catatan Penting

- **Slug = value yang tersimpan di data resep lama.** Tidak bisa diubah setelah create agar data lama tidak rusak.
- **Nonaktifkan ≠ Hapus** — resep lama yang punya jenis tersebut tetap utuh.
- **Murni kategorisasi referensi** — TIDAK memengaruhi tampilan grid/filter kategori di POS (itu diatur terpisah lewat Master Kategori Item di menu Master Barang).
- Hanya Owner & Admin Pusat yang bisa akses halaman ini.

## Troubleshooting

- **Jenis baru tidak muncul di pilihan Master Bumbu Pusat?** Pastikan statusnya Aktif di halaman Master Jenis Menu.
- **Tidak bisa simpan karena "Slug sudah ada"?** Nama yang dimasukkan menghasilkan slug yang sama dengan jenis lain (contoh: "Gyoza" dan "gyoza"). Gunakan nama yang berbeda.
MARKDOWN
        ,
            'modul'  => 'penjualan',
            'urutan' => 99,
            'aktif'  => true,
        ]);
    }

    private function masterResepBumbuUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'resep-bumbu'], [
            'judul'  => 'Master Bumbu Pusat',
            'konten' => <<<'MARKDOWN'
## Tujuan
Menyimpan resep/komposisi bahan baku per produk (mis. Dimsum Ayam butuh Kulit Dimsum + Daging Ayam + Bumbu) — dipakai POS untuk **otomatis** cek & potong stok bahan saat kasir checkout, tanpa kasir perlu pilih apapun secara manual.

## Konsep Penting (WAJIB DIBACA)

- **Cara utama kelola resep SEKARANG lewat form Master → Produk Jual** (section "Komposisi/Resep"), bukan lewat halaman ini. Resep yang sudah terhubung ke sebuah produk otomatis ter-apply di POS begitu kasir klik produknya di grid — **tidak ada tombol pilih resep manual di POS**.
- **Halaman Master Bumbu Pusat ini fungsinya sebagai REFERENSI/OVERVIEW** — lihat semua resep sekaligus (kolom "Produk Terhubung" menunjukkan resep itu dipakai produk yang mana), tanpa harus buka form Produk Jual satu-satu. Klik **Edit** pada resep yang sudah terhubung produk akan otomatis diarahkan ke form Produk Jual.
- **Harga bahan TIDAK disimpan di sini** — selalu diambil otomatis dari Master Barang (`harga_jual`/`harga_beli_terakhir`). Update harga di Master Barang, semua resep yang memakai bahan itu otomatis ikut harga baru.

## ⚠️ Jangan Bikin Resep Baru Lewat Tombol "Tambah Resep" di Halaman Ini

Tombol **"Tambah Resep"** di halaman ini membuat resep **TANPA terhubung ke produk manapun** — resep seperti ini **TIDAK PERNAH dipakai POS** (POS cuma menemukan resep lewat produk yang di-klik kasir, bukan lewat daftar resep independen). Kalau butuh resep baru untuk sebuah produk, buat lewat **Master → Produk Jual → Edit produk → section "Komposisi/Resep"** — supaya resep otomatis terhubung dan langsung dipakai POS.

## Langkah-langkah — Lihat Overview Resep

1. Buka menu **Master Bumbu Pusat** dari sidebar
2. Daftar semua resep tampil dengan kolom: Nama, Kode, **Produk Terhubung**, Jenis Menu, Jumlah Bahan, Status
3. Kolom **"— (belum terhubung produk)"** menandakan resep itu orphan (dibuat lewat cara lama/tombol Tambah Resep) — cek dan hubungkan manual lewat database kalau perlu, atau abaikan kalau memang tidak dipakai
4. Klik ikon pensil pada resep yang **sudah** terhubung produk → diarahkan ke form Produk Jual section Komposisi/Resep
5. Klik ikon nonaktifkan (⏸) untuk menyembunyikan resep dari POS — order lama yang pernah pakai bahan dari resep ini tetap aman

## Satuan "pcs" — untuk Bahan Hitungan Satuan

- Cocok untuk bahan yang dihitung per buah/satuan, bukan berat — misalnya telur, kemasan pcs, dll
- Takaran (`qty_per_unit`) untuk satuan **pcs** berarti "jumlah pcs per 1 unit produksi", **tidak dikonversi** (beda dengan gram/ons yang dikonversi ke kg)

## Aturan Penting

- Master Bumbu Pusat hanya bisa dikelola Owner (dan role yang di-grant permission `master.resep_bumbu.*` lewat UI Role & Hak Akses)
- **Jangan input harga di sini** — harga bahan selalu ikut Master Barang
- Kalau ada bahan yang belum ada di Master Barang, tambah dulu itemnya di sana (tipe `bahan_baku`/`kemasan`), baru bisa dipakai di resep
- Perubahan resep **tidak memengaruhi order lama** yang sudah pernah disubmit
- Produk **tanpa resep** tetap berfungsi normal di POS — cukup potong 1 unit produk itu sendiri (tidak breakdown bahan)

## Troubleshooting

- **Produk baru tidak potong stok bahan bakunya di POS?** Cek apakah produk itu sudah punya resep — buka form Produk Jual → Edit produk → cek section Komposisi/Resep, atau cek kolom "Produk Terhubung" di halaman ini
- **Bikin resep lewat "Tambah Resep" tapi tidak berpengaruh ke POS?** Sesuai peringatan di atas — resep dari tombol ini TIDAK terhubung produk manapun. Hapus/abaikan, lalu buat ulang lewat form Produk Jual
- **Item tidak muncul di dropdown Bahan (saat edit resep lewat Produk Jual)?** Cek Master Barang, pastikan item aktif & tipenya bahan_baku/kemasan
- **Menu tidak muncul di sidebar?** Perlu izin `master.resep_bumbu.view` — minta Owner mencentang di Pengaturan → Role & Hak Akses
MARKDOWN
            ,
            'modul'  => 'penjualan',
            'urutan' => 100,
            'aktif'  => true,
        ]);
    }

    private function laporanKonsumsiBahanUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-konsumsi-bahan'], [
            'judul'  => 'Laporan Konsumsi Bahan Baku',
            'konten' => <<<'MARKDOWN'
## Tujuan
Melihat bahan baku apa saja yang terpakai dalam periode tertentu, lengkap dengan qty dan nilai HPP (Harga Pokok Produksi). Dipakai untuk evaluasi biaya bahan baku bulanan, membandingkan modal vs omzet, prediksi kebutuhan pembelian, dan deteksi anomali (bahan boros/hilang).

## Data Dihitung Dari

- Setiap order jasa giling / produk jadi otomatis mencatat qty bahan yang keluar
- HPP dihitung otomatis dari FIFO harga beli (`stock_batches`) — sama seperti yang dipakai Dashboard Stok
- Data tersimpan di `order_items.hpp` saat order dibuat, laporan ini murni membaca & mengagregasi data tersebut (tidak menulis apapun)

## Cara Baca Laporan

1. **Filter Periode** — pilih tanggal (default **Bulan Ini**), atau pakai tombol cepat: Hari Ini, Kemarin, Minggu Ini, Bulan Ini, Bulan Lalu, 3 Bulan, dst
2. **Filter Cabang** — Semua Cabang (kalau kamu punya akses semua) atau cabang tertentu
3. **Filter Tipe** — Semua Tipe / Bahan Baku / Kemasan / Produk Jadi (fokus ke bahan baku saja kalau cuma mau lihat biaya produksi murni)
4. Klik **Filter** → data update sesuai pilihan

## Yang Bisa Kamu Lihat

- **Kartu Ringkasan** — total item unik, **Total Omzet**, **Total HPP**, **Total Untung** (+ margin %), item yang paling banyak terpakai (qty), item dengan HPP termahal
- **Tabel Breakdown per Item** — setiap item dengan qty terpakai, Omzet, HPP, **Untung**, **Margin**, dan persentase HPP dari total (klik judul kolom untuk sort)
- **Chart Bar Top 10** — visual quick-glance item dengan HPP terbesar
- **Chart Trend 6 Bulan** — line chart total HPP per bulan, untuk analisa jangka panjang
- **Detail Transaksi** — audit per order (waktu, no order, item, qty, HPP, kasir), dikelompokkan per tanggal kalau periode lebih dari 1 hari

## Kartu Ringkasan vs Tabel Breakdown — Kenapa Bisa Beda Angka

Kartu **Total Omzet** di ringkasan menghitung **SEMUA** baris transaksi di periode itu, termasuk baris "Jasa Giling" murni (biaya jasa gilingnya sendiri, bukan bumbu) yang **tidak** terhubung ke item di Master Barang. Tabel **Breakdown per Item** di bawahnya cuma bisa menampilkan baris yang punya item — jadi kalau kamu jumlahkan kolom Omzet di tabel, hasilnya bisa lebih kecil dari kartu Total Omzet. Ini **normal**, bukan bug — selisihnya persis sebesar omzet dari jasa giling itu sendiri (akan muncul catatan info di halaman kalau ini terjadi). Untuk lihat detail lengkap termasuk baris jasa giling, buka **Laporan Laba Rugi**.

## Cara Cek Profit Real

**Total Untung** di kartu ringkasan **SUDAH** merepresentasikan profit real (Omzet − HPP, termasuk jasa giling) — tidak perlu hitung manual lagi. Angka ini belum termasuk gaji karyawan, sewa, listrik, dan biaya operasional lain (itu ranahnya Laporan Keuangan).

## Aturan Penting

- Angka HPP **tidak berubah** walau harga jual ke pelanggan berbeda-beda — HPP dihitung dari harga beli bahan (FIFO), bukan dari harga jual
- Order yang **dibatalkan tidak dihitung** di laporan ini
- Kalau bahan diambil manual (bukan lewat order, mis. adjustment stok) → tidak tampil di sini, cek di Kartu Stok / Laporan Stok
- Laporan ini murni **baca data existing** — tidak mengubah angka di Laporan Penjualan, Laporan Keuangan, atau Dashboard Stok manapun
- Untuk analisis profit lebih detail (per kategori, per jenis olahan, per order, dengan chart pie kontribusi) — lihat menu **Laporan Laba Rugi**

## Troubleshooting

- **Angka lebih kecil dari perkiraan?** Pastikan filter periode sudah benar, cek juga bagian Detail Transaksi untuk lihat rinciannya
- **Item tidak muncul di laporan?** Order-nya kemungkinan belum disubmit atau berstatus dibatalkan
- **Kolom Margin menampilkan "-"?** Baris itu omzetnya Rp 0 (biasanya bumbu mode "Gratis") — margin tidak bisa dihitung dari pembagi nol
- **Export gagal / lambat?** Coba persempit filter periode dulu
- **Menu tidak muncul di sidebar?** Perlu izin `laporan.konsumsi_bahan.view` — minta Owner mencentang di Pengaturan → Role & Hak Akses
MARKDOWN
            ,
            'modul'  => 'laporan',
            'urutan' => 101,
            'aktif'  => true,
        ]);
    }

    private function laporanLabaRugiUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-laba-rugi'], [
            'judul'  => 'Laporan Laba Rugi',
            'konten' => <<<'MARKDOWN'
## Tujuan
Analisis profit real dari setiap transaksi — bandingkan Omzet (yang masuk) dengan HPP (modal bahan) untuk tahu untung/rugi per item, per kategori, per jenis olahan, atau per order.

## Perbedaan dengan Laporan Keuangan

- **Laporan Keuangan** — cash flow (uang masuk vs uang keluar, termasuk gaji, sewa, operasional)
- **Laporan Laba Rugi** — profit produksi (Omzet vs HPP bahan) — **TIDAK** termasuk biaya operasional, ini murni gross profit dari jualan

## Cara Baca

1. **Filter periode + cabang** (default Bulan Ini, sama seperti laporan lain)
2. **Pilih Level Breakdown** di dropdown:
   - **Per Item** — lihat setiap bahan/produk satu-satu
   - **Per Kategori** — Bahan Baku vs Kemasan vs Produk Jadi vs **Jasa** (biaya jasa giling itu sendiri)
   - **Per Jenis Olahan** — Bakso vs Sosis vs Tempura
   - **Per Order** — transaksi individual (No Order, Pelanggan, Kasir)
3. **Kartu Ringkasan** — Total Order, Total Omzet, Total HPP, Total Untung, Margin %
4. **Tabel Detail** — klik judul kolom (Omzet/HPP/Untung/Margin) untuk sortir
5. **Chart** — Top 10 item untung terbesar, trend untung 6 bulan, dan (khusus level Per Kategori) pie chart kontribusi tiap kategori

## Rumus

```
Untung  = Omzet − HPP
Margin% = (Untung / Omzet) × 100
```

## Kategori "Jasa"

Baris "Jasa Giling" (biaya jasa itu sendiri, bukan bumbu) biasanya tidak terhubung ke item di Master Barang — di breakdown Per Kategori, baris ini dikelompokkan sebagai **"Jasa"**. HPP-nya selalu Rp 0 (tidak ada biaya bahan dari baris ini) jadi 100% jadi untung — itu wajar, karena ongkos tenaga kerja & operasional gilingnya sendiri tidak dilacak sebagai HPP di sistem ini.

## Item Berlabel "Gratis"

Bumbu dengan mode "Gratis" di Master Resep akan tampil **rugi** di level Per Item (HPP > 0, Omzet = Rp 0). Ini **normal** — biayanya sudah disubsidi oleh jasa giling / produk jadi (harga bahan itu sudah termasuk di tarif jasa giling). Untuk lihat profit real, lihat kartu ringkasan total (bukan baris gratis satu-satu), atau lihat level **Per Order** yang menggabungkan semua baris dalam 1 transaksi.

## Interpretasi Angka

- Untung Rp 10 juta (bulan ini) = **gross profit** dari operasional produksi
- **Belum termasuk** gaji karyawan, sewa, listrik (itu ada di Laporan Keuangan)
- Untuk **net profit** real = Untung Laba Rugi − Total Pengeluaran Operasional (dari Laporan Keuangan)

## Catatan Omzet & Diskon

Kolom Omzet dihitung dari harga per baris item **sebelum diskon** (diskon dicatat di level order, bukan per baris) — kalau order dapat diskon, jumlah Omzet di sini bisa sedikit lebih tinggi dari Total Bayar aktual di Laporan Penjualan. Pola ini sama seperti "Produk Terlaris" di Laporan Penjualan.

## Aturan Penting

- Order yang **dibatalkan tidak dihitung**
- Laporan ini murni **baca data existing** — tidak mengubah angka di Laporan Penjualan, Laporan Keuangan, atau Laporan Konsumsi Bahan Baku manapun
- Kartu ringkasan di sini akan **sama persis** dengan kartu ringkasan Laporan Konsumsi Bahan Baku kalau filter periode & cabang-nya sama — kalau beda, laporkan sebagai bug

## Troubleshooting

- **Untung negatif total?** Cek harga jual < harga bahan (mungkin harga bahan naik, perlu adjust harga jual) — lihat breakdown Per Item untuk cari penyebabnya
- **Margin sangat kecil (< 20%)?** Evaluasi harga jual atau cari supplier bahan yang lebih murah
- **Item paling untung tidak masuk daftar Top 10?** Coba perpanjang periode filter, atau cek apakah item itu memang jarang terjual di periode ini
- **Menu tidak muncul di sidebar?** Perlu izin `laporan.laba_rugi.view` — minta Owner mencentang di Pengaturan → Role & Hak Akses
MARKDOWN
            ,
            'modul'  => 'laporan',
            'urutan' => 102,
            'aktif'  => true,
        ]);
    }

    private function poDashboardUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'po-dashboard'], [
            'judul'  => 'Dashboard PO',
            'konten' => <<<'MARKDOWN'
## Tujuan
Monitoring Purchase Order end-to-end (draft s/d dibayar) + efisiensi input Kas Keluar langsung dari PO. Ada 3 fitur baru yang saling terkait:

1. **Field "Pilih PO"** di form Kas Keluar — auto-isi Jumlah, Kategori, Keterangan dari PO yang dipilih
2. **Widget "Status PO"** di Dashboard utama — ringkasan cepat 5 status
3. **Menu "Dashboard PO"** (halaman ini) — monitoring lengkap dengan filter, chart, dan quick action

## Alur Umum PO

```
Draft → Disetujui → Dikirim ke Supplier → Diterima → Dibayar
```

Setiap tahap dicatat waktunya (kapan dibuat, kapan disetujui, kapan dikirim, kapan diterima) untuk menghitung **umur** — berapa lama PO "nyangkut" di tahap itu.

## Cara Baca Dashboard Ini

1. **Kartu Ringkasan (5 kartu)** — jumlah PO + total nilai per status:
   - **Menunggu Approval** — PO draft, belum disetujui
   - **Perlu Dikirim** — sudah disetujui, belum dikirim ke supplier
   - **Dalam Perjalanan** — sudah dikirim, belum diterima barangnya
   - **Belum Diterima (total)** — gabungan 3 status di atas, total PO yang belum sampai fisik
   - **Belum Dibayar** — sudah diterima tapi belum ada transaksi pembayaran tercatat
2. **Filter** — status, cabang, supplier, umur minimal (hari), rentang tanggal PO
3. **Chart Bar** — jumlah PO per status (quick visual)
4. **Chart Line** — trend jumlah PO dibuat per bulan, 6 bulan terakhir
5. **Tabel PO Aktif** — semua PO (kecuali dibatalkan), klik judul kolom Total/Umur untuk sortir

## Badge Umur (Visual — Bukan Alert)

| Warna | Umur | Arti |
|---|---|---|
| 🔵 Biru | 0-3 hari | Masih wajar |
| 🟡 Kuning | 4-7 hari | Mulai perlu diperhatikan |
| 🔴 Merah | > 7 hari | Segera follow up |

Badge ini **murni visual** — tidak ada notifikasi otomatis atau alert terpisah (fitur itu di luar scope saat ini).

## Cara Pakai — Tindak Lanjuti PO yang Stuck

1. Cek Dashboard PO → lihat status & badge umur masing-masing
2. Klik nomor PO atau tombol aksi di kolom "Aksi" → navigate ke Detail PO untuk approve/kirim/proses terima (pakai alur yang sudah ada, dashboard ini cuma quick-navigate)
3. Untuk PO yang statusnya **Diterima** tapi **Belum Dibayar**: klik **"Catat Pembayaran"** → otomatis dibawa ke form Kas Keluar dengan PO tersebut **sudah terpilih**
4. Form Kas Keluar otomatis terisi (Jumlah, Kategori, Keterangan) — sesuaikan kalau perlu, lalu **Simpan**
5. PO otomatis tertandai "Sudah Dibayar" — hilang dari kartu "Belum Dibayar" dan widget dashboard

## Field "Pilih PO" di Kas Keluar

- Dropdown ini **cuma alat bantu isi form** — field Jumlah/Kategori/Keterangan yang sudah ada tetap bisa diedit manual sebelum Simpan
- Kalau PO sudah pernah dibayar, PO itu **tidak akan muncul lagi** di dropdown (sudah dianggap lunas)
- Sistem **mencegah bayar dobel** — kalau ada percobaan mencatat pembayaran untuk PO yang sudah tercatat lunas, akan ditolak dengan pesan error
- Kalau dropdown gagal dimuat (koneksi lambat dsb), form tetap bisa diisi manual seperti biasa

## Status Pembayaran di Detail PO

Di halaman Detail PO (untuk PO berstatus Diterima), ada section baru "Status Pembayaran":
- Badge hijau **"Sudah Dibayar"** + link ke transaksi terkait
- Badge kuning **"Belum Dibayar"** + tombol "Catat Pembayaran" (langsung ke Kas Keluar)

## Aturan Penting

- Dashboard ini murni **baca data existing** — tidak mengubah alur approve/kirim/terima PO manapun
- Quick action di tabel cuma **navigate** ke halaman yang sudah ada (Detail PO / Kas Keluar) — bukan aksi langsung dari dashboard ini
- Widget di Dashboard utama & halaman ini pakai sumber data yang sama (`PoDashboardService`) — angkanya selalu konsisten

## Troubleshooting

- **Widget/menu tidak muncul?** Perlu izin `po_dashboard.view` — minta Owner mencentang di Pengaturan → Role & Hak Akses
- **Tombol aksi (Setujui/Kirim/Catat Pembayaran) tidak muncul?** Perlu izin tambahan `po_dashboard.action` — beda dari `po_dashboard.view` yang cuma untuk lihat
- **Dropdown "Pilih PO" kosong?** Berarti tidak ada PO berstatus Diterima yang belum dibayar saat ini — itu kabar baik, semua sudah lunas
- **PO tidak hilang dari "Belum Dibayar" setelah dicatat?** Cek apakah transaksi benar-benar tersimpan (lihat Riwayat Transaksi Keuangan), atau refresh halaman
MARKDOWN
            ,
            'modul'  => 'pembelian',
            'urutan' => 103,
            'aktif'  => true,
        ]);
    }

    private function cleanupToolUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'cleanup-tool'], [
            'judul'  => 'Cleanup Tool (Link PO / Assign Kas / Sinkron Saldo)',
            'konten' => <<<'MARKDOWN'
## Tujuan

Membereskan data historis yang "bolong" — dicatat sebelum atau di luar alur normal aplikasi — **tanpa** query SQL manual ke database. Ada 3 alat, masing-masing untuk masalah yang beda:

1. **Link Transaksi ke PO** — PO sudah dibayar fisik tapi sistem masih tampil "Belum Dibayar"
2. **Assign Kas** — transaksi keuangan yang tidak pernah mengurangi/menambah saldo kas manapun
3. **Sinkron Saldo Kas** — saldo di Menu Kas beda dengan hasil hitungan dari riwayat transaksi

Ketiganya **Owner-only secara default** — role lain harus dicentang manual di Pengaturan → Role & Hak Akses kalau mau diberi akses.

## Kapan Pakai

| Gejala | Alat |
|---|---|
| Detail PO tampil "Belum Dibayar" padahal sudah ditransfer/dibayar tunai ke supplier | **Link Transaksi ke PO** |
| Kolom "Kas" kosong di Daftar Transaksi Keuangan, padahal harusnya ada uang yang keluar/masuk | **Assign Kas** |
| Total saldo di Menu Kas tidak cocok dengan Laporan Keuangan untuk kas yang sama | **Sinkron Saldo Kas** |

## 1. Link Transaksi ke PO

**Lokasi:** Detail PO → section "Status Pembayaran" → tombol **"Cari Transaksi Existing"** (muncul kalau status masih "Belum Dibayar").

Cara pakai:
1. Klik "Cari Transaksi Existing" — sistem cari transaksi **Pengeluaran** di cabang yang sama, ± 3 hari dari tanggal terima PO, yang **belum ter-link ke sumber manapun**
2. Baris dengan latar hijau + badge "Nominal cocok" = nominal transaksi persis sama dengan total PO (kandidat paling kuat)
3. **Cek manual** tanggal, nominal, dan keterangan sebelum klik **Link** — jangan asal klik baris teratas
4. Setelah di-Link: status PO otomatis berubah "Sudah Dibayar", widget Dashboard PO ikut update

**Catatan:** Link tidak mengubah saldo kas — transaksi itu sudah pernah mengurangi saldo kas saat pertama kali dicatat, Link cuma menambah info "transaksi ini untuk PO yang mana".

## 2. Assign Kas

**Lokasi:** Menu Kas & Keuangan (halaman Daftar Transaksi) → card kuning **"Transaksi Tanpa Kas Sumber"** (muncul otomatis kalau ada datanya, di atas tabel transaksi).

Cara pakai:
1. Lihat daftar transaksi yang kas_id-nya kosong
2. Klik **"Assign Kas"** di baris yang mau dibetulkan
3. Pilih Kas yang benar (sesuai cabang transaksi itu)
4. Simpan — saldo kas yang dipilih otomatis bertambah (kalau Pemasukan) atau berkurang (kalau Pengeluaran) sesuai nominal transaksi

**Catatan:** Transaksi dari Adjustment Stok (beban kerugian susut/rusak/hilang) **sengaja tidak muncul** di daftar ini — itu memang didesain tanpa kas fisik (bukan celah data).

## 3. Sinkron Saldo Kas

**Lokasi:** Menu Kas & Keuangan → Kelola Kas → tombol **"Sinkron Saldo"** di kartu kas.

Cara pakai:
1. Klik "Sinkron Saldo" — sistem hitung ulang seharusnya berapa saldo kas ini berdasarkan riwayat transaksi
2. Modal preview menampilkan: Saldo Sekarang (di sistem) vs Seharusnya (hasil hitungan) vs Selisih
3. Kalau **tidak ada selisih** — tombol "Sinkronkan" tidak muncul, tidak perlu aksi apapun
4. Kalau **ada selisih** — klik "Sinkronkan" untuk update saldo ke nilai hasil hitungan

**Catatan:** Rumus hitungan otomatis menyesuaikan apakah kas ini sudah punya transaksi "Saldo awal kas" tercatat atau belum (kas baru vs kas lama) — supaya saldo awal tidak dihitung dobel.

## Aturan

- **Owner-only** oleh default — verifikasi manual per baris sebelum aksi, sistem tidak pernah menebak/auto-link secara massal
- **Semua aksi tercatat di Audit Log** (siapa, kapan, apa yang diubah) — bisa dicek di menu Keamanan → Audit Log
- **Reversibel** — kalau salah Link/Assign, gunakan aksi yang sesuai lagi untuk koreksi (mis. Assign Kas ke kas yang benar setelah salah pilih), bukan hapus data

## Troubleshooting

- **Tombol/menu tidak muncul?** Perlu izin `transaksi.link_po.action` / `transaksi.assign_kas.action` / `kas.sinkron_saldo.action` — minta Owner mencentang di Pengaturan → Role & Hak Akses (Owner sendiri selalu bisa akses tanpa perlu dicentang)
- **"Cari Transaksi Existing" tidak menemukan apa-apa?** Transaksi pembayarannya mungkin di luar rentang ± 3 hari dari tanggal terima PO, atau sudah ter-link ke sumber lain — cek manual di Daftar Transaksi Keuangan
- **Assign Kas ditolak "sudah punya Kas Sumber"?** Transaksi itu sudah pernah di-assign sebelumnya, tidak perlu diulang
MARKDOWN
            ,
            'modul'  => 'keuangan',
            'urutan' => 104,
            'aktif'  => true,
        ]);
    }

    private function posUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'pos'], ['konten' => <<<'MARKDOWN'
## Tujuan
Mencatat transaksi penjualan dimsum/gyoza harian dari pelanggan ke sistem — grid produk dengan gambar, tipe transaksi (Dine-in/Takeaway/Frozen), varian produk, split payment, dan cetak struk.

## Langkah-langkah

1. Buka menu **POS** dari sidebar
2. Pilih **Tipe Transaksi**: Dine-in / Takeaway / Frozen (cuma tipe yang aktif di outlet ini yang muncul)
   - **Dine-in** dengan Nomor Meja aktif → wajib isi nomor meja
   - **Takeaway** → otomatis kena Takeaway Fee kalau outlet men-setnya
3. Klik produk di grid untuk menambah ke keranjang:
   - Produk **tanpa varian** → langsung masuk keranjang
   - Produk **dengan varian** (badge "Ada Varian", mis. Dimsum Mentai Size S/M/L) → muncul modal pilih atribut, harga menyesuaikan varian yang dipilih
   - Produk yang badge-nya **"Stok Habis"** (bahan baku resepnya habis) otomatis tidak bisa diklik
4. **Item Tambahan** (garpu, saus extra, dll) — section terpisah di bawah grid, klik untuk tambah
5. **Custom Item** — tombol di bawah grid, untuk item bebas di luar menu (nama + harga manual)
6. *(Opsional)* Isi diskon (Rp atau %) dan data pelanggan
7. Pilih salah satu:
   - **Save Bill** — simpan keranjang, belum bayar, bisa dilanjut nanti (stok belum berkurang)
   - **Print Bill** — preview cetak sebelum bayar (bukan struk final)
   - **Bayar / Charge** — buka modal pembayaran
8. Di modal pembayaran: pilih metode (Tunai/QRIS/Transfer), atau klik **+ Tambah Metode Bayar** untuk **split payment** (mis. sebagian tunai + sebagian QRIS)
9. Klik **Proses Pembayaran** → stok berkurang otomatis (FIFO, termasuk breakdown bahan baku kalau produknya punya resep), modal struk muncul otomatis

## Mencetak Struk (Modal Struk — Baru!)

Struk tidak lagi membuka tab baru, melainkan muncul sebagai **modal di dalam halaman** — lebih aman untuk PWA dan tablet.

- Centang **Cetak struk otomatis** agar modal langsung muncul setelah proses
- Di modal tersedia pratinjau struk dan tombol **Cetak Struk**
- Jika modal tidak muncul otomatis, klik tombol **Struk** di notifikasi sukses yang tampil

## Cetak 2 Salinan Sekaligus

Dropdown **Salinan** muncul otomatis ketika **Cetak Otomatis** dicentang:
- **1×** — cetak satu salinan (default)
- **2×** — cetak dua salinan dalam satu job cetak, dipisahkan garis potong (- - -)
- Berguna agar kasir dan pelanggan masing-masing punya bukti fisik

## Cetak Ulang Struk

Jika perlu cetak ulang struk transaksi yang sudah selesai:
1. Buka menu **Penjualan → Riwayat Penjualan**
2. Temukan transaksi yang dicari
3. Klik **ikon printer** di kolom Aksi → modal struk muncul, klik **Cetak Struk**

## Footer Struk per Cabang

Setiap cabang bisa punya teks footer sendiri di bagian bawah struk (ucapan terima kasih, nomor WA, info promo). Diatur oleh Owner/Manajer di menu **Pengaturan → Cabang → Edit Cabang → bagian Struk POS**.

## Sembunyi Harga per Item di Struk (Opsional per Cabang)

Untuk cabang yang sering dapat komplain pelanggan soal harga bahan tertentu ("kok tepung mahal amat"), Owner bisa mengizinkan struk dicetak **tanpa harga per item** — cuma nama, qty, dan **TOTAL** akhir yang tercetak.

**Cara aktifkan (Owner):**
1. Buka **Pengaturan → Cabang → Edit Cabang** (cabang yang dituju)
2. Di bagian **Struk POS**, centang **"Izinkan kasir sembunyi harga per item di struk"**
3. Simpan

**Setelah diaktifkan, di POS akan muncul checkbox baru** dekat tombol Proses: **"Tampilkan harga per item di struk"** — defaultnya **TIDAK dicentang** (harga per item disembunyikan, TOTAL tetap selalu tercetak). Kasir bisa centang manual per transaksi kalau pelanggan minta rincian harga.

**Cabang yang tidak diizinkan** tidak melihat checkbox ini sama sekali — struk tetap tampil harga penuh seperti biasa (tidak ada perubahan apapun).

**Catatan penting:**
- Ini murni preferensi tampilan cetak — data harga di database **tidak berubah sama sekali**, tetap tersimpan lengkap
- **Riwayat Order / Detail Order** (bukan struk) selalu tampil harga penuh untuk kebutuhan audit, terlepas dari setting ini
- Berlaku juga untuk cetak Bluetooth (printer thermal), bukan cuma tampilan di layar
- **Cetak ulang** struk dari Riwayat Penjualan defaultnya **sembunyi harga** (kalau cabang mengizinkan) — preferensi checkbox saat transaksi asli tidak tersimpan/diingat, jadi perlu dicentang ulang manual tiap kali cetak ulang kalau memang ingin tampil harga

## Mode Tampilan Tablet

Tombol **Desktop / Tablet** di pojok kanan atas POS mengubah tampilan:
- **Desktop** — tampilan normal dengan sidebar & navbar
- **Tablet** — sembunyikan sidebar & navbar, elemen diperkecil agar muat di layar 9–10 inci landscape

Mode ini tersimpan otomatis (localStorage) dan aktif saat buka POS lagi. Cocok untuk tablet kasir yang selalu terpasang di meja.

## Update Real-Time Antrian

Setelah transaksi berhasil, sistem secara otomatis memperbarui antrian produksi tanpa perlu refresh manual. Frozen **tidak masuk antrian** (siap saji instan, tidak perlu disiapkan seperti dine-in/takeaway).

## Save Bill & Charge Nanti

Bill tersimpan (belum dibayar) muncul di section **"Bill Tersimpan"**, di PALING BAWAH halaman POS (di bawah tombol "PROSES ORDER"/"Save Bill" utama — supaya tidak mengganggu alur bikin transaksi baru). Ada 3 aksi per bill:
- **Tunai Pas** — bayar cepat 1 klik, tunai sejumlah tagihan persis (tanpa kembalian, tanpa modal tambahan)
- **Bayar...** — buka modal pembayaran lengkap (pilih metode Tunai/Transfer/QRIS, bisa Split Payment 2 metode, bisa pilih Kas manual), untuk bayar dengan metode selain tunai pas
- **Batalkan** (ikon silang merah) — HANYA muncul untuk user dengan izin "Batalkan Bill Tersimpan" (`order.bill_tersimpan.batalkan`, default: admin_pusat). Membatalkan bill yang salah/tidak jadi, tanpa proses lebih lanjut — aman karena bill Pending belum pernah potong stok atau catat kas sama sekali.

Stok baru benar-benar dipotong saat bill dibayar (via "Tunai Pas" atau "Bayar..."), bukan saat disimpan — jadi stok bisa berubah antara saat Save Bill dan saat dibayar kalau ada order lain yang menghabiskan stok duluan (sistem cek ulang otomatis saat dibayar).

## Split Payment (1 Transaksi, Beberapa Metode Bayar)

Klik **+ Tambah Metode Bayar** di modal pembayaran untuk membagi 1 tagihan ke beberapa metode (mis. Rp50.000 Tunai + Rp30.000 QRIS).

## Varian Produk

Produk dengan badge **"Ada Varian"** (mis. Dimsum Mentai) akan menampilkan modal pilih atribut (Size, Rasa, dll) saat diklik. Harga otomatis menyesuaikan kombinasi yang dipilih. Stok tetap dicek di level produk induk (bukan per-varian) lewat resep produksinya.

## Auto Potong Stok via Resep

Produk yang punya **resep produksi** (diatur di menu Master Resep) otomatis memotong stok SETIAP BAHAN komposisinya saat dibayar (bukan stok produk itu sendiri). Produk tanpa resep memotong stok dirinya sendiri langsung. Kalau salah satu bahan resep habis, produk otomatis ter-disable di grid dengan badge "Stok Habis".

## Catatan Penting

- Stok DIPOTONG saat klik **Bayar/Charge**, bukan saat klik produk atau Save Bill
- QRIS/Transfer: upload bukti bersifat opsional, bisa dilengkapi nanti di Riwayat Penjualan
- Untuk membatalkan transaksi, hubungi Manajer Cabang
- Transaksi besar otomatis kirim notifikasi ke management
- **Split Bill** (bagi tagihan ke beberapa orang) belum tersedia — tombolnya masih placeholder

## Troubleshooting

- **Produk ter-disable "Stok Habis"?** Cek resep produksinya di Master Resep — salah satu bahan komposisinya kosong di cabang ini
- **Modal varian tidak muncul apa-apa?** Pastikan semua atribut sudah dipilih — kombinasi yang belum lengkap tidak akan menampilkan harga
- **"Cabang belum punya Kas default"?** Hubungi Admin/Owner untuk aktifkan Kas kategori metode bayar itu di outlet ini
- **Modal struk tidak muncul?** Klik tombol **Struk** di halaman Riwayat Penjualan sebagai gantinya
MARKDOWN
        ]);
    }

    private function kategori9Lainnya(): void
    {
        Panduan::where('slug', 'notifikasi')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Melihat dan mengelola semua notifikasi sistem — alert stok, update pembelian, approval, dan info penting lainnya.

## Langkah-langkah

1. Klik **ikon lonceng (🔔)** di navbar untuk melihat notifikasi terbaru (dropdown max 10 item)
2. Klik **Lihat Semua Notifikasi** untuk masuk ke halaman penuh
3. Di halaman notifikasi, gunakan filter:
   - **Status** — Semua / Belum Dibaca / Sudah Dibaca
   - **Kategori** — filter per jenis: Stok / Pembelian / Penjualan / HR / Aset / Keuangan
4. Per notifikasi, tersedia aksi:
   - **Ikon centang** — tandai satu notifikasi sebagai sudah dibaca
   - **Ikon panah kotak** (Buka) — buka halaman yang relevan dengan notifikasi ini
   - **Ikon tempat sampah** — hapus notifikasi
5. Klik **Tandai Semua Dibaca** di header dropdown atau halaman untuk tandai semua sekaligus
6. Badge angka merah di ikon lonceng menunjukkan jumlah **notifikasi belum dibaca**

## Catatan Penting

- Notifikasi dikirim **sesuai role dan cabang** — kamu hanya menerima notifikasi yang relevan denganmu
- Sistem polling notifikasi baru **setiap 30 detik** — tidak perlu refresh halaman manual
- Jenis notifikasi yang diterima:
  - 🔴 **Stok habis / kritis** — diterima Manajer Cabang dan Admin Gudang
  - 🟡 **PO mendesak menunggu approval** — diterima Owner dan Manajer
  - 🟢 **BEP tercapai bulan ini** — diterima Owner dan Manajer
  - 📋 **Periode penilaian dibuka** — diterima semua user di cabang
  - ⏰ **Reminder deadline penilaian** — diterima penilai yang belum isi
- Notifikasi lama (>30 hari dan sudah dibaca) otomatis dibersihkan sistem

## Troubleshooting

- **Tidak ada notifikasi padahal ada event?** Cek apakah role kamu sudah tepat — notifikasi hanya dikirim ke role yang relevan
- **Badge tidak hilang setelah dibaca?** Refresh halaman atau tunggu polling 30 detik berikutnya
MARKDOWN]);

        Panduan::where('slug', 'setoran-harian')->update(['konten' => <<<'MARKDOWN'
## Tujuan
Cek cepat berapa yang harus disetor ke pusat hari ini (atau periode lain) dalam **1 halaman** — tanpa perlu buka Laporan Penjualan, Laporan Keuangan, dan hitung manual satu-satu.

## Langkah-langkah

1. Buka menu **Laporan → Setoran Harian** dari sidebar
2. Default halaman langsung menampilkan data **Hari Ini** — tidak perlu atur filter untuk cek cepat harian
3. Kalau butuh periode lain, atur filter:
   - Tombol cepat: **Hari Ini / Kemarin / Minggu Ini / Bulan Ini / Bulan Lalu**, atau **Custom Range** (isi Dari — Sampai manual)
   - **Cabang** (Owner/Admin Pusat) — filter per cabang atau semua cabang
4. Kartu besar **"Setoran ke Pusat (Net)"** = Total Pemasukan − Total Pengeluaran periode tersebut
5. Breakdown **Pemasukan per Metode Bayar** (tunai/QRIS/transfer) dan **Pengeluaran per Kategori** ditampilkan di bawahnya
6. Tab **Order** dan **Pengeluaran** di bagian bawah menampilkan detail transaksi satu-per-satu — kalau periode lebih dari 1 hari, detail otomatis dikelompokkan per tanggal (klik untuk expand)
7. Klik **Print** atau **Export Excel** untuk cetak/unduh laporan

## Catatan Penting

- Angka **Net** di halaman ini selalu sama dengan Laporan Keuangan → Arus Kas untuk periode yang sama (sumber datanya sama persis)
- Breakdown per Metode Bayar diambil dari data Order — bisa sedikit beda dari Total Pemasukan kalau ada transaksi pemasukan manual (bukan dari order) di periode yang sama
- Kalau mencetak/export periode **lebih dari 7 hari**, sistem akan tanya konfirmasi dulu (supaya tidak salah cetak puluhan halaman tanpa sadar)
- Kategori pengeluaran yang tampil "Lain-lain (belum dikategorikan)" artinya transaksi itu dicatat **sebelum** fitur kategori pengeluaran ini ada — bukan error

## Troubleshooting

- **Menu tidak muncul di sidebar?** Perlu izin khusus `laporan.setoran_harian.view` — minta Owner untuk mencentangnya di **Pengaturan → Role & Hak Akses**
- **Tombol Print/Export tidak muncul?** Sudah bisa lihat halaman tapi belum ada izin cetak/export — sama, minta dicentang izin `laporan.setoran_harian.print`/`.export` terpisah
MARKDOWN]);
    }

    private function dataTerhapusUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'data-terhapus'], [
            'judul'  => 'Data Terhapus',
            'konten' => <<<'MARKDOWN'
## Tujuan

- **Restore** data yang tidak sengaja terhapus (soft-delete) — order, transaksi keuangan, kas, karyawan, aset, cuti, dan +20 jenis data lain
- **Cek detail lengkap** data sebelum memutuskan restore atau hapus permanen
- **Audit trail**: siapa yang menghapus, kapan, dan alasan (kalau ada) — supaya jelas riwayatnya sebelum ambil aksi

## Akses

**Owner-only secara default.** Role lain (termasuk Admin Pusat) **tidak** otomatis dapat akses menu ini — kalau Owner mau delegasikan, centang manual salah satu dari 3 izin berikut di **Pengaturan → Role & Hak Akses**:

| Izin | Untuk Aksi |
|---|---|
| `lihat_data_terhapus` | Lihat daftar & detail data terhapus |
| `restore_data_terhapus` | Memulihkan data |
| `hapus_permanen_data` | Hapus permanen (tidak bisa dikembalikan) |

Ketiganya independen — bisa kasih hanya "lihat" tanpa "restore"/"hapus permanen", misalnya untuk keperluan audit read-only.

## Cara Pakai

1. Buka **Keamanan → Data Terhapus** dari sidebar
2. Pilih tab jenis data di bagian atas (Order, Kas, Transaksi Keuangan, Karyawan, Aset, Cuti, dll.)
3. Klik ikon mata (👁) di baris data untuk buka **halaman Detail** — di situ ada:
   - Info dasar: siapa yang menghapus, kapan, dan alasan (kalau tercatat)
   - Detail lengkap data (mis. Order → items + total + kasir + pelanggan; Kas → riwayat transaksi terakhir; Transaksi Keuangan → referensi PO/Order kalau ada)
   - **Activity Log** — riwayat perubahan (dibuat/diedit/dihapus) untuk data ini
4. **Restore**: klik tombol hijau **Restore**, konfirmasi sekali — data langsung aktif kembali seperti semula (termasuk data terkait untuk beberapa jenis, mis. Cabang/Item/Karyawan yang punya cascade restore)
5. **Hapus Permanen**: klik tombol merah **Hapus Permanen** — muncul modal **2 tahap**:
   - **Tahap 1**: preview data + peringatan "tidak bisa dibatalkan" → klik **Lanjut**
   - **Tahap 2**: ketik persis `HAPUS PERMANEN` di kolom teks untuk mengaktifkan tombol konfirmasi (case-sensitive) → klik **Konfirmasi Hapus Permanen**

## Aturan Penting

- **Hapus Permanen benar-benar menghapus dari database** — beda dari Restore, aksi ini **tidak bisa diurungkan**. Selalu cek halaman Detail dulu sebelum memutuskan.
- **Restore = data kembali seperti sebelum dihapus** — status, relasi, dan nilai kolom lain tidak berubah, cuma tanda "terhapus" yang dicabut
- **"Sistem tidak mencatat" di kolom Dihapus Oleh** artinya data ini dihapus sebelum fitur pencatatan ini aktif (data lama) — bukan bug
- Untuk beberapa jenis data (Cabang, Item, Supplier, Karyawan, Pelanggan, Aset, User, Purchase Order, Order), Restore otomatis ikut memulihkan data anak yang terkait (cascade) — cek ringkasan yang muncul setelah restore untuk detail apa saja yang ikut dipulihkan

## Troubleshooting

- **Menu tidak muncul di sidebar?** Berarti belum ada izin `lihat_data_terhapus` — minta Owner mencentangnya di Pengaturan → Role & Hak Akses (Owner sendiri selalu bisa akses tanpa perlu dicentang)
- **Tombol Restore/Hapus Permanen tidak muncul di halaman Detail?** Sudah bisa lihat data tapi belum ada izin aksinya — sama, minta izin `restore_data_terhapus`/`hapus_permanen_data` dicentang terpisah
- **Tombol Konfirmasi Hapus Permanen tetap abu-abu (disabled)?** Teks yang diketik harus PERSIS `HAPUS PERMANEN` (huruf besar semua, satu spasi) — coba ketik ulang
MARKDOWN
            ,
            'modul'  => 'keamanan',
            'urutan' => 105,
            'aktif'  => true,
        ]);
    }

    private function akuntansiPanduanUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'panduan-akuntansi-index'], [
            'judul'  => 'Panduan Akuntansi — Mulai Dari Sini',
            'konten' => <<<'MARKDOWN'
## Mulai dari Sini Kalau Baru Pertama Pakai Akuntansi ERP

Sistem ERP Berkah Mulyo sekarang punya fondasi akuntansi profesional berstandar **SAK ETAP** (Standar Akuntansi Keuangan Entitas Tanpa Akuntabilitas Publik — standar akuntansi resmi untuk UMKM di Indonesia). Kalau ini pertama kalinya Anda dengar istilah "Chart of Accounts" atau "kode akun", halaman ini adalah titik awal yang tepat.

## Urutan Baca yang Disarankan

1. **[Apa itu Chart of Accounts?](/panduan/chart-of-accounts-overview)** — konsep dasar, kenapa penting, manfaatnya untuk Berkah Mulyo
2. **[Referensi Lengkap Kode Akun](/panduan/chart-of-accounts-referensi)** — tabel semua 59 kode akun yang tersedia
3. **[Cheat Sheet: Skenario Sehari-hari](/panduan/chart-of-accounts-cheat-sheet)** — "kalau kejadiannya begini, kodenya apa?"
4. **[Alur Kerja Harian](/panduan/chart-of-accounts-workflow)** — bagaimana kasir, Owner, dan proses audit pajak saling terhubung
5. **[Transfer / Perpindahan Dana](/panduan/transfer-dana)** — fitur yang berganti nama dari "Setoran ke Pusat"
6. **[Auto Depresiasi Aset](/panduan/auto-depresiasi)** — penyusutan aset otomatis tiap bulan

## Yang Perlu Anda Tahu Duluan

- **Kasir/Admin tidak perlu hafal kode akun.** Semua tetap input transaksi seperti biasa memilih **kategori simple** (Gaji, Sewa, dll) — sistem otomatis memetakan ke kode akun COA di belakang layar.
- **Tidak ada yang berubah di alur kerja harian POS/Kasir.** Fondasi ini murni untuk kebutuhan laporan keuangan formal (Neraca, Laba Rugi standar) yang akan menyusul di fase berikutnya.
- **Owner/Admin Pusat** yang akan paling banyak berinteraksi dengan menu Chart of Accounts secara langsung (lihat referensi, reklas kategori kalau perlu).
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 106,
            'aktif'  => true,
        ]);

        Panduan::updateOrCreate(['slug' => 'chart-of-accounts-overview'], [
            'judul'  => 'Apa itu Chart of Accounts?',
            'konten' => <<<'MARKDOWN'
## Apa itu Chart of Accounts (COA)?

Bayangkan COA seperti **sistem penomoran arsip** di kantor — setiap map punya nomor dan kategori supaya mudah dicari. Chart of Accounts adalah daftar "nomor arsip" untuk setiap jenis transaksi keuangan: setiap kali ada uang masuk, uang keluar, aset dibeli, atau hutang dicatat, transaksinya "diarsipkan" di bawah kode akun yang sesuai.

Contoh sederhana:
- Uang masuk dari jasa giling → arsip nomor **4-1101** (Penjualan Jasa Giling)
- Bayar gaji karyawan → arsip nomor **6-1101** (Beban Gaji)
- Beli mesin baru → arsip nomor **1-2101** (Mesin Produksi, ini ASET bukan beban!)

## Manfaat untuk Berkah Mulyo

1. **Laporan Neraca otomatis** — begitu semua transaksi punya kode akun, sistem bisa hitung otomatis berapa total Aset, Kewajiban, dan Modal usaha tanpa rekap manual
2. **Laporan Laba Rugi formal** — siap dipakai untuk keperluan audit pajak atau pengajuan pinjaman bank, bukan cuma rekap sederhana
3. **Standar SAK ETAP** — standar resmi untuk UMKM di Indonesia, jadi laporan keuangan Berkah Mulyo "berbicara bahasa yang sama" dengan akuntan, bank, atau kantor pajak

## Kenapa 59 Kode Akun, Bukan Cuma 10?

Kategori transaksi yang sudah ada (Gaji, Sewa, dll) itu untuk kebutuhan operasional harian — simpel dan cukup untuk kasir. Tapi laporan keuangan formal butuh detail lebih (misalnya beda antara "Kas Tunai" dan "Kas Bank", atau antara "Aset Tetap" dan "Akumulasi Depresiasi"-nya). 59 kode akun ini mengikuti struktur standar SAK ETAP yang sudah disesuaikan khusus untuk bisnis jasa giling dan produksi olahan daging seperti Berkah Mulyo.

## FAQ

**Apakah saya harus hafal semua 59 kode ini?**
Tidak. Kasir/Admin tetap input transaksi dengan kategori simple seperti biasa — sistem yang memetakan ke kode akun secara otomatis.

**Siapa yang perlu paham detail COA ini?**
Terutama Owner/Admin Pusat, terutama saat butuh laporan keuangan formal untuk pajak atau pengajuan pinjaman.

**Bisa reklas kalau kategori salah dipetakan?**
Bisa, lewat menu Kategori Transaksi → Edit → pilih Kode Akun COA yang benar. Lihat [Alur Kerja](/panduan/chart-of-accounts-workflow) untuk detail.
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 107,
            'aktif'  => true,
        ]);

        Panduan::updateOrCreate(['slug' => 'chart-of-accounts-referensi'], [
            'judul'  => 'Referensi Lengkap Kode Akun COA',
            'konten' => <<<'MARKDOWN'
## Tabel Referensi Kode Akun

Daftar lengkap kode akun ditampilkan secara **live/dinamis** (langsung dari database, bukan tabel statis di halaman ini) di menu:

**Keuangan → Chart of Accounts** — atau akses langsung ke halaman [Chart of Accounts](/coa)

Di halaman tersebut tersedia:
- **Kolom:** Kode | Nama Akun | Tipe | Saldo Normal | Keterangan
- **Search** — cari berdasarkan kode atau nama akun
- **Filter Tipe** — Aset / Kewajiban / Modal / Pendapatan / HPP / Beban Operasional / Pendapatan Lain / Beban Lain
- **Tombol Print** — cetak/simpan sebagai PDF via dialog print browser

## Struktur Penomoran

| Awalan | Tipe | Saldo Normal |
|---|---|---|
| 1-XXXX | Aset | Debet |
| 2-XXXX | Kewajiban | Kredit |
| 3-XXXX | Modal | Kredit |
| 4-XXXX | Pendapatan | Kredit |
| 5-XXXX | HPP (Harga Pokok Penjualan) | Debet |
| 6-XXXX | Beban Operasional | Debet |
| 7-XXXX | Pendapatan Lain-lain | Kredit |
| 8-XXXX | Beban Lain-lain | Debet |

**Saldo Normal** artinya arah "bertambah" akun tersebut — akun dengan saldo normal Debet nilainya bertambah saat di-debet (kecuali beberapa akun kontra seperti Akumulasi Depresiasi dan Prive Owner yang sengaja dibalik, sudah ditandai di kolom Keterangan).

## Akses

Menu ini memerlukan permission `coa.view` — **Owner-only secara default**. Kalau Admin Pusat perlu akses, Owner bisa centang manual di **Pengaturan → Role & Hak Akses**.
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 108,
            'aktif'  => true,
        ]);

        Panduan::updateOrCreate(['slug' => 'chart-of-accounts-cheat-sheet'], [
            'judul'  => 'Cheat Sheet: Skenario Sehari-hari',
            'konten' => <<<'MARKDOWN'
## Cheat Sheet Cepat

Tabel di bawah menjawab pertanyaan "kalau kejadiannya begini, kodenya apa?" untuk skenario yang paling sering terjadi di operasional Berkah Mulyo. Cheat sheet interaktif dengan search juga tersedia langsung di form **Tambah/Edit Transaksi Keuangan** — klik tombol **"?"** kecil di sebelah label Kategori.

| Skenario | Kategori Simple (yang dipilih kasir) | Kode Akun COA |
|---|---|---|
| Terima uang dari pelanggan jasa giling | Jasa Giling | 4-1101 |
| Jual produk jadi (bakso/sosis/tempura) | Penjualan Produk | 4-1102 |
| Bayar sewa gedung bulanan | Sewa Gedung | 6-1102 |
| Bayar gaji karyawan | Gaji Karyawan | 6-1101 |
| Bayar listrik/air/pulsa | Listrik & Air | 6-1103 |
| Beli tepung/daging (bahan baku) | Pembelian Bahan Baku | 5-1101 |
| Beli bumbu & kemasan | Pembelian Bahan Baku | 5-1102 |
| **Beli mesin bakso baru > Rp 500rb** | Pembelian Aset | **1-2101 (ASET, bukan beban langsung!)** |
| Beli sapu/kemoceng/ATK (peralatan kecil) | Biaya Operasional | 6-1106 |
| Isi bensin motor operasional | Biaya Operasional | 6-1302 |
| Konsumsi karyawan (nasi bungkus, aqua) | Biaya Operasional | 6-1303 |
| Servis/reparasi mesin giling | Perawatan Aset | 6-1105 |
| Kerugian stok (susut/rusak/hilang) | Beban Kerugian Stok | 6-1401 |
| Depresiasi bulanan (otomatis) | Penyusutan Aset | 6-1104 |
| Terima bunga tabungan bank | Pemasukan Lainnya | 7-1101 |
| Bayar cicilan pinjaman bank | — | 8-1102 (bunga) + 2-2101 (pokok) |
| Setoran modal Owner ke sistem | — | 3-1101 (Modal Owner) |
| Prive Owner (ambil uang untuk pribadi) | — | 3-1102 (Prive Owner) |
| **Transfer HO ke Cabang untuk operasional** | Transfer / Perpindahan Dana | **BUKAN transaksi akuntansi** (internal) |
| **Pindah kas tunai ke bank (cabang sama)** | Transfer / Perpindahan Dana | **BUKAN transaksi akuntansi** (internal) |

## Kenapa Ada yang "BUKAN Transaksi Akuntansi"?

Perpindahan dana antar kas (misalnya HO kirim modal ke cabang, atau pindah dari Kas Tunai ke Kas Bank) itu **bukan pendapatan atau beban** — uangnya masih milik perusahaan yang sama, cuma pindah "kantong". Karena itu tidak dipetakan ke kode akun Pendapatan/Beban manapun, supaya Laporan Laba Rugi tidak salah hitung (uang yang cuma pindah tempat tidak boleh dianggap untung/rugi).

## Kenapa Beli Mesin Itu "ASET", Bukan "Beban"?

Ini kesalahan paling umum. Membeli mesin bakso Rp 15 juta itu **bukan biaya bulan itu** — mesinnya masih ada dan dipakai bertahun-tahun. Nilainya dicatat sebagai **Aset (1-2101)**, lalu nilainya "dibebankan" sedikit-sedikit tiap bulan lewat **Depresiasi (6-1104)** selama umur ekonomis mesin tersebut. Kalau langsung dicatat sebagai beban penuh di bulan pembelian, laporan Laba Rugi bulan itu akan tampak rugi besar padahal sebenarnya tidak.
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 109,
            'aktif'  => true,
        ]);

        Panduan::updateOrCreate(['slug' => 'chart-of-accounts-workflow'], [
            'judul'  => 'Alur Kerja Harian Akuntansi',
            'konten' => <<<'MARKDOWN'
## Alur Input Harian (Kasir/Admin)

Tidak ada perubahan sama sekali di alur kerja kasir/admin sehari-hari:
1. Kasir input transaksi POS atau Kas Keluar/Masuk seperti biasa
2. Pilih **kategori simple** yang sudah familiar (Gaji, Sewa, Pembelian Bahan Baku, dll)
3. Sistem **otomatis memetakan** kategori tersebut ke kode akun COA di belakang layar — kasir tidak perlu tahu atau memilih kode akun secara manual

## Alur Review Owner

1. **Buka menu Chart of Accounts** untuk melihat struktur kode akun yang tersedia
2. **Buka Kategori Transaksi** untuk mengecek/mengubah mapping kategori → kode akun kalau ada yang perlu direklas
3. Laporan Neraca dan Laba Rugi Formal (menyusul di fase berikutnya) akan otomatis menggunakan data dari kode akun ini

## Alur Audit Pajak

Begitu Laporan Neraca dan Laba Rugi Formal tersedia (Fase 2), laporan tersebut bisa langsung diekspor sesuai standar SAK ETAP untuk keperluan audit pajak atau pengajuan pinjaman bank — tanpa perlu rekap ulang manual.

## Alur Reklas Manual (Kalau Ada Kesalahan Mapping)

1. Buka **Keuangan → Kategori Transaksi**
2. Klik **ikon pensil** pada kategori yang mau direklas
3. Ubah **Kode Akun COA** ke kode yang benar
4. Simpan — transaksi BARU yang pakai kategori ini otomatis ikut kode akun baru

**Catatan:** mengubah mapping kategori TIDAK mengubah data transaksi lama yang sudah tercatat — hanya mempengaruhi bagaimana transaksi itu dikelompokkan di laporan akuntansi ke depannya. Untuk reklas transaksi yang sudah terlanjur salah kategori sejak awal, edit transaksi tersebut satu per satu di menu Kas & Transaksi.
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 110,
            'aktif'  => true,
        ]);

        Panduan::updateOrCreate(['slug' => 'auto-depresiasi'], [
            'judul'  => 'Auto Depresiasi Aset',
            'konten' => <<<'MARKDOWN'
## Apa itu Depresiasi?

Depresiasi (penyusutan) adalah pengakuan bahwa nilai aset (mesin, kendaraan, peralatan) **berkurang sedikit demi sedikit** setiap bulan karena pemakaian. Ini bukan berarti aset itu benar-benar rusak — tapi secara akuntansi, nilai bukunya harus dikurangi bertahap sampai mencapai nilai residu (nilai sisa) di akhir umur ekonomisnya.

## Kenapa Perlu?

- **Akuntansi:** supaya Laporan Laba Rugi mencerminkan biaya pemakaian aset secara adil per bulan, bukan dibebankan sekaligus saat beli
- **Pajak:** biaya depresiasi bisa jadi pengurang pajak penghasilan usaha (sesuai aturan pajak yang berlaku)

## Formula (Garis Lurus / Straight Line — Metode Default SAK ETAP)

```
Penyusutan per Bulan = (Harga Perolehan − Nilai Residu) ÷ Umur Ekonomis (bulan)
```

Contoh: Mesin giling Rp 12.000.000, nilai residu Rp 0, umur ekonomis 24 bulan → penyusutan Rp 500.000/bulan selama 24 bulan.

Sistem juga mendukung metode **Saldo Menurun** dan **Satuan Produksi** kalau aset tertentu diatur memakainya (lihat form Tambah/Edit Aset).

## Auto Trigger (Otomatis)

Sistem otomatis menghitung penyusutan **setiap tanggal 1 jam 01:00 WIB** untuk semua aset berstatus Aktif. Proses ini **idempotent** — aset yang sudah dihitung untuk bulan itu otomatis dilewati, jadi aman kalau proses berjalan lebih dari sekali.

Setiap penyusutan otomatis mencatat 1 baris **Transaksi Keuangan non-cash** (beban depresiasi, kategori "Penyusutan Aset", kode akun **6-1104**) — tidak memotong saldo Kas manapun (bukan kejadian kas fisik).

## Manual Trigger

Selain auto trigger, ada 2 cara manual:
1. **Per-aset:** Menu Aset → Detail Aset → tab Penyusutan → isi Periode → **Hitung Penyusutan**
2. **Bulk (semua aset sekaligus):** Menu Aset → tombol **"Generate Depresiasi Bulan Ini"** (perlu permission `aset.depresiasi.auto`, default Owner + Admin Pusat)

## Backward Compat

Tombol "Hitung Penyusutan" per-aset yang sudah ada sebelumnya **tetap berfungsi seperti biasa** — sekarang otomatis ikut mencatat Transaksi Keuangan juga (sebelumnya tidak).

## FAQ

**Kalau nilai buku aset sudah sama dengan nilai residu, apa yang terjadi?**
Sistem otomatis skip aset tersebut — tidak ada penyusutan lagi karena nilainya sudah mencapai batas minimum (nilai residu).

**Apakah depresiasi mempengaruhi saldo Kas?**
Tidak. Depresiasi adalah entry non-cash (tidak ada uang fisik yang berpindah), jadi kas_id-nya kosong dan tidak memotong saldo Kas manapun.

**Bagaimana kalau generate 2x untuk bulan yang sama?**
Aman — sistem mendeteksi kalau aset itu sudah punya catatan penyusutan untuk periode tersebut dan otomatis melewatinya (tidak ada duplikat).
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 111,
            'aktif'  => true,
        ]);
    }

    private function laporanNeracaUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-neraca'], [
            'judul'  => 'Laporan Neraca (Balance Sheet)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Melihat posisi keuangan Berkah Mulyo pada satu titik waktu tertentu — berapa total **Aset** (harta), **Kewajiban** (hutang), dan **Modal** (kekayaan bersih) usaha. Berstandar **SAK ETAP**, siap dipakai untuk keperluan audit pajak atau pengajuan pinjaman bank.

## Cara Baca

1. **Pilih Tanggal** — Neraca dihitung "per tanggal ini" (snapshot), bukan rentang periode
2. **Pilih Cabang** — Semua Cabang (konsolidasi) atau cabang tertentu (kalau Anda punya akses semua cabang)
3. Klik **Tampilkan**

## Struktur Laporan

- **Aset** = Aset Lancar (Kas, Piutang, Persediaan Bahan Baku/Barang Jadi/Kemasan) + Aset Tetap (nilai buku setelah dikurangi depresiasi)
- **Kewajiban** = Jangka Pendek (Hutang Usaha ke supplier, Hutang Pajak) + Jangka Panjang (Hutang Bank)
- **Modal** = Modal Owner (bisa di-adjust manual, lihat di bawah) + Laba Ditahan (akumulasi laba bersih sejak awal s/d tanggal yang dipilih)

Rumus dasar akuntansi yang harus selalu benar: **Total Aset = Total Kewajiban + Total Modal**

## Catatan Penting: Kas, Persediaan, dan Nilai Buku Aset SELALU Kondisi Terkini

Sistem ini **tidak menyimpan snapshot historis harian** untuk Kas, Persediaan, dan Nilai Buku Aset — jadi kalau Anda pilih tanggal di masa lalu, ketiga angka itu tetap menampilkan kondisi **hari ini**, bukan kondisi di tanggal tersebut. Hanya **Laba Ditahan** yang benar-benar menghormati tanggal yang dipilih (dihitung dari seluruh transaksi sampai tanggal itu).

## Kalau Neraca Tidak Balance, Apakah Itu Bug?

**Belum tentu.** Untuk data historis yang direkam sebelum sistem ini punya fondasi akuntansi formal (Chart of Accounts), Neraca bisa saja tidak balance persis — misalnya aset yang dulu didata langsung tanpa transaksi Kas pembelian yang match persis, atau modal awal usaha yang masuk lewat jalur lain. Sistem akan menampilkan **selisih**-nya secara jujur beserta catatan penjelasan, bukan memaksakan angka supaya kelihatan balance.

## Modal Owner — Figure yang Bisa Di-Adjust Manual

Owner bisa klik ikon pensil di kartu **Modal** untuk mengubah angka **Modal Owner** secara manual. Ini adalah figure "penyeimbang" (plug) yang sengaja dibuat supaya Neraca bisa di-"true up" seiring waktu, terutama untuk menutup selisih dari data historis pra-sistem. Perubahan ini berlaku global (tidak per cabang/tanggal).

## Export PDF

Tombol **Export PDF** menghasilkan dokumen resmi dengan kop perusahaan, tanggal cetak, dan kolom tanda tangan (Disiapkan oleh / Mengetahui Owner) — siap dicetak atau dilampirkan untuk keperluan audit/pinjaman.

## Akses

Menu ini memerlukan permission `laporan.neraca.view` (dan `laporan.neraca.export` untuk export PDF) — **Owner-only secara default**. Kalau Admin Pusat perlu akses, Owner bisa centang manual di **Pengaturan → Role & Hak Akses**.
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 112,
            'aktif'  => true,
        ]);
    }

    private function laporanLabaRugiFormalUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-laba-rugi-formal'], [
            'judul'  => 'Laporan Laba Rugi Formal',
            'konten' => <<<'MARKDOWN'
## Tujuan
Laporan Laba Rugi resmi berstandar **SAK ETAP**, dikelompokkan per **Chart of Accounts (COA)** — berbeda dari menu **Laporan Laba Rugi** yang sudah ada (analisis gross profit per item/kategori/jenis olahan/order). Laporan ini untuk keperluan formal seperti audit pajak atau pengajuan pinjaman bank.

## Perbedaan dengan Laporan Laba Rugi (Existing)

| | Laporan Laba Rugi (existing) | Laporan Laba Rugi Formal (baru) |
|---|---|---|
| Fokus | Gross profit per item/kategori/order | Struktur akuntansi resmi SAK ETAP |
| Pengelompokan | Item, Kategori, Jenis Olahan, Order | Kode akun COA |
| Termasuk beban operasional (gaji, sewa, dll)? | Tidak | Ya |
| Cocok untuk | Analisis harian kasir/manajer | Audit pajak, pinjaman bank |

## Cara Baca

1. **Filter Periode** (dari — sampai) + **Cabang** (Semua Cabang atau tertentu)
2. Klik **Tampilkan**

## Struktur Laporan (Urutan SAK ETAP)

```
Pendapatan
  − Harga Pokok Penjualan (HPP)
= Laba Kotor
  − Beban Operasional
= Laba Usaha
  + Pendapatan Lain-lain
  − Beban Lain-lain
= Laba Bersih Sebelum Pajak
  − Pajak Penghasilan
= Laba Bersih Setelah Pajak
```

Semua akun leaf di Chart of Accounts ditampilkan — termasuk yang belum pernah dipakai (tampil Rp 0) — supaya strukturnya selalu lengkap dan konsisten, bukan cuma akun yang kebetulan sudah ada transaksinya.

## Dari Mana Datanya?

- Transaksi yang sudah punya **Kategori Transaksi** dengan kode akun COA terpasang → langsung dipetakan ke akun tersebut
- Transaksi hasil order/POS (jasa giling, penjualan produk) → dipetakan otomatis lewat kategori lama (enum) ke kode akun yang sesuai, karena transaksi order tidak melalui form Kategori Transaksi manual
- Transfer/Perpindahan Dana antar kas **tidak dihitung** sebagai pendapatan/beban (itu murni perpindahan uang internal, bukan kejadian ekonomi)

## Cara Hitung HPP (Harga Pokok Penjualan)

**HPP di laporan ini dihitung dari nilai bahan baku yang BENAR-BENAR terpakai di order yang terjual** (FIFO cost aktual per item, sumber yang sama dengan **BEP Otomatis** dan **Laporan Konsumsi Bahan Baku**) — **bukan** dari uang yang dibelanjakan untuk beli stok bulan itu.

Kenapa ini penting: kalau HPP dihitung dari belanja bulanan, angkanya bisa menyesatkan — bulan yang belanja stok besar-besaran (buat cadangan beberapa bulan) akan terlihat rugi besar, sementara bulan yang tidak belanja sama sekali (stok masih cukup dari bulan lalu) bisa terlihat HPP-nya Rp 0 padahal ada banyak penjualan. Dengan basis "bahan yang benar-benar terpakai", **Laba Kotor dan Margin % bulanan sekarang mencerminkan performa penjualan riil**, bukan jadwal belanja Owner.

**Konsekuensi:** karena HPP tidak lagi bersumber dari kategori transaksi kas (`Pembelian Bahan Baku`), angka HPP di laporan ini **tidak akan sama** dengan akun `5-1101` di menu **Buku Besar** (yang tetap menampilkan total uang keluar untuk belanja bahan — berguna untuk lihat arus kas, tapi beda konsep dari HPP). Ini **bukan bug** — keduanya sengaja mengukur hal berbeda; lihat catatan di Halaman 6 Laporan Eksekutif untuk penjelasan lengkap.

## Belum Ada Fitur Pajak

Kolom **Pajak Penghasilan** selalu Rp 0 karena sistem belum punya fitur pencatatan pajak penghasilan usaha — Laba Bersih Sebelum dan Setelah Pajak akan selalu sama untuk saat ini.

## Export PDF

Tombol **Export PDF** menghasilkan dokumen resmi dengan kop perusahaan dan kolom tanda tangan — siap dicetak atau dilampirkan.

## Akses

Menu ini memerlukan permission `laporan.laba_rugi_formal.view` (dan `laporan.laba_rugi_formal.export` untuk export PDF) — **Owner-only secara default**. Kalau Admin Pusat perlu akses, Owner bisa centang manual di **Pengaturan → Role & Hak Akses**.
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 113,
            'aktif'  => true,
        ]);
    }

    private function laporanBepOtomatisUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-bep-otomatis'], [
            'judul'  => 'Laporan BEP Otomatis',
            'konten' => <<<'MARKDOWN'
## Tujuan
Melihat titik impas (Break Even Point) lini **Jasa Giling** langsung dari data transaksi real — tanpa perlu setup manual seperti menu **BEP** existing (yang butuh bikin Setting + isi Biaya Tetap + Produk satu-satu per cabang/periode).

## Perbedaan dengan Menu BEP (Existing)

| | Menu BEP (existing) | BEP Otomatis (baru) |
|---|---|---|
| Cara pakai | Setup manual: buat Setting → isi Biaya Tetap → isi Produk (atau klik "Isi Otomatis") | Langsung pilih periode → Generate, tidak ada setup |
| Cakupan | Semua produk (jasa giling + produk jadi) yang di-setting manual | Khusus lini Jasa Giling |
| Sumber Biaya Variabel | Harga beli terakhir dari Master Barang | HPP FIFO aktual dari penjualan (lebih akurat) |
| Sumber Biaya Tetap | Kategori tertentu (Gaji, Sewa, dst — dipilih manual) | Otomatis dari kategori bertanda "Tetap" di Kategori Transaksi |

## Cara Baca

1. **Filter Periode** (dari — sampai) + **Cabang**, klik **Generate**
2. **Kartu Ringkasan**: BEP Unit (kg), BEP Rupiah, Volume Aktual (kg), % Tercapai
3. **Break-Even Chart**: garis Biaya Tetap (putus-putus), Biaya Total, dan Pendapatan — titik potong Biaya Total & Pendapatan adalah BEP
4. **Komponen Perhitungan**: rincian Biaya Tetap, Biaya Variabel/kg, Harga Jual/kg, Margin Kontribusi, Margin of Safety, dan Estimasi Laba
5. **Breakdown Biaya Tetap per Kategori**: rincian kategori mana saja yang menyumbang Biaya Tetap

## Formula

```
Margin Kontribusi / kg = Harga Jual / kg − Biaya Variabel / kg
BEP Unit (kg)          = Biaya Tetap / Margin Kontribusi per kg
BEP Rupiah             = BEP Unit × Harga Jual / kg
Margin of Safety       = ((Volume Aktual − BEP Unit) / Volume Aktual) × 100%
Estimasi Laba          = (Margin Kontribusi × Volume Aktual) − Biaya Tetap
```

## Kenapa Angka Bisa Terlihat Ekstrem di Awal Bulan?

Biaya Tetap (misalnya penyusutan aset) tercatat penuh untuk periode yang dipilih, sementara Volume Aktual baru terkumpul sedikit kalau baru beberapa hari berjalan di bulan itu — jadi **% Tercapai** dan **Margin of Safety** wajar terlihat sangat rendah/negatif di awal bulan. Ini bukan bug, cukup tunggu sampai akhir bulan untuk gambaran utuh, atau pakai periode bulan lalu yang sudah lengkap.

## Kalau "BEP Tidak Bisa Dihitung"

Artinya Harga Jual rata-rata per kg lebih kecil atau sama dengan Biaya Variabel per kg (margin kontribusi negatif/nol) — setiap kg yang terjual justru menambah rugi, BEP tidak akan pernah tercapai berapapun volumenya. Cek harga tarif jasa giling atau kenaikan harga bahan baku.

## Akses

Permission `laporan.bep_otomatis.view` (dan `.export` untuk PDF) — **Owner-only secara default**. Kalau Manajer Cabang perlu akses, Owner bisa centang manual di **Pengaturan → Role & Hak Akses**.
MARKDOWN
            ,
            'modul'  => 'laporan',
            'urutan' => 114,
            'aktif'  => true,
        ]);
    }

    private function dashboardAnalyticsUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'dashboard-analytics'], [
            'judul'  => 'Widget Dashboard Analytics (Kesehatan Finansial)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Widget "Kesehatan Finansial" di Dashboard memberi gambaran cepat kondisi keuangan usaha tanpa perlu buka laporan satu-satu — 4 kartu ringkasan + trend 6 bulan.

## Arti Setiap Card

### Card 1 — Profitabilitas Bulan Ini
- **Pendapatan**: total omzet bulan berjalan (dari Laporan Laba Rugi Formal)
- **Laba Bersih**: hijau kalau untung, merah kalau rugi, plus persentase margin (Laba Bersih / Pendapatan)

### Card 2 — BEP Status Bulan Ini
- **BEP Unit** (kg) vs **Volume Aktual** (kg) — dari Laporan BEP Otomatis
- Progress bar hijau kalau sudah &ge;100% tercapai, kuning kalau belum
- Klik "Lihat Detail" untuk buka Laporan BEP Otomatis lengkap

### Card 3 — Rasio Keuangan Sederhana
- **Rasio Lancar** = Aset Lancar / Kewajiban Jangka Pendek (dari Neraca) — idealnya &ge;1x (aset lancar cukup untuk tutup hutang jangka pendek). Kalau usaha tidak punya hutang jangka pendek sama sekali, ditampilkan "Aman (tanpa hutang)"
- **ROI Sederhana** = Laba Bersih Bulan Ini / Total Modal — indikator kasar seberapa produktif modal menghasilkan laba, BUKAN rumus ROI baku per-investasi

### Card 4 — Trend 6 Bulan
Line chart Pendapatan (hijau) vs Beban (merah) 6 bulan terakhir — untuk melihat arah tren, bukan angka detail (hover chart untuk lihat nominal per bulan)

## Sumber Data

Semua angka di widget ini **murni dihitung ulang dari 3 laporan yang sudah ada** (Neraca, Laba Rugi Formal, BEP Otomatis) — tidak ada logika perhitungan baru di widget ini sendiri. Kalau ada yang terasa janggal, cek langsung ke laporan sumbernya untuk detail lengkap.

## Kenapa Bisa Terlihat Negatif/Ekstrem?

Sama seperti Laporan BEP Otomatis — kalau dilihat di awal bulan, biaya tetap (penyusutan, dll) sudah tercatat penuh sementara pendapatan baru terkumpul sedikit, jadi wajar Margin/ROI terlihat sangat negatif. Ini gambaran "sejauh ini bulan ini", bukan proyeksi akhir bulan.

## Akses

Permission `dashboard.analytics.view` — **Owner-only secara default**. Kalau Manajer Cabang perlu akses, Owner bisa centang manual di **Pengaturan → Role & Hak Akses**.
MARKDOWN
            ,
            'modul'  => 'laporan',
            'urutan' => 115,
            'aktif'  => true,
        ]);
    }

    private function bukuBesarUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'buku-besar'], [
            'judul'  => 'Buku Besar (General Ledger)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Melihat seluruh transaksi yang tercatat ke 1 akun Chart of Accounts (COA) tertentu, lengkap dengan **saldo berjalan (running balance)** — berguna untuk audit trail per akun, misalnya "berapa total dan riwayat lengkap Beban Sewa Gedung bulan ini?".

## Cara Baca

1. Pilih **Akun COA** dari dropdown, **Periode** (dari—sampai), dan **Cabang**
2. Klik **Tampilkan**
3. Tabel menampilkan: Tanggal, Keterangan, Debit, Kredit, dan **Saldo** berjalan (bertambah/berkurang sesuai saldo normal akun tersebut)

## Scope Terbatas — Hanya Akun Pendapatan/HPP/Beban

Buku Besar ini **hanya mendukung akun bertipe Pendapatan, HPP, Beban Operasional, Pendapatan Lain, dan Beban Lain** — akun Aset/Kewajiban/Modal **tidak tersedia** di dropdown, karena datanya berasal dari tabel lain (Kas, Stok, Aset, Purchase Order), bukan dicatat satu-per-satu di `transaksi_keuangans`. Untuk melihat posisi Aset/Kewajiban/Modal, buka menu **Neraca**.

## Catatan Penting: Saldo Awal Dianggap Rp 0

Baris pertama di setiap periode **selalu mulai dari saldo Rp 0** — ini BUKAN saldo kumulatif riil sejak akun itu pernah dipakai, murni saldo berjalan **dalam rentang tanggal yang difilter**. Kalau ingin tahu akumulasi total sejak awal, perlebar filter tanggal ke rentang yang sangat panjang (misalnya sejak `2020-01-01`).

## Debit vs Kredit

- Akun **Beban/HPP** (saldo normal Debet): transaksi pengeluaran tampil di kolom **Debit**, saldo bertambah
- Akun **Pendapatan** (saldo normal Kredit): transaksi pemasukan tampil di kolom **Kredit**, saldo bertambah

## Export PDF

Tombol **Export PDF** mencetak Buku Besar untuk akun+periode yang sedang dipilih, lengkap dengan total Debit/Kredit/Saldo Akhir dan kolom tanda tangan.

## Akses

Permission `laporan.buku_besar.view` (dan `.export` untuk PDF) — **Owner-only secara default**. Kalau Admin Pusat perlu akses, Owner bisa centang manual di **Pengaturan → Role & Hak Akses**.
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 116,
            'aktif'  => true,
        ]);
    }

    private function laporanEksekutifUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-eksekutif'], [
            'judul'  => 'Laporan Eksekutif Keuangan',
            'konten' => <<<'MARKDOWN'
## Tujuan
Laporan komprehensif 9 halaman untuk rapat direksi — merangkum posisi keuangan, kinerja, BEP, arus kas, verifikasi konsistensi data, temuan berbasis bukti, rangkuman final, dan simulasi skenario balik modal dalam satu dokumen PDF siap cetak. Semua angka murni disusun ulang dari laporan yang sudah ada (Neraca, Laba Rugi Formal, BEP Otomatis, Buku Besar) — bukan sumber data baru.

## Cara Generate

1. Buka menu **Laporan → Laporan Eksekutif**
2. Pilih **Tanggal** (laporan dihitung "per tanggal ini") dan **Cabang** (Semua Cabang atau tertentu)
3. (Opsional) Buka panel **"Sesuaikan Skenario Simulasi Balik Modal"** untuk adjust volume Sedang/Optimis/persentase pangkas beban di Halaman 9 — kosongkan untuk pakai default otomatis
4. Klik **Preview** untuk lihat isi lengkap di tab baru (HTML, sebelum jadi PDF) — cek dulu datanya sebelum export
5. Klik **Export PDF** untuk unduh dokumen siap cetak/dibagikan

## Isi 9 Halaman

1. **Cover Overview** — Modal Awal, Aset Tetap (top 10 + total), Penyusutan, Transaksi & Produksi bulan berjalan, ringkasan BEP, Kas, dan **Poin Kunci** (kesimpulan otomatis berdasarkan angka riil — bukan teks template tetap)
2. **Posisi Keuangan (Neraca)** — sama persis dengan menu Neraca, plus status balance
3. **Kinerja Keuangan (Laba Rugi)** — breakdown per akun COA bulan berjalan, sama persis dengan menu Laba Rugi Formal
4. **Analisa BEP** — BEP Unit/Rupiah, Margin of Safety, estimasi hari menuju BEP kalau belum tercapai
5. **Arus Kas** — kas masuk/keluar (2 versi: termasuk & exclude entri non-tunai seperti penyusutan), trend 6 bulan
6. **Drill-Down & Verifikasi Data** — tabel cross-check konsistensi antar laporan + breakdown semua kategori beban dengan alert
7. **Evidence-Based Findings** — 5 temuan konkret berbasis data real: Struktur Beban, Produktivitas Transaksi, Hutang Jatuh Tempo, Kasir Performance, Trend Kas 30 Hari
8. **Rangkuman Final** — Skor Kesehatan Finansial 5 dimensi (0-100), 3 Poin Utama untuk direksi (diurutkan Kritis→Perhatian→Positif), Pertanyaan untuk Direksi (muncul kondisional sesuai kondisi data)
9. **Simulasi 5 Skenario Balik Modal** — Tren Aktual, Volume Sedang, Volume BEP, Volume Optimis, Kombinasi Efisiensi — masing-masing dengan proyeksi laba/rugi bulanan dan estimasi balik modal/modal habis

## Tentang Skor Kesehatan Finansial (Halaman 8)

5 dimensi (Profitabilitas, Likuiditas, Pencapaian BEP, Efisiensi Beban, Solvabilitas), masing-masing 0-100, dihitung dari **threshold sederhana internal** — BUKAN standar penilaian akuntansi/audit baku. Skor bisa terlihat rendah di awal bulan (lihat penjelasan di bawah) — itu bukan indikasi bisnis benar-benar buruk, murni cerminan data periode yang baru berjalan sedikit hari.

## Tentang Simulasi Balik Modal (Halaman 9)

Untuk bisnis yang masih baru (data produksi minim, belum menjalankan marketing aktif), volume "Sedang" dan "Optimis" **tidak bisa disimpulkan dari histori** — karena itu keduanya default mengikuti **1x dan 2x volume harian BEP** (target proporsional, bukan proyeksi historis) dan **bisa Anda ubah manual** di form sebelum generate sesuai kapasitas mesin/rencana marketing yang Anda tahu. 3 skenario lain (Tren Aktual, Volume BEP, Kombinasi Efisiensi — pangkas X% dari total Biaya Tetap) sepenuhnya dihitung dari data real.

## Tentang "Poin Kunci" di Halaman 1

Poin-poin ini **dihasilkan otomatis dari kondisi angka aktual** (laba positif/negatif, BEP tercapai/belum, Neraca balance/tidak, rasio lancar sehat/tidak) — bukan kalimat template yang selalu sama. Kalau kondisi keuangan berubah, kalimatnya ikut berubah sesuai data terbaru.

## Tentang Verifikasi Konsistensi (Halaman 6)

Tabel ini membandingkan angka yang sama dari 2 titik berbeda untuk memastikan tidak ada kesalahan integrasi:
- **Total Beban**: dibandingkan lewat 2 jalur perhitungan kode yang BENAR-BENAR berbeda (Laba Rugi Formal vs akumulasi Buku Besar) — verifikasi paling kuat di tabel ini
- **Laba Ditahan, BEP, Kas**: memvalidasi bahwa Laporan Eksekutif memanggil sumber data dengan parameter (periode/cabang) yang benar — kalau MISMATCH muncul di sini, itu nyaris pasti bug, bukan pembulatan biasa

Toleransi selisih yang dipakai sangat ketat (< Rp0,01) karena semua perbandingan di atas seharusnya identik persis.

## Kenapa Angka Bisa Terlihat Ekstrem di Awal Bulan?

Sama seperti BEP Otomatis dan Widget Dashboard Analytics — kalau laporan digenerate di awal bulan, biaya tetap (termasuk penyusutan) sudah tercatat penuh sementara pendapatan baru terkumpul sedikit. Wajar Margin/BEP/Cash Runway terlihat sangat negatif/ekstrem. Untuk gambaran utuh, generate laporan di akhir bulan atau pilih tanggal bulan sebelumnya yang sudah lengkap.

## Breakdown Gaji per Karyawan — Catatan Penting

Kalau Anda buka detail kategori "Beban Gaji" (lewat Buku Besar atau breakdown), sistem juga menampilkan rincian per-karyawan dari data Penggajian. **Angka ini informasional** — total per-karyawan dari Penggajian TIDAK OTOMATIS SAMA dengan total transaksi kas kategori Gaji, karena bayar gaji dicatat manual terpisah dari proses hitung payroll. Kalau ada selisih, itu wajar, bukan bug — total resmi yang dipakai di semua laporan tetap dari transaksi keuangan.

## Cetak untuk Rapat Direksi

- PDF sudah termasuk kop perusahaan, nomor halaman ("Halaman X dari 9"), dan link balik ke menu sumber di footer setiap halaman
- Ukuran file biasanya di bawah 1MB, aman untuk email/WhatsApp
- Kalau Neraca menunjukkan "Belum Balance", itu bukan cacat laporan — Halaman 2 sudah menjelaskan alasannya (data historis sebelum sistem akuntansi formal)

## Akses

Permission `laporan.eksekutif.view` (dan `.export` untuk PDF) — **Owner-only secara default**. Kalau Admin Pusat perlu akses, Owner bisa centang manual di **Pengaturan → Role & Hak Akses**.
MARKDOWN
            ,
            'modul'  => 'akuntansi',
            'urutan' => 117,
            'aktif'  => true,
        ]);
    }

    private function simulatorBepUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'simulator-bep'], [
            'judul'  => 'Simulator BEP Interaktif',
            'konten' => <<<'MARKDOWN'
## Tujuan
Eksperimen "bagaimana jika" untuk BEP — geser slider (Volume Harian, Harga Jual, Biaya Variabel, Beban Tetap) dan lihat hasilnya berubah **real-time** tanpa perlu generate laporan atau simpan data apapun.

## Cara Pakai

1. Buka menu **Laporan → Simulator BEP**
2. Nilai awal slider sudah diisi otomatis dari data BEP Otomatis bulan berjalan
3. Geser slider mana saja — hasil (BEP Unit, BEP Rupiah, Laba/Rugi Bulanan, Proyeksi Modal) langsung update
4. Klik **Reset** untuk kembali ke nilai awal (data real)

## Contoh Skenario yang Bisa Dicoba

- "Kalau harga jual naik 10%, berapa BEP-nya?" — geser slider Harga Jual
- "Kalau kita dapat harga bahan baku lebih murah, seberapa besar dampaknya ke BEP?" — geser slider Biaya Variabel
- "Kalau sewa naik/turun, berapa BEP baru?" — geser slider Beban Tetap
- "Berapa volume yang perlu dicapai untuk balik modal dalam 12 bulan?" — coba-coba geser Volume Harian sambil lihat "Proyeksi Modal"

## Perhitungan Murni di Browser Anda

Semua kalkulasi berjalan di JavaScript browser (bukan AJAX ke server) — **tidak ada data yang tersimpan atau berubah** di sistem manapun. Aman untuk eksperimen sebanyak apapun. Formula yang dipakai sama persis dengan Laporan BEP Otomatis:

```
Margin Kontribusi = Harga Jual - Biaya Variabel
BEP Unit  = Beban Tetap / Margin Kontribusi
BEP Rupiah = BEP Unit x Harga Jual
Laba Bulanan = (Volume Harian x 26 hari x Harga Jual) - (Volume Harian x 26 hari x Biaya Variabel) - Beban Tetap
```

## Beda dengan Simulasi 5 Skenario (Laporan Eksekutif Halaman 9)

Simulator ini untuk eksplorasi bebas (semua 4 parameter bisa diubah manual), sedangkan Halaman 9 Laporan Eksekutif punya 5 skenario baku (Tren Aktual, Sedang, BEP, Optimis, Kombinasi Efisiensi) yang dirancang untuk presentasi rapat direksi. Keduanya reuse formula BEP yang sama.

## Akses

Permission `laporan.simulator.view` — **Owner-only secara default**. Kalau Manajer Cabang perlu akses, Owner bisa centang manual di **Pengaturan → Role & Hak Akses**.
MARKDOWN
            ,
            'modul'  => 'laporan',
            'urutan' => 118,
            'aktif'  => true,
        ]);
    }

    private function laporanJamRamaiUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-jam-ramai'], [
            'judul'  => 'Analisa Jam Ramai',
            'konten' => <<<'MARKDOWN'
## Tujuan

Melihat jam berapa saja pelanggan paling sering datang/order, supaya bisa dipakai untuk keputusan operasional: jadwal staff (tambah orang di jam ramai) dan promo (diskon di jam sepi untuk tarik pelanggan).

## Sumber Data — Kenapa BUKAN dari Kas/Transaksi Keuangan?

Laporan ini **murni dihitung dari jam order dibuat** (`orders`), bukan dari Kas & Transaksi Keuangan. Alasannya: saat audit ditemukan banyak transaksi keuangan diinput belakangan (beda tanggal dari Tanggal Transaksi aslinya) dan sebagian besar lainnya auto-generated dari batch Depresiasi Aset (bukan aktivitas pelanggan real-time). Kalau dipakai untuk "jam ramai", hasilnya akan menyesatkan. Jam order dibuat jauh lebih mencerminkan kapan pelanggan benar-benar datang.

## Cara Baca

1. **Filter Periode** (dari — sampai) + **Cabang**, klik **Generate**
2. **Kartu Ringkasan**: Jam Puncak (paling ramai), Jam Relatif Sepi, Total Order, Hari Operasional
3. **Chart**: bar per jam (00:00—23:00), batang hijau menandai jam puncak
4. **Rekomendasi**: teks otomatis berdasarkan angka aktual — saran tambah staff di jam puncak, saran promo di jam sepi
5. **Tabel Detail per Jam**: rincian jumlah order dan nominal per jam

## Kolom "Jam" di Riwayat Order & Transaksi Keuangan

Sebagai pelengkap laporan ini, kolom **Jam** juga ditambahkan di:
- **Riwayat Order** — jam order dibuat, representasi akurat (sumber data laporan ini).
- **Kelola Kas & Transaksi Keuangan** — jam ENTRI dicatat di sistem, ada ikon info (ⓘ) yang menjelaskan ini BUKAN selalu jam kejadian bisnis. Kalau ada ikon jam kuning (🕐), artinya transaksi itu diinput di hari yang berbeda dari Tanggal Transaksinya.

## Catatan Data Tipis

Kalau bisnis masih baru berjalan (data cuma beberapa hari/minggu), laporan akan menampilkan disclaimer bahwa pola jam ramai belum solid secara statistik. Sebaiknya tunggu data lebih banyak terkumpul sebelum mengambil keputusan besar seperti perubahan jadwal staff permanen.

## Akses

Permission `laporan.jam_ramai.view` — **Owner-only secara default**. Kalau Manajer Cabang perlu akses, Owner bisa centang manual di **Pengaturan → Role & Hak Akses**. Widget "Jam Ramai Hari Ini" di Dashboard mengikuti permission yang sama.
MARKDOWN
            ,
            'modul'  => 'laporan',
            'urutan' => 119,
            'aktif'  => true,
        ]);
    }

    private function searchBoxUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'pencarian-multi-field'], [
            'judul'  => 'Kotak Pencarian di Halaman List/Tabel',
            'konten' => <<<'MARKDOWN'
## Tujuan

Kotak pencarian (ikon 🔍) ditambahkan di ~20 halaman list/tabel supaya tidak perlu scroll manual untuk mencari data tertentu — cukup ketik kata kunci, sistem cari sekaligus di beberapa kolom (multi-field), bukan cuma 1 kolom.

## Cara Pakai

1. Ketik kata kunci di kotak pencarian (nama, nomor, NIK, dsb — placeholder di tiap halaman menyebutkan field apa saja yang dicari)
2. Tekan Enter atau klik tombol Filter/Cari
3. Hasil otomatis tersaring — kombinasi dengan filter dropdown/tanggal lain (kalau ada) tetap jalan bersamaan, tidak saling menimpa
4. Kosongkan kotak pencarian + submit lagi (atau klik Reset kalau tersedia) untuk kembali melihat semua data

## Halaman yang Sudah Punya Pencarian

**Sudah ada sebelumnya** (di-enhance tambah field): Riwayat Order, Purchase Order, Master Item, Master Karyawan, Master Pelanggan, Master Supplier, Master User, Master Cabang, Stok Barang, Kelola Antrian.

**Baru ditambahkan:** Kas & Transaksi, Transfer/Perpindahan Dana, Permintaan Stok, Transfer Stok, Laporan Absensi, Cuti & Izin, Penggajian, Master Jenis Olahan, Master Resep Bumbu Standar, Manajemen Aset, Audit Log, Laporan Penjualan, Penilaian Karyawan 360° (daftar periode).

## Catatan Khusus: Kelola Absensi & Ranking Evaluasi

Dua halaman ini pencariannya **beda mekanisme** (client-side, bukan submit ke server) karena alasan keamanan data:
- **Kelola Absensi** adalah form input massal (isi kehadiran semua karyawan sekaligus). Kalau pencarian dibuat lewat server (submit form), baris karyawan yang tidak muncul di hasil pencarian akan **hilang dari data yang tersimpan** saat form disubmit — berisiko kehadiran karyawan lain ikut terhapus tanpa sengaja. Solusinya: pencarian di sini hanya **menyembunyikan tampilan baris**, semua data karyawan tetap ada di form dan aman tersimpan.
- **Ranking Evaluasi** (per periode) juga pakai mekanisme sama karena halamannya kecil (skor 1 periode) dan cukup filter tampilan saja.

## Kenapa Sebagian Halaman TIDAK Ditambahkan

Beberapa halaman list SENGAJA dilewati karena tidak cocok dengan pola pencarian standar ini:
- **Kelola Kas** — tampilan kartu (bukan tabel), jumlah kas per cabang biasanya sedikit
- **Kategori Transaksi** — daftar pohon kecil (induk-anak), bukan tabel flat
- **Buku Besar** & **Konsumsi Bahan Baku (detail transaksi)** — datanya belum pakai sistem halaman standar (pagination), butuh perubahan teknis lebih dalam
- **Data Terhapus** — 1 halaman menampilkan 24 jenis data berbeda (Order, Karyawan, Item, dst) yang kolomnya semua beda, butuh pendekatan khusus per jenis data

## Performa

Pencarian pakai pencocokan teks sederhana (`LIKE`), tanpa index database tambahan — cukup untuk skala data saat ini. Kalau di kemudian hari data sudah sangat banyak (puluhan ribu baris) dan pencarian terasa lambat, itu tandanya perlu index database tambahan (bukan mengganti cara kerja pencarian).
MARKDOWN
            ,
            'modul'  => 'lainnya',
            'urutan' => 120,
            'aktif'  => true,
        ]);
    }

    private function transferAntarKasUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'transfer-antar-kas'], [
            'judul'  => 'Cara Pakai Menu Transfer Antar Kas',
            'konten' => <<<'MARKDOWN'
## Tujuan

Mencatat mutasi dana antar Kas **dalam 1 cabang yang sama** — misalnya setor tunai dari Kas Tunai ke Kas Bank, atau tarik tunai dari Kas Bank ke Kas Tunai. 1 form otomatis membuat 2 transaksi berpasangan (keluar dari Kas Asal, masuk ke Kas Tujuan) — menggantikan workaround manual "tambah 2 transaksi keuangan biasa" yang rawan salah nominal/kategori.

## Beda dengan Transfer / Perpindahan Dana

| | Transfer Antar Kas | Transfer / Perpindahan Dana |
|---|---|---|
| Cakupan | **1 cabang yang sama** (Tunai ↔ Bank) | **Antar cabang** (Cabang A → Cabang B) |
| Proses | **Instan** — saldo kedua Kas langsung berubah saat disimpan | Ada **alur persetujuan** (Menunggu → Diterima/Ditolak) |
| Bukti transfer | Tidak wajib | Wajib upload foto/bukti |

Kalau kas asal dan kas tujuan ada di **cabang berbeda**, gunakan menu **Transfer / Perpindahan Dana**, bukan menu ini — sistem akan menolak dengan pesan jelas kalau dicoba di sini.

## Cara Input di UI

1. Buka menu **Keuangan → Transfer Antar Kas** dari sidebar
2. Klik **Transfer Antar Kas** *(butuh permission `transfer_antar_kas.create`)*
3. Isi **Tanggal**, **Jumlah Transfer**, **Kas Asal**, **Kas Tujuan**, dan **Keterangan** (opsional)
4. Klik **Proses Transfer** → saldo Kas Asal langsung berkurang, saldo Kas Tujuan langsung bertambah

## Catatan Penting

- **Bukan Pendapatan/Beban** — transfer ini murni perpindahan kas internal, **TIDAK masuk** ke Laporan Laba Rugi Formal maupun Buku Besar (kategori khusus `Mutasi Kas Masuk`/`Mutasi Kas Keluar` dikecualikan otomatis, sama seperti Transfer/Perpindahan Dana antar cabang)
- **Total Kas di Neraca tidak berubah** — uang cuma pindah lokasi (dari 1 Kas ke Kas lain), bukan bertambah/berkurang secara keseluruhan
- Cabang minimal harus punya **2 Kas aktif** untuk bisa pakai fitur ini
- **Hapus Transfer** akan membalikkan saldo kedua Kas ke kondisi semula dan mencatatnya sebagai soft-delete (bisa dipulihkan lewat menu **Keamanan → Data Terhapus**)

## Troubleshooting

- **Menu tidak muncul di sidebar?** Perlu izin `transfer_antar_kas.view` — minta Owner mencentang di Pengaturan → Role & Hak Akses
- **"Kas asal dan kas tujuan harus berada di cabang yang sama"?** Kas Tujuan yang dipilih ada di cabang lain — gunakan menu **Transfer / Perpindahan Dana** untuk transfer antar cabang
- **"Saldo kas tidak cukup"?** Saldo Kas Asal lebih kecil dari jumlah yang mau ditransfer — cek saldo terkini di menu Kas & Transaksi
- **Cabang cuma punya 1 Kas aktif?** Tambah Kas baru dulu di menu **Kas & Transaksi → Kelola Kas**
MARKDOWN
            ,
            'modul'  => 'keuangan',
            'urutan' => 105,
            'aktif'  => true,
        ]);
    }

    private function programLoyaltyUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'program-loyalty'], [
            'judul'  => 'Program Loyalty',
            'konten' => <<<'MARKDOWN'
## Tujuan
Ada 2 tipe Program Loyalty: **Auto-Track** (Fase 1 — sistem otomatis hitung kumulatif kg jasa giling per pelanggan, dibandingkan ke target, mis. "500 kg → dapat hadiah") dan **Event-Based** (Fase 2 — pelanggan klaim manual dengan bukti, mis. "post di sosmed + tag akun toko → dapat voucher Rp15.000", 1x per pelanggan, di-approve Owner/Admin Pusat).

Kedua tipe dipilih saat **Tambah Program** lewat dropdown "Tipe Program" — field yang muncul di form otomatis menyesuaikan (Target kg + Berulang cuma utk Auto-Track; Nominal Voucher cuma utk Event-Based).

## FASE 1 — Auto-Track (Progress Kg Otomatis)

### Konsep Penting

- **Sumber angka progress SELALU dari `orders.berat_daging_kg`** (kolom level-order, diisi kasir di POS saat berat gilingan diketahui) — **BUKAN** dari jumlah baris item (bumbu, kemasan, dll di dalam 1 order). Kalau dijumlah dari baris item, angkanya akan salah total (bisa sampai 7x lipat lebih besar) karena ikut menghitung kg tepung, kg bumbu, bahkan jumlah pcs kemasan sebagai kg.
- **Kalau ada order jasa giling yang belum diisi berat gilingan-nya** (kolom kosong), order itu dihitung **0 kg untuk sementara** — bukan ditebak. Muncul badge kuning "X order tanpa data" di halaman Detail Program dan Detail Pelanggan supaya kelihatan progress-nya mungkin under-estimate.
- **Periode All-time**: kosongkan Periode Mulai/Akhir saat bikin program → dihitung dari order pertama pelanggan sampai sekarang, tidak pernah reset.
- **Berulang**: kalau dicentang, pelanggan bisa dapat hadiah lagi tiap kelipatan target terlewati (mis. 500kg, lalu 1000kg, dst — masing-masing 1 pencapaian terpisah). Default tidak dicentang (1x per pelanggan per program).

### Cara Kelola (Owner/Admin Pusat)

1. Buka menu **Master Data → Program Loyalty** dari sidebar
2. Klik **Tambah Program** — pilih Tipe Program "Auto-Track", isi Nama, Target (kg), Hadiah, dan opsional Periode/Deskripsi
3. Program **Aktif** langsung mulai dihitung progress-nya untuk semua pelanggan yang punya order jasa giling
4. Klik **Detail** (ikon mata) untuk lihat progress SEMUA pelanggan ke program itu, diurutkan dari yang paling dekat target
5. Kalau ada pelanggan yang sudah **Tercapai**, klik tombol **Tandai Hadiah** setelah hadiah benar-benar diserahkan — status berubah jadi "Hadiah Diberikan" dan tidak akan muncul lagi di widget dashboard

Sistem otomatis cek setiap hari jam 03:00 WIB (`loyalty:cek-pencapaian`) — kalau ada pelanggan yang baru melewati target, otomatis dicatat sebagai "Tercapai". Owner bisa juga langsung klik **Tandai Hadiah** kapan saja — sistem cek ulang progress saat itu juga.

## FASE 2 — Event-Based (Klaim Manual + Bukti)

### Konsep Penting

- **1 pelanggan cuma bisa klaim 1x per program** — kalau klaim masih Pending/Approved/Issued, klaim baru ditolak sistem. Kalau klaim sebelumnya **Rejected**, pelanggan BOLEH klaim ulang (bukan jatah habis, cuma bukti sebelumnya yang tidak diterima).
- **Bukti klaim** boleh **Link** (URL post sosmed/website) ATAU **Upload Foto** — minimal isi salah satu.
- **Alur status**: Pending (baru diajukan) → **Approved** (Owner/Admin Pusat setuju + isi Nominal Voucher) → **Issued** (voucher sudah benar-benar diserahkan ke pelanggan) — atau Pending → **Rejected** (ditolak, wajib isi alasan).
- **Nominal Voucher** di form Approve default terisi dari "Nominal Voucher Default" program (kalau diisi saat bikin program), tapi bisa diubah manual per klaim.

### Cara Buat Klaim (Kasir)

1. Buka menu **Master Data → Program Loyalty → Klaim Event** dari sidebar, atau klik tombol **"Buat Klaim Loyalty untuk Order Ini"** di modal struk setelah checkout POS (kalau pelanggan terdaftar & ada program event-based aktif)
2. Klik **Buat Klaim**, pilih Program dan Pelanggan (otomatis ter-isi kalau datang dari tombol POS)
3. Isi Link Bukti (URL post) atau Upload Foto Bukti — minimal salah satu
4. Submit — klaim berstatus **Pending**, menunggu approval Owner/Admin Pusat

### Cara Approve/Reject/Tandai Diberikan (Owner/Admin Pusat)

1. Buka menu **Klaim Event** (atau Detail Program event-based → langsung tampil daftar klaim)
2. Klaim **Pending** — klik ikon centang hijau (✓) utk **Setujui** (isi Nominal Voucher) atau ikon silang merah (✗) utk **Tolak** (wajib isi alasan)
3. Klaim **Approved** — klik ikon hadiah utk **Tandai Diberikan** (voucher sudah benar-benar diserahkan) setelah pelanggan menerima vouchernya

## Tampilan di Tempat Lain

- **Detail Pelanggan** — kartu "Total Kg Giling" + section "Program Loyalty" (progress Auto-Track) + section terpisah "Riwayat Klaim Loyalty (Event)" (klaim Event-Based, kalau ada)
- **Dashboard** (cabang & pusat) — widget "Pelanggan Loyalty Progress" (Auto-Track, ≥80% progress, max 10) + widget "Klaim Menunggu Approval" (Event-Based, cuma tampil utk yang punya izin approve)
- **POS** — tombol opsional "Buat Klaim Loyalty untuk Order Ini" di modal struk setelah checkout

## Catatan Penting

- Widget & progress **tidak dipisah per cabang** — kumulatif pelanggan/klaim dihitung lintas semua cabang.
- Hanya order dengan status **bukan Dibatalkan** yang dihitung untuk progress Auto-Track.
- Menghapus program (lewat Data Terhapus) tidak menghapus riwayat pencapaian/klaim yang sudah tercatat — riwayat tetap tersimpan untuk audit.
- Program Event-Based **tidak masuk** perhitungan progress kg Auto-Track sama sekali (2 jalur terpisah total) — Target (kg) & Berulang tidak berlaku utk Event-Based.

## Troubleshooting

- **Menu Program Loyalty tidak muncul di sidebar?** Perlu izin `loyalty.view` — minta Owner mencentang di Pengaturan → Role & Hak Akses
- **Progress Auto-Track pelanggan terasa kurang dari yang seharusnya?** Cek badge "order tanpa data" di Detail Program/Detail Pelanggan — mungkin ada order lama yang belum diisi berat gilingan-nya, minta Owner cek & lengkapi manual lewat Edit Order
- **Tombol "Tandai Hadiah" (Auto-Track) tidak muncul?** Perlu izin `loyalty.manage`
- **Menu/tombol "Buat Klaim" tidak muncul?** Perlu izin `loyalty.klaim.buat` (default sudah ada di role Kasir)
- **Tombol Setujui/Tolak/Tandai Diberikan tidak muncul di daftar klaim?** Perlu izin `loyalty.klaim.approve`/`loyalty.klaim.reject`/`loyalty.klaim.issued` (default Owner + Admin Pusat)
- **Tombol "Buat Klaim Loyalty" di struk POS tidak muncul?** Cek 3 syarat: pelanggan harus terdaftar (bukan walk-in), user harus punya izin `loyalty.klaim.buat`, dan minimal ada 1 program Event-Based berstatus Aktif
MARKDOWN
            ,
            'modul'  => 'lainnya',
            'urutan' => 130,
            'aktif'  => true,
        ]);
    }

    // =========================================================================
    // MASTER VARIAN PRODUK (Tahap 2 D'mentai, 2026-09-13) — placeholder,
    // struktur DB+Model sudah ada tapi UI-nya dikerjakan bareng POS di Tahap 3.
    // =========================================================================

    private function itemVarianUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'item-varian'], [
            'judul'  => 'Varian Produk',
            'konten' => <<<'MARKDOWN'
## Tujuan
Membuat beberapa pilihan (Size, Rasa, Level Pedas, dll) untuk 1 produk yang sama — misalnya Dimsum Mentai tersedia dalam Size S/M/L dengan harga berbeda — tanpa perlu bikin item terpisah untuk tiap pilihan.

## Langkah-langkah

1. Buka menu **Produk Jual**, klik **Edit** pada produk yang ingin diberi varian (atau isi saat **Tambah Produk**)
2. Di section **Punya Varian?**, aktifkan toggle
3. Klik **Tambah Atribut** — isi:
   - **Nama** — nama dimensi pilihan, contoh: `Size`
   - **Nilai** — semua pilihan dipisah koma, contoh: `S, M, L`
4. Bisa tambah lebih dari 1 atribut (mis. Size + Level Pedas) — sistem otomatis membuat SEMUA kombinasinya
5. Klik **Update Preview Kombinasi** untuk melihat tabel hasil kombinasi
6. Isi **Harga Override** per baris kombinasi kalau harganya beda dari Harga Jual default (kosongkan kalau sama)
7. Simpan produk

## Cara Kerja di POS
Produk dengan badge **"Ada Varian"** di grid akan membuka modal pilih atribut saat diklik kasir — harga otomatis menyesuaikan kombinasi yang dipilih pelanggan.

## Catatan Penting

- Kalau kamu ubah/hapus salah satu Nilai atribut (misalnya hapus pilihan "S"), kombinasi lama yang memakainya TIDAK dihapus permanen — cuma disembunyikan dari POS, supaya riwayat order lama yang pernah pakai kombinasi itu tetap valid
- Mengubah kombinasi atribut yang SAMA PERSIS (tidak tambah/kurang nilai) akan mempertahankan Harga Override yang sudah diisi — aman diedit berulang kali
- Stok tetap dicek di level produk induk (lewat resep produksinya), BUKAN per-varian — semua varian dari 1 produk berbagi stok bahan baku yang sama
- Kalau toggle **Punya Varian?** dimatikan, data atribut/kombinasi lama TIDAK hilang — cuma modal varian di POS berhenti muncul, bisa diaktifkan lagi kapan saja

## Troubleshooting

- **Kombinasi tidak muncul di preview?** Pastikan sudah klik **Update Preview Kombinasi** setelah mengubah Nilai atribut
- **Harga di POS tidak sesuai?** Cek Harga Override baris kombinasi itu — kosong berarti pakai Harga Jual default produk
- **Mau hapus 1 pilihan varian?** Cukup hapus dari daftar Nilai (pisah koma) lalu Update Preview — jangan hapus seluruh Atribut kalau cuma mau kurangi 1 nilai
MARKDOWN
            ,
            'modul'  => 'master',
            'urutan' => 99,
            'aktif'  => true,
        ]);
    }

    // =========================================================================
    // TAHAP 7 D'MENTAI (2026-09-16) — BACKFILL PANDUAN TAHAP 1-6
    // =========================================================================

    private function masterBahanBakuUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'bahan-baku'], [
            'judul'  => 'Master Bahan Baku & Kemasan',
            'konten' => <<<'MARKDOWN'
## Tujuan
Mengelola data bahan baku (kulit dimsum, isian, saus), kemasan (kotak, plastik, sumpit), dan item tambahan gratis (garpu) yang TIDAK dijual langsung ke pelanggan di POS — beda dari menu **Produk Jual** yang punya foto+resep+varian.

## Langkah-langkah

1. Buka menu **Bahan Baku & Kemasan** dari sidebar
2. Klik **Tambah Item** (tombol oranye kanan atas)
3. Isi form:
   - **Kode Item** — otomatis huruf besar, wajib unik (contoh: `BB-007`)
   - **Nama Item**
   - **Tipe** — pilih salah satu:
     - **Bahan Baku** — bahan mentah untuk resep produksi
     - **Kemasan** — kotak/plastik/sumpit
     - **Tambahan Gratis** — item 1-klik di POS section "Item Tambahan" (mis. garpu)
   - **Satuan** — kg, gram, ml, pcs, dll
   - **Kategori** (opsional, bisa tambah kategori baru langsung dari form)
   - **Harga Beli / HPP** dan **Qty Minimum** (untuk notifikasi stok menipis)
4. *(Opsional)* Isi **Stok Awal per Outlet** — angka langsung tersimpan sebagai stok mula-mula di outlet yang diisi, kosongkan outlet yang belum ada stoknya
5. Klik **Simpan**

## Catatan Penting

- Menu ini TIDAK punya field foto/resep/varian — kalau kamu butuh itu, produk yang kamu maksud kemungkinan seharusnya masuk menu **Produk Jual**, bukan di sini
- Stok Awal cuma bisa diisi saat **Tambah** item baru. Untuk menambah/mengurangi stok item yang sudah ada, gunakan menu **Stok → Adjustment Stok**, bukan edit ulang item ini
- Tipe **Tambahan Gratis** otomatis muncul di POS section "Item Tambahan" — pastikan kategorinya di-set ke kategori khusus item tambahan (kode kategori `TMB`) supaya tampil di tempat yang benar

## Troubleshooting

- **Item tidak muncul di form Resep (Produk Jual)?** Cuma item bertipe Bahan Baku/Kemasan/Tambahan Gratis yang bisa dipilih sebagai bahan resep — cek tipe-nya benar
- **Kode Item ditolak "sudah dipakai"?** Kode harus unik lintas SEMUA tipe item (termasuk Produk Jual), cek dulu di kedua menu
MARKDOWN
            ,
            'modul'  => 'master',
            'urutan' => 95,
            'aktif'  => true,
        ]);
    }

    private function masterProdukJualUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'produk-jual'], [
            'judul'  => 'Master Produk Jual',
            'konten' => <<<'MARKDOWN'
## Tujuan
Mengelola produk yang benar-benar dijual di POS (dimsum, gyoza, minuman, frozen) — lengkap dengan foto, komposisi resep (auto-potong stok bahan), varian (Size/Rasa), dan pengaturan ketersediaan per outlet.

## Langkah-langkah

1. Buka menu **Produk Jual**, klik **Tambah Produk**
2. **Informasi Dasar**:
   - Upload **Foto** (klik kotak foto atau tombol file) — otomatis di-resize ke maksimal 800×800px, format jpg/png/webp, maksimal 2MB
   - **Tipe** — **Produk Jual** (tampil di grid utama POS) atau **Produk Tambahan** (add-on berbayar, mis. Saus Extra)
   - Isi Kode Item, Nama, Kategori, Satuan, **Harga Jual** (default)
3. **Komposisi / Resep** (opsional — lewati kalau produk tidak butuh potong bahan otomatis):
   - Klik **Tambah Bahan**, pilih dari daftar Bahan Baku/Kemasan
   - Isi **Qty/unit** = kebutuhan bahan itu untuk **1 unit** produk ini (contoh: 1 porsi Dimsum Ayam butuh 5 pcs Kulit Dimsum → isi qty 5)
   - Centang **Wajib** kalau bahan itu harus ada (kalau habis, produk otomatis ter-disable di POS)
   - Gunakan **Simulasi Produksi** (di bagian bawah, cuma muncul saat Edit) untuk cek total HPP kalau produksi sekian pcs
4. **Varian** (opsional) — lihat panduan **Varian Produk** untuk detail lengkap
5. **Ketersediaan Outlet** — centang outlet mana saja yang boleh menjual produk ini; isi **Harga Override** kalau harga di outlet itu beda dari Harga Jual default
6. Klik **Simpan Produk**

## Import dari Bumbu Pusat (2026-09-17)

Kalau sebuah bumbu/campuran bahan dipakai di **banyak produk** (mis. "Bumbu Kecap Manis Standar" dipakai Dimsum Ayam, Dimsum Udang, dan Gyoza sekaligus), daripada input bahan yang sama berulang-ulang di tiap produk, **import langsung dari Master Bumbu Pusat**:

1. Di section Komposisi/Resep, klik tombol **"Import dari Bumbu Pusat"** (sebelah "Tambah Bahan")
2. Cari & pilih bumbu dari daftar (cuma menampilkan Bumbu Pusat yang berstatus Aktif)
3. Baris baru muncul dengan badge **🧂 Bumbu Pusat** — isi **Qty** = berapa **porsi bumbu** dipakai per 1 unit produk ini (biasanya 1)
4. Simpan seperti biasa

**Contoh use case**: 3 produk dimsum semua pakai "Bumbu Kecap Manis Standar" (isi: kecap 10g + gula 5g + bawang putih 2g per porsi). Import bumbu ini ke ketiga produk dengan qty masing-masing sesuai kebutuhan (mis. 1 porsi untuk Dimsum Ayam, 1.5 porsi untuk Dimsum Udang yang porsinya lebih besar). Kalau nanti resep bumbu kecap diubah (mis. gula ditambah jadi 7g) di Master Bumbu Pusat, **HPP ketiga produk otomatis ikut berubah** tanpa perlu edit satu-satu.

**Beda baris manual vs baris terlink**:
| | Bahan Manual (Tambah Bahan) | Bumbu Terlink (Import) |
|---|---|---|
| Sumber data | Langsung ke 1 Item bahan baku | Ke sekumpulan bahan di Master Bumbu Pusat |
| Kalau diedit di sumbernya | Cuma pengaruh produk ini | Pengaruh SEMUA produk yang import bumbu itu |
| Cocok untuk | Bahan unik cuma dipakai 1 produk | Bumbu/campuran standar dipakai banyak produk |

## Catatan Penting

- Resep di sini adalah SATU-SATUNYA tempat mengatur komposisi bahan produk — menu **Master Bumbu Pusat** cuma tampilan overview + tempat kelola bumbu REUSABLE (lihat section di atas), bukan tempat edit resep produk tertentu
- Produk TANPA resep akan memotong stok dirinya sendiri langsung (harus diisi stok manual lewat Adjustment Stok) — cocok untuk minuman kemasan jadi
- **Hapus baris bumbu terlink** = cuma hapus LINK-nya dari produk ini, bumbu di Master Bumbu Pusat TIDAK ikut terhapus/berubah
- **Ketersediaan Outlet**: outlet yang TIDAK dicentang berarti produk itu TIDAK muncul sama sekali di POS outlet tersebut. Produk lama yang belum pernah diatur ketersediaannya dianggap aktif di SEMUA outlet secara default
- Menghapus foto: centang **Hapus foto** lalu Simpan (atau upload foto baru untuk menggantinya langsung)

## Troubleshooting

- **Produk tidak muncul di POS outlet tertentu?** Cek section Ketersediaan Outlet — pastikan outlet itu tercentang
- **Badge "Stok Habis" padahal stok bahan ada?** Cek satuan Qty/unit resep — kalau satuannya beda dari satuan stok bahan (mis. isi "gram" tapi stok bahan dalam "kg"), hitungannya bisa salah
- **Upload foto gagal?** Pastikan ukuran file di bawah 2MB dan format jpg/jpeg/png/webp
- **Bumbu yang mau di-import tidak muncul di daftar?** Cek status bumbu itu di Master Bumbu Pusat — cuma yang Aktif yang bisa diimport. Bumbu yang dinonaktifkan setelah di-import tetap jalan normal di produk yang sudah pakai, cuma tidak bisa dipilih lagi untuk produk baru
MARKDOWN
            ,
            'modul'  => 'master',
            'urutan' => 96,
            'aktif'  => true,
        ]);
    }

    private function setoranKasirUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'setoran-kasir'], [
            'judul'  => 'Setoran Kasir',
            'konten' => <<<'MARKDOWN'
## Tujuan
Merekonsiliasi uang tunai hasil penjualan harian outlet ke HO (Head Office/Gudang Pusat) — sistem otomatis menghitung total penjualan dari data POS, kasir tinggal konfirmasi jumlah uang fisik yang diserahkan.

## Langkah-langkah (Kasir)

1. Buka menu **Setoran Kasir** dari sidebar, klik **Buat Setoran Hari Ini**
2. Sistem otomatis menampilkan **Ringkasan Penjualan Sistem** — breakdown per metode bayar (Tunai/Transfer/QRIS) hari ini
3. Isi **Jumlah Uang Tunai yang Diserahkan** — sudah terisi default sesuai sistem, ubah kalau ada selisih uang fisik di laci
4. *(Opsional)* Upload **Bukti Foto** dan isi **Catatan**
5. Klik **Submit Setoran** — status berubah jadi **Menunggu Approval**

## Langkah-langkah (HO / Admin Pusat)

1. Buka menu **Setoran Kasir**, filter status **Menunggu Approval**
2. Klik setoran yang mau diproses untuk lihat detail (breakdown metode, bukti foto, catatan kasir)
3. Pilih salah satu:
   - **Approve** — saldo Kas outlet berkurang, saldo Kas HO bertambah otomatis, isi Catatan (opsional)
   - **Reject** — WAJIB isi alasan penolakan, kasir bisa revisi & submit ulang untuk tanggal yang sama

## Catatan Penting

- **Hanya uang TUNAI yang disetorkan fisik** ke HO lewat fitur ini — metode Transfer/QRIS sudah otomatis masuk ke Kas non-tunai outlet saat transaksi dibuat, TIDAK perlu disetor manual
- Uang BELUM berpindah saat kasir submit — saldo Kas baru berubah setelah HO klik **Approve**
- 1 outlet cuma bisa punya 1 setoran per tanggal — kalau sudah pernah submit untuk hari itu, submit ulang akan memperbarui data yang sama (bukan bikin baris baru), kecuali statusnya masih Menunggu (harus diproses HO dulu)
- Setoran yang **Ditolak** bisa direvisi kasir kapan saja untuk tanggal yang sama — cukup buka menu **Buat Setoran** lagi

## Troubleshooting

- **"Setoran sudah pernah disubmit"?** Cek status setoran hari ini di menu Setoran Kasir — kalau statusnya Menunggu/Disetujui, tunggu diproses HO dulu sebelum bisa submit ulang; kalau Ditolak, submit ulang otomatis jadi revisi
- **Total sistem Rp0 padahal ada transaksi?** Pastikan transaksi hari itu statusnya bukan "Dibatalkan" — order yang dibatalkan tidak ikut dihitung
- **HO tidak bisa Approve?** Pastikan Kas Tunai aktif sudah ada baik di outlet pengirim maupun di Gudang Pusat (HO) — hubungi Admin kalau kas belum di-setup
MARKDOWN
            ,
            'modul'  => 'keuangan',
            'urutan' => 20,
            'aktif'  => true,
        ]);
    }

    private function dashboardOwnerUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'dashboard-owner'], [
            'judul'  => 'Dashboard Owner — Widget Setoran & Kas HO',
            'konten' => <<<'MARKDOWN'
## Tujuan
Memahami arti 4 kartu angka, grafik, dan 2 tabel alert baru di Dashboard Keseluruhan (`/dashboard/pusat`) — semuanya berkaitan dengan uang kas dan setoran, khusus tampil untuk Owner/Admin Pusat.

## Penjelasan Widget

1. **Total Penjualan Hari Ini** — jumlah `total_bayar` semua order hari ini dari SEMUA outlet, tidak termasuk order yang dibatalkan
2. **Total Penjualan Bulan Ini** — sama seperti di atas tapi kumulatif dari tanggal 1 bulan berjalan
3. **Uang Belum Disetor** — total uang tunai dari Setoran Kasir yang statusnya masih **Menunggu** atau **Ditolak** (belum di-approve HO). Hari yang kasirnya belum submit setoran sama sekali TIDAK ikut terhitung di sini
4. **Kas HO Saat Ini** — saldo kas tunai aktif di Gudang Pusat, naik setiap ada Setoran Kasir yang di-approve
5. **Trend Penjualan Harian (7 Hari)** — grafik garis, total omzet semua outlet per hari, 7 hari terakhir
6. **Setoran Menunggu Approval** — daftar 10 setoran terbaru berstatus Menunggu, klik **Proses** untuk langsung buka halaman approve/reject
7. **Outlet dengan Stok Minimum** — daftar bahan baku/kemasan/produk yang stoknya sudah di titik minimum atau di bawahnya, lintas semua outlet
8. **Selisih Setoran vs Sistem** — setoran dengan selisih (uang fisik vs sistem) minimal Rp5.000, dua arah (kurang maupun lebih)

## Catatan Penting

- Widget ini cuma tampil untuk role yang punya akses (Owner otomatis, Admin Pusat perlu izin `dashboard.owner.view`)
- Angka **Uang Belum Disetor** BUKAN "semua uang yang seharusnya disetor" — kalau kasir belum submit setoran hari ini sama sekali, uang itu belum masuk hitungan (soal kepatuhan submit, bukan soal salah hitung)
- Widget ini menampilkan data real-time, tidak ada filter tanggal/outlet — untuk analisa lebih detail per periode, buka menu **Laporan → Laporan Setoran Kasir**

## Troubleshooting

- **Kas HO Saat Ini menampilkan Rp0?** Cek apakah Gudang Pusat sudah punya Kas aktif dengan tipe Tunai di menu Kelola Kas
- **Setoran Menunggu Approval kosong padahal ada yang submit?** Klik "Lihat semua" untuk cek di menu Setoran Kasir — widget dashboard cuma menampilkan 10 teratas
MARKDOWN
            ,
            'modul'  => 'keuangan',
            'urutan' => 21,
            'aktif'  => true,
        ]);
    }

    private function laporanSetoranKasirUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-setoran-kasir'], [
            'judul'  => 'Laporan Setoran Kasir',
            'konten' => <<<'MARKDOWN'
## Tujuan
Melihat rekap Setoran Kasir dalam rentang periode tertentu — total sistem, total disetor, selisih, dan status — lengkap dengan filter dan export ke Excel.

## Langkah-langkah

1. Buka menu **Laporan → Laporan Setoran Kasir**
2. Atur filter sesuai kebutuhan:
   - **Dari / Sampai** — rentang tanggal (default: bulan berjalan)
   - **Cabang** — cuma muncul untuk Owner/Admin Pusat (role lain otomatis terbatas ke outlet sendiri)
   - **Status** — Menunggu / Disetujui / Ditolak
3. Klik **Filter** untuk terapkan
4. Cek 4 kartu ringkasan di atas tabel: Jumlah Setoran, Total Sistem, Total Disetor, Total Selisih
5. Klik **Export Excel** untuk unduh data yang sedang ditampilkan (mengikuti filter aktif) sebagai file `.xls`

## Catatan Penting

- Kolom **Selisih** = Total Disetor − Total Sistem (Tunai). Angka negatif berarti uang fisik LEBIH SEDIKIT dari sistem, angka positif berarti LEBIH BANYAK
- Export mengikuti filter yang sedang aktif di layar — kalau lupa filter tanggal, file bisa jadi sangat besar (semua data)
- Laporan ini cuma untuk Setoran Kasir (rekonsiliasi harian). Untuk Transfer Dana antar cabang manual, cek menu **Laporan → Setoran** (nama lama, fitur berbeda)

## Troubleshooting

- **Tombol Export tidak muncul?** Perlu permission `laporan.setoran_kasir.export`, minta akses ke Owner/Admin Pusat
- **Data kosong padahal ada setoran?** Cek filter tanggal — pastikan rentang Dari/Sampai mencakup tanggal setoran yang dicari
MARKDOWN
            ,
            'modul'  => 'laporan',
            'urutan' => 130,
            'aktif'  => true,
        ]);
    }

    private function pengaturanUmumUpdate(): void
    {
        Panduan::updateOrCreate(['slug' => 'pengaturan-umum'], [
            'judul'  => 'Pengaturan Umum (Branding)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Mengatur identitas brand aplikasi — nama perusahaan, tagline, dan logo — yang tampil di sidebar, halaman login, struk, dan ikon aplikasi (PWA) saat di-install ke HP/tablet.

## Langkah-langkah

1. Buka menu **Pengaturan → Umum** (Owner only)
2. Isi/ubah:
   - **Nama Perusahaan** — tampil di sidebar, halaman login, header struk
   - **Tagline** — tampil di bawah nama perusahaan (contoh: "Dimsum & Gyoza")
   - **Logo** — upload file gambar (disarankan format persegi, PNG dengan background transparan untuk hasil terbaik)
3. Klik **Simpan**
4. Perubahan nama/tagline langsung tampil di seluruh halaman setelah refresh

## Catatan Tentang Logo & PWA

Ada 2 tempat logo dipakai:
- **Logo yang diupload di sini** — tampil di sidebar, halaman login, dan TV Antrian
- **Ikon Aplikasi (PWA)** — logo yang muncul saat aplikasi di-install ke HP/tablet sebagai ikon, dan sebagai favicon browser. Ikon PWA **disiapkan terpisah oleh developer** (8 ukuran file), TIDAK otomatis ikut berubah kalau kamu ganti logo di sini — kalau butuh update ikon PWA juga, sampaikan ke developer/tim teknis

## Catatan Penting

- Perubahan di sini bersifat GLOBAL — berlaku untuk SEMUA outlet, bukan per-cabang (footer struk per-cabang diatur terpisah di menu **Cabang → Edit Cabang**)
- Kalau rebranding total (ganti nama bisnis), pastikan juga cek dokumen/kontrak eksternal yang mencantumkan nama lama — sistem ini cuma mengubah tampilan aplikasi

## Troubleshooting

- **Logo tidak berubah setelah upload?** Coba hard refresh browser (Ctrl+Shift+R) — logo lama mungkin masih ter-cache
- **Mau ganti ikon aplikasi PWA juga?** Ini butuh bantuan teknis (generate 8 ukuran ikon dari file logo baru), tidak bisa dilakukan sendiri lewat menu ini
MARKDOWN
            ,
            'modul'  => 'lainnya',
            'urutan' => 50,
            'aktif'  => true,
        ]);
    }
}
