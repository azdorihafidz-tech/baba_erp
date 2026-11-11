# AUDIT SISTEM — Base Project (Berkah Mulyo → erp-dimsum)

> Dokumen ini dibuat lewat audit read-only terhadap codebase `D:\xampp\htdocs\erp-dimsum` per 2026-09-13. Sumber utama: `CLAUDE.md` (66 rule bisnis + changelog panjang) di-cross-check terhadap kode aktual (`app/`, `resources/views/`, `database/migrations/`, `routes/`). Tidak ada file yang diubah selain file ini.

## Ringkasan Eksekutif

`erp-dimsum` adalah **copy identik** dari ERP "Berkah Mulyo" (jasa giling daging + produksi bakso/sosis/tempura), belum ada satupun penyesuaian bisnis untuk dimsum/mentai selain kemungkinan `.env` APP_NAME. Sistemnya sangat matang — Laravel 12 / PHP 8.2, ~100 migration, 66 aturan bisnis terdokumentasi, dan riwayat pengembangan puluhan sesi dengan banyak bugfix production yang sudah diverifikasi. Modul-modul generik (multi-cabang, stok FIFO, keuangan/akuntansi SAK ETAP, HR/absensi wajah, aset, BEP, loyalty, audit trail) **hampir semuanya reusable apa adanya** karena tidak spesifik ke domain "daging gilingan" — mereka bekerja di level "Item", "Order", "Cabang" generik.

Titik yang **genuinely spesifik ke bisnis daging** dan perlu diadaptasi untuk dimsum/mentai: (1) terminologi "Jasa Giling" (tarif per kg daging) sebagai lini bisnis inti kedua di samping produk jadi — dimsum/mentai kemungkinan besar tidak punya model "jasa giling", cuma jual produk jadi (per pcs/porsi), jadi field `berat_daging`, dropdown satuan kg/ons/gram di POS, dan seluruh alur `tipe_order='jasa_giling'` perlu dikaji ulang relevansinya; (2) Master Resep Bumbu Standar (dirancang untuk bumbu bakso per kg gilingan, satuan g/ml per kg) — konsepnya (resep→auto-isi POS→auto-potong stok) **sangat reusable** untuk resep dimsum/mentai tapi field "per kg gilingan" perlu jadi "per porsi/pcs"; (3) seed data (Item, JenisOlahan="Bakso/Sosis/Tempura", KategoriTransaksi contoh) semuanya konten Berkah Mulyo yang perlu diganti; (4) branding (nama perusahaan, logo) sudah punya jalur konfigurasi bersih (`PengaturanUmum` singleton + `Storage::disk('public')`) sehingga rebranding adalah tugas paling ringan di seluruh audit ini.

---

## A. Overview & Arsitektur

### 📋 Apa Yang Sudah Ada
- **Stack**: Laravel **12.0**, PHP **^8.2**, MySQL. `composer.json` konfirmasi: `barryvdh/laravel-dompdf ^3.1`, `maatwebsite/excel ^3.1`, `pusher/pusher-php-server ^7.2`, `silviolleite/laravelpwa ^2.0`, `spatie/laravel-activitylog ^4.12`, `spatie/laravel-backup ^9.3`. **Tidak ada `spatie/laravel-permission`** — sistem permission 100% custom (lihat Bagian D).
- **Frontend**: Bootstrap 5.3.3 (CDN), Select2 v4 (CDN), SweetAlert2 v11 (CDN), Bootstrap Icons 1.11.3 (CDN), Chart.js (CDN), jQuery (untuk Select2). Vite hanya untuk build asset ringan (`package.json`: Tailwind+Alpine terpasang di devDependencies tapi **tidak dipakai secara aktif** di layout utama — layout pakai CDN Bootstrap langsung, bukan Tailwind).
- **Laravel 12 style**: tidak ada `app/Http/Kernel.php` — pakai `bootstrap/app.php` (konvensi baru Laravel 11/12). Scheduler didaftarkan lewat `routes/console.php` (`Schedule::command()`), bukan `app/Console/Kernel.php` (dikonfirmasi oleh Rule bisnis #46 CLAUDE.md dan `routes/console.php` aktual).
- **Auth pattern**: Laravel Breeze standar (folder `app/Http/Controllers/Auth/`), role disimpan sebagai kolom `role` enum (`App\Enums\RoleUser`) di tabel `users` — bukan tabel `roles` terpisah.
- **Gate pattern** (`app/Providers/AppServiceProvider.php`): `Gate::before` bikin Owner bypass semua ability; semua nama permission di tabel `permissions` di-loop dan didaftarkan sebagai `Gate::define()` dinamis (di-cache 1 jam, key `all_permission_names`) yang delegasikan ke `$user->hasPermission($permName)`. 12 model Observer didaftarkan di sini (Stock, StockRequest, Item, User, PurchaseOrder, Order, Supplier, Karyawan, Penggajian, Cuti, Asset, Cabang, TransaksiKeuangan) — pola konsisten "Observer untuk history/audit".
- **Recurring transaction trigger**: dipicu on-request (bukan cuma cron) via `triggerRecurringIfNeeded()` di `AppServiceProvider::boot()`, dengan cache key harian supaya tidak double-run.
- **Struktur folder**: standar Laravel + tambahan `app/Enums/`, `app/Traits/` (`HasCabang`, `HasAuditLog`, `FillsDeletedBy`), `app/Observers/`, `app/Services/` (29 service class), `app/Models/Scopes/CabangScope.php` (catatan penting: menurut Rule bisnis #34, scope ini **tidak pernah didaftarkan** sebagai global scope aktif — jadi filter cabang dilakukan manual per-controller, bukan otomatis).

### ✅ Cocok Reuse untuk Dimsum
- Seluruh arsitektur inti (multi-cabang, permission custom, audit trail, cascade soft-delete) 100% generik — tidak menyentuh domain daging sama sekali. Reuse langsung.
- Base layout, komponen Blade reusable, dan seluruh sistem notifikasi/panduan — tidak perlu diubah struktural.

### 🔧 Perlu Dimodif
- `.env` `APP_NAME`, dan `PengaturanUmum.nama_perusahaan` default seed (`'Berkah Mulyo'` di 3 tempat: `PengaturanUmumController::edit()`/`update()`, `PengaturanUmum::getSetting()`).
- Nama file publik `public/images/logo.png` (dipakai hardcode sebagai favicon fallback + PDF header, lihat Bagian N).

### ❌ Gap / Missing
- Tidak ada `app/Http/Kernel.php` untuk dicek middleware global — perlu baca `bootstrap/app.php` langsung kalau mau menambah middleware baru (sudah dikonfirmasi ada di root, tidak diaudit detail isinya di sesi ini karena scope besar; disarankan baca sebelum menambah middleware).

### 💡 Rekomendasi
Tahap Branding (roadmap #1) bisa mulai dari sini — ubah 3 titik `'Berkah Mulyo'` + `.env` + file logo, dan itu sudah 80% dari kebutuhan branding dasar karena footer struk/PDF header semua reuse `PengaturanUmum`.

---

## B. Struktur Menu & Routing

### 📋 Apa Yang Sudah Ada
`routes/web.php` (817 baris) — semua route autentik di dalam grup `middleware(['auth','verified','cabang'])`. Modul dikelompokkan dengan prefix jelas: `dashboard/*`, `cabang/*`, `item/*`, `stok/*`, `stock-request/*`, `stock-transfer/*`, `user/*`, `role/*`, `pengaturan/*`, `profile/*`, `supplier/*`, `pembelian/*` (prefix group dengan middleware `can:` per-route — pola paling modern/aman di codebase, lihat Rule #40), `antrian/*` (operator/produksi/cek/display — display tanpa auth untuk TV), `penjualan/*` (POS + riwayat + batalkan/kembalikan/tandai-pengganti), `master/resep-bumbu/*`, `loyalty-program/*` + `loyalty-klaim/*`, `shift/*`, `absensi*` (termasuk face-attendance & absen-device), `penggajian/*`, `cuti/*`, `evaluasi/*`, `keuangan/*` (+ `transfer-antar-kas/*`, `coa/*`, `transfer-dana` = alias URL utk `setoran.*`), `aset/*`, `laporan/*` (~25 sub-laporan berbeda), `audit-log/*`, `trash/*`, `backup/*`, `pemakaian-perlengkapan/*`.

**Route publik tanpa auth** (untuk TV/hardware): `/img/{path}` (serve file storage manual, dipakai di shared hosting tanpa symlink — lihat Bagian T), `/serviceworker.js`, `/offline.html` (PWA), `/antrian/display/{cabang}` + `/antrian/data/{cabang}` (TV Antrian publik), `/absen/scan` + `/face-attendance/proses` (device absen wajah, autentikasi via device token bukan user login).

Sidebar (`resources/views/layouts/app.blade.php`, ~1090 baris relevan) dikelompokkan: Dashboard → Penjualan (POS, Riwayat, Pelanggan, Jenis Olahan, Resep Bumbu, Loyalty) → Antrian Produksi (Cek/Kelola/TV Display) → Stok & Gudang (Dashboard FIFO, Stok Barang, Permintaan, Transfer, Master Barang, Pemakaian Perlengkapan) → Pembelian (PO, Supplier, Dashboard PO) → Keuangan (Dashboard, Kas&Transaksi, Laporan Keuangan, Transfer Antar Kas, Transfer/Perpindahan Dana, Kategori Transaksi, COA, BEP, Recurring) → HR/SDM (Karyawan, Absensi submenu collapsible, Penggajian, Cuti, Evaluasi 360) → Aset → Laporan (submenu collapsible ~20 item) → Pengaturan (Cabang, User, Role, Pengaturan Umum, Pengaturan Penggajian, Tooltip Admin, Panduan Admin) → Bantuan (Panduan) → Keamanan (Audit Log, Data Terhapus, Backup).

Setiap link digate `@can`/`@canany` sesuai permission — polanya sangat konsisten (section wrapper `@canany([...])` di luar, `@can` individual di dalam per-link).

### ✅ Cocok Reuse untuk Dimsum
Seluruh struktur routing & sidebar 100% reusable — tidak ada nama route/URL yang menyebut "daging"/"giling" secara literal di level routing (istilah domain-spesifik ada di level Controller/View/data, bukan URL).

### 🔧 Perlu Dimodif
- Label teks di sidebar untuk "Jenis Olahan" (`master.jenis-olahan.*`) — relevan kalau dimsum juga punya varian olahan, tapi istilah "Olahan" kemungkinan tetap cocok (dimsum: siomay/hakau/lumpia, mentai: varian saus). Cek relevansi field.
- Label "Master Resep Bumbu" — konsepnya reusable sebagai "Master Resep" generik.

### ❌ Gap / Missing
Tidak ada perubahan struktural dibutuhkan di layer routing.

### 💡 Rekomendasi
Roadmap tahap 3 (POS) & tahap 2 (Master Data) HANYA perlu sentuh isi Controller/View, bukan `routes/web.php`.

---

## C. Database & Model

### 📋 Apa Yang Sudah Ada
~100 file migration (lihat daftar di eksplorasi). Tabel-tabel utama per domain:

**Multi-cabang**: `cabangs` (+GPS, jam_masuk, radius_absen, device_absen_token, footer_struk, running_text, izinkan_sembunyi_harga_struk, tipe termasuk `head_office`), `cabang_user` (pivot).

**User & Permission**: `users` (+role enum, SoftDeletes), `permissions`, `role_permissions` (pivot custom dgn kolom `role` string langsung, BUKAN model_has_roles ala Spatie).

**Penjualan/POS**: `orders` (+tampil_di_antrian, parent_order_id, alasan_pembatalan_*, bukti_pembayaran, pelanggan_id, antrian kolom), `order_items` (+hpp, jenis_olahan string), `pelanggans`.

**Master Produk & Stok**: `items` (+jenis enum bahan_baku/perlengkapan, track_stok, ItemCategory FK), `item_categories`, `stocks` (per item+lokasi, qty_minimum per lokasi), `stock_movements`, `stock_batches` (FIFO, harga_beli_per_unit), `stock_requests`+items, `stock_transfers`+items, `jenis_olahans`, `resep_bumbu`+`resep_bumbu_items`, `pemakaian_perlengkapans`.

**Pembelian**: `suppliers`, `purchase_orders` (+tanggal_kirim), `purchase_order_items`.

**Keuangan/Akuntansi**: `kas` (per cabang, default_untuk, saldo_minimum, SoftDeletes), `transaksi_keuangans` (+kategori_id, kategori_pengeluaran, referensi_type/referensi_id polymorphic, status_setoran, setoran_pair_id, SoftDeletes), `kategori_transaksis` (+kode_akun_coa, tipe_biaya), `chart_of_accounts` (59 akun SAK ETAP), `recurring_transaksis`, `neraca_settings` (singleton).

**HR**: `karyawans` (+face_data, face_photos, shift_id, fingerprint_pin), `shifts`, `hari_liburs`, `absensis` (unified record, unique karyawan+tanggal+cabang), `face_attendances` (log per-scan), `absen_devices`, `cutis`, `saldo_cutis`, `penggajians` (+override kolom), `evaluations`+`evaluation_periods`/`evaluation_aspects`/`evaluation_reviewers`/`evaluation_scores`/`evaluation_summaries`.

**Aset**: `assets`, `asset_categories`, `asset_depreciations`, `asset_maintenances`, `asset_mutations`, `asset_disposals`.

**BEP**: `bep_settings`, `bep_fixed_cost_items`, `bep_products`, `bep_reports`.

**Loyalty**: `loyalty_programs` (+tipe_program auto_track/event_based, nominal_voucher), `loyalty_pencapaian`, `loyalty_klaims`.

**Panduan/Notif**: `panduan`, `tooltips`, `notifications` (Laravel bawaan).

**Audit/Keamanan**: `activity_log` (spatie), + ~24 tabel `*_histories` (satu per tabel utama sesuai Rule #CLAUDE.md — `stock_histories`, `order_histories`, `karyawan_histories`, `cabang_histories`, `user_histories`, dll — semua dengan kolom `data_lama` JSON snapshot).

### ✅ Cocok Reuse untuk Dimsum
Skema DB **sangat generik** — tidak ada kolom bertipe "daging"/"giling" di level DB (mis. `items.tipe` cuma enum `bahan_baku/produk_jadi/kemasan/lainnya`, generik). Semua tabel HR, Keuangan, Aset, BEP, Loyalty, Audit — reuse 100% tanpa migration baru.

### 🔧 Perlu Dimodif
- `order_items.jenis_olahan` (string, bebas ketik) dan `order_items.berat_daging` (`DECIMAL(10,3)`, WAJIB diisi utk `tipe_order='jasa_giling'` via `OrderRequest`) — field-field ini **spesifik ke model bisnis "jasa giling per kg"**. Kalau dimsum tidak jual jasa giling (kemungkinan besar), field ini jadi tidak terpakai tapi TETAP ADA di skema (tidak masalah dibiarkan NULL/kosong — tidak perlu migration DROP kolom, cukup tidak dipakai di form POS baru).
- `resep_bumbu_items.qty_per_kg`+`satuan` (g/kg/ml) — didesain untuk takaran bumbu **per kg gilingan bakso**. Untuk resep dimsum (per porsi/pcs), field `qty_per_kg` perlu direinterpretasi jadi "qty per porsi" — nama kolom akan terasa salah literal tapi fungsinya (takaran per unit produksi) tetap sama, jadi bisa REUSE tanpa migration kalau tim menerima nama kolom yang agak menyesatkan, atau butuh migration rename kalau ingin bersih.

### ❌ Gap / Missing
- Tidak ada tabel "varian produk" (mis. ukuran porsi, level pedas) — kalau dimsum/mentai butuh varian per produk (isi 5pcs vs 10pcs, level saus), perlu tabel baru atau field tambahan di `items`.
- Tidak ada konsep "kombo/paket" (bundling beberapa item jadi 1 harga) — kalau bisnis dimsum jual paket, perlu modul baru (di luar cakupan sistem yang ada).

### 💡 Rekomendasi
Untuk roadmap tahap 2 (Master Data), gunakan `items.tipe='produk_jadi'` + `item_categories` untuk kategori dimsum (siomay/hakau/lumpia/dll) dan mentai (original/spicy/dll) — tidak perlu migration baru, murni seed data.

---

## D. Auth, User & Permission system

### 📋 Apa Yang Sudah Ada
- `app/Models/User.php`: `role` di-cast ke `RoleUser` enum (7 role: Owner, AdminPusat, AdminGudang, ManajerCabang, Kasir, OperatorProduksi, Helper). `hasPermission()`/`getPermissions()` query `role_permissions` JOIN `permissions` (cache 5 menit per role, key `role_permissions_{role}`), Owner selalu `true` tanpa query. Relasi `cabangs()` via pivot `cabang_user` (bukan `belongsTo` tunggal — user bisa multi-cabang).
- `PermissionSeeder.php` — 138 baris definisi permission (lewat grep, ~138 permission name literal atau garis kode terkait — perlu dibaca detail kalau mau list lengkap, tapi confirmed banyak group: aset, bep, cabang, evaluasi, hr, keuangan, pelanggan, pembelian, penjualan, antrian, stok, user, keamanan, pengaturan, laporan, master, loyalty, akuntansi, dashboard).
- `RolePermissionSeeder.php` + `HelperRolePermissionSeeder.php` — assignment default per role, banyak permission BARU sengaja **tidak** di-assign default (harus dicentang manual di UI Role).
- Gate registration dinamis (lihat Bagian A) — pattern `@can('modul.aksi')` konsisten di Blade + `abort_unless(auth()->user()->can('...'), 403)` di controller (defense-in-depth ganda, per Rule #40).
- `role/index.blade.php` — UI Role & Hak Akses **generic/dynamic**, loop dari DB by `group` — permission baru otomatis muncul di UI tanpa perlu ubah View.

### ✅ Cocok Reuse untuk Dimsum
100% reusable — sistem permission tidak menyentuh domain bisnis sama sekali.

### 🔧 Perlu Dimodif
Tidak ada perubahan wajib untuk rebranding. Kalau struktur organisasi dimsum beda (mis. tidak butuh role `OperatorProduksi` terpisah dari `Kasir`), itu keputusan bisnis opsional, bukan kebutuhan teknis.

### ❌ Gap / Missing
Tidak ditemukan gap teknis di modul ini.

### 💡 Rekomendasi
Biarkan permission system apa adanya. Kalau ada modul baru khusus dimsum, ikuti Rule #40 CLAUDE.md (definisikan permission granular → pakai di controller → assign role → gate sidebar → otomatis muncul di UI Role).

---

## E. Master modules (Cabang, Karyawan, Item, Supplier, Pelanggan)

### 📋 Apa Yang Sudah Ada
- **Cabang** (`CabangController.php`): CRUD + toggle aktif + assign/remove user + GPS setting (Leaflet+Nominatim) + footer struk + running text + `izinkan_sembunyi_harga_struk`. Tipe: `cabang`/`gudang_pusat`/`head_office`.
- **Karyawan** (`KaryawanController.php`, model `Karyawan.php`): biodata + `shift_id` + face recognition (`face_data` LONGTEXT descriptor, `face_photos` JSON, `face_registered_at`) + `fingerprint_pin` (fitur fingerspot pernah ada lalu di-remove — lihat migration `remove_fingerspot_integration.php`, jadi field fingerprint mungkin vestigial).
- **Item** (`ItemController.php`, model `Item.php`): `kode_item`, `nama_item`, kategori (`ItemCategory` FK), `tipe` (bahan_baku/produk_jadi/kemasan/lainnya), `jenis` (bahan_baku/perlengkapan — Fase 5), `satuan`, `harga_jual`, `harga_beli_terakhir` (auto-update dari PO), `qty_minimum` (global, beda dari `stocks.qty_minimum` per lokasi — lihat Rule #27), `track_stok` boolean. SoftDeletes+HasAuditLog+FillsDeletedBy.
- **Supplier** (`SupplierController.php`): CRUD + toggle aktif, dipakai di PO.
- **Pelanggan** (`PelangganController.php`): CRUD, dipakai di POS (nama+telepon wajib per Rule bisnis validasi POS) dan Loyalty.

### ✅ Cocok Reuse untuk Dimsum
Semua master module 100% generik — `Item` sudah dirancang untuk 4 tipe umum (bahan baku, produk jadi, kemasan, lainnya) yang cocok untuk apapun termasuk dimsum (bahan baku: udang/tepung/kulit pangsit; produk jadi: siomay/hakau/mentai; kemasan: box/plastik).

### 🔧 Perlu Dimodif
- Data seed (`ItemSeeder.php`, `ItemCategorySeeder.php`) — isinya konten Berkah Mulyo (daging, bumbu bakso, dll), perlu diganti total dengan master data dimsum/mentai. Ini murni **data**, bukan kode.
- `Karyawan.fingerprint_pin` kemungkinan vestigial (fitur fingerspot sudah di-remove per migration) — cek apakah masih dipakai di UI sebelum dianggap dead code.

### ❌ Gap / Missing
Tidak ditemukan gap struktural.

### 💡 Rekomendasi
Roadmap tahap 2 (Master Data) = replace seeder content untuk `items`, `item_categories`, `jenis_olahans` (kalau dipakai), `resep_bumbu` — effort Low-Medium karena struktur tabel tidak berubah, murni isi data.

---

## F. POS/Penjualan module (mendalam)

### 📋 Apa Yang Sudah Ada
Alur order-to-payment lengkap di `PenjualanController.php` (528 baris) + `PenjualanService.php` (411 baris):
- `pos()` — render form POS (`resources/views/penjualan/pos.blade.php`), termasuk cabang aktif, kas per tipe pembayaran, resep bumbu aktif.
- `resepBumbuItems()` — endpoint AJAX `GET /pos/resep-bumbu/{id}?berat=X`, konversi otomatis satuan resep (g/ml)→kg, kembalikan harga sesuai `mode_harga` (gratis/pakai_master).
- `store()` (`OrderRequest` validasi) — nama+telepon pelanggan wajib, item minimal 1, berat jasa giling wajib `required_if:tipe,jasa_giling`, hitung ulang total server-side sebelum panggil `PenjualanService::buatOrder()`. Support **AJAX (`expectsJson()`)** dan flow lama (redirect) — checkout modern pakai `fetch()`, modal struk in-place tanpa navigasi (demi stabilitas Web Bluetooth gesture).
- `PenjualanService::buatOrder()` — inti logic: hitung total per item, tentukan `kas_id` (WAJIB ada Kas aktif utk tipe pembayaran, kalau tidak throw Exception — Rule #35), potong stok via `StokService::keluar()` (FIFO, isi `order_items.hpp`), catat `TransaksiKeuangan`, generate nomor order (`generateNomorOrder()`) & nomor transaksi (`generateNomorTransaksi()`), set nomor antrian (kecuali `tampil_di_antrian=false`).
- `batalkan()`/`kembalikan()` — rule hari-sama utk non-Owner, alasan wajib, activity log, restore stok (dengan bug historis yang sudah di-fix per Rule #60 — batch baru senilai HPP asli).
- `cekPengganti()`/`tandaiPengganti()` — deteksi order pengganti (`parent_order_id`) otomatis/manual.
- **Fitur POS**: item selection dari master Item + custom item manual (nama bebas, `item_id=NULL` — dipakai utk baris "Jasa Giling"), dropdown satuan berat kg/ons/gram (auto-convert ke kg sebelum kirim), Master Resep Bumbu auto-populate (pilih resep+berat→auto isi baris), checkbox "Tampilkan di Antrian Produksi" (default ON), checkbox sembunyi-harga-per-item-di-struk (kondisional per cabang), diskon per order (field ada tapi TIDAK ter-refleksi di breakdown laporan per-item — Rule #31), pembayaran tunai/qris/transfer, cash drawer (Bluetooth ESC/POS) auto-buka utk Tunai, printer Bluetooth thermal dengan silent-reconnect+fallback, struk 2 mode render (HTML preview + JS ESC/POS builder — **harus disinkron manual, 2 sumber data terpisah**, per Rule #37). **Tidak ada split-bill atau save-bill/hold-order** — order langsung final saat submit (tidak ditemukan fitur "simpan draft order" di codebase).
- **Antrian Produksi**: `AntrianOperatorController`/`AntrianDisplayController`/`AntrianCekController` — nomor antrian otomatis per cabang per hari, TV Display publik (`/antrian/display/{cabang}`), Mode Produksi fullscreen utk operator tandai mulai-kerja/selesai/simpan-rak/diambil.

### ✅ Cocok Reuse untuk Dimsum
- Struktur order (pelanggan, item-item, pembayaran, struk, antrian produksi) **sangat reusable** — dimsum/mentai juga butuh antrian produksi (siomay dikukus, mentai digoreng) sama persis konsepnya.
- Loyalty tombol di modal struk, sembunyi-harga, cash drawer, printer Bluetooth — semua generik, tidak spesifik daging.

### 🔧 Perlu Dimodif
- **Field `berat_daging`+dropdown satuan kg/ons/gram** — dimsum/mentai kemungkinan dijual per **porsi/pcs**, bukan per kg. Perlu keputusan: (a) kalau dimsum TETAP ada varian "custom order per berat" (misal jual mentai per 100gr), field ini reusable apa adanya; (b) kalau 100% per pcs/porsi, form POS `tambahJasaGiling()` (fungsi existing) mungkin tidak relevan sama sekali dan POS bisa disederhanakan ke alur "pilih item→qty pcs→harga" murni (yang sebenarnya SUDAH ada sebagai alur "Order Produk Jadi", terpisah dari alur jasa giling).
- Terminologi "Jasa Giling" di label UI, field `tipe_order`, dan validasi `OrderRequest` (`required_if:tipe,jasa_giling`) — kalau dimsum tidak punya lini bisnis ini sama sekali, field bisa dibiarkan tidak terpakai (tidak wajib dihapus), tapi label UI "Jasa Giling" akan membingungkan kasir kalau tetap tampil.
- Dropdown "Jenis Olahan" (Bakso/Sosis/Tempura) per baris order — perlu diganti ke daftar jenis dimsum/mentai kalau konsepnya tetap dipakai (mis. "Kukus"/"Goreng" sebagai metode masak, atau dihapus kalau tidak relevan).

### ❌ Gap / Missing
- Tidak ada fitur split-bill.
- Tidak ada draft/hold order (transaksi harus selesai sekali submit).
- Tidak ada varian produk built-in (level pedas, ukuran porsi) — kalau dibutuhkan, POS perlu extend form item row.

### 💡 Rekomendasi
Roadmap tahap 3 (POS) adalah **titik kerja terbesar** dari 7 tahap — perlu keputusan bisnis dulu (apakah ada "custom per berat" utk dimsum) sebelum menyederhanakan/mengubah `pos.blade.php` dan `OrderRequest`. Kompleksitas: **High**, karena JS POS (`pos.blade.php`) sangat besar dan tersambung erat ke printer Bluetooth+cash drawer+resep bumbu+antrian.

---

## G. Stok & Gudang

### 📋 Apa Yang Sudah Ada
- Alur: PO diterima → `StokService::masuk()` (create `StockBatch` dgn harga beli, update `stocks.qty`) → transaksi POS → `StokService::keluar()` (FIFO consume dari `stock_batches`, hitung HPP, catat `StockMovement`) → antar cabang via `StockRequest`(permintaan)/`StockTransfer`(pengiriman).
- `StokDashboardController` — dashboard FIFO dgn insight aging/top-movement/trend-harga/stok-mati, filter tipe item.
- Threshold minimum: `items.qty_minimum` (global) vs `stocks.qty_minimum` (per lokasi, inherit otomatis dari global saat row baru dibuat — fix per Rule #27).
- Adjustment stok (`StokController::adjustmentForm/Store`) dgn alasan susut/rusak/hilang (masuk keuangan) vs salah_hitung/audit (tidak masuk keuangan) — Rule #33.
- Reset stok (Owner-only, konfirmasi ketik ulang nama item) utk cleanup data.

### ✅ Cocok Reuse untuk Dimsum
100% reusable — sistem stok FIFO/HPP/multi-lokasi tidak spesifik ke daging sama sekali, cocok untuk bahan baku dimsum apapun (udang, tepung, kulit pangsit, dsb).

### 🔧 Perlu Dimodif
Tidak ada perubahan kode wajib. Hanya data seed unit/satuan (kg/pcs/liter) disesuaikan bahan baku dimsum.

### ❌ Gap / Missing
Tidak ditemukan gap.

### 💡 Rekomendasi
Reuse penuh, effort Low untuk roadmap tahap 4 (kalau resep dimsum konsisten pakai qty per-porsi bukan per-kg — lihat Bagian H).

---

## H. Produksi & Resep

### 📋 Apa Yang Sudah Ada
- `ResepBumbu`+`ResepBumbuItem` (`app/Http/Controllers/Master/MasterResepBumbuController.php`) — resep per jenis olahan, tiap item resep py `qty_per_kg`, `satuan`, `is_wajib`, `mode_harga`. Endpoint POS `GET /pos/resep-bumbu/{id}?berat=X` konversi otomatis & auto-isi baris order (memanggil fungsi JS `tambahJasaGiling()` existing berkali-kali — POS logic TIDAK disentuh, murni auto-fill form, per prinsip arsitektur Rule bisnis sesi 2026-07-23).
- `JenisOlahan` — master lookup sederhana (nama+slug+is_active), TIDAK ada `_histories`/audit log (Rule bisnis dikonfirmasi: preseden "master lookup sederhana" boleh tanpa history table).
- `PemakaianPerlengkapan`+`PemakaianPerlengkapanService` (Fase 5, Rule #66) — modul terpisah utk barang habis pakai non-produksi (masker, tissue), reuse `StokService::keluar()` murni, TIDAK terhubung ke resep produksi.

### ✅ Cocok Reuse untuk Dimsum
Konsep resep→auto-isi POS→auto-potong stok **sangat cocok** untuk dimsum: resep "Siomay Udang" bisa berisi item udang/tepung/kulit pangsit per porsi, sama persis mekanismenya dengan resep bumbu bakso per kg.

### 🔧 Perlu Dimodif
- **Kunci adaptasi**: `resep_bumbu_items.qty_per_kg` perlu direinterpretasi jadi "qty per unit produksi" (per porsi/pcs, bukan per kg gilingan). Karena endpoint AJAX menerima parameter `?berat=X` (dalam kg) untuk hitung proporsi, kalau basis produksi dimsum adalah "per porsi" (bukan per kg), endpoint dan JS pemanggilnya (`terapkanResep()` di `pos.blade.php`) perlu diubah dari "input berat gilingan (kg)" jadi "input jumlah porsi" — perhitungan proporsional (`qty_per_kg * berat`) berubah jadi (`qty_per_porsi * jumlah_porsi`), secara matematis identik, cuma unit basisnya beda.
- Tabel/model `ResepBumbu` sebaiknya di-rename konsepnya jadi "Master Resep" generik (tidak wajib rename kolom DB, tapi label UI "Resep Bumbu" → "Resep Produksi" akan lebih jelas untuk dimsum).

### ❌ Gap / Missing
Tidak ada BOM (Bill of Materials) versioning atau multi-level resep (resep di dalam resep) — kemungkinan tidak dibutuhkan untuk skala UKM.

### 💡 Rekomendasi
Roadmap tahap 4 — effort **Medium**: struktur tabel reusable penuh, tapi endpoint+JS proporsi butuh penyesuaian basis satuan (kg→porsi).

---

## I. Pembelian

### 📋 Apa Yang Sudah Ada
`PurchaseOrderController.php`+`PurchaseOrderService.php` — alur draft→disetujui→dikirim_supplier (+`tanggal_kirim`)→diterima→stok bertambah (`StokService::masuk()` dgn harga beli, auto-update `Item.harga_beli_terakhir`). Pembelian langsung cabang (`pembelian_langsung` flag) utk kondisi mendesak, butuh approval. Link ke `TransaksiKeuangan` via `referensi_type='purchase_order'`+`referensi_id` (polymorphic, bukan FK khusus — Rule #32). `PoDashboardService` — 5 bucket ringkasan status PO (Menunggu Approval/Perlu Dikirim/Dalam Perjalanan/Belum Diterima rollup/Belum Dibayar), widget dashboard `<x-po-status-widget>`.

### ✅ Cocok Reuse untuk Dimsum
100% generik — pembelian bahan baku dari supplier adalah proses universal, tidak spesifik daging.

### 🔧 Perlu Dimodif
Tidak ada.

### ❌ Gap / Missing
Tidak ditemukan.

### 💡 Rekomendasi
Reuse penuh, tidak ada roadmap tahap khusus dibutuhkan selain pastikan supplier/item baru sesuai dimsum.

---

## J. HR module

### 📋 Apa Yang Sudah Ada
- **Karyawan**: biodata, `shift_id`, face recognition (face-api.js v1.7.13, LONGTEXT descriptor).
- **Shift**: CRUD per cabang, `toleransi_menit` utk deteksi telat.
- **Absensi**: face recognition + liveness (head movement 1x geleng, 15 detik timeout — sudah dievolusi dari blink detection awal), validasi GPS Haversine per cabang, device khusus per cabang (`absen_devices`), unified 1 record per karyawan+hari+cabang.
- **Cuti**: pengajuan+approve/tolak, SoftDeletes (baru ditambahkan per audit 2026-07-27, awalnya hard-delete — Rule bisnis #40/#41 poin CutiObserver).
- **Penggajian**: generate dari rekap absensi (gaji pokok proporsional, lembur, potongan alpha/telat), override manual per slip.
- **Evaluasi 360°**: periode triwulanan, 5 aspek berbobot, 3 tipe penilai (atasan 50%/rekan 30%/self 20%), radar chart, ranking, anonimitas rekan kerja.

### ✅ Cocok Reuse untuk Dimsum
100% generik — HR/payroll/evaluasi tidak menyentuh domain bisnis daging sama sekali. Reuse penuh.

### 🔧 Perlu Dimodif
Tidak ada perubahan wajib.

### ❌ Gap / Missing
Tidak ditemukan gap baru di sesi audit ini (gap lama sudah terdokumentasi lengkap di CLAUDE.md Update Log, sebagian besar sudah di-fix).

### 💡 Rekomendasi
Modul HR bisa langsung dipakai tanpa modifikasi untuk operasional dimsum — tidak masuk roadmap 7 tahap rebranding kecuali struktur organisasi berbeda.

---

## K. Keuangan

### 📋 Apa Yang Sudah Ada
- **Kas**: per cabang, `default_untuk` (tunai/qris/transfer, unique constraint per cabang, null-aware terhadap soft-delete — Rule bisnis fix kas), `saldo_minimum`, `saldo_sekarang` (init dari `saldo_awal`).
- **TransaksiKeuangan**: pemasukan/pengeluaran, `kategori_id` (dinamis, FK `kategori_transaksis`), `kategori_pengeluaran` (enum sederhana terpisah), `referensi_type`/`referensi_id` polymorphic string (BUKAN morphMap Eloquent — WAJIB resolve manual, jangan pernah `$trx->referensi` langsung — Rule #46 poin kritis), `status_setoran`+`setoran_pair_id` (pasangan OUT/IN transfer antar cabang).
- **ChartOfAccount** (COA): 59 akun SAK ETAP, `kategori_transaksis.kode_akun_coa` mapping (nullable utk transfer internal — sengaja exclude dari Laba Rugi).
- **RecurringTransaksi**: generate otomatis on-request harian.
- **NeracaSetting**: singleton, `modal_owner` manual adjustable Owner.
- **TransferAntarKas** (mutasi dalam 1 cabang) vs **Setoran/Transfer-Dana** (antar cabang) — 2 mekanisme terpisah dgn kategori berbeda (`MUTASI-IN/OUT` vs `SETOR-IN/OUT`).

### ✅ Cocok Reuse untuk Dimsum
100% generik — akuntansi SAK ETAP + kas multi-cabang tidak spesifik ke daging. Reuse penuh.

### 🔧 Perlu Dimodif
`KategoriTransaksiSeeder.php` isi kategori kemungkinan sudah generik (Bahan Baku, Gaji, Sewa, dll) — cek isi seeder sebelum reuse, tapi kemungkinan besar tidak perlu diubah karena kategori pengeluaran bisnis F&B pada umumnya serupa.

### ❌ Gap / Missing
Tidak ditemukan gap baru.

### 💡 Rekomendasi
Reuse penuh, masuk roadmap tahap 5 (Keuangan/Setoran) hanya utk verifikasi kategori & setup kas awal tiap cabang dimsum — effort Low (murni data, bukan kode).

---

## L. Aset

### 📋 Apa Yang Sudah Ada
`Asset`+`AssetCategory`+`AssetDepreciation` (3 metode: garis lurus/saldo menurun/satuan produksi) +`AssetMaintenance`+`AssetMutation`+`AssetDisposal`. Auto depresiasi bulanan (`AssetDepreciationService::generateAsetTertentu()`, satu entry point dipakai manual/bulk/scheduler), insert `TransaksiKeuangan` non-cash otomatis ke akun `6-1104`.

### ✅ Cocok Reuse untuk Dimsum
100% generik — mesin kukus/wajan/freezer dimsum sama konsepnya dengan mesin giling daging. Reuse penuh.

### 🔧 Perlu Dimodif
Data seed `AssetCategorySeeder.php` (kategori: Mesin Produksi, Kendaraan, dll) kemungkinan sudah cukup generik, cukup tambah aset spesifik dimsum (steamer, freezer display) sebagai data baru, bukan ubah struktur.

### ❌ Gap / Missing
Tidak ditemukan.

### 💡 Rekomendasi
Tidak masuk roadmap 7 tahap kecuali entry data aset awal.

---

## M. BEP

### 📋 Apa Yang Sudah Ada
- Menu BEP manual (`BepController`+`BepCalculationService`) — Biaya Tetap/Variabel/Harga Jual per produk/jasa, formula standar.
- `BepOtomatisService` (Fase 3) — hitung BEP otomatis dari transaksi real: Biaya Tetap dari `kategori_transaksis.tipe_biaya='tetap'`, Biaya Variabel/Volume dari `order_items.hpp`/`berat_daging` KHUSUS `tipe_order='jasa_giling'`.

### ✅ Cocok Reuse untuk Dimsum
Menu BEP manual 100% reusable.

### 🔧 Perlu Dimodif
**`BepOtomatisService` punya ketergantungan literal ke `tipe_order='jasa_giling'`+`order_items.berat_daging`** (lihat Rule bisnis #47 CLAUDE.md) — kalau dimsum tidak punya order jasa giling sama sekali, source volume utk BEP Otomatis ini akan SELALU KOSONG (`total_kg=0` dari kueri yang expect data jasa giling). Perlu keputusan: (a) extend service utk hitung volume dari `order_items` produk jadi biasa (qty pcs × harga, bukan cuma kg jasa giling), atau (b) terima BEP Otomatis tidak berfungsi utk dimsum dan andalkan menu BEP manual saja.

### ❌ Gap / Missing
`BepOtomatisService::hitungBepOtomatis()` kemungkinan perlu logic baru utk basis volume "per pcs/porsi" — ini genuinely gap kalau BEP Otomatis mau dipakai untuk dimsum.

### 💡 Rekomendasi
Masuk roadmap tahap 5/6 sebagai catatan **flagged** — kalau BEP Otomatis dianggap fitur penting, perlu sesi terpisah utk extend `BepOtomatisService`. Menu BEP manual bisa dipakai sementara tanpa masalah.

---

## N. Pengaturan Umum (PENTING untuk fase branding)

### 📋 Apa Yang Sudah Ada
- `PengaturanUmumController.php` (`app/Http/Controllers/PengaturanUmumController.php:1-66`) — singleton `PengaturanUmum` model (`nama_perusahaan`, `logo_path`, `alamat_perusahaan`).
- Upload logo: `Storage::disk('public')->store('logo', 'public')` — validasi `image|mimes:jpg,jpeg,png,svg,webp|max:2048` (2MB), hapus file lama otomatis saat ganti/hapus logo. Permission `pengaturan.umum_lihat`/`pengaturan.umum_edit`.
- **Favicon/app icon TIDAK dari `PengaturanUmum`** — hardcode di layout: `resources/views/layouts/app.blade.php:12-14` pakai `asset('images/logo.png')` (file statis di `public/images/logo.png`) dan `public/images/icons/icon-192x192.png` (PWA icon, kemungkinan dari `silviolleite/laravelpwa` config, bukan upload dinamis).
- Setting UI-affecting lain: `cabangs.running_text_aktif`+`running_text` (per cabang), `cabangs.footer_struk` (teks custom di struk), `cabangs.izinkan_sembunyi_harga_struk`.
- Setting bisnis-affecting: `PengaturanGaji` singleton (tarif lembur/potongan), `NeracaSetting` singleton (modal owner).

### ✅ Cocok Reuse untuk Dimsum
Jalur upload logo dinamis via `PengaturanUmum` **sudah bersih dan reusable langsung** — tinggal upload logo baru dari UI, tidak perlu sentuh kode.

### 🔧 Perlu Dimodif
- **File statis `public/images/logo.png`** dan **`public/images/icons/icon-*.png`** (PWA icons) — ini file fisik di `public/`, BUKAN dikelola lewat `PengaturanUmum`, jadi ganti logo lewat UI Pengaturan Umum **TIDAK otomatis mengganti favicon/PWA icon**. Ini adalah 2 sistem logo yang terpisah — perlu diganti manual (replace file) utk favicon+PWA icon, terpisah dari upload logo dinamis yang dipakai di header/struk/PDF.
- Config PWA (`laravelpwa` — cek `config/laravelpwa.php` kalau ada) kemungkinan juga hardcode nama app "Berkah Mulyo" utk manifest.json.
- 3 titik default seed `'Berkah Mulyo'` (lihat Bagian A).

### ❌ Gap / Missing
Tidak ada UI utk upload favicon/PWA icon secara dinamis — kalau dibutuhkan, perlu extend `PengaturanUmumController` + field baru.

### 💡 Rekomendasi
Roadmap tahap 1 (Branding) — effort **Low** utk logo dinamis (langsung pakai UI existing), tapi **perlu langkah manual tambahan** utk favicon+PWA icon (replace file `public/images/logo.png`+`public/images/icons/*`+cek `config/laravelpwa.php`/`.env` app name utk manifest PWA). Sebaiknya cek `config/laravelpwa.php` sebelum go-live PWA dimsum.

---

## O. Dashboard & Laporan

### 📋 Apa Yang Sudah Ada
`DashboardController` (cabang/gudang/pusat, 3 mode) dengan banyak widget (`<x-po-status-widget>`, `<x-aset-snapshot-widget>`, `<x-neraca-snapshot-widget>`, `<x-dashboard-analytics-widget>`, `<x-jam-ramai-widget>`, `<x-loyalty-widget>`, `<x-perlengkapan-menipis-widget>`). ~25 controller Laporan (`Laporan*Controller.php`) mencakup Penjualan, Stok, Keuangan (Laba Rugi lama + Formal + Neraca + Buku Besar), HR (Absensi/Penggajian/Evaluasi), Aset, BEP (+Otomatis), Setoran (Harian + biasa), Konsumsi Bahan, Perlengkapan, Jam Ramai, Cabang vs Cabang, Eksekutif (9-halaman PDF), Simulator BEP interaktif. Export: PDF via `barryvdh/laravel-dompdf` (baru dipakai sejak Fase 2 Akuntansi — sebelumnya "export PDF" adalah print-via-browser), Excel via `maatwebsite/excel` (dan beberapa CSV manual `.xls` stream). Semua laporan pakai `<x-date-range-filter>` component dengan preset quick-filter.

### ✅ Cocok Reuse untuk Dimsum
Hampir semua laporan generik (Keuangan, HR, Aset, BEP manual, Loyalty) — reuse penuh. Laporan Penjualan/Konsumsi Bahan/Jam Ramai berbasis `orders`/`order_items` juga generik selama struktur order tetap sama.

### 🔧 Perlu Dimodif
- `LaporanKonsumsiBahanController`/`LaporanLabaRugiController` yang membedakan kategori "jasa" (`item_id NULL` atau `items.tipe='lainnya'`) — asumsi ini berbasis "Jasa Giling" sebagai lini bisnis. Kalau dimsum tidak punya baris item_id NULL sama sekali (semua order via master Item), breakdown "jasa" akan selalu kosong — tidak error, tapi kurang relevan.
- `LaporanBepOtomatisController` — tergantung `BepOtomatisService` yang perlu diadaptasi (lihat Bagian M).

### ❌ Gap / Missing
Tidak ada laporan "Produksi" berdiri sendiri (CLAUDE.md sendiri catat ini sebagai gap yang belum di-apply — "Laporan Produksi: total berat digiling + performa operator", masih pending per Update Log terakhir).

### 💡 Rekomendasi
Roadmap tahap 6 — effort Low-Medium, sebagian besar reuse, cuma perlu cek relevansi breakdown "jasa" dan keputusan soal `BepOtomatisService`.

---

## P. Loyalty

### 📋 Apa Yang Sudah Ada
- `LoyaltyProgram` (2 tipe: `auto_track` — progress kg otomatis dari order jasa giling; `event_based` — klaim manual dgn bukti posting sosmed).
- `LoyaltyPencapaian` (progress auto_track), `LoyaltyKlaim` (workflow pending→approved/rejected→issued, guard 1x per pelanggan kecuali rejected).
- Widget dashboard "Klaim Menunggu Approval", tombol POS "Buat Klaim Loyalty" di modal struk (3 syarat: pelanggan terdaftar, permission, ada program event_based aktif).

### ✅ Cocok Reuse untuk Dimsum
`event_based` (klaim manual + bukti) **100% reusable tanpa modifikasi** — tidak bergantung ke konsep "kg gilingan" sama sekali, cocok untuk reward apapun (misal "posting testimoni dimsum dapat voucher").

### 🔧 Perlu Dimodif
`auto_track` **bergantung ke `order_items.berat_daging` dan tipe order jasa_giling** (progress kg) — Rule #58 CLAUDE.md eksplisit menyebut `LoyaltyService::getWidgetData()`/`cekPencapaianBaru()` HARUS di-scope `->autoTrack()` justru karena `event_based` tidak boleh ikut logic kg. Kalau dimsum ingin loyalty berbasis "total belanja Rp" atau "total pcs dibeli" (bukan kg), `auto_track` type perlu diperluas basisnya — saat ini SPESIFIK ke kg.

### ❌ Gap / Missing
Tidak ada tipe loyalty berbasis "total spending Rp" atau "jumlah transaksi" — hanya kg (auto_track) dan manual klaim (event_based).

### 💡 Rekomendasi
Untuk dimsum, `event_based` bisa langsung dipakai. Kalau butuh auto-tracking berbasis belanja, perlu extend `LoyaltyService` — flagged sebagai pekerjaan opsional, bukan blocker roadmap inti.

---

## Q. Panduan & Tooltip

### 📋 Apa Yang Sudah Ada
`Panduan` model (tabel `panduan`, kolom `slug`+`judul`+`konten`+`modul`+`urutan`), di-render via `<x-panduan-button slug="xxx" />` (modal konten). `Tooltip` model serupa utk hint kontekstual. Admin edit via `Admin\PanduanController`/`Admin\TooltipController` (asumsi ada di folder `app/Http/Controllers/Admin/`). `PanduanKontenSeeder.php`/`PanduanPosSeeder.php`/`PanduanStubSeeder.php`/`TooltipKontenSeeder.php`/`TooltipAdjustmentSeeder.php` — semua pakai pola `updateOrCreate`/idempotent.

### ✅ Cocok Reuse untuk Dimsum
Struktur & mekanisme rendering 100% reusable.

### 🔧 Perlu Dimodif
**Isi konten panduan** (`PanduanKontenSeeder.php` dkk) sepenuhnya berbicara soal alur bisnis Berkah Mulyo (jasa giling, bakso, dll) — perlu ditulis ulang total kontennya utk dimsum, tapi strukturnya (slug per modul) tetap sama.

### ❌ Gap / Missing
Tidak ditemukan.

### 💡 Rekomendasi
Masuk roadmap tahap 7 (testing/go-live) sebagai item konten — effort Medium karena banyak (puluhan slug panduan) tapi bisa dikerjakan bertahap/paralel dengan tahap lain karena tidak memblokir fungsi teknis apapun.

---

## R. Notifikasi, Activity Log, Backup

### 📋 Apa Yang Sudah Ada
- Notifikasi: Laravel Notifications (database channel) + Pusher real-time utk bell icon (`SendBellPusherSignal` listener di `NotificationSent` event). Polling 30 detik.
- Activity log: `spatie/laravel-activitylog` v4, trait `HasAuditLog` dipakai di 16+ model (Keuangan, Penggajian, Order, PurchaseOrder, Supplier, Item, Stock, Karyawan, Asset, Cabang, Shift, HariLibur, User, Pelanggan, FaceAttendance, AbsenDevice, ChartOfAccount, dll — bertambah terus tiap fitur baru).
- Backup: `spatie/laravel-backup` v9, schedule daily 02:00 WIB, halaman `/backup` (list+trigger manual+download+cleanup retention).

### ✅ Cocok Reuse untuk Dimsum
100% reusable, tidak spesifik domain.

### 🔧 Perlu Dimodif
Tidak ada.

### ❌ Gap / Missing
Tidak ditemukan.

### 💡 Rekomendasi
Reuse penuh, tidak masuk roadmap 7 tahap.

---

## S. Frontend & UI pattern

### 📋 Apa Yang Sudah Ada
- Layout utama `resources/views/layouts/app.blade.php` (sidebar+topbar+dark-mode toggle+notification bell+cabang switcher), layout guest terpisah di `resources/views/layouts/` (auth pages), `layouts/print.blade.php` utk halaman print/PDF.
- Komponen Blade reusable (`resources/views/components/`): 29 file — mencakup dashboard widget, modal (`cascade-delete-modal`, `coa-cheat-sheet-modal`), form input (`input-rupiah`, `date-range-filter`, `search-box`), UI generik (`primary-button`, `modal`, `dropdown`, `tooltip`, `panduan-button`), dan struk (`struk-modal`).
- JS pattern: **jQuery + Select2** (dropdown searchable, dipakai luas di POS/form relasi), **SweetAlert2** (semua alert/confirm, fallback ke native `alert()`/`confirm()` kalau CDN gagal load — Rule bisnis POS UX), **Chart.js** (semua grafik dashboard/laporan), vanilla JS utk logic form kompleks (POS checkout, printer Bluetooth). **Tidak ada Alpine.js aktif** meski terpasang di `package.json` devDependencies — kemungkinan sisa scaffolding Breeze yang tidak dipakai di layout utama (worth diverifikasi kalau mau bersihkan dependency).
- Warna: Bootstrap 5 default + custom CSS inline di `<style>` block layout (dark-mode override utk `.nav-tabs`, sidebar nav-link states).

### ✅ Cocok Reuse untuk Dimsum
100% reusable — tidak ada elemen visual/warna yang menyebut brand Berkah Mulyo secara hardcode di CSS (perlu diverifikasi lebih lanjut kalau ada warna brand-spesifik, tapi tidak ditemukan indikasi kuat dari eksplorasi).

### 🔧 Perlu Dimodif
Palet warna/tema visual (kalau dimsum ingin identitas visual beda) — murni preferensi desain, bukan keharusan teknis.

### ❌ Gap / Missing
Tidak ditemukan gap fungsional.

### 💡 Rekomendasi
Tidak masuk roadmap 7 tahap wajib — opsional kalau ingin re-skin visual.

---

## T. File Upload & Media

### 📋 Apa Yang Sudah Ada
- Disk: `Storage::disk('public')` konsisten dipakai (logo, foto karyawan, foto absensi, dll — asumsi berdasarkan pola `PengaturanUmumController`, perlu verifikasi tiap controller upload foto individual kalau perlu detail lebih).
- **Serve file tanpa symlink** (utk shared hosting cPanel tanpa akses `artisan storage:link`): route custom `/img/{path}` (`routes/web.php:85-95`) yang baca `storage_path('app/public/'.$path)` dan `response()->file()` langsung, dengan middleware `auth` — pola ini didokumentasikan di CLAUDE.md sebagai workaround deployment shared hosting.
- Validasi upload logo: `image|mimes:jpg,jpeg,png,svg,webp|max:2048`.

### ✅ Cocok Reuse untuk Dimsum
100% reusable — pattern upload/serve file generik.

### 🔧 Perlu Dimodif
Tidak ada.

### ❌ Gap / Missing
Tidak ditemukan placeholder/missing-file handling eksplisit di audit ini (perlu cek langsung di view foto karyawan/item kalau relevan — tidak sempat diverifikasi detail per view karena volume besar).

### 💡 Rekomendasi
Reuse penuh, tidak masuk roadmap 7 tahap wajib.

---

## Catatan Kualitas Kode

- **Bug yang sudah di-fix (dikonfirmasi masih ada perbaikannya di kode saat ini)**: `AppServiceProvider.php` mengonfirmasi pola `withoutGlobalScopes()` dihindari di titik-titik kritis sesuai Rule #34 (recurring trigger pakai `withoutGlobalScopes()` tapi eksplisit `whereNull('deleted_at')` — konsisten pola aman). `User` model pakai `SoftDeletes`+`FillsDeletedBy` (Rule #41/#42) sesuai dokumentasi.
- **Risiko yang masih relevan untuk fase adaptasi dimsum**: `BepOtomatisService` dan `LoyaltyService` (`auto_track`) punya ketergantungan literal ke `tipe_order='jasa_giling'`+`order_items.berat_daging` — ini BUKAN bug, tapi **coupling ke domain daging** yang akan membuat 2 fitur ini pasif/tidak berfungsi untuk dimsum kecuali di-extend. Ini poin paling penting untuk didiskusikan dengan user sebelum lanjut ke tahap POS.
- **Duplikasi/inkonsistensi yang sudah terdokumentasi CLAUDE.md sendiri** (tidak diverifikasi ulang detail di sesi audit ini karena scope besar, tapi CLAUDE.md eksplisit menyebutnya sebagai "flagged, belum di-fix"): drift `stock_batches` vs `stocks.qty` akibat pembatalan order lama (Rule #58, sebagian sudah dikoreksi via seeder tapi berpotensi muncul lagi kalau `PenjualanService::batalkan()` untuk kasus "batal→pulihkan→batal lagi" — Rule #60 edge case yang sengaja tidak diperbaiki), dan potensi bug DomPDF multi-halaman di beberapa laporan PDF lama yang belum dipatch (Rule #48).
- **Code smell**: 2 sistem logo terpisah (dinamis via `PengaturanUmum` vs file statis `public/images/logo.png` utk favicon/PDF/PWA) — berpotensi membingungkan tim yang tidak tahu keduanya independen (lihat Bagian N).
- **package.json** memuat Tailwind+Alpine yang sepertinya tidak aktif dipakai (layout pakai Bootstrap CDN) — worth diverifikasi & dibersihkan kalau memang dead dependency, tapi tidak mendesak.

## U. Audit Ronde 2 — Detail File Ambigu

> Ronde audit fokus untuk memverifikasi 5 file yang di ronde 1 hanya diasumsikan dari nama/pola, bukan dibaca detail. Semua temuan di bawah dikonfirmasi lewat pembacaan file lengkap + grep empiris terhadap seluruh `app/`.

### U.1 config/laravelpwa.php (Icon PWA)

File (`config/laravelpwa.php:1-52`) berisi 1 array config untuk package `silviolleite/laravelpwa`:
- `name`/`manifest.name` = `'ERP Berkah Mulyo'` (baris 4, 6) — **hardcode literal**, bukan dari `PengaturanUmum` atau `.env`. Beda dari `<title>` di `app.blade.php:10` yang sudah pakai `config('app.name', 'ERP Berkah Mulyo')` (jadi title tab browser ikut `.env` `APP_NAME`, tapi manifest PWA TIDAK — 2 sumber nama app yang independen).
- `manifest.short_name` = `'BM ERP'` (baris 7) — juga hardcode literal, tampil sebagai label di homescreen Android/iOS saat PWA di-install.
- `background_color` = `#472c1d` (coklat tua, baris 9), `theme_color` = `#d0371b` (merah bata, baris 10) — sama persis dengan variabel CSS `--sidebar-bg`/`--sidebar-hover`/`--sidebar-active` di `app.blade.php` (dikonfirmasi lewat pembacaan baris 40-an di ronde 1) — jadi warna splash screen PWA sinkron manual dengan warna sidebar, BUKAN ditarik dari satu sumber config bersama. Ganti salah satu tanpa ganti yang lain akan bikin PWA splash/theme-color tidak match tema sidebar aplikasi.
- `start_url`='/', `display`='standalone', `orientation`='portrait', `status_bar`='black'.
- **8 ukuran icon** (baris 14-47): 72/96/128/144/152/192/384/512 px, semua path statis `/images/icons/icon-{size}x{size}.png` dengan `purpose` 'any' (192 & 512 tambahan 'any maskable'). **Tidak ada field/opsi untuk generate otomatis dari 1 source image** di dalam config ini — package `silviolleite/laravelpwa` (dicek: tidak ada folder `vendor/silviolleite` ter-install lokal di sesi ini untuk dibaca commandnya, tapi dari struktur config yang murni array-driven tanpa referensi ke command Artisan apapun) hanya **membaca path yang sudah didaftarkan** untuk merender tag `<link rel="manifest">` dan `manifest.json` — package ini **tidak menyediakan artisan command untuk generate icon dari 1 gambar sumber**; developer harus menyiapkan sendiri 8 file PNG ukuran berbeda secara manual (misal via tool eksternal seperti realfavicongenerator.io) lalu taruh di `public/images/icons/` dengan nama file persis sesuai daftar di atas.
- `splash: []`, `shortcuts: []`, `custom: []` — semua kosong, fitur splash-screen-per-device dan app-shortcuts dari package ini tidak dipakai sama sekali.
- **Tidak ada UI admin untuk mengubah config ini** — ini file config PHP murni (`config/laravelpwa.php`), beda total dari `PengaturanUmum` (model dengan CRUD lewat `/pengaturan/umum`). Mengubah nama/warna/icon PWA WAJIB edit file ini langsung + replace 8 file PNG fisik, tidak ada jalur dinamis sama sekali.

### U.2 bootstrap/app.php (Middleware Global & Routing Config)

File (`bootstrap/app.php:1-27`), pola Laravel 11/12 (`Application::configure()`), tidak ada `app/Http/Kernel.php`:
- **Routing** (baris 10-14): `web: routes/web.php`, `commands: routes/console.php` (bukan `app/Console/Kernel.php` — konsisten Rule bisnis #46 CLAUDE.md), `health: '/up'` (endpoint health-check bawaan Laravel 11+). **Tidak ada registrasi `api.php`** — dikonfirmasi tidak ada baris `api:` di `withRouting()`, artinya project ini **tidak punya route API terpisah** terdaftar lewat mekanisme standar (kalaupun ada `routes/api.php` secara fisik, tidak di-load framework kecuali didaftarkan manual di tempat lain).
- **Broadcasting** (baris 15-17): `routes/channels.php` didaftarkan via `withBroadcasting()` — dipakai untuk Pusher real-time notification bell (sesuai Bagian R ronde 1).
- **Middleware alias** (baris 18-23) — **HANYA 2 alias custom didaftarkan**: `'role' => RoleMiddleware::class` dan `'cabang' => CabangMiddleware::class`. **Tidak ada middleware global tambahan** didaftarkan di sini (tidak ada `$middleware->append()`/`$middleware->web()`/`$middleware->group()` apapun) — jadi stack middleware `web` yang dipakai murni default bawaan Laravel (EncryptCookies, StartSession, VerifyCsrfToken, dst) ditambah yang dipasang eksplisit per-route (`auth`, `verified`, `cabang`, `can:xxx`, `role:xxx`) di `routes/web.php` sesuai Bagian B ronde 1.
- **`cabang` middleware alias → `App\Http\Middleware\CabangMiddleware`** (dibaca penuh): method `handle()`-nya **TIDAK melakukan blocking/redirect apapun** — fungsinya murni **inisialisasi state per-request**: (1) kalau session belum punya `active_cabang_id`, isi otomatis dari `$user->defaultCabangId()`, atau `null` kalau user `canAccessAllBranches()` tanpa default cabang (mode "lihat semua"), atau cabang pertama milik user sebagai fallback terakhir; (2) `view()->share()` 3 variabel global ke SEMUA view: `activeCabang` (model `Cabang` aktif), `userCabangs` (`$user->cabangs()->aktif()->get()`), `authUser` (user login). Middleware ini **tidak menegakkan CabangScope apapun** — ia cuma memastikan session `active_cabang_id` selalu terisi supaya controller/scope manual (bukan `CabangScope` — lihat U.4) bisa membacanya secara konsisten.
- **`role` middleware alias → `App\Http\Middleware\RoleMiddleware`** (dibaca penuh, `app/Http/Middleware/RoleMiddleware.php`): terima parameter variadic (`role:owner,admin_pusat`), cek `$request->user()->role?->value` ada di daftar yang diizinkan; kalau tidak → `abort(403, ...)`. Kalau tidak ada user login → redirect ke `login`. Ini adalah pola role-check LAMA (hardcode role literal di route) yang menurut Rule #40 CLAUDE.md **sudah digantikan pola permission granular (`can:xxx`) di route-route yang lebih modern** — tapi middleware `role:` masih terdaftar & masih dipakai di beberapa route lama (mis. absen-device management per CLAUDE.md Update Log 2026-06-22), jadi 2 mekanisme otorisasi (`role:` middleware alias literal DAN Gate/`can:` permission dinamis) hidup berdampingan di codebase yang sama.
- **Exception handling** (baris 24-26): `withExceptions()` — **body kosong (`//`)**. Tidak ada custom exception rendering/reporting terdaftar di sini — konfirmasi silang dengan Bagian A ronde 1 yang mencatat "Tidak ada Custom Error Pages (403/404/500)" sebagai item Pending di CLAUDE.md Update Log: benar, tidak ada override apapun, Laravel akan pakai halaman error default bawaan framework untuk semua status code.

### U.3 package.json (Konfirmasi Tailwind+Alpine)

`package.json` (23 baris) — `devDependencies` murni: `@tailwindcss/forms`, `@tailwindcss/vite` (v4, **tidak dipakai** — lihat di bawah), `alpinejs`, `autoprefixer`, `axios`, `concurrently`, `laravel-vite-plugin`, `playwright`, `postcss`, `tailwindcss` (v3, ada `tailwind.config.js` v3-style terpisah — jadi ada 2 versi Tailwind niat pakai berbeda tercampur di dependency, `@tailwindcss/vite` v4 tidak pernah dipasang sebagai plugin di `vite.config.js`). Script `build`/`dev` cuma `vite build`/`vite`.

**Verifikasi empiris — Tailwind & Alpine BENAR-BENAR TIDAK dipakai di aplikasi utama:**
- `vite.config.js` (6 baris) — plugin HANYA `laravel-vite-plugin`, input `['resources/css/app.css', 'resources/js/app.js']`. **Tidak ada plugin Tailwind maupun Alpine terdaftar** di config Vite manapun.
- `resources/css/app.css` isinya persis 3 baris directive Tailwind bawaan (`@tailwind base/components/utilities`) — file default hasil scaffolding Breeze, tidak ada kustomisasi.
- `resources/js/app.js` isinya import `./bootstrap` + `import Alpine from 'alpinejs'; window.Alpine = Alpine; Alpine.start();` — juga persis default Breeze, tidak ada penambahan.
- **`@vite(...)` directive HANYA dipanggil di 1 file**: `resources/views/welcome.blade.php:15` — halaman landing default Laravel yang **bukan** entry point aplikasi (layout utama adalah `resources/views/layouts/app.blade.php`, dikonfirmasi 100% pakai CDN Bootstrap/Select2/SweetAlert2/Chart.js, **tidak ada `@vite` di dalamnya sama sekali**). Artinya build asset Tailwind/Alpine via Vite **tidak pernah ter-load** di halaman manapun yang benar-benar dipakai user aplikasi ERP.
- Grep `x-data=` di seluruh `resources/views/`: **6 file**, SEMUA dari scaffolding Breeze yang tidak disentuh — `components/dropdown.blade.php`, `components/modal.blade.php`, `layouts/navigation.blade.php`, `profile/partials/delete-user-form.blade.php`, `profile/partials/update-password-form.blade.php`, `profile/partials/update-profile-information-form.blade.php`. `x-show=` juga cuma 4 kemunculan (subset file yang sama).
- Grep Tailwind utility class khas (`bg-blue-[0-9]`): **0 hasil**. `text-gray-[0-9]`: **10 file**, semua juga file Breeze bawaan (`auth/register.blade.php`, `components/dropdown-link.blade.php`, `components/input-label.blade.php`, `components/nav-link.blade.php`, `components/responsive-nav-link.blade.php`, `components/secondary-button.blade.php`, `layouts/navigation.blade.php`, dan 3 file `profile/partials/*` yang sama).
- **Kesimpulan definitif**: `layouts/navigation.blade.php` dan komponen `dropdown`/`modal`/`nav-link`/dll ala Breeze **eksis di filesystem tapi tidak pernah di-`@include`/`<x-...>` dari `layouts/app.blade.php`** (layout app custom yang benar-benar dipakai) — mereka adalah sisa scaffolding `laravel/breeze` yang installer generate otomatis saat awal project dibuat, lalu project pivot ke layout custom Bootstrap sendiri tanpa membersihkan file lama. Halaman auth (login/register) KEMUNGKINAN masih memakai layout Breeze asli (perlu dicek terpisah kalau relevan) tapi itu di luar 5 file yang diminta ronde ini.
- **Sanity check `layouts/app.blade.php`**: grep `x-data|x-show|@click="|bg-blue-|text-gray-[0-9]` → **0 hasil**. Dikonfirmasi 100% Bootstrap CDN + vanilla JS/jQuery, tidak ada jejak Tailwind/Alpine sama sekali di layout utama — klaim ronde 1 di Bagian S **terverifikasi benar**.

### U.4 CabangScope + HasCabang Trait (+ HasAuditLog, FillsDeletedBy)

**`app/Models/Scopes/CabangScope.php`** (37 baris) — implementasi `Illuminate\Database\Eloquent\Scope`. Method `apply()`: (1) kalau tidak ada user login → return, tidak filter apapun; (2) kalau `$user->canAccessAllBranches()` (Owner/Admin Pusat) → kalau session `active_cabang_id` terisi, filter `WHERE cabang_id = X`, kalau tidak → tidak difilter sama sekali (mode "semua cabang"); (3) user biasa → filter `WHERE cabang_id = session('active_cabang_id') ?? $user->defaultCabangId()`. Logic-nya sendiri BENAR dan masuk akal sebagai scope multi-cabang — masalahnya murni di pendaftarannya (lihat di bawah).

**`app/Traits/HasCabang.php`** (55 baris) — isi lengkap per method:
- `bootHasCabang()` (baris 13-22): daftarkan listener `static::creating()` — **HANYA** auto-isi `$model->cabang_id` dari `session('active_cabang_id') ?? auth()->user()->defaultCabangId()` kalau kolom itu masih kosong saat record baru dibuat. Ini SATU-SATUNYA efek otomatis yang benar-benar aktif dari trait ini.
- `addCabangScope()` (baris 27-30): method **static terpisah** yang isinya `static::addGlobalScope(new CabangScope())` — method ini **TIDAK dipanggil dari `bootHasCabang()` atau boot manapun**, jadi murni method yang tersedia untuk dipanggil manual tapi tidak ada satupun caller-nya di codebase.
- `cabang()` (baris 35-38): relasi `belongsTo(Cabang::class)` biasa.
- `scopeForCabang($query, $cabangId)` (baris 43-46): local scope manual `->forCabang($id)` untuk filter eksplisit per-cabang di query builder.
- `scopeAllCabang($query)` (baris 51-54): local scope `->allCabang()` yang cuma manggil `$query->withoutGlobalScope(CabangScope::class)` — **method ini jadi no-op dalam praktiknya** karena tidak ada global scope `CabangScope` yang pernah didaftarkan untuk di-`withoutGlobalScope`-kan (lihat verifikasi grep di bawah).

**Verifikasi grep — CONFIRMED CabangScope TIDAK PERNAH aktif sebagai global scope:**
- `grep -rn "addCabangScope" app/` → **hanya 1 hasil**: definisi method itu sendiri di `HasCabang.php:27`. **Nol pemanggilan** di manapun (bukan di `boot()` model manapun, bukan di `AppServiceProvider`, bukan di service provider lain).
- `grep -rl "addGlobalScope" app/Models/*.php` → **0 file**. Tidak ada satupun model yang mendaftarkan `CabangScope` (atau scope apapun) lewat `addGlobalScope()` di method `boot()`-nya.
- `grep -rn "CabangScope::class" app/` → **62 kemunculan**, tersebar di 20 file (14 controller: `AbsensiController`, `AbsensiSayaController`, `CutiController`, `DashboardAbsensiController`, `EvaluationController`, `FaceAttendanceController`, `KaryawanController`, `KeuanganController`, `LaporanAbsensiController`, `LaporanKeuanganController`, `LaporanSaldoKasController`, `PenggajianController`, `RecurringTransaksiController`, `SetoranController` + 5 Service: `BebanBreakdownService`, `BusinessOverviewService`, `EvidenceBasedFindingsService`, `PenggajianService`, `SetoranHarianService` + `HasCabang.php` sendiri) — **SEMUA 62 kemunculan ini adalah pemanggilan `->withoutGlobalScope(CabangScope::class)`** (bentuk tunggal, dengan argumen class), bukan pendaftaran. Ini **PERSIS pola yang didokumentasikan Rule bisnis #34 CLAUDE.md**: "`CabangScope` sendiri tidak pernah didaftarkan sebagai global scope di model manapun... jadi di codebase ini, `withoutGlobalScope()` TIDAK PERNAH benar-benar dibutuhkan untuk bypass CabangScope; efek nyatanya SELALU cuma mematikan `SoftDeletingScope`" — jadi ke-62 pemanggilan ini adalah **defensive pattern yang secara teknis no-op terhadap CabangScope** (tidak ada apa-apa untuk di-"without"-kan), efek riilnya cuma mematikan `SoftDeletingScope` di query itu.
- **KESIMPULAN DEFINITIF**: `CabangScope` adalah kode yang **eksis dan benar secara logic, tapi tidak pernah "hidup"** sebagai global scope aktif di aplikasi ini. Filter cabang di seluruh sistem 100% dilakukan **manual** — lewat `HasCabang::scopeForCabang()`, filter `WHERE cabang_id` eksplisit di tiap controller/service, dan `CabangMiddleware` yang cuma menyediakan `session('active_cabang_id')` sebagai sumber kebenaran. Tidak ada satupun dari 14 model yang `use HasCabang` (`Absensi`, `BepReport`, `BepSetting`, `Cuti`, `Evaluation`, `EvaluationPeriod`, `Karyawan`, `Kas`, `Order`, `Penggajian`, `PurchaseOrder`, `RecurringTransaksi`, `StockRequest`, `TransaksiKeuangan`) yang otomatis ter-filter oleh cabang aktif tanpa kode eksplisit di controller — trait ini pada praktiknya **hanya menyumbang efek `bootHasCabang()`'s creating-hook (auto-isi `cabang_id`) + relasi `cabang()` + 2 local scope opsional**, bukan proteksi data otomatis.

**`app/Traits/HasAuditLog.php`** (22 baris) — wrapper tipis di atas `Spatie\Activitylog\Traits\LogsActivity`. `getActivitylogOptions()`: `logOnly($this->auditedAttributes ?? ['*'])` (log semua atribut kecuali model punya property `$auditedAttributes` custom untuk membatasi), `logExcept(['updated_at','created_at','remember_token','password'])` (field ini tidak pernah di-log walau berubah), `logOnlyDirty()` (cuma log field yang benar-benar berubah nilainya, bukan semua field tiap update), `dontSubmitEmptyLogs()` (skip log kalau tidak ada perubahan setelah exclude di atas), `useLogName(class_basename($this))` (nama log = nama class model, mis. "Order", "Karyawan"). **38 model** memakai `HasAuditLog` (grep `HasAuditLog` di `app/Models/*.php`).

**`app/Traits/FillsDeletedBy.php`** (37 baris) — `bootFillsDeletedBy()` daftarkan listener `static::deleting()`: (1) skip total kalau `isForceDeleting()` true (baris permanen hilang, tidak ada gunanya diisi); (2) skip kalau tabel model itu **tidak punya kolom `deleted_by`** (`Schema::hasColumn()` check — defensif, aman dipasang ke model manapun walau tabelnya belum punya kolom itu); (3) kalau lolos kedua guard, jalankan **raw `DB::table($table)->update(['deleted_by' => auth()->id()])`** dulu (bukan `$model->save()`, sesuai penjelasan di komentar file itu sendiri — supaya tidak bentrok dengan raw-update `deleted_at`/`updated_at` yang dilakukan `SoftDeletes::runSoftDelete()` sendiri), lalu sinkronkan `$model->deleted_by` juga di memory. **29 model** memakai `FillsDeletedBy` (list lengkap: `AbsenDevice`, `Absensi`, `Asset`, `Cabang`, `ChartOfAccount`, `Cuti`, `HariLibur`, `Item`, `ItemCategory`, `Karyawan`, `Kas`, `KategoriTransaksi`, `LoyaltyKlaim`, `LoyaltyPencapaian`, `LoyaltyProgram`, `Order`, `Pelanggan`, `PemakaianPerlengkapan`, `Penggajian`, `PurchaseOrder`, `RecurringTransaksi`, `Shift`, `Stock`, `StockBatch`, `StockRequest`, `StockTransfer`, `Supplier`, `TransaksiKeuangan`, `User`) — cocok dengan daftar `TrashController::$models` yang didokumentasikan CLAUDE.md Rule #41/#42.

### U.5 Kesimpulan Audit Ronde 2

1. **PWA icon 100% file statis + config PHP, nol UI dinamis.** `config/laravelpwa.php` hardcode nama "ERP Berkah Mulyo"/"BM ERP" (baris 4, 6-7) dan warna `#472c1d`/`#d0371b` (harus disinkron manual dengan CSS sidebar `app.blade.php`) — TIDAK ada mekanisme generate-icon-dari-1-gambar dari package `silviolleite/laravelpwa` ini. Rebranding PWA untuk dimsum WAJIB: (a) edit `name`/`short_name`/`background_color`/`theme_color` di file config ini, (b) siapkan & replace manual 8 file PNG di `public/images/icons/` (72 s/d 512px) via tool eksternal, tidak ada shortcut command Artisan bawaan project ini.
2. **`bootstrap/app.php` konfirmasi minimal & bersih**: cuma 2 middleware alias (`role`, `cabang`), tidak ada middleware global tambahan, tidak ada route `api.php` terdaftar, exception handling kosong (konfirmasi tidak ada custom error page 403/404/500, sesuai catatan Pending CLAUDE.md). `cabang` middleware TIDAK melakukan enforcement apapun — cuma inisialisasi session + share 3 variabel view (`activeCabang`, `userCabangs`, `authUser`); `role` middleware adalah mekanisme role-check literal lama yang berdampingan dengan Gate/`can:` modern.
3. **Tailwind+Alpine CONFIRMED 100% dead di aplikasi nyata** — 0 baris di layout utama (`app.blade.php`), hanya hidup di sisa scaffolding Breeze (`layouts/navigation.blade.php`, beberapa `components/*`, `profile/partials/*`, `auth/register.blade.php`) yang tidak pernah di-include dari layout yang benar-benar dipakai, dan `@vite()` cuma dipanggil di `welcome.blade.php` (landing page default, bukan entry aplikasi). Aman dihapus dari `package.json`+`vite.config.js`+`tailwind.config.js` kalau mau bersih-bersih dependency, TAPI perlu dicek dulu apakah halaman `auth/*` (login/register bawaan Breeze) masih dipakai end-user atau sudah digantikan custom — kalau masih dipakai, hapus Tailwind akan merusak tampilannya (di luar 5 file yang diaudit ronde ini, flagged sebagai follow-up).
4. **CabangScope CONFIRMED tidak pernah aktif** — bukan cuma "menurut CLAUDE.md", tapi diverifikasi ulang langsung: `addCabangScope()` di `HasCabang.php:27` nol caller, `addGlobalScope` nol dipakai di model manapun, dan seluruh 62 pemanggilan `withoutGlobalScope(CabangScope::class)` tersebar di 14 controller + 5 service adalah **no-op defensif** terhadap scope yang tidak pernah ada — efek riilnya cuma mematikan `SoftDeletingScope` di query yang sama. Implikasi untuk migrasi ke dimsum: proteksi data multi-cabang di sistem ini **100% manual per-controller**, bukan otomatis via Eloquent scope — kalau menambah controller/laporan baru untuk dimsum, WAJIB replikasi pola filter manual (`session('active_cabang_id')`, `scopeForCabang()`) yang sudah ada, JANGAN berasumsi model `use HasCabang` otomatis aman difilter cabangnya.
5. **HasAuditLog (38 model) dan FillsDeletedBy (29 model) keduanya terverifikasi solid** — implementasinya cocok 100% dengan deskripsi CLAUDE.md, tidak ditemukan celah/inkonsistensi baru. Tidak ada temuan baru yang perlu ditambahkan ke daftar "Pertanyaan untuk User" dari keempat area lain — semuanya murni konfirmasi/detail teknis, bukan keputusan bisnis baru yang perlu ditanyakan.

---

## Pertanyaan untuk User

1. **Apakah bisnis dimsum/mentai punya konsep "custom order per berat"** (misal jual mentai per 100gr, atau dimsum bisa custom porsi)? Ini menentukan apakah field `berat_daging`+dropdown satuan kg/ons/gram di POS masih relevan dipertahankan, atau POS bisa disederhanakan total ke alur "per pcs/porsi" murni.
2. **Apakah "Jasa Giling" sebagai lini bisnis terpisah TIDAK ada sama sekali** di model bisnis dimsum, atau ada padanannya (misal "custom mix pesanan khusus")? Ini menentukan nasib field `tipe_order='jasa_giling'`, validasi `OrderRequest`, dan seluruh `BepOtomatisService`/`LoyaltyService auto_track` yang bergantung padanya.
3. **Apakah Loyalty `auto_track`** (progress berbasis kg) mau diperluas jadi berbasis "total belanja Rp" atau "jumlah transaksi", atau cukup pakai `event_based` (klaim manual) saja untuk dimsum?
4. **Apakah struktur resep (`resep_bumbu_items.qty_per_kg`)** akan direname jadi basis "per porsi", atau dibiarkan nama kolom lama tapi diinterpretasikan ulang secara fungsional saja (tanpa migration)?
5. **Apakah dibutuhkan fitur varian produk** (level pedas mentai, isi per porsi dimsum) yang saat ini tidak ada strukturnya di `items`?
6. **PWA icon/manifest sudah diaudit detail di Bagian U.1/U.5** — `config/laravelpwa.php` hardcode nama "ERP Berkah Mulyo"/"BM ERP" + warna `#472c1d`/`#d0371b`, dan TIDAK ada mekanisme generate-icon-otomatis (8 file PNG 72-512px harus disiapkan & di-replace manual satu-satu). Pertanyaan yang tersisa untuk user: **siapa yang akan menyiapkan 8 file PNG icon dimsum dalam ukuran yang benar** (perlu aset desain baru, bukan sekadar ganti teks config) — apakah ini bagian dari scope tahap Branding yang sama dengan logo utama, atau perlu dijadwalkan terpisah karena butuh aset visual baru?

---

## Peta Konkret untuk 7 Tahap Roadmap

> Catatan: CLAUDE.md tidak menyebutkan roadmap eksplisit "7 tahap" di bagian manapun yang teraudit — 7 tahap berikut adalah **inferensi** dari brief tugas ini (Branding → Master Data → POS → Stok/Resep → Keuangan/Setoran → Dashboard/Laporan → Testing/Go-live), disusun berdasarkan urutan ketergantungan logis dan cakupan gap yang ditemukan.

### Tahap 1 — Branding
**File**: `.env` (`APP_NAME`), `app/Http/Controllers/PengaturanUmumController.php` (2 default seed `'Berkah Mulyo'`), `app/Models/PengaturanUmum.php` (1 default seed), `public/images/logo.png`, `public/images/icons/icon-*.png`, `config/laravelpwa.php` (cek isi, belum teraudit detail), lalu upload logo baru via UI `/pengaturan/umum`.
**Kompleksitas**: **Low**.
**Ketergantungan**: Tidak ada — bisa dikerjakan pertama & independen dari tahap lain.

### Tahap 2 — Master Data (produk dimsum/mentai, kategori, harga)
**File**: `database/seeders/ItemSeeder.php`, `database/seeders/ItemCategorySeeder.php`, `database/seeders/JenisOlahanSeeder.php` (kalau dipakai), `database/seeders/ResepBumbuSeeder.php`, `app/Http/Controllers/ItemController.php`/`Master/JenisOlahanController.php` (view label saja, bukan logic), views `resources/views/item/*`, `resources/views/master/*`.
**Kompleksitas**: **Low-Medium** (murni data, tapi volume seed cukup besar dan perlu didesain ulang kategori dimsum/mentai dari nol).
**Ketergantungan**: Bergantung keputusan Pertanyaan #1/#4/#5 di atas sebelum menentukan struktur final resep & varian.

### Tahap 3 — POS Adaptation
**File**: `resources/views/penjualan/pos.blade.php` (JS besar: `tambahJasaGiling()`, `terapkanResep()`, `setSatuanMode()`, dropdown Jenis Olahan), `app/Http/Requests/OrderRequest.php` (validasi `required_if:tipe,jasa_giling`), `app/Http/Controllers/PenjualanController.php`, `app/Services/PenjualanService.php` (kalau logic `tipe_order` perlu diperluas).
**Kompleksitas**: **High** — file JS POS sangat besar & terikat erat ke printer Bluetooth, cash drawer, resep bumbu, dan validasi server. Perubahan struktural (menghapus/menyederhanakan alur jasa giling) berisiko regresi ke fitur lain yang reuse kode yang sama (mis. Antrian Produksi baca `berat_daging_kg`).
**Ketergantungan**: WAJIB menunggu jawaban Pertanyaan #1 & #2 sebelum mulai — arah kerja (extend vs simplify) sangat berbeda tergantung jawabannya. Juga bergantung Tahap 2 (master item harus siap dulu).

### Tahap 4 — Stok/Resep Adaptation
**File**: `app/Models/ResepBumbu.php`/`ResepBumbuItem.php`, `app/Http/Controllers/Master/MasterResepBumbuController.php`, `app/Http/Controllers/PenjualanController.php::resepBumbuItems()`, kemungkinan migration rename kolom `qty_per_kg`→`qty_per_unit` (opsional, tergantung Pertanyaan #4).
**Kompleksitas**: **Medium** — struktur tabel reusable, tapi proporsi hitung (basis kg→porsi) butuh penyesuaian logic di endpoint + JS pemanggil.
**Ketergantungan**: Bergantung Tahap 3 (POS) karena resep auto-fill langsung terhubung ke form order.

### Tahap 5 — Keuangan/Setoran
**File**: `database/seeders/KategoriTransaksiSeeder.php` (verifikasi isi, kemungkinan minim perubahan), setup data awal `Kas` per cabang (lewat UI, bukan kode), opsional extend `app/Services/BepOtomatisService.php` kalau BEP Otomatis mau dipakai (lihat Pertanyaan #2 & Bagian M).
**Kompleksitas**: **Low** (setup data) hingga **Medium** (kalau extend `BepOtomatisService`).
**Ketergantungan**: Independen dari POS untuk bagian Kas/kategori, tapi extend `BepOtomatisService` bergantung keputusan Tahap 3.

### Tahap 6 — Dashboard/Laporan Adaptation
**File**: `app/Http/Controllers/LaporanKonsumsiBahanController.php`, `LaporanLabaRugiController.php` (kategori "jasa" mapping), `LaporanBepOtomatisController.php`, `app/Services/LoyaltyService.php` (kalau extend auto_track), views laporan terkait, konten `PanduanKontenSeeder.php`/`TooltipKontenSeeder.php` (rewrite total, bisa paralel).
**Kompleksitas**: **Medium** untuk laporan berbasis order (tergantung Tahap 3 selesai), **Low** untuk laporan generik (Keuangan/HR/Aset yang tidak berubah).
**Ketergantungan**: Bergantung Tahap 3 selesai (struktur order final) sebelum laporan bisa diverifikasi akurat.

### Tahap 7 — Testing & Go-Live
**File**: tidak ada file spesifik — verifikasi end-to-end alur POS→Stok→Keuangan→Laporan dengan data dimsum riil, cek seluruh 66 rule bisnis CLAUDE.md yang mungkin punya asumsi implisit "jasa giling"/"kg" masih valid atau perlu penyesuaian dokumentasi, update CLAUDE.md sendiri untuk mencerminkan bisnis dimsum (dokumentasi warisan Berkah Mulyo sebaiknya diarsipkan bukan dihapus, demi jejak sejarah bug-fix yang masih relevan secara teknis).
**Kompleksitas**: **Medium-High** (volume regression testing besar mengingat sistem punya banyak modul saling terhubung).
**Ketergantungan**: Bergantung SEMUA tahap 1-6 selesai.
