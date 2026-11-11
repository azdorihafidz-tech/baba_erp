# ERP Manajemen Berkah Mulyo

## Overview
Sistem ERP **multi-cabang** dan **fully responsive** untuk **Berkah Mulyo**, sebuah usaha jasa penggilingan daging dan produksi olahan daging (bakso, sosis, tempura). Sistem ini mengelola seluruh operasional bisnis mulai dari penerimaan order pelanggan, penggilingan, produksi, stok bahan baku, hingga keuangan, penggajian karyawan, penilaian karyawan 360°, manajemen aset, dan analisis BEP — di setiap cabang maupun secara keseluruhan (pusat). Dapat diakses dari desktop, tablet, maupun HP/mobile.

## Tentang Bisnis
Berkah Mulyo memiliki **beberapa cabang**, **gudang pusat**, dan **dua jenis layanan utama**:

### 1. Jasa Penggilingan (Jasa Giling)
- Pelanggan membawa daging sendiri untuk digiling
- Hasil gilingan bisa berupa adonan bakso, sosis, atau tempura
- Biaya dihitung berdasarkan berat/kg daging yang digiling

### 2. Produksi Sendiri
- Berkah Mulyo membeli bahan baku sendiri
- Memproduksi bakso, sosis, dan tempura dengan merek sendiri
- Produk dijual langsung ke pelanggan / reseller

## Tech Stack
- **Framework:** Laravel 12 (PHP)
- **Database:** MySQL
- **Frontend:** Blade Templates + Bootstrap 5 (responsive)
- **Authentication:** Laravel Breeze / Fortify (sesuaikan)
- **Icons:** Bootstrap Icons / Font Awesome
- **Charts:** Chart.js (untuk grafik BEP, dashboard, laporan)
- **Face Recognition:** face-api.js v1.7.13 Vladmandic fork (via cdnjs, client-side TensorFlow.js)
- **Peta GPS:** Leaflet 1.9.4 + Nominatim (OpenStreetMap) untuk setting lokasi cabang
- **Audit Trail:** spatie/laravel-activitylog v4
- **Backup Database:** spatie/laravel-backup

## Responsive Design & Multi-Device

### Prinsip Utama
Seluruh tampilan aplikasi **WAJIB responsive** dan berfungsi baik di 3 ukuran layar:

| Device | Breakpoint | Keterangan |
|--------|-----------|------------|
| **Mobile / HP** | < 768px | Sidebar collapse jadi hamburger menu, tabel scroll horizontal, form full-width |
| **Tablet** | 768px - 1024px | Sidebar bisa toggle, tabel menyesuaikan, form 2 kolom |
| **Desktop** | > 1024px | Layout penuh dengan sidebar tetap terlihat |

### Aturan Responsive yang WAJIB Diikuti
1. **Gunakan Bootstrap 5 Grid System** — `container-fluid`, `row`, `col-12 col-md-6 col-lg-4` dll
2. **Mobile-first approach** — desain untuk mobile dulu, lalu scale up ke tablet & desktop
3. **Sidebar / Navigation:**
   - Desktop: sidebar tetap terlihat di kiri
   - Tablet: sidebar bisa di-toggle (collapse/expand)
   - Mobile: sidebar tersembunyi, muncul sebagai offcanvas/hamburger menu
4. **Tabel Data:**
   - Desktop: tampil penuh semua kolom
   - Tablet: kolom kurang penting disembunyikan (`d-none d-md-table-cell`)
   - Mobile: tabel bisa di-scroll horizontal (`table-responsive`) atau berubah jadi card/list view
5. **Form Input:**
   - Desktop: bisa 2-3 kolom per baris
   - Tablet: 2 kolom per baris
   - Mobile: 1 kolom per baris (full-width)
6. **Dashboard Cards / Widgets:**
   - Desktop: 4 card per baris
   - Tablet: 2 card per baris
   - Mobile: 1 card per baris (stack vertical)
7. **Grafik / Chart:**
   - Responsive chart dengan `maintainAspectRatio: false` dan container yang flexible
   - Touch-friendly tooltips untuk mobile
8. **Tombol & Action:**
   - Desktop: tombol dengan teks + icon
   - Mobile: tombol icon saja atau floating action button (FAB) untuk aksi utama
   - Minimum touch target 44x44px untuk mobile
9. **Modal & Dialog:**
   - Desktop: modal biasa
   - Mobile: modal full-screen (`modal-fullscreen-sm-down`)
10. **Print / Export:**
    - Halaman print menggunakan CSS `@media print` terpisah
    - Struk POS optimized untuk printer thermal (width 80mm)

### CSS Convention untuk Responsive
```css
/* Mobile first - default styles untuk mobile */
.card-dashboard { width: 100%; margin-bottom: 1rem; }

/* Tablet */
@media (min-width: 768px) {
  .card-dashboard { width: 50%; }
}

/* Desktop */
@media (min-width: 1024px) {
  .card-dashboard { width: 25%; }
}
```

### Komponen Blade Responsive
- Semua komponen Blade harus menggunakan class Bootstrap responsive
- Buat komponen reusable: `<x-responsive-table>`, `<x-mobile-card>`, `<x-sidebar-menu>`
- Gunakan `<x-slot>` untuk content yang berbeda di mobile vs desktop jika perlu

## Arsitektur Multi-Cabang

### Konsep Utama
- Setiap data transaksi (order, stok, keuangan, dll) **wajib memiliki `cabang_id`**
- User terikat ke satu atau lebih cabang
- Tampilan data bisa di-filter **per cabang** atau **keseluruhan (semua cabang)**

### Tabel `cabangs`
```
id, nama_cabang, kode_cabang, alamat, telepon, kepala_cabang_id, tipe (cabang/gudang_pusat), is_active, created_at, updated_at
```
- Gudang pusat juga disimpan sebagai record di tabel `cabangs` dengan `tipe = 'gudang_pusat'`
- Cabang biasa memiliki `tipe = 'cabang'`

### Tabel `cabang_user` (pivot)
```
id, cabang_id, user_id, is_default
```

### Scope & Filter Data
- **Global Scope:** Semua query transaksi otomatis di-filter berdasarkan cabang aktif user (kecuali role Pusat/Owner)
- **Switcher Cabang:** Di navbar ada dropdown untuk pindah cabang (untuk user multi-cabang dan Owner)
- **Mode "Semua Cabang":** Khusus Owner/Admin Pusat, bisa lihat data gabungan semua cabang
- Gunakan Laravel Global Scope atau middleware untuk filter `cabang_id` secara otomatis

### Role per Cabang
| Role | Akses Cabang | Keterangan |
|------|-------------|------------|
| Owner / Admin Pusat | Semua cabang + gudang pusat + mode keseluruhan | Bisa lihat & kelola semua |
| Admin Gudang Pusat | Gudang pusat | Kelola stok gudang, distribusi ke cabang |
| Manajer Cabang | Cabang sendiri | Kelola operasional 1 cabang |
| Kasir | Cabang sendiri | POS & penjualan di 1 cabang |
| Operator Produksi | Cabang sendiri | Produksi & stok di 1 cabang |

## Arsitektur Stok & Pembelian (Gudang Pusat ↔ Cabang)

### Konsep Alur Stok
```
VENDOR / SUPPLIER
       │
       ▼
┌──────────────────┐    (Alur Normal)
│   GUDANG PUSAT   │◄── Semua pembelian bahan dari vendor masuk ke gudang pusat
│   (Stok Pusat)   │
└────────┬─────────┘
         │
         ▼  (Distribusi / Permintaan Cabang)
┌────────────────────────────────────────┐
│                                        │
▼                  ▼                     ▼
┌──────────┐  ┌──────────┐  ┌──────────┐
│ CABANG A │  │ CABANG B │  │ CABANG C │
│(Stok Toko)│ │(Stok Toko)│ │(Stok Toko)│
└──────────┘  └──────────┘  └──────────┘
       ▲                          ▲
       │    (Kondisi Mendesak)    │
       └──────── VENDOR ──────────┘
         Cabang beli langsung ke vendor
```

### Alur Pembelian Bahan Baku

#### A. Alur Normal (Gudang Pusat → Cabang)
1. **Gudang pusat** membeli bahan baku dari vendor/supplier (Purchase Order)
2. Bahan masuk ke **stok gudang pusat**
3. Cabang membuat **permintaan bahan** (Stock Request) ke gudang pusat
4. Gudang pusat menyetujui dan mengirim bahan (**Transfer Keluar**)
5. Cabang menerima bahan (**Transfer Masuk**), stok cabang bertambah
6. Stok gudang pusat berkurang, stok cabang bertambah

#### B. Alur Mendesak (Cabang → Vendor Langsung)
1. Cabang membuat **PO langsung ke vendor** dengan alasan/catatan "mendesak"
2. Bahan masuk langsung ke **stok cabang**
3. PO ditandai sebagai `pembelian_langsung = true`
4. Perlu **approval dari Manajer Cabang** atau Owner
5. Tercatat di laporan sebagai pembelian di luar jalur normal

### Tabel Stok
```
Tabel: stocks
- id, item_id, lokasi_id (cabang_id atau gudang_pusat_id), qty, qty_minimum, updated_at

Tabel: stock_movements (history keluar masuk)
- id, item_id, lokasi_asal_id, lokasi_tujuan_id, qty, tipe (masuk/keluar/transfer/adjustment), 
  referensi_type (purchase_order/stock_request/produksi/penjualan), referensi_id, 
  catatan, user_id, created_at
```

### Tabel Permintaan Bahan (Stock Request)
```
Tabel: stock_requests
- id, cabang_id (cabang peminta), nomor_request, tanggal_request, 
  status (pending/disetujui/ditolak/dikirim/diterima), 
  approved_by, catatan, created_at, updated_at

Tabel: stock_request_items
- id, stock_request_id, item_id, qty_diminta, qty_disetujui, qty_diterima, catatan
```

### Tabel Transfer Stok
```
Tabel: stock_transfers
- id, nomor_transfer, dari_lokasi_id, ke_lokasi_id, 
  tanggal_kirim, tanggal_terima, 
  status (draft/dikirim/diterima_sebagian/diterima/dibatalkan),
  stock_request_id (nullable, jika dari permintaan cabang),
  catatan, created_by, received_by, created_at, updated_at

Tabel: stock_transfer_items
- id, stock_transfer_id, item_id, qty_kirim, qty_terima, catatan
```

### Alur Status Pembelian & Distribusi
```
PEMBELIAN (Gudang Pusat):
  Draft → Disetujui → Dikirim Supplier → Diterima → Stok Gudang Pusat (+)

PERMINTAAN CABANG (Stock Request):
  Cabang Minta → Gudang Setujui → Buat Transfer → Kirim → Cabang Terima
  
TRANSFER STOK:
  Draft → Dikirim → Diterima (Sebagian/Penuh)
  - Stok asal berkurang saat status "Dikirim"
  - Stok tujuan bertambah saat status "Diterima"

PEMBELIAN LANGSUNG (Cabang → Vendor):
  Draft → Perlu Approval → Disetujui → Dikirim Supplier → Diterima → Stok Cabang (+)
  - Ditandai flag `pembelian_langsung = true`
```

## Arsitektur Aset & Penyusutan (Depresiasi)

### Konsep Manajemen Aset
- Setiap aset (mesin giling, freezer, kendaraan, peralatan, dll) **dicatat per lokasi (cabang/gudang)**
- Aset memiliki **nilai perolehan (harga beli)** dan **nilai residu (sisa)**
- Sistem menghitung **penyusutan (depresiasi) otomatis** setiap bulan/tahun
- Aset bisa **dipindahkan antar cabang** (mutasi aset)

### Metode Penyusutan yang Didukung
1. **Garis Lurus (Straight Line):** Penyusutan sama rata setiap tahun
   - Formula: `(Harga Perolehan - Nilai Residu) / Umur Ekonomis`
2. **Saldo Menurun (Declining Balance):** Penyusutan lebih besar di awal, mengecil seiring waktu
   - Formula: `Nilai Buku Awal Tahun × Tarif Penyusutan`
3. **Satuan Hasil Produksi (Units of Production):** Penyusutan berdasarkan penggunaan/output
   - Formula: `(Harga Perolehan - Nilai Residu) / Total Estimasi Produksi × Produksi Aktual`

### Tabel Aset
```
Tabel: assets
- id, kode_aset, nama_aset, kategori_aset_id, lokasi_id (cabang/gudang), 
  tanggal_perolehan, harga_perolehan, nilai_residu, umur_ekonomis_bulan,
  metode_penyusutan (garis_lurus/saldo_menurun/satuan_produksi),
  kondisi (baik/rusak_ringan/rusak_berat/dihapuskan),
  status (aktif/tidak_aktif/dijual/dihapuskan),
  catatan, foto, created_at, updated_at

Tabel: asset_categories
- id, nama_kategori, kode_kategori, created_at, updated_at
  (contoh: Mesin Produksi, Kendaraan, Peralatan Dapur, Elektronik, Furniture, Bangunan)

Tabel: asset_depreciations (catatan penyusutan per periode)
- id, asset_id, periode (YYYY-MM), nilai_buku_awal, jumlah_penyusutan, 
  akumulasi_penyusutan, nilai_buku_akhir, created_at

Tabel: asset_mutations (perpindahan aset antar lokasi)
- id, asset_id, dari_lokasi_id, ke_lokasi_id, tanggal_mutasi, 
  alasan, approved_by, created_at

Tabel: asset_maintenances (riwayat perawatan/perbaikan)
- id, asset_id, tanggal_maintenance, jenis (perawatan_rutin/perbaikan/overhaul),
  deskripsi, biaya, vendor_maintenance, created_at

Tabel: asset_disposals (penghapusan/penjualan aset)
- id, asset_id, tanggal_disposal, tipe (dijual/dibuang/dihibahkan),
  nilai_jual, nilai_buku_saat_disposal, keuntungan_kerugian,
  pembeli, catatan, approved_by, created_at
```

### Alur Aset
```
PEMBELIAN ASET:
  Pengajuan → Approval → Pembelian → Dicatat di Sistem → Penyusutan Berjalan

PENYUSUTAN BULANAN (otomatis/manual trigger):
  Hitung depresiasi per aset → Catat di asset_depreciations → Update nilai buku

MUTASI ASET:
  Cabang A → Pengajuan Mutasi → Approval → Pindah ke Cabang B → Update lokasi_id

PENGHAPUSAN ASET:
  Aset rusak/dijual → Pengajuan → Approval → Catat disposal → Update status
  → Hitung keuntungan/kerugian dari selisih nilai jual vs nilai buku
```

## Analisis Break Even Point (BEP)

### Konsep BEP
BEP menghitung **titik impas** di mana total pendapatan = total biaya (tidak untung, tidak rugi).
Sistem menghitung BEP **per cabang** dan **keseluruhan**, serta **per produk/jasa**.

### Komponen Perhitungan BEP

#### Biaya Tetap (Fixed Cost) — per bulan
- Gaji karyawan tetap
- Sewa tempat / gedung
- Penyusutan aset (dari modul aset)
- Listrik & air (komponen tetap)
- Asuransi
- Biaya administrasi tetap

#### Biaya Variabel (Variable Cost) — per unit produk
- Bahan baku (daging, tepung, bumbu per kg produk)
- Kemasan / packaging per unit
- Gas / listrik produksi (komponen variabel)
- Upah lembur / tenaga borongan

#### Harga Jual — per unit produk / per kg jasa
- Harga jual bakso per kg/pack
- Harga jual sosis per kg/pack
- Harga jual tempura per kg/pack
- Tarif jasa giling per kg

### Formula BEP
```
BEP (dalam unit)  = Biaya Tetap / (Harga Jual per Unit - Biaya Variabel per Unit)
BEP (dalam rupiah) = Biaya Tetap / (1 - (Biaya Variabel per Unit / Harga Jual per Unit))
Margin Kontribusi  = Harga Jual per Unit - Biaya Variabel per Unit
```

### Tabel BEP
```
Tabel: bep_settings (konfigurasi komponen BEP per cabang per periode)
- id, cabang_id, periode (YYYY-MM), 
  total_biaya_tetap, catatan, created_at, updated_at

Tabel: bep_fixed_cost_items (rincian biaya tetap)
- id, bep_setting_id, nama_komponen, kategori (gaji/sewa/depresiasi/listrik/asuransi/lainnya),
  jumlah, catatan

Tabel: bep_products (data BEP per produk/jasa)
- id, bep_setting_id, nama_produk, tipe (produk/jasa_giling),
  harga_jual_per_unit, biaya_variabel_per_unit, 
  bep_unit, bep_rupiah, margin_kontribusi,
  target_penjualan_unit, catatan

Tabel: bep_reports (laporan BEP hasil perhitungan)
- id, cabang_id, periode (YYYY-MM),
  total_biaya_tetap, total_biaya_variabel, total_pendapatan,
  bep_tercapai (boolean), selisih_dari_bep,
  created_at
```

### Fitur BEP di Dashboard
- **Grafik BEP:** Visualisasi titik impas (grafik garis: biaya tetap, biaya total, pendapatan)
- **BEP per Produk:** Berapa kg bakso/sosis/tempura harus dijual untuk BEP
- **BEP per Cabang:** Perbandingan pencapaian BEP antar cabang
- **Monitoring Real-time:** Sudah berapa % dari BEP tercapai bulan ini
- **Proyeksi:** Estimasi kapan BEP tercapai berdasarkan trend penjualan
- **Alert:** Notifikasi jika penjualan di bawah target BEP

### Integrasi BEP dengan Modul Lain
- **Biaya tetap** otomatis diambil dari: gaji karyawan (HR), sewa (keuangan), depresiasi aset (aset)
- **Biaya variabel** otomatis diambil dari: harga bahan baku (stok/pembelian)
- **Pendapatan** otomatis diambil dari: penjualan & jasa giling (POS)

## Penilaian Karyawan 360° (Performance Review)

### Konsep Penilaian
- Penilaian dilakukan **setiap 3 bulan (triwulan)**: Q1 (Jan-Mar), Q2 (Apr-Jun), Q3 (Jul-Sep), Q4 (Okt-Des)
- Menggunakan metode **360 degree feedback**: dinilai oleh atasan, rekan kerja, dan diri sendiri
- Setiap aspek dinilai dengan **skala 1-5** (1=Sangat Kurang, 2=Kurang, 3=Cukup, 4=Baik, 5=Sangat Baik)
- Hasil akhir adalah **rata-rata tertimbang** dari semua penilai

### Aspek Penilaian (5 Aspek)
| No | Aspek | Bobot | Deskripsi |
|----|-------|-------|-----------|
| 1 | **Kedisiplinan** | 25% | Kehadiran, ketepatan waktu, kepatuhan aturan |
| 2 | **Kinerja / Produktivitas** | 30% | Hasil kerja, kecepatan, kualitas output, pencapaian target |
| 3 | **Kerjasama Tim** | 20% | Komunikasi, kolaborasi, membantu rekan, sikap positif |
| 4 | **Kebersihan & Kerapian** | 10% | Kebersihan area kerja, kerapian diri, hygiene produksi |
| 5 | **Inisiatif / Kemandirian** | 15% | Proaktif, problem solving, tidak selalu menunggu perintah |

### Tipe Penilai & Bobot Penilai
| Penilai | Bobot | Keterangan |
|---------|-------|------------|
| **Atasan Langsung** (Manajer/Kepala Cabang) | 50% | Penilaian utama |
| **Rekan Kerja** (Peer Review, 2-3 orang) | 30% | Dipilih secara acak atau ditentukan atasan |
| **Self Assessment** (Diri Sendiri) | 20% | Karyawan menilai diri sendiri |

### Kategori Hasil Penilaian
| Skor Rata-rata | Predikat | Warna |
|---------------|----------|-------|
| 4.5 - 5.0 | **Sangat Baik** | Hijau |
| 3.5 - 4.4 | **Baik** | Biru |
| 2.5 - 3.4 | **Cukup** | Kuning |
| 1.5 - 2.4 | **Kurang** | Orange |
| 1.0 - 1.4 | **Sangat Kurang** | Merah |

### Alur Penilaian
```
PROSES PENILAIAN TRIWULANAN:
  1. Admin/Manajer membuka periode penilaian (misal Q1 2026)
  2. Sistem otomatis assign penilai:
     - Atasan langsung karyawan
     - 2-3 rekan kerja (random/ditentukan)
     - Karyawan sendiri (self assessment)
  3. Semua penilai mengisi form penilaian (skala 1-5 per aspek + komentar)
  4. Deadline pengisian (misal 2 minggu)
  5. Setelah semua penilai selesai → sistem hitung skor akhir otomatis
  6. Manajer review & finalisasi
  7. Hasil bisa dilihat oleh karyawan (tanpa tahu siapa rekan yang menilai)

REMINDER OTOMATIS:
  - Notifikasi saat periode penilaian dibuka
  - Reminder jika belum mengisi mendekati deadline
  - Notifikasi saat hasil penilaian sudah final
```

### Tabel Penilaian Karyawan
```
Tabel: evaluation_periods (periode penilaian)
- id, nama_periode (contoh: "Q1 2026"), cabang_id,
  tanggal_mulai, tanggal_selesai, deadline_pengisian,
  status (draft/dibuka/ditutup/final),
  created_by, created_at, updated_at

Tabel: evaluation_aspects (master aspek penilaian)
- id, nama_aspek, deskripsi, bobot_persen, urutan, is_active, created_at

Tabel: evaluations (penilaian per karyawan per periode)
- id, evaluation_period_id, karyawan_id, cabang_id,
  skor_akhir, predikat (sangat_baik/baik/cukup/kurang/sangat_kurang),
  catatan_manajer, status (proses/selesai/final),
  finalized_by, finalized_at, created_at, updated_at

Tabel: evaluation_reviewers (siapa saja penilai untuk setiap karyawan)
- id, evaluation_id, reviewer_id (user_id penilai),
  tipe_reviewer (atasan/rekan_kerja/self_assessment),
  bobot_reviewer_persen, status (belum_isi/sudah_isi),
  submitted_at

Tabel: evaluation_scores (detail skor per aspek per penilai)
- id, evaluation_reviewer_id, evaluation_aspect_id,
  skor (1-5), komentar

Tabel: evaluation_summaries (ringkasan skor per aspek setelah dihitung)
- id, evaluation_id, evaluation_aspect_id,
  skor_atasan, skor_rekan_rata, skor_self,
  skor_tertimbang, skor_final
```

### Formula Perhitungan Skor
```
Per Aspek:
  Skor Aspek = (Skor Atasan × 50%) + (Rata-rata Skor Rekan × 30%) + (Skor Self × 20%)

Skor Akhir:
  Skor Akhir = Σ (Skor Aspek × Bobot Aspek)
  
Contoh:
  Kedisiplinan:   (4×50% + 3.5×30% + 4×20%) × 25% = 3.85 × 25% = 0.9625
  Kinerja:        (4×50% + 4×30% + 3×20%) × 30%   = 3.80 × 30% = 1.1400
  Kerjasama:      (3×50% + 4×30% + 4×20%) × 20%   = 3.50 × 20% = 0.7000
  Kebersihan:     (5×50% + 4×30% + 4×20%) × 10%   = 4.50 × 10% = 0.4500
  Inisiatif:      (3×50% + 3.5×30% + 4×20%) × 15% = 3.35 × 15% = 0.5025
  ─────────────────────────────────────────────────────────────────
  SKOR AKHIR = 3.755 → Predikat: BAIK
```

### Fitur Penilaian di Aplikasi
- **Form Penilaian:** Interface mudah dengan slider/rating stars per aspek + kolom komentar
- **Dashboard HR:** Ringkasan penilaian per cabang, trend perkembangan karyawan
- **Radar Chart:** Visualisasi 5 aspek penilaian per karyawan (spider/radar chart)
- **Perbandingan Periode:** Grafik perkembangan skor karyawan dari Q1 ke Q2 ke Q3 dst
- **Ranking Karyawan:** Per cabang dan keseluruhan berdasarkan skor
- **Histori Penilaian:** Seluruh riwayat penilaian per karyawan dari waktu ke waktu
- **Export:** Cetak hasil penilaian per karyawan (PDF) dan rekap per cabang (Excel)
- **Anonimitas:** Skor dari rekan kerja ditampilkan sebagai rata-rata, bukan per individu

### Integrasi Penilaian dengan Modul Lain
- **Absensi (HR):** Data kehadiran otomatis jadi referensi untuk aspek Kedisiplinan
- **Produksi:** Data output produksi bisa jadi referensi untuk aspek Kinerja/Produktivitas
- **Penggajian:** Hasil penilaian bisa jadi dasar kenaikan gaji / bonus triwulanan

## Project Structure (Laravel Default)
```
erp-manajemenberkahmulyo/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── CabangController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── PenjualanController.php
│   │   │   ├── StokController.php
│   │   │   ├── StockRequestController.php
│   │   │   ├── StockTransferController.php
│   │   │   ├── PurchaseOrderController.php
│   │   │   ├── KeuanganController.php
│   │   │   ├── KaryawanController.php
│   │   │   ├── PenggajianController.php
│   │   │   ├── EvaluationController.php
│   │   │   ├── AssetController.php
│   │   │   ├── AssetDepreciationController.php
│   │   │   ├── BepController.php
│   │   │   ├── LaporanController.php
│   │   │   └── UserController.php
│   │   └── Middleware/
│   │       └── CabangMiddleware.php
│   ├── Models/
│   │   ├── Cabang.php
│   │   ├── User.php
│   │   ├── Order.php
│   │   ├── Item.php
│   │   ├── Stock.php
│   │   ├── StockMovement.php
│   │   ├── StockRequest.php
│   │   ├── StockTransfer.php
│   │   ├── PurchaseOrder.php
│   │   ├── Supplier.php
│   │   ├── Karyawan.php
│   │   ├── EvaluationPeriod.php
│   │   ├── EvaluationAspect.php
│   │   ├── Evaluation.php
│   │   ├── EvaluationReviewer.php
│   │   ├── EvaluationScore.php
│   │   ├── EvaluationSummary.php
│   │   ├── Asset.php
│   │   ├── AssetCategory.php
│   │   ├── AssetDepreciation.php
│   │   ├── AssetMutation.php
│   │   ├── AssetMaintenance.php
│   │   ├── AssetDisposal.php
│   │   ├── BepSetting.php
│   │   ├── BepProduct.php
│   │   ├── BepReport.php
│   │   └── Scopes/
│   │       └── CabangScope.php
│   ├── Services/
│   │   ├── StokService.php              # Logic stok masuk/keluar/transfer
│   │   ├── PenjualanService.php         # Logic order & pembayaran
│   │   ├── ProduksiService.php          # Logic produksi & pengurangan bahan
│   │   ├── TransferService.php          # Logic transfer gudang ↔ cabang
│   │   ├── EvaluationService.php        # Logic perhitungan skor penilaian 360°
│   │   ├── AssetDepreciationService.php # Logic perhitungan penyusutan aset
│   │   └── BepCalculationService.php    # Logic perhitungan BEP
│   ├── Enums/
│   │   ├── TipeCabang.php               # cabang, gudang_pusat
│   │   ├── StatusOrder.php              # pending, proses, selesai
│   │   ├── StatusTransfer.php           # draft, dikirim, diterima
│   │   ├── TipeStockMovement.php        # masuk, keluar, transfer, adjustment
│   │   ├── MetodePenyusutan.php         # garis_lurus, saldo_menurun, satuan_produksi
│   │   ├── KondisiAset.php              # baik, rusak_ringan, rusak_berat, dihapuskan
│   │   ├── StatusAset.php              # aktif, tidak_aktif, dijual, dihapuskan
│   │   ├── TipeReviewer.php            # atasan, rekan_kerja, self_assessment
│   │   └── PredikatEvaluasi.php        # sangat_baik, baik, cukup, kurang, sangat_kurang
│   └── Traits/
│       └── HasCabang.php
├── resources/
│   └── views/
│       ├── layouts/
│       │   ├── app.blade.php            # Layout utama (responsive sidebar + navbar)
│       │   └── print.blade.php          # Layout untuk print/PDF
│       ├── components/
│       │   ├── responsive-table.blade.php
│       │   ├── mobile-card.blade.php
│       │   ├── sidebar-menu.blade.php
│       │   ├── cabang-switcher.blade.php
│       │   └── dashboard-card.blade.php
│       ├── dashboard/
│       ├── cabang/
│       ├── penjualan/
│       ├── stok/
│       │   ├── index.blade.php
│       │   ├── request/
│       │   └── transfer/
│       ├── pembelian/
│       ├── keuangan/
│       ├── karyawan/
│       │   ├── index.blade.php
│       │   ├── absensi/
│       │   ├── penggajian/
│       │   └── evaluasi/
│       │       ├── periods.blade.php       # Daftar periode penilaian
│       │       ├── form.blade.php          # Form isi penilaian (responsive)
│       │       ├── result.blade.php        # Hasil penilaian + radar chart
│       │       ├── history.blade.php       # Riwayat penilaian karyawan
│       │       └── ranking.blade.php       # Ranking karyawan per cabang
│       ├── aset/
│       │   ├── index.blade.php
│       │   ├── show.blade.php
│       │   ├── depreciation.blade.php
│       │   ├── mutation.blade.php
│       │   └── maintenance.blade.php
│       ├── bep/
│       │   ├── index.blade.php
│       │   ├── settings.blade.php
│       │   ├── products.blade.php
│       │   └── report.blade.php
│       ├── laporan/
│       └── user/
├── routes/
│   ├── web.php
│   └── api.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── public/
├── config/
└── CLAUDE.md
```

### Trait HasCabang (untuk semua model yang terkait cabang)
```php
// Semua model transaksi harus use HasCabang
// Trait ini otomatis menambahkan:
// - Relasi belongsTo Cabang
// - Global scope filter by cabang aktif
// - Auto-set cabang_id saat create
```

## Commands
- `php artisan serve` — Jalankan development server
- `php artisan migrate` — Jalankan migration
- `php artisan migrate:fresh --seed` — Reset database + seed data awal
- `php artisan db:seed` — Jalankan seeder saja
- `php artisan make:model NamaModel -mcr` — Buat model + migration + controller resource
- `composer install` — Install PHP dependencies
- `npm install && npm run dev` — Install & compile frontend assets

## Modules

### 1. Manajemen Cabang & Gudang Pusat
- CRUD data cabang (nama, alamat, kode, telepon)
- Setup gudang pusat (tipe = gudang_pusat)
- Assign kepala cabang / admin gudang
- Aktif / nonaktifkan cabang
- **Cabang Switcher** di navbar untuk pindah konteks cabang/gudang

### 2. Manajemen User & Roles
- Login, register, profile
- Role-based access: Owner/Admin Pusat, Admin Gudang, Manajer Cabang, Kasir, Operator Produksi
- Permission per modul
- **Assign user ke cabang/gudang** (satu user bisa di beberapa lokasi)

### 3. Manajemen Penjualan / POS
- **Order Jasa Giling:** Input pelanggan, berat daging, jenis olahan (bakso/sosis/tempura), harga per kg
- **Order Produk Jadi:** Penjualan bakso/sosis/tempura produksi Berkah Mulyo
- Invoice & struk pembayaran
- Riwayat transaksi per pelanggan
- **Data otomatis tercatat per cabang**

### 4. Manajemen Stok / Inventori
- **Stok Gudang Pusat:** Stok utama bahan baku dari vendor
- **Stok Cabang (Toko):** Stok bahan baku & produk jadi per cabang
- Stok kemasan / packaging
- Notifikasi stok minimum (per lokasi)
- History keluar masuk barang (stock movements) per lokasi
- **Permintaan Bahan (Stock Request):** Cabang request bahan ke gudang pusat
- **Transfer Stok:** Pengiriman bahan dari gudang pusat ke cabang (atau antar cabang)
- **Penerimaan Transfer:** Cabang konfirmasi terima barang dari gudang

### 5. Manajemen Pembelian / Procurement
- Daftar supplier / vendor bahan baku
- **Purchase Order via Gudang Pusat** (alur normal): Gudang pusat PO ke vendor → masuk stok gudang
- **Purchase Order Langsung oleh Cabang** (kondisi mendesak): Cabang PO ke vendor → masuk stok cabang → butuh approval → ditandai `pembelian_langsung`
- Penerimaan barang & cek kualitas
- History pembelian per lokasi
- **Laporan pembelian normal vs pembelian langsung/mendesak**

### 6. Manajemen Keuangan / Akuntansi
- Pencatatan pemasukan (penjualan + jasa giling) **per cabang**
- Pencatatan pengeluaran (pembelian bahan, gaji, operasional) **per cabang & gudang pusat**
- Kas masuk & kas keluar harian per lokasi
- Laporan laba rugi **per cabang dan konsolidasi**
- Rekonsiliasi keuangan
- **Biaya transfer bahan dari gudang ke cabang** (jika ada ongkos kirim)
- **Integrasi dengan depresiasi aset** (beban penyusutan masuk ke laporan keuangan)

### 7. Manajemen Karyawan / HR / Penggajian
- Data karyawan (biodata, jabatan, tanggal masuk) **per cabang/gudang**
- Absensi harian per lokasi
- Perhitungan gaji (gaji pokok + lembur + potongan)
- Slip gaji
- Cuti & izin
- **Penilaian Karyawan 360°** (lihat detail di bawah)

### 8. Penilaian Karyawan 360° (Performance Review)
- **Periode Triwulanan:** Q1, Q2, Q3, Q4 per tahun
- **5 Aspek Penilaian:** Kedisiplinan (25%), Kinerja (30%), Kerjasama (20%), Kebersihan (10%), Inisiatif (15%)
- **3 Tipe Penilai:** Atasan langsung (50%), Rekan kerja 2-3 orang (30%), Self assessment (20%)
- **Skala 1-5** per aspek dengan komentar
- **Skor otomatis** dihitung dari rata-rata tertimbang semua penilai × bobot aspek
- **Predikat:** Sangat Baik / Baik / Cukup / Kurang / Sangat Kurang
- **Radar Chart** visualisasi 5 aspek per karyawan
- **Trend & History:** Grafik perkembangan skor per karyawan lintas periode
- **Ranking** karyawan per cabang dan keseluruhan
- **Anonimitas** rekan kerja (skor ditampilkan sebagai rata-rata)
- **Reminder otomatis** saat periode dibuka dan mendekati deadline
- **Integrasi:** Data absensi → Kedisiplinan, Output produksi → Kinerja, Hasil → Dasar kenaikan gaji/bonus

### 9. Manajemen Aset & Penyusutan (Depresiasi)
- **Pendataan Aset:** CRUD data aset per lokasi
- **Kategori Aset:** Mesin Produksi, Kendaraan, Peralatan Dapur, Elektronik, Furniture, Bangunan
- **Pembelian Aset:** Pencatatan pembelian aset baru dengan harga perolehan
- **Penyusutan Otomatis:** Garis Lurus, Saldo Menurun, Satuan Produksi
- **Nilai Buku:** Tracking real-time
- **Mutasi Aset:** Perpindahan antar cabang dengan approval
- **Perawatan Aset:** Riwayat maintenance & biaya
- **Penghapusan/Penjualan Aset:** Disposal + perhitungan untung/rugi

### 10. Analisis Break Even Point (BEP)
- **Setup Komponen:** Biaya Tetap + Biaya Variabel + Harga Jual
- **Perhitungan:** BEP unit, BEP rupiah, margin kontribusi
- **Dashboard:** Grafik BEP, monitoring %, proyeksi, alert
- **Integrasi otomatis** dengan modul HR, Keuangan, Aset, POS

### 11. Laporan / Dashboard
- **Dashboard per cabang:** Ringkasan harian (omzet, order, stok rendah)
- **Dashboard gudang pusat:** Stok gudang, permintaan, PO
- **Dashboard keseluruhan:** Semua cabang + gudang, perbandingan performa
- Laporan penjualan — filter per cabang atau semua
- Laporan stok — per lokasi atau semua
- Laporan pergerakan stok — transfer gudang ↔ cabang
- Laporan pembelian — normal vs langsung
- Laporan keuangan — per cabang dan konsolidasi
- **Laporan aset & penyusutan** — nilai buku, jadwal depresiasi, maintenance
- **Laporan BEP** — pencapaian per produk, per cabang, keseluruhan
- **Laporan penilaian karyawan** — skor per karyawan, ranking, trend per periode
- Laporan kinerja karyawan per lokasi
- **Ranking / perbandingan antar cabang**
- Export laporan ke PDF / Excel
- **Semua laporan & dashboard responsive** (mobile, tablet, desktop)

## Database Naming Conventions
- Nama tabel: plural, snake_case (contoh: `orders`, `order_items`, `raw_materials`, `cabangs`)
- Nama kolom: snake_case (contoh: `total_harga`, `nama_pelanggan`, `cabang_id`)
- Foreign key: `nama_tabel_singular_id` (contoh: `order_id`, `user_id`, `cabang_id`)
- Enum/status menggunakan string (contoh: `status` => 'pending', 'proses', 'selesai')
- **Setiap tabel transaksi WAJIB punya kolom `cabang_id` atau `lokasi_id`**
- Lokasi bisa merujuk ke cabang atau gudang pusat (keduanya ada di tabel `cabangs`)

## Ketentuan History / Audit Trail

### Aturan Wajib: Tabel History untuk Setiap Tabel yang Bisa Diubah/Dihapus
Setiap tabel yang memiliki fungsi **update/edit** atau **hapus** WAJIB memiliki tabel pasangan dengan akhiran `_histories`.

### Tujuan
Menyimpan snapshot data **sebelum** diubah atau dihapus, sebagai pegangan jika terjadi kesalahan atau sengketa data.

### Struktur Tabel History
Setiap tabel `_histories` wajib memiliki kolom:
```
id             : bigint unsigned auto_increment
[nama_tabel]_id: bigint unsigned — ID record asli yang diubah/dihapus
action         : enum('updated','deleted') — jenis aksi yang dilakukan
data_lama      : JSON — snapshot seluruh data record sebelum diubah/dihapus
changed_by     : bigint unsigned — user_id yang melakukan perubahan (disimpan langsung, bukan relasi)
changed_by_name: varchar(255) — nama user yang melakukan perubahan (disimpan langsung, bukan relasi)
changed_at     : timestamp — waktu perubahan terjadi
keterangan     : varchar(255) nullable — catatan tambahan jika perlu
```

### Implementasi di Laravel
- Gunakan **Eloquent Observer** (`php artisan make:observer`) untuk setiap model
- Observer menangkap event `updating` dan `deleting`
- Pada event tersebut, simpan data lama ke tabel `_histories` sebelum perubahan terjadi
- Daftarkan Observer di `AppServiceProvider` atau langsung di Model via `$observedBy`

### Contoh
```php
// Observer: StockObserver
public function updating(Stock $stock): void
{
    StockHistory::create([
        'stock_id'   => $stock->id,
        'action'     => 'updated',
        'data_lama'  => $stock->getOriginal(), // data sebelum diubah
        'changed_by'      => auth()->id(),
        'changed_by_name' => auth()->user()?->name,
        'changed_at'      => now(),
    ]);
}

public function deleting(Stock $stock): void
{
    StockHistory::create([
        'stock_id'        => $stock->id,
        'action'          => 'deleted',
        'data_lama'       => $stock->toArray(),
        'changed_by'      => auth()->id(),
        'changed_by_name' => auth()->user()?->name,
        'changed_at'      => now(),
    ]);
}
```

### Daftar Tabel yang WAJIB Punya History
| Tabel Utama | Tabel History |
|---|---|
| `stocks` | `stock_histories` |
| `stock_requests` | `stock_request_histories` |
| `stock_request_items` | `stock_request_item_histories` |
| `stock_transfers` | `stock_transfer_histories` |
| `stock_transfer_items` | `stock_transfer_item_histories` |
| `stock_movements` | `stock_movement_histories` |
| `items` | `item_histories` |
| `purchase_orders` | `purchase_order_histories` |
| `purchase_order_items` | `purchase_order_item_histories` |
| `orders` | `order_histories` |
| `order_items` | `order_item_histories` |
| `keuangans` | `keuangan_histories` |
| `karyawans` | `karyawan_histories` |
| `penggajians` | `penggajian_histories` |
| `assets` | `asset_histories` |
| `cabangs` | `cabang_histories` |
| `users` | `user_histories` |

### Aturan Tambahan
- Tabel history **tidak boleh diedit atau dihapus** oleh user biasa — hanya bisa dibaca
- Tabel history **tidak memiliki soft delete** — data di sini permanen
- Data JSON di kolom `data_lama` disimpan lengkap (semua kolom record asli)
- History **tidak perlu** tabel history-nya sendiri (tidak rekursif)

## Coding Conventions
- Gunakan **Resource Controller** untuk semua CRUD
- Gunakan **Form Request** untuk validasi input
- Gunakan **Eloquent Relationships** (hasMany, belongsTo, dll)
- Gunakan **Service class** untuk logic bisnis yang kompleks
- Gunakan **Trait HasCabang** untuk semua model yang terkait cabang/lokasi
- Gunakan **Global Scope** untuk auto-filter data per cabang
- Gunakan **Enum class** untuk status dan tipe (jangan hardcode string)
- **Bootstrap 5 responsive classes** wajib dipakai di semua view
- **Mobile-first approach** — desain untuk mobile dulu, scale up ke desktop
- Blade components untuk UI yang reusable (`<x-responsive-table>`, `<x-dashboard-card>`, dll)
- Penamaan variabel bisnis boleh pakai **Bahasa Indonesia** (contoh: `$totalHarga`, `$beratDaging`)
- Komentar kode boleh pakai **Bahasa Indonesia**
- Setiap model harus punya migration, seeder, dan factory
- **Minimum touch target 44x44px** untuk tombol di mobile
- **Tabel wajib `table-responsive`** untuk scroll horizontal di mobile

## Absensi Face Recognition (Pengenalan Wajah)

### Konsep Utama
- Absensi menggunakan kamera HP/tablet untuk mengenali wajah karyawan
- Setiap karyawan harus **mendaftarkan wajah** terlebih dahulu (foto dari beberapa sudut)
- Saat absen: karyawan berdiri di depan kamera → sistem mengenali wajah → otomatis tercatat
- Menggunakan library **face-api.js** (berbasis TensorFlow.js, jalan di browser tanpa server AI)
- Semua proses pengenalan wajah berjalan **di browser (client-side)**, tidak perlu server GPU

### Jenis Absensi (4 Tipe — Implementasi Aktual)
| Tipe | Kolom di `absensis` | Keterangan |
|------|---------------------|------------|
| **Masuk** | `jam_masuk` | Absen datang pagi / masuk kerja |
| **Keluar** | `jam_keluar` | Absen pulang kerja |
| **Lembur Masuk** | `jam_lembur_masuk` | Mulai lembur setelah jam kerja |
| **Lembur Keluar** | `jam_lembur_keluar` | Selesai lembur |

**Struktur data unified:** 1 karyawan + 1 hari + 1 cabang = **1 record** di tabel `absensis`.
Unique constraint: `(karyawan_id, tanggal, cabang_id)`.
Tabel `face_attendances` hanya menyimpan **log per-scan** (berhasil/gagal), terpisah dari `absensis`.

### Alur Pendaftaran Wajah (Face Registration)
```
1. Admin/Manajer buka menu "Daftarkan Wajah Karyawan"
2. Pilih karyawan dari daftar
3. Kamera HP/tablet aktif
4. Ambil 3-5 foto wajah karyawan dari sudut berbeda (depan, kiri sedikit, kanan sedikit)
5. Sistem generate face descriptor (data numerik wajah) menggunakan face-api.js
6. Face descriptor disimpan di database (kolom face_data di tabel karyawans)
7. Karyawan siap untuk absen wajah
```

### Alur Absensi Wajah
```
1. Karyawan berdiri di depan device absen (tablet/HP khusus di cabang)
2. Kamera aktif, mendeteksi wajah secara real-time
3. Sistem mencocokkan wajah dengan data yang terdaftar di cabang tersebut
4. Jika cocok (confidence > 60%):
   a. Cek lokasi GPS device (harus dalam radius cabang)
   b. Jika GPS valid → absen tercatat otomatis
   c. Tampilkan: foto, nama karyawan, jam, status (Clock In/Clock Out)
   d. Jika sudah clock in hari ini → otomatis jadi clock out
5. Jika tidak cocok → tampilkan pesan "Wajah tidak dikenali"
6. Jika GPS di luar radius → tampilkan pesan "Lokasi di luar area cabang"
```

### Validasi Lokasi GPS
- Setiap cabang memiliki koordinat GPS (latitude, longitude) dan radius toleransi (meter)
- Saat absen, device mengambil lokasi GPS saat ini
- Sistem menghitung jarak antara device dan cabang
- Jika jarak > radius toleransi → absen ditolak
- Default radius: 100 meter (bisa diatur per cabang)

### Device Khusus Per Cabang
- Setiap cabang mendaftarkan 1 device khusus untuk absen (berdasarkan device ID/token)
- Hanya device terdaftar yang bisa digunakan untuk absen wajah
- Device token disimpan di database dan divalidasi saat absen
- Admin bisa reset/ganti device jika diperlukan

### Tabel Tambahan (Kolom Aktual)
```
Tambahan kolom di tabel karyawans:
- face_data (LONGTEXT/JSON) — face descriptor dari face-api.js (128-float array)
- face_registered_at (TIMESTAMP) — kapan wajah didaftarkan
- face_photos (JSON) — path foto-foto pendaftaran wajah

Tabel: absensis (rekap absensi harian — 1 record per karyawan per hari per cabang)
- id, karyawan_id, cabang_id, tanggal
- jam_masuk, jam_keluar, jam_lembur_masuk, jam_lembur_keluar
- status (hadir/alpha/izin/sakit/libur/cuti)
- menit_terlambat, durasi_lembur_menit
- keterangan, input_manual_by, created_at, updated_at, deleted_at
- UNIQUE (karyawan_id, tanggal, cabang_id)

Tabel: face_attendances (log per-scan absensi wajah)
- id, karyawan_id, cabang_id
- tipe (masuk/keluar/lembur_masuk/lembur_keluar)
- waktu, foto_absen (path), confidence_score
- latitude, longitude, jarak_dari_cabang (meter)
- device_id, is_liveness_passed
- status (valid/invalid_lokasi/tidak_dikenali/duplikat/liveness_failed)
- created_at

Tambahan kolom di tabel cabangs:
- latitude (DECIMAL 10,8)
- longitude (DECIMAL 11,8)
- radius_absen_meter (INTEGER, default 100)
- jam_masuk (TIME, default '08:00:00')
- device_absen_token (VARCHAR, nullable)
- device_absen_name (VARCHAR, nullable)

Tabel: shifts (shift kerja per cabang)
- id, cabang_id, nama_shift, jam_masuk, jam_keluar, toleransi_menit, created_at, updated_at

Tabel: hari_liburs (hari libur nasional + per cabang)
- id, tanggal, keterangan, tipe (nasional/cabang), cabang_id (nullable untuk nasional), created_at

Tabel: absen_devices (device terdaftar per cabang)
- id, cabang_id, device_name, device_token,
  is_active, registered_at, last_used_at, created_at
```

### Anti-Spoofing (Keamanan)
- Deteksi liveness: cek apakah wajah berasal dari orang nyata (bukan foto/layar HP)
- Simpan foto saat absen sebagai bukti
- Log semua percobaan absen (berhasil maupun gagal)
- Notifikasi ke Manajer jika ada percobaan absen gagal berulang

### Integrasi dengan Modul Lain
- Data absensi wajah otomatis masuk ke rekap absensi bulanan (modul HR)
- Terlambat (clock in > jam masuk) otomatis tercatat
- Tidak clock out → ditandai "Lupa Absen Pulang", Manajer bisa input manual
- Data kehadiran → referensi penilaian aspek Kedisiplinan (modul Evaluasi 360°)
- Data kehadiran → perhitungan gaji (potongan alpha/terlambat di modul Penggajian)

### Liveness Detection (Anti-Spoofing Aktual)
- **EAR (Eye Aspect Ratio)** threshold = 0.25 untuk deteksi kedipan
- Wajib **kedip 2x** + **gerakan kepala** (yaw detection via landmark wajah face-api.js)
- Timeout **10 detik** — jika tidak lulus → `status = liveness_failed` di `face_attendances`
- Field `is_liveness_passed` di tabel `face_attendances`

### Alur Lengkap Absensi (dengan Liveness — Implementasi Aktual)
```
1. Pilih tipe absen (Masuk / Keluar / Lembur Masuk / Lembur Keluar)
2. Liveness Detection (maks 10 detik):
   - Kedip mata 2x (EAR < 0.25 terdeteksi)
   - Gerakkan kepala kanan/kiri (yaw detection)
3. Face Recognition: cocokkan wajah dengan database (confidence > 60%)
4. Validasi GPS Haversine (jarak device ke cabang ≤ radius_absen_meter)
5. Konfirmasi full-screen + countdown 8 detik (bisa di-cancel)
6. Catat di absensis (upsert per hari) + log di face_attendances
```

### Shift & Jam Kerja
- **Tabel `shifts`** — CRUD per cabang (nama, jam_masuk, jam_keluar, toleransi_menit)
- Karyawan diassign ke shift via kolom `shift_id` di tabel `karyawans`
- Auto-detect telat: `jam_masuk_aktual > shift.jam_masuk + toleransi_menit` → isi `menit_terlambat`
- Durasi lembur: `jam_lembur_keluar − jam_lembur_masuk` (dalam menit) → isi `durasi_lembur_menit`

### Hari Libur
- **Tabel `hari_liburs`** — hari libur nasional + hari libur khusus per cabang
- Status `libur` otomatis pada hari libur di rekap absensi
- CRUD oleh Owner / Manajer Cabang

### Setting Lokasi Cabang via Peta
- Setting GPS cabang menggunakan **Leaflet 1.9.4** (peta interaktif click-to-set)
- Search alamat via **Nominatim** (OpenStreetMap geocoding API, gratis)
- Klik peta atau ketik alamat → koordinat tersimpan di `cabangs.latitude/longitude`
- Setting `radius_absen_meter` dan `jam_masuk` default per cabang di form yang sama

### Dashboard Absensi & Laporan
- **Dashboard real-time** (`/dashboard/absensi`): siapa sudah hadir/belum hari ini, persentase kehadiran
- **Laporan absensi** (`/laporan/absensi`): filter tanggal/karyawan/cabang, tampil foto + GPS + jam
- **Export:** Excel (maatwebsite/excel) dan PDF (dompdf)

### Penggajian dari Rekap Absensi
- Slip gaji hitung otomatis dari rekap absensi bulanan:
  - Hari hadir → gaji pokok proporsional
  - Total jam lembur → uang lembur (× tarif per jam dari `pengaturan_gajis`)
  - Hari alpha → potongan (× tarif per hari)
  - Menit telat → potongan (× tarif per menit)
- Override manual: field `uang_lembur_manual` & `potongan_alpa_manual` di `penggajians`

### Akses & Role untuk Menu Absensi
- **Karyawan biasa:** hanya lihat absensi sendiri (`/absensi-saya`) — read-only
- **Manajer Cabang:** kelola absensi semua karyawan di cabangnya, edit manual, lihat laporan
- **Owner:** akses semua cabang, pengaturan tarif global, setting GPS cabang
- Sub-menu absensi di sidebar menggunakan collapse/accordion (`collapsible`)

## Key Business Rules
1. **Multi-Cabang + Gudang Pusat:** Semua data transaksi harus tercatat dengan `cabang_id`/`lokasi_id`
2. **Alur Stok Normal:** Vendor → Gudang Pusat → Cabang (melalui stock request & transfer)
3. **Alur Stok Mendesak:** Vendor → Cabang langsung (harus ada approval, ditandai `pembelian_langsung`)
4. **Transfer Stok:** Stok asal berkurang saat "dikirim", stok tujuan bertambah saat "diterima"
5. **Jasa Giling:** Harga dihitung per kg, berbeda tiap jenis olahan, tercatat per cabang
6. **Produksi:** Harus kurangi stok bahan baku otomatis saat produksi (stok per cabang)
7. **Stok:** Tidak boleh minus, tampilkan warning saat mendekati minimum, stok terpisah per lokasi
8. **Pembayaran:** Support tunai dan non-tunai (transfer)
9. **Akses Lokasi:** User hanya bisa akses data lokasi (cabang/gudang) yang di-assign ke mereka
10. **Owner/Admin Pusat:** Bisa melihat data semua lokasi + mode konsolidasi/keseluruhan
11. **Dashboard:** Harus bisa toggle antara tampilan per cabang, gudang pusat, dan keseluruhan
12. **Laporan:** Semua laporan harus support filter per lokasi dan gabungan semua lokasi
13. **Aset:** Setiap aset harus tercatat lokasi-nya, penyusutan dihitung otomatis per bulan
14. **Depresiasi:** Beban penyusutan otomatis masuk ke laporan keuangan & komponen biaya tetap BEP
15. **BEP:** Dihitung per produk/jasa, per cabang, dan keseluruhan — data ditarik otomatis dari modul terkait
16. **Pembelian Aset:** Harus ada approval, tercatat di keuangan dan sebagai aset baru
17. **Penilaian 360°:** Dilakukan triwulanan, skor dihitung otomatis, rekan kerja anonim
18. **Responsive:** Seluruh tampilan WAJIB responsive — berfungsi baik di mobile, tablet, dan desktop
19. **Order Tambahan (Antrian):** Checkbox "Tampilkan di Antrian Produksi" di POS (default ON) — kalau di-uncheck, order tetap tersimpan normal tapi TIDAK dapat nomor antrian & TIDAK muncul di TV Antrian/Mode Produksi (dipakai untuk order tambahan yang dikerjakan bareng order utama)
20. **Pembatalan Order — Rule Hari Sama:** Non-Owner hanya boleh membatalkan order yang dibuat di HARI YANG SAMA (`created_at` hari ini); order dari hari sebelumnya hanya bisa dibatalkan Owner. Alasan pembatalan (kategori + detail opsional) WAJIB diisi setiap pembatalan, dicatat ke activity log
21. **Order Pengganti:** Order baru bisa ditautkan ke order lama yang dibatalkan via `parent_order_id` — otomatis terdeteksi & ditawarkan saat submit POS (pelanggan sama, hari sama, order lama belum ada penggantinya), atau manual lewat tombol "Tandai sebagai Pengganti" di halaman detail order
22. **Cash Drawer (Buka Laci):** Laci otomatis terbuka saat pembayaran Tunai (permission `kas.buka_laci`), tidak untuk QRIS/Transfer; tersedia juga tombol manual dengan log audit
23. **Bluetooth Printer:** Silent reconnect via `getDevices()` untuk skip popup pilih printer di transaksi ke-2 dst, auto-fallback ke `requestDevice()` kalau silent gagal — device Android tablet perlu Chrome flag `web-bluetooth-new-permissions-backend` enabled
24. **Format Satuan Berat:** Input POS bisa kg/ons/gram (dikonversi ke kg sebelum simpan), tampilan ringkasan POS auto-convert (≥1 kg = kg, ≥0.1 kg = ons, <0.1 kg = gram), struk kertas & database SELALU dalam kg (`order_items.berat_daging` `DECIMAL(10,3)`) supaya tetap auditable (kg × harga_per_kg = subtotal)
25. **Kategori Pengeluaran (`kategori_pengeluaran`):** Kolom baru di `transaksi_keuangans` (nullable, terpisah dari `kategori`/`kategori_id` yang sudah ada) — WAJIB diisi di form Tambah/Edit Transaksi Keuangan kalau `tipe = pengeluaran` (default pilihan: `lain_lain`). Data lama (`NULL`, sebelum kolom ini ada) ditampilkan sebagai "Lain-lain (belum dikategorikan)" — beda label dari `lain_lain` yang dipilih eksplisit oleh user
26. **Laporan Setoran Harian:** Konsolidasi pemasukan/pengeluaran/net 1 halaman (`/laporan/setoran-harian`) menggantikan cek manual 3 laporan terpisah. Filter tanggal pakai kolom akuntansi (`tanggal_order`/`tanggal_transaksi`), BUKAN `created_at`. Default periode: HARI INI (beda dari kebanyakan laporan lain yang default bulan ini). Permission terpisah (`laporan.setoran_harian.view/print/export`) — sengaja TIDAK di-assign ke role manapun secara default, harus dicentang manual di UI Role & Hak Akses per role (Owner tetap bypass semua)
27. **Threshold Stok Minimum Per Lokasi:** `stocks.qty_minimum` (per item+cabang) adalah threshold OPERASIONAL yang dipakai alert/notifikasi/dashboard — beda dari `items.qty_minimum` (global, di master data). Stok baru otomatis inherit dari `items.qty_minimum` saat pertama kali dibuat di suatu lokasi; bisa di-override manual per lokasi lewat tombol "Set Minimum" di menu Stok (permission `stok.minimum.set`)
28. **Ranking Kasir:** Section di Laporan Penjualan (permission `laporan.ranking_kasir.view`, tidak default ke role manapun) — join `orders.kasir_id → users.id` (kasir adalah akun `users`, bukan tabel `karyawans` terpisah)
29. **Master Resep Bumbu Standar:** Helper auto-isi form POS (bukan pengganti logic) — kasir pilih resep + berat gilingan di POS → sistem auto-tambah baris "Jasa Giling" persis seperti input manual (item, takaran, harga sesuai `mode_harga` gratis/pakai_master). Endpoint AJAX-nya (`GET /pos/resep-bumbu/{id}`) **tidak** digated permission `master.resep_bumbu.*` (itu khusus CRUD master data) — kasir tetap bisa pakai resep tanpa akses kelola resep
30. **Laporan Konsumsi Bahan Baku:** Agregasi qty + HPP dari `order_items.hpp` (FIFO cost) per periode (`/laporan/konsumsi-bahan`), murni READ — tidak mengubah `order_items`/laporan lain manapun. Default periode: BULAN INI. `item_id` NULL di `order_items` (item manual/terhapus) di-exclude dari breakdown via INNER JOIN. Permission terpisah (`laporan.konsumsi_bahan.view/print/export`) — sengaja TIDAK di-assign ke role manapun secara default. Trend 6 bulan WAJIB hitung `subMonths()` dari `startOfMonth()` (bukan dari `endOfMonth()` yang bisa tanggal 31) — subtraksi bulan dari tanggal 31 overflow kalau bulan tujuan lebih pendek (bug nyata yang ketemu & diperbaiki saat development: 31 Jul −5 bulan jadi awal Mar bukan akhir Feb)
31. **Scope Omzet — Ringkasan (LUAS) vs Breakdown per Item (SEMPIT):** Di Laporan Konsumsi Bahan Baku & Laporan Laba Rugi, kartu ringkasan (Total Omzet/Untung/Margin) SENGAJA pakai query LEBIH LUAS (`LEFT JOIN items`, termasuk `order_items.item_id` NULL) daripada tabel breakdown per item (`INNER JOIN items`, exclude `item_id` NULL) — alasan: baris "Jasa Giling" murni (biaya jasa itu sendiri, kasir ketik nama bebas tanpa pilih dari master Item) selalu `item_id NULL`, tabel per-item TIDAK BISA menampilkan baris tanpa item, tapi omzetnya TETAP harus terhitung di ringkasan supaya "Total Untung" representasikan profit riil (termasuk margin jasa giling, salah satu dari 2 lini bisnis inti). Kalau `SUM(breakdown per item)` < `Total Omzet` ringkasan, itu NORMAL — selisihnya = omzet dari baris jasa giling tanpa item, ada disclaimer info di UI (pola sama seperti Setoran Harian). **Laporan Laba Rugi** (`/laporan/laba-rugi`, permission `laporan.laba_rugi.view/print/export`) breakdown-nya LEBIH LENGKAP: Per Item / Per Kategori (`bahan_baku`/`kemasan`/`produk_jadi`/**`jasa`** — kategori `jasa` = `item_id` NULL ATAU `items.tipe='lainnya'`) / Per Jenis Olahan (`order_items.jenis_olahan`) / Per Order — Omzet SELALU dari `SUM(order_items.total_harga)` di semua level (bukan `orders.total_bayar`) supaya konsisten, efek sampingnya diskon order tidak ter-refleksi di angka per-baris (didisclose di panduan). **Catatan teknis:** GROUP BY ekspresi CASE (`CASE WHEN items.tipe IS NULL...`) DITOLAK MySQL `ONLY_FULL_GROUP_BY` strict mode walau identik dgn SELECT — solusinya GROUP BY kolom asli (`items.tipe`) lalu remap+merge kategori `jasa` di PHP (Collection), bukan di SQL. Kartu ringkasan Laba Rugi WAJIB match persis dengan kartu ringkasan Konsumsi Bahan Baku untuk filter periode+cabang yang sama (diverifikasi via test sintetis) — kalau beda, itu bug.
32. **PO Tools (Pilih PO + Widget + Dashboard PO):** Link PO↔Transaksi Keuangan REUSE `transaksi_keuangans.referensi_type='purchase_order'` + `referensi_id=po->id` (pola polymorphic yang sama dengan order, BUKAN kolom `po_id` baru — zero migration untuk linking-nya). PO "sudah dibayar" dideteksi via `whereNotExists` (transaksi dgn referensi_type+referensi_id itu). Cegah bayar dobel: `KeuanganController::store()` cek `TransaksiKeuangan::where('referensi_type','purchase_order')->where('referensi_id',$request->po_id)->exists()` sebelum simpan — kalau sudah ada, tolak dgn pesan error, tidak ubah logic simpan manual sama sekali. Kolom baru `purchase_orders.tanggal_kirim` (nullable, diisi `PurchaseOrderService::kirimSupplier()`) — SATU-SATUNYA sentuhan additive ke flow existing (approve/terima tidak disentuh), murni tambah timestamp tanpa ubah validasi/behavior. **5 bucket status PO** (`PoDashboardService::getRingkasanStatus()`): Menunggu Approval (draft, umur dari `created_at`), Perlu Dikirim (disetujui, umur dari `approved_at`), Dalam Perjalanan (dikirim_supplier, umur dari `tanggal_kirim`) — 3 bucket ini SPESIFIK per status; **Belum Diterima** = ROLLUP gabungan ketiganya (umur dari `created_at`, total waktu PO outstanding) — beda metrik dari "Dalam Perjalanan", bukan duplikat; Belum Dibayar (diterima tanpa transaksi referensi, umur dari `tanggal_terima`). Badge umur (biru 0-3 hari, kuning 4-7, merah >7) VISUAL ONLY — tidak ada notifikasi/alert (di luar scope sesi ini). Kolom qty TIDAK di-sum untuk breakdown Per Kategori/Per Order (mixed satuan kg vs pcs jadi angka tanpa arti) — sama prinsip dengan Laporan Laba Rugi. Widget dashboard cuma ditambah ke `dashboard/cabang.blade.php` + `pusat.blade.php` (bukan `gudang.blade.php` yang sudah punya `$poAktif` count sendiri, sengaja tidak disentuh). Permission (`kas.pilih_po.view` group keuangan, `po_dashboard.view`/`po_dashboard.action` group pembelian) sengaja TIDAK di-assign default ke role manapun. Sidebar "Pembelian" wrapper diubah dari `@can('pembelian.view')` ke `@canany(['pembelian.view','po_dashboard.view'])` supaya section tetap muncul kalau user cuma punya salah satu permission (pola sama seperti section Keuangan) — link "Purchase Order" & "Dashboard PO" tetap masing-masing di-gate `@can` sendiri di dalamnya.
33. **Adjustment Stok — Alasan Menentukan Dampak Keuangan:** `StokService::adjustment()` HANYA catat `transaksi_keuangan` untuk alasan `susut`/`rusak`/`hilang` (kejadian ekonomi riil). Alasan `salah_hitung`/`audit` (murni koreksi data, stok fisik tidak pernah berubah) **TIDAK** pernah bikin transaksi keuangan — stock_movement & stock_batch tetap dicatat normal. Alasan `lainnya` default TIDAK masuk keuangan kecuali user eksplisit centang checkbox `catat_sebagai_biaya` (opsi tambahan di `$options` array, bukan parameter baru — backward compatible, default `false` kalau tidak dikirim). Variabel penentu (`$catatKeKeuangan`) dihitung SEKALI di awal closure transaction, dipakai untuk gate KEDUA titik `TransaksiKeuangan::create()` (arah turun DAN naik) — bukan cuma salah satu arah. **Tombol Hapus Stok** (`StokService::resetStok()`, permission `stok.hapus.reset`, default tidak ke role manapun) adalah fitur TERPISAH dari Adjustment untuk reset qty ke 0 total (cleanup pre-go-live) — bukan sekadar Adjustment dengan alasan tertentu. **Temuan kritis saat audit:** `stock_batches`/`stock_movements` TIDAK punya kolom `stock_id` (relasi via `item_id`+`lokasi_id`, bukan FK langsung ke `stocks`), dan `stock_movements` **tidak punya `deleted_at`** — hard DELETE di situ permanen & tidak reversibel (beda dari `stock_batches` yang punya SoftDeletes). Karena itu `resetStok()` SENGAJA: (1) soft-delete `stock_batches` (reversibel via menu Data Terhapus existing, tanpa `withoutGlobalScopes()` supaya `SoftDeletingScope` tetap aktif — bukan raw `DELETE`), (2) **TIDAK** menghapus `stock_movements` lama sama sekali, cuma menambah 1 movement baru bertipe `adjustment` dengan catatan `"RESET STOK oleh Owner: {qty} → 0"` sebagai jejak, (3) set `qty=0` di `stocks`, (4) `activity('Stock')->log(...)` eksplisit di atas auto-log `HasAuditLog` bawaan model `Stock`/`StockBatch`. Konfirmasi UI wajib ketik ulang nama item persis (case-sensitive, exact match) sebelum tombol submit aktif — validasi ulang juga di server (`StokController::reset()`), tidak cuma di JS.
34. **`withoutGlobalScopes()` (tanpa argumen) BERBAHAYA di model ber-SoftDeletes:** Method ini menghapus SEMUA global scope, bukan cuma yang dimaksud — kalau model pakai trait `SoftDeletes`, `SoftDeletingScope` juga ikut mati, sehingga row yang sudah di-soft-delete (mis. transaksi dari order yang dibatalkan) ikut ke-SUM di laporan. **`CabangScope`** (`app/Models/Scopes/CabangScope.php`) sendiri **tidak pernah didaftarkan sebagai global scope di model manapun** (`HasCabang::addCabangScope()` tidak pernah dipanggil) — jadi di codebase ini, `withoutGlobalScopes()` TIDAK PERNAH benar-benar dibutuhkan untuk bypass CabangScope; efek nyatanya SELALU cuma mematikan SoftDeletingScope. **Pola aman yang WAJIB dipakai:** `Model::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)` (bentuk tunggal, dengan argumen class) — sudah jadi konvensi di 40+ tempat (`KaryawanController`, `PenggajianController`, `EvaluationController`, `CutiController`, `LaporanAbsensiController`, dll), JANGAN pakai bentuk jamak tanpa argumen untuk query baca/SUM. **Pengecualian yang SENGAJA tetap pakai bentuk jamak** (jangan disamakan): `PenjualanService::kembalikan()` (butuh cari row yang soft-deleted untuk di-`restore()`) dan `LaporanSetoranController` (sengaja tampilkan setoran ditolak/dibatalkan yang soft-deleted, ada di komentar eksplisit). Masih ada 40+ titik lain di codebase (Order/Karyawan/Absensi/PurchaseOrder di `DashboardController`, `LaporanPenjualanController`, `LaporanHRController`, dll) berpotensi bug sama — belum diaudit satu-satu, flagged untuk sesi audit lanjutan.
35. **`kas_id` WAJIB diisi di setiap `TransaksiKeuangan`** (kecuali transaksi non-cash yang SENGAJA `kas_id=null`, yaitu beban Adjustment Stok susut/rusak/hilang via `StokService::adjustment()`, `referensi_type='stock_movement'`) — `kas_id` nullable dulu menyebabkan transaksi manual (`KeuanganController::store()`) atau order (`PenjualanService::buatOrder()`) bisa tersimpan tanpa pernah mengurangi/menambah `kas.saldo_sekarang` manapun, padahal tetap ke-SUM penuh di Laporan Keuangan → Menu Kas jadi tampak lebih tinggi dari yang seharusnya. `PenjualanService::buatOrder()` SEKARANG throw exception kalau cabang tidak punya Kas aktif dengan `default_untuk` yang cocok untuk tipe pembayaran order (bukan diam-diam simpan `kas_id=NULL`) — **setiap cabang WAJIB punya kas aktif untuk SETIAP tipe pembayaran (tunai/transfer/qris) yang benar-benar dipakai kasirnya**, kalau tidak checkout akan gagal dengan pesan jelas.
36. **`saldo_sekarang` Kas SUDAH di-init dari `saldo_awal` sejak kas dibuat** (`KeuanganController::storeKas()`) — bukan mulai dari 0. Saat kas baru dibuat dengan `saldo_awal > 0`, sistem OTOMATIS bikin 1 `TransaksiKeuangan` pendamping (`kategori=SaldoAwal`, `referensi_type='kas'`, `referensi_id=kas->id`) supaya saldo awal itu juga ke-track di riwayat & laporan. **Penting untuk rekonsiliasi/tooling:** kalau mau hitung ulang "seharusnya berapa saldo kas dari riwayat transaksi", WAJIB cek dulu apakah kas itu punya transaksi `referensi_type='kas'` ini — kalau ADA, rumusnya `net(SEMUA transaksi kas ini)` (saldo_awal sudah ikut di situ, JANGAN ditambah lagi/double-count); kalau TIDAK ADA (kas legacy yang dibuat sebelum logic ini ada, atau di-seed manual), rumusnya `saldo_awal + net(transaksi)`. Lihat `KeuanganController::hitungExpectedSaldoKas()`.
37. **Sembunyi Harga per Item di Struk — Setting Per Cabang, Preferensi Cetak (Bukan Data):** Kolom baru `cabangs.izinkan_sembunyi_harga_struk` (boolean, default `false`) dikontrol Owner via Edit Cabang → section "Struk POS" (sudah ada dari fitur `footer_struk`). Kalau `true`, muncul checkbox opsional di POS ("Tampilkan harga per item di struk", default **UNCHECKED**) dekat tombol Proses — kasir bisa toggle per transaksi. **Value ini TIDAK PERNAH disimpan ke tabel `orders`/`order_items`** — murni preferensi cetak yang mengalir lewat: checkbox POS → ikut ter-submit di `FormData` checkout → di-echo balik di response JSON `PenjualanController::store()` (pola sama seperti `copies`/`print_copies`) → diteruskan sebagai query param ke `openStrukModal(orderId, copies, tampilHargaStruk)` (fungsi global `layouts/app.blade.php`) → `struk-modal?tampil_harga_struk=X` → `PenjualanController::shouldShowItemPrice()` hitung `$showItemPrice` dari `!$cabang->izinkan_sembunyi_harga_struk || request()->boolean('tampil_harga_struk')`. **Konsekuensi by-design (disetujui Owner):** karena tidak persist, "cetak ulang" struk dari Riwayat Penjualan / Detail Order (yang juga panggil `openStrukModal()` tapi cuma 2 argumen) otomatis default **sembunyi harga** (kalau cabang mengizinkan) — kasir perlu centang ulang manual kalau mau tampil harga lagi. **Halaman Detail Order (`penjualan/show.blade.php`) sama sekali tidak disentuh** — selalu tampil harga penuh untuk audit, terlepas dari setting ini. **Struk dirender di 3 tempat berbeda, ketiganya WAJIB konsisten** (temuan audit penting — brief awal cuma sebut 1 tempat): (1) `struk.blade.php` HTML preview (halaman print browser, dipakai dari Antrian Cek & Operator Produksi), (2) `_struk_modal_content.blade.php` HTML preview (modal in-place POS, jalur utama kasir sehari-hari), (3) **JS builder ESC/POS mentah** di dalam kedua file itu (`buildOneCopy()`/fungsi sejenis, generate command Bluetooth thermal printer) — item HTML disembunyikan tidak otomatis menyembunyikan versi ESC/POS-nya karena keduanya baca sumber data terpisah (`$item->harga_satuan` di Blade vs `STRUK.items[i].harga` di objek JS `@json($struData)`). Baris TOTAL/Subtotal (level order, bukan per-item) **selalu tercetak** di kedua mode, tidak pernah disembunyikan.
38. **Cleanup Tool (Link PO / Assign Kas / Sinkron Saldo) — Owner-only, verifikasi manual, BUKAN auto-fix massal:** 3 permission baru (`transaksi.link_po.action`, `transaksi.assign_kas.action`, `kas.sinkron_saldo.action`, group `keuangan`) sengaja TIDAK di-assign default ke role manapun. **Link PO** (Detail PO → "Cari Transaksi Existing"): cari kandidat transaksi Pengeluaran `referensi_type IS NULL` di cabang yang sama, ± 3 hari dari `tanggal_terima` PO, Owner klik "Link" satu-satu setelah verifikasi manual nominal/tanggal — TIDAK mengubah saldo kas (transaksi itu sudah pernah potong saldo saat pertama dibuat, Link cuma isi metadata referensi). **Assign Kas** (Daftar Transaksi → card "Transaksi Tanpa Kas Sumber"): filter `kas_id IS NULL AND referensi_type IS NULL` — sengaja exclude SEMUA transaksi yang punya `referensi_type` (termasuk `stock_movement` dari Adjustment Stok, yang MEMANG didesain tanpa kas fisik) supaya tidak salah tempel kas ke entry non-cash. **Sinkron Saldo** (Kelola Kas → tombol per kartu): preview dulu (saldo sekarang vs hasil hitung vs selisih) sebelum Owner konfirmasi apply — kalau selisih < Rp1, tombol "Sinkronkan" disembunyikan (tidak ada yang perlu disinkron). Semua 3 aksi tercatat ke `activity()` log terpisah dari auto-log `HasAuditLog` model.
39. **Setoran (transfer kas antar cabang) — pasangan OUT/IN via `setoran_pair_id`, RAWAN orphan kalau di-hapus lewat luar aplikasi:** `SetoranController` model-kan setoran sebagai 2 baris `transaksi_keuangans` (SETOR-OUT di kas asal, SETOR-IN di kas tujuan) yang saling terhubung lewat kolom `setoran_pair_id` — TIDAK ada tabel `setoran` terpisah. `SetoranController::index()` WAJIB pakai `withoutGlobalScope(CabangScope::class)` (bukan `withoutGlobalScopes()` tanpa argumen — sama kelas bug dengan #34) supaya `SoftDeletingScope` tetap aktif; kalau tidak, setoran yang sudah dihapus permanen tetap muncul di list. `SetoranController::destroy()` mencari pasangan HANYA lewat `setoran_pair_id` (`resolveSetoranOut()`, tidak ada fallback cocokkan by tanggal+nominal+cabang) — kalau `status_setoran='diterima'` (kedua kas sudah kepengaruh) tapi pasangan tidak ketemu (`setoran_pair_id` NULL/rusak, atau row pasangan hilang), `destroy()` WAJIB tolak dengan pesan error jelas ("Pasangan setoran tidak ditemukan...") SEBELUM `DB::transaction()` dimulai — JANGAN diam-diam proses cuma satu sisi (bug nyata yang ditemukan di produksi 2026-07-27: kas asal ke-kredit tapi kas tujuan tidak ikut dikoreksi, karena pasangannya lebih dulu jadi orphan lewat edit database langsung di luar aplikasi). Guard ini SENGAJA spesifik ke status `diterima` saja — status `menunggu_diterima`/`ditolak`/`dibatalkan` tidak butuh `$trxIn` sama sekali untuk membalik saldo dengan benar, jadi tidak digate. **Root cause historis (2026-07-26/27):** setoran Modal Awal (id 231/232, Rp198.329.749) dan setoran "kekurangan Pengeluaran" (id 245/246, Rp16.000) sama-sama jadi orphan setelah salah satu sisi di-restore langsung via database (bypass `TrashController`, tidak lewat activity log) tanpa merekonstruksi `setoran_pair_id` — pelajaran: restore data yang di-pair (setoran, order+pengganti, dll) WAJIB lewat UI resmi, tidak lewat query manual, supaya relasi ikut konsisten.

40. **Permission WAJIB untuk Setiap Menu/Fitur Baru (zero-tolerance):** Audit produksi 2026-07-27 menemukan banyak controller (Keuangan inti, Aset, BEP, Stock Request/Transfer, Cuti, dll) yang permission-nya SUDAH didefinisikan di `PermissionSeeder` dan SUDAH di-assign ke role di `RolePermissionSeeder`, tapi TIDAK PERNAH benar-benar di-cek di controller — permission "ada di kertas" tapi nol enforcement, jadi siapapun yang login (role apapun) bisa akses. Supaya tidak terulang, setiap kali tambah menu/fitur baru WAJIB:
    1. Definisikan permission granular di `PermissionSeeder` — minimal `{modul}.view`; tambah `.create`/`.edit`/`.delete`/`.action` kalau memang ada aksi terpisah (jangan bikin granular berlebihan untuk fitur simpel — 1 permission `.manage` cukup untuk modul kecil, lihat pola `aset.view`/`aset.manage`, `bep.view`/`bep.manage`)
    2. **WAJIB langsung dipakai** di controller: `abort_unless(auth()->user()->can('{modul}.xxx'), 403);` di SETIAP method publik (bukan cuma didefinisikan lalu dilupakan) — ini pola dominan di codebase ini (bukan route middleware `can:`, walau itu juga valid & dipakai di beberapa modul seperti Pembelian/Laporan). Kedua pola boleh dipakai bersamaan (defense-in-depth) tapi MINIMAL SALAH SATU harus ada.
    3. Update `RolePermissionSeeder` — assign ke role yang seharusnya dapat (default hemat; kalau ragu peran mana yang pantas, kasih ke `admin_pusat`/`manajer_cabang` saja dulu, JANGAN broadcast ke semua role). Owner selalu bypass semua via `Gate::before` di `AppServiceProvider` — tidak perlu di-assign eksplisit.
    4. Sidebar (`layouts/app.blade.php`) WAJIB `@can('{modul}.xxx')` untuk link menu baru, dan tombol aksi destruktif/sensitif di Blade WAJIB `@can` juga — jangan cuma andalkan proteksi backend, tombol yang tetap tampil ke role tanpa akses itu UX yang membingungkan (klik → 403).
    5. UI Role & Hak Akses (`role/index.blade.php`) sudah **generic/dynamic** (loop dari DB by `group`) — permission baru OTOMATIS muncul di situ asal `group` di-isi konsisten. Kalau mau ikon custom, tambah 1 baris di `match($group)` — kalau tidak, fallback ke ikon gear generik (kosmetik, bukan blocker).
    6. Panduan Cara Pakai (kalau modul itu punya panduan) sebut siapa yang boleh akses & cara grant permission via UI Role.
    Referensi controller yang SUDAH benar sebagai contoh: `PurchaseOrderController`, `SetoranController`, `KaryawanController`, `PelangganController`, `ItemController`, `KategoriTransaksiController`, `MasterResepBumbuController`.
41. **Data Terhapus + Activity Log WAJIB untuk Aksi Destructive (zero-tolerance):** Audit yang sama menemukan `Cuti` di-hard-delete tanpa `SoftDeletes`/`HasAuditLog` sama sekali (hapus pengajuan cuti = hilang permanen, nol jejak), dan 2 model (`OrderItem`, `FaceAttendance`) terdaftar di `TrashController::$models` padahal TIDAK pakai trait `SoftDeletes` (kolom `deleted_at` di tabelnya cuma keisi lewat cascade raw-query dari parent, bukan lewat model) — akibatnya tab-nya di UI Data Terhapus selalu kosong (`onlyTrashed()` throw exception yang ke-catch diam-diam) dan restore individual akan 500 kalau dipaksa. Aturan wajib ke depan untuk setiap model baru yang punya fungsi hapus atau edit critical (mengubah nominal uang/stok/data HR sensitif):
    1. Model WAJIB `use SoftDeletes;` (bukan hard delete) — KECUALI tabel anak yang memang didesain cuma reversibel lewat cascade parent-nya (dokumentasikan eksplisit kalau begitu, jangan didaftarkan sendiri di `TrashController` kalau modelnya tidak pakai trait ini)
    2. Migration WAJIB `$table->softDeletes();`
    3. Model WAJIB `use HasAuditLog;` (spatie activitylog otomatis, `logOnlyDirty()`) supaya histori update/delete ter-capture tanpa kode tambahan
    4. Daftarkan di `TrashController::$models` (key = nama tabel snake_case, value = FQCN model) — HANYA kalau model itu benar pakai `SoftDeletes` (cek dulu sebelum daftar, jangan asumsi)
    5. `TrashController` sudah pakai permission granular (`lihat_data_terhapus`, `restore_data_terhapus`, `hapus_permanen_data`, group `keamanan`) yang generic untuk SEMUA model — tidak perlu bikin permission per-model baru (`trash.restore.{model}` dst BUKAN pola yang dipakai di codebase ini, cukup 1 permission generic yang sudah ada)
    6. Edit critical (mis. ubah jumlah transaksi keuangan, saldo kas) HARUS lewat form terkontrol (bukan raw query `DB::table()->update()`), supaya `HasAuditLog` bisa capture before/after value-nya secara otomatis
    Referensi model yang SUDAH benar: `TransaksiKeuangan`, `Kas`, `Order`, `Karyawan`, `Absensi`, `Penggajian`, `Asset`, `PurchaseOrder` — semua pakai `SoftDeletes` + `HasAuditLog` + terdaftar di `TrashController`.
42. **Data Terhapus — `deleted_by` Auto-fill via Trait, Bukan Edit Service Manual:** Setiap model yang terdaftar di `TrashController::$models` (24 model per 2026-07-28) WAJIB `use FillsDeletedBy;` (`app/Traits/FillsDeletedBy.php`) di samping `SoftDeletes`/`HasAuditLog` — trait ini mendaftarkan listener `static::deleting()` yang otomatis isi kolom `deleted_by` (nullable, tanpa FK, konsisten dengan pola `changed_by` di tabel `_histories`) via raw `DB::table()->update()` (BUKAN `$model->save()`, karena `SoftDeletes::runSoftDelete()` sendiri juga raw-update kolom `deleted_at`/`updated_at` — dua raw update terpisah ke kolom berbeda, tidak saling konflik). **Kenapa trait, bukan edit satu-satu di tiap Service/Controller `destroy()`:** zero titik yang bisa kelewat, dan Service delete existing (StokService::resetStok, SetoranController::destroy, CascadeDeleteService::deleteXxxCascade, dll) otomatis ikut ter-cover tanpa disentuh sama sekali. Diabaikan otomatis saat `forceDelete()` (`isForceDeleting()` true → skip, baris akan hilang total). Data lama (soft-deleted SEBELUM kolom ini ada) tetap `NULL` — UI wajib tampilkan "Sistem tidak mencatat", bukan dianggap bug.
    - **Halaman Detail per model** (`/trash/{model}/{id}/detail`, `TrashController::detail()`) — load record `onlyTrashed()` + relasi (`$detailRelations` map, HANYA untuk 10 model "kritis": Order, Kas, TransaksiKeuangan, PurchaseOrder, Stock, Item, Pelanggan, Karyawan, Asset, Cuti) + `Activity::where('subject_type', $modelClass)->where('subject_id', $id)` untuk activity log. Sisa ~14 model pakai fallback generik (tabel key-value seluruh atribut). **Perhatian kritis:** `TransaksiKeuangan.referensi_type` disimpan sebagai string logis (`'order'`/`'purchase_order'`/`'kas'`/`'stock_movement'`), BUKAN FQCN, dan **tidak ada morphMap terdaftar** — `$trx->referensi` (relasi `morphTo()` bawaan) akan ERROR kalau dipanggil langsung (Eloquent coba `new order()`, class tidak ada). WAJIB resolve manual di controller (`resolveReferensi()`) dengan `match()` + try/catch, JANGAN pernah akses `$record->referensi` di Blade.
    - **Modal Hapus Permanen 2 tahap** (`trash/_modal-hapus-permanen.blade.php`, partial dipakai bareng oleh `trash/index.blade.php` dan `trash/detail.blade.php`) — Tahap 1: preview + tombol "Lanjut" (murni ganti visible pane via JS, TIDAK submit). Tahap 2: input teks harus PERSIS `HAPUS PERMANEN` (case-sensitive) baru tombol submit aktif. Beda dari pola "Hapus Stok" existing (`stok/index.blade.php`) yang cuma 1 modal dengan input ketik ulang nama item — di sini sengaja 2 pane terpisah sesuai permintaan Owner, tapi prinsip "harus ketik teks pasti untuk konfirmasi aksi destruktif" tetap sama.
    - **Permission Data Terhapus TETAP Owner-only default** (`lihat_data_terhapus`/`restore_data_terhapus`/`hapus_permanen_data`, group `keamanan`) — TIDAK ada role lain yang dapat otomatis, konsisten dengan pola `master.resep_bumbu.*` dan `lihat_backup`/`buat_backup`/dll. Kalau perlu delegasi ke Admin Pusat, Owner centang manual per permission (bisa granular: kasih `lihat_data_terhapus` tanpa `restore_data_terhapus` untuk akses read-only/audit).
43. **Chart of Accounts (COA) — Fondasi Akuntansi SAK ETAP, Read-Only Terhadap Data Historis:** Tabel `chart_of_accounts` (59 kode akun, `id` auto-increment + `kode` unique varchar(6) format `X-XXXX`, `parent_kode` self-reference TANPA FK keras — konsisten prinsip hindari FK constraint di kolom yang bisa orphan) menyediakan struktur SAK ETAP standar (1-XXXX Aset s/d 8-XXXX Beban Lain) untuk kebutuhan Laporan Neraca/Laba Rugi formal di fase mendatang. **Kategori transaksi existing (`kategori_transaksis`) TIDAK diganti** — cuma ditambah 2 kolom nullable (`kode_akun_coa` varchar(6), `tipe_biaya` enum tetap/variabel) via `KategoriCoaBackfillSeeder` yang **idempotent by-design**: setiap update di-guard `whereNull('kode_akun_coa')` supaya reklas manual Owner via UI (Edit Kategori Transaksi → dropdown Kode Akun COA) tidak pernah tertimpa re-run seeder. Kategori `PNYS` (Penyusutan Aset) dan 17 kategori lain SUDAH ADA dari sesi sebelumnya — di-mapping ke kode akun yang sesuai, BUKAN dibuat ulang/diduplikasi. Kategori bertipe transfer internal (`SETOR-IN`/`SETOR-OUT`, saldo awal `SALDO`) SENGAJA `kode_akun_coa = NULL` — perpindahan kas internal bukan pendapatan/beban riil, tidak boleh masuk Laporan Laba Rugi. Permission `coa.view`/`coa.manage` (group `akuntansi`) Owner-only default, pola sama dengan `master.resep_bumbu.*`.
44. **Auto Depresiasi Aset — Insert TransaksiKeuangan Non-Cash, Idempotent per Aset+Periode:** `AssetDepreciationService::generateAsetTertentu(Asset $asset, string $periode)` adalah SATU-SATUNYA titik masuk untuk generate penyusutan — dipakai bareng oleh trigger manual per-aset existing (`AssetController::hitungPenyusutan()`, sudah ada sejak sebelum sesi ini), tombol bulk baru ("Generate Depresiasi Bulan Ini", permission `aset.depresiasi.auto`), dan scheduler (`aset:generate-depresiasi`, `routes/console.php` — **BUKAN `app/Console/Kernel.php`**, file itu tidak ada lagi di Laravel 12, project ini pakai konvensi `Schedule::command()` di `routes/console.php` sejak fitur recurring transaction/backup). Idempotency guard: return `null` tanpa efek apapun kalau `AssetDepreciation` untuk `asset_id`+`periode` itu sudah ada ATAU `nilai_buku <= nilai_residu` — aman dipanggil berkali-kali (manual+bulk+scheduler tumpang tindih di bulan yang sama) tanpa duplikat. Setiap generate berhasil otomatis insert 1 `TransaksiKeuangan` non-cash (`kas_id = NULL`, kategori `PNYS` yang di-reuse, kode akun `6-1104` Beban Depresiasi, `referensi_type = 'asset_depreciation'`) — pola identik dengan beban kerugian stok susut/rusak/hilang di `StokService::adjustment()`. Query loop bulk **SENGAJA TIDAK pakai `Asset::withoutGlobalScopes()`** (plural, tanpa argumen) — `CabangScope` tidak pernah terdaftar sebagai global scope di codebase ini (Rule #34), jadi query polos `Asset::where('status', StatusAset::Aktif)` sudah otomatis mencakup semua cabang tanpa risiko ikut mematikan `SoftDeletingScope` Asset.
45. **Rename "Setoran ke Pusat" → "Transfer / Perpindahan Dana" — URL Pindah, Nama Route Tetap:** URL fisik pindah dari `/setoran` ke `/transfer-dana`, TAPI seluruh nama route (`setoran.index`, `setoran.create`, `setoran.show`, dst) **SENGAJA TIDAK diubah** — Laravel route name tidak terikat ke path URL-nya, jadi seluruh `route('setoran.xxx')`/`redirect()->route('setoran.xxx')` di `SetoranController` dan `resources/views/setoran/*.blade.php` tetap jalan tanpa satu baris pun diedit. **Temuan teknis penting saat apply:** mendaftarkan 2 route dengan method+URI IDENTIK tapi nama berbeda (percobaan awal bikin alias `transfer-dana.*` di URI yang sama) TIDAK bisa coexist — `Illuminate\Routing\RouteCollection` meng-key route by `[method][URI]`, jadi route kedua **menimpa route pertama sepenuhnya** (nama pertama hilang total dari route lookup, `route('nama-pertama')` throw `RouteNotFoundException`) — bukan sekadar "route kedua yang menang saat dipanggil", registrasinya sendiri yang hilang. Solusi: HANYA SATU nama per URI (`setoran.*`, karena itu yang dipakai kode existing). Backward compat bookmark URL lama: `Route::redirect('/setoran', '/transfer-dana')` + `Route::get('/setoran/{any}', ...)->where('any','.*')` untuk sub-path. Kategori `SETOR-IN`/`SETOR-OUT` di-rename nama tampilan ("Perpindahan Dana Masuk"/"Keluar") — `kode` tidak berubah, `TransaksiKeuangan.status_setoran` enum tidak disentuh sama sekali.
46. **Laporan Neraca + Laba Rugi Formal (FASE 2 Akuntansi) — Murni READ, Resolusi COA Berlapis:** `NeracaService::hitungNeraca(Carbon $tanggal, ?int $cabangId)` dan `LabaRugiFormalService::hitungLabaRugi(Carbon $mulai, Carbon $akhir, ?int $cabangId)` murni baca data existing (Kas, StockBatch FIFO, Asset, PurchaseOrder, ChartOfAccount dari Fase 1) — nol tulis, nol perubahan ke `PenjualanService`/`StokService`/`KeuanganController` input form. **Resolusi kode akun COA berlapis 3 tingkat** di `LabaRugiFormalService::hitungSaldoPerKodeAkun()`: (1) `kategori_id` terisi & `kode_akun_coa`-nya ADA → pakai apa adanya; (2) `kategori_id` terisi tapi `kode_akun_coa` sengaja NULL (transfer internal SETOR-IN/OUT/SALDO) → **di-exclude total dari laporan**, TIDAK di-fallback ke enum (kalau di-fallback, transfer internal salah kereklas jadi Beban Lain-lain via `kodeAkunCoaFromEnum('lainnya')` — bug nyata yang ditemukan & diperbaiki saat development sesi ini); (3) `kategori_id` NULL sama sekali (transaksi hasil order/POS — `PenjualanService::buatOrder()` yang tidak boleh disentuh cuma mengisi kolom enum lama `kategori`, tidak pernah `kategori_id`) → fallback ke `KategoriTransaksi::kodeAkunCoaFromEnum()`. Kategori dual-purpose (`tipe='keduanya'`, mis. "Lainnya"/"Marketing") yang kebetulan transaksinya `tipe_transaksi='pemasukan'` di-override ke akun `4-1199` (Pendapatan Usaha Lainnya) alih-alih ikut `kode_akun_coa` bawaan kategori itu yang bias ke sisi beban. **Bug Eloquent yang ditemukan & jadi pelajaran umum:** alias kolom di `selectRaw()` yang KEBETULAN sama persis dengan nama kolom asli bermodel-cast (`transaksi_keuangans.tipe as tipe`) tetap kena aturan `casts()` model (`tipe` → enum `TipeTransaksiKeuangan`) walau nilainya cuma agregat SQL biasa — perbandingan string (`=== 'pemasukan'`) jadi selalu false TANPA ERROR APAPUN sampai dipaksa cast ke string di tempat lain. **Aturan wajib ke depan: JANGAN PERNAH alias kolom `selectRaw()`/raw query dengan nama yang sama persis dengan kolom asli yang punya `casts()` di model itu** — selalu pakai alias berbeda (mis. `tipe_transaksi`, `kategori_enum`) untuk menghindari cast tak sengaja. `NeracaService::hitungModal()` treat "Laba Ditahan" sebagai laba kumulatif SEMUA transaksi sejak `2000-01-01` s/d tanggal cutoff (bukan 1 periode) — representasi akuntansi yang benar untuk retained earnings. **`balance_check` WAJAR bernilai false untuk data historis pra-COA** (aset didata manual tanpa transaksi Kas pembelian yang match, atau modal awal masuk lewat jalur Transfer bukan kategori Modal) — `NeracaSetting.modal_owner` (singleton config, pola sama `PengaturanGaji::getSetting()`) sengaja dibuat sebagai figure "plug" yang bisa di-adjust manual Owner via modal edit di halaman Neraca (gate `role:owner`, bukan permission granular baru — konsisten pola Pengaturan Penggajian/Umum), BUKAN sistem memaksakan rekonsiliasi otomatis atas data yang memang tidak lengkap. Controller sengaja diberi nama FLAT `LaporanNeracaController`/`LaporanLabaRugiFormalController` (bukan subfolder `Controllers/Laporan/`) — mengikuti konvensi 14 controller Laporan* existing yang semuanya flat. PDF export pakai `barryvdh/laravel-dompdf` (baru diinstall sesi ini, pertama kali dipakai di codebase — fitur PDF sebelumnya semua "print via browser" bukan dompdf beneran) dengan CSS **table-based murni** (bukan flexbox/grid — dompdf tidak reliable untuk itu), nomor halaman via `$pdf->getDomPDF()->getCanvas()->page_text(...)` dipanggil dari controller SEBELUM `->download()` (bukan `<script type="text/php">` di blade, supaya tidak perlu enable `dompdf.enable_php` yang berisiko keamanan).
47. **Fitur Analytics WAJIB Reuse Existing Service (FASE 3) — BEP Otomatis, Dashboard Analytics, Buku Besar:** Prinsip ditegakkan lewat 2 mekanisme konkret, bukan sekadar himbauan. **(a) Resolver kode akun di-extract jadi method publik reusable:** `LabaRugiFormalService::resolveKodeAkun($kategoriId, $kodeAkunCoaKategori, $kategoriTipe, $kategoriEnum, $tipeTransaksi)` — method PRIVATE `hitungSaldoPerKodeAkun()` dari Fase 2 di-refactor supaya logic resolusi 3-lapisnya (lihat Rule #46) dipanggil dari method public ini, BUKAN diduplikasi. `BukuBesarService` (baru) memanggil method yang SAMA persis untuk resolusi per-baris (bukan agregat) — kalau ada bug/perubahan resolusi di masa depan, cukup fix di 1 tempat, otomatis konsisten di Laba Rugi Formal DAN Buku Besar. Diverifikasi via regression test: hasil refactor (`hitungSaldoPerKodeAkun` yang sudah pakai `resolveKodeAkun()`) 100% identik dengan logic lama sebelum refactor pada data live yang sama. **(b) `DashboardAnalyticsService` (widget "Kesehatan Finansial") murni compose 3 service existing** (`NeracaService`, `LabaRugiFormalService`, `BepOtomatisService`) — nol kalkulasi akuntansi baru di file itu sendiri, cuma menyusun ulang output ketiganya. Diverifikasi: `rasio_lancar` = `NeracaService` punya Aset Lancar/Kewajiban Jk. Pendek, `pendapatan_bulan_ini`/`laba_bersih_bulan_ini` = panggil `LabaRugiFormalService::hitungLabaRugi()` apa adanya, trend 6 bulan = loop `LabaRugiFormalService` 6× (bukan query manual baru). **`BepOtomatisService` (baru, BUKAN reuse)** — ini SATU-SATUNYA kalkulasi baru di Fase 3, karena tidak ada service existing yang menghitung "BEP dari HPP FIFO jasa giling secara otomatis tanpa setup" — beda total dari `BepCalculationService` existing (`app/Services/BepCalculationService.php`, TIDAK disentuh sama sekali) yang butuh `BepSetting`/`BepProduct`/`BepFixedCostItem` diisi manual dulu (lewat form atau tombol "Isi Otomatis" yang cuma bantu isi form, bukan generate on-the-fly). **Sumber Biaya Tetap BEP Otomatis** = `kategori_transaksis.tipe_biaya='tetap'` (kolom dari Fase 1, isinya saat ini: GAJI/SEWA/PNYS/LISTRIK) — BUKAN hardcode daftar kategori seperti `BepController::autoFill()` existing. **Sumber Biaya Variabel/Volume** = `order_items.hpp`/`berat_daging` untuk order `tipe_order='jasa_giling'` — BUKAN dari kategori `tipe_biaya='variabel'` (itu transaksi pembelian/beban, bukan HPP barang yang benar-benar terjual, jadi tidak representatif untuk biaya variabel per unit produksi). **Buku Besar scope SENGAJA terbatas** ke akun tipe `pendapatan`/`hpp`/`beban_operasional`/`pendapatan_lain`/`beban_lain` — akun Aset/Kewajiban/Modal TIDAK didukung karena datanya dari tabel lain (Kas/StockBatch/Asset/PurchaseOrder), bukan `transaksi_keuangans` per baris, jadi tidak ada jalur "per transaksi" yang valid untuk ditampilkan sebagai buku besar. Debit/Kredit ditentukan dari `ChartOfAccount.tipe` (pendapatan/pendapatan_lain → kolom Kredit, hpp/beban_operasional/beban_lain → kolom Debit) — bukan re-derive dari `tipe_transaksi` per baris, karena override "keduanya"+pemasukan di `resolveKodeAkun()` sudah menjamin akun yang ter-resolve SELALU konsisten dengan sisi normalnya. **Saldo berjalan (running balance) Buku Besar SELALU mulai dari Rp0 di baris pertama periode** (bukan saldo kumulatif riil sejak akun itu ada) — keterbatasan yang SAMA seperti disclaimer Neraca, didisclose eksplisit di UI+panduan, bukan disembunyikan. Diverifikasi via cross-check: `BukuBesarService::getTransaksiPerAkun()` untuk akun manapun (baik sisi debit `6-1104` Beban Depresiasi maupun sisi kredit `4-1101` Pendapatan Jasa Giling termasuk baris hasil fallback order/POS) `saldo_akhir`-nya PERSIS sama dengan angka `jumlah` akun yang sama di breakdown `LabaRugiFormalService::hitungLabaRugi()` pada periode yang sama — 0 selisih.
48. **DomPDF Multi-Halaman — `page_text()` WAJIB Dipanggil SETELAH `render()` Eksplisit, Bukan Sebelumnya:** Bug nyata ditemukan saat membangun PDF 6-halaman pertama di codebase ini (Laporan Eksekutif) — pola `$pdf->getDomPDF()->getCanvas()->page_text(...)` yang dipanggil LANGSUNG setelah `Pdf::loadView()->setPaper()` (pola yang dipakai SEMUA PDF Fase 2/3 sebelumnya: Neraca, Laba Rugi Formal, BEP Otomatis, Buku Besar) cuma "kebetulan benar" untuk dokumen 1-halaman — dibuktikan lewat `get_page_count()` yang mengembalikan `1` SEBELUM `render()` dipanggil (nilai default/belum ter-layout), padahal jadi `6` SETELAH `render()` eksplisit dipanggil. Akibatnya kalau `page_text()` dipanggil sebelum render, `{PAGE_COUNT}` ke-bake jadi `1` dan overlay teksnya CUMA MUNCUL DI HALAMAN 1 (bukan di semua halaman) — tidak error, tidak exception, cuma diam-diam salah/hilang di halaman 2 dst. **Fix wajib:** panggil `$pdf->render()` (method public wrapper barryvdh, bukan `$pdf->getDomPDF()->render()`) SEBELUM `page_text()`, baru kemudian `$pdf->download()`/`->stream()` (yang akan skip re-render karena `$this->rendered` sudah `true`). **Dampak ke Fase 2/3:** ke-4 PDF existing (Neraca/Laba Rugi Formal/BEP Otomatis/Buku Besar) SECARA TEORI rawan bug yang sama kalau kontennya suatu saat overflow ke halaman ke-2 (mis. Buku Besar dengan sangat banyak transaksi, atau Laba Rugi Formal dengan banyak akun COA berisi) — belum di-patch di sesi ini (di luar scope Laporan Eksekutif), **flagged untuk sesi perbaikan terpisah** kalau Owner mau preventif memperbaikinya sebelum kejadian nyata di produksi.
49. **Laporan Eksekutif Keuangan (Sesi A) — Compose Murni 5 Service, Cross-Check Genuinely Independen vs Sanity-Check Integrasi:** `BusinessOverviewService`, `BebanBreakdownService`, `CrossCheckValidator` MURNI compose dari `NeracaService`/`LabaRugiFormalService`/`BepOtomatisService`/`BukuBesarService`/`AssetDepreciationService` (Fase 1-3, semua tetap tidak disentuh) + `DashboardAnalyticsService` (Fase 3, milik sendiri — diperluas dengan parameter opsional `?Carbon $asOf` supaya bisa dipanggil untuk periode historis, backward-compatible karena default `null`→`Carbon::now()` sama seperti sebelumnya, dipakai widget dashboard tanpa perubahan). Kalkulasi BARU yang genuinely ditambahkan (bukan duplikasi apapun): sisa umur ekonomis rata-rata aset (aritmatika tanggal), ringkasan transaksi/produksi (count/sum/avg polos dari Order/OrderItem), Arus Kas (query sama persis seperti `LaporanKeuanganController::arusKas()` existing yang tidak disentuh — SENGAJA tidak difilter `kas_id` di angka utama supaya tetap cross-check konsisten dengan menu itu, tapi ditambah breakdown `tunai_only` terpisah sebagai info tambahan karena angka utama TERMASUK entri non-tunai seperti penyusutan aset yang kas_id-nya NULL — lihat Rule #35/#44). **Cross-Check kejujuran soal independensi:** dari 4 pengecekan di `CrossCheckValidator`, HANYA "Total Beban" genuinely independen (2 jalur agregasi kode berbeda — 1 query SQL ter-agregasi di `LabaRugiFormalService` vs loop per-akun `BukuBesarService`, walau keduanya reuse `resolveKodeAkun()` yang sama untuk resolusi per-baris); 3 check lain (Laba Ditahan, BEP, Kas) memanggil service YANG SAMA dengan parameter yang (seharusnya) sama — nilainya sebagai "sanity check integrasi" (menangkap salah parameter/state), BUKAN verifikasi 2-algoritma-independen, didisclose eksplisit di UI+panduan supaya tidak menyesatkan. Toleransi cross-check `< Rp0,01` (BUKAN `< Rp1` seperti `NeracaService::balance_check`) karena semua perbandingan di atas seharusnya identik persis, bukan "cukup dekat". **Gap Breakdown Gaji per Karyawan didisclose eksplisit (bukan disembunyikan):** `Penggajian` (payroll per karyawan) TIDAK terhubung otomatis ke `transaksi_keuangans` kategori Gaji (bayar gaji = input manual Admin di Kas Keluar, terpisah dari proses hitung payroll) — `BebanBreakdownService::getBreakdownGajiPerKaryawan()` return `total_penggajian` DAN `total_transaksi_kas` terpisah + field `selisih` eksplisit, UI wajib tampilkan disclaimer "informasional" bukan mengklaim keduanya sama. Lihat Rule #48 untuk bug DomPDF multi-halaman yang ditemukan saat membangun fitur ini.
50. **Laporan Eksekutif Sesi B — Halaman 7-9, Simulator BEP, Patch Preventif JADI Patch Aktif:** `EvidenceBasedFindingsService`, `RangkumanFinalService`, `SimulasiBalikModalService` MURNI compose dari service Sesi A/Fase 1-3 (`BebanBreakdownService`, `PoDashboardService` existing, `NeracaService`, `BepOtomatisService`) — 3 query BARU genuinely ditulis (Produktivitas Transaksi, Kasir Performance — mirror pola `LaporanPenjualanController` tapi TIDAK inject controller sebagai service, Trend Kas 30 Hari) karena tidak ada service untuk itu, semuanya join/groupby/sum polos tanpa logic akuntansi. **Health Score 5 dimensi** (Profitabilitas/Likuiditas/Pencapaian BEP/Efisiensi Beban/Solvabilitas, masing-masing 0-100) adalah heuristik rule-based BARU (bukan reuse, karena genuinely belum ada scoring numerik sebelumnya) — didisclose eksplisit BUKAN standar audit baku. **Bug floating-point ditemukan & diperbaiki saat testing**: skenario "Volume BEP" (tepat di titik impas) sempat menghasilkan `waktu_balik_modal_bulan` astronomis (`1.06e+17`) karena `laba_bulanan` yang seharusnya `0` bisa jadi angka sangat kecil non-zero akibat floating-point arithmetic, lalu `modalAwal / labaMendekatiNol` meledak — fix: epsilon guard `Rp1.000` (nilai di bawah ini dianggap "impas", bukan untung/rugi) di `SimulasiBalikModalService::hitungSkenario()`. **Parameter skenario "Volume Sedang"/"Volume Optimis" (Simulasi Balik Modal & keputusan Owner post-audit):** karena histori produksi cuma ~1,5 minggu (bisnis baru, belum marketing aktif) dan tidak representatif untuk "kapasitas riil", KEDUANYA default ke 1x/2x BEP unit harian (proporsional terhadap BEP, bukan angka absolut arbitrary) dan BISA di-override via query param (`volume_sedang`, `volume_optimis`, `pangkas_beban_persen`) dari form — UI wajib disclaimer "target/asumsi Anda, bukan proyeksi dari data historis". **Skenario "Kombinasi Efisiensi" digeneralisasi** jadi "pangkas X% dari TOTAL Biaya Tetap" (bukan spesifik ke kategori Sewa seperti brief awal) karena kategori Sewa/Gaji/Listrik semuanya bisa bernilai Rp0 di suatu periode (cuma Penyusutan Aset yang selalu ada) — general lever tetap valid berapapun komposisi beban berjalan. **Interactive Simulator BEP** — kalkulasi BEP client-side JavaScript murni (aljabar dasar, formula sama seperti `BepOtomatisService`), nilai AWAL slider reuse `BepOtomatisService`/`NeracaService` (server-side, sekali saat load), tidak ada AJAX/service tambahan karena tidak menyentuh data apapun. **Patch preventif Rule #48 ke 4 PDF Fase 2/3 TERNYATA jadi patch AKTIF, bukan cuma preventif**: saat testing regresi, Buku Besar untuk akun `6-1104` (39 transaksi) TERBUKTI SUDAH genuinely 2 halaman di data real (bukan skenario hipotetis) — sebelum patch, kedua halaman salah menampilkan "Halaman 1 dari 1"/(footer PDF tidak muncul di halaman 2 sama sekali); setelah patch, benar "Halaman 1 dari 2"/"Halaman 2 dari 2". **Fix logo PDF** — root cause BUKAN di 4 PDF lama (PDF-only, tidak pernah dirender sebagai HTML browser, `public_path()` selalu benar di sana) — murni di `_page-header.blade.php` (Laporan Eksekutif Sesi A) yang dipakai GANDA untuk preview HTML browser DAN PDF: `public_path()` (path filesystem, `D:\...`) valid untuk DomPDF tapi tidak bisa dimuat browser sebagai `<img src>`; fix pakai `($isPdf ?? false) ? public_path(...) : asset(...)`, flag `$isPdf` sudah ada dari Sesi A.
51. **Workflow Sync Lokal Otomatis Setelah Push Commit (Claude Code Jalankan Sendiri, BUKAN File .bat):** Setiap kali Claude Code selesai push commit baru ke `main` (baik 1 commit maupun multi-commit dalam 1 sesi), Claude Code WAJIB langsung menjalankan SENDIRI (via bash/terminal tool, bukan minta Owner klik file apapun) urutan berikut secara berurutan: (1) `git pull`, (2) `composer install --optimize-autoloader` — kalau ada dependency baru terdeteksi, (3) **`php artisan migrate --force` — WAJIB DIJALANKAN SETIAP KALI, TANPA SYARAT, bukan "kalau ada migration baru saja"** (lihat insiden di bawah — jangan andalkan penilaian sendiri soal "apakah ada migration baru", karena bisa salah/lupa; `migrate --force` aman & idempotent dipanggil berkali-kali, kalau memang tidak ada migration pending cuma print "Nothing to migrate" dan langsung lanjut, jadi TIDAK ADA alasan untuk skip step ini), (4) `php artisan db:seed --class=PermissionSeeder --force`, (5) `php artisan db:seed --class=RolePermissionSeeder --force`, (6) `php artisan db:seed --class=PanduanKontenSeeder --force`, (7) `php artisan config:clear`, (8) `php artisan route:clear`, (9) `php artisan view:clear`, (10) `php artisan cache:clear`, (11) `php artisan optimize:clear`. Setelah semua step sukses, laporkan ke Owner: *"Sudah sync ke lokal otomatis. Siap test di http://127.0.0.1:8000"*. **Kalau ada step yang gagal** (migration error, composer conflict, dll) — laporkan error-nya jelas ke Owner, JANGAN lanjut ke step berikutnya secara diam-diam (berhenti di step yang gagal, tunggu arahan). Zero-tolerance: tidak boleh minta Owner ketik command manual satu-satu setelah push commit, kecuali ada kondisi khusus yang TIDAK bisa dihandle otomatis (mis. perlu upload file besar seperti `vendor.zip` ke server produksi, atau butuh konfirmasi interaktif Owner). **Riwayat rule ini:** versi awal Rule #51 mengharuskan file `deploy-lokal.bat` untuk Owner klik manual di File Explorer — SUDAH DIKOREKSI Owner, file itu sudah dihapus (tidak pernah ter-commit karena masuk `.gitignore`) dan TIDAK diperlukan lagi karena Claude Code menjalankan seluruh alur ini langsung, tanpa perantara file/klik apapun. **Insiden nyata (2026-08-12):** sepanjang sesi Program Loyalty Fase 2 (7 commit, termasuk 2 migration baru: `add_tipe_program_to_loyalty_programs_table` + `create_loyalty_klaims_table`), Claude Code menjalankan "Rule #51 sync" berkali-kali TAPI selalu melewatkan step `migrate --force` (cuma jalankan git pull + 3 seeder + cache clear) — karena secara keliru menganggap step itu "kondisional, cek dulu ada migration baru atau tidak" lalu lupa mengecek. Akibatnya kedua migration itu tetap berstatus Pending di database sampai sesi BERIKUTNYA menemukan lewat audit terpisah (fitur Klaim Event error SQL kalau diakses, karena tabel `loyalty_klaims` & kolom `tipe_program` belum ada secara fisik walau kode Controller/View/Route sudah live). Pelajaran: langkah sync yang "kondisional berdasarkan penilaian sendiri" gampang di-skip diam-diam tanpa disadari — makanya step ini diubah jadi WAJIB TANPA SYARAT, bukan lagi bergantung ke penilaian "apakah ada migration baru".
52. **Analisa Jam Ramai — SATU Sumber Data Terpakai di Report + Widget Dashboard, `transaksi_keuangans` Sengaja Dikecualikan:** `JamRamaiService::getAnalisaJamRamai(Carbon $mulai, Carbon $akhir, ?int $cabangId)` MURNI dari `orders.created_at` (`HOUR(created_at)`, exclude order berstatus Dibatalkan), dipanggil identik oleh `LaporanJamRamaiController` (menu `/laporan/jam-ramai`) DAN widget dashboard `<x-jam-ramai-widget>` (`DashboardController::cabang()`/`pusat()`, filter `Carbon::today()` s/d `Carbon::now()->endOfDay()`) — nol duplikasi logic hitung jam puncak/sepi. **Kenapa BUKAN dari `transaksi_keuangans`:** audit sebelum apply menemukan 58,9% baris `transaksi_keuangans` di-input belakangan (`DATE(created_at) != tanggal_transaksi`) dan 19,8% (39 dari 197 baris) adalah entri auto-generated dari batch Auto Depresiasi (`referensi_type='asset_depreciation'`, lihat Rule #44) — bukan traffic pelanggan real-time. `orders.created_at` sebaliknya 100% valid (0 NULL dari 14 order saat audit, timestamp bervariasi wajar), jadi dipilih sebagai satu-satunya sumber. Keputusan ini eksplisit disetujui Owner setelah audit, bukan asumsi. `jam_sepi` cuma dipilih dari jam yang SUDAH ada aktivitas (`jumlah_transaksi > 0`) — bukan sekadar "jam dengan 0 transaksi" (trivial, mis. jam 3 pagi selalu 0, tidak actionable untuk keputusan promo) — dan diset `null` kalau kebetulan sama dengan `jam_puncak` (edge case cuma 1 jam aktif). **Kolom "Jam" di Riwayat Order & Transaksi Keuangan (2 sumber data BEDA, sengaja tidak disamakan):** Riwayat Order tampilkan `created_at` apa adanya (representasi akurat, sumber data laporan ini). Kelola Kas & Transaksi Keuangan tampilkan `created_at` JUGA, tapi WAJIB disertai ikon info (tooltip "jam entri dicatat, BUKAN jam kejadian bisnis") + ikon jam kuning kondisional kalau `DATE(created_at) != tanggal_transaksi` — konsisten dengan temuan audit 58,9% di atas, supaya user tidak salah asumsi kolom Jam di kedua list punya keandalan yang sama. Permission `laporan.jam_ramai.view` (group `laporan`) Owner-only default, dipakai bersama oleh menu laporan DAN widget dashboard (satu permission, dua tempat tampil) — bukan permission terpisah untuk widget seperti pola `dashboard.analytics.view` di Fase 3, karena keduanya secara substansi menampilkan hal yang sama (cukup 1 gate). Read-only murni — nol perubahan ke `PenjualanService`/`KeuanganController::store()`/skema DB (tidak ada migration).
53. **Search Box Multi-Field — `<x-search-box>` Reusable, Ditempel di Form Filter yang Sudah Ada (Bukan Form Terpisah):** Component `resources/views/components/search-box.blade.php` cuma render `<input name="search">` + icon — SENGAJA tidak membungkus `<form>` sendiri, harus ditaruh DI DALAM `<form method="GET">` filter yang sudah ada di tiap halaman (pola `row g-2 align-items-end` yang sudah jadi konvensi di ~30 halaman list codebase ini) — supaya search + filter dropdown/tanggal submit BARENG dalam satu request tanpa perlu JS penggabung query string. Setiap controller nambah blok `if ($request->filled('search')) { $query->where(fn($q)=>$q->where(...)->orWhere(...)->orWhereHas(...)); }` DI ANTARA filter dropdown existing dan baris `->paginate()` — urutan ini penting supaya search konsisten dengan filter lain (termasuk ikut mempengaruhi kartu ringkasan/total yang dihitung dari clone query yang sama, mis. Kas & Transaksi, Laporan Penjualan). **~20 halaman disentuh** (17 ditambah search baru/di-enhance, 3 ditolak Owner setelah audit: Kas card-grid, Kategori Transaksi tree kecil, dan 3 lagi dideferred karena belum paginated/multi-model: Buku Besar, Data Terhapus, Konsumsi Bahan detail — lihat panduan slug `pencarian-multi-field` untuk daftar lengkap). **2 halaman SENGAJA client-side, bukan server WHERE** (`resources/views/absensi/index.blade.php` — Kelola Absensi, dan `resources/views/evaluasi/ranking.blade.php`): keduanya pakai pola `data-search-row="{{ strtolower(...) }}"` di tiap `<tr>`/card + JS `input` listener yang toggle `style.display` (baris TETAP ada di DOM, cuma disembunyikan) — krusial untuk Kelola Absensi karena itu FORM BATCH SUBMIT (semua karyawan sekaligus disimpan via `$request->absensi` array); kalau searchnya server-side (WHERE di query), baris yang tidak match hilang dari HTML yang di-generate SEBELUM form disubmit, sehingga karyawan yang "tersaring" ikut hilang dari data yang tersimpan — potensi kehilangan data kehadiran kalau user lupa reset search sebelum klik Simpan. **Tab-link status yang sebelumnya reset search saat pindah tab, DIPERBAIKI** di 3 halaman (`setoran/index.blade.php`, `stok/request/index.blade.php`, `stok/transfer/index.blade.php`) — tab pakai `<a href>` biasa (bukan bagian dari `<form>`), jadi `route(...,['status'=>$val])` yang sebelumnya cuma bawa `status` sekarang ditambah `'search' => request('search')` supaya kombinasi search+tab tidak saling override (syarat testing wajib "search + filter bisa dipakai bersamaan"). **2 bug pre-existing genuinely ditemukan & diperbaiki saat testing (bukan disengaja, insidental karena kebetulan nyenggol controller yang sama):** (1) `StockTransferController::index()` CRASH TOTAL (fatal error, `Call to a member function canAccessAllBranches() on null`) untuk SEMUA user karena `$authUser` dipakai di view (`stok/transfer/index.blade.php`, 7 tempat) tapi tidak pernah masuk `compact()` controller — `StockRequestController` punya bug identik tapi cuma warning (kebetulan short-circuit `&&` menghindari fatal call); (2) `EvaluationController::periodIndex()` — view `evaluasi/periods.blade.php` pakai `isset($authUser)` untuk nampilkan card filter cabang DAN tombol "Buat Periode Baru", tapi `$authUser` juga tidak pernah di-compact — `isset()` diam-diam return `false` untuk variabel undefined (bukan warning/fatal seperti kasus StockTransfer), jadi bug ini 100% SILENT: card filter dan tombol itu SELALU tersembunyi untuk SEMUA user termasuk Owner, tidak ada error di log manapun yang bisa ketahuan tanpa baca kode. Fix kedua bug: cuma tambah `$authUser` ke `compact()`, nol perubahan logic lain — pola sama persis (assign `$user` yang sudah ada ke `$authUser` sebelum `return view(...)`).
54. **2 Bug Sistemik Modul Aset (ditemukan via audit query langsung, bukan laporan Owner) — `nilai_buku` Orphan Saat Edit Harga + Chain Depresiasi Rusak Kalau Digenerate Mundur:** (a) `AssetController::update()` SEBELUMNYA tidak pernah menyentuh kolom `nilai_buku` sama sekali — edit `harga_perolehan`/`nilai_residu` pada aset yang sudah punya `nilai_buku` ter-set (dari `create()` atau dari depresiasi sebelumnya) bikin `nilai_buku` orphan/stale terhadap harga baru. Fix: kalau `harga_perolehan`/`nilai_residu` `isDirty` (dibandingkan sebelum vs sesudah `update()`), `nilai_buku` di-resync = `harga_perolehan_baru − SUM(AssetDepreciation.jumlah_penyusutan existing)`, di-cap minimum `nilai_residu_baru` (floor sama dengan `hitungPenyusutanBulanan()`) — **1 formula ini otomatis mencakup 2 kasus sekaligus**: belum ada depresiasi (SUM=0 → nilai_buku_baru = harga_perolehan_baru, setara reset penuh) DAN sudah ada depresiasi (resync proporsional) — makanya tidak perlu 2 cabang logic terpisah seperti sempat diusulkan. (b) `generateAsetTertentu()` SEBELUMNYA tidak ada guard terhadap generate periode MUNDUR (lebih lama dari periode yang sudah ada) — karena `hitungPenyusutanBulanan()` selalu pakai `nilai_buku` SAAT INI (bukan state "seharusnya di awal periode itu") sebagai titik awal, generate periode lama SETELAH periode baru sudah ada bikin `nilai_buku_awal` mewarisi state yang salah (chain rusak). Fix: guard `MAX(periode) existing vs periode baru` di `generateAsetTertentu()` (satu-satunya entry point bersama dipakai manual/bulk/scheduler) — throw Exception dengan pesan jelas kalau mundur (BUKAN silent-skip seperti guard idempotensi existing, karena ini genuine user error yang perlu terlihat). `AssetController::hitungPenyusutan()` (satu-satunya caller tanpa try/catch) dibungkus try/catch supaya pesan error tampil rapi bukan 500 — jalur bulk (`generateUntukPeriode()`) sudah pakai try/catch dari awal, otomatis aman.
    **`FixAsetDepresiasiSeeder` — Data Fix Generic-Loop, BUKAN Hardcode per Kode Aset (direvisi setelah audit ulang):** Versi awal seeder ini hardcode 2 method per kode aset spesifik (`fixAst0016()`/`fixAst0043()`). **Audit ulang menemukan 2 aset LAIN** (AST-2026-0027, AST-2026-0030) kena bug sistemik yang SAMA — dikonfirmasi via Activity Log kedua edit terjadi ~5 jam SEBELUM commit fix (`757ea7b`) di-push, jadi genuinely legacy, bukan bug baru. Karena pola ini terbukti bisa muncul berulang dari histori sebelum fix ada, seeder di-refactor total jadi **2 method generic-loop** yang auto-detect SEMUA aset aktif tanpa hardcode kode aset: `fixChainOrder()` (loop semua aset, deteksi chain rusak via bandingkan `nilai_buku_akhir` periode N vs `nilai_buku_awal` periode N+1, kalau rusak: hapus semua record depresiasi aset itu + soft-delete `TransaksiKeuangan` pasangan + reset `nilai_buku`=`harga_perolehan` + generate ulang berurutan — snapshot record sebelum dihapus di-log ke `storage/logs/laravel.log`, pengganti backup karena `AssetDepreciation` tidak punya `deleted_at`) dijalankan **SEBELUM** `fixSelfConsistency()` (loop semua aset, formula `nilai_buku_seharusnya = harga_perolehan − SUM(depresiasi)` di-cap minimum `nilai_residu`, auto-fix kalau selisih > Rp1) — urutan ini sengaja "fix struktur dulu baru fix angka", walau secara matematis SUM depresiasi tidak berubah oleh regenerate (rate garis lurus tidak tergantung `nilai_buku_awal`), jadi urutan sebenarnya tidak kritis. **Ini jaring pengaman PERMANEN** — kalau ada aset lain lolos dari radar (mis. dari histori sebelum fix, belum pernah ke-audit), tinggal jalankan ulang seeder yang sama, tidak perlu tulis kode baru per kasus. TIDAK didaftarkan di `DatabaseSeeder::run()` (pola sama `KategoriCoaBackfillSeeder`/`NeracaSettingSeeder`) — dijalankan manual via `--class=`, perlu dijalankan lagi di produksi (bukan cuma lokal) tiap kali ada temuan baru. Diverifikasi idempotent penuh (re-run kedua = 0 aksi di kedua method) dan Neraca vs Dashboard aset snapshot selalu konsisten (baca `Asset.nilai_buku` yang sama).
55. **`Route::redirect()` Catch SEMUA HTTP Method (via `Route::any()` Internal) — Backward-Compat Redirect WAJIB `Route::get()` Eksplisit:** `Route::redirect('/setoran', '/transfer-dana')` (backward-compat bookmark lama pasca-rename di Rule #45) secara default meng-capture SEMUA method (GET/POST/PUT/PATCH/DELETE) karena implementasinya pakai `Route::any()` di internal Laravel — bukan cuma GET seperti yang terlihat dari niatnya ("redirect bookmark lama"). Bookmark/link lama SELALU diakses via GET (tidak pernah ada yang bookmark sebuah form POST), jadi meng-capture method lain itu murni potensi bahaya tanpa manfaat — kalau ada request POST/PUT/DELETE yang seharusnya menuju endpoint lain tapi entah kenapa ke-resolve ke path redirect ini (mis. karena kesalahan konfigurasi proxy/rewrite di hosting produksi, atau race condition routing lain), route ini akan diam-diam meng-intersepsinya jadi redirect kosong alih-alih meneruskan ke controller yang benar. Fix: ganti ke `Route::get('/setoran', fn () => redirect('/transfer-dana'))` — eksplisit GET-only, method lain otomatis dapat `MethodNotAllowedHttpException` (bukan ke-redirect diam-diam). **Prinsip umum ke depan:** backward-compat redirect untuk URL lama WAJIB pakai `Route::get()` eksplisit, JANGAN `Route::redirect()` polos, kecuali memang sengaja mau menerima semua method (kasus yang sangat jarang).
56. **`kas.blade.php` — Modal Tambah/Edit Kas WAJIB Tampilkan Error Validasi + Auto-Reopen (Root Cause "Kas Ke-3 Tidak Masuk"):** Root cause pasti dari laporan "Tambah Kas silent fail" — BUKAN bug Apache/`.htaccess`/routing (sudah dibuktikan lewat E2E test asli lewat Apache: submit form Tambah Kas via curl asli berhasil insert ke DB) — murni `resources/views/keuangan/kas.blade.php` TIDAK PERNAH menampilkan error validasi. Backend (`KeuanganController::storeKas()`/`updateKas()`) SUDAH BENAR menolak duplikasi `default_untuk` per cabang (maks 1 kas per tipe pembayaran per cabang) dengan `back()->withInput()->withErrors([...])` — tapi karena form ada di dalam **modal Bootstrap** yang default tertutup setelah redirect, dan tidak ada `@error`/`old()` di field manapun, pesan error itu hilang total dari mata user (terlihat persis seperti "klik Simpan, tidak ada notifikasi, redirect kosong"). Fix: (a) semua field di modal Tambah DAN tiap modal Edit (1 per kas, di-loop) sekarang punya `@error`+`old()`; (b) field hidden `_form_source` (`"tambah"` atau `"edit_{id}"`) dikirim tiap form supaya JS tahu **modal MANA** yang perlu di-reopen otomatis via `old('_form_source')` setelah redirect gagal — krusial karena ada N modal Edit berbeda di 1 halaman yang sama, modal yang salah tidak boleh ikut ke-buka/ke-isi old-input milik kas lain; (c) modal Edit pakai flag `$isEditingThis = old('_form_source') === 'edit_' . $kas->id` untuk scope error/old-value HANYA ke kas yang benar-benar gagal disubmit — field kas LAIN di halaman yang sama tetap tampil data DB normal, tidak ikut kebawa old-input/error kas lain; (d) pesan `default_untuk` diperjelas via helper `pesanDuplikatDefaultUntuk()` (dipakai bareng store+update): `"Opsi \"Tunai\" sudah dipakai kas lain di cabang ini. Pilih opsi lain atau kosongkan."` — bukan pesan generik lama. **Bonus fix ditemukan sekalian** (sama file, sama validasi array yang sedang disentuh): validasi `nomor_rekening` sebelumnya `max:50` padahal kolom DB `varchar(30)` — mismatch ini bisa bikin `QueryException` uncaught (crash 500, BUKAN validation redirect halus) kalau ada input 31-50 karakter; diselaraskan jadi `max:30` di kedua method. **Keterbatasan yang didisclose, bukan di-fix (out of scope, low-risk):** `<x-input-rupiah>` (dipakai utk `saldo_minimum`) baca `$errors->has($name)` global tanpa scoping per-modal — kalau validasi `saldo_minimum` genuinely gagal, SEMUA modal (Tambah + tiap Edit) akan ikut nampilkan red-border sekaligus, bukan cuma yang relevan. Risiko rendah karena `saldo_minimum` selalu terisi angka bersih dari hidden-field JS sync komponen itu sendiri — praktis tidak pernah gagal validasi lewat UI normal.

57. **Transfer Antar Kas (mutasi dana dalam 1 cabang, mis. Tunai ↔ Bank) — 2 Kategori Satu-Arah WAJIB, BUKAN `tipe='keduanya'`:** `TransferAntarKasService::buatTransfer()` INSTAN (kedua saldo Kas langsung terpengaruh dalam 1 `DB::transaction()`, tanpa status menunggu/approval) — beda total dari `SetoranController` (Transfer/Perpindahan Dana ANTAR CABANG, ada workflow approval + `status_setoran`). **Temuan kritis sebelum apply (mengubah desain brief awal):** kategori baru sempat direncanakan 1 kategori dual-purpose `tipe='keduanya'` (kode_akun_coa NULL) — tapi audit menemukan `LabaRugiFormalService::resolveKodeAkun()` (dipakai bersama oleh Laba Rugi Formal + Buku Besar + CrossCheckValidator, lihat Rule #47) punya override PAKSA: `if ($kategoriTipe === 'keduanya' && $tipeTransaksi === 'pemasukan') { $kode = '4-1199'; }` — override ini MENGABAIKAN `kode_akun_coa` yang sengaja NULL, jadi sisi pemasukan (kas tujuan) akan salah kehitung sebagai Pendapatan Usaha Lainnya. Override ini didesain Fase 2 untuk kategori dual-purpose yang defaultnya condong ke beban tapi kadang dipakai pemasukan (mis. "Lainnya"/"Marketing" reimbursement, lihat Rule #46) — BUKAN untuk transfer internal murni yang harus 100% netral. **Fix desain: 2 kategori satu-arah terpisah** — `MUTASI-IN` (nama "Mutasi Kas Masuk", `tipe='pemasukan'`) dan `MUTASI-OUT` (nama "Mutasi Kas Keluar", `tipe='pengeluaran'`), keduanya `kode_akun_coa=NULL` — persis pola `SETOR-IN`/`SETOR-OUT` yang sudah terbukti aman sejak Fase 1, di-seed via `KategoriTransaksiSeeder` (bukan seeder terpisah, `firstOrCreate` idempotent by kode). **Pairing 2 transaksi via `referensi_type`/`referensi_id` polymorphic existing** (`referensi_type='transfer_antar_kas'`, kedua baris saling menunjuk id satu sama lain) — BUKAN `setoran_pair_id` (kolom itu terikat erat ke workflow approval Setoran: dipasangkan dengan `status_setoran`/`diterima_oleh_id`/`waktu_diterima`, dan query `SetoranController` sendiri berasumsi pasangannya kategori `SETOR-IN`/`SETOR-OUT` — reuse akan membingungkan & berisiko nyerempet logic itu). **Zero migration baru** — `referensi_type`/`referensi_id` sudah nullable & fillable di `transaksi_keuangans` sejak awal, tidak ada tabel baru maupun kolom baru ke tabel ledger inti. **Guard di service:** kas asal ≠ kas tujuan, saldo kas asal cukup, kas asal & kas tujuan WAJIB `cabang_id` sama (beda cabang ditolak dengan pesan arahkan ke menu Transfer/Perpindahan Dana existing) — 3 guard ini dites eksplisit via `ValidationException` per skenario. **`hapusTransfer()`** membalik saldo kedua Kas lalu soft-delete kedua sisi (pola sama `SetoranController::destroy()` — resolve pasangan dulu via `referensi_id`, kalau pasangan tidak ketemu `RuntimeException` sebelum mutasi apapun, bukan diam-diam proses 1 sisi saja, lihat Rule #39 untuk kelas bug yang sama persis dihindari). **Diverifikasi lewat rolled-back transaction test terhadap data live**: `resolveKodeAkun()` return `NULL` untuk kedua sisi MUTASI-IN/MUTASI-OUT ✅, `LabaRugiFormalService::hitungLabaRugi()` total pendapatan/beban/laba bersih IDENTIK sebelum-sesudah transfer ✅, `BukuBesarService::getTransaksiPerAkun()` transaksi transfer TIDAK nyangkut di akun manapun ✅, `NeracaService::hitungNeraca()` total kas cabang IDENTIK sebelum-sesudah (uang cuma pindah lokasi, `NeracaService` sum langsung `Kas.saldo_sekarang`, tidak tergantung kategori sama sekali) ✅. Permission `transfer_antar_kas.view/.create/.delete` (group `keuangan`) Owner-only default, sidebar link "Transfer Antar Kas" digate permission sendiri (bukan nested di `keuangan.view`) supaya tetap muncul kalau di-grant terpisah.

58. **Program Loyalty Fase 2 (Event-Based) — `tipe_program` Cabang 2 Jalur Total, Nol Overlap dengan Fase 1:** `LoyaltyProgram` diperluas dengan kolom `tipe_program` (enum `auto_track`/`event_based`, default `auto_track` — migration TERPISAH dari migration asli, bukan edit migration lama, konsisten pola existing) + `nominal_voucher` (nullable, cuma dipakai `event_based`). **`event_based` SENGAJA `target_qty_kg=0`** (kolom NOT NULL di DB, di-force di `LoyaltyProgramController::store()`/`update()`) — bukan dipakai kalkulasi apapun untuk tipe ini. Model baru `LoyaltyKlaim` (SoftDeletes+HasAuditLog+FillsDeletedBy, terdaftar `TrashController`) — workflow murni transisi status via `LoyaltyKlaimService` (`buatKlaim()`→pending, `approve()`→approved+nominal_voucher, `reject()`→rejected+alasan wajib, `markIssued()`→issued, guard `ValidationException` di setiap transisi kalau status awal tidak sesuai). **Guard 1x per pelanggan per program**: cek `whereIn('status', ['pending','approved','issued'])` — SENGAJA exclude `rejected` (pelanggan boleh klaim ulang kalau bukti sebelumnya ditolak, bukan "jatah" yang habis terpakai selamanya). **Bug laten ditemukan & diperbaiki saat implementasi (bukan dari brief, murni konsekuensi menambah `tipe_program` ke model yang sudah dipakai method lama):** `LoyaltyService::getWidgetData()` dan `cekPencapaianBaru()` (Fase 1) loop `LoyaltyProgram::aktif()->get()` TANPA scope tipe — untuk program `event_based` (target_qty_kg=0): kalau `berulang=true` → `floor($totalKg / 0)` **DivisionByZeroError** (crash scheduled command `loyalty:cek-pencapaian`); kalau `berulang=false` → SETIAP pelanggan yang pernah order jasa giling otomatis dianggap "tercapai" (`0 >= 0` selalu true) → `LoyaltyPencapaian` palsu ter-generate massal. Fix: kedua method WAJIB `->autoTrack()` scope — event_based sudah punya jalur sendiri (`LoyaltyKlaim`), tidak boleh ikut pipeline auto-track lama. `PelangganController::show()` sama — `$loyaltyProgress` (progress kg) di-scope `autoTrack()`, `$loyaltyKlaims` (riwayat klaim event) query terpisah gate `loyalty.klaim.view`. **5 permission baru** (`loyalty.klaim.view/buat/approve/reject/issued`, group `loyalty`) — `view`+`buat` default ke kasir (input klaim di lapangan), `approve`+`reject`+`issued` default admin_pusat (Owner selalu bypass). **Widget Dashboard "Klaim Menunggu Approval"** gate `loyalty.klaim.approve` (BUKAN `loyalty.klaim.view` yang juga dipegang kasir — kasir bisa lihat/buat klaim tapi tidak actionable atas widget approval ini, jadi tidak perlu lihat widgetnya). **Tombol POS "Buat Klaim Loyalty untuk Order Ini"** (modal struk, opsional) cuma tampil kalau 3 syarat sekaligus: `order->pelanggan_id` ada (bukan walk-in), user punya `loyalty.klaim.buat`, DAN ada minimal 1 program `event_based` berstatus aktif (`PenjualanController::strukModal()` hitung `$adaEventBasedAktif` sekali, supaya tidak jadi tombol mati/dead-end kalau memang belum ada program buat diklaim) — link ke `loyalty-klaim.create` dengan `pelanggan_id`+`order_id` ter-prefill via query string.
    **Temuan terpisah (bukan Fase 2, ditemukan TIDAK SENGAJA saat testing E2E order POS untuk fitur tombol di atas — genuine bug produksi, dilaporkan ke Owner, TIDAK diperbaiki di sesi ini karena di luar scope 2 topik tugas):** `PenjualanService::batalkan()` (fungsi "Batalkan Order" existing) memanggil `StokService::masuk()` untuk mengembalikan stok tanpa parameter `$hargaBeli` (default `0.0`) — lihat `StokService::masuk()`: `StockBatch::create()` di dalamnya HANYA jalan `if ($hargaBeli > 0)`. Akibatnya, order jasa_giling/produk_jadi yang dibatalkan SETELAH stoknya sempat dikonsumsi FIFO: `stocks.qty` naik lagi dengan benar (via `updateStok()`, tidak tergantung harga), TAPI `stock_batches.qty_sisa` **tidak ikut dipulihkan** — batch yang sempat dikonsumsi tetap permanen berkurang, membuat `stocks.qty` makin lama makin menyimpang dari `SUM(stock_batches.qty_sisa)` untuk item itu (drift kumulatif dari SETIAP pembatalan order yang pernah terjadi di seluruh histori produksi). Efek nyata: `NeracaService::hitungNeraca()` (yang menghitung Persediaan dari FIFO batch, bukan `stocks.qty` langsung) jadi UNDER-STATE nilai persediaan sebesar akumulasi drift ini — kemungkinan penyumbang sebagian dari selisih `-Rp17.511,29` yang sudah dikenal sejak Fase 2 Akuntansi (lihat disclaimer `balance_check` di Rule #46), walau belum diverifikasi persis berapa porsinya. **Butuh keputusan Owner sebelum diperbaiki**: opsi (a) `batalkan()` mulai kirim `hargaBeli` (dari `StockMovement.harga`/rata-rata batch yang dikonsumsi) supaya pembatalan BARU ke depan tidak nambah drift lagi, dan/atau (b) jalankan audit+fix seeder one-off (pola sama `FixAsetDepresiasiSeeder` Rule #54, generic-loop scan semua order Dibatalkan historis vs `stock_batches`) untuk koreksi drift yang SUDAH terjadi — **flagged untuk sesi terpisah**, TIDAK di-apply sekarang karena menyentuh data stok/keuangan produksi yang butuh audit dulu sebelum ada fix apapun dijalankan (prinsip sama seperti seluruh temuan bug lain di riwayat sesi ini).

59. **POS UX Reaktif per Satuan Master Item (Kg vs Pcs) — Bug FormRequest Diam-Diam Bikin Backend Guard Tidak Pernah Aktif:** `pos.blade.php` sebelumnya SELALU tampil label "Berat (kg)" + dropdown kg/ons/gram + "Tarif/kg" apapun `items.satuan` master item-nya — membingungkan kasir untuk item ber-satuan Pcs (mis. kemasan/bumbu jadi hitungan biji) walau data tersimpan sudah benar (`OrderItem.satuan` override dari master, bukan dari UI). Fix: `setSatuanMode(idx, masterSatuan)` (dipanggil dari `pilihBahan()` dan `terapkanResep()`) rebuild dropdown+label+step input berdasar `isSatuanBerat(satuan)` (helper generalisasi, bukan hardcode string `'Pcs'` — future-proof kalau nanti ada satuan non-berat lain selain kg/Pcs) — item Pcs: dropdown terkunci 1 opsi (disabled), label "Qty (Pcs)"/"Tarif/Pcs", step=1; item kg: behavior lama utuh (dropdown kg/ons/gram aktif). `onSatuanDropdownChange()` + `showSatuanWarning()`/`clearSatuanWarning()` jaga-jaga realtime kalau dropdown somehow ke-ubah manual ke satuan yang salah (tombol Proses Order auto-disabled sampai diperbaiki) — walau di practice dropdown Pcs sudah di-disable, ini defense-in-depth murni UX. **Backend defense-in-depth di `PenjualanService::buatOrder()`** (loop `$itemRows`, bandingkan `$masterItem->satuan` vs `$row['satuan']` yang dikirim klien, `throw new \Exception(...)` kalau item bukan kg tapi dikirim dengan satuan berat) **TERBUKTI TIDAK PERNAH AKTIF sampai ditemukan lewat bypass test curl langsung** — root cause: `items.*.satuan` TIDAK PERNAH ada di `OrderRequest::rules()`, jadi `$request->validated()` di `PenjualanController::store()` DIAM-DIAM MEMBUANG field itu sebelum sampai ke service (Laravel FormRequest behavior: field yang tidak ada di `rules()` selalu di-strip dari `validated()`, walau `nullable`/opsional sekalipun) — `$row['satuan'] ?? ''` di service SELALU string kosong apapun yang dikirim client, guard jadi logically unreachable. Fix: tambah `'items.*.satuan' => 'nullable|string|max:20'` ke `OrderRequest::rules()`. **Pelajaran umum ke depan**: field yang cuma dipakai validasi/logic SERVER-SIDE (bukan utk disimpan ke DB) tetap WAJIB didaftarkan di `rules()` FormRequest kalau service butuh membacanya dari `$request->validated()` — kalau tidak, silently hilang tanpa error apapun (persis kelas bug yang sama dengan Rule #47's alias `selectRaw()` vs model cast — 2 mekanisme Laravel berbeda, sama-sama silent data loss kalau lupa satu langkah). Diverifikasi ulang setelah fix: bypass test (kirim satuan salah via curl langsung, skip UI) benar-benar 422 dengan pesan error yang benar, order/order_items/transaksi TIDAK ada sama sekali di DB (bukan partial-save).

60. **Fix `PenjualanService::batalkan()` — Restore Batch Baru Senilai HPP Asli, BUKAN Cari Batch Lama:** Bug di Rule #58 (batalkan() panggil `StokService::masuk()` tanpa `$hargaBeli`, jadi `StockBatch::create()` di dalamnya ke-skip) sudah diperbaiki. **Audit dulu sebelum fix menemukan:** `stock_movements` TIDAK punya kolom `batch_id` (relasi cuma via `item_id`+`lokasi_id`, konsisten Rule #34) — jadi SECARA STRUKTURAL TIDAK MUNGKIN restore ke batch ASAL yang persis sama. **Desain fix:** bikin 1 batch BARU per item yang dikembalikan, dengan harga = `order_items.hpp / order_items.qty` (harga rata-rata per unit yang BENAR-BENAR tercatat di baris order saat penjualan asli) — BUKAN harga batch aktif sekarang (bisa sudah beda) atau `Item.harga_beli_terakhir` generik. Pairing movement→order_item pakai antrian per `item_id` terurut `id` (bukan asumsi naif 1 movement = 1 order_item), supaya tetap benar walau 1 order punya `item_id` yang sama di beberapa baris. `StockMovement` tetap jadi source-of-truth untuk cegah restore 2x (perilaku existing, tidak diubah). **Pola ini reusable** untuk kasus serupa manapun ke depan: kalau butuh "kembalikan nilai yang pernah dikonsumsi" tapi tidak ada jejak batch/sumber persis, cari kolom `hpp`/nilai-tercatat-saat-transaksi-asli di baris anak (order_item/movement) sebagai sumber harga — jangan pernah fallback ke harga SEKARANG (bisa beda, bikin drift baru).
    **Data fix historis (`FixOrderCancelledStockBatchSeeder`):** generic-loop (bukan hardcode ke order tertentu, pola sama `FixAsetDepresiasiSeeder` Rule #54) — scan SEMUA order `dibatalkan` yang punya `stock_movement` 'keluar' tapi belum ada `StockBatch` pasangan (`referensi_type=order`+`referensi_id`+`item_id`), lalu koreksi pakai logic yang SAMA dengan fix di atas. Idempotent, TIDAK didaftarkan di `DatabaseSeeder::run()` — dijalankan manual via `--class=`, WAJIB dijalankan lagi tiap kali audit menemukan order dibatalkan baru yang lolos radar (bukan sekali-jalan-selesai).
    **Edge case ditemukan saat testing, SENGAJA TIDAK diperbaiki (di luar scope, langka):** kalau `batalkan()` dipanggil LAGI setelah `kembalikan()` pada order yang sama, query pencarian movement tipe='keluar' ikut menangkap movement dari `kembalikan()` (yang juga bertipe 'keluar' dengan `referensi` sama) — berpotensi restore 2x. Skenario "batal→pulihkan→batal lagi" dianggap cukup langka untuk di-flag saja, bukan diperbaiki preventif sekarang.
61. **Interpretasi Laba Ditahan Negatif Besar untuk Bisnis Baru Buka Cabang — JANGAN Langsung Simpulkan "Rugi", Cek Distribusi Bulanan Dulu:** Ditemukan lewat audit mendalam (16 Agustus 2026): Laba Ditahan -Rp82.632.268 sempat terlihat mengkhawatirkan, tapi audit forensik (breakdown per akun COA + per bulan) menemukan **77,2% dari SELURUH beban operasional all-time (Rp65,7 juta dari Rp85,1 juta) tercatat SEBELUM order pertama pernah terjadi** — ini biaya buka cabang (sewa gedung, ongkos kirim+pasang mesin dari luar kota, renovasi instalasi listrik, acara syukuran pembukaan), BUKAN pola kerugian operasional berulang. **Cara audit yang benar untuk kasus serupa:** (1) cari tanggal order pertama (`Order::orderBy('tanggal_order')->first()`) sebagai penanda "mulai operasional", (2) pisahkan `LabaRugiFormalService::hitungLabaRugi()` per bulan (bukan cuma cumulative all-time) untuk lihat trend, (3) bandingkan bulan SETELAH buka vs SEBELUM — kalau bulan-bulan operasional normal punya margin kotor sehat (kasus ini: Agustus 46%) sementara bulan pembukaan yang menyeret rata-rata jadi negatif ekstrem, itu tandanya masalahnya "presentasi/klasifikasi akuntansi" (biaya investasi 1x tercampur beban rutin), BUKAN "bisnis sedang berdarah-darah". **Kandidat reklasifikasi yang WAJAR dicurigai** (tapi TIDAK boleh diubah sepihak tanpa konfirmasi Owner soal periode/kontrak aslinya): pembayaran sewa gedung dalam 1 transaksi besar sekaligus (kandidat "dibayar dimuka utk multi-bulan/tahun", seharusnya diamortisasi bertahap, bukan diakui penuh di bulan pembayaran) — sistem ERP ini BELUM punya fitur "Beban Dibayar Dimuka" (prepaid expense amortization), jadi kalau memang polanya berulang (bisnis buka cabang baru lagi ke depan), ini kandidat fitur baru, bukan sekadar reklas kategori manual.

62. **PO Payment — Lock Nominal + Exact-Match Filter + Kolom Status di List (Defense-in-Depth Pola Konsisten Rule #40):** Audit produksi (16 Agustus 2026) menemukan 4 gap UX di PO Tools (Rule #32/#38) — SEMUANYA additive, nol migration, nol perubahan ke flow approve/kirim/terima/Cleanup Tool existing. Kas Keluar dengan `po_id` terisi WAJIB `jumlah == po->total_harga` di 2 lapis: **(1) Frontend** — `jumlahInput.readOnly = true` + `bg-light` + hint teks di `terapkanPo()` (`keuangan/create.blade.php`), di-lepas lagi di `btnResetPo` handler (readonly cuma di VISIBLE display input dari `<x-input-rupiah>`, BUKAN hidden input yang benar-benar ter-submit — jadi auto-fill JS tetap bisa sync value meski readonly aktif). **(2) Server** — `KeuanganController::store()` cek eksplisit `(int)$request->jumlah !== (int)$po->total_harga` kalau `po_id` terisi, ditempatkan SETELAH guard "sudah dibayar" existing (bukan menggantikannya). `update()` **SENGAJA TIDAK** dikasih guard serupa — sudah ada guard existing yang reject total edit transaksi ber-`referensi_type` (dan `edit.blade.php` memang tidak punya UI "Pilih PO" sama sekali, jadi tidak ada jalur untuk kasus ini muncul di situ).
    **Modal Cari Transaksi Existing (Rule #38):** backend `PurchaseOrderController::cariTransaksi()` SUDAH lama menghitung `exact_match`/`selisih_nominal` per baris (dipakai buat sorting & highlight hijau) — gap-nya MURNI di Blade/JS: tombol "Link" sebelumnya SELALU aktif utk semua baris tanpa syarat. Fix: tombol di-disable (bukan baris-nya dibuang — Owner tetap perlu lihat konteks near-match utk verifikasi manual) + tooltip Bootstrap penjelasan, utk baris `!exact_match`. `PurchaseOrderController::linkTransaksi()` ditambah re-check `(int)$transaksi->jumlah !== (int)$po->total_harga` SEBELUM `$transaksi->update(...)` — response tetap `back()->with('error', ...)` (bukan JSON) karena form ini POST biasa (`document.getElementById('linkTransaksiForm').submit()`), bukan fetch/AJAX.
    **List Purchase Order — kolom "Pembayaran"** (badge Sudah/Belum Dibayar): cuma tampil untuk PO status `Diterima` (status lain memang belum layak dibayar, tampil em-dash). Method BARU `PoDashboardService::getStatusPembayaranBatch(array $poIds)` — 1x query `whereIn('referensi_id', $poIds)` + `DB::table()` raw (BUKAN Eloquent — konsisten class docblock PoDashboardService yang sengaja hindari interaksi CabangScope/SoftDeletingScope, semua service ini pakai raw query + `whereNull('deleted_at')` eksplisit). Dipanggil SETELAH `paginate()` di `PurchaseOrderController::index()` (bukan masuk query utama) — cegah N+1 dibanding panggil `cekSudahDibayar()` (method lama, tetap dipakai apa adanya di Detail PO single-record) dalam loop per baris list. Badge dipasang di desktop table DAN mobile card (`d-md-none`), disusun vertikal (`flex-column`) di mobile supaya tidak overflow horizontal berdampingan dgn badge status alur.
    **Dead-end link "Lihat Transaksi" di Detail PO:** sebelumnya arah ke `route('keuangan.edit', ...)` yang SELALU ditolak untuk transaksi ber-`referensi_type` (guard existing di `edit()`) — user klik, langsung dilempar balik dgn error, tidak pernah bisa lihat detailnya. Fix: route+method+view BARU `keuangan.show` (`GET /keuangan/{transaksi}/detail`, method `KeuanganController::show()`) — read-only, reuse permission `keuangan.view` yang sudah ada (bukan permission baru). Resolusi `referensi_type='purchase_order'` manual (BUKAN `$transaksi->referensi` — morphTo() bawaan error karena `referensi_type` string logis tanpa morphMap, sama pola dgn `TrashController::resolveReferensi()`), dipakai utk tombol "Kembali ke PO" di halaman detail.
63. **Kas Keluar Pilih PO — Auto-Fill Semua Field Sekaligus + Reset Total (Fase 3 Fix Regresi):** Owner melaporkan (via screenshot) `terapkanPo()` (Rule #62) partial jalan — lock nominal 🔒 aktif tapi Tipe/Kategori/Keterangan/Jumlah tidak ter-isi. Audit kode TIDAK menemukan bug logic yang pasti (semua selector/urutan statement sudah benar secara statis: `tipeSelect.value='pengeluaran'` di-set SEBELUM `dispatchEvent('change')`, backend `poPendingList()` sudah mengembalikan `kategori_id_default`/`kategori_pengeluaran_default`/`keterangan_default` dengan benar — diverifikasi lewat panggilan langsung `PoDashboardService` di tinker) — kemungkinan race-condition/timing spesifik browser yang tidak bisa direproduksi lewat testing curl (JS browser TIDAK BISA dites via curl, hanya server-side yang bisa diverifikasi otomatis). Fix defensif: `filterKategori()`/`toggleKategoriPengeluaran()` sekarang DIPANGGIL LANGSUNG di `terapkanPo()` (bukan cuma mengandalkan `dispatchEvent('change')` + listener chain 3-lapis yang terpasang), plus `kategoriSelect.dispatchEvent('change')` setelah value di-set (supaya `toggleHighlightPo()` ikut update), plus seluruh `terapkanPo()` dibungkus `try/catch` dengan `console.error` (kalau masih gagal di browser Owner, error message sekarang tertangkap & bisa dilaporkan). `btnResetPo` diperluas: sebelumnya cuma reset Jumlah, sekarang ikut reset Tipe/Kategori/Kategori Pengeluaran/Keterangan (`filterKategori()`+`toggleKategoriPengeluaran()` dipanggil ulang dengan `tipeSelect.value=''`) — supaya kasir yang batal pakai PO benar-benar mulai dari form kosong, bukan tersisa nilai auto-fill yang membingungkan. Filter dropdown **Status Pembayaran** baru di List PO (`status_bayar=sudah/belum`) — WAJIB `whereExists`/`whereNotExists` di query builder SEBELUM `paginate()` (bukan filter Collection setelah fetch), supaya total count & pagination tetap benar; otomatis dipaksa `status=Diterima` (status lain tidak punya konsep bayar).
    **Investigasi "Tombol Cari Transaksi Existing tidak muncul" — TERNYATA BUKAN BUG:** dicek 3 kandidat sebelum ubah kode apapun (sesuai prinsip audit-dulu): (1) permission `transaksi.link_po.action` — Owner (role Owner) BYPASS via `Gate::before`, dikonfirmasi `$owner->can('transaksi.link_po.action')` return `true`; (2) `cekSudahDibayar()` mengembalikan `stdClass`/`null` (bukan boolean) — konsisten dengan pemakaian `$transaksiPembayaran->nomor_transaksi` di Blade, tidak ada type-mismatch; (3) tombol MEMANG cuma tampil di kondisi `$po->status === Diterima DAN belum dibayar` (`pembelian/show.blade.php` baris ~187, di dalam `@else` dari `@if($transaksiPembayaran)`) — diverifikasi lewat 2 PO real (Belum Dibayar → tombol muncul; Sudah Dibayar → tombol hilang, link "Lihat Transaksi" yang muncul). Kesimpulan: Owner kemungkinan testing di PO yang statusnya bukan Diterima, atau PO yang sudah dibayar — behavior BENAR sesuai desain, TIDAK ADA perubahan kode untuk temuan ini.
    **TEMUAN KRITIS saat testing — `TransaksiKeuanganObserver::deleted()` cascade-soft-delete PO/Order (observer PRE-EXISTING, bukan buatan Fase 2/3):** `app/Observers/TransaksiKeuanganObserver.php` (terdaftar `AppServiceProvider::boot()`) punya method `deleted()` yang OTOMATIS soft-delete `purchase_orders`+`purchase_order_items` (atau `orders`+`order_items`) manapun yang jadi `referensi_id` dari `TransaksiKeuangan` yang baru saja dihapus (raw `DB::table()->update(['deleted_at'=>now()])`, cascade "safety net" untuk kode yang bypass controller). Event `deleted` ini fire baik untuk soft-delete BIASA **maupun `forceDelete()`** (perilaku default Eloquent). **Insiden nyata saat testing Fase 3:** `forceDelete()` transaksi test yang di-link ke PO REAL (#36, bukan PO test disposable) men-trigger observer ini, PO#36 (data produksi asli, bukan data uji) ikut ter-soft-delete tanpa disengaja. Ketauan lewat baseline Q3/Q4 yang selisih 1 dari snapshot awal, ditelusuri via `Activity::where('subject_type', TransaksiKeuangan::class)` (timestamp `deleted_at` PO persis sama dengan timestamp force-delete transaksi test) — segera direstore manual (`DB::table('purchase_orders')`/`purchase_order_items` set `deleted_at=null`, PurchaseOrderItem model TIDAK punya trait `SoftDeletes` jadi `withTrashed()` tidak berlaku, WAJIB raw query). **Pelajaran wajib untuk sesi testing ke depan:** kalau test-link sebuah `TransaksiKeuangan` ke Order/PO **REAL** (bukan PO/Order test buatan sendiri yang memang akan dihapus juga), JANGAN PERNAH `forceDelete()`/`delete()` transaksi test itu langsung — WAJIB salah satu: (a) `$transaksi->update(['referensi_type'=>null,'referensi_id'=>null])` dulu sebelum delete (putuskan link-nya, observer cuma cek `referensi_type` saat event `deleted` fire), atau (b) kalau linknya justru sengaja mau dites lalu dibersihkan, SELALU langsung cek ulang status PO/Order yang jadi referensi setelahnya (`withTrashed()`/query `deleted_at`) sebelum lanjut ke baseline re-check — jangan asumsikan "cuma transaksi yang kena efek".
64. **Select2 (jQuery Plugin) + Native addEventListener Compatibility — Root Cause Asli Bug Rule #63 (Ditemukan via Playwright Headless, Bukan Audit Statis):** Rule #63 sempat menerapkan fix DEFENSIF (panggil filterKategori()/toggleKategoriPengeluaran() langsung + try/catch) karena audit statis tidak menemukan bug pasti — root cause SEBENARNYA baru ketahuan lewat testing browser headless (Playwright) sungguhan, karena bug ini SECARA STRUKTURAL tidak mungkin ketahuan dari baca kode atau curl testing: dropdown #poSelect di-enhance Select2 ($(poSelect).select2({...})), dan saat user klik opsi di widget Select2, perubahan dipicu lewat jQuery.trigger('change') INTERNAL Select2 — event ini TIDAK terdengar oleh element.addEventListener('change', ...) native biasa (dibuktikan lewat observer independen: nativeListenerFired=false, jqueryListenerFired=true untuk trigger yang identik). Akibatnya terapkanPo() (dipasang via poSelect.addEventListener('change', ...)) TIDAK PERNAH JALAN setiap kali user pilih PO lewat widget Select2 — poIdInput tetap kosong jadi bukti pasti. **Fix final** (keuangan/create.blade.php): handler handlePoChange() dipasang di DUA jalur sekaligus — poSelect.addEventListener('change', handlePoChange) (native, fallback kalau Select2/jQuery gagal load dari CDN) DAN jQuery(poSelect).on('change', handlePoChange) (menangkap trigger Select2) — dengan guard let lastPoValue (skip kalau val === lastPoValue) supaya tidak dobel-proses kalau kondisi browser tertentu memicu keduanya untuk 1 event yang sama. Logic reset diekstrak ke resetPoFields() (dipakai bareng tombol Reset dan handlePoChange() saat dropdown di-clear ke ""). Bug yang SAMA PERSIS juga diam-diam merusak fitur pre-select ?po_id=X (dipakai tombol "Catat Pembayaran" dari Dashboard PO/Detail PO, $(poSelect).val(preselectId).trigger('change')) — otomatis ikut ter-fix tanpa perlu sentuhan kode terpisah, karena sama-sama lewat handlePoChange().
    **Related pitfall — Bootstrap Utility Class !important vs Inline style:** ditemukan berbarengan saat investigasi di atas — #jumlahLockedHint (hint kunci "Nominal terkunci...") punya class d-block SEKALIGUS inline style="display:none". Bootstrap 5 utility classes (.d-block/.d-none/.d-flex/dst) semuanya !important — MENANG melawan inline style biasa (yang TIDAK !important), jadi elemen ini SELALU tampil dari initial page load, regardless of apakah terapkanPo() pernah jalan atau tidak. Ini yang bikin laporan awal Owner ("readonly aktif, help-text kunci muncul, tapi field lain kosong") terlihat seperti PARTIAL success padahal SEBENARNYA nol field ter-auto-fill sama sekali — hint-nya cuma kebetulan selalu nyala duluan karena bug CSS terpisah, bukan sinyal bahwa sebagian logic JS jalan. **Fix:** hapus d-block, ganti default state jadi class d-none (Bootstrap idiomatic), toggle pakai classList.add('d-none')/classList.remove('d-none') di JS — BUKAN style.display, supaya tidak pernah lagi bentrok dengan utility class manapun ke depan. **Prinsip umum ke depan:** kalau sebuah elemen show/hide-nya dikontrol JS, JANGAN campur style.display dengan utility class Bootstrap (.d-*) sekaligus di elemen yang sama — pilih SATU mekanisme (disarankan: classList + .d-none, bukan inline style).
    **Metodologi baru untuk bug UI yang lolos audit statis + curl:** kalau audit kode manual tidak menemukan bug pasti tapi Owner tetap melaporkan gejala nyata di browser sungguhan (apalagi setelah cache/incognito dikesampingkan), WAJIB eskalasi ke testing browser headless (Playwright, sudah terinstall npm install --save-dev playwright + npx playwright install chromium) — reproduce via page.evaluate()/page.waitForResponse(), JANGAN asumsikan curl atau baca kode statis cukup untuk bug yang melibatkan interaksi JS-plugin (Select2/Choices.js/dst) dengan event DOM. Simulasikan trigger PERSIS seperti yang plugin lakukan (jQuery(el).val(x).trigger('change'), BUKAN page.selectOption() yang memicu native event asli — beda skenario), pasang listener observer independen untuk membandingkan nativeListenerFired vs jqueryListenerFired, dan SELALU screenshot before/after untuk menangkap state visual yang mungkin menyesatkan (seperti kasus hint kunci di atas).
65. **Batal Bayar PO — Fitur Rollback Pembayaran, WAJIB Bypass Observer Cascade Rule #63 (Fase 4):** Owner/Admin Pusat (permission po.batal_bayar.action, default Owner+admin_pusat) bisa membatalkan pembayaran PO via tombol "Batal Bayar PO" di Detail PO (muncul cuma di dalam kondisi Sudah Dibayar, sebelah link "Lihat Transaksi") — untuk kasus salah kas/salah PO/PO cancelled (jarang, 1-2x/bulan). PurchaseOrderController::batalBayar(): (1) restore saldo kas via increment(), (2) TransaksiKeuangan::forceDelete() (hard delete, bukan soft — biar bersih, tidak nyisa jejak ambigu di Data Terhapus), (3) status PO otomatis balik "Belum Dibayar" murni dari absennya transaksi ber-referensi (getStatusPembayaranBatch()/cekSudahDibayar() existing, TIDAK ADA kolom yang diubah di purchase_orders), (4) activity('PurchaseOrder')->log(...) manual. **KRITIS:** seluruh restore-saldo+forceDelete WAJIB dibungkus TransaksiKeuangan::withoutEvents(fn() => ...) — TANPA ini, TransaksiKeuanganObserver::deleted() (bug pre-existing Rule #63, app/Observers/TransaksiKeuanganObserver.php) otomatis cascade-soft-delete PurchaseOrder yang jadi referensi_id transaksi itu (safety net untuk kode yang bypass controller, tapi di sini justru merusak — PO yang barusan mau di-restore-pembayarannya malah ikut hilang). Diverifikasi lewat testing: PO tetap ada & aktif setelah batal bayar, deleted_at tetap NULL.
    **Permission — bukan Spatie, custom Permission model + role_permissions:** codebase ini TIDAK pakai package spatie/laravel-permission (dicek: tidak ada di composer.json) — pakai model App\Models\Permission sendiri (kolom name/display_name/group/description) + tabel pivot role_permissions (kolom role STRING langsung, BUKAN model_has_roles/role_has_permissions ala Spatie) + Gate::define() dinamis di AppServiceProvider dari Permission::pluck('name') (di-cache 1 jam, key all_permission_names) + User::hasPermission() yang query role_permissions join permissions (di-cache 5 menit per role, key role_permissions_{role}) dengan Owner selalu true tanpa query. **Permission baru WAJIB lewat PermissionSeeder.php (array + Permission::firstOrCreate, idempotent) + RolePermissionSeeder.php (tambah nama permission ke array role yang dituju) — BUKAN migration terpisah** (migration di codebase ini reserved untuk perubahan skema tabel, bukan data permission — data permission ikut alur Rule #51 seeder re-run tiap deploy, migration sekali-jalan tidak akan ter-refresh otomatis kalau ada revisi). `po.batal_bayar.action` ditambahkan ke `admin_pusat` array saja di RolePermissionSeeder (Owner tidak perlu, selalu bypass); Manajer Cabang/role lain bisa ditambah manual kapan saja lewat UI Role & Hak Akses tanpa perlu sentuh kode (array RolePermissionSeeder cuma DEFAULT, UI Role overridable per role).
66. **Modul Perlengkapan Habis Pakai — 100% Additive, Zero Sentuh Fitur Existing (Fase 5):** Extend Master Barang (items) untuk tracking barang habis pakai non-produksi (masker, pulpen, sabun, tissue) — bukan modul terpisah. 2 kolom BARU: items.jenis (enum bahan_baku|perlengkapan, default bahan_baku, kolom ORTOGONAL dari items.tipe existing yang sudah 4 nilai sejak awal - bahan_baku/produk_jadi/kemasan/lainnya) + items.track_stok (boolean, default true). Item existing otomatis jenis=bahan_baku+track_stok=true, zero perubahan behavior. Menu "Master Barang" (BUKAN "Bahan Baku" - salah asumsi umum, menu ini sudah generik 4 tipe sejak awal) TIDAK di-rename, filter jenis cuma ditambah paralel dengan filter tipe existing.
    **Beban akuntansi (Metode A - Cash Basis):** kategori KategoriTransaksi baru "PERLENGKAPAN" (Beban Perlengkapan Kantor) menunjuk ke kode COA 6-1106 yang SUDAH ADA sejak ChartOfAccountsSeeder awal (Fase 1 Akuntansi) tapi belum pernah dipetakan ke kategori manapun - PerlengkapanKategoriCoaSeeder cuma insert 1 baris KategoriTransaksi, ZERO insert baru ke chart_of_accounts, ZERO sentuh resolveKodeAkun()/LabaRugiFormalService. Alur: buat item jenis=perlengkapan → PO ke supplier (reuse 100% workflow existing - approve/kirim/terima, PurchaseOrderController::create() sudah query Item::where('is_active',true) tanpa filter tipe/jenis, jadi otomatis muncul di picker PO tanpa sentuhan kode) → bayar via Kas Keluar dengan kategori "Beban Perlengkapan Kantor" (Fase 3B auto-fill dropdown Pilih PO tetap works apa adanya). Beban diakui saat PO dibayar, sama persis pola PO Bahan Baku - diverifikasi Laba Rugi Formal bulan berjalan IDENTIK sebelum/sesudah Commit 1 (kategori baru 0 transaksi historis, tidak mengubah resolusi kode akun transaksi manapun).
    **Menu BARU "Pemakaian Perlengkapan"** (model+tabel BARU pemakaian_perlengkapans, SoftDeletes+HasAuditLog+FillsDeletedBy pola LoyaltyKlaim) — TERPISAH dari Adjustment Stok existing (StokController/AdjustmentStokRequest/StokService/stok/adjustment.blade.php ZERO disentuh) untuk hindari risk regresi ke fitur bahan baku yang sudah jalan. PemakaianPerlengkapanService murni memanggil StokService::keluar() existing (signature asli: int $itemId, int $lokasiId, float $qty, ?string $catatan, ?string $referensiType, ?int $referensiId) - baris pemakaian ditautkan ke StockMovement via referensi_type='pemakaian_perlengkapan'+referensi_id (pola polymorphic existing Rule #32, BUKAN kolom baru di stock_movements atau marker teks di catatan). Notifikasi stok minimum OTOMATIS jalan (StokService::keluar()→updateStok()→cekNotifikasiStok() existing, Rule #27) - zero kode notifikasi baru ditulis, cukup reuse qty_minimum per lokasi yang harus di-set manual saat bikin item perlengkapan (default 0 = notif tidak aktif, ada peringatan di form create/edit).
    **Laporan Pemakaian Perlengkapan** (LaporanPerlengkapanService BARU) baca langsung dari tabel pemakaian_perlengkapans - BUKAN reuse LaporanKonsumsiBahanService (sumber datanya order_items, perlengkapan tidak pernah masuk order pelanggan jadi tidak ada baris untuk dibaca di situ). Widget dashboard "Perlengkapan Menipis" SENGAJA self-contained (query DB::table/Model inline langsung di file components/perlengkapan-menipis-widget.blade.php, di-@include bukan <x-component> dengan props) - beda struktur dari 6 widget dashboard lain yang semua terima data via props dari DashboardController, demi memenuhi syarat ketat "DashboardController TIDAK DISENTUH SAMA SEKALI". Dipasang di dashboard/cabang.blade.php + pusat.blade.php (bukan gudang.blade.php, konsisten pola widget lain).
    **4 permission BARU** (pemakaian_perlengkapan.view/create/delete/export, group stok) - HANYA view+create yang punya route aktif di Fase 5 ini (index/create/store/show), delete/export cuma didaftarkan sebagai placeholder untuk fitur susulan (belum ada UI/logic-nya). 3 permission laporan (laporan.perlengkapan.view/print/export, group laporan). Default: owner+admin_pusat+admin_gudang+manajer_cabang dapat pemakaian_perlengkapan.view/create; kasir+helper TIDAK dapat (bukan tanggung jawab mereka, sesuai keputusan Owner). laporan.perlengkapan.* default admin_pusat saja (Owner selalu bypass).
    **Temuan audit yang SENGAJA TIDAK diperbaiki di Fase 5** (hormati prinsip "jangan sentuh existing"): filter tipe di halaman Stok Barang (stok/index.blade.php) cuma py 3 opsi (bahan_baku/produk_jadi/kemasan, kurang 'lainnya') beda dari filter tipe di Master Barang yang py 4 opsi lengkap - kalau item perlengkapan pakai tipe='lainnya', tidak bisa difilter dari halaman Stok Barang sampai bug pre-existing ini diperbaiki di fase terpisah. Section sidebar "Stok & Gudang" (`@canany(['stok.view','stok.request','stok.transfer'])`) tidak include 'pemakaian_perlengkapan.view' atau 'item.view' - kalau ada role custom yang CUMA dapat salah satu dari keduanya tanpa stok.view sama sekali, section (termasuk link baru ini) tidak akan tampil; tidak jadi masalah untuk assignment role default Fase 5 (semua yang dapat pemakaian_perlengkapan.view juga sudah py stok.view), tapi flagged untuk kesadaran ke depan kalau ada role custom baru.

## Format Nominal Rupiah

### Aturan Input (Form)
- Semua input nominal uang WAJIB menggunakan komponen `<x-input-rupiah>`
- Saat user mengetik angka, otomatis muncul titik pemisah ribuan (contoh: 15000000 → 15.000.000)
- Prefix "Rp" tampil di depan input menggunakan Bootstrap input-group
- Hanya boleh input angka (blokir huruf dan karakter lain)
- Saat form submit, nilai yang dikirim ke server adalah angka murni tanpa titik
- Input harus nyaman diketik di mobile

### Aturan Tampilan (View / Tabel / Laporan)
- Semua nominal di tabel, card, dashboard, dan laporan tampil dengan format "Rp 15.000.000"
- Gunakan Blade directive `@rupiah($nominal)` untuk format tampilan
- Gunakan helper function `formatRupiah()` di PHP
- Konsisten di seluruh halaman dan modul

### Field yang WAJIB Format Rupiah
- Penjualan/POS: harga per kg, harga jual, subtotal, total, nominal bayar, kembalian
- Pembelian: harga satuan, subtotal, total PO
- Karyawan/Penggajian: gaji pokok, tunjangan, potongan, lembur, total gaji
- Keuangan: jumlah kas masuk, kas keluar, saldo
- Aset: harga perolehan, nilai residu, nilai buku, biaya maintenance, nilai jual disposal
- BEP: biaya tetap, biaya variabel, harga jual per unit, BEP rupiah
- Stok: harga beli item, harga jual item

### Perhitungan Otomatis
- Input yang menghitung otomatis (contoh: subtotal = qty × harga) hasilnya juga harus terformat Rupiah
- Perhitungan menggunakan nilai angka murni, tampilan menggunakan format titik ribuan

## Tim Pengguna
Sistem digunakan oleh tim kecil (2-5 orang per lokasi) dengan role berbeda:
- **Owner / Admin Pusat** — Akses penuh semua cabang + gudang pusat + dashboard konsolidasi + BEP + aset + penilaian semua karyawan
- **Admin Gudang Pusat** — Kelola stok gudang, terima PO dari vendor, proses permintaan cabang, kirim transfer
- **Manajer Cabang** — Kelola operasional 1 cabang, approve pembelian mendesak, dashboard & laporan cabang, lihat BEP cabang, **menilai karyawan cabang sendiri**
- **Kasir** — POS, penjualan, pembayaran di cabang sendiri, **menilai rekan kerja & self assessment**
- **Operator Produksi** — Input produksi, stok bahan baku di cabang sendiri, **menilai rekan kerja & self assessment**

## Sistem Notifikasi (Bell Notification)

### Konsep Utama
- Setiap event penting menghasilkan notifikasi real-time di ikon lonceng (🔔) di navbar
- Badge angka merah menunjukkan jumlah notifikasi belum dibaca
- Klik lonceng → dropdown daftar notifikasi terbaru (max 10)
- Link "Lihat Semua" → halaman daftar notifikasi lengkap dengan filter & pagination
- Notifikasi bisa ditandai sudah dibaca (per satu atau tandai semua dibaca)
- Menggunakan Laravel Notifications (database channel)
- Polling setiap 30 detik untuk cek notifikasi baru
- Notifikasi otomatis dikirim ke user yang relevan berdasarkan role & cabang

### Tabel Notifications
Menggunakan tabel bawaan Laravel Notifications:
```
php artisan notifications:table
php artisan migrate
```

### Daftar Event yang Menghasilkan Notifikasi

#### Pembelian / Procurement
| Event | Dikirim ke | Isi Notifikasi |
|-------|-----------|----------------|
| PO baru dibuat (via gudang) | Admin Gudang, Owner | "PO baru #PO-001 dari Supplier X menunggu persetujuan" |
| PO disetujui | Admin Gudang, Pembuat PO | "PO #PO-001 telah disetujui" |
| PO barang diterima | Admin Gudang, Owner | "Barang PO #PO-001 telah diterima di Gudang Pusat" |
| PO langsung/mendesak oleh cabang | Owner, Manajer Cabang | "⚠️ Pembelian mendesak #PO-002 oleh Cabang A menunggu approval" |
| PO langsung disetujui/ditolak | Kasir/Pembuat PO | "Pembelian mendesak #PO-002 telah disetujui/ditolak" |

#### Stok
| Event | Dikirim ke | Isi Notifikasi |
|-------|-----------|----------------|
| Stok mendekati minimum | Manajer Cabang, Admin Gudang | "⚠️ Stok Daging Sapi di Cabang A tinggal 5 kg (minimum: 10 kg)" |
| Stok habis (0) | Manajer Cabang, Admin Gudang, Owner | "🔴 Stok Tepung Terigu di Cabang B HABIS!" |
| Stock request baru dari cabang | Admin Gudang | "Permintaan bahan baru dari Cabang A menunggu persetujuan" |
| Stock request disetujui | Manajer Cabang peminta | "Permintaan bahan #SR-001 telah disetujui oleh Gudang" |
| Stock request ditolak | Manajer Cabang peminta | "Permintaan bahan #SR-001 ditolak. Alasan: ..." |
| Transfer stok dikirim | Manajer Cabang tujuan | "Transfer bahan dari Gudang Pusat dalam perjalanan ke Cabang A" |
| Transfer stok diterima | Admin Gudang, Owner | "Transfer #TF-001 telah diterima oleh Cabang A" |

#### Penjualan
| Event | Dikirim ke | Isi Notifikasi |
|-------|-----------|----------------|
| Target penjualan harian tercapai | Manajer Cabang, Owner | "🎉 Target penjualan harian Cabang A tercapai! Omzet: Rp X" |
| Transaksi besar (di atas threshold) | Manajer Cabang, Owner | "Transaksi besar Rp 5.000.000 di Cabang A oleh Pelanggan X" |

#### Karyawan / HR
| Event | Dikirim ke | Isi Notifikasi |
|-------|-----------|----------------|
| Pengajuan cuti baru | Manajer Cabang | "Pengajuan cuti dari Budi (Cabang A) tanggal 1-3 Jan 2026" |
| Cuti disetujui/ditolak | Karyawan pengaju | "Cuti Anda tanggal 1-3 Jan 2026 telah disetujui/ditolak" |
| Slip gaji sudah digenerate | Semua karyawan cabang | "Slip gaji bulan Januari 2026 sudah tersedia" |
| Karyawan tidak hadir tanpa keterangan | Manajer Cabang | "⚠️ Budi (Operator) tidak hadir tanpa keterangan hari ini" |

#### Penilaian Karyawan 360°
| Event | Dikirim ke | Isi Notifikasi |
|-------|-----------|----------------|
| Periode penilaian dibuka | Semua user di cabang | "📋 Periode penilaian Q1 2026 telah dibuka. Deadline: 15 Jan 2026" |
| Ditugaskan sebagai penilai | User penilai | "Anda ditugaskan menilai 3 rekan kerja untuk periode Q1 2026" |
| Reminder belum mengisi penilaian | User yang belum isi | "⏰ Reminder: Anda belum mengisi penilaian Q1 2026. Deadline 3 hari lagi" |
| Semua penilai selesai mengisi | Manajer Cabang | "Semua penilaian Q1 2026 untuk Cabang A sudah lengkap, siap di-review" |
| Hasil penilaian sudah final | Karyawan yang dinilai | "Hasil penilaian Q1 2026 Anda sudah tersedia. Skor: 4.2 (Baik)" |

#### Aset
| Event | Dikirim ke | Isi Notifikasi |
|-------|-----------|----------------|
| Pengajuan pembelian aset baru | Owner | "Pengajuan pembelian aset: Mesin Giling Baru untuk Cabang A - Rp 15.000.000" |
| Aset perlu maintenance rutin | Manajer Cabang, Owner | "🔧 Mesin Giling #AST-001 sudah waktunya maintenance rutin" |
| Aset rusak dilaporkan | Manajer Cabang, Owner | "⚠️ Freezer #AST-005 di Cabang B dilaporkan rusak berat" |
| Mutasi aset menunggu approval | Owner | "Pengajuan mutasi aset Mesin Giling dari Cabang A ke Cabang B" |

#### Keuangan
| Event | Dikirim ke | Isi Notifikasi |
|-------|-----------|----------------|
| Kas harian belum ditutup | Kasir, Manajer Cabang | "⏰ Kas harian Cabang A belum ditutup/rekonsiliasi" |
| Selisih kas ditemukan | Manajer Cabang, Owner | "⚠️ Selisih kas Rp 50.000 ditemukan di Cabang A" |
| BEP tercapai bulan ini | Manajer Cabang, Owner | "🎉 BEP Cabang A tercapai bulan ini! Surplus: Rp X" |
| BEP belum tercapai mendekati akhir bulan | Manajer Cabang, Owner | "⚠️ BEP Cabang B belum tercapai (baru 65%), sisa 5 hari" |

### Tampilan Notifikasi

#### Lonceng di Navbar
- Ikon 🔔 di navbar (samping cabang switcher)
- Badge merah dengan angka notifikasi belum dibaca
- Badge hilang jika semua sudah dibaca
- Klik → dropdown notifikasi terbaru (max 10 item)
- Setiap item: ikon kategori + judul + waktu relatif (5 menit lalu, 1 jam lalu, kemarin)
- Hover/klik item → tandai sudah dibaca + redirect ke halaman terkait
- Tombol "Tandai Semua Dibaca" di header dropdown
- Link "Lihat Semua Notifikasi" di footer dropdown

#### Halaman Notifikasi (/notifikasi)
- Daftar semua notifikasi dengan pagination
- Filter: semua/belum dibaca/sudah dibaca, per kategori (stok/pembelian/penjualan/hr/aset/keuangan)
- Tandai sudah dibaca per item atau bulk
- Hapus notifikasi lama
- Responsive: card view di mobile

### Pengaturan Notifikasi (Opsional)
- User bisa on/off jenis notifikasi tertentu di halaman profil/settings
- Contoh: Kasir bisa matikan notifikasi stok jika tidak relevan

### Aturan Notifikasi
- Notifikasi hanya dikirim ke user yang relevan (berdasarkan role DAN cabang)
- Notifikasi stok cabang A tidak dikirim ke Manajer Cabang B
- Owner menerima notifikasi penting dari semua cabang
- Notifikasi lama (> 30 hari sudah dibaca) bisa di-cleanup otomatis
- Warna/ikon notifikasi sesuai kategori:
  - 🔴 Merah: urgent/error (stok habis, selisih kas)
  - 🟡 Kuning: warning (stok rendah, deadline mendekati)
  - 🔵 Biru: info (PO disetujui, transfer dikirim)
  - 🟢 Hijau: success (BEP tercapai, target tercapai)

## Filter Tanggal & Data Gabungan

### Filter Rentang Tanggal (WAJIB)
Semua halaman yang menampilkan data transaksi/laporan WAJIB memiliki filter tanggal menggunakan komponen `<x-date-range-filter>`.

#### Komponen Date Range Filter
- 2 input tanggal: Dari (mulai) & Sampai (akhir)
- Quick filter buttons: Hari Ini, Minggu Ini, Bulan Ini, 3 Bulan, 6 Bulan, Tahun Ini, 1 Tahun Terakhir, Semua Data
- Tombol "Filter" dan "Reset"
- Default filter: Bulan Ini
- Simpan filter terakhir di session supaya tidak reset saat pindah halaman
- Responsive: quick filter buttons scroll horizontal di mobile

#### Halaman yang WAJIB ada Filter Tanggal
- Dashboard (semua card & grafik)
- Kas & Transaksi
- Laporan Keuangan (Laba Rugi, Arus Kas)
- Analisa BEP
- Laporan Penjualan
- Laporan Stok (pergerakan stok)
- Laporan HR (absensi, penggajian)
- Laporan Aset (depresiasi, maintenance)
- Riwayat transaksi penjualan
- Riwayat pembelian/PO

### Data Gabungan Semua Cabang (WAJIB untuk Owner/Admin Pusat)
Saat Owner/Admin Pusat memilih mode "Semua Cabang":
- Semua halaman harus menampilkan data gabungan dari seluruh cabang
- Tabel transaksi gabungan harus ada kolom "Cabang" untuk identifikasi asal data
- Card ringkasan menampilkan total gabungan semua cabang
- Tampilkan juga ringkasan per cabang (breakdown per cabang)
- Grafik menampilkan data gabungan atau perbandingan antar cabang

## Sistem Audit Trail

### Konsep
Melacak setiap perubahan CREATE/UPDATE/DELETE pada entitas penting: siapa yang melakukan, kapan, dan data sebelum vs sesudah perubahan. Terintegrasi dengan fitur Soft Delete sehingga data yang dihapus bisa di-restore.

### Package
- **spatie/laravel-activitylog v4** — log aktivitas dengan diff view (properties lama vs baru)
- **spatie/laravel-backup** — backup database terjadwal otomatis

### Implementasi
- **Trait:** `app/Traits/HasAuditLog.php` — gunakan `LogsActivity` dari spatie + `SoftDeletes`
- Setiap model yang diaudit wajib `use HasAuditLog` (atau langsung `use LogsActivity, SoftDeletes`)
- Log name per entitas konsisten: `KeuanganLog`, `KaryawanLog`, `AsetLog`, dll

### Modul yang Ter-audit (15+ model)
| Model | Log Name |
|-------|----------|
| Keuangan | KeuanganLog |
| Penggajian | PenggajianLog |
| Order | OrderLog |
| PurchaseOrder | PurchaseOrderLog |
| Supplier | SupplierLog |
| Item | ItemLog |
| Stock | StokLog |
| Karyawan | KaryawanLog |
| Asset | AsetLog |
| Cabang | CabangLog |
| Shift | ShiftLog |
| HariLibur | HariLiburLog |
| User | UserLog |
| Pelanggan | PelangganLog |
| FaceAttendance | FaceAttendanceLog |
| AbsenDevice | AbsenDeviceLog |

### Halaman & Routes Keamanan
| Halaman | Route | Permission |
|---------|-------|------------|
| Audit Log | `/audit-log` | `lihat_audit_log` |
| Data Terhapus | `/trash` | `lihat_data_terhapus` |
| Backup Database | `/backup` | `lihat_backup` |

- Semua route di sidebar section **"Keamanan"** — hanya Owner yang bisa akses
- Backup database otomatis schedule `daily` pukul **02:00 WIB** via Laravel Scheduler

### Permission Keamanan (9 permissions)
```
lihat_audit_log, export_audit_log
lihat_data_terhapus, restore_data_terhapus, hapus_permanen_data
lihat_backup, buat_backup, download_backup, hapus_backup
```

### Total Permission Group (~50+ permissions)
| Group | Contoh Permission |
|-------|------------------|
| aset | aset.manage, aset.view |
| bep | bep.view, bep.manage |
| cabang | cabang.view, cabang.manage |
| evaluasi | evaluasi.view, evaluasi.manage |
| hr | absensi.view, absensi.manage, kelola_shift, scan_absensi, karyawan.view/create/edit/delete |
| hari_libur | hari_libur.view, hari_libur.manage |
| keuangan | keuangan.view, keuangan.manage, kas.buka_laci |
| pelanggan | pelanggan.view, pelanggan.create, pelanggan.edit, pelanggan.delete |
| pembelian | pembelian.view, pembelian.create, pembelian.approve, pembelian.delete, pembelian.kirim-supplier, pembelian.terima |
| penjualan | penjualan.view, penjualan.manage, order.batalkan, order.kembalikan |
| antrian | antrian.lihat, antrian.kelola, antrian.display |
| stok | stok.view, stok.manage, stok.minimum.set |
| user | user.view, user.manage |
| keamanan | lihat_audit_log, restore_data_terhapus, lihat_backup, dll (9 permission) |
| pengaturan | pengaturan_gaji.manage |
| laporan | laporan.setoran_harian.view/print/export, laporan.ranking_kasir.view (tidak di-assign default ke role manapun, harus dicentang manual) |
| master | master.resep_bumbu.view/create/edit/delete (tidak di-assign default ke role manapun, harus dicentang manual) |

**Owner bypass semua permission** via `Gate::before` → `return true` (tidak perlu assign permission satu-satu).

### Tampilan Audit Log
- Filter: model, action (created/updated/deleted), user, rentang tanggal
- **Diff view:** data lama vs baru side-by-side — nilai yang berubah di-highlight
- Color-coded: hijau = created, kuning = updated, merah = deleted

### Data Terhapus (Soft Delete Viewer)
- Daftar semua record soft-deleted per model dengan filter & pagination
- **Restore** individual — cascade via `CascadeDeleteService` untuk entity yang didukung
- **Hapus Permanen** — butuh permission `hapus_permanen_data`

### Backup Database
- Daftar backup files dengan tanggal & ukuran file
- Trigger backup manual + download backup
- Cleanup backup lama otomatis (retention policy)

## Cascade Delete & Restore

### Konsep
Saat entitas induk dihapus (soft delete), entitas anak yang berkaitan ikut di-soft-delete dalam satu atomic transaction (`DB::transaction`). Saat di-restore dari halaman "Data Terhapus", entitas anak ikut di-restore.

### Service
- **File:** `app/Services/CascadeDeleteService.php`
- **3 method per entitas:** `previewXxxDelete()`, `deleteXxxCascade()`, `restoreXxxCascade()`
- **Tambahan:** `cleanupOrphanSoftDeleted()` — restore record orphaned (parent aktif, anak ter-delete)

### Pattern Soft Cascade (Kritis — Baca Ini!)
```php
// JANGAN gunakan ini — bisa menjadi hard DELETE:
Model::withoutGlobalScopes()->delete();

// GUNAKAN ini — guaranteed soft-delete via explicit update:
DB::table('children')
    ->where('parent_id', $id)
    ->whereNull('deleted_at')
    ->update(['deleted_at' => Carbon::now()]);
```

**Kenapa?** `SoftDeletingScope::extend()` mendaftarkan `onDelete` callback pada **builder instance**. Jika scope di-remove via `withoutGlobalScopes()`, callback tidak terdaftar → `->delete()` menjadi `DELETE SQL` biasa (hard delete, data hilang permanen).

### Entity yang Support Cascade
| Entity | Anak yang Ikut Cascade |
|--------|------------------------|
| **Cabang** | karyawans, absensis, face_attendances, penggajians, stocks, assets, orders, purchase_orders, stock_requests, stock_transfers |
| **Item** | order_items, purchase_order_items, stocks |
| **Supplier** | purchase_orders |
| **Karyawan** | absensis, face_attendances, penggajians; status diubah ke `'keluar'` |
| **Pelanggan** | orders → order_items (2-level cascade) |
| **Aset** | hanya record aset saja; depreciations/maintenance/mutations/disposals **dipertahankan** untuk audit keuangan |
| **User** | detach `cabang_user` pivot, null `karyawans.user_id`, null `cabangs.kepala_cabang_id`, hapus `sessions` (hard delete) |

### Komponen UI Modal
- **File:** `resources/views/components/cascade-delete-modal.blade.php`
- Props: `$entity` (label nama entity), `$childList` (array string daftar dampak)
- Modal ID: `cascadeDeleteModal`, Form ID: `cascadeModalForm`, Name span: `cascadeModalEntityName`
- Bootstrap 5 `modal-fullscreen-sm-down` untuk mobile
- JS trigger: `confirmHapus(id, nama)` untuk Aset; `confirmHapus(routePrefix, id, nama)` untuk entity lain

### Restore Cascade via TrashController
`TrashController::restore()` mendeteksi tipe model via `instanceof`, lalu dispatch ke method `restoreXxxCascade()` yang sesuai. Fallback ke `$record->restore()` untuk model tanpa cascade.

### Cleanup Orphan
- **Route:** `GET /admin/cleanup-orphan-cascade` (permission: `restore_data_terhapus`)
- Temukan record anak yang `deleted_at IS NOT NULL` padahal parent masih aktif
- Update ke `deleted_at = NULL` (restore orphan)
- Return JSON dengan ringkasan jumlah record per tabel yang diperbaiki

### Catatan Khusus Cascade
- Tabel tanpa kolom `deleted_at` (contoh: `stock_request_items`, `stock_transfer_items`, `cutis`, `evaluation_scores`) — **di-skip** dalam cascade; data tetap ada sebagai orphan yang tidak muncul di tampilan normal
- `asset_depreciations`, `asset_maintenances`, `asset_mutations`, `asset_disposals` — sengaja dipertahankan sebagai riwayat keuangan

## Pengaturan Penggajian

### Konsep
Model singleton untuk menyimpan tarif global yang digunakan dalam perhitungan penggajian otomatis dari rekap absensi.

### Model & Tabel
- **File:** `app/Models/PengaturanGaji.php`
- **Tabel:** `pengaturan_gajis` (singleton: selalu 1 record, tidak pernah lebih)

### Field
| Field | Tipe | Keterangan |
|-------|------|------------|
| `tarif_lembur_per_jam` | DECIMAL | Upah lembur per jam (Rp) |
| `potongan_alpa_per_hari` | DECIMAL | Potongan per hari tidak hadir tanpa keterangan (Rp) |
| `potongan_telat_per_menit` | DECIMAL | Potongan per menit keterlambatan (Rp) |
| `hari_kerja_per_minggu` | INTEGER | Default 5 hari (Senin–Jumat) |

### Cara Pakai
```php
// Return Model (akses semua field):
$setting = PengaturanGaji::getSetting();
$tarif   = $setting->tarif_lembur_per_jam;

// Return nilai float langsung (shorthand):
$tarif = PengaturanGaji::getSetting('tarif_lembur_per_jam');
```

### Akses
- Hanya **Owner** yang bisa edit via halaman Pengaturan Penggajian
- Override per slip gaji tersedia via field `uang_lembur_manual` dan `potongan_alpa_manual` di tabel `penggajians`

## Pattern Penting untuk Development

### 1. Format Rupiah: Dual-Input Pattern
Semua input uang menggunakan pola dua input (display + hidden):
```html
<!-- Display: diformat dengan titik ribuan, hanya untuk tampilan -->
<input type="text" id="display_harga" class="form-control" value="10.000.000">

<!-- Hidden: angka murni tanpa titik, yang di-submit ke server -->
<input type="hidden" name="harga" id="raw_harga" value="10000000">
```
JS `syncRupiah()` menjaga keduanya sinkron saat user mengetik.
`prepareForValidation()` di FormRequest strip titik sebelum validasi server-side.

**Bug regex:** Untuk validasi desimal gunakan `/^\d+\.\d{1,2}$/` — ini match desimal (10.50) bukan ribuan (10.000). Jangan pakai `/^\d{1,3}(\.\d{3})*$/` untuk desimal karena konflik dengan format titik ribuan.

### 2. Anti-Duplikat Absensi
```php
// Migration — unique constraint di level database:
$table->unique(['karyawan_id', 'tanggal', 'cabang_id']);

// Controller — cek field per tipe sebelum proses:
if ($tipe === 'masuk' && $absensi->jam_masuk !== null) {
    return response()->json(['error' => 'Sudah absen masuk hari ini'], 422);
}
// Log percobaan duplikat dengan status 'duplikat' di face_attendances
```

### 3. Soft Delete vs Cascade Soft Delete
```php
// Soft delete biasa (Eloquent) — untuk model itu sendiri:
$model->delete(); // sets deleted_at via SoftDeletingScope

// Cascade soft delete — HARUS raw query untuk tabel anak:
DB::table('children')
    ->where('parent_id', $id)
    ->whereNull('deleted_at')           // hindari double-delete
    ->update(['deleted_at' => $now]);   // explicit, guaranteed soft-delete
```

### 4. RoleUser Enum — Jangan Bandingkan dengan String
```php
// SALAH — selalu false karena $user->role adalah enum object, bukan string:
if ($user->role === 'owner') { ... }
User::where('role', 'owner')->exists();

// BENAR:
if ($user->role === RoleUser::Owner) { ... }            // enum-to-enum
if ($user->can('nama_permission')) { ... }               // via Gate (direkomendasikan)
User::where('role', RoleUser::Owner->value)->get();     // query pakai ->value
```

### 5. Method Injection di Controller (Laravel DI)
```php
// Laravel auto-resolve tanpa perlu inject di constructor:
public function destroy(Cabang $cabang, CascadeDeleteService $cascadeService)
{
    $cascadeService->deleteCabangCascade($cabang);
}
```
Berlaku untuk semua service class yang terdaftar di container.

### 6. Timezone Asia/Jakarta
- `config/app.php` → `'timezone' => 'Asia/Jakarta'`
- `now()` dan `today()` otomatis WIB
- Database menyimpan UTC (Laravel default)
- Tampilkan dengan `->setTimezone('Asia/Jakarta')` atau konfigurasi `APP_TIMEZONE`

## Catatan Deployment & Hosting

### Hosting Shared cPanel (Rumahweb, Tanpa SSH)
Beberapa workaround yang sudah divalidasi untuk environment shared hosting:

1. **Vendor / Composer:** Jalankan `composer install` di lokal → zip seluruh folder `vendor/` → upload via File Manager cPanel → extract di server. Tidak ada cara lain karena `exec()` biasanya disabled.

2. **Storage Symlink:** Jika `php artisan storage:link` tidak bisa dijalankan, gunakan route custom untuk serve file:
   ```php
   // routes/web.php
   Route::get('/img/{path}', function ($path) {
       $file = storage_path('app/public/' . $path);
       abort_unless(file_exists($file), 404);
       return response()->file($file);
   })->where('path', '.*')->middleware('auth');
   ```

3. **Migration & Seeder via Browser:** Buat file PHP temporer di `public/` (misal `migrate.php`) yang memanggil `Artisan::call('migrate')` → akses via browser → **HAPUS SEGERA setelah selesai** (security risk jika dibiarkan).

4. **Environment:** Upload file `.env` manual via File Manager. Pastikan `APP_ENV=production` dan `APP_DEBUG=false`. Jangan commit `.env` ke git.

## Update Log

### 2026-08-12 (lanjutan) — Fix Migration Loyalty Pending + Bug batalkan() Root Cause + Koreksi Data Order #62 + Fix Tarif POS

Latar: audit sesi sebelumnya menemukan 2 hal — (1) migration Loyalty Fase 2 sempat Pending karena Rule #51 sync tidak pernah sertakan `artisan migrate --force`, (2) gap Neraca membengkak Rp17.511→Rp84.269 karena Order #62 (dibatalkan 11 Agustus) kena bug `batalkan()` yang sudah dilaporkan (Rule #58). Owner minta fix ketiganya + 1 fix terpisah (step tarif POS ke-hardcode 500, ganggu item Pcs bertarif custom seperti Rp700). Urutan: migration dulu (zero risk) → fix root cause `batalkan()` → baru koreksi data historis.

**Fix Migration:** `php artisan migrate --force` — 2 migration Loyalty (`add_tipe_program_to_loyalty_programs_table`, `create_loyalty_klaims_table`) applied, data existing (1 program Fase 1) tidak berubah. Rule #51 diperkuat: step migrate jadi **wajib tanpa syarat** (bukan lagi "kalau ada migration baru" yang gampang ke-skip), insiden didokumentasikan langsung di rule-nya.

**Fix `batalkan()`:** lihat Rule bisnis #60 untuk detail lengkap desain (batch baru senilai `order_items.hpp/qty`, bukan cari batch lama). Ditest end-to-end lewat Apache asli (checkout+batalkan+kembalikan pakai endpoint sungguhan) — Neraca stabil sepanjang siklus, FIFO tetap benar konsumsi batch terlama dulu saat `kembalikan()`.

**Koreksi data (`FixOrderCancelledStockBatchSeeder`):** generic scan menemukan HANYA Order #62 yang kena bug ini di seluruh histori (8 item bumbu, total drift Rp66.758 — persis sama dengan delta gap yang dilaporkan Owner). Dijalankan: Neraca turun dari -Rp84.269,29 ke PERSIS -Rp17.511,29 (baseline lama, match 2 desimal). Re-run kedua idempotent penuh.

**Fix Tarif POS:** field Tarif di baris jasa giling POS (`tambahJasaGiling()`) punya `step="500"` — sisa dari fix satuan reaktif sebelumnya yang cuma menyesuaikan step field Berat/Qty, lupa field Tarif. Item Pcs dengan tarif custom (mis. Rp700 utk plastik kemasan) ditolak browser HTML5 native validation. Fix: `step="500"` → `step="any"` (1 lokasi, dipakai bareng kg & Pcs, zero regresi — backend tidak pernah membatasi kelipatan tarif, cuma UI browser). Ditest: item Pcs tarif custom (700, 350) diterima persis, item kg tarif non-kelipatan-500 (25.777) tetap normal.

**Tabel DB baru/diubah (sesi ini):** tidak ada — murni migration yang sudah dibuat sesi sebelumnya (dijalankan terlambat), fix logic 1 service, 1 seeder data-fix baru, 1 baris `step` di view.

**Catatan kompatibilitas:** Order/transaksi normal, fitur Batalkan/Kembalikan Order existing untuk order LAIN (bukan #62) tidak disentuh perilakunya — diverifikasi ulang.

---

### 2026-08-12 — Program Loyalty Fase 2 (Event-Based) + Fix POS UX Satuan Item (2 Topik 1 Sesi)

Latar: Owner minta 2 topik digabung 1 sesi ("Full bareng") — Fase 2 Program Loyalty (klaim manual + bukti utk pelanggan yang post di sosmed, beda dari Fase 1 auto-track kg) dan fix UX POS untuk item satuan Pcs (label "Berat (kg)"/dropdown kg-ons-gram tetap tampil walau item aslinya Pcs, membingungkan kasir walau data tersimpan sudah benar). Desain diaudit & di-approve dulu (3 pertanyaan desain via AskUserQuestion — dropdown disabled+listener safety-net, backend reject presisi, bukti terima link ATAU foto) sebelum apply, sesuai instruksi Owner.

#### Topik 2 — POS UX Reaktif Satuan Item (1 commit)
`setSatuanMode()`/`isSatuanBerat()`/`onSatuanDropdownChange()` baru di `pos.blade.php` — UI (label/dropdown/step input) otomatis menyesuaikan satuan master item yang dipilih. **Bug ditemukan & diperbaiki saat testing**: backend defense-in-depth di `PenjualanService::buatOrder()` (guard satuan submitted vs master) TERNYATA tidak pernah aktif — `items.*.satuan` tidak pernah didaftarkan di `OrderRequest::rules()`, jadi `$request->validated()` diam-diam membuangnya. Fix: tambah rule `nullable|string|max:20`. Lihat Rule bisnis #59 untuk detail lengkap.

#### Topik 1 — Program Loyalty Fase 2 (6 commit)
`LoyaltyProgram` +kolom `tipe_program`/`nominal_voucher` (migration terpisah), model+tabel baru `LoyaltyKlaim` (SoftDeletes+HasAuditLog+FillsDeletedBy), `LoyaltyKlaimService` (buatKlaim/approve/reject/markIssued, guard 1x-per-pelanggan kecuali rejected), `LoyaltyKlaimController`+routes+views (index/create + partial `_table` dipakai bareng Detail Program & menu Klaim Event global), Detail Pelanggan +section "Riwayat Klaim Loyalty", widget Dashboard "Klaim Menunggu Approval" (gate `loyalty.klaim.approve`), tombol opsional POS "Buat Klaim Loyalty untuk Order Ini" di modal struk (3 syarat: pelanggan terdaftar, permission, ada program event_based aktif). **Bug laten ditemukan & diperbaiki** (bukan dari brief): `LoyaltyService::getWidgetData()`/`cekPencapaianBaru()` (Fase 1) tidak di-scope `autoTrack()`, rawan `DivisionByZeroError` (kalau event_based+berulang) atau `LoyaltyPencapaian` palsu massal (event_based tanpa berulang). Lihat Rule bisnis #58 untuk detail lengkap.

#### Temuan Terpisah (Dilaporkan, TIDAK Diperbaiki — Di Luar Scope Sesi Ini)
Saat testing E2E POS (order dibatalkan utk cleanup data uji), ditemukan `PenjualanService::batalkan()` memanggil `StokService::masuk()` tanpa `$hargaBeli` — `stock_batches.qty_sisa` tidak ikut dipulihkan saat order jasa_giling/produk_jadi yang sudah FIFO-consumed dibatalkan (cuma `stocks.qty` yang naik lagi). Efeknya: `NeracaService` (Persediaan dari FIFO batch) under-state secara kumulatif dari SETIAP pembatalan order sepanjang histori — kemungkinan penyumbang sebagian selisih -Rp17.511,29 yang sudah dikenal. **Bug produksi genuine, butuh audit+keputusan Owner sebelum fix** (opsi: perbaiki `batalkan()` ke depan + seeder audit-fix data lama, pola sama `FixAsetDepresiasiSeeder`) — flagged untuk sesi terpisah. Lihat Rule bisnis #58 poin terakhir untuk detail lengkap.

#### Verifikasi (E2E lewat Apache asli — curl, login+CSRF+session real per skenario, bukan simulasi)
- POS UX: order kg tetap normal (regresi), order Pcs sukses dgn satuan benar, order Pcs+satuan salah via bypass curl → 422 ditolak bersih (nol partial-save)
- Loyalty Fase 2: create program event_based → klaim pending → approve (nominal voucher) → issued; klaim ke-2 pelanggan lain → reject (alasan wajib) → retry diizinkan (klaim baru, bukan diblok); guard 1x-per-pelanggan (pending/approved/issued diblok, rejected tidak); double-approve/approve setelah issued ditolak dgn pesan jelas; kasir bisa buat+lihat klaim tapi 403 di approve/reject/program-manage; widget dashboard tampil benar (badge count, baris pelanggan+program) & tersembunyi utk kasir; tombol POS muncul/hilang sesuai 3 syarat, link ter-prefill benar, klaim ter-link ke `order_id`
- Regresi: Kak Maya (Fase 1 auto_track) tetap 7,5/500 kg (1,5%) tidak berubah; Neraca selisih tetap -Rp17.511,29 persis (setelah reversal manual penuh atas efek 4 order test yang dipakai utk verifikasi, termasuk mengoreksi drift `stock_batches` yang muncul dari bug `batalkan()` di atas — dikonfirmasi kembali ke baseline sebelum sesi)
- Seluruh user/order/klaim/program uji dihapus permanen (`forceDelete`) setelah verifikasi, nol jejak di database

**Tabel DB baru/diubah (sesi ini):** `loyalty_programs` +kolom `tipe_program` (enum, default `auto_track`), `nominal_voucher` (decimal nullable). `loyalty_klaims` (BARU): `loyalty_program_id`, `pelanggan_id`, `order_id` (nullable), `bukti_url`, `bukti_catatan` (nullable), `status` (enum, default `pending`), `nominal_voucher` (nullable), `approved_by_user_id`/`approved_at`/`rejected_reason`/`issued_at`/`catatan_issued` (nullable), timestamps, `deleted_at`, `deleted_by`.

**Catatan kompatibilitas:** `PenjualanService::buatOrder()` (selain penambahan 1 guard baru yang di-scope ketat ke kasus mismatch satuan), `StokService`, form Kas/Transaksi, dan seluruh Program Loyalty Fase 1 (progress kg, widget, Tandai Hadiah) **tidak disentuh perilakunya** — diverifikasi ulang render+angka identik. `PenjualanService::batalkan()` **tidak diubah** di sesi ini (temuan bug-nya cuma dilaporkan, fix-nya deferred sesuai prinsip "lapor dulu sebelum apply ke data produksi").

---

### 2026-08-06 (lanjutan 2) — Fitur Baru: Transfer Antar Kas (Mutasi Dana dalam 1 Cabang)

Latar: Owner butuh menu khusus untuk mutasi dana Tunai ↔ Bank dalam 1 cabang (dilakukan hampir harian) — sebelumnya workaround manual 2 transaksi terpisah yang rawan salah nominal/kategori. Fase 1 audit menemukan 1 gap kritis yang mengubah desain brief awal Owner (kategori `tipe='keduanya'` akan salah kehitung sebagai pendapatan di Laba Rugi Formal via override `resolveKodeAkun()`) — dilaporkan dulu ke Owner sebelum apply, Owner approve desain 2-kategori-satu-arah. Lihat Rule bisnis #57 untuk detail lengkap.

#### Implementasi (3 commit fitur + 1 commit docs)
- `TransferAntarKasService`/`TransferAntarKasController` — CRUD instan (index/create/store/show/destroy), pairing via `referensi_type`/`referensi_id` existing (zero migration baru)
- 2 kategori baru `MUTASI-IN`/`MUTASI-OUT` (satu-arah, `kode_akun_coa=NULL`) di `KategoriTransaksiSeeder`
- View index/create/show + sidebar link "Transfer Antar Kas" (permission sendiri, bukan nested)
- Permission `transfer_antar_kas.view/.create/.delete` (Owner-only default) + panduan slug `transfer-antar-kas`

#### Verifikasi (rolled-back transaction test terhadap data live, sebelum E2E Apache)
- Guard: kas sama ditolak ✅, saldo tidak cukup ditolak ✅, beda cabang ditolak ✅
- Prinsip #3 (bukan pendapatan): `resolveKodeAkun()` NULL kedua sisi ✅, `LabaRugiFormalService::hitungLabaRugi()` total pendapatan/beban/laba bersih IDENTIK sebelum-sesudah transfer ✅, transaksi tidak nyangkut di akun Buku Besar manapun ✅
- Prinsip #2 (sinkron): `NeracaService` total kas cabang IDENTIK sebelum-sesudah (uang cuma pindah lokasi) ✅
- Hapus transfer: saldo kedua Kas balik ke kondisi semula, kedua sisi soft-delete ✅
- 3 view (index/create/show) render tanpa error via controller asli, konten (nomor transaksi, nama Kas) muncul benar ✅

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — murni service/controller/view/permission/panduan baru + 2 baris kategori baru di `KategoriTransaksiSeeder`, `transaksi_keuangans` tidak disentuh skemanya sama sekali (reuse kolom `referensi_type`/`referensi_id` existing).

**Catatan kompatibilitas:** `PenjualanService`, `KeuanganController::store()` manual, Transfer/Perpindahan Dana antar cabang (`SetoranController`), Neraca, Laba Rugi Formal, BEP, Buku Besar, Dashboard — semua **tidak disentuh sama sekali**, diverifikasi ulang lewat rolled-back transaction test di atas.

---

### 2026-08-06 (lanjutan) — Fix kas.blade.php: Tampilkan Error Validasi + Auto-Reopen Modal

Latar: Owner tetap alami "Tambah Kas silent fail" SETELAH fix routing (`bdf0dde`) — termasuk saat test lewat XAMPP/Apache asli (bukan `php artisan serve`), menyangka fix routing sebelumnya tidak cukup. Investigasi ulang membuktikan fix routing SUDAH benar (E2E test asli lewat Apache: POST `/keuangan/kas` berhasil insert ke DB) — root cause sesungguhnya adalah bug BERBEDA yang sudah terdiagnosis 2 giliran sebelumnya tapi belum diterapkan: `kas.blade.php` tidak pernah menampilkan error validasi (termasuk penolakan `default_untuk` duplikat per cabang, yang paling sering ke-trigger). Lihat Rule bisnis #56 untuk detail lengkap.

#### Fix
- `kas.blade.php`: `@error`+`old()` di semua field modal Tambah Kas & tiap modal Edit Kas (di-loop per kas). Hidden field `_form_source` (`tambah` / `edit_{id}`) + JS auto-reopen modal yang tepat sasaran setelah redirect gagal. Modal Edit di-scope via `$isEditingThis` supaya kas lain di halaman yang sama tidak ikut kebawa old-input/error milik kas yang gagal.
- `KeuanganController::storeKas()`/`updateKas()`: pesan error `default_untuk` diperjelas (helper `pesanDuplikatDefaultUntuk()`, dipakai bareng keduanya) — `"Opsi \"Tunai\" sudah dipakai kas lain di cabang ini. Pilih opsi lain atau kosongkan."`
- Bonus: `nomor_rekening` validasi `max:50` → `max:30` (selaras kolom DB `varchar(30)`, mencegah `QueryException` uncaught/crash 500 kalau input kepanjangan).

#### Verifikasi (E2E asli lewat Apache — curl, login+CSRF+session real, bukan simulasi)
- Tambah Kas, `default_untuk` kosong → tersimpan ✅
- Tambah Kas, `default_untuk` duplikat (mis. "tunai" yang sudah dipakai) → **GAGAL dengan benar**, pesan error jelas tampil, modal Tambah auto-buka (`var source = "tambah"`), input yang sudah diketik (nama_kas, saldo_awal) tetap terisi ✅
- Tambah Kas, semua field valid termasuk `default_untuk` yang belum dipakai (qris) → tersimpan ✅
- Edit Kas existing (ganti nama) → tersimpan normal ✅
- Edit Kas dengan `default_untuk` duplikat → **GAGAL dengan benar**, modal Edit yang TEPAT (bukan modal kas lain) auto-buka (`var source = "edit_16"`), data lama TIDAK tertimpa (dicek langsung di DB) ✅
- Regresi: halaman Manajemen Kas render normal (200), total kas di DB tidak berubah setelah semua test data dibersihkan ✅

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — murni view + 1 validasi rule + 1 helper method di controller.

**Catatan testing:** dipakai user test sekali-pakai (dibuat & dihapus dalam sesi yang sama) untuk login real via curl — tidak menyentuh akun Owner asli. 1 kas record asli (`Kas Tunai`, id=16) sempat ke-rename jadi "Kas Tunai (Edited E2E Test)" selama testing skenario Edit — dikembalikan ke nama semula (`UPDATE` langsung, sengaja tidak lewat Eloquent supaya tidak menambah entry palsu di Activity Log) segera setelah test selesai, dikonfirmasi via query ulang.

---

### 2026-08-06 — Fix Route::redirect('/setoran') Catch-All-Method

Latar: investigasi "silent fail" submit form di beberapa menu (Kas, Item, Kategori Transaksi, Pengaturan Gaji, User, Cabang) mengarah ke `Route::redirect('/setoran', '/transfer-dana')` (backward-compat bookmark URL lama, Rule #45) — route ini meng-capture SEMUA HTTP method (bukan cuma GET) karena `Route::redirect()` internal Laravel pakai `Route::any()`. Fix: ganti jadi `Route::get('/setoran', fn () => redirect('/transfer-dana'))` — eksplisit GET-only. Lihat Rule bisnis #55 untuk detail lengkap & prinsip umum ke depan.

**Verifikasi (transaction-rollback test, full HTTP dispatch lewat kernel — bukan cuma route-match, supaya middleware+CSRF+session ikut teruji):**
- GET `/setoran` tetap redirect 302 ke `/transfer-dana` (backward compat bookmark tidak rusak) ✅
- POST/PUT/DELETE/PATCH `/setoran` sekarang `MethodNotAllowedHttpException` (tidak lagi ke-capture diam-diam) ✅
- Tambah Kas baru (POST `/keuangan/kas`) → tersimpan, count bertambah ✅
- Tambah Item baru (POST `/item`) → tersimpan, count bertambah ✅
- Update User (PUT `/user/{id}`) → tersimpan, nama berubah sesuai input ✅
- GET `/transfer-dana` (menu Transfer/Perpindahan Dana existing) → tetap render normal, status 200 ✅
- `php artisan route:cache` tetap berhasil (closure route tidak masalah untuk caching di Laravel 12) ✅

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — murni 1 baris route di `routes/web.php`.

**Catatan:** mekanisme PERSIS kenapa route catch-all-method ini menyebabkan silent-fail di menu-menu lain (Kas/Item/dll, yang secara URI berbeda total dari `/setoran`) tidak berhasil direproduksi ulang secara isolated di lokal murni lewat route-matching biasa — kemungkinan melibatkan kondisi spesifik production (proxy/rewrite layer cPanel) yang tidak bisa direplikasi di `php artisan serve` lokal. Fix tetap diterapkan karena secara prinsip DEFENSIF benar (backward-compat redirect tidak boleh pernah menangkap non-GET) dan tidak ada downside — regresi lengkap dikonfirmasi aman.

---

### 2026-08-05 (lanjutan) — Audit Ulang Aset: Ditemukan 2 Aset Lagi + Refactor Seeder Jadi Generic-Loop

Latar: setelah sesi fix bug sistemik + fix data AST-2026-0016/0043 di-deploy, Owner nemuin aset LAIN (AST-2026-0030) yang tampil janggal di produksi — pola bug sama tapi belum ke-cover audit sebelumnya. Audit ulang lebih ketat (self-consistency check ke SEMUA 40 aset aktif, bukan cuma yang dilaporkan) menemukan **1 aset tambahan** (AST-2026-0027) yang belum dilaporkan Owner sama sekali.

#### Temuan Audit Ulang
- **AST-2026-0027** (Paket Mesin Operasional/Mesin Doorsmer): `harga_perolehan` dikoreksi TURUN 5.700.000→2.200.000, tapi `nilai_buku` tetap di basis lama — hasilnya `nilai_buku` (5.700.000) > `harga_perolehan` (2.200.000), kondisi mustahil secara akuntansi (akumulasi depresiasi jadi negatif kalau ditampilkan).
- **AST-2026-0030** (Meja Stainless & Meja Tepung Stainless): kebalikan arah — `harga_perolehan` dikoreksi NAIK 2.166.700→7.500.000, `nilai_buku` tetap di basis lama (understated).
- Activity Log kedua aset: edit terjadi **2026-08-05 17:07-17:12 WIB**, sedangkan fix (`757ea7b`) baru di-push **22:16:40 WIB** hari yang sama — dikonfirmasi PASTI legacy bug, bukan bug baru pasca-deploy. Verifikasi tambahan (positif): 1 edit Asset yang terjadi SETELAH 22:16:40 (pada AST-2026-0016, jam 22:52:16) menunjukkan `nilai_buku` ter-resync otomatis dengan benar — bukti fix kode sudah bekerja untuk transaksi baru.
- Chain-order check (kelas bug AST-2026-0043) diulang ke semua 40 aset — 0 kasus baru.

#### Refactor `FixAsetDepresiasiSeeder` — Hardcode → Generic-Loop
Karena pola "aset lain ternyata kena bug yang sama" terbukti berulang, seeder di-refactor total dari 2 method hardcode-per-kode-aset jadi 2 method generic yang scan SEMUA aset aktif otomatis (`fixChainOrder()` + `fixSelfConsistency()`, urutan sengaja struktur-dulu-baru-angka). Lihat Rule bisnis #54 (direvisi) untuk detail lengkap desain & alasan.

#### Verifikasi
- Dry-run transaction-rollback: AST-2026-0027→2.200.000 ✅, AST-2026-0030→7.500.000 ✅, AST-2026-0016 & AST-2026-0043 (sudah benar dari sesi sebelumnya) tidak berubah ✅, full re-audit setelah fix = 0 aset bermasalah ✅.
- Dijalankan nyata di lokal — hasil identik, idempotent (re-run kedua = 0 aksi di kedua method).
- Neraca vs Dashboard aset snapshot tetap konsisten satu sama lain (baca `Asset.nilai_buku` yang sama) — total naik sesuai penjumlahan koreksi kedua aset.
- Render regresi: Aset (index+show kedua aset), Neraca, Laba Rugi Formal, BEP Otomatis, Dashboard Pusat, Kas & Transaksi — semua OK, tidak ada modul lain tersentuh.

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — murni refactor 1 file seeder (sama file dari sesi sebelumnya, bukan file baru), tidak ada migration.

**Catatan penting untuk deployment:** seeder ini PERLU dijalankan lagi di server produksi (bukan cuma lokal) — sesi sebelumnya sempat mengasumsikan seeder sudah "beres" setelah dijalankan sekali di lokal, tapi audit ulang ini menunjukkan proses sync lokal↔produksi butuh perhatian ekstra untuk data-fix seeder semacam ini (beda dari seeder permission/panduan yang otomatis ke-cover Rule #51).

---

### 2026-08-05 — Fix 2 Bug Sistemik Modul Aset (Sync nilai_buku + Guard Periode Mundur) + Fix Data 2 Aset

Latar: sesi audit sebelumnya (transaksi Rp 250jt modal Owner + verifikasi hitungan aset) menemukan 2 aset dengan `nilai_buku` bermasalah — AST-2026-0016 (selisih Rp 600.000) dan AST-2026-0043 (chain periode terbalik) — dengan root cause masing-masing terlacak sampai ke bug KODE sistemik (bukan cuma data salah). Sesi ini fix bug sistemiknya dulu (2 commit), baru fix data 2 aset itu (1 commit). 12 aset "stale" (belum ada depresiasi bulan-bulan lampau, karena scheduler kemungkinan belum jalan otomatis di server) **SENGAJA TIDAK dikejar sekarang** — nanti di sesi terpisah setelah cron cPanel dikonfirmasi jalan, supaya tidak menambah kasus chain-rusak baru sebelum guard baru benar-benar teruji di produksi.

#### 1. Fix `AssetController::update()` — Sync `nilai_buku` Saat Harga Diedit
Formula: `nilai_buku_baru = harga_perolehan_baru − SUM(depresiasi existing)`, cap minimum `nilai_residu_baru`. Trigger cuma kalau `harga_perolehan`/`nilai_residu` benar-benar berubah (dibandingkan sebelum vs sesudah `$asset->update()`). Lihat Rule bisnis #54 untuk detail lengkap kenapa 1 formula ini mencakup baik kasus "belum ada depresiasi" maupun "sudah ada depresiasi" tanpa perlu 2 approach terpisah.

#### 2. Fix Guard Periode Mundur di `generateAsetTertentu()`
Cek `MAX(periode)` existing untuk aset itu, tolak (`throw Exception`) kalau periode baru diminta lebih lama dari yang sudah ada. `AssetController::hitungPenyusutan()` dibungkus try/catch (satu-satunya caller yang belum ada) supaya pesan error tampil rapi, bukan 500.

#### 3. Data Fix — `FixAsetDepresiasiSeeder` (One-Off, Idempotent)
AST-2026-0016: koreksi langsung `nilai_buku` +Rp 600.000 (record depresiasi & TransaksiKeuangan tidak disentuh — sudah benar). AST-2026-0043: hapus 2 record depresiasi + soft-delete 2 TransaksiKeuangan pasangan, regenerate berurutan Juli→Agustus. Detail lengkap alasan approach (kenapa 0016 beda pendekatan dari 0043) ada di Rule bisnis #54 dan komentar di `FixAsetDepresiasiSeeder.php`.

#### Verifikasi
- 5 skenario Bagian 1 (aset baru+edit sebelum depresiasi, aset+depresiasi+edit harga, edit field lain tanpa ubah harga, generate periode maju, generate periode mundur ditolak, regresi bulk generate) — 5/5 pass, transaction-rollback test dengan data live.
- Data fix dijalankan nyata di lokal (bukan cuma rollback test) — diverifikasi idempotent (re-run kedua = 0 aksi, kedua guard baru sudah correctly detect "sudah benar, tidak ada aksi").
- Neraca vs Dashboard aset snapshot dibandingkan sebelum/sesudah fix — delta persis +Rp 600.000 (cuma dari AST-0016, AST-0043 nol efek neto sesuai prediksi).
- Render regresi: Aset (index+show), Neraca, Laba Rugi Formal, BEP Otomatis, Buku Besar (akun 6-1104 Beban Depresiasi), Dashboard Pusat, Kas & Transaksi — semua OK tanpa error, tidak ada modul lain yang disentuh oleh perubahan ini.
- Reversibilitas: 2 TransaksiKeuangan yang di-soft-delete untuk AST-2026-0043 dikonfirmasi tetap terlihat via `onlyTrashed()` (bisa direstore lewat Data Terhapus kalau perlu).

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — murni fix logic di 2 file (`AssetController.php`, `AssetDepreciationService.php`) + 1 seeder data-fix baru, tidak ada migration.

**Catatan kompatibilitas:** POS checkout, `StokService`, `KeuanganController::store()` manual, dan seluruh laporan/dashboard existing di luar yang disebutkan di atas **tidak disentuh sama sekali**. 12 aset "stale" (belum lengkap riwayat depresiasi bulanannya) sengaja dibiarkan apa adanya di sesi ini — flagged untuk sesi terpisah setelah cron cPanel produksi dikonfirmasi aktif.

---

### 2026-08-04 — Search Box Multi-Field di ~20 Halaman List/Tabel (4 Sesi Bertahap)

Latar: Owner minta search box ditambah di semua halaman list/tabel yang relevan, pencarian multi-field (beberapa kolom sekaligus), pattern konsisten. Wajib audit dulu (inventarisasi ~32 halaman) sebelum apply — hasil audit disetujui Owner dengan 2 keputusan: (1) 5 halaman poor-fit (Kas card-grid, Kategori Transaksi tree kecil, Buku Besar & Konsumsi Bahan detail belum paginated, Data Terhapus 24-model) di-skip untuk sesi ini; (2) 5 commit terpisah per prioritas sesuai rencana awal. Prinsip "jangan ganggu yang sudah ada" tetap berlaku — nol perubahan logic filter existing, nol perubahan `PenjualanService`/`KeuanganController::store()`/skema DB (tidak ada migration, tidak ada index tambahan — skala data saat ini `LIKE` polos sudah cukup).

#### Audit Sebelum Apply — Temuan Kunci
- 14 dari ~32 halaman SUDAH punya search ad-hoc (markup `<input name="search">` ditulis ulang manual tiap file, tidak ada component reusable sama sekali sebelumnya).
- 2 di antaranya (Riwayat Order, Purchase Order) cuma search 1-2 kolom, direkomendasikan di-enhance.
- Audit Log punya backend search YANG SUDAH JALAN tapi input UI-nya tidak pernah dipasang di view — dead code, "cheapest win".
- Kelola Absensi (form batch input harian) dan Ranking Evaluasi diidentifikasi butuh pendekatan BEDA (client-side) karena risiko data hilang dari form submit kalau search server-side.

#### 1. `<x-search-box>` — Component Reusable (Commit 1, `d13a67d`)
Cuma render `<input name="search">` + icon, TANPA `<form>` sendiri — dipasang di dalam form filter GET existing di tiap halaman, supaya search+filter submit bareng tanpa JS penggabung query string. Lihat Rule bisnis #53 untuk detail pattern lengkap.

#### 2. Prioritas 1 — Order, Transaksi, PO (Commit 2, `3b3d6f4`)
Kas & Transaksi (BARU: nomor_transaksi, keterangan, kas.nama_kas, kategori.nama). Riwayat Order & Purchase Order (enhance search existing + field tambahan: telepon_pelanggan/catatan, catatan/alasan_langsung).

#### 3. Prioritas 2 — Transfer Dana, Stock Request/Transfer, Absensi, Cuti, Penggajian (Commit 3, `b7945f5`)
7 halaman ditambah search baru. Tab-link status di 3 halaman (Setoran, Stock Request, Stock Transfer) diperbaiki supaya preserve `search` saat pindah tab (sebelumnya reset). Kelola Absensi pakai client-side filter (JS toggle display, row tetap di DOM) — lihat Rule #53 untuk alasan lengkap. **Bug insidental ditemukan & diperbaiki**: `StockTransferController::index()` CRASH TOTAL untuk semua user (`$authUser` dipakai view tapi tidak pernah di-compact), `StockRequestController` sama tapi cuma warning.

#### 4. Prioritas 3 — Jenis Olahan, Resep Bumbu, Aset, Audit Log, Laporan Penjualan, Evaluasi (Commit 4, `a07c7b7`)
6 halaman/grup ditambah search. Audit Log murni tambah `<x-search-box>` (nol perubahan controller, backend sudah jalan). Evaluasi Ranking pakai client-side filter (sama pola Absensi). **Bug insidental ke-2**: `EvaluationController::periodIndex()` — `$authUser` tidak pernah di-compact, `isset($authUser)` di view diam-diam `false` untuk SEMUA user (termasuk Owner) sehingga card filter cabang + tombol "Buat Periode Baru" SELALU tersembunyi, silent (bukan error/warning apapun) — baru ketahuan karena kebetulan halaman ini butuh disentuh untuk search.

#### 5. Panduan + CLAUDE.md (Commit 5)
Panduan slug `pencarian-multi-field` (modul `lainnya`, urutan 120) — daftar lengkap halaman yang punya search, penjelasan mekanisme client-side khusus Absensi/Ranking, dan alasan 5 halaman yang sengaja di-skip. CLAUDE.md Rule #53.

#### Verifikasi
- Setiap controller yang diubah: match-count diverifikasi terhadap data live sebelum/sesudah search diterapkan (mis. Order 14→2 match untuk keyword telepon, TransaksiKeuangan 210→34 match untuk keyword keterangan, Laporan Penjualan 14 match nomor_order).
- Search kosong → render sama seperti baseline (tidak ada regresi). Search tanpa hasil (keyword nonsense) → render empty-state graceful, tidak error.
- Pagination tetap jalan setelah search (`?search=x&page=2` tidak error).
- Beberapa tabel (StockRequest, StockTransfer, Cuti, Penggajian, EvaluationPeriod) kosong di data lokal (fitur belum pernah dipakai) — search-nya divalidasi via pattern-matching yang identik dengan pattern yang sudah teruji di tabel berisi data (Order, TransaksiKeuangan, Setoran).
- Regresi: seluruh filter dropdown/tanggal/status existing di 20 halaman tidak diubah logicnya — cuma ditambah 1 kondisi `WHERE` baru yang independen.

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — murni component + query enhancement, tidak ada migration, tidak ada index tambahan.

**Catatan kompatibilitas:** POS checkout, `PenjualanService::buatOrder()`, `KeuanganController::store()`, dan seluruh filter/pagination existing di 20 halaman **tidak disentuh sama sekali** — diverifikasi ulang render tanpa error, angka/hasil filter existing tidak berubah. 2 bugfix insidental (`StockTransferController`/`StockRequestController`, `EvaluationController::periodIndex`) murni menambah 1 variabel ke `compact()`, tidak ada perubahan logic lain.

---

### 2026-08-04 — Kolom Jam di Riwayat Order/Transaksi Keuangan + Laporan Analisa Jam Ramai + Widget Dashboard

Latar: Owner minta visibilitas jam transaksi + laporan jam ramai (peak hours) untuk keputusan jadwal staff & promo. Wajib audit dulu keandalan `created_at` sebagai sumber "jam kejadian" sebelum apply — hasil audit mengubah desain: laporan & widget cuma pakai `orders`, TIDAK `transaksi_keuangans`. Prinsip "jangan ganggu yang sudah ada" tetap berlaku — nol perubahan ke `PenjualanService`/`StokService`/`KeuanganController::store()`/skema DB (tidak ada migration).

#### Audit Sebelum Apply — Temuan yang Mengubah Desain
- `orders.created_at`: 100% valid (0 NULL dari 14 order), timestamp bervariasi wajar — aman dipakai sebagai "jam order dibuat" (traffic pelanggan real).
- `transaksi_keuangans.created_at`: **58,9% baris** punya `DATE(created_at) != tanggal_transaksi` (diinput belakangan/backdated), dan **19,8%** (39 dari 197 baris) adalah entri auto-generated dari batch Auto Depresiasi (`referensi_type='asset_depreciation'`, 4 grup timestamp identik dari `AssetDepreciationService`) — bukan aktivitas bisnis real-time.
- `transaksi_keuangans.tanggal_transaksi` dikonfirmasi kolom `date`-only (tanpa jam) — tidak bisa dipakai sebagai pengganti.
- Timezone dikonfirmasi aman: `config('app.timezone')='Asia/Jakarta'`, MySQL `time_zone=SYSTEM`, `NOW()` MySQL identik dengan `now()` PHP — tidak ada bug pergeseran jam.
- **Keputusan (disetujui Owner sebelum apply):** Laporan Jam Ramai + Widget Dashboard MURNI dari `orders.created_at`, TIDAK PERNAH dari `transaksi_keuangans`. Kolom Jam tetap ditambahkan di kedua list, tapi Transaksi Keuangan diberi disclaimer eksplisit (tooltip + ikon kondisional) karena keandalannya beda jauh dari Riwayat Order.

#### 1. Kolom Jam — Riwayat Order & Transaksi Keuangan
- **Riwayat Order** (`penjualan/index.blade.php`, desktop+mobile): kolom Jam = `created_at->format('H:i')` apa adanya — representasi akurat, sumber data laporan Jam Ramai.
- **Transaksi Keuangan** (`keuangan/index.blade.php`, desktop+mobile, tabel utama "Daftar Transaksi" saja — tabel cleanup tool "Tanpa Kas Sumber" sengaja tidak disentuh): kolom Jam = `created_at` + ikon info (ⓘ, tooltip "jam entri dicatat, BUKAN jam kejadian bisnis") + ikon jam kuning (🕐) kondisional kalau `DATE(created_at) != tanggal_transaksi` — user langsung lihat baris mana yang backdated.

#### 2. JamRamaiService — Satu Service, Dua Pemakai (Report + Widget)
- `getAnalisaJamRamai(Carbon $mulai, Carbon $akhir, ?int $cabangId)` — group `orders` (exclude Dibatalkan) by `HOUR(created_at)`, hasilkan array 24 jam (zero-filled), `jam_puncak`/`jam_sepi` (jam sepi cuma dari jam yang SUDAH ada aktivitas, `null` kalau kebetulan sama dengan jam_puncak), total transaksi/nominal, jumlah hari operasional, dan rekomendasi data-driven (termasuk disclaimer data tipis kalau ≤14 hari/≤30 transaksi — pola sama seperti disclaimer di seluruh sistem lain).
- Dipanggil identik oleh `LaporanJamRamaiController` (menu `/laporan/jam-ramai`, permission `laporan.jam_ramai.view`) dan `<x-jam-ramai-widget>` (dashboard cabang & pusat, filter `Carbon::today()`—`Carbon::now()->endOfDay()`) — nol duplikasi logic.
- Verifikasi: `total_transaksi` hasil service PERSIS sama dengan hitung manual all-time order count pada data live yang sama.

#### 3. Menu Laporan Analisa Jam Ramai + Widget Dashboard
- View: filter Dari/Sampai + Cabang, kartu ringkasan (Jam Puncak/Sepi, Total Order, Hari Operasional), bar chart 24 jam (Chart.js, batang hijau untuk jam puncak — reuse library yang sudah global, nol dependency baru), tabel detail per jam, rekomendasi.
- Widget "Jam Ramai Hari Ini" — mini bar chart (cuma render jam yang punya aktivitas, bukan 24 bar kosong), highlight jam puncak, link ke laporan lengkap. Dipasang di `dashboard/cabang.blade.php` + `dashboard/pusat.blade.php` setelah `<x-dashboard-analytics-widget>`.
- Permission `laporan.jam_ramai.view` (group `laporan`) Owner-only default — dipakai BERSAMA oleh menu laporan dan widget (1 permission, 2 tempat tampil, bukan permission terpisah untuk widget).

#### Verifikasi
- Render langsung `LaporanJamRamaiController::index()`, `DashboardController::cabang()`, `DashboardController::pusat()` dengan data live — semua render tanpa error, widget & laporan tampil dengan angka benar.
- Permission gate: Owner bypass ✅, non-Owner tanpa grant → 403 ✅.
- Route `laporan.jam-ramai.index` terverifikasi terdaftar.
- Regresi: `PenjualanService::buatOrder()`, `KeuanganController::store()`, form POS, dan seluruh laporan/dashboard existing tidak disentuh sama sekali.

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — murni service/controller/view/permission/panduan baru + penambahan kolom tampilan (bukan kolom DB) di 2 list existing.

**Catatan kompatibilitas:** POS checkout, Kas Keluar manual, dan seluruh laporan/dashboard existing **tidak disentuh sama sekali** — diverifikasi ulang render tanpa error, angka tidak berubah.

---

### 2026-08-03 — Laporan Eksekutif Keuangan Sesi B: Findings, Rangkuman Final, Simulasi Balik Modal, Simulator BEP, Patch PDF Lama

Latar: lanjutan Sesi A (6 halaman selesai + deploy sukses). Sesi B menambah 3 halaman PDF (Evidence-Based Findings, Rangkuman Final, Simulasi 5 Skenario Balik Modal → total 9 halaman), Menu Interactive Simulator BEP, patch preventif bug nomor halaman ke 4 PDF Fase 2/3, dan fix logo PDF. Prinsip "jangan ganggu yang sudah ada" tetap berlaku — ZERO modifikasi ke `PenjualanService`/`StokService`/form Kas, dan ZERO modifikasi ke `NeracaService`/`LabaRugiFormalService`/`BepOtomatisService`/`BukuBesarService`/`AssetDepreciationService`/`BusinessOverviewService`/`BebanBreakdownService`/`CrossCheckValidator` (Fase 1-3 + Sesi A) — semua CUMA dipanggil/reuse.

#### Audit Sebelum Apply — 1 Keputusan Owner Dibutuhkan
Parameter skenario "Volume Sedang"/"Volume Optimis" untuk Simulasi Balik Modal butuh konfirmasi Owner — histori produksi cuma ~1,5 minggu (bisnis baru, belum jalankan marketing), tidak representatif untuk "kapasitas riil". Owner approve: kedua skenario default ke **1x/2x BEP unit harian** (proporsional, bukan angka absolut arbitrary) dan **bisa di-override manual** di form sebelum generate — bukan angka tetap yang diklaim proyeksi historis.

#### 1. EvidenceBasedFindingsService + RangkumanFinalService (Halaman 7-8)
- 5 temuan Halaman 7: Struktur Beban (reuse `BebanBreakdownService`), Hutang Jatuh Tempo (reuse `PoDashboardService::getPoPendingBayar()` existing), Produktivitas Transaksi/Kasir Performance/Trend Kas 30 Hari (3 query baru sederhana, join/groupby/sum polos — Kasir Performance MIRROR pola `LaporanPenjualanController` tapi tidak inject controller sebagai service)
- Halaman 8: **Health Score 5 dimensi BARU** (Profitabilitas/Likuiditas/Pencapaian BEP/Efisiensi Beban/Solvabilitas, 0-100, heuristik threshold internal — didisclose BUKAN standar audit baku) + 3 Poin Utama (sortir severity dari kesimpulan Sesi A + Findings + CrossCheck, KRITIS→PERHATIAN→POSITIF) + Pertanyaan Direksi (generate kondisional dari data, bukan template statis)

#### 2. SimulasiBalikModalService (Halaman 9) — Bug Floating-Point Ditemukan & Diperbaiki
- Reuse `NeracaService` (modal awal) + `BepOtomatisService` (harga jual, biaya variabel, biaya tetap, BEP unit) — nol kalkulasi harga/biaya baru
- 5 skenario: Tren Aktual, Volume Sedang (default 1x BEP harian, override-able), Volume BEP (locked, titik impas), Volume Optimis (default 2x BEP harian, override-able), Kombinasi Efisiensi (volume Tren Aktual + pangkas X% dari TOTAL Biaya Tetap — digeneralisasi dari brief awal yang spesifik ke "Sewa", karena kategori Sewa/Gaji/Listrik bisa Rp0 di suatu periode)
- **Bug ditemukan saat testing**: skenario "Volume BEP" (persis di titik impas) menghasilkan `waktu_balik_modal_bulan` = `1.06e+17` (angka astronomis tidak masuk akal) — root cause: `laba_bulanan` yang seharusnya `0` bisa jadi angka sangat kecil non-zero (floating-point arithmetic), lalu `modalAwal / labaMendekatiNol` meledak. Fix: epsilon guard `Rp1.000` — di bawah nilai ini dianggap "impas", bukan untung/rugi. Diverifikasi juga di skenario "Volume Sedang" (default = 1x BEP, hampir persis breakeven) yang sebelum fix juga kena bug yang sama

#### 3. Menu Interactive Simulator BEP (`/laporan/simulator-bep`)
- 4 slider (Volume Harian, Harga Jual, Biaya Variabel, Beban Tetap), kalkulasi client-side JavaScript murni (formula sama seperti `BepOtomatisService`, aljabar dasar) — nol AJAX, nol service tambahan karena tidak menyentuh data apapun
- Nilai awal slider reuse `BepOtomatisService`/`NeracaService` (server-side, sekali saat load halaman)
- Permission `laporan.simulator.view`, Owner-only default

#### 4. Patch Preventif Bug Nomor Halaman (Rule #48) — Ternyata Jadi Patch AKTIF
- Pattern fix (`$pdf->render()` sebelum `page_text()`) diterapkan ke `LaporanNeracaController`, `LaporanLabaRugiFormalController`, `LaporanBepOtomatisController`, `BukuBesarController::export()`
- **Temuan penting saat regression test**: Buku Besar untuk akun `6-1104` (39 transaksi) TERNYATA SUDAH genuinely 2 halaman di data real (bukan skenario hipotetis "kalau nanti overflow") — sebelum patch, KEDUA halaman salah menampilkan footer "Halaman 1 dari 1"; setelah patch, benar "Halaman 1 dari 2"/"Halaman 2 dari 2". Ini bug AKTIF yang sudah live di produksi sebelum sesi ini, bukan murni preventif seperti dugaan awal
- 3 PDF lain (Neraca, Laba Rugi Formal, BEP Otomatis) diverifikasi tetap 1 halaman dengan output identik sebelum/sesudah patch (regresi aman)

#### 5. Fix Logo PDF
- Root cause: BUKAN masalah di 4 PDF lama (PDF-only, tidak pernah dirender sebagai HTML browser, `public_path()` selalu benar di sana)
- Masalah murni di `_page-header.blade.php` (Laporan Eksekutif Sesi A) yang dipakai GANDA (preview HTML browser + PDF dari view yang sama) — `public_path()` (path filesystem `D:\...`) valid untuk DomPDF tapi browser tidak bisa memuatnya sebagai `<img src>`
- Fix: `($isPdf ?? false) ? public_path(...) : asset(...)` — flag `$isPdf` sudah ada dari Sesi A, tidak perlu perubahan lain

#### Verifikasi (transaction-rollback test terhadap data produksi lokal)
- Ketiga service baru dites terpisah dengan data live sebelum controller diupdate
- PDF 9-halaman: 276KB, page count = 9 (form-feed count), semua footer "Halaman X dari 9" benar
- Cross-check numerik Halaman 9 vs test service langsung — 100% identik
- Regression test 4 PDF lama: semua tetap valid, 3 di antaranya (Neraca/Laba Rugi Formal/BEP Otomatis) 1 halaman tanpa perubahan, Buku Besar terbukti benar 2 halaman (lihat poin 4)
- Non-Owner diblokir 403 di Simulator BEP dan seluruh endpoint Laporan Eksekutif
- Regresi: semua laporan existing + widget dashboard + Laporan Eksekutif Sesi A (6 halaman pertama) tetap render tanpa error, angka tidak berubah

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — murni service/controller/view/permission/panduan baru + patch logic di 4 controller existing (Fase 2/3, bukan salah satu dari 8 service yang dilindungi).

**Catatan kompatibilitas:** POS checkout, Kas Keluar manual, seluruh laporan existing (Fase 1-3 + Sesi A), dan widget dashboard existing **tidak disentuh sama sekali** — diverifikasi ulang render tanpa error dan angka identik. **Marathon Laporan Eksekutif Keuangan (Sesi A + Sesi B) selesai — 9 halaman lengkap + Menu Simulator BEP interaktif.**

---

### 2026-08-03 — Laporan Eksekutif Keuangan Sesi A: Cover, Neraca, Laba Rugi, BEP, Arus Kas, Drill-Down (6 Halaman)

Latar: Owner minta "Laporan Eksekutif Keuangan" untuk rapat direksi — komprehensif, data-driven, drill-down ke menu existing. Dibagi 2 sesi untuk safety production; Sesi A membangun fondasi (3 service baru) + PDF 6 halaman pertama. Sesi B (Evidence-Based Findings, Rangkuman Final, Simulasi 5 Skenario, Interactive Simulator) menyusul terpisah. Prinsip "jangan ganggu yang sudah ada" tetap berlaku — ZERO modifikasi ke `PenjualanService`/`StokService`/form Kas, dan ZERO modifikasi ke `NeracaService`/`LabaRugiFormalService`/`BepOtomatisService`/`BukuBesarService`/`AssetDepreciationService` (Fase 1-3) — semua CUMA dipanggil/reuse.

#### Audit Sebelum Apply — 3 Penyesuaian Disetujui Owner
1. **Breakdown Gaji per Karyawan**: ditemukan `Penggajian` (payroll) TIDAK terhubung otomatis ke `transaksi_keuangans` kategori Gaji — keduanya independen. Disetujui: tampilkan berdampingan + disclaimer eksplisit "informasional, total resmi dari transaksi keuangan" (lihat Rule bisnis #49).
2. **Cross-Check Aset → diganti Cross-Check Beban**: "Total Aset (Neraca) vs Buku Besar" tidak feasible karena scope Buku Besar (keputusan Fase 3) sengaja exclude akun Aset. Disetujui ganti ke "Total Beban (Laba Rugi Formal) vs Total Beban (agregat Buku Besar)" — 2 jalur agregasi kode genuinely berbeda, toleransi diperketat < Rp0,01 (bukan < Rp1 seperti `balance_check` Neraca).
3. **Bonus reuse `DashboardAnalyticsService`**: diperluas dengan parameter opsional `?Carbon $asOf` (backward compatible, default `null`→`now()`) supaya bisa dipanggil untuk periode historis oleh Laporan Eksekutif, bukan cuma "bulan berjalan" seperti pemakaian widget dashboard.

#### 1. BusinessOverviewService — Cover Overview (Halaman 1)
- Compose murni: `NeracaService` (modal awal, aset tetap breakdown per-item — REUSE, tidak query `Asset::` ulang), `AssetDepreciationService` (snapshot penyusutan agregat), `BepOtomatisService` (target vs realisasi), `DashboardAnalyticsService` (trend 6 bulan, rasio, profitabilitas)
- Kalkulasi BARU (bukan duplikasi apapun, karena genuinely tidak ada service yang menghitungnya): sisa umur ekonomis rata-rata aset (aritmatika tanggal `umur_ekonomis_bulan - bulan_berjalan`), ringkasan transaksi/produksi (count/sum/avg polos dari Order/OrderItem), `getArusKasSummary()` untuk Halaman 5
- **Arus Kas — temuan penting**: query dasarnya (total_masuk/keluar) SENGAJA disamakan persis dengan `LaporanKeuanganController::arusKas()` existing (tidak difilter `kas_id`) supaya cross-check konsisten dengan menu itu — TAPI ini berarti termasuk entri non-tunai (penyusutan aset, `kas_id=NULL`, lihat Rule #35/#44) yang bisa menyesatkan untuk laporan berjudul "Arus Kas". Solusi: tambah breakdown `tunai_only` (exclude `kas_id NULL`) sebagai info tambahan, BUKAN mengganti angka utama — keduanya ditampilkan dengan disclaimer jelas
- Kesimpulan/"Poin Kunci" Halaman 1 di-generate dari kondisi if/else atas angka aktual (laba positif/negatif, BEP tercapai/belum, Neraca balance/tidak, rasio lancar) — bukan teks template statis, isinya berubah sesuai data

#### 2. BebanBreakdownService — Detail per Akun Beban
- `getRingkasanSemuaKategori()` reuse `LabaRugiFormalService::hitungLabaRugi()` breakdown; `getBebanDetail()` reuse `BukuBesarService::getTransaksiPerAkun()` untuk daftar transaksi mentah (link drill-down) — nol resolusi kode akun baru ditulis
- Breakdown per karyawan khusus akun Gaji (6-1101) dari tabel `Penggajian` — return `total_penggajian` DAN `total_transaksi_kas` terpisah + `selisih` eksplisit (lihat penyesuaian #1 di atas)
- Alert threshold sederhana (rasio beban/pendapatan ≥50%=kritis, ≥30%=perhatian) — heuristik internal, didisclose bukan standar baku industri

#### 3. CrossCheckValidator — Verifikasi Halaman 6
- 4 check: Total Beban (genuinely independen, lihat penyesuaian #2), Laba Ditahan/BEP/Kas (sanity check integrasi parameter, bukan verifikasi independen — didisclose jujur di docblock+UI)
- Toleransi `< Rp0,01` untuk semua 4 check (lebih ketat dari `balance_check` Neraca karena seharusnya identik persis)

#### 4. LaporanEksekutifController + PDF 6 Halaman
- Routes `/laporan/eksekutif` (index/preview/export), permission `laporan.eksekutif.view/export`
- 6 partial Blade (`halaman-1-cover` s/d `halaman-6-drill-down`) + 1 `master.blade.php` yang menggabungkan semuanya dengan `page-break-before: always` — dipakai GANDA: sebagai PDF (DomPDF) dan sebagai preview HTML biasa (browser, tanpa DomPDF) dari view yang SAMA persis (WYSIWYG, satu sumber kebenaran visual)
- **Bug DomPDF ditemukan & diperbaiki (lihat Rule bisnis #48 untuk detail lengkap)**: nomor halaman ("Halaman X dari Y") cuma muncul di halaman 1 dengan Y yang salah (selalu 1) kalau `page_text()` dipanggil sebelum `render()` eksplisit — pola yang dipakai SEMUA PDF Fase 2/3 sebelumnya tapi baru ketahuan sekarang karena baru kali ini ada PDF genuinely multi-halaman di codebase ini. Fix: panggil `$pdf->render()` dulu, baru `page_text()`
- Karakter spesial (`&sup2;`, `&ge;`, `&rarr;`) di template PDF diganti ASCII biasa (`rata-rata`, `>=`, `->`) — mengulang pelajaran yang sama dari Fase 3 (font default DomPDF tidak reliable untuk simbol Unicode)

#### Verifikasi (transaction-rollback test terhadap data produksi lokal)
- Ketiga service baru dites terpisah dengan data live sebelum controller dibuat
- **Cross-check numerik**: keempat check di `CrossCheckValidator` MATCH dengan selisih Rp0,00 (termasuk "Total Beban" yang genuinely 2 jalur kode berbeda)
- **Cross-check `BebanBreakdownService`**: `jumlah` dari `getBebanDetail()` PERSIS sama dengan `ledger.saldo_akhir` dari `BukuBesarService` yang di-reuse di dalamnya
- PDF 6-halaman: 262KB (jauh di bawah batas 3MB), page count diverifikasi = 6 via form-feed count (`pdftotext | awk 'BEGIN{RS="\f"}'`), nomor halaman "Halaman X dari 6" benar di SEMUA 6 halaman setelah fix
- Render `index()`/`generate()`(preview HTML)/`export()`(PDF) dites dengan user Owner — semua OK; non-Owner diblokir 403
- Regresi: semua laporan existing (Neraca, Laba Rugi Formal, BEP Otomatis, Buku Besar, Konsumsi Bahan, Laba Rugi lama, Penjualan) + widget dashboard (PO Status, Aset Snapshot, Neraca Snapshot, Analytics) dites render ulang tanpa error, angka tidak berubah

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — murni service/controller/view/permission/panduan baru + 1 parameter opsional baru (`?Carbon $asOf`) di `DashboardAnalyticsService::getSnapshot()` (Fase 3, backward compatible, bukan salah satu dari 5 service yang dilindungi).

**Catatan kompatibilitas:** POS checkout, Kas Keluar manual, seluruh laporan existing (termasuk Fase 1-3), dan widget dashboard existing **tidak disentuh sama sekali** — diverifikasi ulang render tanpa error dan angka identik. **Temuan terpisah untuk sesi mendatang**: bug DomPDF multi-halaman (Rule #48) berpotensi memengaruhi 4 PDF Fase 2/3 (Neraca/Laba Rugi Formal/BEP Otomatis/Buku Besar) KALAU kontennya suatu saat overflow ke halaman ke-2 — belum di-patch di sesi ini (di luar scope Laporan Eksekutif), flagged untuk keputusan Owner.

---

### 2026-08-02 — Marathon Fase 3 (Penutup): Laporan BEP Otomatis + Widget Dashboard Analytics + Buku Besar

Latar: penutup marathon 3 fase dari Fase 1 (Fondasi COA) dan Fase 2 (Neraca + Laba Rugi Formal). Fase 3 menambah Laporan BEP Otomatis (hitung BEP langsung dari transaksi real tanpa setup manual), Widget Dashboard Analytics "Kesehatan Finansial" (4 kartu financial health), dan Buku Besar (General Ledger, scope terbatas — hasil keputusan Owner setelah audit menimbang risiko vs value). Prinsip "jangan ganggu yang sudah ada" tetap berlaku — nol perubahan ke `PenjualanService::buatOrder()`, `StokService`, form input Kas/Transaksi, data historis, dan seluruh output Fase 1+2 (COA, Neraca, Laba Rugi Formal, Auto Depresiasi) diperlakukan READ ONLY.

#### Audit Sebelum Apply — 3 Temuan Kunci
- **Menu BEP existing (`BepController`/`BepCalculationService`) formulanya SUDAH BENAR** (BEP unit = biayaTetap/marginKontribusi) — TIDAK ada bug matematis. Yang genuinely gap: seluruh alurnya SEMI-MANUAL (butuh bikin `BepSetting` per cabang+periode dulu, isi Biaya Tetap+Produk manual atau via tombol "Isi Otomatis"), dan `autoFill()` existing-nya sendiri masih hardcode 4 sumber biaya tetap (Gaji dari `Penggajian`, Depresiasi, kategori kode `SEWA`/`OPS` literal) — TIDAK memakai kolom `kategori_transaksis.tipe_biaya` yang sudah ada dari Fase 1 persis untuk kebutuhan ini. Biaya variabelnya juga masih pakai `Item.harga_beli_terakhir` (snapshot), bukan HPP FIFO aktual dari `order_items.hpp`.
- **Buku Besar dinilai worth-build dengan syarat scope dibatasi** — Owner diberi 2 opsi (build scope terbatas vs skip) via pertanyaan eksplisit, MEMILIH build. Risiko utama yang diidentifikasi sebelum apply: menentukan Debit/Kredit per transaksi salah bisa menghasilkan "buku besar" yang terlihat resmi tapi keliru — dimitigasi dengan reuse persis resolver dari `LabaRugiFormalService` (bukan re-derive logic baru) dan scope dibatasi HANYA akun Pendapatan/HPP/Beban (akun Aset/Kewajiban/Modal di luar jangkauan `transaksi_keuangans` per-baris).
- **Angka real diaudit dari DB lokal sebelum coding**: Biaya Tetap bulan berjalan Rp7.498.907, Volume jasa giling 19,69 kg, Biaya Variabel/kg Rp4.730, Harga Jual rata-rata/kg Rp7.263 → BEP ≈2.961 kg/Rp21,5 juta — dipakai sebagai baseline verifikasi setelah service selesai ditulis.

#### 1. Refactor Reusable: `LabaRugiFormalService::resolveKodeAkun()`
- Method PRIVATE `hitungSaldoPerKodeAkun()` dari Fase 2 di-refactor: logic resolusi 3-lapis (kategori_id+kode ada → pakai; kategori_id ada tapi kode NULL sengaja → exclude; kategori_id NULL total → fallback enum) diekstrak jadi method PUBLIC `resolveKodeAkun()` yang dipanggil bareng oleh method agregat itu sendiri DAN `BukuBesarService` (per-baris, bukan agregat) — supaya 0 duplikasi logic (Rule bisnis #47 baru)
- **Diverifikasi murni refactor, 0 perubahan behavior**: dites dengan cara membandingkan hasil logic LAMA (inline, disalin persis dari kode sebelum refactor) vs logic BARU (lewat method hasil refactor) pada data live yang SAMA dalam 1 transaksi — hasil 100% identik

#### 2. BepOtomatisService::hitungBepOtomatis() — Satu-satunya Kalkulasi Baru
- Biaya Tetap: `SUM(transaksi_keuangans)` untuk kategori `tipe_biaya='tetap'` (reuse kolom Fase 1, bukan hardcode kategori seperti autoFill lama) + breakdown per kategori
- Biaya Variabel/kg & Volume: `SUM(order_items.hpp)`/`SUM(order_items.berat_daging)` untuk order `tipe_order='jasa_giling'` — **BUKAN** dari kategori `tipe_biaya='variabel'` (itu transaksi pembelian bahan, bukan HPP barang yang benar-benar terjual)
- Harga Jual/kg: rata-rata tertimbang (`total_omzet/total_kg`), bukan `AVG(harga_satuan)` sederhana, supaya proporsional terhadap volume
- Formula lengkap: BEP Unit, BEP Rupiah, % Tercapai, Margin of Safety, Estimasi Laba pada volume aktual — semua dihitung, termasuk guard division-by-zero (margin kontribusi ≤0 → `bisa_bep=false`, volume aktual=0 → margin of safety `null`)
- `LaporanBepOtomatisController` (`/laporan/bep-otomatis`) — extend menu BEP existing (link baru di sidebar setelah "BEP"), permission `laporan.bep_otomatis.view/export`. Break-Even Chart (Chart.js, pola visual disalin dari chart BEP existing) + PDF export

#### 3. DashboardAnalyticsService — Murni Komposisi 3 Service Existing
- Widget "Kesehatan Finansial" (4 card: Profitabilitas Bulan Ini, BEP Status, Rasio Keuangan Sederhana, Trend 6 Bulan) — **nol kalkulasi baru**, murni panggil `NeracaService`+`LabaRugiFormalService`+`BepOtomatisService` lalu susun ulang outputnya (Rule bisnis #47)
- Rasio Lancar = Aset Lancar/Kewajiban Jk. Pendek dari `NeracaService` (null-safe: kalau kewajiban jk. pendek Rp0, ditampilkan "Aman (tanpa hutang)" bukan divide-by-zero error)
- ROI Sederhana = Laba Bersih Bulan Ini/Total Modal — didisclose eksplisit di panduan sebagai "indikator kasar", BUKAN rumus ROI baku per-investasi
- Trend 6 bulan: loop `LabaRugiFormalService::hitungLabaRugi()` 6× — **`subMonths()` WAJIB dipanggil dari `startOfMonth()` dulu** (bukan dari tanggal hari ini), supaya tidak kena bug overflow yang sama seperti Rule bisnis #30 (subtraksi bulan dari tanggal 29/30/31 bisa overflow ke bulan berikutnya)
- Dipasang di `dashboard/cabang.blade.php` + `dashboard/pusat.blade.php` (pola sama widget Fase 1/2), permission `dashboard.analytics.view` (group baru `dashboard`)

#### 4. BukuBesarService — Buku Besar Scope Terbatas
- `getTransaksiPerAkun(kodeAkun, mulai, akhir, cabangId)` — list transaksi + running balance untuk 1 akun COA, REUSE `LabaRugiFormalService::resolveKodeAkun()` persis (bukan tulis ulang)
- Scope HANYA akun tipe `pendapatan`/`hpp`/`beban_operasional`/`pendapatan_lain`/`beban_lain` (`BukuBesarService::TIPE_DIDUKUNG`) — dropdown akun di UI otomatis exclude Aset/Kewajiban/Modal
- Debit/Kredit ditentukan dari `ChartOfAccount.tipe` (pendapatan/pendapatan_lain → Kredit, sisanya → Debit), saldo berjalan pakai formula generik berdasar `saldo_normal` akun
- **Saldo baris pertama periode SELALU Rp0** (bukan kumulatif riil) — disclaimer eksplisit di UI+panduan, konsisten pola disclaimer Neraca
- `BukuBesarController` (`/laporan/buku-besar`), permission `laporan.buku_besar.view/export`, PDF export dengan total Debit/Kredit/Saldo Akhir

#### 5. Panduan + Permission
- 3 panduan slug baru: `laporan-bep-otomatis` (modul `laporan`), `dashboard-analytics` (modul `laporan`), `buku-besar` (modul `akuntansi`)
- 5 permission baru: `laporan.bep_otomatis.view/export` (group `laporan`), `laporan.buku_besar.view/export` (group `akuntansi`), `dashboard.analytics.view` (group baru `dashboard`) — semua Owner-only default, `RolePermissionSeeder` TIDAK disentuh

#### Verifikasi (transaction-rollback test terhadap data produksi lokal)
- Refactor `resolveKodeAkun()`: dites identik dengan logic lama (lihat poin 1) — 0 regresi ke Laba Rugi Formal Fase 2
- `BepOtomatisService`: angka hasil hitung (BEP Unit 2.961,12 kg, BEP Rupiah Rp21.505.368) match persis dengan audit manual sebelum coding
- `BukuBesarService`: **cross-check ketat** — `saldo_akhir` untuk akun sisi debit (`6-1104` Beban Depresiasi, Rp7.498.906,74) DAN akun sisi kredit termasuk baris hasil fallback order/POS (`4-1101` Pendapatan Jasa Giling, Rp143.000) keduanya PERSIS SAMA dengan angka `jumlah` akun yang sama di breakdown `LabaRugiFormalService::hitungLabaRugi()` — 0 selisih di kedua arah
- Render `index()`+`export()` (PDF) untuk `LaporanBepOtomatisController` dan `BukuBesarController` — kedua PDF valid (`%PDF` header, ukuran ~240KB), non-Owner diblokir 403 di keduanya
- Dashboard `cabang()`/`pusat()` dites render dengan widget Analytics baru — berhasil, widget Fase 1/2 (`po-status-widget`, `aset-snapshot-widget`, `neraca-snapshot-widget`) tidak terpengaruh
- **Insiden kecil ditemukan & diperbaiki saat testing**: karakter `&sup2;` (superscript 2, dipakai untuk "rata²") mangled jadi karakter aneh di font default DomPDF — diganti ke teks polos "rata-rata" di template PDF BEP Otomatis (HTML view tetap pakai karakter asli karena browser render UTF-8 normal, cuma PDF-nya yang bermasalah)

**Tabel DB baru/diubah (sesi ini):** TIDAK ADA — Fase 3 murni menambah service/controller/view/permission/panduan baru + 1 method public baru (refactor) di `LabaRugiFormalService` (Fase 2), tidak ada migration sama sekali.

**Catatan kompatibilitas:** POS checkout, Kas Keluar manual, Laporan Penjualan/Laba Rugi/Konsumsi Bahan existing, Menu BEP existing (`BepController`/`BepCalculationService`/`LaporanBepController`), Laporan Keuangan, Neraca, Laba Rugi Formal, Auto Depresiasi, Transfer/Perpindahan Dana **tidak disentuh sama sekali** — diverifikasi ulang render tanpa error. Fase 3 murni menambah 4 service baru (1 di antaranya hasil refactor dari service Fase 2, terverifikasi identik), 2 controller baru, 3 view index baru + 2 PDF template baru, 1 widget dashboard baru, 5 permission baru, dan 3 panduan baru — **marathon 3 fase Akuntansi Profesional (Fase 1-3) selesai**.

---

### 2026-08-02 — Marathon Fase 2: Laporan Neraca + Laba Rugi Formal + Export PDF Professional

Latar: lanjutan marathon 3 fase dari Fase 1 (Fondasi COA). Fase 2 membangun 2 laporan akuntansi formal berstandar SAK ETAP yang MEMBACA data COA/kategori dari Fase 1: Laporan Neraca (Balance Sheet) dan Laporan Laba Rugi Formal (dikelompokkan per kode akun COA, berbeda dari Laporan Laba Rugi existing yang analisis gross profit per item). Prinsip "jangan ganggu yang sudah ada" tetap berlaku sama seperti Fase 1 — nol perubahan ke `PenjualanService::buatOrder()`, `StokService`, form input Kas/Transaksi, data historis `transaksi_keuangans`, dan seluruh output Fase 1 (COA/kategori mapping/auto depresiasi) diperlakukan READ ONLY.

#### Audit Sebelum Apply — Angka Real dari DB Lokal
Sebelum coding, diaudit dulu sumber data Neraca dari DB produksi-copy lokal: Kas aktif (`Kas.saldo_sekarang`), Persediaan FIFO (`stock_batches.qty_sisa × harga_beli_per_unit` per tipe item), Aset aktif (`assets.harga_perolehan`/`nilai_buku`), Hutang Usaha (`PurchaseOrder` diterima tanpa `TransaksiKeuangan` referensi), dan Modal (tidak ada tabel modal — perlu keputusan desain, lihat di bawah). Owner approve pendekatan sebelum apply lewat 2 keputusan: (1) fallback enum→kode akun untuk transaksi order/POS yang `kategori_id`-nya selalu NULL, dan (2) Modal Owner sebagai **setting manual** (bukan auto-derive dari data yang tidak lengkap), default di-seed dari histori.

#### 1. NeracaService::hitungNeraca() — Snapshot Per Tanggal
- Aset Lancar: Kas aktif per lokasi + Persediaan FIFO (join `stock_batches`+`items`, group by `tipe` — `bahan_baku`+`lainnya` digabung karena COA cuma punya 3 leaf persediaan) + Piutang (hardcode 0, belum ada fitur)
- Aset Tetap: dari `Asset::where('status', Aktif)` — `harga_perolehan` (bruto), `nilai_buku`, `akumulasi_depresiasi` dihitung selisihnya, + detail per-aset untuk expand di UI
- Kewajiban: Hutang Usaha via `whereNotExists` PO diterima tanpa transaksi referensi `purchase_order` yang match; Hutang Pajak/Bank hardcode 0 (belum ada fitur)
- Modal: `NeracaSetting.modal_owner` (singleton, seperti `PengaturanGaji`) + Laba Ditahan (kumulatif SEMUA laba bersih sejak `2000-01-01` s/d tanggal cutoff, bukan cuma 1 periode — representasi retained earnings yang benar)
- `balance_check` = `abs(TotalAset - (TotalKewajiban+TotalModal)) < 1` — **WAJAR false** untuk data historis pra-COA, didisclose jujur di UI dengan penjelasan, bukan dipaksa balance

#### 2. LabaRugiFormalService::hitungLabaRugi() — Resolusi COA 3 Lapis + 2 Bug Ditemukan
- Breakdown per akun leaf COA (`pendapatan`/`hpp`/`beban_operasional`/`pendapatan_lain`/`beban_lain`) — SEMUA leaf ditampilkan termasuk yang belum pernah dipakai (Rp 0), supaya struktur selalu lengkap
- **Resolusi kode akun 3 lapis** (lihat Rule bisnis #46 untuk detail lengkap): kategori_id+kode ada → pakai apa adanya; kategori_id ada tapi kode sengaja NULL (transfer internal) → exclude, TIDAK fallback; kategori_id NULL total (order/POS) → fallback ke `KategoriTransaksi::kodeAkunCoaFromEnum()`
- **Bug #1 ditemukan saat testing**: SETOR-IN/OUT awalnya ikut fallback ke enum karena resolusi lama cuma cek `kode_akun_coa ?? fallback` tanpa cek `kategori_id` dulu — transfer internal salah kereklas jadi Beban Lain-lain (Rp693 juta salah masuk beban). Fix: cek `kategori_id !== null` dulu sebelum decide fallback
- **Bug #2 ditemukan saat testing (Eloquent gotcha)**: override untuk kategori dual-purpose (`tipe='keduanya'`) tidak pernah jalan walau logic terlihat benar — root cause: alias `selectRaw('transaksi_keuangans.tipe as tipe')` bentrok dengan `casts()` model (`tipe` → enum `TipeTransaksiKeuangan`), Eloquent otomatis cast hasil raw SQL itu jadi objek enum, bikin `$row->tipe === 'pemasukan'` SELALU false tanpa error apapun. Fix: rename alias jadi `tipe_transaksi` (beda dari nama kolom asli). **Pelajaran umum untuk ke depan**: jangan pernah alias kolom `selectRaw()` sama persis dengan nama kolom bermodel-cast — lihat Rule bisnis #46
- Setelah kedua fix: Total Pendapatan Rp250.892.100, Total Beban Operasional Rp87.028.190, Laba Bersih Setelah Pajak Rp155.689.666 (angka masuk akal, diverifikasi manual)

#### 3. NeracaSetting — Singleton Config untuk Modal Owner
- Migration `neraca_settings` (id, modal_owner decimal, catatan, timestamps) — model `NeracaSetting::getSetting()` pola `firstOrCreate` sama seperti `PengaturanGaji`
- `NeracaSettingSeeder` idempotent — cuma set default `Rp198.329.749` (dari histori) kalau `modal_owner` masih 0, tidak pernah menimpa adjustment manual Owner
- Edit Modal Owner via modal di halaman Neraca, route `PUT /pengaturan/laporan/neraca/setting` di-gate `role:owner` (bukan permission granular baru) — konsisten pola Pengaturan Penggajian/Umum yang sudah ada

#### 4. Controller + View + PDF Export
- `LaporanNeracaController`/`LaporanLabaRugiFormalController` — **flat naming**, bukan `Controllers/Laporan/` subfolder seperti brief awal, supaya konsisten dengan 14 controller Laporan* existing yang semuanya flat
- Routes di dalam `Route::prefix('laporan')->name('laporan.')` group existing — `laporan.neraca.index/export`, `laporan.laba-rugi-formal.index/export`, semua `middleware('can:...')` per-route + `abort_unless` di controller (defense-in-depth, konsisten Rule #40)
- View index masing-masing custom (bukan reuse `<x-date-range-filter>` untuk Neraca karena itu snapshot 1-tanggal bukan rentang) + sidebar link baru di section Laporan existing (`@can` per permission)
- PDF export pakai `barryvdh/laravel-dompdf` (baru diinstall, **pertama kali dipakai di codebase ini** — fitur "export PDF" sebelumnya semua sebenarnya "print via browser", bukan dompdf beneran). Template CSS **table-based murni** (dompdf tidak reliable untuk flexbox/grid) dengan kop perusahaan (logo dari `public/images/logo.png`, nama hardcode "Berkah Mulyo"), kolom tanda tangan (Disiapkan oleh = user login, Mengetahui = Owner), nomor halaman via `$pdf->getDomPDF()->getCanvas()->page_text(...)` dipanggil dari controller (bukan `<script type="text/php">` di blade, supaya tidak perlu enable `dompdf.enable_php` yang berisiko keamanan)

#### 5. Widget Dashboard Neraca + Panduan
- `<x-neraca-snapshot-widget>` — Total Aset/Kewajiban/Modal + indikator Balance/Belum Balance + link "Lihat Detail", dipasang di `dashboard/cabang.blade.php` + `dashboard/pusat.blade.php` (pola sama `<x-aset-snapshot-widget>`), gate `@can('laporan.neraca.view')`, data dari `NeracaService` yang di-inject ke `DashboardController`
- 2 panduan slug baru: `laporan-neraca`, `laporan-laba-rugi-formal` (modul `akuntansi`, urutan 112-113)
- 4 permission baru (`laporan.neraca.view/export`, `laporan.laba_rugi_formal.view/export`, group `akuntansi`) — Owner-only default, `RolePermissionSeeder` TIDAK disentuh, delegable manual via UI Role & Hak Akses

#### Verifikasi (transaction-rollback test terhadap data produksi lokal)
- `NeracaService`/`LabaRugiFormalService` dites langsung dengan data DB lokal sebelum controller dibuat — angka di-cross-check manual (lihat poin 2)
- Render `index()` + `export()` (PDF) untuk kedua controller dites lewat pemanggilan langsung (bukan cuma routing) dengan user Owner — kedua PDF valid (`%PDF` header, ukuran ~240KB, jauh di bawah batas 2MB Owner)
- **PDF cross-validation tanpa render visual**: `pdftotext -layout` awalnya menampilkan angka yang terlihat "jumbled"/salah baris — setelah diinvestigasi lewat ekstraksi raw stream order (tanpa `-layout`) dan pencocokan posisional manual, semua subtotal/total match persis dengan sum komponen-nya (mis. Total Aset Lancar = jumlah 5 komponennya, Modal Owner = persis Rp198.329.749 dari seeder, Laba Ditahan = persis Rp155.689.666 dari test service) — **jumbling itu murni artefak heuristik `pdftotext -layout` pada baris header bold tanpa sel nilai**, bukan cacat rendering PDF sesungguhnya. Tidak ada tool rasterisasi PDF (`pdftoppm`/ImageMagick/PyMuPDF) tersedia di environment ini untuk cek visual langsung — **direkomendasikan Owner buka PDF hasil generate untuk 1x spot-check visual** setelah deploy
- Non-Owner (role tanpa permission) dites diblokir 403 di kedua controller
- Dashboard `cabang()`/`pusat()` dites render dengan widget baru — keduanya berhasil tanpa error, widget lama (`po-status-widget`, `aset-snapshot-widget`) tidak terpengaruh

**Tabel DB baru/diubah (sesi ini):** `neraca_settings` (BARU: `id`, `modal_owner` decimal, `catatan`, timestamps — tidak ada FK, singleton). Tidak ada perubahan skema ke tabel Fase 1 atau tabel manapun yang sudah ada.

**Catatan kompatibilitas:** POS checkout, Kas Keluar manual, Laporan Penjualan/Laba Rugi/Konsumsi Bahan existing, Laporan Keuangan, Transfer/Perpindahan Dana, dan seluruh output Fase 1 (COA, auto depresiasi) **tidak disentuh sama sekali** — Fase 2 murni menambah 2 service baru (read-only), 2 controller baru, 2 view baru, 2 PDF template baru, 1 widget dashboard baru, 4 permission baru, dan 1 tabel setting baru.

---

### 2026-08-02 — Marathon Fase 1: Fondasi COA + Rename Setoran + Auto Depresiasi + Panduan Komprehensif

Latar: Owner minta upgrade ke akuntansi profesional level enterprise, dikerjakan 3 fase dengan checkpoint deploy per fase. Sesi ini Fase 1 — fondasi COA (Chart of Accounts) standar SAK ETAP, rename "Setoran ke Pusat" → "Transfer/Perpindahan Dana", extend Auto Depresiasi Aset (sebelumnya 100% manual, belum pernah catat ke keuangan), dan panduan komprehensif 7 slug. Fase 2 (Laporan Neraca + Laba Rugi Formal) dan Fase 3 (Laporan BEP + widget analytics) menyusul di sesi terpisah. Prinsip eksplisit Owner: **"jangan ganggu yang sudah ada/sudah works"** — PenjualanService::buatOrder(), StokService, form input Kas & Transaksi kasir harian, data historis `transaksi_keuangans`/`orders`/`purchase_orders`, dan Laporan Penjualan/Konsumsi Bahan/Laba Rugi existing eksplisit READ-ONLY, tidak boleh disentuh.

#### Audit Sebelum Apply — 3 Temuan Kunci
- **Formula depresiasi existing (Garis Lurus) SUDAH BENAR** sesuai SAK ETAP (`(harga_perolehan - nilai_residu) / umur_ekonomis_bulan`) — tidak perlu koreksi. Yang genuinely hilang: (1) trigger 100% manual, belum ada scheduler; (2) idempotency check sudah ada tapi cuma di level controller single-asset, belum reusable untuk bulk; (3) **tidak ada integrasi ke `TransaksiKeuangan` sama sekali** — depresiasi tidak pernah tercatat sebagai beban di Kas & Transaksi.
- **`app/Console/Kernel.php` tidak ada** — brief awal minta registrasi cron di situ, tapi Laravel 12 project ini pakai konvensi `routes/console.php` (`Schedule::command()`) sejak fitur recurring transaction/backup — cron didaftarkan di sana, bukan Kernel.php.
- **Kategori "Penyusutan Aset" (kode `PNYS`) SUDAH ADA** dari sesi lama — brief minta "auto-create kategori beban_depresiasi kalau belum ada", ternyata cukup di-reuse, tidak perlu bikin baru (hindari duplikasi kategori).

#### 1. Chart of Accounts — 59 Kode Akun (Bukan 60-80 Literal, Tapi Sesuai Daftar Owner)
- Migration `chart_of_accounts`: `kode` unique varchar(6), `parent_kode` self-reference TANPA FK keras, `saldo_normal` enum debet/kredit, `level` 1-3, `is_leaf`, SoftDeletes+`deleted_by`
- Model `ChartOfAccount` — `SoftDeletes`+`HasAuditLog`+`FillsDeletedBy` (konsisten sesi lalu), terdaftar di `TrashController::$models`
- `ChartOfAccountsSeeder` — 59 akun idempotent (`updateOrCreate` by kode) persis mengikuti daftar literal dari Owner (ASET 1-XXXX s/d BEBAN LAIN 8-XXXX) — bukan 60-80 seperti disebut di awal brief, tapi itu cuma estimasi ballpang Owner, bukan angka pasti yang harus dicapai
- Menu baru **Keuangan → Chart of Accounts** (`/coa`, permission `coa.view`/`coa.manage`, group `akuntansi`, Owner-only default) — search + filter tipe + tombol print (browser print, bukan PDF generator terpisah)

#### 2. Mapping Kategori Transaksi → COA (Backfill Safe)
- Migration tambah `kode_akun_coa` (varchar 6, nullable) + `tipe_biaya` (enum tetap/variabel, nullable) ke `kategori_transaksis` — tanpa FK keras
- `KategoriCoaBackfillSeeder` — mapping 18 kategori existing (termasuk kategori baru "Listrik & Air", kode `LISTRIK`, disetujui Owner karena kategori ini genuinely tidak ada sebelumnya) ke kode akun yang sesuai. **Idempotent by-design**: pakai penanda `$sudahDiproses` (bukan cuma `whereNull`) supaya kategori dengan target sengaja NULL (transfer/saldo awal) tidak ikut ke-timpa default catch-all untuk kategori custom
- Form Tambah/Edit Kategori Transaksi + kolom list ditambah dropdown/tampilan Kode Akun COA — reklas manual Owner via UI tidak pernah tertimpa re-run seeder (guard `whereNull` di level seeder)

#### 3. Auto Depresiasi Aset
- `AssetDepreciationService::generateAsetTertentu()` — satu-satunya titik masuk, dipakai bareng trigger manual per-aset EXISTING (`AssetController::hitungPenyusutan()`, diupdate untuk ikut lewat method ini — backward compat penuh, hasil hitung identik, sekarang PLUS catat TransaksiKeuangan), tombol bulk baru, dan scheduler
- `generateBulanIni()`/`generateUntukPeriode()` — loop semua aset aktif, idempotent per aset+periode
- Setiap generate sukses insert 1 `TransaksiKeuangan` non-cash (`kas_id=NULL`, kategori `PNYS`, kode akun `6-1104`, `referensi_type='asset_depreciation'`) — pola sama dengan beban kerugian stok di `StokService::adjustment()`
- Command `aset:generate-depresiasi` terdaftar di `routes/console.php`, jadwal tanggal 1 jam 01:00 WIB
- Tombol manual bulk "Generate Depresiasi Bulan Ini" di menu Aset (permission `aset.depresiasi.auto`, default Owner + Admin Pusat)
- Widget Dashboard baru (`<x-aset-snapshot-widget>`, di `dashboard/cabang.blade.php` + `pusat.blade.php`, gate `aset.view`) — Total Nilai Aset (harga beli/akumulasi depresiasi/nilai buku) + Depresiasi Bulan Ini

#### 4. Rename Setoran ke Pusat → Transfer / Perpindahan Dana
- URL pindah `/setoran` → `/transfer-dana`, nama route TETAP `setoran.*` (lihat Rule #45 untuk detail teknis kenapa 2 nama di URI yang sama tidak bisa coexist di Laravel RouteCollection)
- `SetoranController` class TIDAK di-rename (risk regresi terlalu tinggi, sesuai instruksi Owner)
- Backward compat: `Route::redirect('/setoran', '/transfer-dana')` + wildcard sub-path redirect
- Sidebar rename di 2 tempat (menu Keuangan utama + menu Laporan) — keduanya sebelumnya berlabel sama "Setoran ke Pusat"
- Kategori `SETOR-IN`/`SETOR-OUT` rename nama tampilan jadi "Perpindahan Dana Masuk"/"Keluar" (kode & enum tidak berubah)

#### 5. Tombol "?" Cheat Sheet + 7 Slug Panduan
- Modal `<x-coa-cheat-sheet-modal>` — 28 skenario umum bisnis Berkah Mulyo (Skenario | Kategori Simple | Kode Akun COA), search client-side, murni referensi visual — dipasang di form Tambah & Edit Transaksi Keuangan
- 7 slug panduan baru/rename: `panduan-akuntansi-index` (landing page), `chart-of-accounts-overview`, `chart-of-accounts-referensi`, `chart-of-accounts-cheat-sheet`, `chart-of-accounts-workflow`, `transfer-dana` (rename dari slug `setoran` — slug lama otomatis hilang, `judul`+`konten` sekaligus diupdate), `auto-depresiasi`

#### Verifikasi (transaction-rollback test terhadap data produksi lokal)
- Migration: 59 akun ter-seed idempotent (re-run kedua = 0 perubahan), 2 kolom baru di `kategori_transaksis` idempotent (manual override disimulasikan, re-run backfill tidak menimpa)
- Auto Depresiasi: generate 1x → `AssetDepreciation` + `TransaksiKeuangan` non-cash tercipta benar (kas_id NULL, jumlah sesuai formula, kategori PNYS); generate ke-2 periode sama → return NULL, TIDAK ada duplikat; bulk generate mendeteksi aset yang sudah diproses dengan benar
- Rename route: `route('setoran.index')` menghasilkan URL `/transfer-dana` yang benar; `GET /setoran` dan `GET /setoran/5` keduanya redirect 302 ke `/transfer-dana` dengan benar
- **Regresi penuh** (POS checkout, Kas Keluar manual, 3 halaman Laporan, data historis Setoran): POS checkout tetap potong stok + tambah saldo kas dengan benar; Kas Keluar manual tetap potong saldo; Laporan Penjualan/Konsumsi Bahan Baku/Laba Rugi ketiganya render tanpa error; 8 transaksi historis Setoran/Transfer tetap ada tidak berubah
- Semua 7 halaman panduan + halaman COA + dashboard cabang/pusat + form Tambah/Edit Transaksi Keuangan (dengan tombol cheat sheet) di-render langsung — semua OK tanpa error
- Blade compile check untuk semua file view yang diubah/ditambah — semua OK

**Tabel DB baru/diubah (sesi ini):** `chart_of_accounts` (BARU, 59 baris). `kategori_transaksis` +kolom `kode_akun_coa` (varchar 6, nullable), `tipe_biaya` (enum tetap/variabel, nullable) — +1 kategori baru "Listrik & Air" (kode `LISTRIK`).

**Catatan kompatibilitas:** `PenjualanService::buatOrder()`, `StokService`, form input Kas & Transaksi kasir harian, dan seluruh data historis (`transaksi_keuangans`/`orders`/`purchase_orders`) **tidak disentuh sama sekali** — diverifikasi lewat regression test transaction-rollback. Tombol "Hitung Penyusutan" per-aset existing tetap berfungsi seperti biasa, sekarang otomatis ikut catat TransaksiKeuangan (sebelumnya tidak — ini penambahan behavior yang disengaja, bukan perubahan formula).

---

### 2026-07-28 — Update Keamanan Data Terhapus + Halaman Detail + Modal Hapus Permanen 2 Tahap

Latar: menyusul audit permission sesi sebelumnya, Owner minta 3 penambahan khusus untuk menu Data Terhapus: (1) audit trail siapa-yang-menghapus per baris, (2) halaman Detail lengkap per model (bukan cuma preview 3 kolom di list), (3) konfirmasi Hapus Permanen 2 tahap (preview → ketik teks) menggantikan `confirm()` browser 1 klik. Prinsip eksplisit dari Owner: **"jangan ganggu yang sudah ada/sudah works"** — tidak boleh ubah logic `index()`/`restore()`/`forceDestroy()` existing, tidak boleh edit satu-satu di tiap Service delete, tidak boleh sentuh trait `SoftDeletes`/`HasAuditLog`.

#### 1. Kolom `deleted_by` — Trait Baru, Bukan Edit Service Manual
- Migration tambah `deleted_by` (nullable, unsignedBigInteger, tanpa FK — konsisten pola `changed_by` di `_histories`) ke SEMUA 24 tabel yang terdaftar di `TrashController::$models` (bukan cuma "15 tabel" seperti disebut Owner di awal — setelah dicek ulang, 24 adalah jumlah aktual model yang terdaftar; diperluas ke semua supaya konsisten, tidak ada tabel yang "kelewat" tanpa audit trail siapa-hapus)
- Trait baru `app/Traits/FillsDeletedBy.php` — daftarkan listener `static::deleting()` yang isi `deleted_by` via raw `DB::table($table)->update(['deleted_by' => auth()->id()])` SEBELUM soft-delete-nya sendiri jalan. Dipilih raw query (bukan `$model->save()`) karena `SoftDeletes::runSoftDelete()` Laravel sendiri JUGA raw-update kolom `deleted_at`/`updated_at` langsung ke query builder — kalau saya pakai `$model->save()` di listener, attribute lain yang dirty tidak sengaja bisa ikut ke-flush duluan. Dua raw update terpisah (kolom beda) di record yang sama, tidak saling konflik
- Skip otomatis kalau `isForceDeleting()` true (baris akan hilang total, tidak ada gunanya diisi)
- Trait ditempel ke SEMUA 24 model (`use SoftDeletes, HasAuditLog, FillsDeletedBy;`) — zero titik yang bisa kelewat, dan Service delete existing (`StokService::resetStok()`, `SetoranController::destroy()`, `CascadeDeleteService::deleteXxxCascade()`, `CutiController::destroy()`, dll) otomatis ter-cover TANPA disentuh sama sekali — diverifikasi eksplisit lewat 4 skenario regression test (lihat Verifikasi)
- **Insiden kecil saat apply:** percobaan pertama nambah `use App\Traits\FillsDeletedBy;` di 24 file model pakai `sed` di Git Bash gagal silent (backslash di path namespace `App\Traits\...` kemakan escaping shell) — trait terpasang di `use HasFactory, ...;` tapi importnya kosong, ketauan pas verifikasi ulang (bukan pas `php -l`, karena itu cuma cek syntax bukan resolve symbol). Diperbaiki manual 24x pakai Edit tool, diverifikasi ulang dengan pattern match yang benar (`grep -F`, bukan regex biasa)

#### 2. Halaman Detail (`/trash/{model}/{id}/detail`)
- `TrashController::detail()` — load record `onlyTrashed()->with($relations)`, resolve `deleted_by` → nama user, query `Activity::where('subject_type', $modelClass)->where('subject_id', $id)` untuk activity log
- **10 model "kritis"** dapat tampilan kustom (bukan 100% sesuai daftar awal Owner — 2 substitusi dijelaskan ke Owner sebelum apply): Order (items+cabang+kasir+pelanggan+kas), Kas (10 riwayat transaksi terakhir), TransaksiKeuangan (+resolusi referensi polymorphic, +deteksi baris Setoran via `kategoriDinamis->kode`), PurchaseOrder (items+supplier), Stock, Item, Pelanggan, Karyawan, Asset, Cuti. Sisa ~14 model pakai fallback generik (tabel key-value seluruh atribut, exclude `password`/`remember_token`)
- **Temuan kritis saat audit (dikonfirmasi ke Owner sebelum apply):** `ResepBumbu` (salah satu dari 10 model kritis yang diminta Owner) TERNYATA tidak pernah punya `SoftDeletes` sama sekali (dikonfirmasi dari sesi sebelumnya: "sengaja tidak ada tabel `_histories` dan tidak pakai `HasAuditLog`") — tidak mungkin masuk Data Terhapus karena tidak pernah ter-soft-delete. Diganti dengan `Asset` sebagai model kritis ke-10. "Setoran" juga bukan tabel terpisah (per rule bisnis #39: pasangan OUT/IN di `transaksi_keuangans`) — ditangani sebagai case khusus di dalam tampilan detail TransaksiKeuangan, bukan entry model sendiri
- **Bug laten yang ditemukan & dihindari:** `TransaksiKeuangan.referensi_type` disimpan sebagai string logis (`'order'`/`'purchase_order'`/`'kas'`/`'stock_movement'`), BUKAN FQCN, dan tidak ada morphMap terdaftar di project ini — kalau halaman detail asal panggil `$record->referensi` (relasi `morphTo()` bawaan Eloquent), akan FATAL ERROR (`Class "order" not found`, karena Eloquent coba instansiasi class bernama literal `order`). Resolusi manual di controller (`resolveReferensi()`, `match()` + try/catch per tipe), tidak pernah akses `$record->referensi` langsung di Blade

#### 3. Modal Hapus Permanen — 2 Tahap
- Partial baru `trash/_modal-hapus-permanen.blade.php` — 1 modal, 2 "pane" digantikan via JS `style.display` (bukan submit ganda): Tahap 1 preview data + peringatan "tidak bisa dibatalkan" + tombol "Lanjut" (murni pindah pane, TIDAK submit form). Tahap 2 input teks, tombol submit disabled sampai teks match persis `HAPUS PERMANEN` (case-sensitive, `.trim()` di JS)
- Dipakai bareng oleh `trash/index.blade.php` (tombol per baris) dan `trash/detail.blade.php` (tombol aksi) via 1 fungsi JS `bukaModalHapusPermanen(actionUrl, namaData)` — konsisten dengan pola "1 modal dipakai bareng semua baris" yang sudah ada di modal Reset Stok, tapi dengan tambahan tahap preview yang diminta Owner
- Restore TETAP pakai `confirm()` browser 1 langkah (sesuai brief — cuma Hapus Permanen yang butuh 2 tahap)

#### 4. TIDAK Diubah (Sesuai Prinsip "Jangan Ganggu")
- `TrashController::index()`/`restore()`/`forceDestroy()` — nol perubahan logic, cuma tambah 1 method baru (`detail()`) + 1 property baru (`$detailRelations`)
- Permission seeder untuk 3 izin `keamanan` — TIDAK diubah sama sekali (Owner eksplisit bilang "sudah default kosong", dikonfirmasi tetap benar: cuma `admin_pusat` yang punya `lihat_data_terhapus`, `restore_data_terhapus`/`hapus_permanen_data` tetap Owner-only, tidak disentuh)
- Tidak ada Service/Controller delete manual yang diedit — `deleted_by` 100% terisi lewat trait, sesuai instruksi Owner

#### Verifikasi (transaction-rollback test terhadap data produksi lokal)
- Migration: SEMUA 24 tabel terverifikasi punya kolom `deleted_by` (loop `Schema::hasColumn`)
- Trait: soft-delete baru → `deleted_by` terisi `auth()->id()` yang benar; tanpa auth (simulasi) → `NULL`; restore tetap jalan; `forceDelete()` tetap jalan tanpa error
- **4 regression test pada flow delete existing** (bukan test unit terisolasi, tapi manggil langsung Service/Controller sungguhan): `StokService::resetStok()` (batch ter-soft-delete + `deleted_by` benar), `CascadeDeleteService::deleteOrderCascade()` (order ter-soft-delete + `deleted_by` benar), `CutiController::destroy()` (`deleted_by` benar) — SEMUA lolos tanpa perubahan kode di service-service tersebut
- Halaman Detail: di-render langsung (bukan cuma dicek routing) untuk Order/Kas/TransaksiKeuangan/PurchaseOrder/Cuti/Karyawan (kritis) + Shift (fallback generik) — semua render tanpa error
- Edge case: akses detail untuk ID yang tidak ada → `ModelNotFoundException` (404) terverifikasi; akses oleh role tanpa `lihat_data_terhapus` → 403 terverifikasi
- Blade compile check untuk semua file view yang diubah/ditambah — semua OK

**Tabel DB baru/diubah (sesi ini):** 24 tabel (lihat daftar di `TrashController::$models`) +kolom `deleted_by` (unsignedBigInteger nullable, tanpa FK). Migration `2026_07_28_000001_add_deleted_by_to_trash_tables`.

**Catatan kompatibilitas:** Data soft-delete lama (`deleted_by` NULL) tampil "Sistem tidak mencatat" di halaman Detail — bukan bug. Seluruh alur delete/restore/forceDelete existing di semua modul (POS batal order, hapus PO, reset stok, hapus transaksi, dll) **tidak berubah perilakunya sama sekali** — perubahan HANYA menambah 1 kolom pasif (diisi otomatis via trait) dan 1 halaman baru (detail), tidak ada logic bisnis existing yang disentuh.

---

### 2026-07-27 — Audit + Enforce Rule Permission & Data Terhapus untuk Semua Menu

Latar: Owner minta audit menyeluruh apakah setiap menu/fitur sudah punya permission granular yang benar-benar ditegakkan (bukan cuma didefinisikan) dan apakah setiap tabel yang bisa diubah/dihapus sudah terdaftar dengan benar di Data Terhapus. Audit menemukan gap CRITICAL: beberapa modul inti (Keuangan, Aset, BEP, Stock Request/Transfer) sama sekali tidak punya pengecekan permission di controller walau permission-nya sudah ada di `PermissionSeeder` dan sudah di-assign ke role di `RolePermissionSeeder` — "permission ada di kertas, nol enforcement".

#### Temuan CRITICAL — Permission Terdefinisi tapi Tidak Pernah Dicek
- `KeuanganController`: `index()`, `create()`, `store()`, `kas()`, `storeKas()`, `updateKas()`, `laporan()` — nol gate. Siapapun yang login (role apapun) bisa lihat SEMUA transaksi keuangan lintas cabang, tambah transaksi manual, dan yang paling berisiko: kelola Kas termasuk kolom `default_untuk` yang menentukan kas mana yang dipotong saat checkout POS
- `AssetController`: nol gate di SEMUA method (index/show/create/store/edit/update/destroy/hitungPenyusutan/tambahMaintenance/disposal)
- `BepController`: nol gate di SEMUA method — siapapun bisa ubah setting biaya tetap/produk BEP cabang manapun
- `StokController`: `index()`/`kartu()` nol gate (`adjustmentForm()`/`adjustmentStore()` ternyata SUDAH benar tergate `stok.adjustment` dari sesi sebelumnya — brief awal salah asumsi titik gap-nya)
- `StockRequestController` & `StockTransferController`: nol gate di semua method — siapapun bisa approve/kirim/batalkan permintaan & transfer stok lintas cabang

Fix: tambah `abort_unless(auth()->user()->can('...'), 403)` di setiap method, pakai permission yang SUDAH ADA (tidak bikin permission baru untuk kasus ini karena sudah lengkap): `keuangan.view/create/kas_view/kas_create/kas_edit`, `aset.view/manage`, `bep.view/manage`, `stok.view`, `stok.request`, `stok.transfer`. Untuk `approve()`/`terima()` di Stock Request/Transfer, pemilihan permission disesuaikan dengan siapa yang benar-benar memegangnya di `RolePermissionSeeder` (mis. `approve()` pakai `stok.transfer` karena itu yang dipegang admin_gudang, bukan `stok.request` yang tidak dia punya) — supaya nol regresi ke alur approval yang sudah jalan.

#### Regresi yang Ditemukan & Diperbaiki Saat Apply (Tombol UI Tanpa Permission Check)
Setelah gate controller dipasang, ditemukan beberapa tombol/link di Blade yang SEBELUMNYA tidak di-`@can`-wrap (karena memang belum pernah perlu, wong controllernya juga belum digate) — kalau dibiarkan, role yang kurang permission akan lihat tombol yang berujung 403:
- "Tambah Transaksi" & "Laporan" di `keuangan/index.blade.php` dan sidebar "Laporan Keuangan" — kasir punya `keuangan.view`+`keuangan.kas_view` tapi TIDAK punya `keuangan.create`/`laporan.view`, jadi 2 tombol ini digate ulang match permission controller
- "Tambah Kas" di `keuangan/kas.blade.php` — kasir tidak punya `keuangan.kas_create`
- "Tambah Aset", tombol Edit, form Hitung Penyusutan/Tambah Maintenance/Disposal di `aset/index.blade.php` & `aset/show.blade.php` — manajer_cabang & admin_gudang cuma punya `aset.view`, bukan `aset.manage`
- Semua tombol/form di `bep/setting.blade.php` (Isi Otomatis, Buat Setting, Tambah/Hapus Biaya Tetap, Tambah/Hapus Produk, Hitung Ulang) — manajer_cabang cuma punya `bep.view`
- Tombol "Buat Permintaan"/Batalkan di `stok/request/*.blade.php`, form Terima di `stok/transfer/show.blade.php` — kasir tidak punya `stok.request`/`stok.transfer` sama sekali

Semua digate ulang dengan `@can`/`@canany` yang match persis permission yang dicek controller — prinsipnya: kalau backend menolak, frontend jangan menampilkan tombolnya (Rule #40 poin 4).

#### HIGH — Cuti: Nol Permission + Nol Soft Delete
`CutiController` (index/create/store/edit/update/destroy/approve/tolak) nol gate sama sekali, dan model `Cuti` hard-delete (tidak ada `SoftDeletes`/`HasAuditLog`) — hapus pengajuan cuti = hilang permanen tanpa jejak. Fix:
- 4 permission baru: `cuti.view`, `cuti.create`, `cuti.approve`, `cuti.delete` (group `hr`)
- Migration tambah `deleted_at` ke tabel `cutis` + trait `SoftDeletes`+`HasAuditLog` di model
- Role default: `admin_pusat`+`manajer_cabang` dapat keempatnya (approval level); `admin_gudang`/`kasir`/`operator_produksi` dapat `view`+`create`+`delete` (SENGAJA termasuk `delete` — panduan Cara Pakai slug `cuti` sudah mendokumentasikan "Pengajuan yang masih Pending bisa dibatalkan sendiri oleh karyawan pengaju", jadi `cuti.delete` tidak dibatasi ke level manajer saja supaya fitur self-cancel yang sudah didokumentasikan tidak regresi); `helper` cuma `view` (konsisten dengan footprint helper yang minimal di seluruh sistem)
- Terdaftar di `TrashController::$models` sebagai `cutis`

#### HIGH — Perbaikan Registry `TrashController::$models`
- **Dihapus:** `order_items` (`OrderItem`) dan `face_attendances` (`FaceAttendance`) — keduanya terdaftar tapi modelnya TIDAK pakai trait `SoftDeletes` (kolom `deleted_at` di tabel cuma keisi lewat cascade raw-query dari parent Order/Karyawan, bukan lewat model sendiri). Akibatnya tab-nya di UI selalu kosong (`onlyTrashed()` throw exception yang di-catch diam-diam di `index()`) dan `restore()` individual akan 500 kalau dipaksa dipanggil (tidak ada try/catch di situ)
- **Ditambahkan:** `stock_batches` (`StockBatch`) dan `recurring_transaksis` (`RecurringTransaksi`) — keduanya SUDAH pakai `SoftDeletes`+`HasAuditLog` sejak dibuat tapi belum pernah didaftarkan
- Tab label di `trash/index.blade.php` (`$modelLabels`) disamakan dengan registry controller — sekalian menambahkan tab yang HILANG untuk `kas` dan `kategori_transaksis` (sudah terdaftar di controller dari sesi sebelumnya tapi tidak pernah dapat tab UI, jadi cuma bisa diakses lewat URL manual `?model=kas`)

#### MEDIUM — Evaluasi 360 & Laporan Lintas Modul
- `EvaluationController::result()`/`history()` sebelumnya bisa diakses siapa saja yang login tanpa cek apapun — skor 360 karyawan lain (termasuk agregat skor rekan kerja yang seharusnya anonim di level individu) bisa dilihat asal tahu/tebak ID. Gate dengan `evaluasi.view` ATAU karyawan yang bersangkutan sendiri ATAU reviewer yang ditugaskan untuk evaluasi itu (supaya alur existing tetap jalan — reviewer diarahkan ke halaman `result` setelah submit penilaian). Link "Lihat Detail" evaluasi di `karyawan/show.blade.php` ikut digate match logic yang sama
- `LaporanPenjualanController::index()` dan `LaporanStokController` (index/pergerakan/minimum) ditambah `laporan.view` — sebelumnya nol gate
- `LaporanHRController` (absensi/penggajian/evaluasi) & `LaporanAsetController` digate pakai permission SPESIFIK (`absensi.view`/`penggajian.view`/`evaluasi.view`/`aset.view`) alih-alih `laporan.view` generik — supaya role yang kebetulan punya `laporan.view` luas (mis. admin_gudang) tidak otomatis kebagian akses laporan HR/payroll yang sensitif
- `LaporanBepController` digate `bep.view` — sidebar-nya ternyata SUDAH pakai permission granular ini sejak sesi sebelumnya, tapi controllernya sendiri belum pernah menegakkannya (front-end sudah benar, back-end baru sekarang menyusul)

#### Verifikasi (transaction-rollback + panggilan controller langsung terhadap data produksi lokal)
- Positive/negative check permission per role (Owner bypass, admin_pusat/manajer_cabang/kasir/admin_gudang/operator_produksi) untuk `cuti.*`, `keuangan.*`, `aset.*`, `bep.*`, `stok.request`/`stok.transfer` — semua sesuai desain RolePermissionSeeder
- `Cuti::create()` → soft-delete → `onlyTrashed()` ketemu → `restore()` → aktif lagi — semua jalan normal
- Verifikasi via Reflection: SEMUA model di `TrashController::$models` sekarang benar-benar punya trait `SoftDeletes` (nol WARNING)
- Render langsung (bukan cuma cek permission) halaman `keuangan/index`, `keuangan/kas`, `aset/index`, `bep/index`, `bep/setting`, `stock-request/index`, `stock-transfer/index` untuk role kasir/manajer_cabang/admin_gudang — semua render tanpa error, tombol yang harusnya hilang (403-prone) benar-benar tidak muncul untuk role tanpa permission
- Blade compile check untuk semua 13 file view yang diubah — semua OK

**Tabel DB baru/diubah (sesi ini):** `cutis` +kolom `deleted_at` (migration `2026_07_27_000001_add_soft_deletes_to_cutis_table`). Tidak ada perubahan skema lain.

**Catatan kompatibilitas:** POS checkout, approval PO, dan seluruh alur transaksi normal untuk role dengan permission yang sudah benar (admin_pusat, manajer_cabang untuk modulnya masing-masing) **tidak berubah sama sekali** — perubahan HANYA menutup akses untuk role yang SEHARUSNYA tidak punya izin (sesuai desain `RolePermissionSeeder` yang sudah ada), plus menyembunyikan tombol yang match. Beberapa temuan dari brief awal ternyata SUDAH diimplementasikan dengan benar di sesi-sesi sebelumnya (tidak diulang untuk hindari permission ganda/duplikat): Adjustment Stok sudah tergate `stok.adjustment`, `TrashController` sudah tergate 3 permission `keamanan` (`lihat_data_terhapus`/`restore_data_terhapus`/`hapus_permanen_data`), `TransaksiKeuangan` sudah terdaftar di trash registry, dan Master Item/Kategori sudah punya permission `item.*`/`kategori.*` sendiri (tidak perlu `master.item.*` baru).

---

### 2026-07-27 — Fix SetoranController: Soft-Delete List Bug + Guard Orphan Destroy

Latar: audit lanjutan dari investigasi selisih kas Kas Tembung menemukan setoran "kekurangan Pengeluaran" (Rp16.000) yang sudah dihapus Owner tetap tampil di menu Setoran ke Pusat dengan kolom "KE" kosong. Digali lebih dalam, ketemu 2 bug nyata + 1 insiden data corrupted yang sempat berisiko ke setoran Modal Awal (Rp198,3 juta).

#### Bug A — Setoran Terhapus Tetap Tampil di List
- Root cause: `SetoranController::index()` pakai `withoutGlobalScopes()` tanpa argumen — pola bug yang SAMA persis dengan Bug 1 sesi 2026-07-26 (`LaporanKeuanganController`/`SetoranHarianService`/`KeuanganController::index()`), tapi `SetoranController` belum sempat kena fix waktu itu (di-flag "40+ titik lain, out of scope")
- Fix: ganti ke `withoutGlobalScope(\App\Models\Scopes\CabangScope::class)` — pola aman yang sudah jadi konvensi di codebase ini

#### Bug B — `destroy()` Silently Proses Satu Sisi Kalau Pasangan Orphan
- Root cause: `resolveSetoranOut()` cari pasangan HANYA lewat kolom `setoran_pair_id`, tidak ada fallback apapun. Kalau `status_setoran='diterima'` (kedua kas sudah kepengaruh saat setoran dibuat) tapi pasangannya tidak ketemu (`setoran_pair_id` sudah NULL/rusak), kode lama diam-diam cuma membalik kas asal dan SKIP kas tujuan — kas asal jadi over-credit, kas tujuan tidak pernah dikoreksi
- **Insiden nyata yang memicu temuan ini:** setoran "kekurangan Pengeluaran" id 245/246 jadi orphan setelah salah satu sisi (245) di-restore langsung lewat database (bypass `TrashController`, tidak ada log activity untuk transisi restore ini) tanpa merekonstruksi `setoran_pair_id`. Saat Owner hapus lagi via UI, Bug B ke-trigger: kas Head Office ke-kredit 2x, kas Tembung tidak ikut dikoreksi
- **Risiko yang lebih besar ditemukan saat audit:** pasangan setoran Modal Awal (id 231/232, Rp198.329.749) — yang sempat ikut ke-restore Owner dalam insiden yang sama — SEKARANG JUGA orphan (`setoran_pair_id` NULL) walau kedua sisi aktif. Kalau ada yang coba hapus pasangan ini SEBELUM fix di-deploy, bug yang sama akan kejadian lagi tapi di angka Rp198 juta
- Fix: guard eksplisit SEBELUM `DB::transaction()` — kalau status `diterima` tapi pasangan (`$trxIn`) tidak ketemu, `return back()->with('error', ...)` tanpa mutasi apapun. Guard sengaja spesifik ke status `diterima` saja — status lain tidak butuh `$trxIn` untuk membalik saldo dengan benar, jadi tidak ikut digate (backward compat terjaga)

#### Verifikasi (transaction-rollback test terhadap data produksi lokal)
- List Setoran: setoran soft-deleted (id 245) hilang dari list, setoran aktif (id 231, Modal Awal) tetap tampil normal
- `destroy()` pada setoran orphan (231): DITOLAK dengan pesan error jelas, kas 3 & kas 16 tidak berubah sama sekali, transaksi tetap aktif (tidak jadi terhapus)
- `destroy()` pada setoran normal (pair lengkap, dibuat sintetis): tetap berhasil, kedua kas ter-reverse dengan benar seperti sebelumnya

**Tabel DB baru/diubah (sesi ini):** tidak ada — murni logic fix di 1 controller, tidak ada migration.

**Catatan kompatibilitas:** Data historis (termasuk setoran orphan 231/232/245/246) TIDAK disentuh oleh fix ini — pembersihan datanya murni aksi manual Owner via UI (Data Terhapus) setelah deploy, sesuai prinsip "jangan sentuh data historis via query otomatis".

---

### 2026-07-27 — Fitur Sembunyi Harga Per Item di Struk (Kontrol Per Cabang)

Latar: Owner minta fitur struk bisa sembunyikan harga per item (cuma tampil total) untuk hindari komplain pelanggan soal harga bahan tertentu — 2 level kontrol: Owner set izin per cabang, kasir toggle per transaksi di POS.

#### Audit Kunci — Koreksi 3 Asumsi Brief Sebelum Apply
- **`auth()->user()->cabang` tidak ada** di codebase ini (User multi-cabang via pivot `cabang_user`, bukan belongsTo tunggal) — dipakai pola existing `session('active_cabang_id') ?? $authUser->defaultCabangId()` yang sudah konsisten di 4 tempat lain `PenjualanController`
- **Struk dirender di 3 tempat, bukan 1**: HTML preview `struk.blade.php` + HTML preview `_struk_modal_content.blade.php` (modal in-place, jalur utama kasir) + **JS builder ESC/POS mentah untuk Bluetooth thermal printer** di dalam kedua file itu — brief awal cuma sebut blade HTML, kalau cuma itu yang diubah maka struk yang BENAR-BENAR tercetak fisik (Bluetooth) tetap tampilkan harga karena builder ESC/POS baca sumber data JS terpisah (`STRUK.items[i].harga`, bukan `$item->harga_satuan`)
- Section "Struk POS" di form Edit Cabang **sudah ada** (dari fitur `footer_struk`) — field baru ditaruh di situ, bukan bikin section baru

#### Implementasi
- Kolom baru `cabangs.izinkan_sembunyi_harga_struk` (boolean, default `false`, zero risk regresi untuk cabang existing)
- Checkbox di Edit/Create Cabang (section Struk POS, pola hidden-input-0 sama seperti `antrian_produksi_aktif`/`running_text_aktif`) — Owner-only via `CabangRequest::authorize()` yang sudah ada
- Checkbox conditional di POS (`@if($cabangAktif?->izinkan_sembunyi_harga_struk)`) dekat tombol Proses, default **UNCHECKED** (sembunyi harga)
- **Value TIDAK disimpan ke `orders`/`order_items` sama sekali** — murni preferensi cetak yang mengalir: checkbox POS → `FormData` checkout → di-echo balik di response JSON `PenjualanController::store()` (pola sama seperti `copies`) → `openStrukModal(orderId, copies, tampilHargaStruk)` → query string `struk-modal?tampil_harga_struk=X` → `PenjualanController::shouldShowItemPrice()` hitung `$showItemPrice`
- Ketiga tempat render (2 HTML blade + JS ESC/POS builder di masing-masing) diubah SEKALIGUS supaya konsisten — item hidden mode cuma tampil nama+qty, TOTAL/Subtotal (level order) tetap selalu tercetak di kedua mode
- **`penjualan/show.blade.php` (Detail Order) SAMA SEKALI TIDAK DISENTUH** — selalu tampil harga penuh untuk audit trail, terlepas dari setting ini
- **Konsekuensi by-design (disetujui Owner):** karena preferensi tidak persist, "cetak ulang" dari Riwayat Penjualan/Detail Order (yang panggil `openStrukModal()` cuma 2 argumen) otomatis default sembunyi harga (kalau cabang mengizinkan) — kasir perlu centang ulang manual kalau mau tampil harga lagi saat cetak ulang

Lihat rule bisnis #37 untuk detail teknis lengkap.

**Tabel DB baru/diubah (sesi ini):** `cabangs` +kolom `izinkan_sembunyi_harga_struk` (boolean, default false).

**Catatan kompatibilitas:** `PenjualanService::buatOrder()`, `StokService`, kalkulasi total/HPP/kas/keuangan, struktur `order_items`, dan halaman Detail Order **tidak disentuh sama sekali** — cabang yang tidak mengizinkan (semua cabang existing, default) 100% behavior lama tanpa perubahan apapun.

---

### 2026-07-26 — Audit + Fix 3 Bug Production (Laporan Keuangan, Dashboard PO, Selisih Kas) + Cleanup Tool

Latar: Owner audit dengan DB production hasil deploy (146 transaksi, 11 PO, 5 kas, di-import ke lokal untuk investigasi). 3 bug dikonfirmasi via query real + baca kode, semua diperbaiki logic-nya + dibuatkan UI cleanup tool untuk data historis (bukan SQL manual).

#### Bug 1 — Laporan Keuangan Baca Transaksi dari Order Dibatalkan sebagai Omzet
- Root cause: `withoutGlobalScopes()` (tanpa argumen) di `LaporanKeuanganController::labaRugi()`/`arusKas()` dan `SetoranHarianService::baseTransaksiQuery()` mematikan `SoftDeletingScope` model `TransaksiKeuangan` — transaksi yang sudah di-soft-delete (mis. saat order dibatalkan, lihat `PenjualanService::batalkan()`) ikut ke-SUM lagi sebagai pemasukan
- **Ditemukan titik ke-4** yang sama saat audit lanjutan: `KeuanganController::index()` (`$ringkasanCabang`, breakdown per-cabang di halaman utama Menu Kas & Keuangan mode "Semua Cabang") — di-include sekalian sesuai approval Owner
- Fix: ganti ke `withoutGlobalScope(\App\Models\Scopes\CabangScope::class)` (bentuk tunggal, matching pola aman yang sudah dipakai 40+ tempat lain di codebase) — lihat rule bisnis #34 untuk detail lengkap kenapa ini aman (CabangScope tidak pernah benar-benar terdaftar sebagai global scope)
- **Sengaja TIDAK disentuh:** `PenjualanService::kembalikan()` (butuh cari row soft-deleted untuk restore) dan `LaporanSetoranController` (sengaja tampilkan setoran ditolak/dibatalkan) — beda semantik, bukan bug
- **Sengaja di luar scope:** 40+ titik lain dengan pola sama (Order/Karyawan/Absensi/PurchaseOrder di berbagai controller) — flagged untuk audit lanjutan sesi berikutnya, tidak diubah sesi ini

#### Bug 3 — `kas_id` Nullable Bikin Transaksi Tidak Pernah Sentuh Saldo Kas
- Root cause: `kas_id` di `transaksi_keuangans` nullable, increment/decrement `saldo_sekarang` cuma jalan `if ($request->kas_id)` — transaksi tanpa kas tetap ke-SUM penuh di Laporan Keuangan tapi TIDAK PERNAH mengurangi/menambah saldo kas manapun (Menu Kas jadi lebih tinggi dari seharusnya)
- Fix: `KeuanganController::store()`/`update()` — validasi `kas_id` jadi `required` (form UI ikut diupdate, asterisk merah, opsi "Tanpa Kas" dihapus)
- `PenjualanService::buatOrder()` — kalau Kas default untuk cabang+tipe_pembayaran tidak ketemu, **throw Exception jelas** ("Cabang belum punya Kas default untuk pembayaran X...") — BUKAN diam-diam simpan `kas_id=NULL` seperti sebelumnya. **Prasyarat sebelum deploy:** setiap cabang WAJIB punya kas aktif untuk setiap tipe pembayaran yang benar-benar dipakai (dikonfirmasi Owner sudah reaktivasi Kas Bank BRI + Kas QRIS BCA cabang BM Tembung sebelum fix ini live)
- Lihat rule bisnis #35 untuk detail lengkap (termasuk pengecualian `StokService::adjustment()` yang SENGAJA tetap `kas_id=null` untuk beban non-cash)

#### Bug Bonus — `saldo_sekarang` vs `saldo_awal` (Ternyata Bukan Bug Kode)
- Audit awal menduga `KasController::store()` tidak initialize `saldo_sekarang` dari `saldo_awal` — **setelah dicek, kode (`KeuanganController::storeKas()`) SUDAH BENAR**: `saldo_sekarang = saldo_awal` saat create + otomatis bikin `TransaksiKeuangan` pendamping (`kategori=SaldoAwal`, `referensi_type='kas'`). A3 (fix logic) **dibatalkan** — 2 kas yang selisih (id cabang Head Office & BM Tembung) ternyata data legacy yang dibuat SEBELUM logic ini ada (kemungkinan insert manual/seed pre-go-live), bukan bug yang sedang aktif terjadi
- Solusi: cukup UI Cleanup Tool "Sinkron Saldo" (lihat di bawah), tidak ada perubahan logic `storeKas()`

#### Bug 2 — Dashboard PO "Belum Dibayar" padahal Sudah Dibayar (UX Preventif)
- Root cause tetap sama seperti audit sebelumnya (kasir bayar via Kas Keluar manual tanpa dropdown "Pilih PO", `referensi_type`/`referensi_id` tidak pernah terisi) — logic deteksi `PoDashboardService::fetchBelumDibayar()` sendiri sudah benar, cuma titik inputnya yang perlu dibantu
- Fix UX preventif (bukan logic): dropdown "Pilih PO" di form Kas Keluar auto-highlight (border kuning + hint alert) begitu user pilih Kategori "Pembelian Bahan Baku" (`kode='PBB'`) — murni visual, tidak mengubah validasi/behavior simpan

#### UI Cleanup Tool — Owner-only, Verifikasi Manual, BUKAN Query SQL Massal
3 permission baru (`transaksi.link_po.action`, `transaksi.assign_kas.action`, `kas.sinkron_saldo.action`, group `keuangan`) — sengaja TIDAK di-assign default ke role manapun, `RolePermissionSeeder` tidak disentuh. Lihat rule bisnis #37 untuk detail lengkap. Ringkasan:
1. **Link Transaksi ke PO** (Detail PO → "Cari Transaksi Existing") — cari kandidat transaksi Pengeluaran belum ter-link, ± 3 hari dari tanggal terima, badge hijau untuk nominal exact match, Owner klik Link satu-satu
2. **Assign Kas** (Daftar Transaksi → card "Transaksi Tanpa Kas Sumber") — list transaksi `kas_id IS NULL AND referensi_type IS NULL`, modal pilih Kas per baris, saldo kas otomatis ter-adjust
3. **Sinkron Saldo Kas** (Kelola Kas → tombol per kartu) — preview selisih (formula dual-path, lihat rule #36) sebelum Owner konfirmasi apply

Semua 3 aksi: activity log terpisah (`activity('...')->log(...)`), tidak ada auto-fix/query massal — murni tool bantu Owner cleanup satu-satu dengan verifikasi manual.

**Tabel DB baru/diubah (sesi ini):** tidak ada — murni logic fix + 3 permission baru + views baru, skema `transaksi_keuangans`/`kas`/`purchase_orders` tidak disentuh sama sekali.

**Catatan kompatibilitas:** Alur checkout POS, Kas Keluar manual, approve/kirim/terima PO — semua tetap jalan seperti biasa untuk kasus normal (kas terisi benar). Yang berubah HANYA: (1) laporan sekarang exclude transaksi soft-deleted dengan benar, (2) kasir WAJIB pilih kas saat checkout/Kas Keluar (sebelumnya opsional), (3) ada 3 tombol cleanup baru yang Owner-only dan tidak muncul untuk role lain kecuali di-grant manual.

---

### 2026-07-25 — Fix Adjustment Stok Alasan Koreksi + Tombol Hapus Stok

Latar: audit menemukan SEMUA 6 alasan Adjustment Stok (susut/rusak/hilang/salah_hitung/audit/lainnya) selalu bikin `transaksi_keuangan` tanpa pengecualian — padahal `salah_hitung`/`audit` murni koreksi data (stok fisik tidak pernah berubah), bukan kejadian ekonomi riil. Owner juga butuh tombol reset qty ke 0 untuk cleanup data test pre-go-live.

#### 1. Temuan Kritis Audit — Koreksi Pseudocode Brief Sebelum Apply
- **`stock_id` tidak ada** di `stock_batches` maupun `stock_movements` — relasi ke `stocks` selalu via `item_id`+`lokasi_id`, bukan FK langsung. Pseudocode awal brief (`DELETE ... WHERE stock_id`) tidak match struktur DB real
- **`stock_movements` tidak punya `deleted_at`** — tidak bisa di-soft-delete sama sekali. Kalau ikuti pseudocode literal (hard DELETE), itu **permanen menghapus seluruh riwayat pergerakan fisik item** (termasuk histori PO diterima & penjualan lama, bukan cuma data test) — flagged sebelum apply, disetujui Owner untuk diubah pendekatannya
- **Resolusi disetujui:** `stock_batches` di-soft-delete (reversibel via menu Data Terhapus existing), `stock_movements` **tidak disentuh sama sekali** — cuma ditambah 1 movement baru sebagai jejak reset

#### 2. Fix Logic Adjustment (`StokService::adjustment()`)
- Variabel `$catatKeKeuangan` dihitung sekali di awal transaction closure: `true` untuk `susut`/`rusak`/`hilang`, `false` untuk `salah_hitung`/`audit`, dan untuk `lainnya` ikut opsi baru `$options['catat_sebagai_biaya']` (default `false` — aman, backward compatible karena opsional di array `$options` yang sudah ada, bukan parameter baru)
- Gate ditambahkan di **KEDUA** titik `TransaksiKeuangan::create()` (arah turun DAN naik) — `if ($nilaiTransaksi > 0 && $catatKeKeuangan)` — stock_movement, stock_batch, dan update `stocks.qty` tetap jalan normal terlepas dari alasan (cuma sisi keuangan yang di-skip)
- Diverifikasi via 7 skenario sintetis (transaction-rollback): susut/rusak/hilang → transaksi dibuat ✅; salah_hitung/audit → tidak ✅; lainnya tanpa centang → tidak ✅; lainnya + centang → dibuat ✅

#### 3. UI Form Adjustment — Hint Dinamis + Checkbox
- Badge di bawah dropdown Alasan, update live via JS: merah "Akan tercatat sebagai biaya rugi" (susut/rusak/hilang) atau biru "Cuma koreksi data, tidak masuk Keuangan" (salah_hitung/audit)
- Checkbox "Catat sebagai biaya rugi?" muncul cuma untuk alasan `lainnya`, default UNCHECKED
- **Bug ditemukan & diperbaiki saat implementasi:** modal konfirmasi JS (`buildModalContent()`) sebelumnya SELALU menampilkan teks "Akan otomatis dicatat ke Keuangan" tanpa syarat — kalau tidak diperbaiki, teks ini akan berbohong ke user setelah fix backend diterapkan (bilang "akan masuk keuangan" padahal sebenarnya di-skip). Ditambah helper `alasanAkanKeKeuangan()` (mirror logic PHP) dipakai di modal konfirmasi supaya teksnya akurat sesuai alasan yang dipilih

#### 4. Tombol Hapus Stok — Fitur Terpisah (`StokService::resetStok()`)
- Soft-delete semua `stock_batches` aktif untuk item+lokasi itu (Eloquent biasa, BUKAN `withoutGlobalScopes()`, supaya `SoftDeletingScope` tetap aktif dan delete beneran soft — reversibel via Data Terhapus)
- Tambah 1 `stock_movements` baru (tipe `adjustment`, catatan `"RESET STOK oleh Owner: {qty} → 0"`) — riwayat movement LAMA sama sekali tidak disentuh/dihapus
- Set `stocks.qty = 0`, TIDAK PERNAH bikin `transaksi_keuangan`
- `activity('Stock')->log(...)` eksplisit di atas auto-log `HasAuditLog` bawaan `Stock`/`StockBatch` — diverifikasi: 4 activity log entry per reset (2× StockBatch deleted, 1× Stock auto-updated, 1× custom log message)
- Permission `stok.hapus.reset` (group `stok`) sengaja TIDAK di-assign default — diverifikasi: Owner bypass ✅, Manajer tanpa grant → tidak bisa ✅, Admin Gudang setelah di-grant manual → bisa ✅
- Konfirmasi UI: input teks harus PERSIS sama dengan nama item (case-sensitive) sebelum tombol submit aktif — divalidasi ulang di server (bukan cuma JS), diverifikasi nama salah → ditolak dengan pesan error + qty tidak berubah, nama benar → sukses

**Tabel DB baru/diubah (sesi ini):** tidak ada — murni logic service + 1 permission baru, `purchase_orders`/`stocks`/`stock_batches`/`stock_movements` struktur tidak disentuh.

**Catatan kompatibilitas:** Adjustment lama (data historis) tetap valid tanpa perubahan — fix ini cuma mengubah behavior adjustment BARU ke depan. `LaporanKeuangan`, `LaporanPenjualan`, dan Kartu Stok diverifikasi ulang render normal setelah semua perubahan.

---

### 2026-07-24 — Marathon PO Tools (Pilih PO + Widget + Dashboard PO Full)

Latar: Owner butuh efisiensi procurement + monitoring PO end-to-end. 3 fitur sekaligus: (1) helper dropdown "Pilih PO" di Kas Keluar, (2) widget "Status PO" di Dashboard utama, (3) halaman Dashboard PO Full dengan filter/chart/quick action.

#### 1. Temuan Audit Kunci — Reuse Pola Existing, Bukan Kolom Baru
- **Link PO↔Transaksi:** `transaksi_keuangans` SUDAH punya `referensi_type`/`referensi_id` (polymorphic, sudah dipakai `PenjualanService` untuk order) — REUSE persis (`referensi_type='purchase_order'`), **zero migration** untuk linking-nya. Brief awal minta kolom `po_id` baru, tapi ini akan bikin 2 mekanisme link paralel untuk konsep yang sama — dihindari
- **`tanggal_kirim` genuinely missing** — `approved_at` (untuk approve) dan `tanggal_terima` (untuk terima) SUDAH ada & terisi, tapi `kirimSupplier()` cuma update status tanpa timestamp. Tambah 1 kolom nullable + 1 baris di `PurchaseOrderService::kirimSupplier()` — **satu-satunya sentuhan** ke flow existing (approve/terima sama sekali tidak disentuh), murni additive
- **`suppliers` tidak punya kolom "tipe"** — "kategori auto-detect by tipe supplier" dari brief tidak bisa diimplementasi literal, disimplifikasi: selalu default ke kategori "Pembelian Bahan Baku" (dicari via `kode='PBB'`, BUKAN hardcode ID numerik — `kode` stabil lintas environment, ID auto-increment bisa beda)

#### 2. Fitur 1 — Helper "Pilih PO" di Kas Keluar
- Dropdown Select2 searchable (pola sama persis dengan pelanggan/resep bumbu) di atas form — pilih PO → auto-isi Tipe=Pengeluaran, Kategori="Pembelian Bahan Baku", Kategori Pengeluaran="Bahan Baku", Keterangan, dan Jumlah (via `data-rupiah-for="jumlah"` + dispatch event `input`, BUKAN sentuh id random dari komponen `<x-input-rupiah>`)
- Field tetap **fully editable** setelah auto-isi — prinsip helper tool, logic `KeuanganController::store()` cuma tambah validasi `po_id` opsional + cek existence sebelum simpan, tidak ubah apapun dari alur simpan manual
- **Cegah bayar dobel:** `TransaksiKeuangan::where('referensi_type','purchase_order')->where('referensi_id',$po_id)->exists()` sebelum create — diverifikasi via test: simpan pertama sukses, simpan kedua dengan PO sama DITOLAK dengan pesan error, transaksi tanpa PO tetap tersimpan normal (`referensi_type`/`referensi_id` NULL, backward compatible)
- Endpoint `GET /keuangan/po-pending-list` — list PO diterima yang belum dibayar, filter cabang aktif user, sekali fetch di-cache client-side (bukan fetch ulang per-PO saat onchange)

#### 3. Fitur 2 — Widget "Status PO" di Dashboard
- Component reusable `<x-po-status-widget>` dipasang di `dashboard/cabang.blade.php` + `pusat.blade.php` (BUKAN `gudang.blade.php` — sudah punya `$poAktif` count sendiri, sengaja tidak disentuh, dan `pusat()` juga sudah punya `$alertPembelianMendesak` terpisah, keduanya tidak diubah)
- `DashboardController::cabang()`/`pusat()` +method injection `PoDashboardService`, +1 variable `$poRingkasan` — widget lain di halaman yang sama tidak disentuh

#### 4. Fitur 3 — Dashboard PO Full (`PoDashboardService`, 6 method)
- **5 bucket ringkasan** — 3 spesifik per-status (Menunggu Approval/Perlu Dikirim/Dalam Perjalanan) + 1 ROLLUP ("Belum Diterima" = gabungan ketiganya, resolve ambiguitas brief yang sempat terlihat duplikat) + 1 payment-based (Belum Dibayar). Tanggal acuan umur MENGIKUTI status PO saat ini (draft→`created_at`, disetujui→`approved_at`, dikirim→`tanggal_kirim`, diterima-belum-bayar→`tanggal_terima`)
- Semua query pakai `DB::table()` raw (bukan Eloquent `withoutGlobalScopes()`) — `PurchaseOrder` pakai `SoftDeletes`, dan `withoutGlobalScopes()` blanket akan ikut strip `SoftDeletingScope` juga (bug laten yang ditemukan sebagai side-effect audit ini di service session sebelumnya yang pakai pola itu untuk `Order`) — raw query selalu eksplisit `whereNull('deleted_at')`
- **Bug ditemukan & diperbaiki saat testing:** `Carbon::diffInDays()` di environment ini mengembalikan `float` (bukan `int`), menyebabkan PHP 8.1 deprecation warning "Implicit conversion from float to int loses precision" saat dipassing ke parameter `badgeUmur(int $hari)` — fix: `(int)` cast eksplisit di titik komputasi, bukan mengandalkan implicit widening
- Tabel PO Aktif sengaja **tidak sum qty** untuk breakdown per kategori/order (beda satuan kg vs pcs, sama prinsip dengan Laporan Laba Rugi) — cuma level Per Item yang aman (1 grup = 1 item = 1 satuan)
- Quick action per PO cuma **navigate** ke halaman existing (Detail PO untuk approve/kirim/terima, Kas Keluar dgn `?po_id=X` untuk bayar) — tidak ada aksi langsung dari dashboard

#### 5. Permission & Sidebar
- 3 permission baru: `kas.pilih_po.view` (group keuangan), `po_dashboard.view`/`po_dashboard.action` (group pembelian) — sengaja tidak di-assign default, `RolePermissionSeeder` tidak disentuh
- Diverifikasi: Owner bypass ✅, Manajer tanpa grant → 403 ✅, Manajer dgn hanya `.view` → bisa akses tapi tombol aksi (@can po_dashboard.action) tersembunyi ✅
- Sidebar "Pembelian" wrapper diubah `@can('pembelian.view')` → `@canany(['pembelian.view','po_dashboard.view'])` (pola sama seperti section Keuangan) — link "Purchase Order" tetap `@can('pembelian.view')` sendiri di dalamnya, active-state link diperbaiki jadi eksplisit (`pembelian.index,pembelian.create,pembelian.show`) supaya tidak ikut nyala saat di halaman Dashboard PO (yang route name-nya `pembelian.po-dashboard.*`, technically match wildcard `pembelian.*` lama)

#### 6. Detail PO — Section "Status Pembayaran" Baru
- Untuk PO berstatus Diterima: badge hijau "Sudah Dibayar" + link transaksi, atau badge kuning "Belum Dibayar" + tombol "Catat Pembayaran" → `keuangan.create?po_id=X`
- `PurchaseOrderController::show()` +method injection `PoDashboardService` untuk cek status pembayaran — murni tampilan tambahan, data PO tidak disentuh

**Tabel DB baru/diubah (sesi ini):** `purchase_orders` +kolom `tanggal_kirim` (datetime nullable) +index `status` +index composite `(cabang_id, status)`. **Tidak ada kolom baru di `transaksi_keuangans`** (reuse `referensi_type`/`referensi_id` existing).

**Catatan kompatibilitas:** Approval flow, terima barang flow, menu PO existing, form Kas Keluar logic simpan, dan semua widget dashboard lain **tidak disentuh** — diverifikasi ulang render + regresi `LaporanPenjualan`/`LaporanKeuangan` setelah semua perubahan, tidak ada perubahan angka.

---

### 2026-07-24 — Laporan Laba Rugi + Kolom Untung di Laporan Konsumsi (Kombinasi A+B)

Latar: Owner butuh visibilitas profit — bandingkan omzet vs HPP bahan. Fitur A: extend Laporan Konsumsi Bahan Baku (kartu + kolom Omzet/Untung/Margin). Fitur B: menu baru Laporan Laba Rugi dengan 4 level breakdown (item/kategori/jenis olahan/order) + chart.

#### 1. Temuan Kritis Audit — Baris "Jasa Giling" Tanpa Item
Baris order_items untuk biaya jasa giling itu sendiri (bukan bumbu) biasanya punya `item_id = NULL` (kasir ketik nama bebas, tidak pilih dari master Item). `LaporanKonsumsiBahanService::baseQuery()` (existing) sengaja `INNER JOIN items` + exclude `item_id NULL` — benar untuk laporan "bahan baku", tapi kalau ditempel langsung ke kartu Total Omzet akan **understate** omzet real (hilang pendapatan jasa giling, salah satu dari 2 lini bisnis inti). **Resolusi:** kartu ringkasan (kedua laporan) pakai query LEBIH LUAS (`LEFT JOIN items`, method baru `hitungTotalOmzet()` di Konsumsi / `baseQuery()` di Laba Rugi keduanya LEFT JOIN), sementara tabel breakdown-per-item tetap `INNER JOIN` (tidak mungkin tampilkan baris "item" untuk sesuatu yang bukan item). Diverifikasi: kalau `SUM(breakdown) < Total Omzet ringkasan`, disclaimer info otomatis muncul di UI (pola sama seperti Setoran Harian "breakdown bisa beda dari total").

#### 2. Fitur A — Laporan Konsumsi Bahan Baku: +3 Kolom
- `LaporanKonsumsiBahanService::getRingkasan()` +field `total_omzet`/`total_untung`/`margin`; `getBreakdownPerItem()` +kolom `total_omzet`/`total_untung`/`margin` per baris (formula: `untung = omzet - hpp`, `margin = untung/omzet*100` atau `null` kalau omzet=0)
- View: +3 kartu ringkasan, +3 kolom tabel (sortable, warna hijau/merah sesuai untung positif/negatif), alert info kalau breakdown < ringkasan
- **Tidak ada perubahan skema DB** — omzet dihitung dari `order_items.total_harga` yang SUDAH tersimpan (dihitung `PenjualanService::buatOrder()` saat order dibuat: `qty * harga_satuan`), tidak perlu recompute

#### 3. Fitur B — Laporan Laba Rugi (Menu Baru)
- **Service baru** `LaporanLabaRugiService` — 8 method: `getRingkasan`, `getBreakdownPerItem/PerKategori/PerJenisOlahan/PerOrder`, `getTrendUntungBulanan`, `getTopUntung`, `getDetailTransaksi`. Base query LEFT JOIN items (beda dari Konsumsi yang INNER JOIN) — lihat rule bisnis #31
- **Kategori "jasa"**: `item_id NULL` ATAU `items.tipe='lainnya'` di-merge jadi 1 kategori sintetis `'jasa'` — **bug MySQL strict mode ditemukan & diperbaiki saat testing**: `GROUP BY` pada ekspresi `CASE WHEN...` ditolak `ONLY_FULL_GROUP_BY` walau identik dengan SELECT (`SQLSTATE[42000] ... isn't in GROUP BY`). Fix: `GROUP BY items.tipe` (kolom asli) lalu remap+merge kategori `jasa` di PHP (`Collection::groupBy()`), bukan di SQL
- Per Kategori/Per Jenis Olahan/Per Order **sengaja tidak ada kolom qty** — item dalam 1 kategori/order bisa beda satuan (kg vs pcs), menjumlahkan qty lintas satuan jadi angka tanpa arti (cuma Per Item yang aman sum qty, karena 1 grup = 1 item = 1 satuan)
- Omzet SELALU dari `SUM(order_items.total_harga)` di semua level (bukan `orders.total_bayar`) — konsisten lintas level, efek samping: diskon order tidak ter-refleksi di angka per-baris (dicek: 0 dari 7 order historis pakai diskon, dampak minim, didisclose di panduan — pola sama seperti "Produk Terlaris" di `LaporanPenjualanController` yang juga tidak prorate diskon)
- 4 chart: bar Top 10 untung, line trend 6 bulan (reuse fix overflow-bug dari Konsumsi), pie kontribusi kategori (khusus level Per Kategori, reuse pattern dari `laporan/kategori.blade.php`), + Detail Transaksi collapsible per tanggal (row-level, SEMUA order_items termasuk jasa giling)

#### 4. Permission Baru — Sengaja Tidak Ada yang Dapat Default
- `laporan.laba_rugi.view/print/export` — group `laporan`, `RolePermissionSeeder.php` TIDAK disentuh
- Diverifikasi 3 skenario (transaction-rollback): Owner bypass ✅, Manajer tanpa grant → 403 ✅, Manajer dengan hanya `.view` di-grant → akses index OK tapi print/export tetap 403 ✅

#### 5. Verifikasi Konsistensi Silang (Testing Requirement)
Diuji via data sintetis (order + 3 order_items: jasa giling `item_id NULL`, bumbu `bahan_baku`, `produk_jadi`) dalam transaction yang di-rollback: **kartu ringkasan Laba Rugi = kartu ringkasan Konsumsi Bahan Baku** (total_omzet & total_hpp match persis) untuk filter periode+cabang yang sama — syarat testing #10 terpenuhi.

**Tabel DB baru/diubah (sesi ini):** tidak ada — murni service/controller/view/permission/panduan baru + extend service existing, `order_items`/`orders`/`items` tidak disentuh sama sekali.

**Catatan kompatibilitas:** `LaporanPenjualanController`, `LaporanKeuanganController`, dan semua laporan existing lain **tidak disentuh** — diverifikasi ulang render setelah semua perubahan, tidak ada regresi.

---

### 2026-07-24 — Laporan Konsumsi Bahan Baku (GAP #1 Sesi 1b)

Latar: fondasi data untuk laporan ini sudah ada dari fitur FIFO Phase A (`order_items.hpp` terisi otomatis sejak Juni 2026) — task ini murni agregasi + display, tidak ada perubahan skema DB atau logic order/stok.

#### 1. Service Baru — `LaporanKonsumsiBahanService` (Murni READ)
- 4 method: `getRingkasan()`, `getBreakdownPerItem()`, `getTrendBulanan()`, `getDetailPerOrder()` — semua pakai `DB::table()` raw join (bukan Eloquent), pola PERSIS sama seperti `SetoranHarianService` (filter cabang eksplisit lewat parameter, bukan global scope Eloquent yang bisa diam-diam ikut membatasi hasil ke cabang aktif session)
- Sumber HPP = `order_items.hpp` (FIFO cost). `item_id` NULL (item manual/terhapus) di-exclude otomatis via INNER JOIN ke `items`
- **Bug ditemukan & diperbaiki saat testing** (transaction-rollback synthetic test): trend 6 bulan awalnya hitung `subMonths(5)` dari `endOfMonth()` — kalau tanggal akhir jatuh di tanggal 31 (Juli, Agustus, dst), subtraksi bulan overflow ke bulan tujuan yang lebih pendek (31 Jul −5 bulan jadi awal Maret, BUKAN akhir Februari), akibatnya window geser 1 bulan dan bulan berjalan malah tidak ikut ke-agregasi. Fix: `subMonths()` dipanggil dari `startOfMonth()` dulu (tanggal 1, tidak pernah overflow). Diverifikasi juga edge case year-boundary (window melewati Des→Jan)

#### 2. Controller — `LaporanKonsumsiBahanController` (`index`/`print`/`export`)
- `abort_unless` per method (defense-in-depth di atas middleware `can:`)
- Breakdown per item pakai sort+pagination server-side (klik header kolom → re-sort via query string `sort`/`dir`, 50 item/halaman via `LengthAwarePaginator` manual dari Collection hasil agregasi) — tidak ada precedent sortable-table di codebase ini sebelumnya, jadi dibuat pola baru murni untuk laporan ini
- Print & Export selalu pakai data breakdown PENUH (bukan yang sudah dipaginate) — `$breakdownPenuh` dipisah dari `$breakdown` (yang dipaginate) di `buildData()`

#### 3. Permission Baru — Sengaja Tidak Ada yang Dapat Default
- `laporan.konsumsi_bahan.view` / `.print` / `.export` — group `laporan` (icon & label sudah otomatis ready di `role/index.blade.php`, group ini generic/dynamic sejak Setoran Harian, tidak perlu ubah UI Role & Hak Akses sama sekali)
- `RolePermissionSeeder.php` **TIDAK disentuh** — 0 role dapat permission ini secara default, termasuk Owner (Owner tetap bypass semua via `Gate::before`)
- Diverifikasi end-to-end 3 skenario (transaction-rollback): Owner bypass ✅, Manajer tanpa grant → 403 ✅, Manajer dengan HANYA `.view` di-grant manual → bisa akses index tapi `print()`/`export()` tetap 403 ✅

#### 4. Views — Reuse Komponen Existing, Nol Dependency Baru
- `<x-date-range-filter>` direuse langsung (preset "Kemarin"/"Bulan Lalu" via prop `extraPresets`, sama seperti Setoran Harian) — default periode **BULAN INI** (beda dari Setoran Harian yang default hari ini)
- Chart.js **tidak** tambah CDN baru — sudah di-load global di `layouts/app.blade.php` (v4.4.0), tinggal `new Chart(...)` langsung (Bar chart Top 10 HPP + Line chart trend 6 bulan)
- Export "Excel" = CSV stream dengan `Content-Type: application/vnd.ms-excel` + ekstensi `.xls` (pola Setoran Harian — `maatwebsite/excel` **tidak terinstall** di project ini, bukan reimplementasi library beneran)
- "PDF" = `print.blade.php` + `window.print()` browser (pola Setoran Harian — `dompdf` juga **tidak terinstall**, jadi generate PDF server-side di luar scope, user pakai "Print to PDF" browser)
- Warning konfirmasi range >7 hari sebelum print/export — kode JS disalin persis dari `laporan/setoran-harian/index.blade.php` (estimasi halaman kasar)

#### 5. Sidebar & Panduan
- Menu baru di grup Laporan (`layouts/app.blade.php`, setelah "Setoran Harian") — icon `bi-basket3` (konvensi Bootstrap Icon existing, bukan emoji mentah di link sidebar; emoji 📊 dipakai di judul H4 halaman saja)
- Panduan Cara Pakai slug `laporan-konsumsi-bahan` (`PanduanKontenSeeder::laporanKonsumsiBahanUpdate()`, pola `updateOrCreate` karena slug baru di luar 46 stub original) — jelaskan cara baca laporan, formula "profit real" (Omzet − HPP), dan catatan bahwa order dibatalkan tidak dihitung

**Catatan audit data (bukan bug fitur ini):** DB lokal saat development cuma punya 8 `order_items` dari Maret 2026 — SEBELUM kolom `hpp`/fitur FIFO dibuat (Juni 2026) — jadi semuanya `hpp=0.00` secara sah. Formula agregasi diverifikasi lewat data sintetis di transaction yang di-rollback, bukan data produksi asli. Owner perlu cek manual di server: kalau order pasca-Juni 2026 di produksi juga `hpp=0` semua, itu bug FIFO terpisah di luar scope task ini.

**Tabel DB baru/diubah (sesi ini):** tidak ada — murni service/controller/view/permission/panduan baru, `order_items`/`orders`/`items` tidak disentuh sama sekali.

**Catatan kompatibilitas:** `LaporanPenjualanController`, `LaporanKeuanganController`, `StokDashboardController`, dan semua laporan existing lain **tidak disentuh** — Laporan Konsumsi Bahan Baku murni menambah file baru + 3 permission baru + 1 baris menu sidebar.

---

### 2026-07-23 — Master Resep Bumbu Standar + Auto-populate POS (GAP #1 Sesi 1a)

Latar: kasir jasa giling bakso input bumbu manual di POS dengan harga Rp 0 (sudah include tarif/kg). Sistem sudah auto-potong stok + hitung HPP untuk item Rp 0 (dikonfirmasi audit sebelumnya), tapi kalau kasir lupa/skip input bumbu → stok tidak berkurang → HPP tidak akurat. Solusi: Master Resep Bumbu + auto-isi form POS.

#### Prinsip Arsitektur — Helper Tool, BUKAN Ubah Logic POS
Ini keputusan desain paling penting di fitur ini (insight dari Owner): **Master Resep Bumbu murni alat bantu UI untuk auto-isi field form yang sudah ada** — `PenjualanService::buatOrder()`, `StokService::keluar()`, kalkulasi total/HPP/subtotal, update kas/keuangan, dan seluruh proses submit order **TIDAK disentuh sama sekali**. Endpoint AJAX baru (`GET /pos/resep-bumbu/{id}?berat=X`) cuma mengembalikan data mentah; JS di POS memanggil fungsi `tambahJasaGiling()` **existing** berkali-kali lalu isi field via DOM + panggil `updateBeratKg()` **existing** untuk recompute — persis efeknya seperti kasir isi manual baris demi baris. Kalau AJAX gagal, kasir tetap bisa lanjut input manual seperti biasa (fallback aman, zero risk regresi ke POS).

#### 1. Tabel Baru: `resep_bumbu` + `resep_bumbu_items`
- `resep_bumbu`: `nama`, `kode` (unique), `jenis_olahan_id` (nullable FK ke `jenis_olahans` — dipakai auto-isi dropdown "Jenis Olahan" existing di baris POS saat resep diterapkan), `is_active`, `catatan`, `dibuat_oleh`
- `resep_bumbu_items`: `resep_bumbu_id`, `item_id` (FK ke `items`), `qty_per_kg` (takaran per 1 kg gilingan), `satuan` (g/kg/ml — cuma utk interpretasi takaran, bukan satuan stok), `is_wajib`, **`mode_harga`** (enum `gratis`/`pakai_master` — gratis = harga_satuan 0 dikirim ke POS, pakai_master = ambil `items.harga_jual`), `urutan`
- **Sengaja TIDAK ada tabel `_histories`** dan tidak pakai `HasAuditLog` — mengikuti preseden nyata `jenis_olahans` (master data lookup sederhana, sudah dikonfirmasi tidak ada history table juga). Nonaktifkan resep = `is_active=false` (soft-disable, bukan hard delete atau SoftDeletes), sama pola dengan `JenisOlahanController::destroy()`

#### 2. Master Resep Bumbu — Full CRUD (`Master\MasterResepBumbuController`)
- Route `/master/resep-bumbu` — **100% permission-based** (`can:master.resep_bumbu.view/create/edit/delete` di level route + `abort_unless` di controller + `@can` di sidebar), **BUKAN** `role:owner,admin_pusat` hardcode seperti pola lama `Master\JenisOlahanController` — supaya Manajer Cabang bisa di-grant tanpa ubah kode, sesuai kebutuhan bisnis (Owner + Admin Pusat + Manajer Cabang boleh CRUD)
- Halaman create (nama/kode/catatan/jenis olahan) terpisah dari edit (tambah/hapus bahan satu-per-satu, form sederhana bukan SPA)
- 4 permission baru group `master`: `master.resep_bumbu.view/create/edit/delete` — **sengaja TIDAK di-assign ke role manapun secara default** (RolePermissionSeeder tidak disentuh), harus dicentang manual di UI Role & Hak Akses

#### 3. Endpoint AJAX POS (`GET /pos/resep-bumbu/{id}?berat=X`)
- **Sengaja TIDAK digated permission `master.resep_bumbu.*`** — itu untuk CRUD master data saja. Kasir yang tidak punya akses CRUD tetap harus bisa PAKAI resep di POS (cuma butuh akses POS biasa, sama seperti endpoint POS lain yang tidak ada permission tambahan)
- Konversi otomatis `qty_per_kg` (satuan g/ml) → kg sebelum dikirim ke POS, karena field `berat_daging`/`qty` di form SELALU dalam kg
- `harga_satuan` dihitung server-side sesuai `mode_harga` per item (gratis=0, pakai_master=`items.harga_jual`)

#### 4. Integrasi POS — Dropdown "Pilih Resep Bumbu" + Input Berat Gilingan
- UI baru di atas daftar item POS: dropdown resep + input "Berat Gilingan (kg)" + tombol "Terapkan Resep" — tampil cuma kalau ada resep aktif
- Badge visual "Wajib"/"Opsional" per baris hasil auto-populate — **murni informasi**, tidak memblokir tombol hapus (kasir tetap bisa hapus baris manapun, sama kebebasan seperti baris manual — keputusan simplifikasi yang dikonfirmasi Owner, tidak perlu hard-block penghapusan bahan wajib)
- Dropdown "Jenis Olahan" **existing** (Bakso/Sosis/Tempura per baris) tidak diubah — resep cuma auto-isi value-nya kalau resep dikaitkan ke suatu jenis olahan
- Label sengaja **"Pilih Resep Bumbu"**, bukan "Jenis Olahan" — supaya tidak tabrakan istilah dengan dropdown existing yang sudah ada di form yang sama

#### 5. Seed Data Starting Point (`ResepBumbuSeeder`)
- 3 resep: Bakso Kojek, Bakso Kuah, Bakso Bakar — dikaitkan ke jenis olahan "Bakso"
- Mapping bahan disesuaikan ke master Item existing: **"Merica" dipetakan ke "Lada Putih Bubuk"** (tidak ada nama persis match), **"Tepung Sagu" (Bakso Kuah) di-skip** karena item-nya belum ada sama sekali di master — Owner tambah manual lewat UI setelah item dibuat di Master Barang
- Idempotent (`firstOrCreate` by kode/item_id) — aman di-re-seed

**Tabel DB baru (sesi ini):** `resep_bumbu`, `resep_bumbu_items` (lihat detail kolom di atas)

**Catatan kompatibilitas:** `PenjualanService::buatOrder()`, `StokService::keluar()`, `OrderRequest`, dan seluruh alur submit/kalkulasi POS **tidak disentuh satu baris pun** — fitur ini murni tambahan controller/model/view/endpoint baru + 1 UI helper di POS yang cuma mengisi field form yang sudah ada.

---

### 2026-07-23 — Fix Alert Stok Minimum + Ranking Kasir + Filter Tipe Dashboard Stok

Dari audit detail Dashboard Stok: ditemukan sistem alert/notifikasi stok minimum (bell icon, Pusher real-time, `StokMinimumNotification`) **sudah ada dan lengkap**, tapi diam-diam tidak pernah bunyi untuk kombinasi item+lokasi baru karena `stocks.qty_minimum` (threshold per lokasi) hardcode 0 saat row baru dibuat, dan tidak ada UI untuk mengubahnya.

#### 1. Fix `StokService::updateStok()` — Inherit Threshold dari Master Item
- Baris yang bikin `stocks` row baru sekarang inherit `qty_minimum` dari `items.qty_minimum` (bukan hardcode 0) — kalau master item sudah di-set (mis. Bawang Putih = 5 kg), stok baru di cabang manapun langsung dapat threshold itu, alert otomatis aktif tanpa setup manual
- Kalau `items.qty_minimum` juga 0 (belum di-set), `stocks.qty_minimum` tetap 0 seperti sebelumnya — behavior lama tidak berubah untuk item yang memang belum ada thresholdnya

#### 2. UI "Set Minimum" per Lokasi (`stok.minimum.set`)
- Tombol baru di halaman `/stok` (ikon target, desktop tabel + dropdown mobile) buka modal shared, input qty minimum, konfirmasi SweetAlert2 sebelum submit ("Set minimum untuk {item} di {cabang} ke {X}?")
- Route `PATCH /stok/{stock}/set-minimum` (route-model-binding), permission baru `stok.minimum.set` — **default TIDAK di-assign ke role manapun** (RolePermissionSeeder tidak disentuh)
- Pakai Eloquent `$stock->update()` (bukan raw query) — `StockObserver` yang sudah ada otomatis catat history perubahan ke `stock_histories`, tidak perlu kode audit tambahan

#### 3. Fix Inkonsistensi `stok/index.blade.php` (2 Bug, Bukan 1)
- Kolom tampilan "Qty Minimum" di tabel desktop sebelumnya baca `item.qty_minimum` (global) padahal highlight baris "di bawah minimum" di baris yang sama baca `stocks.qty_minimum` (per lokasi) — angka yang ditampilkan tidak match logic highlight-nya
- Card mobile pakai `item.qty_minimum` (global) untuk highlight "Stok Kritis" — beda sumber dari tabel desktop (`stocks.qty_minimum`, per lokasi), jadi status kritis bisa beda antara tampilan desktop & mobile untuk baris data yang sama
- Fix: keduanya disamakan ke `stocks.qty_minimum` (per lokasi) — sumber yang benar-benar dipakai `StokDashboardController` & notifikasi

#### 4. Backfill `stocks.qty_minimum` dari Data Lama (`BackfillStockQtyMinimumSeeder`)
- Idempotent — guard `stocks.qty_minimum = 0`, tidak pernah menimpa yang sudah di-set manual (via "Set Minimum" atau backfill sebelumnya)
- Raw `DB::table()->update()` (bukan Eloquent) — sengaja **bypass `StockObserver`**, supaya backfill massal (bukan aksi user) tidak membanjiri `stock_histories` dengan ribuan entry palsu
- Log jumlah baris ter-update + sisa yang masih 0 (karena `items.qty_minimum`-nya juga 0)

#### 5. Ranking Kasir di Laporan Penjualan (`laporan.ranking_kasir.view`)
- Section baru "Ranking Kasir" di `/laporan/penjualan` — mirror pola "Produk Terlaris" yang sudah ada (filter dari/sampai/cabang identik, limit 10)
- Query join `orders.kasir_id → users.id` (**bukan tabel karyawan** — `kasir_id` adalah FK ke `users`), group by kasir+cabang, urut total omzet desc, ikon trofi untuk top 3
- Permission baru `laporan.ranking_kasir.view` — default tidak di-assign ke role manapun

#### 6. Filter Tipe Item di Dashboard Stok FIFO
- Dropdown baru "Semua Tipe / Bahan Baku / Kemasan / Produk Jadi" di `/stok/dashboard`, digabung dalam form filter cabang yang sudah ada (bukan form terpisah)
- Filter merambat ke semua widget di halaman itu (alert stok, nilai stok per cabang, list item, 4 smart insight: aging/top-movement/trend-harga/stok-mati) — **reuse permission `stok.view` yang sudah ada**, tidak bikin permission baru
- **Sengaja TIDAK merambat** ke 4 halaman "Lihat Semua" (`stok.aging`, `stok.top-movement`, `stok.trend-harga`, `stok.stok-mati`) — di luar scope, route/behavior-nya tetap persis seperti sebelumnya
- Default `tipe=null` (Semua Tipe) = perilaku identik dengan sebelum fitur ini ada — backward compatible

**Tabel DB baru/diubah (sesi ini):** tidak ada kolom baru — `stok.minimum.set` & `laporan.ranking_kasir.view` cuma permission baru, `qty_minimum` sudah ada di skema `stocks` sejak awal.

**Catatan kompatibilitas:** `LaporanKeuangan*`, `LaporanPenjualanController` (query existing), `Master Item` form, dan `StokService` flow lain **tidak disentuh** — angka historis laporan penjualan/keuangan diverifikasi identik sebelum & sesudah.

---

### 2026-07-23 — Laporan Setoran Harian Konsolidasi (GAP #3 dari Audit Laporan)

#### 1. Konsolidasi Setoran Harian (1 halaman, ganti cek manual 3 laporan)
- Halaman baru `/laporan/setoran-harian` — ringkasan Net (Pemasukan − Pengeluaran), total order, breakdown pemasukan per metode bayar (tunai/qris/transfer), breakdown pengeluaran per kategori, detail transaksi (tab Order/Pengeluaran)
- **Sumber angka Pemasukan/Pengeluaran/Net = `transaksi_keuangans`**, pola query (`withoutGlobalScopes()` + filter cabang manual) PERSIS sama seperti `LaporanKeuanganController::arusKas()` — supaya Net di sini SELALU cocok dengan Laporan Arus Kas untuk periode yang sama, tidak ada laporan yang beda angka
- Breakdown metode bayar diambil dari `orders` (bukan `transaksi_keuangans`) karena kolom `tipe_pembayaran` cuma ada di situ — bisa sedikit beda dari Total Pemasukan kalau ada pemasukan manual non-order di periode yang sama (dikasih catatan info di UI, bukan dianggap bug)
- Filter tanggal pakai kolom akuntansi `tanggal_order`/`tanggal_transaksi` (BUKAN `created_at`) — setoran mengikuti kejadian akuntansi real, bukan kapan input ke sistem
- Default periode: **HARI INI** (beda dari kebanyakan laporan lain yang default bulan ini) — untuk kebutuhan cek cepat setoran harian
- Detail transaksi range panjang (>1 hari) otomatis dikelompokkan per tanggal via accordion collapsible; range 1 hari (default) tampil flat tanpa accordion
- Print & Export: kalau range >7 hari, tampil konfirmasi SweetAlert2 "Anda akan mencetak N hari, ±X halaman. Lanjut?" sebelum lanjut (estimasi halaman kasar, bukan hitungan presisi)
- Service baru `SetoranHarianService` (murni READ, terpisah total dari `PenjualanService`/`StokService` biar nol risiko ke fitur lain)
- Component `<x-date-range-filter>` di-extend prop opsional `extraPresets` (dipakai halaman ini utk tombol "Kemarin"/"Bulan Lalu") — additive murni, 8 preset default + semua laporan lain yang sudah pakai komponen ini tidak berubah sama sekali

#### 2. Kategori Pengeluaran Terstruktur (`kategori_pengeluaran`)
- Kolom baru `transaksi_keuangans.kategori_pengeluaran` (nullable, string 30) — terpisah dari `kategori`/`kategori_id` (kategori dinamis) yang sudah ada, khusus untuk breakdown Laporan Setoran Harian
- Enum `KategoriPengeluaran`: Bahan Baku, Gaji & Upah, Operasional, Transportasi, Marketing & Pelanggan, Lain-lain (default pilihan form)
- Form Tambah/Edit Transaksi Keuangan: dropdown baru, WAJIB diisi kalau `tipe = pengeluaran` (toggle show/hide + required via JS, sama pola dengan filter kategori existing berdasar tipe), disembunyikan & di-null-kan kalau tipe = pemasukan
- Data lama (`NULL`, sebelum kolom ini ada) ditampilkan sebagai **"Lain-lain (belum dikategorikan)"** — beda label dari `lain_lain` yang dipilih eksplisit, supaya kelihatan data mana yang belum pernah dikategorikan ulang

#### 3. Permission Baru — Sengaja Tidak Ada yang Dapat Default
- `laporan.setoran_harian.view` / `.print` / `.export` — group baru `laporan` (icon & label sudah otomatis ready di `role/index.blade.php`, tidak perlu ubah UI Role & Hak Akses sama sekali)
- **`RolePermissionSeeder.php` TIDAK disentuh** — 0 role dapat permission ini secara default, termasuk Owner (Owner tetap bypass semua via `Gate::before`, bukan lewat assignment)
- Harus dicentang manual per role lewat UI Role & Hak Akses kalau mau diaktifkan
- Diverifikasi end-to-end via simulasi: grant manual → cache di-clear → akses berhasil → revert (lihat catatan reminder Gate cache di bawah)

**Reminder deployment:** `Gate::define()` untuk semua permission di-generate dari cache 1 jam (`AppServiceProvider.php`, key `all_permission_names`) — WAJIB `php artisan cache:clear` setelah seed permission baru supaya `can:laporan.setoran_harian.*` langsung aktif untuk role non-Owner.

**Tabel DB baru/diubah (sesi ini):**
- `transaksi_keuangans`: +kolom `kategori_pengeluaran` (string 30, nullable, default NULL — data lama tidak berubah)

**Catatan kompatibilitas:** Kolom baru nullable tanpa default paksa → data lama 100% tidak berubah (diverifikasi: seluruh baris existing tetap NULL setelah migration). `LaporanPenjualanController`, `LaporanKeuanganController`, dan semua laporan existing lain **tidak disentuh sama sekali** — Setoran Harian murni menambah controller/service/view baru + 1 kolom baru + 3 permission baru.

#### 4. Backfill `kategori_pengeluaran` dari Kategori Lama (Data Migration)
- **Root cause ditemukan lewat audit lanjutan:** ada 3 kolom kategori paralel di `transaksi_keuangans` — `kategori` (enum lama), `kategori_id` (FK dinamis ke `kategori_transaksis`, **sudah dipakai user sejak lama & wajib diisi di form**), dan `kategori_pengeluaran` (baru, kosong total untuk semua data lama). Breakdown Setoran Harian cuma baca `kategori_pengeluaran`, jadi semua histori tampil "Lain-lain (belum dikategorikan)" walau user sudah rutin isi kategori lewat `kategori_id`
- Seeder baru `KategoriPengeluaranBackfillSeeder` — data migration sekali jalan, 3 tahap: (1) mapping nama `kategori_transaksis.nama` (case-insensitive+trim) → `KategoriPengeluaran` sesuai tabel yang dikonfirmasi Owner, (2) fallback keyword utk kategori custom yang belum ada di mapping literal (gaji/insentif/bonus/thr→gaji_upah, sedekah/zakat/csr→lain_lain, pajak→operasional, dst), (3) fallback via kolom `kategori` enum lama utk baris tanpa `kategori_id`, lalu default terakhir `lain_lain` utk sisa yang tidak match apapun
- **Idempotent** — setiap UPDATE digated `whereNull('kategori_pengeluaran')`, jadi tidak pernah menimpa baris yang sudah terisi (backfill sebelumnya ATAU koreksi manual user lewat form). Diverifikasi: jalan 2x hasil sama (0 baris ter-update di run kedua), dan simulasi override manual tetap tidak tertimpa setelah re-run
- Terdaftar di `DatabaseSeeder.php` (setelah `KategoriTransaksiSeeder`) — aman di-include karena no-op kalau `transaksi_keuangans` kosong (instalasi baru)
- Diverifikasi dengan 19 kasus sintetis (15 dari tabel mapping Owner + 4 contoh fallback: Bonus/Sedekah/Zakat/Pajak) dalam transaction yang di-rollback — 100% match, tidak ada sisa data uji coba
- `LaporanKeuanganController::arusKas()`/`labaRugi()` dan `LaporanPenjualanController::index()` **angkanya identik sebelum & sesudah backfill** — murni update 1 kolom, tidak menyentuh `kategori`/`kategori_id`/`jumlah`/kas manapun

---

### 2026-07-16 — Order Pengganti + Filter Antrian Produksi + Rule Pembatalan Hari Sama

#### 1. Checkbox "Tampilkan di Antrian Produksi" (Order Tambahan)
- Kolom baru `orders.tampil_di_antrian` (boolean, default `true`, safe untuk data lama)
- Checkbox di POS (default checked), pakai pola hidden-input + checkbox supaya nilai `0`/`1` selalu terkirim walau di-uncheck
- `PenjualanService::buatOrder()`: kalau `tampil_di_antrian = false` → `nomor_antrian`/`status_produksi` tidak di-set (dibiarkan NULL)
- **Tidak ada perubahan query** di `AntrianDisplayController`/`AntrianOperatorController` — keduanya sudah otomatis exclude order lewat `whereNotNull('status_produksi')`, jadi order dengan checkbox di-uncheck otomatis tidak muncul di TV Antrian & Mode Produksi

#### 2. Order Pengganti (`parent_order_id`)
- Kolom baru `orders.parent_order_id` (self-referencing FK, nullable, `nullOnDelete`), relasi `parentOrder()`/`childOrders()` di model `Order`
- **Auto-detect di POS:** saat submit, cek endpoint `GET /penjualan/cek-pengganti` (harus terdaftar SEBELUM route `GET /penjualan/{order}` supaya tidak ketabrak route-model-binding) — kalau ada order Dibatalkan hari ini dari pelanggan sama & belum ada penggantinya, tampilkan popup konfirmasi "Order ini pengganti order #XXX?"
- **Manual (backup):** tombol "Tandai sebagai Pengganti" di halaman detail order → `POST /penjualan/{order}/tandai-pengganti`, pilih dari daftar order Dibatalkan cabang yang sama & belum punya pengganti
- Badge di halaman detail order: kuning "🔄 PENGGANTI dari #XXX" (kalau `parent_order_id` terisi), merah "❌ DIBATALKAN → diganti #XXX" (kalau order Dibatalkan & sudah punya child order)

#### 3. Rule Pembatalan: Hari Sama + Alasan Wajib
- Kolom baru `orders.alasan_pembatalan_kategori` (string, nullable) + `alasan_pembatalan_detail` (text, nullable)
- Non-Owner hanya boleh batalkan order dengan `created_at` di HARI YANG SAMA — validasi di server (`PenjualanController::batalkan()`) + tombol Batalkan auto-disabled di UI kalau bukan hari ini & bukan Owner
- Owner tetap bisa bypass (existing `Gate::before`), tapi alasan tetap wajib diisi & log dicatat beda pesan ("Bypassed by Owner: ...")
- Modal Batalkan (ganti dari `confirm()` biasa) wajib pilih kategori: Pengurangan Item / Salah Input Kasir / Pelanggan Ganti Menu / Pelanggan Batal Datang / Lainnya + detail opsional
- Dicatat ke `activity('Order')` (spatie/laravel-activitylog) — ditampilkan di accordion "Riwayat Pembatalan" pada halaman detail order

#### 4. Panduan Kasir POS
- Update `PanduanPosSeeder` (pakai `updateOrCreate` supaya re-seed benar-benar apply): tambah section "Cara Buat Order Tambahan", "Cara Kurangi Item / Pelanggan Ganti Pesanan", dan rule hari-sama di "Aturan Penting" — sudah otomatis tampil lewat `<x-panduan-button slug="pos" />` yang sudah ada di header POS, tidak perlu modal custom baru

**Tabel DB baru/diubah (sesi ini):**
- `orders`: +kolom `tampil_di_antrian` (boolean default true), `parent_order_id` (FK self-reference nullable), `alasan_pembatalan_kategori` (string 50 nullable), `alasan_pembatalan_detail` (text nullable)

**Catatan kompatibilitas:** Semua kolom baru nullable/default aman → data order lama tidak berubah, angka laporan penjualan/dashboard tidak terpengaruh (query agregasi tidak menyentuh kolom ini), fitur "Batalkan"/"Kembalikan Order" existing tetap jalan mundur-kompatibel.

---

### 2026-07-14 / 2026-07-16 — Marathon: POS UX Berat & Satuan + Validasi + Struk Format + Fix PO Permission

#### 1. Dropdown Satuan Berat di POS (kg/ons/gram)
- Step input berat jasa giling dinaikkan presisi ke `0.001` (dari `0.1`) — DB `order_items.berat_daging` sudah `DECIMAL(10,3)`, aman
- Dropdown satuan (kg/ons/gram) di samping input berat per baris — dikonversi ke kg oleh `updateBeratKg()`, disimpan ke hidden field `items[idx][berat_daging]` yang sama seperti sebelumnya (controller/service/model/validasi tidak disentuh, data yang dikirim ke server selalu kg)
- Label dinamis "Berat (kg/ons/gram)" ikut satuan yang dipilih
- Hint konversi "= 0.020 kg" **dicoba 3x lalu dibuang** — selalu bikin kolom Berat lebih tinggi dari kolom lain di row `align-items-end` (dicoba `display:none`→`visibility:hidden`→pindah ke baris info col-12 di bawah row, tetap tidak stabil) — versi final: dropdown satuan tanpa hint tambahan
- Ringkasan POS: `formatBerat(kg)` auto-convert tampilan (≥1 kg = kg, ≥0.1 kg = ons, <0.1 kg = gram, tanpa desimal kalau bulat) — **hanya tampilan**, field `berat_daging` yang dikirim tetap kg. Field level-order `berat_daging_kg` (untuk antrian, `DECIMAL(8,2)`) sengaja tidak ikut diubah stepnya (beda presisi, murni informasional)
- Ringkasan 1 baris compact: nama + berat/qty di kiri, subtotal di kanan; detail breakdown (jenis olahan, qty x harga) tampil di bawah nama

#### 2. Validasi POS Lengkap + SweetAlert2
- Bug urgent (fixed): order dengan nama/telepon/item/bayar kosong bisa submit sukses tanpa validasi apapun
- Client-side: cek berurutan nama pelanggan wajib → telepon wajib → item minimal 1 & berat jasa giling wajib → jumlah bayar ≥ total → dialog konfirmasi ringkasan (Total/Bayar/Kembalian) sebelum `fetch()`
- Server-side (defense-in-depth): `OrderRequest` — `nama_pelanggan`/`telepon_pelanggan` jadi `required`, `items.*.berat_daging` pakai `required_if:tipe,jasa_giling`; `PenjualanController::store()` hitung ulang total & bandingkan ke `jumlah_bayar` sebelum panggil `PenjualanService::buatOrder()`
- Semua `alert()`/`confirm()` native di POS diganti **SweetAlert2** (CDN jsdelivr, bukan npm) via helper `window.showAlert()`/`window.showConfirm()` — fallback otomatis ke native kalau CDN gagal load, jadi tidak pernah silent-break. `window.showToast()` juga dipindah ke SweetAlert2 toast mode

#### 3. Tombol Kembali di Halaman Fullscreen (PWA + Browser)
- Component baru `<x-back-button-pwa />` — awalnya hanya tampil di mode PWA standalone (`window.isPWAStandalone()`), lalu diubah tampil di **kedua mode** (PWA & browser biasa) karena halaman fullscreen (Scan Absensi, Mode Produksi) menyembunyikan sidebar/topbar termasuk tombol back browser
- Dipasang di `face-attendance/scan.blade.php` dan `antrian/produksi.blade.php` (keduanya fullscreen); `antrian/operator.blade.php` tidak perlu karena sidebar/topbar tetap tampil normal

#### 4. Fix Kas Unique Constraint untuk Soft Delete
- `kas_cabang_default_unique (cabang_id, default_untuk)` tidak null-aware terhadap baris soft-deleted → kas dihapus lalu dibuat ulang dengan kombinasi sama → duplicate entry error 500 (kejadian nyata di cabang 7)
- Migration idempotent (pola sama seperti fix absensi `2026_06_22_000001`): pastikan `cabang_id` punya backing index sebelum drop unique lama → drop `kas_cabang_default_unique` → tambah virtual generated column `soft_uniq_key` (`'active'` kalau `deleted_at` NULL, NULL kalau soft-deleted) → unique index baru `(cabang_id, default_untuk, soft_uniq_key)`
- **Catatan:** migration `2026_07_15_000001_fix_kas_soft_delete_unique_constraint` sudah di-commit tapi **belum berhasil apply di DB lokal** (lihat Pending di bawah)

#### 5. Revisi Struk Fisik (3 iterasi)
- Font diperkecil (ESC M 1) untuk detail order/item/footer → **di-revert ke font normal** karena render kekecilan/aneh di printer fisik
- Baris "Jenis Olahan" dipindah ke header struk (1x per struk, bukan diulang per item), baris "Berat: Xkg - jenis" yang redundan per item dihapus
- Item pakai adaptive layout: 1 baris kalau muat ≤32 karakter, fallback 2 baris kalau tidak muat
- Karakter `×` (unicode) diganti `x` ASCII — render aneh di printer thermal (masalah sama seperti separator gunting sebelumnya)
- Format akhir `formatQtyHarga()`: spasi antar angka+satuan, spasi sebelum/sesudah `x`, prefix `"Rp "`, kapitalisasi satuan (kg→Kg, ons→Ons, pcs→Pcs, dll) — contoh: `"1 Kg x Rp 22.000"`, `"7 Ons x Rp 22.000"`, `"5 Pcs x Rp 3.000"`

#### 6. Fix PO Permission-Based Routing
- Root cause 403 di menu PO: route `pembelian.*` masih hardcode `role:owner,admin_pusat,admin_gudang`, padahal sidebar & permission seeder sudah berbasis `pembelian.*` — `manajer_cabang` di-assign `pembelian.view`/`pembelian.create` tapi route tidak pernah membaca permission itu
- Ganti semua route pembelian ke middleware `can:` per-route (index/show→`pembelian.view`, create/store→`pembelian.create`, approve→`pembelian.approve`, destroy/batalkan→`pembelian.delete`, kirim-supplier/terima→permission baru `pembelian.kirim-supplier`/`pembelian.terima`)
- Matrix akhir: admin_pusat (semua), admin_gudang (semua kecuali delete), manajer_cabang (view+create saja), Owner bypass via `Gate::before`
- Controller ditambah `abort_unless()` per method (defense-in-depth) + tombol di Blade dibungkus `@can` sesuai

---

### 2026-07-07 / 2026-07-10 — Marathon: Cash Drawer + Bluetooth Printer Reliability + POS Ajax Refactor

#### 1. Buka Laci Kasir (Cash Drawer ESC/POS)
- Laci otomatis terbuka saat pembayaran Tunai (1x meski cetak struk 2 salinan), tidak buka untuk QRIS/Transfer
- Tombol manual "Buka Laci" di POS, permission `kas.buka_laci` (Owner/Admin Pusat/Manajer Cabang), dicatat ke activity log untuk audit

#### 2. Bluetooth Printer Reliability
- **Silent reconnect** via `navigator.bluetooth.getDevices()` — skip popup pilih printer di transaksi ke-2 dst, reuse `localStorage` key `btLastPrinterName`. Diterapkan juga di `bukaLaciManual()` (share pairing dengan printer struk)
- **Auto-fallback ke `requestDevice()`** kalau silent reconnect gagal (device stale/di luar jangkauan) — retry maks 2 attempt tanpa nampilkan error di antaranya, error hanya muncul kalau attempt kedua (device baru dipilih user) juga gagal
- **Fix zombie GATT connection**: scan service printer sebelumnya jalan di luar `try/finally` utama — kalau throw tak terduga, `server.disconnect()` ter-skip. Sekarang seluruh alur connect+scan+kirim dibungkus 1 `try/finally` + delay 200ms sebelum disconnect (flush buffer printer)
- **Service Worker retry-with-backoff**: root cause "Sedang Offline" palsu setelah cetak Bluetooth — tablet Android budget dengan antena WiFi+BT bersama bisa alami gangguan WiFi sesaat saat radio BT aktif. Mitigasi: retry 1x `fetch()` setelah jeda 700ms sebelum fallback ke `offline.html`. Bump `CACHE_VERSION` ke `bm-erp-v2`

#### 3. POS Ajax Checkout + Modal In-Place
- Root cause auto-print lemot/gagal: redirect POST→GET setelah checkout menghapus transient activation Chrome, sehingga Web Bluetooth API (`requestDevice`) jadi tidak reliable dipanggil otomatis di halaman hasil redirect
- Fix: checkout submit via `fetch()` (bukan form POST biasa), modal struk dibuka in-place di halaman POS yang sama tanpa navigasi — gesture klik "Proses" tetap hidup untuk operasi Bluetooth berikutnya
- `PenjualanController::store()` deteksi `$request->expectsJson()`: true → balikin JSON, false → flow lama (redirect + session flash), backward compatible penuh
- `resetPosForm()` baru (reset seluruh state form/cart/pembayaran), dijalankan setelah modal struk ditutup (bukan langsung setelah submit)
- **Auto-trigger cetak otomatis dicoba lalu di-revert**: testing di device nyata, silent reconnect konsisten gagal kalau dipanggil dalam chain async panjang (checkout→modal fetch→script inject→auto-trigger) — popup pilih printer tetap muncul tiap transaksi, malah nambah friction. Revert murni ke behavior klik manual tombol printer di modal (Ajax checkout + modal in-place tetap dipertahankan)

#### 4. UX Modal Struk
- Checkbox "Cetak Struk Otomatis" persist via `localStorage` (`pos_auto_print_struk`, default UNCHECKED), bukan reset tiap transaksi
- Modal struk jadi strict: `backdrop:'static'` + `keyboard:false` — klik luar/Escape tidak menutup modal, satu-satunya jalur tutup adalah tombol "Selesai"
- `resetPosForm()` dijamin jalan tepat 1x lewat listener `hidden.bs.modal` `{ once: true }`
- Layout tombol modal dirapikan jadi 3 grup (Primary: Cetak + Pilih Printer, Finish: Selesai + Lihat Detail, Utility: Lupakan Printer + Cetak Browser) pakai class Bootstrap standar
- Separator "gunting" antar salinan struk (`copies >= 2`) — awalnya pakai ikon unicode `✂`, **diganti ASCII "POTONG DI SINI"** karena unicode tidak tercetak di printer thermal 58mm

---

### 2026-06-21 / 2026-06-22 — Marathon: Face Attendance Polish + Real-time + Bug Fix

#### 1. Face Attendance — Head Movement Liveness (Final)
- **Ganti blink detection → head movement only** (Fase 4): 1x geleng kiri/kanan, threshold 18px, reset 20px, timeout 15s
- Stuck state auto-reset: jika > 3 detik tidak kembali ke center → force reset + re-anchor `initialNoseX`
- Constants: `REQUIRED_HEAD_MOVEMENTS=1`, `HEAD_MOVEMENT_THRESHOLD=18`, `HEAD_RESET_THRESHOLD=20`, `LIVENESS_TIMEOUT_MS=15000`
- Race condition fix: `isSubmitting` guard (1 fetch at a time) + `overlayShown` flag (cegah `finally` override `isProcessing` saat success overlay)
- Fatal error state: `isFatalError` flag stop detection loop pada HTTP 4xx/5xx, user harus klik "Coba Lagi"
- **File-api.js models**: download script `.bat` (Windows) + `.sh` (Linux), `.gitignore` exclude `*.json` + `*-shard*`, dokumentasi upload cPanel

#### 2. Face Attendance — Fullscreen UI Polish
- `body.scan-absensi-mode` CSS class: hide sidebar/topbar, reset margin-top dari `var(--topbar-height)=60px`, `height: 100dvh` untuk iOS Safari
- `viewport-fit=cover` ditambah via JS (scoped, di-restore saat leave)
- Panel anti-spoofing: dipindah dari overlay video (cover wajah) → static element di info panel kanan dengan `max-height` collapse animation
- `body.scanning-active` CSS class: smart compact saat liveness checking — hide `#idleBox`, `.panel-title`, `#dateEl`; kompres `#clockBar`, `#selectedTypeBar`, `#actionBar`
- Tombol exit ✕ di pojok kanan atas video (`scan-exit-btn`)

#### 3. Bug Fix — Soft Delete + Unique Constraint Absensi
- **Root cause**: `Absensi::firstOrNew()` melewatkan SoftDeletes scope → soft-deleted record tidak ditemukan → INSERT baru → DUPLICATE entry → HTTP 500
- **Solusi A** (withTrashed + restore) di-rollback: behavior salah — user mau fresh record baru, bukan restore record lama
- **Solusi B — migration** `2026_06_22_000001_fix_absensis_soft_delete_unique_constraint`:
  - `ADD INDEX absensis_karyawan_id_index (karyawan_id)` — plain index dulu agar FK tidak kehilangan backing index
  - `DROP INDEX absensis_karyawan_tanggal_cabang_unique`
  - `ADD COLUMN soft_uniq_key VARCHAR(80) GENERATED ALWAYS AS (IF(deleted_at IS NULL, CONCAT(...), NULL)) STORED`
  - `ADD UNIQUE INDEX absensis_soft_unique (soft_uniq_key)` — NULL diabaikan MariaDB UNIQUE
- Server MariaDB 10.4.32: support virtual/generated column + UNIQUE null-aware ✅

#### 4. Bug Fix — Route absen-device.* Hilang
- `AbsenDeviceController` (6 method) + view `absen-device/index.blade.php` + migration `create_absen_devices_table` sudah ada, tapi route tidak terdaftar di `web.php`
- `/face-attendance/today` crash 500 saat cabang belum punya device aktif (memanggil `route('absen-device.index')`)
- Fix: tambah 6 route di `web.php` dengan middleware `role:owner,admin_pusat,manajer_cabang`

#### 5. Security
- Pusher key di-rotate (key lama ter-expose di chat session)
- Audit hardcoded key di semua blade: **semua sudah pakai** `config('broadcasting.connections.pusher.key')` — tidak ada hardcode
- Update `.env` di server cPanel, key lama dihapus dari Pusher Dashboard

#### 6. Update Tooltip & Panduan Absensi
- Pesan error liveness gagal: "berkedip 2x" → "Gelengkan kepala ke kiri atau kanan 1 kali dengan jelas"
- Panduan scan-absensi (DB row slug=`scan-absensi`): langkah liveness + troubleshooting di-update, timeout 10s → 15s
- `PanduanKontenSeeder` idempotent (semua `->update()`) → re-seed aman via `clear-cache.php` pattern

---

## Status Terkini (per 2026-07-26)

| Modul | Status |
|-------|--------|
| Audit + Fix 3 Bug Production (Laporan Keuangan, Dashboard PO, Selisih Kas) + Cleanup Tool | ✅ DONE |
| Fix Adjustment Stok Alasan Koreksi + Tombol Hapus Stok | ✅ DONE |
| Marathon PO Tools (Pilih PO + Widget + Dashboard PO Full) | ✅ DONE |
| Laporan Laba Rugi + Kolom Untung di Laporan Konsumsi (Kombinasi A+B) | ✅ DONE |
| Laporan Konsumsi Bahan Baku (GAP #1 Sesi 1b) | ✅ DONE |
| Master Resep Bumbu Standar + Auto-populate POS (GAP #1 Sesi 1a) | ✅ DONE |
| Fix Alert Stok Minimum (inherit threshold + UI Set Minimum + backfill) | ✅ DONE |
| Ranking Kasir di Laporan Penjualan | ✅ DONE |
| Filter Tipe Item di Dashboard Stok FIFO | ✅ DONE |
| Laporan Setoran Harian Konsolidasi + Kategori Pengeluaran Terstruktur | ✅ DONE |
| Order Pengganti + Filter Antrian + Rule Pembatalan Hari Sama | ✅ DONE |
| POS UX: Dropdown Satuan Berat (kg/ons/gram) + Validasi + SweetAlert2 | ✅ DONE |
| Struk Fisik: Format Spasi + Rp + Kapitalisasi + Adaptive Layout | ✅ DONE |
| Cash Drawer (Buka Laci ESC/POS) | ✅ DONE |
| Bluetooth Printer: Silent Reconnect + Auto-Fallback + Fix Zombie Connection | ✅ DONE |
| POS Ajax Checkout + Modal In-Place | ✅ DONE |
| Fix PO Permission-Based Routing (manajer_cabang VIEW+CREATE) | ✅ DONE |
| Tombol Kembali PWA + Browser di Halaman Fullscreen | ✅ DONE |
| Keuangan Phase 1 (SP1A/B/C) | ✅ FULL COMPLETE |
| FIFO Phase A (stock_batches) | ✅ VERIFIED single + multi-batch |
| Dashboard Stok Phase B+C | ✅ COMPLETE + 4 halaman Lihat Semua |
| Security: Item permission + Kasir block | ✅ DONE |
| BEP Auto-fill dari penjualan aktual | ✅ DONE |
| Tipe Cabang Head Office | ✅ DONE |
| Face Attendance: Head Movement Liveness | ✅ DONE (1x geleng, 15s timeout) |
| Face Attendance: Fullscreen UI Polish | ✅ DONE (scan-absensi-mode + scanning-active) |
| Soft Delete Bug Absensi | ✅ FIXED (virtual column migration) |
| Route absen-device.* | ✅ FIXED |
| Pusher key rotated | ✅ DONE |

### Sistem POS — Ringkasan Production-Grade (per 2026-07-16)
- POS Ajax + Modal in-place (tidak ada redirect setelah checkout)
- Cash drawer auto-buka saat Tunai + tombol manual dengan permission `kas.buka_laci`
- Bluetooth: silent reconnect + auto-fallback requestDevice() + fix zombie GATT connection
- Modal cetak strict close (hanya via tombol "Selesai") + auto-reset state POS setelah cetak
- Layout tombol modal 3 grup (Primary/Finish/Utility) + separator ASCII antar salinan struk
- Input & ringkasan berat auto-convert (kg/ons/gram) — struk & database tetap konsisten dalam kg
- Struk fisik: format spasi + prefix "Rp" + kapitalisasi satuan
- Validasi POS lengkap (client + server) + dialog SweetAlert2 (fallback ke native kalau CDN gagal)
- Tombol Kembali (PWA & browser) di halaman fullscreen (Scan Absensi, Mode Produksi)
- Order pengganti + filter antrian produksi + audit trail pembatalan
- Panduan Cara Pakai POS (`<x-panduan-button slug="pos" />`) lengkap dengan flow order tambahan & order pengganti

### Permission Baru (sesi 2026-07-07 s/d 2026-07-16)
- `kas.buka_laci` — Buka Laci Kasir manual
- `order.batalkan` (+ rule hari sama, Owner bypass), `order.kembalikan`
- `pelanggan.view/create/edit/delete`
- `karyawan.view/create/edit/delete` (group `hr`)
- `pembelian.view/create/approve/delete/kirim-supplier/terima` (per-route, gantikan role hardcode lama)

**Tabel DB baru/diubah (sesi 2026-07-23, Master Resep Bumbu):**
- `resep_bumbu` (BARU): `nama`, `kode` unique, `jenis_olahan_id` (FK nullable), `is_active`, `catatan`, `dibuat_oleh`
- `resep_bumbu_items` (BARU): `resep_bumbu_id`, `item_id`, `qty_per_kg`, `satuan`, `is_wajib`, `mode_harga` (gratis/pakai_master), `urutan`

**Tabel DB baru/diubah (sesi 2026-07-23):**
- `transaksi_keuangans`: +kolom `kategori_pengeluaran` (string 30, nullable, default NULL — data lama tidak berubah)

**Tabel DB baru/diubah (sesi 2026-07-16):**
- `orders`: +kolom `tampil_di_antrian` (boolean default true), `parent_order_id` (FK self-reference nullable), `alasan_pembatalan_kategori` (string 50 nullable), `alasan_pembatalan_detail` (text nullable)

**Tabel DB baru/diubah (sesi 2026-07-15):**
- `kas`: migration `2026_07_15_000001_fix_kas_soft_delete_unique_constraint` — +kolom `soft_uniq_key` (STORED GENERATED virtual), unique baru `(cabang_id, default_untuk, soft_uniq_key)`, drop unique lama `kas_cabang_default_unique` (pola sama seperti fix absensi)

**Tabel DB baru/diubah (sesi 2026-06-22):**
- `absensis`: +kolom `soft_uniq_key` (STORED GENERATED virtual), +index `absensis_karyawan_id_index`, +unique `absensis_soft_unique`, -unique `absensis_karyawan_tanggal_cabang_unique`

**Pending sesi berikutnya:**
- Custom Error Pages (403, 404, 500)
- Notifikasi: Setoran, Stok minimum, Recurring transaction trigger
- Test Setup Device flow (daftarkan tablet ke cabang via `/absen-device`)
- Edge case restore di Data Terhapus: warning jika record baru sudah ada
- Migration `2026_07_15_000001_fix_kas_soft_delete_unique_constraint` — **sudah jalan & terverifikasi di server produksi**, tapi masih **Pending/gagal** di DB lokal development (`Cannot drop index 'kas_cabang_default_unique': needed in a foreign key constraint`) — perlu investigasi FK sebelum migration berikutnya dijalankan berurutan di lokal
- Full auto-print struk (100% zero-click tanpa klik tombol printer sama sekali) — **deferred**, dicoba (commit aadf2a0) & di-revert (839b9f3) karena silent Bluetooth reconnect tidak reliable dipanggil otomatis dalam chain async panjang di browser; butuh native app/WebView kalau mau dikejar lagi
- Setup data awal tiap cabang (saldo kas awal + stok awal bahan baku)
- Test transaksi real dari kasir asli di lapangan (bukan cuma testing developer)
- Recipe/BOM system untuk bumbu include per produk — dikesampingkan dulu, belum jadi prioritas
- Sisa gap dari Audit Laporan (lihat riwayat audit sebelumnya) yang belum di-apply: **Laporan Produksi** (total berat digiling + performa operator dari kolom antrian di `orders`, belum ada laporan historis sama sekali). ~~Ranking Kasir~~ sudah selesai (lihat sesi 2026-07-23), ~~Laporan Konsumsi Bahan Baku~~ sudah selesai (lihat sesi 2026-07-24)
- **Cek manual Owner di produksi:** verifikasi order pasca-Juni 2026 punya `order_items.hpp > 0` (lihat catatan audit di Update Log 2026-07-24) — kalau ternyata 0 semua di server juga, itu bug FIFO fundamental terpisah yang perlu task investigasi baru

---

### 2026-06-17 — Dashboard Stok FIFO + Security + BEP Auto-fill

#### 1. Security: Item Permission & Kasir Block
- **4 permission baru:** `item.view`, `item.create`, `item.edit`, `item.delete`
- `ItemController`: `abort_unless` di 7 method; sidebar wrap `@can('item.view')`
- `RolePermissionSeeder`: admin_pusat semua, admin_gudang 3, manajer 2, kasir/operator/helper 0
- Kasir block adjustment: hapus `stok.request` dari kasir, `StokController::adjustment()` + `@can` wrap di view
- **File diubah:** `ItemController.php`, `RolePermissionSeeder.php`, `sidebar-menu.blade.php`, `stok/index.blade.php`, `StokController.php`

#### 2. Bug Fix: Item Show View (H-1)
- **File baru:** `resources/views/item/show.blade.php` — detail item + stok per lokasi + 50 movement terbaru
- `ItemController::show()` tambah `$recentMovements`
- Tombol "Lihat Detail" (eye icon) di `item/index.blade.php` desktop + mobile dengan `@can('item.view')`

#### 3. FIFO Phase A — Foundation
- **Migration baru:** `stock_batches` table (item_id, lokasi_id, qty_awal, qty_sisa, harga_beli_per_unit, tanggal_masuk, SoftDeletes) + 35 initial seed batches + kolom `hpp` di `order_items`
- **Model baru:** `app/Models/StockBatch.php` (SoftDeletes, HasAuditLog, scopeAktif, scopeUntukLokasi)
- `StokService`: `masuk()` +hargaBeli param, `keluar()` return HPP via `consumeBatchesFifo()` (lockForUpdate)
- `PurchaseOrderService`: pass `harga_satuan` ke `masuk()` + auto-update `Item.harga_beli_terakhir`
- `PenjualanService`: `keluar()` dipindah sebelum `OrderItem::create`, simpan HPP
- **Verified:** multi-batch FIFO (7 kg @ Rp 13rb + 5 kg @ Rp 16rb = HPP Rp 171.000 ✅)

#### 4. Dashboard Stok Phase B — Overview & FIFO Insights
- **File baru:** `app/Http/Controllers/StokDashboardController.php` (index, itemBatches)
- **File baru:** `resources/views/stok/dashboard.blade.php`
- **Route:** `stok.dashboard`, `stok.dashboard.item-batches`
- **Fitur:** 3 stat cards, alert habis/kritis per cabang, nilai stok FIFO per cabang, list 17 item dengan AJAX batch expand (badge "↑ NEXT" batch tertua), filter search + status + cabang, mobile responsive

#### 5. Dashboard Stok Phase C — Smart Insights + Lihat Semua
- **4 widget tambah** di `StokDashboardController`: `buildAgingStok`, `buildTopMovement` (90 hari), `buildTrendHarga` (>5%), `buildStokMati` (>30 hari tanpa keluar)
- **File baru (4 halaman):** `stok/aging.blade.php`, `stok/top-movement.blade.php`, `stok/trend-harga.blade.php`, `stok/stok-mati.blade.php` — masing-masing dengan filter + paginate 30
- **Route:** +4 (`stok.aging`, `stok.top-movement`, `stok.trend-harga`, `stok.stok-mati`)
- Tombol "Lihat Semua →" di setiap widget dashboard

#### 6. Bug Fix: StockMovement created_at NULL
- **Root cause:** `StockMovement::$timestamps = false` tanpa auto-set → POS movements punya `created_at = NULL`
- **Fix:** `app/Models/StockMovement.php` tambah `boot()` dengan `creating` hook: `$m->created_at ??= now()`
- Query `buildTopMovement` diupdate: `WHERE (created_at >= $since OR created_at IS NULL)` untuk backward compat data lama

#### 7. BEP Auto-fill Enhanced
- `BepController::autoFill()` step 4 baru: query `order_items JOIN orders` WHERE cabang+periode, GROUP BY item_id → `SUM(qty)` sebagai `target_penjualan_unit`
- Harga dari `Item.harga_jual` + `Item.harga_beli_terakhir` (snapshot saat auto-fill)
- `updateOrCreate` by `(bep_setting_id, nama_produk)` — idempotent, skip `harga_jual=0`
- Fallback ke master `produk_jadi` jika tidak ada order bulan ini
- Flash message informatif (created/skipped/no_order)
- UI hint per product card menampilkan `$p->catatan`
- **File diubah:** `BepController.php`, `bep/setting.blade.php`

---

*(Status terkini: lihat tabel di atas — per 2026-06-22)*

**Tabel DB baru/diubah (sesi 2026-06-17):**
- `stock_batches` (BARU): FIFO batch per lokasi, SoftDeletes
- `order_items`: +kolom `hpp DECIMAL(15,2)`
- `cabangs.tipe`: enum tambah `'head_office'`
- `stock_movements`: boot hook auto-set `created_at`

---

### 2026-06-13 / 2026-06-14 — Major Feature Release
- ✅ **Modul Absensi Face Recognition** — 4 tipe absensi (Masuk/Keluar/Lembur Masuk/Lembur Keluar), liveness detection (EAR threshold 0.25, kedip 2x + gerakan kepala, timeout 10s), validasi GPS Haversine, shift dinamis (CRUD), hari libur (nasional + per cabang), dashboard real-time, laporan + export Excel/PDF, konfirmasi full-screen countdown 8 detik, setting GPS via peta Leaflet 1.9.4 + Nominatim search
- ✅ **Sistem Audit Trail** — spatie/laravel-activitylog v4, HasAuditLog trait, SoftDeletes pada 15+ model, halaman Audit Log + Data Terhapus + Backup Database, 9 permission keamanan, backup schedule daily 02:00 WIB
- ✅ **Cascade Delete & Restore** — CascadeDeleteService (7 entity × 3 method = 21 method + cleanupOrphan), DB::table soft-cascade pattern, cascade-delete-modal component (reusable), cascade restore via TrashController elif chain, cleanupOrphanSoftDeleted() untuk perbaiki data lama
- ✅ **Permission System** — ~50+ total permissions, Owner bypass via Gate::before, 13+ permission groups, middleware `permission:xxx` konsisten
- ✅ **Pengaturan Penggajian** — singleton model PengaturanGaji, tarif lembur/alpha/telat, method getSetting(), override per slip gaji
- ✅ **Bug fixes** — format rupiah regex (desimal vs ribuan), serve foto absensi via custom `/img/{path}` route, Leaflet peta konflik CSS, RoleUser enum string comparison, 403 pada cleanup route
- ✅ Total **~110+ file** dibuat/diubah dalam 2 sesi pengembangan ini
