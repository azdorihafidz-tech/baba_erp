# CLAUDE.md — ERP D'mentai

> **Untuk Claude Code**: File ini adalah **single source of truth** untuk seluruh project. WAJIB dibaca sebelum eksekusi apapun.
> Isinya: keputusan bisnis, temuan audit, aturan teknis, dan filosofi kerja.

**Versi**: 4.13  
**Update terakhir**: 2026-11-10 (Porting coffeeshop: Produksi, Kirim ke Cabang, Retur, POS HP, Label gambar — [[4.49]]; Warna Aplikasi di Pengaturan Umum — [[4.48]]; POS: harga terkunci + izin Ubah Harga, produk sama digabung, panel bayar tablet — [[4.47]]; Laporan Transaksi/Produk/Kategori di menu Penjualan untuk kasir — [[4.46]]; Order dihapus tidak masuk laporan — [[4.45]]; Porting erp-coffeeshop: POS pilih cabang, Identitas Usaha, APK Kasir, Label Produk — [[4.44]]; Rapikan Role & Hak Akses — [[4.43]]; Ambil Produk Jual dari cabang lain — [[4.42]]; POS tipe transaksi per cabang & Uang Pas — [[4.41]]; POS stok tersedia dari resep — [[4.40]]; Data barang terpisah per cabang — lihat [[4.39]]; Perbaikan Harga, Wajib Bukti, PO ke Gudang, Role Bebas — lihat [[4.38]])  
**Status**: 🎉 **PROJECT LIVE DI PRODUCTION** (https://erp.dmentaiindonesia.com — domain lama `erpdimsum.azwacore.com` sudah tidak dipakai; **GO-LIVE data real: lihat [[4.28]]**) — Tahap 1-7 SELESAI SEMUA + rangkaian bug fix & improvement lintas sesi (lihat section 4 utk detail lengkap tiap item): Bug Fix Ronde 2, Rename Jenis Menu/Master Bumbu Pusat, Fitur Import dari Bumbu Pusat, Rename Label & Hapus Gojek/Grab, Fix Foto Produk Production, UI Preview Harga Master/Subtotal Resep (Ronde 3-5), Auto-isi Satuan Master Bumbu Pusat, Fix ENUM Kategori Saldo Awal, Fix Label Kode Kategori Wajib, Default Basis Program Loyalty ke Rp, Widget Total Pembelian Pelanggan, Fix Export Excel Laporan Keuangan. **Sprint 3 Batch 1 SELESAI SEMUA** (5 menu Prioritas 1 — Penjualan, Setoran Kasir, Setoran Harian/Rekap, Stok 3 sub-view, Laba Rugi Produksi — lihat [[4.24]], [[4.25]]) — Sprint 2 (konversi satuan resep) DIDEFER [[12.12]], Sprint 3 Batch 2/3 (17 menu Laporan Prioritas 2/3) + TODO tracking stock movement [[12.14]] MENYUSUL [[12.13]].

---

## 1. GAMBARAN UMUM PROJECT

### 1.1 Identitas Brand
- **Nama Brand**: **D'mentai**
- **Tagline**: **Dimsum & Gyoza**
- **Jenis Bisnis**: Retail food (dimsum & gyoza)
- **Sistem**: ERP (Enterprise Resource Planning) — POS + inventory + HR + keuangan

### 1.2 Asal Project
- **Base**: hasil copy penuh dari `erp-manajemenberkahmulyo` (Laravel 12) — ERP untuk jasa giling daging
- **Tanggal fork**: September 2026
- **Alasan fork**: bisnis beda total, tapi 90% infrastruktur bisa reuse

### 1.3 Struktur Bisnis Saat Ini
- **1 HO / Gudang Pusat** (di rumah owner)
- **5 Outlet retail**
- **Total 6 lokasi** di sistem

### 1.4 Prinsip Fleksibilitas (FILOSOFI UTAMA!)
Meski saat ini ada 5 outlet, sistem **HARUS fleksibel** untuk:
- Jumlah outlet bisa nambah/berkurang tanpa ganti kode
- Lokasi outlet: 1 kota / lintas kota / lintas provinsi
- Harga per outlet: bisa sama semua atau beda-beda (config-driven)
- Jenis produk per outlet: bisa beda (config per outlet)
- Varian produk: N-dimensi, opsional per produk
- Stok varian: bisa terpisah atau ikut induk (config per produk)

**Aturan emas: kalau bisa jadi config, JANGAN hardcode.**

---

## 2. TECH STACK & LINGKUNGAN

### 2.1 Stack
| Item | Detail |
|------|--------|
| Framework | Laravel 12 (bootstrap/app.php style, no Kernel.php) |
| PHP | 8.2+ |
| Database | MySQL (via XAMPP) |
| Frontend | Bootstrap 5.3.3 (CDN) + Select2 + SweetAlert2 + Chart.js + Bootstrap Icons + jQuery |
| Permission | 100% custom (BUKAN spatie/laravel-permission) |
| PDF | barryvdh/laravel-dompdf ^3.1 |
| Excel | maatwebsite/excel ^3.1 |
| Activity Log | spatie/laravel-activitylog ^4.12 |
| Backup | spatie/laravel-backup ^9.3 |
| PWA | silviolleite/laravelpwa ^2.0 |
| Realtime | pusher/pusher-php-server ^7.2 |

### 2.2 Lingkungan Lokal
| Item | Detail |
|------|--------|
| Path folder | `D:\xampp\htdocs\erp-dimsum` |
| Nama database | `erp_dimsum` |
| Port dev | `php artisan serve --port=8001` |
| URL akses | `http://localhost:8001` |

### 2.3 Project Lain di Environment (JANGAN DISENTUH)
| Project | Folder | Database | Port | Status |
|---------|--------|----------|------|--------|
| erp-manajemenberkahmulyo (ASLI) | `erp-manajemenberkahmulyo` | `erp_berkahmulyo` | 8000 | 🔴 Production, HARAM disentuh |
| erp-dimsum (INI) | `erp-dimsum` | `erp_dimsum` | 8001 | 🟢 Aktif dikerjakan |
| erp-coffeeshop (lain) | `erp-coffeeshop` | `erp_coffeeshop` | 8002 | 🟡 Dikerjakan di chat lain |

---

## 3. ATURAN KERJA WAJIB (JANGAN DILANGGAR)

### 🔴 3.1 JANGAN pernah sentuh folder ini:
- `D:\xampp\htdocs\erp-manajemenberkahmulyo` — project produksi Berkah Mulyo
- `D:\xampp\htdocs\erp-coffeeshop` — project coffeeshop terpisah
- Database `erp_berkahmulyo` — data real yang lagi dipakai

Semua modifikasi HANYA di folder `erp-dimsum` dan database `erp_dimsum`.

### 🔴 3.2 Test SETIAP perubahan
Setelah edit kode, WAJIB:
1. Cek error di `storage/logs/laravel.log`
2. Jalankan `php artisan config:clear` dan `php artisan view:clear`
3. Coba akses menu terkait di browser
4. Kalau ada error, FIX DULU sebelum lanjut fitur lain

### 🔴 3.3 Reuse dulu, bikin baru terakhir
Sebelum bikin controller/model/view baru, **cek dulu** apakah pola serupa sudah ada:
- Cek folder: `app/Http/Controllers`, `app/Models`, `resources/views`
- Kalau ada pola serupa, IKUTI pola itu (naming, struktur folder, style code)
- Jangan bikin pola baru kalau tidak perlu

### 🔴 3.4 Filter Cabang WAJIB Manual (SECURITY-CRITICAL!)
**Temuan audit ronde 2**: `CabangScope` DORMANT (tidak pernah aktif). Ke-62 pemanggilan `withoutGlobalScope(CabangScope::class)` di codebase adalah no-op — efek nyatanya cuma matiin `SoftDeletingScope`.

**Konsekuensi**: Filter cabang HARUS ditulis manual di setiap query.

**Contoh yang BENAR**:
```php
$orders = Order::where('cabang_id', auth()->user()->cabang_aktif_id)->get();
```

**Contoh yang SALAH** (akan bocor data cabang lain):
```php
$orders = Order::all(); // SECURITY BUG — lihat semua cabang!
```

**Aturan**: Setiap query yang menyentuh data cabang, cek dulu apakah sudah ada filter `cabang_id`. Ini security-critical.

### 🔴 3.5 Migration Harus Urut & Bisa Rollback
- Nama file migration harus datetime yang benar (bukan asal timestamp)
- Setiap `up()` HARUS ada `down()` yang balikin state
- Cek foreign key: tabel yang dirujuk harus dibuat DULU

### 🔴 3.6 Permission + Panduan + Tooltip + Tombol Cara Pakai Wajib Dibuat
Setiap fitur/menu BARU wajib:
1. Tambah entry di `PermissionSeeder` (untuk hak akses)
2. Tambah entry di `RolePermissionSeeder` (mapping role ke permission)
3. Tambah panduan di menu Panduan (via `PanduanKontenSeeder`)
4. Tambah tooltip untuk form-form penting (via `TooltipKontenSeeder`)
5. **Embed tombol "Cara Pakai" (`<x-panduan-button slug="{slug}" />`) di halaman fitur** — pojok kanan atas header halaman, atau header section untuk sub-fitur (mis. section Varian di form Produk Jual). Panduan yang cuma bisa diakses lewat menu Panduan (bukan langsung dari halaman fiturnya) dianggap **belum lengkap**.

Ini WAJIB, bukan optional. Kalau lupa, kerjaan bakal dobel di akhir.

**Validasi (2026-09-16)**: audit menyeluruh Tahap 1-6 menemukan Permission + Role Mapping SELALU konsisten diisi di setiap tahap sebelumnya (0 gap) — tapi Panduan + Tooltip 2 poin terakhir kelewat di 6 dari 9 fitur baru. Backfill konten sudah dilakukan (lihat 4.11), TAPI backfill pertama itu sendiri kelewat poin ke-5 (tombol Cara Pakai) — ketahuan dari test manual, bukan dari checklist. Backfill kedua (embed `<x-panduan-button>` ke 13 halaman) sudah menutup gap ini. **Pelajaran**: poin 1-2 (Permission/Role) rawan "otomatis kepikiran" karena langsung berhubungan dengan akses/keamanan (kalau lupa, fitur langsung error 403 — cepat ketahuan). Poin 3-5 (Panduan/Tooltip/Tombol) TIDAK menyebabkan error apapun kalau kelewat — silently missing, baru ketahuan kalau ada yang audit atau test manual. Ke depan: checklist section 13 WAJIB dicentang eksplisit per poin (termasuk poin 5), jangan cuma diingat — dan "panduan sudah ditulis" TIDAK SAMA dengan "panduan sudah accessible dari halaman fitur".

---

## 4. TEMUAN AUDIT PENTING (WAJIB DIINGAT)

### 4.1 🔴 BUG SILENT: Hardcoded `jasa_giling` + `berat_daging`

**Lokasi**:
- `app/Services/BepOtomatisService.php` → filter query pakai `tipe_order='jasa_giling'` + `berat_daging_kg`
- `app/Services/LoyaltyService.php` (method `auto_track`) → sama

**Efek untuk D'mentai**:
- Dashboard BEP tampil kosong/nol (karena tidak ada order tipe `jasa_giling`)
- Loyalty poin `auto_track` tidak pernah bertambah otomatis (silent fail, no error)

**Rencana Fix**:
- Extend service supaya bisa handle `tipe_order='penjualan'` juga
- Basis perhitungan diubah dari kg → jumlah pcs / total belanja Rp
- Dikerjakan saat menyentuh modul BEP & Loyalty (Tahap 5 & 6)

### 4.2 🟡 2 SISTEM LOGO TERPISAH — PERANGKAP BRANDING

**Lokasi**:
- **Mekanisme 1 (dinamis)**: `PengaturanUmum` model → upload via UI `/pengaturan/umum` → Storage
- **Mekanisme 2 (statis)**: file `public/images/logo.png` → dipakai favicon, PWA icon, PDF header

**Efek**: User upload logo baru di Pengaturan Umum → cuma muncul di TV Antrian, tidak di sidebar/PDF/favicon.

**Rencana Fix (Tahap 1 - Branding)**:
- Replace file statis `public/images/logo.png` dengan logo D'mentai
- Update `PengaturanUmum` default seed → "D'mentai"
- IDEAL: bikin fitur "logo master" di Pengaturan Umum yang override 2 mekanisme (opsional, kompleksitas tinggi)

### 4.3 🟡 PWA ICON HARDCODE (harus siapkan manual)

**Fakta**: `config/laravelpwa.php` 100% hardcode. 8 ukuran PNG (72-512px) harus disiapkan external, tidak ada UI admin atau command artisan untuk generate.

**Rencana**:
- Generate 8 ukuran PWA icon dari logo D'mentai full
- Tools: Imagemagick / ImageMagick PHP / online tool (pilih saat eksekusi)
- Dikerjakan di Tahap 1 - Branding

### 4.4 🟢 CabangScope DORMANT — JANGAN DIUBAH

**Fakta**: `app/Models/Scopes/CabangScope.php` tidak pernah aktif. Ke-62 pemanggilan `withoutGlobalScope(CabangScope::class)` adalah no-op.

**Aturan**: JANGAN diubah atau dihapus — terlalu banyak tempat, salah satu bisa bug. Biarkan sebagai "dokumentasi diri sendiri" bahwa dulu ada rencana global scope.

### 4.5 🟢 Tailwind + Alpine DEAD CODE

**Fakta**: `package.json` include Tailwind + Alpine, tapi 100% tidak dipakai di app real. Cuma tersisa di Breeze scaffolding (`welcome.blade.php`, register form) yang tidak dipakai.

**Rencana**: Skip dulu, cleanup di Tahap 7 (Testing/Go-live).

### 4.6 🟢 BOOTSTRAP APP MINIMALIS

**Fakta**: `bootstrap/app.php` cuma 2 middleware alias (`role`, `cabang`). Tidak ada middleware global tambahan. `routes/api.php` tidak terdaftar. Exception handling kosong (tidak ada custom 403/404/500).

**Aturan**: 
- Kalau nambah route API, harus register `api.php` dulu di `bootstrap/app.php`
- Kalau butuh middleware global, tambah di `bootstrap/app.php` (bukan `app/Http/Kernel.php` — file itu tidak ada di Laravel 12)

### 4.7 🟢 Recurring Transaction Trigger On-Request

**Fakta**: `AppServiceProvider::boot()` panggil `triggerRecurringIfNeeded()` dengan cache key harian. Jadi recurring dipicu on-request, bukan cuma cron.

**Aturan**: Kalau modif logic recurring, hati-hati — dia dipanggil setiap request.

### 4.8 🟢 `items.jenis` vs `items.tipe` — 2 Axis Orthogonal, JANGAN Digabung

**Fakta**: `items.jenis` (`bahan_baku`/`perlengkapan`, dari modul "Perlengkapan Habis Pakai" warisan Berkah Mulyo) dan `items.tipe` (5 nilai sejak Tahap 2.5, lihat 7.1) adalah **2 kolom independen** yang KEBETULAN sama-sama punya nilai literal `'bahan_baku'` — tapi maknanya beda total (satu soal "barang produksi vs ATK", satu lagi soal "dijual di POS atau tidak"). Jangan pernah asumsikan salah satu bisa dihitung dari yang lain.

### 4.9 🟢 `item_cabang` — Fallback "Tanpa Row = Aktif di Semua Cabang"

**Fakta**: Pivot `item_cabang` (Tahap 2.5) dipakai POS untuk filter ketersediaan produk_jual/produk_tambahan per outlet + `harga_override`. Item yang TIDAK PUNYA row sama sekali di tabel ini dianggap **aktif di semua cabang** (`Item::tersediaDiCabang()`) — sengaja begitu supaya 21 item dummy lama (belum pernah di-assign eksplisit lewat form Produk Jual baru) tidak hilang dari POS. Produk BARU yang dibuat lewat menu Produk Jual selalu dapat row eksplisit per cabang (checked/unchecked), jadi behaviour-nya deterministik ke depan — fallback ini murni utk data lama.

### 4.10 🟢 Permission Naming: `item.*` (Flat) vs `master.produk_jual.*`/`master.bahan_baku.*` (Namespaced)

**Fakta**: `item.*` (CRUD generik warisan Berkah Mulyo, tetap ada sebagai "Master Barang (Lengkap)" — unified fallback semua 5 tipe) dipakai bareng dengan 2 set permission baru `master.bahan_baku.*`/`master.produk_jual.*` (menu split Tahap 2.5) — pola namespace `master.*` ini konsisten dengan `master.item_varian.*`/`master.resep_bumbu.*` yang sudah ada duluan. Ketiga controller (`ItemController`, `MasterBahanBakuController`, `MasterProdukJualController`) sama-sama baca/tulis tabel `items` yang SAMA — bukan 3 sumber data terpisah, cuma beda filter+form+permission gate.

### 4.11 🟢 Backfill Panduan & Tooltip Fitur Tahap 1-6 (2026-09-16)

**Temuan**: Audit lengkap terhadap 9 fitur baru sejak fork (Tahap 1-6) menunjukkan Permission + Role Mapping SELALU lengkap (0 gap — kemungkinan karena lupa permission langsung menghasilkan error 403 yang cepat ketahuan saat testing), tapi **Panduan + Tooltip kelewat di 6 fitur**: Master Bahan Baku, Master Produk Jual, Setoran Kasir, Dashboard Owner (widget setoran), Laporan Setoran Kasir, dan Pengaturan Umum (branding) — plus 1 panduan **stale** (`item-varian` masih bilang "Coming Soon" padahal UI-nya sudah live sejak Tahap 3).

**Backfill yang dilakukan**: 6 panduan baru + 1 rewrite (semua via `Panduan::updateOrCreate()`, format `## Tujuan → ## Langkah-langkah → ## Catatan Penting → ## Troubleshooting`, 300-800 kata) + 20 tooltip baru (`master_produk_jual.*`, `master_bahan_baku.*`, `pos.*` tambahan, `setoran_kasir.*`, `laporan_setoran_kasir.*`, `dashboard_owner.*`) di `TooltipKontenSeeder.php`, DI-EMBED langsung ke `<x-tooltip key="...">` di 7 file view terkait (bukan cuma masuk tabel tanpa dipakai — cek ulang, tooltip yang tidak dipanggil di view TIDAK tampil ke user sama sekali).

**Bug pre-existing ditemukan & diperbaiki sekalian**: `resources/views/components/tooltip.blade.php` melakukan `e()` manual pada title+content lalu mencetak hasilnya lewat `{{ }}` (yang otomatis escape lagi) — DOUBLE-ESCAPE untuk tooltip mana pun yang kontennya mengandung karakter `&`/`<`/`>` (mis. tooltip baru `laporan_setoran_kasir.filter_status` yang berisi "Menunggu, Disetujui **&** Ditolak" tampil sebagai `&amp;amp;` bukan `&`). Fix: ganti jadi `{!! $tipContent !!}` (variabel sudah di-escape manual sebelumnya, tidak perlu escape kedua). Bug ini laten sejak tooltip component dibuat — mempengaruhi SEMUA tooltip lama yang kebetulan mengandung karakter HTML-special, bukan cuma yang baru ditambah sesi ini.

**Metode verifikasi**: 19 HTTP Feature test baru (`tests/Feature/Tahap7/PanduanTooltipBackfillTest.php`) — akses tiap slug panduan via route asli, cek isi (bukan cuma render OK), cek tooltip benar-benar muncul di response HTML form terkait (bukan cuma ada di tabel `tooltips`), regresi ke panduan/tooltip lama.

### 4.12 🟢 Tahap 7 — Final Polish & Go-Live (2026-09-14)

**A. Cleanup dead code**: Hapus `welcome.blade.php` + `layouts/navigation.blade.php` (dikonfirmasi 0 reference — root `/` adalah closure custom, bukan `view('welcome')`; navigation.blade.php tidak pernah di-`@include`). Hapus `tailwind.config.js` + `postcss.config.js`. `package.json` devDependencies dipangkas 11→5 (buang `tailwindcss`, `@tailwindcss/*`, `autoprefixer`, `postcss`, `alpinejs`). `resources/css/app.css` dan `resources/js/app.js` DIKOSONGKAN (bukan dihapus) — biar `vite.config.js` tetap valid, tidak ada view aktif yang `@vite()` isinya. **Register route & profil (`register.blade.php`, `/profile`) SENGAJA DIBIARKAN** — bukan dead code, itu keputusan produk (bisa dipakai suatu saat), bukan scope cleanup.

**B1. Fix `BepOtomatisService` hardcode `jasa_giling`+kg** (lihat [[4.1]]): pola fix = **fallback by data presence** — cek dulu apakah ada order `tipe_order='jasa_giling'` di periode query; kalau ADA pakai basis kg (logic lama, backward compat data historis), kalau TIDAK ADA (kasus normal D'mentai) pakai basis pcs dari `order_items.qty` dengan `tipe_order='penjualan'`. **Nama key return array (`volume_kg`, dst) SENGAJA DIPERTAHANKAN** meski semantiknya berubah (kg→pcs) — trade-off disetujui owner untuk hindari rename yang beresiko regresi di 6+ file konsumen; unit sebenarnya didokumentasikan via `@return array{...}` PHPDoc di `hitungBepOtomatis()`. `BusinessOverviewService::hitungProduksiSummary()` (dipakai laporan eksekutif PDF) punya bug identik, ikut difix dengan pola sama sebagai efek samping temuan audit.

**B2. Fix `LoyaltyService::auto_track` hardcode kg** (lihat [[4.1]]): migration widen enum `loyalty_programs.sumber_data` dari 1 nilai (`orders.berat_daging_kg`) jadi 3 (+`orders.total_bayar`, `orders.count`). Service pakai helper `resolveAgregat()` untuk branch SUM(total_bayar) / COUNT(*) / SUM(berat_daging_kg) sesuai `sumber_data` program. Basis kg lama tetap jalan apa adanya untuk program existing (backward compat, tidak perlu migrasi data). Form create/edit loyalty program ditambah dropdown "Basis Perhitungan"; `satuan_qty` di-derive otomatis dari `sumber_data` yang dipilih (Rp / transaksi / kg).

**B3. Sinkron 2 mekanisme logo** (lihat [[4.2]]): dibuat `PengaturanUmumObserver::saved()`, registrasi di `AppServiceProvider::boot()`. Saat `PengaturanUmum.logo_path` berubah → otomatis copy isi file ke `public/images/logo.png` (mekanisme statis). **Scope SENGAJA dibatasi cuma logo utama** — TIDAK menyentuh `logo-icon.png` atau 8 ukuran PWA icon (itu tetap manual sesuai [[4.3]], kompleksitas generate ulang PWA icon di luar scope Tahap 7).

**C. Error pages 403/404/500** di-restyle (bukan dibuat baru — file-nya sudah ada dari base Berkah Mulyo) pakai palet brand D'mentai (`#1A1A1A`/`#FF6B00`/`#FFF8E7`), logo D'mentai, tombol CTA gradient balik ke dashboard/login.

**Verifikasi**: 14 test baru `BepLoyaltyExtendTest.php` (B1+B2, termasuk 3 test backward-compat data lama) + 7 test `ErrorPagesAndLogoSyncTest.php` (error pages + sinkron logo real-filesystem + regresi cleanup) + 14 test `EndToEndFlowTest.php` (4 alur bisnis end-to-end: kasir jualan→setor, HO approve→dashboard update, permission 6 role, konsistensi tombol Cara Pakai). Total 118 test lintas Tahap 2.5/5/6/7 PASS, 0 regresi.

### 4.13 🔴 Bug Fix Ronde 2 — Temuan Test Manual Final Sebelum Go-Live (2026-09-15)

Setelah Tahap 7 "selesai" ([[4.12]]), test manual final Owner menemukan 4 bug baru (di luar 7 tahap resmi) — 3 di antaranya genuine bug produksi, bukan cuma polish:

- **Bug 1 (Dropdown Cabang Aktif cuma tampil sampai Outlet 2)**: root cause BUKAN CSS overflow seperti dugaan awal — `CabangMiddleware` memfilter `$userCabangs` (daftar cabang di dropdown switcher) ke `$user->cabangs()` (pivot `cabang_user` eksplisit) BAHKAN untuk role `canAccessAllBranches()` (owner/admin_pusat). Dikonfirmasi data dev: Owner/Admin Pusat cuma punya 3/6 pivot cabang. Fix: role dengan `canAccessAllBranches()` → tampilkan SEMUA cabang aktif, bukan cuma yang di-pivot. CSS `max-height`+`overflow-y:auto` tetap ditambahkan sebagai defensive fix kedua (kalau outlet bertambah banyak ke depan, sesuai filosofi fleksibilitas [[1.4]]).
- **Bug 2 (Bill Tersimpan POS)**: 3 perbaikan — (a) posisi dipindah ke PALING BAWAH halaman POS, di luar `<form id="formPos">` (menu sekunder, jangan ganggu alur transaksi baru); (b) tombol Batalkan BARU dengan permission `order.bill_tersimpan.batalkan` (default admin_pusat) — reuse `PenjualanService::batalkan()` yang sudah otomatis skip reversal stok/kas kalau order bukan status Selesai (bill Pending memang belum pernah potong stok apapun, jadi aman); (c) tombol "Bayar..." baru buka modal pembayaran lengkap (Tunai/Transfer/QRIS/Gojek/Grab + Split Payment + pilih Kas), reuse endpoint `charge-bill` yang sama dengan "Tunai Pas" existing.
- **Bug 3 🔴 KRITIS (Data Ghost di Produk Jual)**: root cause **nested `<form>` HTML**, BUKAN delete-recreate/filter-salah seperti 3 dugaan awal. `master/produk-jual/_form.blade.php` dan `master/bahan-baku/edit.blade.php` punya form "Zona Berbahaya" (hapus) yang ter-nested di dalam form utama (Simpan). Browser membuang tag `<form>` dalam yang bersarang tapi tetap memasukkan child input-nya (termasuk hidden `_method=DELETE`) ke form LUAR — karena posisinya di DOM SETELAH hidden `_method=PUT` bawaan form utama, klik "Simpan Produk" ter-method-spoof jadi DELETE (PHP: nilai `_method` TERAKHIR yang menang di `$_POST`), produk ke-soft-delete alih-alih ter-update. **Data TIDAK hilang permanen** (soft-delete, `CascadeDeleteService::deleteItemCascade()` pakai `$item->delete()` bukan `forceDelete()`) — bisa direstore via `/trash?model=items`. Kebingungan "Data Terhapus kelihatan kosong" murni UX (default tab "Orders", bukan bug kedua). Fix: pisahkan form hapus jadi SIBLING (bukan nested), tombol hapus pakai HTML5 `form="id"` attribute biar tetap tampil di card sidebar tanpa jadi child DOM form utama. Lihat [[4.14]] untuk guard rail permanennya.
- **Bug 4 (Audit Sync)**: audit menyeluruh pasca Bug 1-3 — smoke test 187 route GET, guard rail nested-form seluruh project, cek coverage Data Terhapus, cek 4 komponen wajib fitur baru. Menemukan 2 bug tambahan independen (Bug 5, Bug 6 di bawah) sebagai efek samping audit ini.

**Bonus temuan audit sync (disetujui Owner untuk sekalian difix, kriteria: crash/data-loss/silent-failure + fix <15menit + pola sudah ada di codebase)**:
- **Bug 5 (`notifikasi/index.blade.php`)**: nested form SAMA PERSIS pattern-nya dengan Bug 3 — form "Tandai Semua Dibaca" (POST) nested di dalam form filter (GET). Efeknya BUKAN data loss, tapi **silent failure**: tombol ter-asosiasi ke form GET yang salah, jadi klik "Tandai Semua Dibaca" cuma reload halaman filter, TIDAK benar-benar memanggil endpoint `notifikasi.read-all`. Fix: sibling form + `form="id"` attribute, sama seperti Bug 3.
- **Bug 6 (`RangkumanFinalService::hitungHealthScore()`)**: crash 500 di Laporan Eksekutif (`/laporan/eksekutif/preview` & `/export`) kalau cabang/periode TIDAK ADA penjualan sama sekali bulan berjalan (`margin_persen` null, `skalakan()` butuh `float` non-null). Ditemukan lewat smoke test 187 halaman, BUKAN bagian fitur Tahap manapun (laten dari Laporan Eksekutif versi lama). Severity dianggap kritis karena akan **natural terpicu di production** (outlet baru buka bulan ini = belum ada penjualan = kondisi ini persis). Fix: null-check eksplisit sebelum `skalakan()`, pola sama seperti `rasio_lancar`/rasio kewajiban-aset di fungsi yang sama.

**Metode verifikasi**: `Bug1CabangSwitcherScrollTest.php`, `Bug2BillTersimpanTest.php`, `Bug3NestedFormFixTest.php`, `Bug5NotifikasiNestedFormFixTest.php`, `Bug6RangkumanFinalNullMarginTest.php`, `DataTerhapusCoverageTest.php`, `NestedFormGuardRailTest.php`, `SmokeTestSemuaMenuTest.php` — semua di `tests/Feature/Tahap7/`.

### 4.14 🔴 Nested `<form>` HTML — POLA HARAM, DIGUARD RAIL

**Aturan mutlak**: `<form>` TIDAK BOLEH nested (bersarang) di dalam `<form>` lain, di file blade manapun, ke depan tanpa kecuali.

**Kenapa ini bukan cuma "kode jelek" tapi BUG NYATA**: HTML5 parser membuang tag `<form>` yang bersarang (invalid), TAPI child input-nya (hidden `_token`/`_method`, dst) tetap masuk ke DOM sebagai bagian dari form LUAR. Efeknya tergantung isi form dalam:
- Kalau form dalam punya `@method('DELETE')`/`PUT` yang beda dari form luar → **method-spoofing salah sasaran** (submit form luar memicu action form DALAM secara tidak sengaja) — ini yang terjadi di [[4.13]] Bug 3 (data ghost, KRITIS)
- Kalau form dalam action-nya beda tapi method sama → tombol form dalam **ter-asosiasi ke form luar yang salah**, silent tidak memanggil endpoint yang dimaksud — ini yang terjadi di Bug 5 (notifikasi tandai dibaca)

**Fix standar**: pisahkan jadi 2 form SIBLING (bukan nested). Tombol yang secara visual perlu ada "di dalam" card/section form lain pakai **HTML5 `form="id-form-lain"` attribute** pada `<button type="submit">`-nya — submit ke form yang benar tanpa perlu nested DOM. Lihat `master/produk-jual/_form.blade.php`, `master/bahan-baku/edit.blade.php`, `notifikasi/index.blade.php` untuk contoh pola fix-nya.

**Guard rail permanen**: `tests/Feature/Tahap7/NestedFormGuardRailTest.php` — scan SEMUA file `.blade.php` project tiap kali test suite dijalankan, gagal kalau ada nested form baru manapun. **WAJIB tetap PASS** — kalau gagal karena fitur baru, JANGAN suppress test-nya, perbaiki struktur form-nya (sibling + `form="id"` attribute).

### 4.15 🟢 Rename UI "Jenis Olahan" → "Jenis Menu", "Resep Bumbu Standar" → "Master Bumbu Pusat" (2026-09-15)

**Latar belakang**: audit read-only menemukan 2 menu warisan Berkah Mulyo ini masih hidup dan dipakai (bukan dead code sepenuhnya), tapi seluruh isi panduan+UI-nya mendeskripsikan alur **jasa-giling lama yang sudah tidak ada** ("dropdown 🧂 Pilih Resep Bumbu di POS", "Terapkan Resep", "Berat Gilingan (kg)") — endpoint AJAX pendukungnya (`GET /pos/resep-bumbu/{id}`) dikonfirmasi **0 caller** di `resources/views` manapun. Keputusan: **rename + perbaiki panduan** (bukan hapus) — route name/permission name/model/table SEMUA TETAP (`jenis-olahan.*`, `resep-bumbu`, `master.resep_bumbu.*`, tabel `jenis_olahans`/`resep_bumbu` tidak disentuh), zero migration.

- **"Jenis Olahan" → "Jenis Menu"**: label di sidebar, judul halaman, judul kolom Laporan Laba Rugi ("Per Jenis Olahan" → "Per Jenis Menu", key query `jenis_olahan` TETAP), tooltip, dan panduan (`slug: jenis-olahan`) — direposisi sebagai kategorisasi referensi untuk Master Bumbu Pusat, BUKAN lagi "dropdown POS transaksi Jasa Giling".
- **"Resep Bumbu Standar" → "Master Bumbu Pusat"**: label di sidebar, judul halaman, `display_name` permission, dan panduan (`slug: resep-bumbu`) ditulis ulang total — sekarang eksplisit menjelaskan bahwa **cara utama kelola resep adalah lewat form Produk Jual** (section Komposisi/Resep, lihat Tahap 2.5), halaman ini murni overview/referensi. Kolom BARU **"Produk Terhubung"** ditambahkan ke tabel index (`ResepBumbuController::index()` eager-load relasi `item`) supaya user langsung lihat resep mana yang genuinely dipakai POS vs orphan.
- **⚠️ Peringatan ditambahkan eksplisit di panduan**: tombol "Tambah Resep" di halaman Master Bumbu Pusat membuat resep TANPA `item_id` (orphan, tidak pernah dipakai POS karena POS cuma menemukan resep lewat produk yang di-klik kasir) — user diarahkan pakai form Produk Jual untuk resep baru. Tombolnya SENGAJA TIDAK dihapus/diubah perilakunya di sesi ini (di luar scope "rename UI + panduan"; kalau mau ditutup beneran, lihat rekomendasi audit terpisah).
- **Info box BARU** ditambahkan ke modal "Tambah Kategori Baru" (satu-satunya UI kelola `item_categories`, ada di `item/create.blade.php` & `item/edit.blade.php` — TIDAK ada halaman "Master Kategori Item" terpisah) menjelaskan kategori baru tidak langsung muncul sebagai chip filter POS (baru muncul kalau sudah punya ≥1 produk aktif).

**TODO awalnya dicatat di sini (2026-09-15) sudah DIKERJAKAN 2026-09-17** — lihat [[4.16]] fitur "Import dari Bumbu Pusat".

**Verifikasi**: `tests/Feature/Tahap7/RenameJenisMenuMasterBumbuPusatTest.php` (10 test) — label baru tampil di halaman+panduan, teks lama sudah hilang, route/permission name regresi tidak berubah, tombol Cara Pakai tetap ada, kolom Produk Terhubung menampilkan nama item.

### 4.16 🟢 Fitur "Import dari Bumbu Pusat" — Link (Bukan Copy) Resep Antar Produk (2026-09-17)

**Konsep**: Master Bumbu Pusat (`ResepBumbu` tanpa `item_id`, sebelumnya orphan tidak berguna — lihat [[4.15]]) sekarang bisa di-**link** ke resep produk manapun lewat tombol **"Import dari Bumbu Pusat"** di form Produk Jual. 1 baris resep produk sekarang `item_id` langsung (bahan manual) ATAU `resep_bumbu_ref_id` (link ke Master Bumbu Pusat) — mutually exclusive, divalidasi di `ProdukJualRequest::withValidator()`.

**Skema**: migration `2026_09_17_600002` — `resep_bumbu_items.item_id` dibuat nullable, tambah kolom `resep_bumbu_ref_id` (FK nullable ke `resep_bumbu.id`, `nullOnDelete`).

**Anti cyclic-reference BY CONSTRUCTION (bukan cuma validasi runtime)**: `MasterResepBumbuController::storeItem()` (form Master Bumbu Pusat sendiri) **TIDAK PERNAH** menerima field `resep_bumbu_ref_id` — item milik sebuah bumbu SELALU `item_id` langsung. Kombinasi dengan aturan "cuma bisa link ke `ResepBumbu` yang `item_id IS NULL`" (`MasterProdukJualController::syncResep()`) membuat kedalaman referensi **maksimal 1 level** (Produk → Bumbu → Bahan Mentah) secara struktural — cycle tidak mungkin terjadi apapun inputnya, tidak perlu deteksi cycle runtime/rekursi.

**🟢 Pattern "Live Calculation, No Cache/Job" untuk auto-update lintas-entitas**: HPP dan potong-stok baris linked dihitung **live** setiap kali dibutuhkan (`ResepBumbuItem::expandKeBahanMentah()` — expand 1 baris jadi kebutuhan bahan mentah on-the-fly, dipanggil dari `PenjualanService::cekResepCukup()`/`potongStokUntukItem()` saat checkout DAN `MasterProdukJualController::kalkulatorResep()` saat preview). **TIDAK ADA snapshot/cache/job apapun** — begitu Master Bumbu Pusat diedit, SEMUA produk yang link ke situ otomatis dapat angka baru di request berikutnya, tanpa event listener/queue/invalidasi cache. Prinsip ini konsisten dengan pola lama "Jangan input harga di Master Resep" (harga selalu live dari `Item::harga_beli_terakhir`) — cukup diperluas 1 level referensi. **Kapan pola ini BUKAN pilihan tepat**: kalau perhitungan yang di-live-kan mahal (query berat/N+1 dalam jumlah besar) atau butuh nilai historis-beku (mis. harga di struk transaksi lama TIDAK BOLEH ikut berubah kalau master harga diedit — itu sebabnya `order_items.hpp`/`harga_satuan` tetap snapshot permanen, beda dari resep yang genuinely representasi "kondisi sekarang").

**Permission**: reuse `master.produk_jual.edit` (tidak ada permission baru) — keputusan Owner: 1 tombol = 1 permission, siapa yang boleh edit produk otomatis boleh import bumbu.

**File utama**: migration `2026_09_17_600002_add_resep_bumbu_ref_to_resep_bumbu_items_table.php`, `ResepBumbuItem::expandKeBahanMentah()`/`isLinked()`, `MasterProdukJualController::{syncResep,kalkulatorResep,listBumbuPusat}()`, `ProdukJualRequest::withValidator()`, `PenjualanService::{cekResepCukup,potongStokUntukItem}()` (expand-aware), `master/produk-jual/_form.blade.php` (modal picker + badge baris linked).

**Verifikasi**: `tests/Feature/Tahap7/ImportBumbuPusatTest.php` (21 test) — struktur data, modal picker (render/search/permission), import=link bukan copy, HPP akurat (manual+linked mix), auto-update HPP tanpa sentuh produk, hapus link vs master tetap ada, edge case (bumbu nonaktif tetap jalan utk link lama, cyclic reference structural block 2 arah), regresi fitur existing (create/edit/varian/foto/kalkulator/Master Bumbu Pusat CRUD/POS checkout stok terpotong benar).

### 4.17 🟢 Improvement Test Manual Production: Rename Label "Nama" + Hapus Gojek/Grab (2026-09-18)

**Latar belakang**: 2 temuan test manual Owner di production (tablet POS) — (a) label "Nama (jika tidak terdaftar)" kepanjangan, wrap 2 baris di layar sempit; (b) 5 tipe pembayaran (Tunai/Transfer/QRIS/Gojek/Grab) dianggap terlalu ramai untuk bisnis retail walk-in D'mentai (bukan food delivery). Sebelum eksekusi, audit read-only menyeluruh dijalankan dulu (grep semua file yang sentuh "gojek"/"grab" + cek data historis production via Owner) — hasilnya **0 baris data** pakai Gojek/Grab baik di dev maupun production, sehingga aman dieksekusi tanpa risiko data loss.

- **Rename label**: `resources/views/penjualan/pos.blade.php` — "Nama (jika tidak terdaftar)" → "Nama" (placeholder "Nama pelanggan / walk-in" DIPERTAHANKAN, masih relevan sebagai hint).
- **Hapus Gojek/Grab dari `TipePembayaran` enum**: `Gojek`/`Grab` cases dihapus, `kasKategori()` disederhanakan (dulu ada branch khusus Gojek/Grab→'transfer', sekarang tinggal `return $this->value` krn cuma 3 case tersisa yang semuanya kasKategori = value-nya sendiri).
- **Migration** `2026_09_18_700001_hapus_gojek_grab_dari_enum_tipe_pembayaran.php` — pola **"data-migrate unconditional lalu alter enum"** dalam 1 migration: `UPDATE ... WHERE tipe_pembayaran/metode IN ('gojek','grab') SET = 'transfer'` (no-op aman kalau 0 baris) diikuti `ALTER TABLE ... MODIFY ENUM('tunai','transfer','qris')` pada `orders.tipe_pembayaran` dan `order_payments.metode`. Diverifikasi reversibel (`migrate:rollback` → enum balik ke 5 opsi → `migrate` lagi → balik ke 3 opsi, tanpa data loss krn dev/production sama-sama 0 baris affected).
- **Blast radius kode**: validasi (`PenjualanController`, `OrderRequest`), breakdown hardcode (`SetoranKasirService` — array loop 5→3 metode), UI POS (~10 titik: tombol utama, 2 dropdown split-payment, modal Bill Tersimpan, 3 baris JS), UI Setoran Kasir (2 baris tabel breakdown), Panduan (6 lokasi di slug `pos` + `setoran-kasir`). **TIDAK perlu diubah**: `PenjualanController::edit()`+`SetoranHarianService` (sudah pakai `TipePembayaran::cases()` dinamis, otomatis adaptif), model `Order`/`OrderPayment` (cast enum, ikut definisi), Dashboard/Laporan Setoran Kasir (iterate `setoran_details` dinamis), Tooltip (0 hasil grep — tidak ada yang menyebut Gojek/Grab), seeder `KategoriTransaksi` (0 hasil — tidak ada mapping kategori ke situ).
- Migration lama (`2026_09_13_200002`, `2026_09_13_200006`) yang ORIGINALLY menambahkan Gojek/Grab **SENGAJA TIDAK diedit** — migration yang sudah pernah jalan di production tidak boleh diubah isinya, riwayatnya tetap sebagai catatan historis; migration baru inilah yang jadi "penyeimbang"-nya.

**Verifikasi**: `tests/Feature/Tahap7/HapusGojekGrabTest.php` (12 test) — label baru, tombol Gojek/Grab hilang dari POS, enum cuma 3 value (app+DB), submit order 3 metode valid, submit dgn `gojek`/`grab` ditolak validasi (baik lewat `POST /penjualan` maupun `POST .../charge`), breakdown Setoran Kasir 3 metode, submit setoran total akurat, panduan sudah bersih. 1 test lama (`Tahap5\SetoranKasirHttpTest`) diupdate assertion-nya (`assertCount(5,...)` → `assertCount(3,...)`) krn memang sengaja berubah oleh perubahan ini, bukan regresi tak terduga.

### 4.18 🔴 Foto Produk Tidak Tampil di Production (Rumah Web Shared Hosting) — Root Cause Ganda + Fix Override `asset()`

**Konteks**: Production (`erp.dmentaiindonesia.com`, sebelumnya `erpdimsum.azwacore.com`; Rumah Web shared hosting cPanel) TIDAK PUNYA terminal/SSH, dan `symlink()` PHP DIBLOKIR provider — `php artisan storage:link` (jalan normal di XAMPP lokal, lihat [[10.5b]]) tidak bisa dipakai sama sekali di production. Foto produk (upload sukses, file genuinely ada di `storage/app/public/produk/`) tidak tampil di UI manapun (Master Produk Jual, POS). 4 percobaan fix awal (proxy `.htaccess`, ubah config `filesystems.php`, tambah route `/storage/{path}`, replace compiled views) SEMUANYA gagal — audit menemukan **2 root cause independen** yang saling menjelaskan kenapa keempatnya gagal:

**Root Cause A — `.htaccess` bawaan blokir `/storage/*` SEBELUM sampai Laravel**: `public/.htaccess` (warisan template Berkah Mulyo, ada sejak initial commit) punya `RewriteRule ^storage/ - [L,NC]` — didesain utk kondisi `public/storage` adalah symlink/junction BENERAN (fast-path: Apache serve langsung tanpa lewat Laravel). Di production, symlink itu TIDAK ADA (diblokir), jadi Apache coba serve file yang tidak ada → 404 Apache → kemungkinan besar Rumah Web punya `ErrorDocument 404` custom yang fallback ke `index.php` → Laravel jalan tapi HANYA menemukan "tidak ada route match", render 404 branded-nya sendiri. **Rule `[L]` ini terminate proses SEBELUM custom `.htaccess` rule ATAU route Laravel `/storage/{path}` manapun sempat dievaluasi** — itu sebabnya Coba 1 (proxy rule) dan Coba 3 (route baru) sama-sama gagal walau masing-masing secara terpisah sudah benar.

**Root Cause B — URL foto di-hardcode `asset('storage/'.$item->foto)`, TIDAK baca config apapun**: tidak ada accessor `getFotoUrlAttribute()` di `Item.php`. 7 titik hardcode tersebar (`master/produk-jual/index.blade.php`, `_form.blade.php`, `penjualan/pos.blade.php`, `face-registration/index.blade.php`, `setoran-kasir/show.blade.php`, `FaceAttendanceController.php`, `FaceRegistrationController.php`). `asset()` Laravel HANYA menempelkan `APP_URL` — TIDAK PERNAH membaca `config('filesystems.disks.public.url')` (config itu cuma dipakai `Storage::url()`, yang justru dipakai 2 view LAIN — `karyawan/edit.blade.php`, `karyawan/show.blade.php` — inkonsistensi pola lama, bukan bug baru). Ini sebabnya Coba 2 (ubah config `url` ke `/asset`) 0 efek, dan Coba 4 (replace compiled views) 0 file diubah (URL bukan string statis di compiled view, tapi hasil `asset()` yang resolve saat runtime).

**Temuan tambahan penting**: codebase SUDAH PUNYA pola serupa yang battle-tested — route `Route::get('/img/{path}', ...)` (nama route `img.serve`, di `routes/web.php`) sudah lama dipakai utk foto absensi/face-attendance/bukti-transaksi/logo (>10 titik pemakaian, lihat `url('/img/'.$path)`). Route ini TIDAK kena blokir Root Cause A krn prefix-nya bukan `/storage/`. Ini validasi kuat bahwa strategi "serve dari `storage/app/public/` lewat route Laravel dgn prefix BUKAN `/storage/`" memang sudah proven jalan di environment production yang sama.

**Fix (Approach B, dipilih Owner)**: override `asset()` secara GLOBAL, bukan edit 7 view satu-satu:
- `app/Support/StorageAwareUrlGenerator.php` — subclass `Illuminate\Routing\UrlGenerator`, override `asset()`: path yang literal diawali `storage/` di-rewrite jadi `asset/` sebelum diteruskan ke `parent::asset()`. Path lain (css/js/images, URL absolute, atau `storage` yang cuma kebetulan ada di tengah string) TIDAK disentuh.
- `AppServiceProvider::register()` — daftarkan subclass ini via `$this->app->extend('url', ...)`, **MEREPLIKASI PERSIS** setup resolver yang dilakukan `Illuminate\Routing\RoutingServiceProvider` bawaan (session resolver, key resolver utk signed URL, `rebinding('request', ...)`, `rebinding('routes', ...)`) — kalau tidak direplikasi, fitur signed URL (mis. verifikasi email) akan diam-diam rusak. Diverifikasi eksplisit via test: `URL::signedRoute()` tetap generate signature valid (`hasValidSignature()` true).
- `app/Http/Controllers/StorageAssetController.php` + route baru `Route::get('/asset/{path}', ...)->name('storage.asset')` — stream file dari `Storage::disk('public')` (bukan filesystem langsung), ada guard path-traversal (`str_contains($path,'..')` → 404).
- **TIDAK edit `.htaccess` production** (lebih berisiko, sulit diverifikasi tanpa akses server) — pendekatan ini sepenuhnya di level aplikasi Laravel, deploy via git push seperti biasa.

**Kenapa override di level `UrlGenerator` (bukan edit 7 view)**: 1 file berubah scope-nya (`AppServiceProvider`), otomatis berlaku ke SEMUA pemanggilan `asset('storage/...)` — termasuk view baru di masa depan yang belum ditulis — tanpa perlu diingat "jangan lupa pakai helper khusus". Reversibel: kalau pindah hosting yang symlink-nya jalan normal, cukup hapus registrasi di `register()`.

**Verifikasi**: `tests/Feature/Tahap7/StorageAssetOverrideTest.php` (11 test) — path rewrite akurat (`storage/` di awal saja, bukan di tengah string), path lain (css/js/images) tidak terpengaruh, URL absolute passthrough, `route()`/`URL::signedRoute()` tetap valid (bukti resolver ter-preserve), route `/asset/{path}` genuinely serve file + 404 utk file tidak ada + tolak path traversal, end-to-end Master Produk Jual & POS render `/asset/...` bukan `/storage/...`. Full regression 205 test lintas fase PASS (0 regresi) — termasuk `SmokeTestSemuaMenuTest` (187 route) yang membuktikan override ini tidak menyebabkan 500 di halaman manapun.

**Deploy production**: script sekali-pakai `public/clear-cache.php` (WAJIB dihapus dari server setelah dijalankan — tidak ada proteksi auth) untuk `config:clear`+`view:clear`+`cache:clear`+`route:clear`+rebuild `config:cache`+`route:cache` via akses browser, karena production tidak punya terminal/SSH.

### 4.19 🟡 UI Preview "Harga Master" & "Subtotal" di Section Resep Produk Jual (2026-09-19)

**Laporan Owner dari production**: bahan "Isian Ayam" 45rb/kg, takaran 35 gram di resep, Total HPP tampil Rp 1.575.000 — kelihatan seperti bug hitung.

**Audit menemukan root cause SEBENARNYA lebih nuanced dari sekadar "bug hitung"**: `MasterProdukJualController::kalkulatorResep()` (dipakai tombol "Simulasi Produksi" di form admin) mengalikan `$ri->qty_per_unit` MENTAH (35, tanpa konversi satuan) dengan `harga_beli_terakhir` (Rp/kg) → 35×45000=Rp1.575.000. **Konversi satuan yang benar SUDAH ADA** di `ResepBumbuItem::getQtyPerUnitDalamKgAttribute()` (gram/ml÷1000, ons÷10) dan **SUDAH DIPAKAI DENGAN BENAR** di `expandKeBahanMentah()` — yaitu jalur yang genuinely dipakai POS checkout ([[4.16]]). **Kesimpulan krusial: HPP transaksi RIIL di POS TIDAK KENA bug ini** — cuma preview "Simulasi Produksi" di form admin yang salah karena skip accessor konversi yang sudah ada.

**Keputusan Owner (Opsi C — Kombinasi, bukan fix penuh)**:
- **Dikerjakan sekarang**: HANYA UI improvement, TIDAK fix logic konversi satuan `kalkulatorResep()`. Ditambahkan kolom **"Harga Master"** (readonly, live preview dari `harga_beli_terakhir`, format "Rp X / satuan") dan **"Subtotal"** (readonly, `qty × harga`) per baris resep manual — **KEDUANYA MURNI PERKALIAN APA ADANYA, TANPA KONVERSI SATUAN, SENGAJA** (bukan bug, keputusan produk eksplisit: "surface bad data, don't auto-correct" — supaya anomali input seperti "Rp45.000/gram" langsung KETAHUAN user secara visual sebelum Simpan, bukan disembunyikan lewat koreksi otomatis yang justru menutupi kesalahan input data aslinya di Master Bahan Baku). Baris resep ter-link ke Master Bumbu Pusat (badge 🧂) tampilkan placeholder "— (lihat Simulasi Produksi)" di kedua kolom (tidak applicable per-item, karena expand-nya multi-bahan). Info alert `alert-warning` di bawah tabel, link ke Master Bahan Baku. Kolom mode-harga existing di-rename "Harga" → **"Mode Harga"** biar tidak rancu dengan "Harga Master" baru.
- **Dideferred ke Sprint 2**: fix logic konversi satuan `kalkulatorResep()` itu sendiri (Opsi A, unit-family system) — lihat [[12.12]].
- **Data anomali "Isian Ayam" di production TIDAK difix oleh kode** — tanggung jawab Owner via UI Master Bahan Baku (cek satuan & harga item itu).

**Bug kedua ditemukan sebagai efek samping audit (BELUM difix, di luar scope eksplisit sesi ini)**: `ResepBumbuItem::getTotalHargaMasterAttribute()` (dipakai kolom "Total /kg" di `master/resep-bumbu/edit.blade.php`) pakai `item->harga_jual` (kosong untuk `bahan_baku`, field itu punyanya `produk_jual`) — harusnya `harga_beli_terakhir`, sehingga kolom itu selalu tampil Rp0 untuk bahan baku manapun.

**Implementasi**: HANYA 1 file view diubah (`resources/views/master/produk-jual/_form.blade.php`) — kalkulasi JS client-side murni, TIDAK ada endpoint AJAX baru (`BAHAN_OPTIONS` array yang sudah ada di JS ditambah field `harga`). **Bug tak terduga saat implementasi**: ekspresi kompleks `@json($bahanOptions->map(fn($b) => [...(float) ($x ?? 0)...]))` langsung di dalam directive `@json(...)` membuat Blade compiler SALAH PARSE argumen (comma-splitter Blade ter-confuse oleh kombinasi cast+null-coalesce+nested-paren di dalam closure), hasil compile PHP terpotong di tengah array literal → `ViewException: Unclosed '['`. **Pelajaran**: JANGAN taruh ekspresi PHP kompleks (cast, null-coalesce, nested function call majemuk) langsung sebagai argumen `@json()`/directive Blade lain — compute dulu di `@php` block jadi variabel sederhana, baru `@json($variabel)`.

**Verifikasi**: `tests/Feature/Tahap7/ResepHargaMasterPreviewTest.php` (12 test) — rendering kolom+alert, data `BAHAN_OPTIONS` (field `harga` benar termasuk kasus 0), placeholder baris linked, regresi (Import Bumbu Pusat, nested-form check, save resep manual/varian/edit resep existing, POS render). Full regression 217 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🔴 Addendum — Bug Fix Ronde 3 (2026-09-19), ditemukan Owner setelah Opsi B deploy**:

**Bug A — Simulasi Produksi baca angka BEDA dari form input**: Owner ubah qty "Isian Ayam" di form dari 35g jadi 0.2g TAPI belum klik Simpan, lalu klik tombol Simulasi Produksi → hasilnya tetap pakai 35g (angka lama). Root cause: `hitungKalkulator()` (JS) melakukan GET-fetch ke `MasterProdukJualController::kalkulatorResep()`, yang query resep **langsung dari DB** (`$produkJual->load('resep.items...')`) — bukan dari state form di browser. Kolom "Subtotal" (Opsi B, client-side) baca `qtyInput.value` langsung dari DOM sehingga benar (0.2g), sedangkan Simulasi Produksi baca snapshot DB lama — **2 sumber data berbeda, bukan salah hitung**. Fix: `hitungKalkulator()` sekarang serialize semua baris `#bodyResep` (fungsi `serializeResepUntukKalkulator()`) dan kirim via **POST** (route `kalkulator-resep` diubah jadi `Route::match(['get','post'], ...)` — GET tetap didukung utk backward compat) ke `kalkulatorResep()`, yang sekarang terima `request->input('resep')` (dinormalisasi via `normalisasiResepDariForm()` — batch-load `Item`/`ResepBumbu` by id, hindari N+1) dan **fallback ke DB kalau payload kosong**. Server tetap yang hitung (perlu expand Bumbu Pusat server-side untuk baris linked, tidak bisa pure-JS) — cuma sumber datanya sekarang form real-time, bukan DB snapshot.

**Bug B — Kolom Subtotal tidak ada Total footer**: ditambah `<tfoot>` di tabel resep dengan baris "Total HPP (Preview Cepat)" — JS `hitungTotalHpp()` sum semua `.subtotalPreview` yang berupa angka, dipanggil tiap `hitungPreviewBaris()` jalan + saat baris ditambah/dihapus (`hapusBarisResep()` baru, ganti inline `this.closest('tr').remove()`). **Baris linked (Bumbu Pusat) SENGAJA di-skip dari sum** (subtotal-nya butuh expand server-side, sama seperti Bug A) — ditandai via class `linkedBumbuMarker` di placeholder cell, memicu warning terpisah (`#warningBarisLinked`, hidden by default, muncul dinamis kalau ada ≥1 baris linked) yang mengarahkan user ke Simulasi Produksi Lengkap untuk total termasuk Bumbu Pusat.

**Konsolidasi 2 area total (Opsi C, disetujui Owner)** — masing-masing punya use case beda, TIDAK dihapus salah satu, dikasih label+caption biar tidak bingung:
- **"Total HPP (Preview Cepat)"** (footer tabel, caption: "instant, client-side, tanpa konversi satuan otomatis") — quick check per-baris manual, TANPA Bumbu Pusat.
- **"Simulasi Produksi Lengkap"** (tombol existing, caption: "Server-side, hitung dari isi form saat ini (belum perlu Simpan dulu), expand Bumbu Pusat penuh") — hitungan lengkap termasuk expand Bumbu Pusat, sekarang FIXED baca form real-time (Bug A).

**File yang diedit**: `resources/views/master/produk-jual/_form.blade.php` (footer + JS), `app/Http/Controllers/MasterProdukJualController.php` (`kalkulatorResep()` + `normalisasiResepDariForm()`), `routes/web.php` (`kalkulator-resep` GET→GET+POST).

**Verifikasi**: `tests/Feature/Tahap7/SimulasiProduksiFormRealtimeTest.php` (8 test) — payload form override DB (0.2g bukan 35g → Rp9.000 bukan Rp1.575.000), fallback ke DB kalau payload kosong, expand Bumbu Pusat tetap benar dari payload form, skip baris invalid/kosong, tidak terganggu oleh flag `punya_varian`, label+caption baru tampil, markup footer+warning ada, regresi save produk masih normal. Full regression 225 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🟡 Addendum kedua — Simplifikasi Ronde 4 (2026-09-19), 2 fix simpel diminta Owner**:

1. **Format qty rancu ("1.000" terbaca "seribu")**: kolom DB `qty_per_unit` DECIMAL(10,3) + Eloquent cast `'decimal:3'` balikin STRING fixed-3-desimal ("1.000", "0.200") yang ditaruh mentah ke `value=""` input — buat Indonesia (titik = pemisah ribuan) angka "1.000" terbaca seperti "seribu" padahal cuma 1 pcs. Fix: fungsi JS baru `formatQtyInput()` (`parseFloat` lalu `String()`, buang trailing zero) dipakai di kedua tempat qty di-render ke `value=` (baris manual & baris linked Bumbu Pusat) — berlaku SAMA rata semua satuan (owner minta simpel, tidak usah beda-beda per family satuan).
2. **Konsolidasi 2 mode Total HPP jadi 1**: tombol "Simulasi Produksi Lengkap" + `jumlahProduksi`/`hasilKalkulator` **DIHAPUS total** dari view (bukan disembunyikan). Footer "Total HPP" sekarang jadi SATU-SATUNYA total, dan sekarang BENAR untuk baris linked juga — baris Bumbu Pusat (🧂) hitung subtotal-nya sendiri via AJAX otomatis (`hitungSubtotalLinkedBaris()`, debounce 400ms, POST ke `kalkulatorResep()` yang sama, `jumlah=1` krn qty di baris linked sudah representasi "per 1 unit produk") begitu baris ditambah / qty diubah — hasilnya masuk ke cell `.subtotalPreview` yang sama dengan baris manual, jadi otomatis ke-total oleh `hitungTotalHpp()` tanpa logic skip/warning terpisah lagi (elemen `#warningBarisLinked` & class `linkedBumbuMarker` dihapus, sudah tidak relevan). Backend `kalkulatorResep()`/`normalisasiResepDariForm()`/route tidak berubah (sudah cukup fleksibel dari fix Ronde 3).

**File yang diedit**: HANYA `resources/views/master/produk-jual/_form.blade.php` — 0 perubahan controller/route/migration.

**Verifikasi**: 2 test lama diupdate assertion-nya (placeholder statis → "Menghitung..."+AJAX; label+warning lama → footer tunggal + tombol lama sudah hilang) + 2 test baru (`formatQtyInput` dipakai di kedua tempat, qty desimal tersimpan tetap render normal). Full regression 227 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🔴 Addendum ketiga — Bug Fix Ronde 5 (2026-09-19), 2 bug ditemukan Owner setelah Ronde 4 deploy**:

1. **Subtotal Bumbu Pusat selalu "—"/Rp0**: root cause — `hitungSubtotalLinkedBaris()` (JS) pakai `KALKULATOR_URL`, yang route-nya `/{produkJual}/kalkulator-resep` **butuh `Item` yang sudah tersimpan**. Di halaman **Create** belum ada `$item` sama sekali, jadi const `KALKULATOR_URL` bahkan tidak didefinisikan (dibungkus `@if($isEdit)`) — fungsi langsung short-circuit ke "—" tanpa pernah coba AJAX. Padahal subtotal 1 Bumbu Pusat TIDAK butuh produk sama sekali (cuma butuh: bumbu apa + qty berapa porsi). Fix: endpoint BARU `POST /master/produk-jual/preview-bumbu/{bumbu}` (`previewSubtotalBumbu()`) di-bind langsung ke `ResepBumbu` (bukan `Item`) — jalan di Create MAUPUN Edit tanpa syarat produk tersimpan. Logic expand-nya di-extract jadi `private hitungSubtotalBumbuTunggal(ResepBumbu $bumbu, float $qtyPerUnit, int $jumlah): float`, dipakai ULANG di `kalkulatorResep()` (branch linked, existing) supaya tidak duplikat logic. JS const baru `PREVIEW_BUMBU_URL_BASE` (template URL dgn placeholder `__ID__`, SELALU ada tidak bersyarat `$isEdit`) — `KALKULATOR_URL` dibiarkan apa adanya utk proses lain di halaman Edit.
2. **Total HPP salah jumlah** (contoh Owner: 1.200+2.250+200+800 → tampil 1.003, seharusnya 4.450): root cause — `hitungTotalHpp()` parse teks `"Rp 1.200"` pakai regex `[^0-9.-]` yang MENYISAKAN titik "jaga-jaga kalau ada desimal", padahal `formatRupiahPreview()` SELALU `Math.round()` sebelum format (tidak pernah ada desimal asli) — titik yang muncul SELALU pemisah ribuan Indonesia. `parseFloat("1.200")` dibaca JS sebagai `1.2` (titik = decimal separator di JS), bukan 1200 — persis match bukti Owner (`1.2+2.25+200+800=1003.45`→round→`1.003`). Fix: buang SEMUA karakter non-digit sebelum parse (`replace(/[^0-9]/g, '')`), bukan cuma sebagian.

**Bug tak terduga saat implementasi (ditemukan sendiri sebelum sempat mengganggu Owner)**: comment JS baru sempat menulis literal teks `@if($isEdit)` di dalam `//` comment sebagai penjelasan — Blade compiler MEMPARSE `@if(...)` di MANA SAJA di file (termasuk di dalam text yang secara visual "cuma komentar JS"), bukan JS-aware, sehingga `@if`/`@endif` count jadi tidak seimbang → `ViewException: unexpected end of file, expecting endif`. Fix: reword comment supaya tidak mengandung pola literal `@directive(...)`. **Pelajaran baru**: hindari menulis pola `@kata(...)` apapun (bahkan sekadar contoh/referensi) di dalam comment blade manapun — Blade tidak tahu bedanya "kode nyata" vs "teks yang kebetulan mirip directive".

**File yang diedit**: `app/Http/Controllers/MasterProdukJualController.php` (extract method + endpoint baru), `routes/web.php` (route baru, static path di atas `{produkJual}` sesuai pola `bumbu-pusat/list`), `resources/views/master/produk-jual/_form.blade.php` (const baru + `hitungSubtotalLinkedBaris()` + fix regex).

**Verifikasi**: `tests/Feature/Tahap7/PreviewBumbuDanTotalHppParseTest.php` (8 test) — regex fix ada di markup, endpoint preview-bumbu hitung benar TANPA produk tersimpan (skenario Bug 1 asli), skip bahan mode gratis, tolak tanpa login (401)/tanpa permission (403), form Create pakai endpoint baru (bukan bergantung `KALKULATOR_URL`), regresi save produk dgn bumbu linked. Full regression 235 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🟢 Addendum keempat — Auto-isi Satuan di Master Bumbu Pusat (2026-09-19)**: form "Tambah Bahan" (`resources/views/master/resep-bumbu/edit.blade.php`) dulu dropdown "Satuan" SELALU default ke opsi pertama ("kg") apapun bahan yang dipilih — user harus ingat ganti manual sesuai satuan asli bahan (mis. Kulit Dimsum = pcs), rawan salah pilih tanpa disadari (beda dari section Resep Produk Jual yang sudah auto-isi satuan sejak fitur Import Bumbu Pusat, [[4.16]]). Fix: opsi `<select id="itemIdSelect">` ditambah `data-satuan="{{ $b->satuan }}"`, JS baru `autoisiSatuan()` (listener `change` di `itemSelect`, terdaftar SEBELUM listener `updatePreview()` yang sudah ada, supaya `satuanEl.value` sudah ter-update duluan saat preview dihitung) meng-set dropdown Satuan begitu bahan dipilih. Karena dropdown Satuan cuma 4 opsi baku (`kg`/`g`/`ons`/`pcs`) sedangkan `Item.satuan` bebas teks ("gram", "ml", "buah", dst), ditambah `normalisasiSatuan()` yang memetakan variasi teks umum ke 4 opsi itu — **kalau satuan bahan tidak dikenali** (mis. "liter"/"ml", belum ada opsi volume di dropdown ini) fungsi return `null` dan dropdown DIBIARKAN apa adanya, tidak dipaksa ke nilai yang salah. Murni 1 file view, 0 perubahan controller/route/migration.

**Verifikasi**: `tests/Feature/Tahap7/AutoSatuanResepBumbuTest.php` (4 test) — `data-satuan` ada di markup, JS `autoisiSatuan()`/`normalisasiSatuan()` ter-render, variasi teks "gram"/"kg" ter-normalisasi, satuan tak dikenal ("liter") tidak dipaksa, regresi simpan bahan masih normal. Full regression 239 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.20 🔴 Error 500 "Tambah Transaksi" Pemasukan Kas Kategori "Saldo Awal" — ENUM DB Tidak Sinkron dengan Enum PHP (2026-09-19)

**Laporan Owner**: submit form "Tambah Transaksi" (menu Keuangan) — pemasukan, kategori "Saldo Awal", jumlah Rp1.000.000 — 500 error: `SQLSTATE[01000]: Data truncated for column 'kategori'`.

**Root cause**: `transaksi_keuangans.kategori` masih raw MySQL **ENUM** warisan Berkah Mulyo (`migration 2024_01_01_000015_create_keuangan_tables.php`) dengan **9 nilai TETAP** (`penjualan, jasa_giling, pembelian_bahan, gaji, sewa_gedung, penyusutan, operasional, pembelian_aset, lainnya`). Di level PHP, `App\Enums\KategoriTransaksi` sudah punya case ke-10 `SaldoAwal = 'saldo_awal'`, dan `KategoriTransaksi` (model, tabel `kategori_transaksis`, seeder) sudah punya row `kode='SALDO'` (`is_system=true`, tipe `pemasukan`, dipilih otomatis saat bikin Kas baru **ATAU** bisa dipilih manual di dropdown "Tambah Transaksi") — tapi kolom DB **TIDAK PERNAH ikut di-`ALTER`** saat case PHP itu ditambahkan. Insert dengan `kategori='saldo_awal'` MySQL truncate ke closest-match/kosong dan (karena `sql_mode` strict) Laravel melempar `QueryException`, bukan silent-fail. Bug ini **laten sejak lama**, baru kepicu begitu ada user genuinely memilih kategori "Saldo Awal" secara manual di dropdown (jalur create-Kas otomatis kemungkinan jarang dipakai / kasnya sudah lama ada duluan sebelum bug ini ketemu).

**Fix**: migration `2026_09_19_800001_widen_kategori_enum_in_transaksi_keuangans_table` — `ALTER TABLE ... MODIFY kategori ENUM(...9 value lama..., 'saldo_awal')`, pola sama seperti widen enum sebelumnya ([[4.17]] Gojek/Grab, migration loyalty `sumber_data`). Reversibel (`down()` migrasi data `saldo_awal`→`lainnya` dulu baru shrink ENUM, diverifikasi manual `migrate`→`rollback`→`migrate`). **Hanya 1 value yang kurang** — dikonfirmasi dari `KategoriTransaksi::toEnumValue()` (`app/Models/KategoriTransaksi.php`) yang memetakan PERSIS 9 kode lama + `SALDO`→`saldo_awal`, `default` jatuh ke `'lainnya'` untuk kode manapun di luar itu — jadi tidak ada kategori lain yang berpotensi kena bug serupa.

**Verifikasi**: `tests/Feature/Tahap7/KategoriSaldoAwalEnumFixTest.php` (4 test) — kolom DB benar sudah terima `'saldo_awal'`, submit manual "Tambah Transaksi" kategori Saldo Awal berhasil (reproduksi persis skenario Owner), jalur create-Kas otomatis dengan saldo awal tetap normal (regresi), kategori lama (operasional) masih bisa disimpan. Full regression 243 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.21 🟢 Modal "Tambah Kategori Baru" — Field "Kode Kategori" Frontend Bilang Opsional, Backend Sudah Wajib (2026-09-19)

**Laporan Owner**: modal "Tambah Kategori Baru" (menu Master Barang → Tambah Item) — field "Kode Kategori" tampil placeholder "(opsional)" tanpa tanda wajib, padahal harus diisi supaya kategori itu tampil di pilihan.

**Root cause**: `ItemController::storeKategori()` SUDAH LAMA mewajibkan `kode_kategori` (`'required|string|max:20|unique:...'`) — backend tidak pernah longgar. Yang salah murni UI: `item/create.blade.php` & `item/edit.blade.php` (2 file, modal sama persis dikopi ke keduanya) menampilkan placeholder "(opsional)" dan TIDAK ada atribut `required`/tanda bintang merah — front-end dan back-end tidak sinkron. Efeknya: user submit tanpa isi Kode, form redirect balik dengan error validasi yang membingungkan karena UI-nya bilang boleh dikosongkan.

**Fix**: murni UI, samakan pola dengan field "Nama Kategori" di sebelahnya (yang sudah benar) — tambah `<span class="text-danger">*</span>` di label + atribut `required` di input + ganti placeholder jadi cth: "CAT-001" (tanpa "(opsional)"), di KEDUA file. 0 perubahan controller/route/migration.

**Verifikasi**: `tests/Feature/Tahap7/KodeKategoriWajibTest.php` (4 test) — label+placeholder baru tampil di form Create & Edit, backend tetap menolak kode kosong (regresi validasi existing), kategori dengan kode lengkap tetap bisa disimpan. Full regression 247 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.22 🟢 Default Basis Program Loyalty: Kg Giling → Total Belanja (Rp) (2026-09-21)

**Laporan Owner**: form "Tambah Program Loyalty" — dropdown "Tipe Program" (Auto-Track) berlabel "kumulatif kg giling, otomatis", padahal D'mentai tidak pernah pakai satuan kg.

**Root cause**: `LoyaltyService::auto_track` sudah di-extend Tahap 7 ([[4.12]] B2) supaya support 3 basis (`orders.berat_daging_kg`/`orders.total_bayar`/`orders.count`) dan form `sumber_data` sudah default-select "Total Belanja (Rp)" — TAPI 3 lapis lain masih basis kg warisan Berkah Mulyo, tidak ikut disinkronkan saat widen enum dulu:
1. **DB column default**: `loyalty_programs.sumber_data`/`satuan_qty` masih `DEFAULT 'orders.berat_daging_kg'`/`'kg'` (migration awal `2026_08_11_000001`, tidak diubah saat widen `2026_09_17_600001`).
2. **Backend fallback**: `LoyaltyProgramController::store()` — `$validated['sumber_data'] ?? 'orders.berat_daging_kg'` (fallback kalau field tidak dikirim).
3. **Label statis di view**: dropdown "Tipe Program" (create & edit) hardcode "kumulatif kg giling, otomatis" + helper text sebut `orders.berat_daging_kg` eksplisit, TIDAK PEDULI basis apa yang sebenarnya dipilih di dropdown "Basis Perhitungan" terpisah.
4. **`loyalty-program/show.blade.php`**: info alert + tooltip kolom "Order Tanpa Data" hardcode teks "berat gilingan"/"kg" utk SEMUA program apapun basisnya — salah/membingungkan utk program Rp/transaksi (yang sekarang jadi default).

**Fix (sinkronisasi 4 lapis, opsi kg TETAP ADA sbg pilihan legacy — tidak dihapus, cuma bukan default)**:
1. Migration `2026_09_21_900001_ubah_default_sumber_data_loyalty_programs_ke_rp` — `ALTER ... MODIFY` DEFAULT kolom jadi `orders.total_bayar`/`Rp`. Reversibel, diverifikasi manual `migrate`→`rollback`→`migrate`.
2. `LoyaltyProgramController::store()` — fallback diubah ke `'orders.total_bayar'`.
3. `create.blade.php`/`edit.blade.php` — label "Auto-Track (kumulatif otomatis dari transaksi pelanggan)" + helper text generik (tidak hardcode kolom kg), default nama program & target diganti ke skenario Rp ("Hadiah Loyalty Pelanggan Setia", target Rp 500.000).
4. `show.blade.php` — info alert & tooltip "Order Tanpa Data" sekarang dinamis (`@php $sumberLabel`/`$tanpaDataLabel` di-`match()` dari `$loyaltyProgram->sumber_data`) — program basis kg (legacy) tetap tampil teks kg yang benar, program Rp/transaksi tampil teks yang sesuai.
5. `database/seeders/ProgramLoyaltySeeder.php` — demo seed diupdate ke basis Rp (**dead code**, tidak terdaftar di `DatabaseSeeder.php`, tidak pernah dieksekusi otomatis — disinkronkan isinya utk jaga-jaga kalau dijalankan manual suatu saat).

**Temuan terkait TIDAK diubah (di luar scope, murni informasi)**: `pelanggan/show.blade.php` (halaman detail 1 pelanggan) punya widget stat card "Total Kg Giling" (`PelangganController` query `SUM(berat_daging_kg)`) — SELALU tampil 0 kg utk semua pelanggan D'mentai (tidak ada order jasa giling sama sekali), dead-weight display warisan Berkah Mulyo. Tidak disentuh karena di luar scope "form Tambah Program Loyalty" yang diminta — perlu keputusan Owner terpisah apakah mau dihapus/diganti widget lain.

**File yang diedit**: migration baru, `LoyaltyProgramController.php`, `loyalty-program/{create,edit,show}.blade.php`, `ProgramLoyaltySeeder.php`.

**Verifikasi**: `tests/Feature/Tahap7/LoyaltyDefaultBasisRpTest.php` (8 test) — DB default sekarang Rp, submit tanpa `sumber_data` fallback ke Rp bukan kg, label create/edit tidak sebut "kg giling" lagi, show basis Rp tidak tampilkan teks "berat gilingan", show basis kg (legacy, kalau ada program lama) tetap tampil teks kg yang benar, regresi submit eksplisit basis kg & Rp keduanya tetap normal. Full regression 255 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🟢 Lanjutan — Widget "Total Kg Giling" di Detail Pelanggan Diganti "Total Pembelian" (2026-09-21)**: temuan bonus dari audit [[4.22]] di atas — halaman detail pelanggan (menu Pelanggan → klik 1 pelanggan) punya 2 widget beda definisi: "Total Belanja" (`$pelanggan->orders()->sum('total_bayar')`, TANPA filter status/tanggal) dan "Total Kg Giling" (SELALU 0 utk semua pelanggan D'mentai — dead-weight display warisan Berkah Mulyo, tidak ada order jasa giling sama sekali). **Keputusan Owner: selaraskan jadi SATU widget** "Total Pembelian" (bukan tambah widget ke-3) — alasan: order POS D'mentai langsung lunas saat itu juga (tidak ada konsep "pending lama" yang butuh dibedakan dari "Total Belanja" kasar), 1 angka lebih jelas & maintainable.

- **Accessor baru** `Pelanggan::getTotalPembelianAttribute()` — `orders()->where('status','selesai')->whereDate('tanggal_order','<', hari_ini)->sum('total_bayar')`. Dipakai controller (`$pelanggan->total_pembelian`) menggantikan 2 query lama (`$totalBelanja` unfiltered + `$totalKgGiling` DB::table query) — `use Illuminate\Support\Facades\DB` di `PelangganController` ikut dihapus (sudah tidak dipakai lagi di controller itu).
- **View**: widget "Total Belanja"+"Total Kg Giling" (2 card) jadi 1 card "Total Pembelian" (icon `bi-cash-coin`, tetap warna hijau `border-success` existing). Widget "Rata-rata / Order" ikut pakai angka `$totalPembelian` yang baru (sengaja disederhanakan, bukan dipertahankan sbg 2 basis beda — konsisten keputusan "1 angka lebih jelas").
- **Bonus fix ditemukan saat audit "elemen lain masih pakai kg"**: section "Program Loyalty" di halaman yang SAMA (`pelanggan/show.blade.php:160`, terpisah dari `loyalty-program/show.blade.php`) ternyata PUNYA COPY TEKS hardcode "belum ada data berat gilingan (dihitung 0 kg)" yang SAMA PERSIS bug-nya dengan yang sudah difix di [[4.22]] tapi lolos karena beda file — sekarang ikut dibuat dinamis (`$progress['program']->sumber_data`/`satuan_qty`).

**File yang diedit**: `app/Models/Pelanggan.php` (accessor baru), `app/Http/Controllers/PelangganController.php` (pakai accessor, hapus 2 query lama + import `DB` tak terpakai), `resources/views/pelanggan/show.blade.php` (widget + teks loyalty dinamis). 0 migration (murni query+view).

**Verifikasi**: `tests/Feature/Tahap7/TotalPembelianPelangganTest.php` (9 test) — accessor: 0 tanpa transaksi, sum benar 3 transaksi, skip order pending-hari-ini (cuma hitung status selesai + tanggal<hari ini), skip order dibatalkan; rendering: widget baru tampil nilai benar, widget lama (Total Kg Giling/Total Belanja) sudah tidak ada, Rp 0 utk pelanggan baru, teks loyalty tidak hardcode "berat gilingan" utk basis Rp, regresi widget lain (Total Order/Order Terakhir/Rata-rata) masih normal. Full regression 264 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.23 🔴 Error 500 Export Excel "Laporan → Keuangan" — Enum Object-to-String + Excel Palsu (CSV Bertopeng .xls) (2026-09-21)

**Laporan Owner**: menu Laporan → Keuangan (`laporan.keuangan.laba-rugi`, BEDA dari "Kelola Kas & Transaksi → Laporan Keuangan" `keuangan.laporan` yang terpisah) — klik Export Excel **error**. Juga keluhan umum lintas banyak menu Laporan lain: "1 kolom excel banyak isi" saat dibuka.

**Root cause 1 (crash, spesifik Laporan Keuangan)**: `LaporanKeuanganController::labaRugi()`/`arusKas()` export manual pakai `fputcsv()` — data `$pemasukan`/`$pengeluaran` didapat dari `TransaksiKeuangan::selectRaw('kategori, SUM(jumlah) as total')->groupBy('kategori')->get()`. Eloquent **TETAP menerapkan cast model** (`'kategori' => App\Enums\KategoriTransaksi::class`) ke atribut `kategori` MESKIPUN datang dari `selectRaw()` (bukan select kolom biasa) — jadi `$p->kategori` adalah OBJEK enum PHP, bukan string. `fputcsv($file, [$p->kategori, ...])` mencoba string-cast objek itu → **fatal error** `Object of class App\Enums\KategoriTransaksi could not be converted to string`. Ini bug NYATA, direproduksi persis via test (bukan cuma "kurang rapi").

**Root cause 2 (keluhan "1 kolom banyak isi", pola sama di export-export lain)**: file yang di-generate SEBENARNYA teks CSV plain (`fputcsv` dgn delimiter `;`) yang dikasih ekstensi `.xls` + header `Content-Type: application/vnd.ms-excel` — BUKAN file Excel biner/xlsx sungguhan. Excel coba buka sbg file native, gagal, fallback parse sbg teks — kalau regional setting Windows si user pakai KOMA sbg list separator (bukan titik-koma), SEMUA kolom yang dipisah `;` gagal terdeteksi dan collapse jadi 1 kolom utuh. Pola "CSV-as-.xls" ini historically dipilih (lihat [[7.5]]) krn simpel tanpa dependency — tapi codebase SUDAH PUNYA solusi lebih baik yang proven jalan: `app/Exports/TransaksiKeuanganExport.php` (dipakai "Kelola Kas & Transaksi") pakai **maatwebsite/excel** (sudah ada di `composer.json`, dependency existing bukan baru) — xlsx biner sungguhan, universal dibuka Excel apapun localenya.

**Fix**: 2 Export class BARU (`app/Exports/LaporanLabaRugiExport.php`, `app/Exports/LaporanArusKasExport.php`), pola sama `TransaksiKeuanganExport` (`WithStyles` utk header bold+warna+auto-size kolom, kategori dipanggil `->label()`/`label_kategori` bukan objek mentah) — `LaporanKeuanganController` diubah pakai `Excel::download(...)`, 2 method private CSV manual (`exportLabaRugiExcel()`/`exportArusKasExcel()`) DIHAPUS total.

**Scope sesi ini SENGAJA dibatasi ke "Laporan → Keuangan" saja** (yang genuinely error) — Owner melaporkan daftar panjang 20+ menu Laporan lain dgn keluhan serupa ("Excel belum rapi"/"belum ada PDF"), TAPI itu backlog terpisah yang jauh lebih besar (each menu beda struktur data, sebagian belum py export sama sekali) — didaftarkan sbg TODO Sprint 3 ([[12.13]]), bukan dikerjakan sekaligus tanpa scoping/prioritas dari Owner.

**File yang diedit**: `app/Http/Controllers/LaporanKeuanganController.php` (pakai Excel facade, hapus 2 method CSV manual), 2 file baru di `app/Exports/`.

**Verifikasi**: `tests/Feature/Tahap7/LaporanKeuanganExcelFixTest.php` (7 test) — HTML render normal (laba-rugi & arus-kas), export Excel dgn kategori enum asli TIDAK CRASH (reproduksi persis bug Owner), export dgn kategori `saldo_awal` tidak regresi, Content-Type xlsx sungguhan (`spreadsheetml`), tolak tanpa permission (403), alias `harian()`/`pengeluaran()` masih normal. Full regression 271 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.24 🟢 Sprint 3 Batch 1a — Rapikan Excel + Tambah PDF: Laporan Penjualan & Setoran Kasir (2026-09-21)

**Konteks**: lanjutan [[12.13]] — 2 dari 5 menu Prioritas 1 ("dipakai harian"). Owner minta pattern KONSISTEN lintas semua menu Sprint 3 ke depan (bukan cuma 2 ini): Excel header bold+fill krim brand, auto-width, format Rupiah/tanggal Indonesia, footer "Dicetak oleh"; PDF logo D'mentai, judul besar, info filter, tabel border+zebra-stripe, footer nomor halaman.

**Foundation BARU (dipakai SEMUA menu Sprint 3 ke depan, bukan cuma 2 ini)**:
- `app/Exports/Concerns/HasLaporanStyles.php` (trait) — `styleHeaderRow()` (bold+fill `#FFF8E7`), `autoSizeAllColumns()`, `styleRupiahColumn()` (number-format mask `"Rp" #,##0` — angka TETAP numerik asli, cuma DITAMPILKAN dgn prefix, supaya kolom tetap bisa di-SUM/sort normal di Excel, BUKAN string manual), `footerDicetakOleh()`.
- `resources/views/laporan/pdf/layout.blade.php` — layout Blade `@extends`-able, header logo+judul+info-filter (pola sama `laporan/pdf/neraca.blade.php` yang sudah proven), CSS zebra-stripe + border tipis siap pakai (class `table.data`), footer nomor halaman via **CSS `position:fixed` + `counter(page)`/`counter(pages)`** (BUKAN teknik `<script type="text/php">` dompdf yang butuh `config/dompdf.php` `enable_php=true` — project SENGAJA `enable_php=false` demi keamanan, jadi dipilih pendekatan CSS murni yang tidak butuh ubah config sama sekali).

**Per menu**:
1. **Laporan Penjualan**: `LaporanPenjualanExport` (ganti `fputcsv()` manual) + `laporan/penjualan/pdf.blade.php` (portrait) + tombol Export PDF baru di view. Method private `exportExcel()` lama DIHAPUS.
2. **Laporan Setoran Kasir**: `LaporanSetoranKasirExport` + `laporan/setoran-kasir-pdf.blade.php` (portrait, row selisih≠0 di-highlight kuning/merah tipis sesuai tanda selisih) — PDF dipicu via query param BARU `?format=pdf` pada route `export` yang SAMA (bukan route terpisah), Excel tetap default kalau `format` tidak diisi.

**Bug regresi ditemukan & difix sekalian**: 1 test lama (`Tahap6\DashboardOwnerHttpTest::test_export_laporan_setoran_kasir_berhasil`) assert `Content-Type: application/vnd.ms-excel; charset=UTF-8` (header CSV lama) — di-update ke `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (xlsx asli), krn memang sengaja berubah oleh fix ini, bukan regresi tak terduga.

**File yang diedit**: `app/Exports/Concerns/HasLaporanStyles.php` (baru), `app/Exports/LaporanPenjualanExport.php` (baru), `app/Exports/LaporanSetoranKasirExport.php` (baru), `resources/views/laporan/pdf/layout.blade.php` (baru), `resources/views/laporan/penjualan/pdf.blade.php` (baru), `resources/views/laporan/setoran-kasir-pdf.blade.php` (baru), `LaporanPenjualanController.php`, `LaporanSetoranKasirController.php`, `laporan/penjualan/index.blade.php`, `laporan/setoran-kasir.blade.php`.

**Verifikasi**: `tests/Feature/Tahap7/LaporanBatch1aExportTest.php` (10 test) — Excel xlsx valid (`spreadsheetml`), PDF valid (`application/pdf`), filter tanggal+cabang+status sesuai, data kosong tetap generate tanpa error, tolak tanpa permission (403), regresi HTML kedua halaman masih normal. Full regression 281 test lintas Tahap 2.5/5/6/7 PASS (0 regresi, 1 assertion lama diupdate krn perubahan disengaja).

### 4.25 🟢 Sprint 3 Batch 1b+1c — Rapikan Excel + Tambah PDF: Setoran Harian, Stok (3 sub-view), Laba Rugi Produksi (2026-09-21)

**Konteks**: lanjutan [[4.24]] — sisa 3 dari 5 menu Prioritas 1. **2 blocker genuine ditemukan saat audit, DIKONFIRMASI ke Owner sebelum eksekusi** (bukan diasumsikan sendiri):

**Blocker 1 — Laporan Setoran Harian**: struktur data asli (`SetoranHarianService`) adalah Ringkasan+Breakdown+Detail utk 1 cabang/1 periode, BUKAN 1 baris per Tanggal+Cabang. Owner minta format export BARU yang lebih ringkas. **Keputusan Owner**: bangun agregasi BARU (1 baris per Tanggal+Cabang: Total Order, Total Pemasukan, Total Pengeluaran, Kas Bersih) **KHUSUS UNTUK EXPORT** — halaman `index()`/`print()` (dipakai closing kas harian) **SENGAJA TIDAK DIUBAH**, tetap multi-section seperti semula, karena kebutuhan export (rekap ringkas lintas hari/cabang) genuinely beda dari kebutuhan tampilan harian (detail 1 hari). Kolom **SENGAJA TIDAK dinamai "Total Setoran Kasir"** (rawan rancu dgn menu Laporan Setoran Kasir yang beda sumber data, [[4.24]]) — tombol export di-label ulang "Export Rekap (Excel/PDF)" biar jelas beda dari tampilan detail di halaman yang sama.

**Anti-double-count**: `Total Pemasukan` = `SUM(orders.total_bayar)` + `SUM(transaksi_keuangans WHERE tipe=pemasukan AND referensi_type != 'order')` — order OTOMATIS bikin `TransaksiKeuangan` ber-`referensi_type='order'` (`PenjualanService::buatOrder()`), jadi kalau di-SUM mentah dari `transaksi_keuangans` tanpa filter itu, omzet order ke-hitung DUA KALI. Diverifikasi eksplisit via test.

**Blocker 2 — Laporan Pergerakan Stok**: Owner minta kolom "Sisa Stok" (running balance) & "Referensi" (link ke order/adjustment/PO asal) — KEDUANYA belum ditrack sistem sama sekali (`stock_movements` cuma simpan qty movement itu sendiri + `catatan` freeform, tidak ada running-balance atau `referensi_type`/`referensi_id` terstruktur). **Keputusan Owner**: SKIP 2 kolom itu (bukan fitur export, tapi fitur tracking baru genuinely) — export pakai kolom yang SUDAH ada (Tanggal, Kode, Bahan, Cabang, Tipe, Qty, Satuan, Catatan), PDF dikasih info alert "Untuk stok saat ini, lihat Laporan Stok (Index)". Dicatat sbg TODO Sprint terpisah — lihat [[12.14]].

**Per menu**:
1. **Setoran Harian**: `LaporanRekapHarianExport` (FromCollection) + `laporan/setoran-harian/pdf.blade.php` (portrait, row Kas Bersih negatif = highlight merah). Method private `exportCsv()` lama DIHAPUS, diganti `buildRekapHarian()` (agregasi baru, private).
2. **Stok Index**: `LaporanStokExport` + `laporan/stok/pdf-index.blade.php` (portrait, row Habis=merah/Minim=kuning). Kolom Kode ditambah (`item->kode_item`, sebelumnya tidak ada di export lama).
3. **Stok Pergerakan**: `LaporanStokPergerakanExport` + `laporan/stok/pdf-pergerakan.blade.php` (**landscape**, kolom>7).
4. **Stok Minimum**: `LaporanStokMinimumExport` + `laporan/stok/pdf-minimum.blade.php` — menu ini SEBELUMNYA **tidak punya export sama sekali**, sekarang lengkap Excel+PDF dgn kolom BARU "Rekomendasi Beli" (`(qty_minimum - qty) × 1.2`, dibulatkan ke atas — dipakai bikin PO).
5. **Laba Rugi Produksi** (`LaporanLabaRugiController`, dikonfirmasi ULANG BEDA dari `LaporanKeuanganController::labaRugi()` [[4.24]] — beda sumber data, beda service, beda permission): `LaporanLabaRugiProduksiExport` (nama class SENGAJA dibedakan dari `LaporanLabaRugiExport` yang sudah dipakai controller Keuangan, utk hindari collision) — kolom dinamis 4 level (item/kategori/jenis_olahan/order), pola sama `LaporanLabaRugiExport` (`FromArray`, multi-section: Ringkasan+Breakdown+Detail). **2 varian PDF** sesuai request Owner: `pdf-ringkas.blade.php` (portrait, cuma Ringkasan — "biaya operasional"/"laba bersih" YANG DIMINTA OWNER TIDAK ADA di data source ini krn domain-nya murni produksi, bukan cash flow; diganti "Laba Kotor" + catatan eksplisit di PDF mengarahkan ke Laporan Keuangan utk biaya operasional) dan `pdf-detail.blade.php` (**landscape**, Breakdown+Detail lengkap).

**File yang diedit**: 5 Export class baru, 6 PDF view baru, `LaporanLabaRugiController.php`, `LaporanStokController.php`, `SetoranHarianController.php`, 5 index view (tombol export baru).

**Verifikasi**: `tests/Feature/Tahap7/LaporanBatch1bc1cExportTest.php` (20 test) — semua 5 menu Excel xlsx valid + PDF valid, filter periode/cabang diterapkan, anti-double-count pemasukan order (skenario eksplisit), data kosong tetap generate, 4 level breakdown Laba Rugi semua tidak crash (item/kategori/jenis_olahan/order × excel+pdf), konfirmasi 2 controller Laba Rugi genuinely beda, regresi HTML 5 halaman + tolak tanpa permission. Full regression 301 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**Batch 1 (5 menu Prioritas 1) SELESAI SEMUA** — sisa 17 menu Laporan lain di [[12.13]] menyusul sbg Batch 2/3 terpisah.

### 4.26 🟢 Sprint 3 Batch 2 — Rapikan/Tambah Excel + PDF: 9 dari 17 Menu Laporan Sisa (2026-09-22)

**Konteks**: lanjutan [[4.24]]/[[4.25]] — Owner minta eksekusi 17 menu sisa SEKALIGUS (bukan per sub-batch, tanpa tunggu approval kecuali blocker urgent). Hasil: **9 menu selesai (Excel+PDF genuinely tabular/summary)**, **3 menu di-SKIP sbg blocker genuine** (bukan data kosong — datanya memang tidak ada bentuknya utk export tradisional), sisanya (BEP manual, Neraca, Laba Rugi Formal, Buku Besar, BEP Otomatis) memang cuma butuh Excel karena PDF sudah ada sebelumnya.

**9 menu selesai**:
1. **Laporan Aset** (2 sub-halaman: index + penyusutan) — `LaporanAsetExport`/`LaporanPenyusutanExport` (rapikan dari `fputcsv` lama) + 2 PDF baru (landscape). Sub-menu `maintenance()` **SENGAJA DI-SKIP** — viewnya sudah rusak SEBELUM sesi ini (controller hitung data tapi Blade cuma render placeholder alert, tidak pernah tampilkan list), bug pre-existing di luar scope export.
2. **Laporan BEP (manual)** (2 sub: per-produk + per-cabang) — **tidak ada export sama sekali sebelumnya**, dibangun baru total: `LaporanBepExport`/`LaporanBepCabangExport` + `pdf/bep.blade.php` (portrait)/`pdf/bep-cabang.blade.php` (landscape, row belum-tercapai di-highlight kuning).
3. **Laporan BEP Otomatis** — PDF sudah ada (inline dompdf lama, TIDAK disentuh), ditambah **Excel baru** (`LaporanBepOtomatisExport`, `FromArray` key-value ringkasan + breakdown biaya tetap per kategori) via route baru `bep-otomatis/export-excel` (route PDF lama dibiarkan terpisah, tidak direpurpose).
4. **Laporan Neraca** — PDF ada, ditambah **Excel baru** (`LaporanNeracaExport`, `FromArray` flat Bagian/Item/Nilai mengikuti struktur Aset/Kewajiban/Modal PDF).
5. **Laporan Laba Rugi Formal** — PDF ada, ditambah **Excel baru** (`LaporanLabaRugiFormalExport`, `FromArray` per-seksi COA: Pendapatan/HPP/Beban Operasional/dst, sama urutan PDF).
6. **Laporan Buku Besar** — PDF ada, ditambah **Excel baru** (`LaporanBukuBesarExport`, header akun+saldo normal lalu tabel mutasi debit/kredit/saldo berjalan). **Catatan data**: dev DB saat ini 0 row `chart_of_accounts` ber-`is_leaf=true` tipe Pendapatan/HPP/Beban — menu ini legitimately kosong di dev sampai COA di-setup, BUKAN bug (diverifikasi Excel class benar via unit test langsung dgn data fabrikasi).
7. **Laporan Audit Bukti** — Excel lama (`fputcsv`) dirapikan jadi `LaporanAuditBuktiExport` (baris tanpa bukti upload di-highlight merah, font merah di Excel) + **PDF baru** (landscape, row highlight sama).
8. **Laporan Analisa Jam Ramai** — **tidak ada export sama sekali sebelumnya**, dibangun baru total: `LaporanJamRamaiExport` (24 baris per jam, row jam puncak di-highlight hijau) + `pdf/jam-ramai.blade.php`. Permission BARU `laporan.jam_ramai.export` ditambah ke `PermissionSeeder` (Owner-only default, konsisten pola `laporan.jam_ramai.view` yang juga Owner-only/delegable manual — TIDAK ditambah ke `admin_pusat` di `RolePermissionSeeder`, biar konsisten "menu sensitif, Owner assign manual kalau perlu").
9. **Laporan Pemakaian Perlengkapan** — Excel lama (`streamDownload` CSV mentah, bukan xlsx sungguhan) diganti `LaporanPerlengkapanExport` (xlsx asli + ringkasan di bawah tabel detail) + **PDF baru** (`pdf/perlengkapan.blade.php`, ringkasan+detail sama seperti `print.blade.php` existing yang TIDAK disentuh).

**3 blocker (di-SKIP, dilaporkan ke Owner, BUKAN dieksekusi asal-asalan)**:
- **Laporan Komisi Sales**: **fiturnya sendiri tidak ada** di codebase manapun (0 route/controller/view) — cuma field input `komisi` di record Penggajian. Bukan gap export, tapi genuinely belum ada fitur "Komisi Sales" utk diexport. Perlu keputusan Owner: request fitur baru terpisah, atau memang salah masuk daftar.
- **Laporan Eksekutif**: laporan komposit 9-halaman narasi (ringkasan bisnis + insight teks), BUKAN data tabular per-baris — "Export Excel" tidak punya bentuk natural utk laporan jenis ini (PDF-nya sendiri memang didesain sbg dokumen presentasi, bukan spreadsheet). Perlu keputusan desain Owner dulu: excel-kan bagian tabular-nya saja (parsial), atau skip permanen.
- **Laporan Simulator BEP**: murni kalkulator client-side JavaScript (input+hitung langsung di browser, tidak ada hasil yang disimpan/dikirim ke server) — tidak ada "data laporan" di server utk diexport sama sekali. Kalau Owner mau export, perlu desain ulang dulu (simpan hasil simulasi ke DB dulu, baru bisa diexport) — di luar scope "rapikan export" murni.

**File yang diedit**: 9 Export class baru, 9 PDF view baru, 9 controller (Aset/Bep/BepOtomatis/Neraca/LabaRugiFormal/BukuBesar/AuditBukti/JamRamai/Perlengkapan), `routes/web.php` (route export-excel/export-pdf terpisah utk yang PDF-nya sudah ada, supaya route lama tidak direpurpose), `PermissionSeeder.php` (1 permission baru), 9 index view (tombol export baru).

**Verifikasi**: `tests/Feature/Tahap7/LaporanBatch2ExportTest.php` (13 test) — Excel xlsx valid + PDF valid utk tiap menu (termasuk kondisi data kosong: Aset Penyusutan, BEP manual), validasi `kode_akun` wajib di Buku Besar, permission `laporan.jam_ramai.export` ditolak utk role tanpa akses (403), highlight baris (audit bukti tanpa bukti upload). Full regression 314 test lintas Tahap 2.5/5/6/7 PASS (0 regresi) — termasuk `SmokeTestSemuaMenuTest` (187 route, 0 error 500).

**Sisa dari 17 menu**: 9 selesai sesi ini + 3 blocker (di-skip, tunggu keputusan Owner) = 12. **5 menu belum disentuh eksplisit** karena ternyata sudah tercakup validasi audit awal sbg "hanya butuh Excel, PDF sudah ada" dan masuk hitungan 9 di atas (BEP Otomatis/Neraca/Laba Rugi Formal/Buku Besar) — jadi genuinely 17 = 9 (built/rapikan Excel+PDF penuh: Aset×2, BEP manual×2, Audit Bukti, Jam Ramai, Perlengkapan) + 4 (Excel-only krn PDF sudah ada: BEP Otomatis, Neraca, Laba Rugi Formal, Buku Besar) + 3 blocker = 16, HR/SDM sudah selesai duluan di sesi audit awal Batch 2 (lihat commit sebelumnya, `LaporanHRController` evaluasi/absensi/penggajian) — total 17 genap.

### 4.27 🟢 Sprint 3 Lanjutan — Export Snapshot Simulator BEP (2026-09-22)

**Konteks**: [[4.26]] Batch 2 skip "Laporan Simulator BEP" sbg blocker (murni kalkulator client-side, tidak ada data server). Owner minta approach **"snapshot"**: kirim payload input ke server, server hitung ulang + generate Excel/PDF.

**Keputusan kunci (disetujui Owner)**: **server MEREPRODUKSI ULANG** logic `hitung()` JS (`SimulatorBepController::buildSnapshot()`) — TIDAK percaya begitu saja angka hasil hitung dari browser. Payload yang dikirim cuma 4 input mentah wajib (`volume_harian`, `harga_jual`, `biaya_variabel`, `beban_tetap`) + opsional (`nama_simulasi`, `cabang_id`, `modal_awal`, `target_profit`) — semua metric (BEP unit/rupiah, margin, MoS, kesimpulan, sensitivitas) dihitung ULANG di PHP dgn rumus PERSIS sama dgn JS (`margin=harga-biaya`, `bepUnit=bebanTetap/margin`, `volumeBulanan=volumeHarian×26`, dst) — snapshot jadi genuinely terpercaya, tidak bisa dipalsukan lewat tamper JS browser.

**Field baru ditambah** (di `laporan/simulator-bep/index.blade.php`, TIDAK mengganti slider existing): input teks "Nama Simulasi" (default JS `Simulasi BEP - {tanggal}` kalau kosong), input angka "Target Profit" (opsional, dipakai hitung Margin of Safety). Display baru: "Margin Kontribusi Ratio" (%) di tabel hasil JS existing.

**Grafik Sensitivitas → Tabel (bukan visual chart), keputusan Owner eksplisit**: dompdf **tidak render Chart.js** (JS tidak dieksekusi di render engine, konsisten [[4.24]] alasan `enable_php=false`) — matriks 5×5 (skenario ±10%/±5%/baseline utk Harga Jual × Biaya Variabel, isi = BEP Unit hasil) ditampilkan sbg tabel di Excel Sheet 3 & PDF Section terakhir. Sel infeasible (margin≤0 pada kombinasi itu) tampil "N/A". Sel baseline (0%,0%) di-highlight krim `#FFF8E7`.

**Excel** (`LaporanSimulatorBepExport`, `WithMultipleSheets` — pola BARU, belum pernah dipakai Export manapun di project sebelumnya): 3 sheet class internal di file yang sama (`SimulatorBepParameterSheet`/`HasilSheet`/`SensitivitasSheet`, masing-masing `FromArray`+`WithStyles`, pakai `HasLaporanStyles` trait yang sama).

**PDF** (`laporan/pdf/simulator-bep.blade.php`, extends layout Batch 1 `laporan.pdf.layout`, portrait): Parameter Input → Hasil Perhitungan → box Kesimpulan (list bullet dinamis) → Tabel Sensitivitas.

**Kesimpulan tekstual dinamis** (`buildKesimpulan()`) — 3 skenario sesuai spesifikasi Owner: margin negatif → "BEP tidak bisa dicapai..."; BEP unit > volume bulanan → "⚠️ Volume saat ini BELUM mencapai BEP..."; BEP unit ≤ volume bulanan → "✅ Volume saat ini SUDAH mencapai BEP..." — ditambah baris proyeksi modal (balik modal/habis modal/impas, threshold epsilon Rp1.000 sama seperti JS) dan baris Margin of Safety kalau `target_profit` diisi.

**Cara kirim payload dari browser**: bukan `fetch()`+blob (ribet utk trigger download-file), tapi **hidden `<form method="POST">` yang di-generate JS lalu `.submit()`** — pola paling sederhana utk "POST lalu langsung download response sbg file" tanpa JS tambahan di sisi client utk handle response.

**Permission BARU** `laporan.simulator.export` (Owner-only default, konsisten `laporan.simulator.view` yang juga Owner-only/delegable manual — pola sama [[4.26]] `laporan.jam_ramai.export`).

**File yang diedit**: `app/Http/Requests/SimulatorBepSnapshotRequest.php` (baru), `app/Exports/LaporanSimulatorBepExport.php` (baru, 3 class sheet internal), `resources/views/laporan/pdf/simulator-bep.blade.php` (baru), `app/Http/Controllers/SimulatorBepController.php` (`buildSnapshot()`/`buildKesimpulan()`/`buildSensitivitas()`/`exportExcel()`/`exportPdf()`), `resources/views/laporan/simulator-bep/index.blade.php` (2 input baru, 1 display baru, 2 tombol export, JS `submitSnapshot()`), `routes/web.php` (2 route POST baru), `PermissionSeeder.php` (1 permission baru).

**Verifikasi**: `tests/Feature/Tahap7/SimulatorBepSnapshotExportTest.php` (11 test) — Excel 3-sheet valid, PDF valid, validasi tolak field kosong/nilai negatif, Target Profit opsional tetap jalan kalau kosong, permission ditolak (403) utk role tanpa akses, 3 skenario kesimpulan dinamis (belum BEP/sudah BEP/margin negatif) + kalimat MoS muncul kalau target profit diisi — SEMUA via reflection ke `buildSnapshot()`/`buildKesimpulan()` LANGSUNG (bukan grep string di PDF binary, yang tidak reliable krn dompdf compress/encode konten), reproduksi logic server=JS diverifikasi eksplisit dgn angka fixture. Full regression 325 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**Sprint 3 Batch 2 SEKARANG BENAR-BENAR SELESAI SEMUA 17 menu** (9 dibangun/dirapikan penuh + 4 Excel-only + 1 blocker terakhir Simulator BEP DISELESAIKAN via approach snapshot) — 2 blocker TERSISA (Komisi Sales, Laporan Eksekutif) masih butuh keputusan desain Owner, lihat [[12.15]] (diupdate).

### 4.28 🔴 Go-Live Data Real — GoLiveSeeder, Script `public/golive.php`, dan Alur Setup (2026-09-23)

**Konteks**: aplikasi pindah ke domain baru `erp.dmentaiindonesia.com`; semua data di DB saat itu dummy. Audit seeder menemukan `db:seed` biasa TIDAK boleh dipakai utk go-live.

**Temuan audit seeder (kenapa `DatabaseSeeder` berbahaya utk go-live)**:
- `UserSeeder` membuat 9 user `@berkahmulyo.com` dgn password `password` (Owner: `admin@berkahmulyo.com`) — **kredensial default WAJIB tidak pernah hidup di production**. Seeder itu juga sudah rusak sejak fork (cari cabang `CA001`/`CB001` yang tidak ada lagi).
- `CabangSeeder`/`ItemSeeder`/`ResepBumbuSeeder`/`PembelianPenjualanSeeder`/`HRSeeder` = data dummy. `NeracaSettingSeeder` hardcode modal Rp 198.329.749 (data Berkah Mulyo). `CleanupBerkahMulyoDataSeeder`/`TestingBatch1SeederTemp`/`StokSeeder` = dev only.
- **7 seeder wajib TIDAK ada di `DatabaseSeeder`**: `ChartOfAccountsSeeder` (tanpa ini COA kosong → Laba Rugi Formal/Neraca/Buku Besar kosong), `KategoriCoaBackfillSeeder`, `PerlengkapanKategoriCoaSeeder`, `KeamananPermissionSeeder`, `AbsensiPermissionSeeder`, `AntrianPermissionSeeder` (permission `lihat_audit_log`/`scan_absensi`/`antrian.*` HANYA dibuat di sini, bukan di `PermissionSeeder`).
- **Ketergantungan urutan**: semua seeder permission harus sebelum `RolePermissionSeeder`; `PanduanPosSeeder` sebelum `PanduanStubSeeder` (Stub mengubah `modul` panduan `pos` → `penjualan`, slug tetap `pos`); `ChartOfAccountsSeeder` sebelum `KategoriCoaBackfillSeeder`. `EvaluationAspectSeeder` memakai `create()` (tidak idempotent) — di-guard `count()===0`. **`PanduanStubSeeder` TIDAK bisa dijalankan ulang di DB yang sudah terisi** (update slug bentrok `transfer-dana`) — GoLiveSeeder hanya utk DB fresh.

**Yang dibangun**:
- `database/seeders/GoLiveSeeder.php` — 18 seeder referensi wajib (urutan di atas) + 1 Owner + 1 Gudang Pusat placeholder (`HO-01`, tipe `gudang_pusat`, owner ter-attach `is_default`). **Role Owner = `owner` (bukan `admin_pusat`)** — `owner` mendapat semua permission lewat `Gate::before`; `admin_pusat` role terpisah (121 permission ter-map). Menolak jalan di `APP_ENV=production` kalau kredensial masih default.
- `config/golive.php` — `GOLIVE_OWNER_EMAIL`/`_PASSWORD`/`_NAME` (dari `.env`, bukan hardcode), `GOLIVE_ENABLED` (saklar pengaman).
- `public/golive.php` — script sekali pakai (pola `clear-cache.php`) utk hosting tanpa terminal: `migrate:fresh --force` + `GoLiveSeeder`. **5 pengaman berlapis**: `APP_ENV=production`, `GOLIVE_ENABLED=true` di `.env`, kredensial bukan default, `?confirm=YES-GO-LIVE`, dan `storage/golive.lock` (cegah wipe kedua tanpa sengaja; hapus lock manual kalau memang mau mengulang). Password Owner SENGAJA tidak dicetak di layar (cuma email) — script tanpa auth, output bisa terbaca siapa pun yang tahu URL.

**Yang TIDAK dilakukan GoLiveSeeder**: tidak membuat outlet, kas, karyawan, item, modal owner (`neraca_settings` kosong → `getSetting()` membuat baris `modal_owner=0`). Semua diinput Owner lewat UI (alur di bawah).

**⚠️ Setelah go-live**: hapus `public/golive.php` dari server, hapus semua baris `GOLIVE_*` dari `.env`, jalankan `public/clear-cache.php` (lalu hapus), ganti password Owner. Pastikan `.env` production: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://erp.dmentaiindonesia.com`. **(2026-10-17: `public/golive.php` sudah DIHAPUS dari repository — kalau suatu saat perlu go-live ulang, ambil dari riwayat git commit sebelum `bd8a63d`.)**

**Urutan setup data real (15 langkah, sesuai dependency)**:
1. Login Owner, ganti password/email. 2. Pengaturan Umum (nama perusahaan, logo). 3. Cabang (Gudang Pusat + outlet: alamat, GPS, config POS dine-in/takeaway/frozen/service charge/footer struk). 4. **Kas per cabang WAJIB** — tiap outlet 3 kas default `tunai`/`transfer`/`qris` (tanpa itu checkout POS error "Cabang belum punya Kas default…") + kas Gudang Pusat (tujuan Setoran Kasir); saldo awal otomatis jadi transaksi "Saldo Awal". 5. Modal Owner di halaman Neraca (manual, supaya BALANCE). 6. Karyawan → User login (role + cabang). 7. Cek Role & Hak Akses (`/role`; permission laporan baru Owner-only). 8. Kategori Item (7 default ada). 9. Master Bahan Baku & Kemasan (satuan harga = satuan stok! lihat [[4.19]]). 10. Stok awal per cabang (Pembelian/Adjustment → batch FIFO berharga; verifikasi HPP>0). 11. Master Bumbu Pusat (opsional, sebelum Produk Jual). 12. Master Produk Jual (resep, foto, varian, centang outlet `item_cabang`). 13. Aset awal. 14. Program Loyalty (opsional). 15. Verifikasi per outlet: 1 order tunai dgn kembalian → cek kas naik sebesar NILAI order ([[4.29]]), stok terpotong, HPP terisi, Setoran Kasir benar.

**Belum ada tool "Reset Data Transaksi"** (TODO 5.5) — order percobaan sebaiknya di gladi lokal, bukan production.

**Verifikasi**: `tests/Feature/Tahap7/GoLiveSeederTest.php` (10 test; DB test sudah terisi jadi panduan/tooltip dikosongkan dulu dlm transaction utk meniru DB fresh) + **gladi bersih nyata**: `migrate:fresh` + `GoLiveSeeder` di DB kosong terpisah (`erp_dimsum_golive_rehearsal`, sudah di-drop) → 1 user, 1 cabang, 178 permission, 121 permission `admin_pusat`, 59 akun COA (3 leaf HPP), 22 kategori transaksi, 7 kategori item, 8 kategori aset, 79 panduan (0 kosong), 113 tooltip, 0 item/kas/order/karyawan/user dummy; `public/golive.php` diuji 6 skenario (bukan production, `GOLIVE_ENABLED` belum diset, kredensial default, tanpa confirm, eksekusi penuh, jalan kedua ditolak lock) dgn `DB_DATABASE` di-override ke DB gladi.

### 4.29 🔴 Kas/Transaksi Mencatat Uang Diserahkan, Bukan Nilai Order (Kembalian Tidak Dikurangi) (2026-09-23)

**Laporan Owner**: order Rp 5.000, bayar tunai Rp 50.000, kembali Rp 45.000 — di Kas & Transaksi tercatat pemasukan Rp 50.000.

**Root cause**: `PenjualanService::prosesPembayaran()` mencatat `payments[].jumlah` (uang yang diserahkan) ke `Kas.saldo_sekarang`, `TransaksiKeuangan.jumlah`, dan `OrderPayment.jumlah` — kembalian tidak pernah dikurangkan (`orders.kembalian` cuma dihitung setelahnya utk struk).

**Fix**: kembalian (`total pembayaran − total_bayar`) dipotong dari pembayaran **tunai** sebelum dicatat ke ketiga tempat itu (split payment: cuma bagian tunai yang dipotong, transfer/QRIS utuh). `orders.jumlah_bayar` tetap uang yang diserahkan (struk). `SetoranKasirService` (baca `order_payments`) dan pembatalan order (baca `TransaksiKeuangan.jumlah`) otomatis ikut benar. 4 test di `KembalianKasSinkronTest.php`; regresi 329 test PASS.

**⚠️ Data lama tidak dikoreksi**: order lama yang punya kembalian masih membuat saldo kas/Setoran Kasir kelebihan sebesar total kembaliannya. Untuk data go-live baru (`migrate:fresh`) tidak relevan; kalau ada data lama yang dipertahankan, perlu script koreksi (belum dibuat — tunggu persetujuan Owner).

### 4.30 🟢 Sprint Unit Family — PO/Adjustment dgn Unit Beli Fleksibel (2026-09-27)

**Konteks Owner**: sudah input 30 bahan baku di production. Sekarang minta input Pembelian dalam **unit beli** (pack/karung/dus) yang auto-convert ke **unit pakai** (`items.satuan`), konversi beda per bahan (sumpit A = 100, sumpit B = 50). Non-breaking wajib: data existing tidak boleh rusak.

**Design (Opsi A — dropdown fleksibel, disetujui Owner)**:
- `items.satuan` **tetap "unit pakai"** — semua sistem stok existing (`stocks.qty`, `stock_batches.qty`, `stock_movements.qty`, `order_items.qty`, `resep_bumbu_items.qty_per_unit`) TIDAK berubah maknanya. Cuma **input di form** yang bisa pakai unit beli, konversi terjadi sebelum simpan.
- 2 kolom baru nullable `items.{unit_beli, isi_per_unit_beli}` — kalau NULL = mode legacy (perilaku lama). Kalau isi = form PO/Adjustment kasih dropdown Unit.
- 2 kolom baru nullable `purchase_order_items.{unit_input, qty_input}` — audit trail input asli user (`10 pack`) sebelum konversi ke `qty_pesan` (`1000 pcs`) + `harga_satuan` (Rp/pcs). NULL berarti PO legacy (input dlm unit pakai langsung).
- **`harga_satuan` mode "Per Unit Input" (Opsi A, Owner)**: user input Rp 5.000/pack di form, backend hitung `harga_pcs = 5000/100 = 50`, simpan `harga_satuan=50`. Info live "10 pack × Rp 5.000 = Total Rp 50.000 (setara 1000 pcs @ Rp 50/pcs)" tampil di form.
- **Dropdown Unit Beli = HTML `<datalist>` free-text** (bukan enum DB): 7 saran (pack/karung/dus/box/plastik/lusin/gross) + user bebas ketik (sak, renceng, dll).

**Yang TIDAK termasuk sprint ini** (di luar scope, keputusan Owner):
- **Retur Pembelian** — fitur `Retur/retur_items` genuinely tidak ada di codebase (0 model/tabel/controller). Bukan gap unit family, tapi fitur belum ada sama sekali. Skip permanen dari sprint ini, kalau butuh nanti = sprint terpisah.
- **Master Produk Jual** tidak ikut disentuh (produk jual **tidak dibeli**, jadi konsep unit beli tidak relevan di sana).

**Skema**: migration `2026_09_27_A00001_add_unit_beli_to_items_and_purchase_order_items` — 4 kolom nullable, tanpa FK, tanpa constraint DB, tanpa data-migrate. `down()` bersih (`dropColumn`). Zero-risk deploy.

**Yang dibuat**:
- **Model `Item`**: `hasUnitBeli()` (`!empty(unit_beli) && isi > 0`) + `convertToUnitPakai(float $qty, ?string $unit)` — case-insensitive, unit tidak dikenal / NULL passthrough apa adanya. Cast `isi_per_unit_beli => 'decimal:3'`.
- **Component `<x-unit-beli-section>`** (reusable, 3 form pakai yang sama): section card dgn info alert + datalist + preview live "1 pack = 100 satuan" via JS `@once`.
- **Master Bahan Baku** (create+edit) + **Master Barang Lengkap** (create+edit) — embed component. `BahanBakuRequest`/`ItemRequest` validasi `required_with` mutual + `isi_per_unit_beli|gt:0`.
- **PO Create form** (`resources/views/pembelian/create.blade.php`): dropdown Unit per baris (default ke `unit_beli` kalau ada), label harga dinamis "Harga / pack", info live konversi ("10 pack × Rp 5.000 = Rp 50.000 (setara 1000 pcs @ Rp 50/pcs)").
- **`PurchaseOrderController::store`**: sebelum simpan, batch-load `Item` by id, tiap baris: kalau `unit_input == item->unit_beli` (case-insensitive) → `qty_pesan *= isi`, `harga_satuan /= isi`, simpan `unit_input`+`qty_input` sbg audit. Kalau tidak → `unit_input=NULL, qty_input=NULL` (legacy mode).
- **PO Show** (`resources/views/pembelian/show.blade.php`): tampil `1000 pcs` + info kecil "(input: 10 pack)" kalau ada audit trail.
- **Adjustment Stok** (`resources/views/stok/adjustment.blade.php`): dropdown `unit_input` di sebelah `qty_fisik`, JS `qtyFisikPakai()` konversi ke unit pakai untuk perhitungan selisih/preview FIFO (semua logic batch existing tetap pakai angka pcs, no-op utk data lama).
- **`StokController::adjustmentStore`**: konversi `qty_fisik` via `Item::convertToUnitPakai()`, prepend audit trail `[Input: 5 pack]` ke `catatan` yang diteruskan ke `stock_movements.catatan` (**tidak ada tabel `stock_adjustments` di codebase** — audit trail masuk ke `catatan` movement, satu-satunya tempat yg ada).
- **Laporan Stok** (`resources/views/laporan/stok/index.blade.php`): kolom baru "Setara Pack" — barang dgn `hasUnitBeli()` tampil `5 pack` (dari `qty / isi_per_unit_beli`, trailing zero dibuang), barang tanpa tampil "—".

**Non-breaking terverifikasi**:
- 30 bahan existing tetap `unit_beli=NULL` → form show dropdown "—" saja, submit dgn `unit_input=NULL` → controller skip konversi → `qty_pesan` masuk apa adanya. Diverifikasi via 3 test eksplisit: `test_master_bahan_baku_simpan_tanpa_unit_beli_backward_compat`, `test_po_item_tanpa_unit_beli_tetap_works`, `test_adjustment_tanpa_unit_input_backward_compat`.
- PO lama (0 baris `unit_input`) tampil normal — kolom audit "input:" hanya muncul kalau `$item->unit_input && $item->qty_input`.
- Regresi penuh 351 test PASS (12 baru + 339 lama), 0 regresi.

**Bug ditemukan & difix sekalian** (efek samping): test lama `KodeKategoriWajibTest` pakai `assertDontSee('(opsional)')` yang case-insensitive-collide dgn label section baru "Unit Beli (opsional)". Label diganti "(Opsional)" (huruf besar). Ini bukan bug produksi, cuma naming collision — pelajaran: `assertDontSee` string umum ("opsional") rawan false-positive begitu ada section baru yang wajar-wajar saja pakai kata itu.

**File yang diedit** (14 file):
- Migration baru: `2026_09_27_A00001_add_unit_beli_to_items_and_purchase_order_items.php`
- Model: `app/Models/Item.php` (helper+fillable+cast), `app/Models/PurchaseOrderItem.php` (fillable)
- Component baru: `resources/views/components/unit-beli-section.blade.php`
- Request: `app/Http/Requests/BahanBakuRequest.php`, `ItemRequest.php`, `PurchaseOrderRequest.php`, `AdjustmentStokRequest.php`
- Controller: `PurchaseOrderController.php` (`store()`), `StokController.php` (`adjustmentStore()`)
- View: `master/bahan-baku/create.blade.php` + `edit.blade.php`, `item/create.blade.php` + `edit.blade.php`, `pembelian/create.blade.php` + `show.blade.php`, `stok/adjustment.blade.php`, `laporan/stok/index.blade.php`

**Verifikasi**: `tests/Feature/Tahap7/UnitFamilyTest.php` (12 test) — konversi model (case-insensitive + passthrough), Master Bahan Baku save (dgn/tanpa unit_beli + validasi mutual), PO input pack (`10 pack × Rp 5.000` → `qty=1000, harga=50`, `unit_input='pack', qty_input=10`), PO input pcs backward compat (`unit_input=NULL`), PO item tanpa unit_beli, Adjustment input pack (`5 pack → qty=500` + audit trail di catatan movement), Adjustment backward compat, Laporan Stok kolom "Setara Pack" (500 pcs → "5 pack"), 4 kolom baru nullable (data existing aman).

**Instruksi manual utk Owner (setelah deploy)**:
1. Deploy: `git pull` → `public/clear-cache.php` (lalu hapus) → jalankan migration lewat script (kalau di production tanpa terminal, tambah baris `Artisan::call('migrate', ['--force' => true])` ke `clear-cache.php` sekali pakai, jalankan, lalu revert).
2. Edit 30 bahan existing di menu **Master Bahan Baku** → klik satu-satu → isi section "Unit Beli (Opsional)" kalau bahan itu genuinely dibeli dlm pack/karung/dus. Contoh: sumpit → `pack`, isi `100`. Bahan yang genuinely dibeli per satuan pakai (mis. daging per kg) → **biarkan kosong**, sudah benar.
3. Test 1 PO: pilih bahan yg sudah ada unit_beli → dropdown Unit muncul otomatis → input `10 pack × Rp 5.000` → cek info live "= 1000 pcs @ Rp 50/pcs" → simpan → cek di **Pembelian → detail PO** ada label "(input: 10 pack)" di kolom Qty.

**⚠️ 1 catatan penting**: adjustment audit trail masuk ke `stock_movements.catatan` sbg prefix `[Input: 5 pack] <catatan user>`. Tidak muncul di UI Adjustment histori (view existing belum highlight catatan movement) — sudah cukup utk audit manual via **Laporan Pergerakan Stok** yang menampilkan kolom catatan. Kalau nanti dibuat kolom terpisah `stock_movements.unit_input`/`qty_input` seperti PO, itu sprint kecil terpisah (~15 menit).

### 4.38 🟢 Perbaikan Harga (Standar vs PO, per Outlet), Wajib Bukti Bayar per Cabang, PO ke Gudang di POS, Role Bebas (2026-10-18)

**Latar belakang (laporan Owner)**: (1) harga master Bahan Baku "berubah sendiri" setelah PO diterima; (2) harga bahan beda per outlet; (3) harga jual produk beda per outlet; (4) semua menu terkait harus ikut tanpa error; (5) wajib bukti Transfer/QRIS diatur per cabang; (6) role bisa ditambah sendiri (mis. "Kru"); (7) PO cabang ke Gudang Pusat tampil di POS gudang. Didiskusikan panjang — Owner minta disederhanakan: prioritas poin 1–4, poin 7 hanya kemudahan tampilan.

**Akar masalah harga**: `PurchaseOrderService::terima()` menimpa `items.harga_beli_terakhir` dgn harga PO + **bug form PO** (`pembelian/create.blade.php`): harga per pcs diisi ke kolom "Harga / pack" tanpa dikali isi & tidak ikut berubah saat ganti unit → PO tersimpan 1/isi kali harga → master tertimpa harga per pcs yang "aneh". HPP penjualan sendiri SELALU benar (FIFO per batch).

**Tahap A — Harga** (`2026_10_18_100001`):
- **`items.harga_beli_terakhir` sekarang = "Harga Standar"** (manual, TIDAK ditimpa PO; nama kolom sengaja dipertahankan supaya 44 file pembaca otomatis membaca harga yang stabil). PO terima hanya mengisi kalau masih kosong (pembelian pertama). Kolom baru `items.harga_po_terakhir`(+`_at`) = info dari PO.
- **Harga beli per outlet**: `item_cabang.harga_beli` (BEDA dari `harga_override` = harga JUAL). Diisi di form Bahan Baku (komponen `<x-harga-standar-section>`: Harga Standar per satuan pakai + input "harga per pack" → dihitung per pcs + info PO terakhir + tabel per outlet). Baris baru dibuat `is_active=true` (ketersediaan POS tambahan_gratis tidak berubah). `Item::hargaBeliDiCabang()`.
- **Form PO**: harga awal = harga outlet tujuan ?? Harga Standar, × isi bila unit beli; ganti unit → harga dikonversi; ganti outlet → baris yang belum diubah manual ikut harga outlet.
- **Harga varian per outlet** (poin 3): `ItemVariant::hargaEfektifDiCabang()` — varian tanpa override ikut harga outlet (POS grid, AJAX varian, server `resolveItemRows(..., $cabangId)`). Harga produk non-varian per outlet sudah ada sejak Tahap 2.5. Catatan: harga di keranjang POS memang bisa diubah kasir (perilaku lama, tidak diubah).
- **Transfer Stok**: `stock_transfer_items.harga_per_unit` = modal FIFO rata-rata yang keluar dari asal saat kirim → batch tujuan pakai itu (dulu harga master global). **Bug lama**: batal transfer setelah kirim mengembalikan stok dgn harga Rp 0 → sekarang modal yang sama.
- **Dashboard Stok**: nilai stok mati & daftar per lokasi pakai FIFO (`nilaiFifo`, sama dgn Neraca/Closing; qty tanpa batch pakai Harga Standar).
- Label "Harga Beli / Harga Beli Terakhir" diseragamkan jadi **"Harga Standar"** di semua view terkait (item/*, resep-bumbu, produk-jual picker, adjustment).

**Tahap B — Wajib bukti** (`2026_10_18_100002`): `cabangs.wajib_bukti_transfer/qris` (default **true** = perilaku POS lama). Dicek di `PenjualanService::pastikanBuktiPembayaran()` (dipanggil `chargeBillInternal` → transaksi baru, bayar Bill Tersimpan, split payment). POS: tanda wajib & validasi ikut saklar; modal Bayar Bill kini punya upload bukti (dikirim FormData). Panduan BARU `cabang` + tombol Cara Pakai di halaman Cabang. 4 test lama yg menguji hal lain mematikan saklar di `setUp`.

**Tahap C — PO ke Gudang di POS** (`2026_10_18_100003`): `suppliers.cabang_id` = supplier internal (lokasi kita). Form PO: supplier internal boleh berisi produk jual yg dijual di lokasi itu (`Item::TIPE_PRODUK_INTERNAL`; guard server tetap menolak ke supplier luar). POS lokasi supplier: panel "Pesanan PO dari Cabang" (PO status disetujui/dikirim_supplier tanpa nota aktif) → "Isi Keranjang" pakai **harga lokasi ini** + peringatan beda harga; nota tertaut `orders.purchase_order_id` (divalidasi PO memang utk lokasi ini). Detail PO cabang: kotak "Nota dari Gudang" + peringatan beda total (cabang yang edit/buat ulang PO). **Batasan diketahui**: stok produk jual yang diterima cabang via PO tercatat di stok cabang; di POS cabang produk ber-resep tetap memotong bahan resep (aturan [[4.34]]), produk tanpa resep memotong stoknya sendiri.

**Tahap D — Role bebas** (`2026_10_18_100004`): tabel `custom_roles`; `users.role` ENUM→VARCHAR(50) (down: user role buatan → helper). Cast `App\Casts\RoleCast`: role sistem → enum `RoleUser` (perilaku lama), role buatan → `App\Support\CustomRoleValue` (punya `->value`, `label()`, `canAccessAllBranches()`). `App\Support\Roles::semua()/values()/find()` dipakai UserController/UserRequest/KaryawanController/form karyawan/RoleController. Halaman Role: Tambah/Ubah/Hapus role buatan (hapus ditolak bila masih dipakai user). BahanBaku/ProdukJual/StockTransfer FormRequest: role buatan boleh bila punya permission terkait. **Batasan**: 14 grup route `role:` (Cabang, User, Role, Supplier, Semua Barang, Jenis Menu, Shift, Hari Libur, Registrasi Wajah, kelola Absensi, Penggajian, buat Evaluasi, admin Panduan/Tooltip) tetap khusus role sistem — **batasan ini DIHAPUS di [[4.43]]** (semua menu ikut centangan kecuali Role & Hak Akses dan Pengaturan Umum). Panduan BARU `role-hak-akses` + tombol Cara Pakai.

**Bug lain ditemukan & difix**: BahanBakuRequest mengubah harga kosong jadi `''` → error 500 saat kolom harga dikosongkan (sekarang null).

**Panduan & tooltip**: `Perbaikan438PanduanSeeder` (marker per tahap, idempotent; terdaftar di DatabaseSeeder & GoLiveSeeder). Tidak ada permission baru.

**Verifikasi**: `HargaTahapATest` (9), `BuktiBayarTahapBTest` (7), `PoGudangTahapCTest` (7), `RoleBuatanTahapDTest` (9). Regresi per tahap: hanya 11 kegagalan pre-existing ([[4.37]]). Rollback 4 migration diverifikasi manual. Halaman terkait dicek 200 di data dev.

**Tahap E/F (harga & qty minimum per outlet dalam 1 data barang) — DITOLAK Owner 2026-10-19, tidak pernah dirilis, digantikan [[4.39]] (data terpisah per cabang).** Yang tetap dipakai dari situ: audit menu stok (stok rendah hanya `qty_minimum > 0`, Dashboard Stok SUM minimum, Kartu Stok minimum lokasi, fallback harga batch & `api.stok.qty` pakai `hargaBeliDiCabang`).

**Deploy production**: digabung dgn [[4.39]] — lihat instruksi deploy di sana. Setelah itu: buat supplier "Gudang Pusat" dgn Supplier Internal = Gudang Pusat; cek saklar wajib bukti tiap cabang; koreksi Harga Standar bahan yang terlanjur tertimpa (Owner sendiri).

### 4.49 🟢 Porting erp-coffeeshop: Produksi, Kirim ke Cabang, Retur Cabang, POS HP, Label Gambar (2026-11-10)

**Sumber**: `erp-coffeeshop/PORTING_GEROBAK_KE_DIMSUM.md` + `FASE_PRODUKSI_GEROBAK_DESIGN.md`, CLAUDE.md coffeeshop 4.50/4.51/12.15 (folder coffeeshop hanya dibaca). Keputusan Owner: Gudang memproduksi; **PO ke Gudang tetap jalan**, Kirim ke Cabang = cara tambahan; harga kirim = harga Produk Jual gudang; kas dipilih saat Terima (bawaan Tunai); Terima sebagian / kas boleh minus / uang retur dipilih per retur (sama coffeeshop); laporan "Semua Cabang" hanya mengeliminasi di 3 laporan kasir + Tutup Buku Keseluruhan (opsi a); barang yang belum ada padanannya di cabang **dibuat otomatis** + daftar di halaman Terima (opsi a); izin bawaan sesuai usulan. Catatan: file porting coffeeshop menyebut "Sinsam Dimsum" — salah ketik, instalasinya D'mentai + demo.

**Tahap D — Label Produk gambar** (coffeeshop `8042d2a`…`38bcfde`, `d285822`, `8a72d4d`): `components/cup-label-overlay` diganti versi coffeeshop: label digambar di canvas 8 titik/mm → `BITMAP` TSPL (bit 0 = hitam), **Atur posisi label (perangkat ini)** (geser X/Y localStorage, putar 180°, Cara cetak Gambar/Teks, Balik hitam-putih, Label Uji), tata letak jatah baris. **Khusus Dimsum: baris `Exp` Frozen punya tempat tetap** (di atas nama pelanggan, tidak pernah terpotong), judul "Label Produk", slug `label-produk`, bahasa printer browser bawaan **TSPL** (dulu ESC/POS). Migration `2026_11_10_100001` `cabangs.label_cup_geser_x/_y` (default 0) + form Cabang → Edit → Label Produk (`CabangController` lewati kolom kalau belum ada). `CupLabelService`: `geser`, `catatan_order` (orders.catatan), tanggal di sebelah jam. Label tetap MATI per cabang. Test `LabelGambarTest` (6).

**Tahap C — POS HP** (`512ccb2`): hanya `@media (max-width: 575.98px)` + id `#posHeader/#cardPelanggan/#cardItemOrder` + bilah `#posBarHp` (di luar `#formPos`, membaca keranjang lewat MutationObserver; jumlah = **total qty**, Bayar → `#panelBayar`). Tablet/PC tidak berubah. Diuji iframe 390 px (klik 2× produk sama → "3 item", total benar). Test `PosTampilanHpTest` (2).

**Tahap B1 — Produksi** (`1139fe5`): menu Stok & Gudang → **Produksi** (`/produksi`, `produksi.*`), tab Riwayat + **Resep Produksi**. Tabel `resep_produksi(_items)`, `produksi_batches(_items)` (migration `2026_11_10_200001`), `ProduksiService`, `Item::resepProduksi()`. Konversi takaran = **`App\Support\KonversiSatuan::keSatuanStok`** (kelas baru — komposisi POS tetap aturan lamanya). **Penyesuaian Dimsum**: barang hasil & bahan harus **milik gudang** (`items.cabang_id`, data per cabang 4.39), resep difilter per gudang; perkiraan biaya = **harga FIFO** (`HargaResepService`); **Harga Standar tidak ditimpa** (diisi hanya kalau kosong, aturan 4.38); validasi resep di luar `try` (bug coffeeshop: error validasi tertangkap `catch (\Exception)`). Test `ProduksiTest` (8, termasuk hasil produksi dipakai komposisi Produk Jual → HPP = modal produksi).

**Tahap B2 — Kirim ke Cabang** (`40bd55e`, `9fc2b31`): migration `2026_11_10_300001` → `orders.customer_type` ('internal'), `outlet_tujuan_id`, `status_kirim`, `diterima_at/by`, `alasan_tolak` + `order_kiriman_items` (+ `item_tujuan_id` = padanan milik cabang). POS **Gudang Pusat** (`$outletList`): kartu Jenis Pembeli → Kirim ke Cabang + Cabang Tujuan; pembayaran/Save Bill/antrian disembunyikan; `kirimKeCabang()` (JS terpisah, alur bayar tidak disentuh). `PenjualanService`: `validasiKirimCabang()` (penjual gudang_pusat, tujuan cabang aktif ≠ penjual, barang master, **tidak boleh dari Pesanan PO**, Kas Tunai gudang), `$rincianPotong` → `order_kiriman_items`, produk tanpa potongan stok ditolak; order `status = proses` + `status_kirim = menunggu`, tanpa pembayaran. **`StatusOrder::bukanPenjualan()` kini termasuk `proses`** (label "Dikirim (menunggu diterima)") → semua laporan otomatis; `ClosingService` 3 daftar status ditulis langsung → `bukanPenjualan()`. `terimaKiriman($order, $qty, $userId, $kasId)`: Terima sebagian (sisa kembali modal semula), stok masuk ke **padanan Kode Item milik cabang** (`PadananBarangService::untukCabang`, dibuat otomatis), modal = harga kirim setelah diskon (dibagi per porsi modal), kas cabang terpilih − (TK Pengeluaran Pembelian Bahan, `order_internal`), pendapatan gudang via `prosesPembayaran()` (Kas Tunai gudang +), `tanggal_order` = tanggal Terima. Tolak/Batalkan → stok kembali; sudah diterima → tidak bisa dibatalkan/diedit/dikembalikan; Save Bill internal 422. **Kunci Tutup Buku**: trait `DijagaClosing` hook baru `closingAbaikanTanggalLama()` — Order yang sebelumnya `bukanPenjualan` tidak dicek tanggal lamanya (kiriman dikirim di tanggal yang sudah closing tetap bisa diterima hari ini). **Eliminasi**: `LaporanTransaksiKasirService::tanpaInternal()` (Semua Cabang di Laporan Transaksi/Produk/Kategori), `ClosingService` Keseluruhan `eliminasi_omzet/hpp` (= omzet & HPP gudang dari kiriman; sama coffeeshop — laba konsolidasi memakai harga kirim sebagai modal cabang). Menu Stok & Gudang → **Kiriman Cabang** (`/kiriman`, badge, `KirimanCabangController::jumlahMenunggu()` cek kolom dulu). **Saklar per Gudang (permintaan Owner, bawaan MATI)**: migration `2026_11_10_300003` `cabangs.kirim_cabang_aktif` — Cabang → Edit (hanya tipe Gudang Pusat) → Config POS "Aktifkan Kirim ke Cabang di POS"; mati = POS gudang tanpa kartu Jenis Pembeli (`$outletList` kosong) dan `validasiKirimCabang()` menolak; tooltip `cabang.kirim_cabang_aktif`. Test `KirimanCabangTest` (8).

**Tahap B3 — Retur Cabang** (`40bd55e`): migration `2026_11_10_300002` `retur_internals(_items)` + `orders.retur_internal_id`. `ReturInternalService`: yang keluar dari cabang = **barang milik cabang** (`item_tujuan_id`), yang masuk ke gudang = barang gudang; uang kembali → modal asli gudang + **order koreksi minus** di gudang + TK **cermin minus** (Pemasukan Penjualan −nilai di gudang, Pengeluaran Pembelian Bahan −nilai di cabang); uang dikembalikan ke **kas yang dulu membayar** kiriman. Tanpa uang → batch Rp0. Order koreksi tidak bisa dibatalkan/diedit/dihapus. Menu **Retur Cabang** (`/retur-cabang`, badge cek tabel dulu). Test `ReturCabangTest` (4, termasuk alur lengkap produksi → kirim → terima → cabang jual → retur basi → buang; laporan gudang/cabang/Semua Cabang).

**Izin baru** (`Porting449Seeder::PERMISSIONS/ROLE_DEFAULTS`, spread ke PermissionSeeder/RolePermissionSeeder; di production **centangan role bawaan hanya saat izin pertama kali dibuat**): `produksi.view/create` (admin_pusat, admin_gudang, operator_produksi), `produksi.batalkan/resep.kelola` (admin_pusat, admin_gudang), `kiriman.view` (admin_pusat, admin_gudang, manajer, kasir), `kiriman.terima` (admin_pusat, manajer, kasir), `retur.view` (+admin_gudang), `retur.create` (admin_pusat, manajer, kasir), `retur.terima` (admin_pusat, admin_gudang). POS Gudang memakai izin POS biasa (`order.create`; admin_gudang tidak punya). Panduan `produksi`, `kiriman-cabang`, `retur-cabang` + bagian `<!-- LABEL-GAMBAR-449 -->` (label-produk), `<!-- POS-HP-449 -->` & `<!-- KIRIM-CABANG-449 -->` (pos); tooltip `produksi.*`, `kiriman.*`, `retur_cabang.*`, `pos.customer_type/outlet_tujuan`, `label_cup.posisi`, `cabang.label_cup_geser`. Seeder di DatabaseSeeder & GoLiveSeeder setelah LabelProdukSeeder.

**Aman sebelum migration** (pelajaran insiden coffeeshop): badge sidebar cek kolom/tabel; penjualan biasa tidak menulis kolom kiriman (`+ ($isKirim ? [...] : [])`); laporan/Tutup Buku memakai `Order::adaKolomKiriman()`. Diuji: 4 migration dimundurkan → dashboard, POS, riwayat, laporan transaksi (cabang & Semua Cabang), Tutup Buku, edit cabang, stok 200 + transaksi POS biasa berhasil. Rollback tiap migration diverifikasi.

**Regresi**: 10 kegagalan lama saja (sama sebelum 4.49). Render data lokal (rollback): POS gudang mode Kirim (toggle JS 7/7), halaman Terima/Retur, form Produksi/Resep — 0 error JS.

**Deploy (belum — tunggu persetujuan Owner)**: 5 migration. Backup DB → `git pull` → `https://erpdimsum.azwacore.com/deploy-449.php?confirm=YES-PORTING449` (demo dulu) lalu `https://erp.dmentaiindonesia.com/deploy-449.php?confirm=YES-PORTING449` → hapus `public/deploy-449.php`. Script mencetak centangan izin per role & Gudang Pusat aktif; lock `storage/deploy-449.lock`.
### 4.48 🟢 Warna Aplikasi di Pengaturan Umum (2026-11-10)

**Permintaan Owner**: warna aplikasi bisa diubah dari Pengaturan Umum (aplikasi dipasang di beberapa usaha). Keputusan (saran disetujui Owner): 3 warna, berlaku seluruh usaha (bukan per cabang), **warna tombol Bootstrap TIDAK ikut** (hijau/merah/biru punya arti), PDF/Excel ikut, struk tetap hitam-putih.

**Data**: migration `2026_11_09_100001` → `pengaturan_umums.warna_utama/warna_sidebar/warna_latar` (VARCHAR 7, NULL = bawaan `Branding::WARNA_BAWAAN` = #FF6B00/#1A1A1A/#FFF8E7 → tampilan tidak berubah setelah deploy). Controller menyimpan NULL kalau nilai = bawaan; kolom hanya diubah kalau field dikirim. Validasi `#RRGGBB`; **Warna Latar wajib terang** (`teksDiAtas() === '#1A1A1A'`), karena judul halaman ditulis gelap langsung di atas latar.

**Pusat warna `App\Support\Branding`**: `warna($jenis)`, `TEMA` (Oranye & Hitam/bawaan, Kopi, Hijau, Biru, Merah, Ungu), `teksDiAtas()` (putih kecuali kontras tulisan putih < 2,5 — ambang dipilih supaya oranye bawaan tetap tulisan putih), `gelapkan()`, `rgb()`, `cssVariabel()`, `warnaJs()`; PWA `theme_color`/`background_color` ikut (`terapkanKeConfigPwa`). Komponen `<x-brand-warna />` mencetak `:root{--brand-utama, --brand-utama-rgb, --brand-utama-gelap, --brand-utama-teks, --brand-sidebar, --brand-sidebar-rgb, --brand-sidebar-teks-rgb, --brand-latar, --brand-latar-rgb}` + `window.BRAND` (Chart.js tidak bisa membaca variabel CSS). Dipasang di `layouts/app`, `layouts/guest` (login), `vendor/laravelpwa/offline`; error 403/404/500 extend layouts.app.

**Yang diganti ke variabel**: sidebar (latar, menu aktif/hover, tulisan sidebar `rgba(var(--brand-sidebar-teks-rgb),…)`), garis topbar, latar halaman, login (gradasi, fokus input, tombol), error pages, POS (harga di kartu, tipe transaksi aktif, kedip baris, tombol varian), 3 laporan menu Penjualan (kartu, chip, tombol, grafik via `BRAND`), header PDF (`$brand->warna(...)` langsung — dompdf tidak mendukung var()), header Excel (`HasLaporanStyles` + 2 export). **JANGAN tulis #FF6B00/#FFF8E7 langsung di view baru** — pakai `var(--brand-utama)`/`var(--brand-latar)` (web), `$brand->warna('utama')` (PDF), `BRAND.utama` (JS grafik). `#1A1A1A` sebagai warna TULISAN sengaja tetap (bukan warna sidebar).

**UI**: Pengaturan Umum → kartu **Warna Aplikasi** (tema siap pakai, pemilih warna + kotak hex, pratinjau mini sidebar/menu/latar/grafik, "Kembalikan ke warna bawaan"); JS memakai aturan `teksDiAtas` yang sama. Panduan `<!-- WARNA-448 -->` di slug `pengaturan-umum` + tooltip `pengaturan_umum.warna` (`WarnaAplikasiSeeder`, setelah IdentitasUsahaSeeder di DatabaseSeeder & GoLiveSeeder — IdentitasUsahaSeeder menulis ulang panduan itu). Tanpa izin baru (Pengaturan Umum khusus Owner).

**Test**: `Tahap7/WarnaAplikasiTest` (8). Render tema Kopi di 8 halaman (dashboard, POS, pengaturan, 3 laporan, 404, login) + export PDF/Excel → 200, 0 sisa #FF6B00, semua script lolos `node --check`; simulasi Node grafik Laporan Transaksi (data salinan live) & form warna. Rollback migration diverifikasi.

**Deploy**: `git pull` → `https://<alamat-erp>/deploy-448.php?confirm=YES-WARNA448` (migrate + seeder + cache; mencetak warna aktif; lock `storage/deploy-448.lock`) → hapus `public/deploy-448.php`. Kedua instalasi.
### 4.47 🟢 POS: Panel Bayar Mode Tablet, Harga/Unit Terkunci (Izin Baru), Produk Sama Digabung (2026-11-07)

**Temuan test manual Owner di tablet (landscape)**:

1. **Panel bayar mode Tablet**: panel "Ringkasan & Pembayaran" ikut tergeser saat grid produk di-scroll. Fix (HANYA `body.pos-tablet-mode`, layar ≥768px; Desktop tidak berubah): kolom `#posKolomKiri`/`#posKolomKanan` selalu berdampingan (58/42%), `#panelBayar` sticky setinggi layar (flex column), `#panelBayarIsi` scroll sendiri, `#panelBayarKaki` (BARU — dipisah dari card-body, isi: Total `#kakiTotalNilai` yang menyalin `#totalBayarDisplay` lewat MutationObserver, PROSES ORDER, Save Bill, Lihat Riwayat) menempel di bawah. Tombol tetap di dalam `<form id="formPos">`.
2. **Harga/Unit terkunci**: izin BARU `order.ubah_harga` "Ubah Harga Jual di POS (kolom Harga/Unit)" (grup penjualan, `PosUbahHargaSeeder`, ADDITIVE). **Keputusan Owner: tidak dicentang untuk role mana pun** (Owner via Gate::before) → setelah deploy kasir tidak bisa lagi mengubah harga sampai Owner centang manual. Server (`PenjualanService::resolveItemRows`): tanpa izin, harga dari form DIABAIKAN → `hargaEfektifDiCabang()` (varian: `hargaEfektifDiCabang` varian, seperti dulu); dengan izin, harga dari form dipakai (termasuk varian — dulu harga varian selalu ditimpa server). Tanpa user login (mis. service dipanggil langsung) = dianggap tidak berizin. Form: `window.POS_BOLEH_UBAH_HARGA` → input `readonly` + ikon gembok. Tooltip `pos.harga_unit` di judul Item Order. Potongan harga tetap lewat kolom Diskon.
3. **Produk sama digabung**: `gabungKeBarisSama(item, variant)` — klik produk + varian sama yang sudah ada di Item Order dan **catatan item kosong** → qty +1 (kedip `.pos-item-tambah`), bukan baris baru; kalau baris punya catatan → baris baru (keputusan Owner). Isi keranjang dari PO tetap baris sendiri (`tambahkanItemKeForm(item, null, false)`). Ringkasan & struk ikut rapi karena diturunkan dari baris. Daftar di Ringkasan & Pembayaran sekarang seperti struk: nama, lalu "qty satuan x Rp harga satuan" + subtotal (`hitungTotal()`).

**Panduan**: `<!-- POS-447 -->` di slug `pos` (PosUbahHargaSeeder, terdaftar di DatabaseSeeder & GoLiveSeeder; izin di-spread ke PermissionSeeder).

**Test**: `Tahap7/PosTabletPanelBayarTest` (1), `PosUbahHargaDanGabungTest` (5) + simulasi Node logika gabung. `KembalianKasSinkronTest`/`BepLoyaltyExtendTest` memanggil service dgn harga beda dari harga produk → sekarang `actingAs(owner)` (perubahan disengaja).

**Catatan lokal**: setelah restart komputer MySQL XAMPP sempat gagal start (`mysql.roles_mapping` crashed), lalu aktif kembali.

**Deploy**: `git pull` → `https://<alamat-erp>/deploy-447.php?confirm=YES-POS447` (seeder + cache; mencetak role pemegang izin; lock `storage/deploy-447.lock`) → hapus `public/deploy-447.php`. Kedua instalasi.
### 4.46 🟢 Laporan Transaksi / Produk / Kategori di Menu Penjualan (Bisa Dibuka Kasir) (2026-11-05)

**Permintaan Owner**: 3 laporan di bagian sidebar **Penjualan** (bukan bagian Laporan) yang bisa dibuka kasir; **keuntungan tidak boleh terlihat kasir**. Laporan Penjualan lama (`laporan.penjualan`) tidak diubah.

**Izin** (`LaporanPenjualanKasirSeeder`, ADDITIVE insertOrIgnore — aman di production): `penjualan.laporan_transaksi.view`, `penjualan.laporan_produk.view`, `penjualan.laporan_kategori.view` → role yang sudah punya `order.view` (termasuk role buatan) + kasir/manajer_cabang/admin_pusat; `penjualan.laporan.keuntungan` → admin_pusat & manajer_cabang (Owner via Gate::before). Juga di-spread ke `PermissionSeeder`/`RolePermissionSeeder`, terdaftar di DatabaseSeeder & GoLiveSeeder. Data live: role Team belum punya `order.view` → menu belum muncul untuk Team (centang manual).

**Aturan keuntungan**: controller MEMBUANG kunci `hpp`/`keuntungan`/`margin` dari data (baris, rincian varian/produk, total) sebelum ke view/export kalau tidak berhak — bukan sekadar disembunyikan di HTML. Bagian JS grafik yang menyebut keuntungan dibungkus `@if($lihatUntung)`. Urut "keuntungan" ditolak untuk kasir. Test memeriksa marker `kotakKeuntungan`, `Untung</th>`, `label: 'Keuntungan'` (kata "Keuntungan" sendiri boleh muncul di teks panduan).

**Cabang**: `CabangAktif::untukLaporan()` (BARU) = cabang aktif header; role tanpa akses semua cabang yang sesinya menunjuk cabang bukan miliknya → dikembalikan ke cabangnya sendiri (bukan null/"Semua Cabang"; -1 kalau tidak punya cabang). **Temuan**: `CabangAktif::id()` sendiri tidak memvalidasi sesi (switcher yang memvalidasi) — sesi basi setelah penugasan cabang diubah masih bisa menunjuk cabang lama di menu lain; belum diubah global.

**1. Laporan Transaksi** (`penjualan.laporan.transaksi`, `LaporanTransaksiKasirService`): bertingkat Selama ini (per tahun) → Tahun (per bulan) → Bulan (per hari) → Hari (daftar transaksi + grafik per jam) → struk (`openStrukModal`, `PenjualanController::pastikanBolehLihat` ikut menerima izin laporan ini). Rentang tanggal `?dari&sampai`: < 92 hari per hari, lebih panjang per bulan; klik bulan → sub-rentang (dipotong ke rentang asal, `asal_dari/asal_sampai` dibawa untuk jejak & Kembali). Pendapatan = `orders.total_bayar`; keuntungan = pendapatan − Σ`order_items.hpp`. Periode belum terjadi tidak ditampilkan. Grafik batang/garis (localStorage), chip Tertinggi/Rata-rata.

**2. Laporan Produk** (`penjualan.laporan.produk`) & **3. Kategori** (`penjualan.laporan.kategori`), `LaporanBarangKasirService`: penjualan = `order_items.total_harga`; **diskon order dibagi per porsi harga** (`orders.diskon × item.total_harga / orders.total_harga`); pendapatan = penjualan − diskon. Service charge + take away fee = `biayaTambahan()` baris terpisah, sehingga **Σ pendapatan produk + biaya tambahan = pendapatan Laporan Transaksi** (diverifikasi data live semua cabang: selisih 0, juga untuk keuntungan). Produk dikelompokkan per **Kode Item** (bukan item_id — data barang per cabang 4.39). Varian dirinci kalau > 1 varian terjual. Kategori = penjumlahan `produk()` (selalu sama dengan Laporan Produk); "Tanpa kategori" = filter `kategori=-1`. Periode bersama `App\Support\PeriodeLaporan` (Hari ini/Bulan ini/Tahun ini/Selama ini/rentang, bawaan hari ini).

**Bahasa tanggal**: `APP_LOCALE=en` di `.env` → `translatedFormat()` keluar bahasa Inggris. Laporan ini memakai `LaporanTransaksiKasirService::tanggal()/labelRentang()` (nama hari/bulan Indonesia sendiri). Laporan lain yang memakai `translatedFormat()` kemungkinan tampil Inggris — belum diubah.

**Temuan data live**: ada produk jual berkategori "Bahan Baku"/"Kemasan" → ikut tampil sebagai kategori penjualan; order diskon hampir 100% (Rp 18.000 → Rp 1) ikut terbagi dengan benar.

**Export**: `LaporanTransaksiKasirExport`, `LaporanBarangKasirExport` (produk/kategori, baris rincian menjorok), PDF `transaksi-pdf`/`barang-pdf`. Panduan `laporan-transaksi-kasir`, `laporan-produk-kasir`, `laporan-kategori-kasir` + tooltip `laporan_transaksi_kasir.*`, `laporan_produk_kasir.*`, `laporan_kategori_kasir.*`.

**Test**: `Tahap7/LaporanTransaksiKasirTest` (17), `LaporanProdukKasirTest` (10), `LaporanKategoriKasirTest` (6). Regresi: hanya 10 kegagalan lama.

**Deploy**: `git pull` → `https://<alamat-erp>/deploy-446.php?confirm=YES-LAPORAN446` (seeder izin+panduan+tooltip, cache; mencetak role ber-izin; lock `storage/deploy-446.lock`) → hapus `public/deploy-446.php`. Kedua instalasi. Gladi lokal sukses, jalan kedua & konfirmasi salah ditolak.
### 4.45 🔴 Order "Dihapus" Masih Masuk Laporan — Batalkan vs Hapus (2026-11-04)

**Laporan Owner**: transaksi "sudah dibatalkan" masih masuk Laporan Laba Rugi. **Root cause (data live)**: kasir memakai tombol **Hapus** (🗑️ di daftar Riwayat), bukan Batalkan. Hapus (`CascadeDeleteService::deleteOrderCascade`) hanya soft-delete order + order_items + transaksi_keuangans — **stok tidak dikembalikan, saldo kas tidak dikurangi, status tetap selesai** — dan banyak laporan tetap menghitungnya karena memakai `DB::table` atau `Order::withoutGlobalScopes()` (yang ikut mematikan SoftDeletes). Data live: 32 order selesai terhapus (Rp 5,4 jt) di 7 lokasi. Bonus: bill tersimpan yang belum dibayar (`pending`) dihitung sebagai omzet di ~40 query.

**Fix**:
- `StatusOrder::bukanPenjualan()` = `[pending, dibatalkan]` + scope `Order::penjualanSah()` (`whereNull(orders.deleted_at)` + `whereNotIn(orders.status, …)`, kolom di-qualify). 40 query di 17 file diganti (Dashboard, Laporan Penjualan/Laba Rugi/Konsumsi Bahan/BEP/Jam Ramai/Eksekutif, Setoran Harian, Setoran Kasir, Laba Rugi Formal, Loyalty, Analisis). Query `DB::table` memakai `whereNotIn(...bukanPenjualan())->whereNull('orders.deleted_at')`. **Pengecualian sengaja**: `PenjualanController` `ordersGudang` (nota PO aktif boleh pending). **Laporan order baru WAJIB pakai `penjualanSah()`** (atau pola raw yang sama) — jangan `where('status','!=',Dibatalkan)` saja.
- `PenjualanController::destroy()`: hanya order `pending`/`dibatalkan` yang boleh dihapus (selain itu flash error "Batalkan dulu") + cek `CabangAktif::bolehAkses`. Riwayat: tombol **Batalkan** (modal bersama `#modalBatalkanList`, alasan dari partial `penjualan/_alasan-batal-fields.blade.php` yang juga dipakai Detail Order; non-Owner hanya order hari ini) untuk order Selesai; tombol Hapus hanya untuk Pending/Dibatalkan.
- Panduan `riwayat-order` bagian `<!-- BATAL-HAPUS-445 -->` (Perbaikan438PanduanSeeder).
- **Data lama tidak dikoreksi otomatis (keputusan Owner)**: Owner memulihkan manual di Data Terhapus → Orders lalu Batalkan (restore mengembalikan order/item/transaksi → Batalkan membalik stok & kas). `deploy-445.php` mencetak daftarnya.

**Test**: `Tahap7/PembatalanOrderLaporanTest` (11). Regresi: hanya 10 kegagalan lama. Cek data live lokal: Laba Rugi Batuphat 3 Nov omzet 2.105.000 → 2.042.000 (baris Best Seller Box Gyoza dari order terhapus 003-2026-00110 hilang).

**Deploy**: `git pull` → `https://<alamat-erp>/deploy-445.php?confirm=YES-BATAL445` (panduan + cache + daftar order terhapus; lock `storage/deploy-445.lock`) → hapus `public/deploy-445.php`. Kedua instalasi.

### 4.44 🟢 Porting dari erp-coffeeshop — Poin 4: POS Minta Pilih Cabang saat "Semua Cabang" (2026-11-03)

**Sumber**: `D:\xampp\htdocs\erp-coffeeshop\PORTING_KE_DIMSUM.md` (coffeeshop commit `c72a84c`+`fe81503`; folder coffeeshop READ-ONLY). Rangkaian porting: Poin 4 (ini) → Poin 3 Identitas Usaha → Poin 1 APK Kasir (sisi ERP) → Poin 2 Label (bawaan MATI per cabang). Kode ini dipasang di 2 DB: `azwc7142_erp_dmentai` (live) & `azwc7142_erp_dimsum` (demo) — deploy ke keduanya, hanya setelah Owner setuju.

**Bug (direproduksi)**: user role akses semua cabang TANPA cabang default membuka POS saat "Semua Cabang" → 500 `Item::tersediaDiCabang(null)`. Fix: `PenjualanController::pos()` menampilkan `penjualan/pilih-cabang.blade.php` (form ke `cabang.switch`) kalau cabang aktif kosong — **Gudang Pusat ikut jadi pilihan** (beda dari coffeeshop: POS gudang melayani PO cabang, 4.38 Tahap C). `tolakTanpaCabang()` di `store()` & `simpanBill()` (422 JSON / back+error). Owner yang PUNYA cabang default tetap seperti dulu (POS cabang default). Test `Tahap7/PosPilihCabangTest` (8).

**Poin 3 — Identitas Usaha (coffeeshop `aa0ca0c`, bagian PHP/view)**: `App\Support\Branding` (singleton, `View::share('brand')`, cache `branding:v1`, dihapus oleh `PengaturanUmumObserver` — observer TIDAK lagi menyalin logo ke `public/images/logo.png`, file itu ikut git). Kolom baru `pengaturan_umums.nama_singkat/tagline/telepon` (migration `2026_11_03_100001`; instalasi yang punya cabang diisi **"D'mentai" · "Dimsum & Gyoza"** = teks yang dulu ditulis langsung di view → tampilan tidak berubah setelah deploy; Owner bisa ganti untuk usaha lain). **JANGAN tulis nama/logo usaha langsung di view** — pakai `$brand->namaSingkat()/nama()/tagline()/subjudul()/footerStrukBaris()/logoUrl()/logoFile()/faviconUrl()/ikonUrl()`. Tempat yang dulu "D'mentai" memakai **nama singkat** (sidebar, judul tab, login, struk, pratinjau bill, PDF laporan, slip gaji, offline); TV Antrian memakai nama lengkap (seperti dulu). Logo: upload baru → `public/uploads/branding/logo-*.png` (+ikon 8 ukuran via GD, `.gitignore`); upload lama di disk `public` dilayani `/img/{path}` (aman tanpa symlink, 4.18); file hilang → logo bawaan. Favicon bawaan tetap `images/logo-icon.png`; manifest PWA hanya diganti ikonnya kalau ada ikon upload baru (middleware `TerapkanBranding`). SVG tidak diterima lagi. Panduan `pengaturan-umum` ditulis ulang + 3 tooltip di `IdentitasUsahaSeeder` (DatabaseSeeder & GoLiveSeeder). Belum: 4 panduan lain masih menyebut "D'mentai" di isinya (tidak mengganggu). Test `Tahap7/BrandingTest` (12), `ErrorPagesAndLogoSyncTest` disesuaikan.
**Poin 1 — APK Kasir, sisi ERP saja (coffeeshop `097a577`+`b5155eb`+route `/app-info` dari `aa0ca0c`)**: APK "Kasir POS" yang SAMA dengan coffeeshop dipasang di tablet (alamat ERP diketik di aplikasi; folder `android/` TIDAK di-porting). `GET /app-info` (publik, `app.info`) → `{erp:true} + Branding::untukAplikasi()` — **WAJIB ada**, APK menolak alamat tanpa endpoint ini. `components/apk-printer.blade.php` (`window.KDApk`: `ada/info/cetak/bukaLaci/bukaPengaturan/toast`, timeout 30 dtk) di-include di `layouts/app` untuk user login; menu sidebar `#kdApkSettingsLink` "Pengaturan Printer Aplikasi" hanya tampil di APK. Struk modal: `#strukApkGroup` + `strukCetakApk()` memakai `buildReceipt()` yang sama (buka laci kalau tunai), cetak otomatis kalau `info.auto_struk`; di APK UI Web Bluetooth/Cetak Browser disembunyikan. POS `bukaLaciManual()` lewat `KDApk.bukaLaci()` + tetap POST log laci. Di browser biasa tidak ada yang berubah. **Kontrak APK jangan diubah**: `window.KopiDripPrinter.info()/print(target, base64, cbId)/openDrawer(cbId)/openSettings()`, callback `window.KopiDripPrinterResult(id, ok, pesan)`, user-agent `KopiDripKasir/`, kunci JSON `/app-info`. QR Order & Layout Meja coffeeshop dilewati (tidak ada di Dimsum); kait label otomatis menyusul di Poin 2. Panduan `pos` + bagian `<!-- APK-KASIR-444 -->` (IdentitasUsahaSeeder). Test `Tahap7/ApkPrinterTest` (6) + simulasi Node KDApk.
**Poin 2 — Label Produk / stiker (coffeeshop `7d1abf2` bagian label + `0dea130` + `585ecb0`)**: nama kode SENGAJA sama dengan coffeeshop (`CupLabelService`, `CupLabelController`, kolom `cabangs.label_cup_*`, izin `label_cup.print`, route `label-cup.order`, `components/cup-label-overlay`, `window.openCupLabel`) supaya perbaikan berikutnya mudah di-porting; teks UI = "Label Produk". Migration `2026_11_03_100002`: `label_cup_aktif` **default false** (semua cabang mati → POS/struk/detail order tidak berubah), `label_cup_kategori` JSON (NULL = semua kategori yang punya produk jual — lihat `Cabang::kategoriLabelCup()`; disimpan eksplisit begitu form Cabang disimpan), ukuran default **40×30 mm**. Diatur di Cabang → Edit (kotak "Label Produk (stiker)"). 1 stiker = 1 qty (dibulatkan, min 1, maks 100). Isi stiker: nama singkat usaha, nomor antrean/nomor order pendek, ke/dari, nama, varian, catatan item, pelanggan, **Exp** (khusus Frozen dgn `tanggal_expired_frozen`), tipe·jam. Dimsum tidak punya Bill meja/QR Order/Layout Meja → label HANYA dari Order: tombol "Cetak Label (N stiker)" di jendela struk setelah bayar, otomatis di APK setelah struk (kalau `auto_label`, hanya transaksi baru di POS), dan cetak ulang di Detail Order. **Save Bill sengaja TIDAK mencetak label** (beda coffeeshop dine-in) — supaya tidak dobel saat bill dibayar. Kolom catatan per item di POS hanya tampil kalau label aktif di cabang itu (`window.POS_LABEL_AKTIF`); `items.*.catatan` divalidasi di `OrderRequest` & `simpanBill`. Browser: pilihan bahasa printer bawaan **ESC/POS** (Blueprint ECO80BT), TSPL tetap ada; di APK bahasa diatur di aplikasi. Izin + panduan `label-produk` + 3 tooltip `cabang.label_cup_*` di `LabelProdukSeeder` (ADDITIVE: izin dicentang untuk semua role yang sudah punya `order.create`, termasuk role buatan; terdaftar di DatabaseSeeder & GoLiveSeeder; `PermissionSeeder`/`RolePermissionSeeder` juga memuatnya utk instalasi baru). Closing Periode coffeeshop TIDAK di-porting (Dimsum punya 4.37). Test `Tahap7/CupLabelTest` (9) + simulasi Node builder ESC/POS & TSPL 40 mm.

**Deploy 4.44 (belum dijalankan — tunggu persetujuan Owner)**: backup DB → `git pull` → `https://<alamat-erp>/deploy-444.php?confirm=YES-PORTING444` (migrate 2 migration + IdentitasUsahaSeeder + LabelProdukSeeder + cache; mencetak identitas usaha & role ber-izin label; lock `storage/deploy-444.lock`) → hapus `public/deploy-444.php`. Jalankan di **kedua** instalasi (D'mentai live DB `azwc7142_erp_dmentai`, demo DB `azwc7142_erp_dimsum`). Gladi di salinan DB dev: sukses, konfirmasi salah & jalan kedua ditolak. Setelah deploy: cek logo di sidebar/login (sekarang membaca logo upload lama via `/img/`), lalu di tablet pasang APK "Kasir POS" → Pengaturan → alamat ERP → Hubungkan → pilih printer struk (IWARE C58BT) & label (Blueprint ECO80BT) → Tes Cetak.

### 4.43 🔴 Rapikan Role & Hak Akses — Setiap Menu Punya Centangan Sendiri & Centangan Selalu Berlaku (2026-11-02)

**Laporan Owner**: Laporan Stok tidak bisa dicentang untuk Kasir. Audit 86 menu sidebar menemukan 4 masalah: (1) seluruh bagian **Laporan** di sidebar terkunci izin `laporan.view` ("Lihat Laporan", tercantum di kelompok Keuangan) — Kasir yang punya 8 izin laporan tidak melihat satu pun; 11 laporan tidak punya izin sendiri (cuma `laporan.view` + izin modul), jadi mencentang "Lihat Laporan" untuk Kasir ikut membuka Laporan Keuangan/Saldo Kas/Audit Bukti; (2) ±15 menu dikunci `role:` di route tapi menunya tampil berdasar centangan → role Team dll melihat menu lalu 403; (3) POS/Riwayat Penjualan/detail order tanpa izin sama sekali (siapa pun login bisa transaksi / membuka order cabang lain lewat URL); (4) 11 centangan tidak dipakai halaman mana pun. Bonus: bagian sidebar Penjualan/Stok/Keuangan menyembunyikan menu (Pelanggan, Jenis Menu, Bahan Baku, Produk Jual, Setoran Kasir, dst) kalau role tidak punya izin "induk" bagian itu.

**Pusat aturan**: `App\Support\HakAksesMenu` — `IZIN_BARU`, `WARISAN`, `LABEL`, `TIDAK_DIPAKAI`, `IZIN_LAPORAN` (dipakai `@canany` bagian Laporan sidebar), `terapkan()` (buat izin + label + centang warisan, `insertOrIgnore`), `lupakanCache()`. **Pewarisan** = izin baru diberikan ke role yang DULU bisa melihat menu sekaligus membukanya (laporan: `laporan.view` + izin modul sidebar; menu role-locked: role sistem di daftar `role:` lama + izin yang dipakai sidebar) → tidak ada yang kehilangan akses yang benar-benar dipakai; role yang cuma melihat menu tapi selalu 403 TIDAK diwarisi. Migration `2026_11_02_100001` menjalankan `terapkan()` sekali & mencatat semua perubahan di `hak_akses_migrasi_logs` (izin/centang/label) → `down()` hanya membatalkan perubahan itu (rollback diverifikasi: hash role_permissions + label identik). Instalasi baru: `HakAksesMenuSeeder` (setelah `RolePermissionSeeder` di DatabaseSeeder & GoLiveSeeder).

**Tahap 1 — Laporan**: 13 izin baru grup `laporan` (`laporan.penjualan/stok/keuangan/transfer_dana/kategori/audit_bukti/saldo_kas/hr_absensi/hr_penggajian/hr_evaluasi/aset/bep/cabang_vs_cabang.view`) menggantikan cek `laporan.view`/izin modul di 11 controller + `KeuanganController::laporan`. Cabang vs Cabang tetap juga butuh `canAccessAllBranches()`. Laporan Absensi (route) dijaga `laporan_absensi`. Tombol pindah antar laporan HR & link Laporan BEP ikut izin. `laporan.view` → "(lama — tidak dipakai lagi)".

**Tahap 2 — role-locked → centangan**: Cabang (`cabang.view/create/edit/delete` per aksi), User (`user.*` per aksi), Supplier (BARU `supplier.view`/`supplier.manage`, sebelumnya tampil untuk siapa pun ber-`pembelian.view`), Semua Barang (`item.view`), Jenis Menu (`jenis-olahan.view`), Shift (`kelola_shift`), Absensi Manual + Rekap + Log Wajah (`absensi.manage` — BUKAN `absensi.view` yang dimiliki Kasir/Helper), Dashboard Absensi (`dashboard_absensi`), hapus absensi/log (`hapus_absensi`/`hapus_log_absensi`), Device Absensi (BARU `absensi.device`), Registrasi Wajah (`registrasi_wajah`), Hari Libur (`kelola_hari_libur`), Penggajian (`penggajian.view` lihat / `penggajian.manage` ubah), Evaluasi (`evaluasi.view` daftar+ranking+detail / `evaluasi.manage` kelola), Pengaturan Penggajian (`pengaturan_penggajian`), Panduan & Tooltip admin (BARU `panduan.kelola`). **Tetap khusus Owner**: Role & Hak Akses, Pengaturan Umum (izin `pengaturan.umum_*` diberi label "khusus Owner"). FormRequest BahanBaku/ProdukJual/StockTransfer/Panduan/Tooltip/User sekarang murni izin (dulu daftar nama role). Tombol di halaman (Tambah/Edit/Hapus/Toggle) dibungkus `@can` sesuai izin aksinya; tombol Transfer/Permintaan Stok, Cuti approve, Evaluasi, "Beli" di Stok Minimum dulu cek nama role → sekarang izin. Tambah Kategori (`item.kategori.store`, dipakai form Bahan Baku & Produk Jual) boleh untuk pengelola Bahan Baku/Produk Jual (dulu role-locked + `item.create`).

**Keamanan menu User (bug lama ikut difix)**: Manajer Cabang dulu bisa membuat user ber-role **Owner**. Sekarang: role Owner hanya bisa diberikan & akun Owner hanya bisa diubah/dinonaktifkan/dihapus/di-reset oleh Owner (`UserController::pastikanBolehKelola()`, `UserRequest` `Roles::values($isOwner)`); role tanpa akses semua cabang hanya mengelola user yang berbagi cabang & hanya menugaskan ke cabangnya sendiri (cek `RoleUser::ManajerCabang` diganti `!canAccessAllBranches()`).

**Tahap 3 — POS**: grup route `can:order.create` (POS, simpan/charge/preview bill, store, cek-pengganti), Riwayat `can:order.view`. Detail order & struk (dibuka juga dari Antrian/Dashboard Cabang/Kas) → `PenjualanController::pastikanBolehLihat()`: salah satu `order.view/order.create/antrian.lihat/antrian.kelola/keuangan.view` + `CabangAktif::bolehAkses(order->cabang_id)`.

**Tahap 4**: 10 izin tidak dipakai (`coa.manage`, `export_audit_log`, `item.delete`, `lihat_shift`, `master.item_varian.*`, `pemakaian_perlengkapan.delete/export`) diberi akhiran "(tidak dipakai — centangan tidak berpengaruh)" — TIDAK dihapus. Halaman Role menampilkan centangan berlabel itu redup (`text-muted fst-italic`).

**Tahap 5**: panduan `role-hak-akses` — kalimat batasan lama diganti (`gantiTeks()`, juga sumber `panduanRole()` utk instalasi baru) + bagian `<!-- HAK-AKSES-443 -->`; tooltip `role.centangan_laporan` ditempel di judul kelompok Laporan halaman Role. Semua di `Perbaikan438PanduanSeeder`.

**Bug lama ditemukan saat uji data live**: Pemakaian Perlengkapan error 500 untuk semua user non-semua-cabang (`$user->cabangs()->aktif()->pluck('id')` ambigu) → `pluck('cabangs.id')`.

**PENTING Gate**: permission didaftarkan sebagai Gate dari cache `all_permission_names` (1 jam). Izin baru BELUM berlaku sampai cache itu dibuang — migration memanggil `lupakanCache()`, deploy menjalankan `cache:clear`.

**Test**: `HakAksesLaporanTest` (9), `HakAksesMenuRoleTest` (15), `HakAksesPosTest` (4), `HakAksesPanduanTest` (3); `RoleBuatanTahapDTest` diubah (Cabang sekarang ikut centangan — perubahan disengaja). Uji data live (salinan production, rollback): user nyata per role (Owner, Team, Admin Pusat, Kasir, Admin Gudang) + user sementara (Manajer Cabang, Operator Produksi, Helper) membuka SEMUA link sidebar-nya → 0 error setelah fix Pemakaian Perlengkapan. Regresi: hanya kegagalan lama [[4.37]].

**Deploy**: `git pull` → `https://erp.dmentaiindonesia.com/deploy-443.php?confirm=YES-HAKAKSES443` (migrate + panduan + cache; mencetak centangan warisan per role; lock `storage/deploy-443.lock`) → hapus `public/deploy-443.php`. Gladi di salinan live: hasil warisan admin_gudang (Kelola Absensi, Supplier, 8 laporan), admin_pusat (Device Absensi, Kelola Panduan & Tooltip), manajer_cabang (Device Absensi, 12 laporan).
### 4.42 🟢 Ambil Produk Jual dari Cabang Lain + "Buat juga di cabang" (2026-10-30)

**Permintaan Owner**: pelanggan tidak mau input produk jual berulang di tiap cabang, TAPI data tetap terpisah per cabang ([[4.39]]). Solusi: produk **disalin** jadi milik cabang tujuan; komposisi menunjuk **bahan milik cabang tujuan** (stok dipotong dari cabang itu). Tanpa sinkron otomatis setelah disalin.

**Mesin** `App\Services\AmbilProdukCabangService` — `rencana(sumber, produkIds, tujuan)` (READ, tidak mengubah data) & `jalankan(..., produkDipilih, keputusanBahan, keputusanBumbu)` (1 transaksi). Pencocokan bahan & Bumbu Pusat (aturan dari Owner): kode sama + nama sama → **cocok**; kode tidak ada tapi nama sama → **nama_sama** (bawaan pakai yg bernama sama); kode sama nama beda → **kode_sama** (WAJIB dipilih, `ValidationException` kalau kosong); tidak ada → **baru** (salinan bahan stok 0, kode unik `KODE-2` kalau bentrok); bahan data lama `cabang_id NULL` → **umum** (dipakai apa adanya). Perbandingan tanpa beda huruf besar/kecil & spasi berlebih. Pilihan id divalidasi milik cabang tujuan. Produk: kode sudah ada (termasuk di Data Terhapus) → dilewati; nama sama kode beda → bawaan tidak dicentang. Yang disalin: data produk, foto (**file disalin sendiri** — supaya ganti/hapus foto 1 cabang tidak menghilangkan foto cabang lain), atribut/nilai/varian, resep, Bumbu Pusat (baru atau pakai yang ada). Stok & transaksi tidak.

**UI**: tombol **"Ambil dari Cabang Lain"** di daftar Produk Jual → `master.produk-jual.ambil.index` (pilih sumber, centang produk; mode Semua Cabang pilih tujuan dulu) → `ambil.periksa` (GET, layar pemeriksaan per cabang tujuan: Produk / Bumbu Pusat / Bahan dgn dropdown "buat baru" atau bahan milik tujuan) → `ambil.simpan` (POST). Form Tambah Produk Jual: card **"Buat juga di cabang"** (cabang `CabangAktif::pilihan()` selain pemilik, hanya create) → setelah simpan redirect ke layar pemeriksaan dgn `tujuan[]`. Controller `AmbilProdukCabangController` (`bacaPermintaan()` memaksa tujuan ∈ pilihan + `bolehAkses`, 403 kalau bukan). Permission reuse `master.produk_jual.create` (sumber boleh cabang mana pun — keputusan Owner). Tanpa migration.

**Panduan/tooltip**: panduan BARU slug `ambil-produk-cabang` (langkah lengkap + tabel status) + ringkasan pendek `<!-- AMBIL-PRODUK-442 -->` di slug `produk-jual` + 4 tooltip `ambil_produk.*`, semua di `Perbaikan438PanduanSeeder`; tombol Cara Pakai (`ambil-produk-cabang`) di 2 halaman baru. Isi lengkap SENGAJA tidak ditempel ke panduan `produk-jual` — guard `PanduanTooltipBackfillTest` membatasi 1.200 kata dan panduan itu sudah ~1.080 kata di DB test.

**Test**: `AmbilProdukCabangServiceTest` (9), `AmbilProdukCabangHttpTest` (8), `BuatJugaDiCabangTest` (5). Uji data dev (rollback): 22 produk tersalin, 7 dilewati, 20 bahan baru, 0 baris resep menunjuk cabang lain.

**Lanjutan yang disarankan (belum dikerjakan)**: pemeriksaan kode+nama yang sama di penerimaan PO dari Gudang (kasus kode produk gudang "DM 57" ≠ bahan outlet "DM 001", [[4.39]] Fase 3).

**Deploy**: `git pull` → `https://erp.dmentaiindonesia.com/deploy-442.php?confirm=YES-AMBIL442` (seeder panduan + cache, lock `storage/deploy-442.lock`) → hapus `public/deploy-442.php`.
### 4.41 🟢 POS: Pilihan Tipe Transaksi per Cabang + Tombol Uang Pas (2026-10-22)

**Permintaan Owner**: (1) pilihan Dine-in/Takeaway/Frozen di POS hanya tampil kalau diaktifkan di pengaturan cabang; (2) tombol "uang pas" = nominal belanja agar ngasir cepat. Saklar per tipe (`dine_in_aktif`/`takeaway_aktif`/`frozen_aktif`) SUDAH ada sejak Tahap 3, tapi kasir tetap wajib klik tipe walau cuma 1 aktif, dan kalau semua dimatikan transaksi tidak bisa disimpan.

**Yang dibuat** (migration `2026_10_22_100001`):
- `cabangs.pilih_tipe_transaksi` (default **true** = perilaku lama) + `cabangs.tipe_transaksi_default` (default `takeaway`) — saklar "Tampilkan pilihan Tipe Transaksi di POS" + dropdown "Tipe bawaan" di Config POS (form tambah & edit cabang).
- `Cabang::tipeTransaksiOtomatis()`: pilihan disembunyikan → tipe bawaan; hanya 1 tipe aktif → tipe itu; selain itu null. POS: kartu Tipe Transaksi hanya tampil kalau pilihan aktif (mode sembunyi: teks "Tipe transaksi: … (otomatis)"), hidden input `tipe_transaksi` terisi tipe otomatis + `pilihTipeTransaksi()` dipanggil saat load (nomor meja & takeaway fee ikut jalan). Server fallback kalau `tipe_transaksi` kosong: `OrderRequest::prepareForValidation()` & `PenjualanController::simpanBill()`.
- **Uang Pas**: tombol nominal tetap (50/100/150/200rb) diganti `#nominalCepat` yang dirender `renderNominalCepat(total)` tiap `hitungTotal()` — tombol hijau "Uang Pas · Rp X" (= total) + maks 3 pembulatan ke atas (5rb/10rb/50rb/100rb).
- Bug kecil ikut difix: `CabangController::update()` mengakses `$data['kepala_cabang_id']` tanpa cek (500 kalau field tidak terkirim).
- Panduan `<!-- POS-441 -->` (pos, cabang) + tooltip `cabang.pilih_tipe_transaksi` di `Perbaikan438PanduanSeeder`. Test `TipeTransaksiUangPasTest` (5).

**Deploy**: SELESAI 2026-10-22 di DB `azwc7142_erp_dmentai` (via `public/deploy-441.php`, sudah dihapus dari repository; ambil dari riwayat git kalau instalasi lain perlu). Mencakup juga 4.40.
### 4.40 🟢 POS "Stok tersedia" dari Bahan Resep (2026-10-19)

**Laporan Owner** (setelah deploy 4.39): di baris item order POS tertulis "Stok tersedia: 0" padahal produk bisa diklik/dijual. **Bug lama** (sejak initial commit, angkanya selalu 0 sejak 4.34 produk jual tidak menyimpan stok sendiri): tulisan membaca stok produk itu sendiri, sedangkan tanda "Stok Habis" dihitung dari bahan resep.

**Fix**: `App\Services\KetersediaanProdukService` — rumus SAMA dgn pengecekan saat Bayar (`PenjualanService::cekResepCukup` → `ResepBumbuItem::expandKeBahanMentah`): produk ber-resep = jumlah yang bisa dibuat dari bahan paling sedikit di outlet (stok ÷ kebutuhan per unit, **termasuk bahan di dalam Bumbu Pusat**), satuan = satuan produk, "(terbatas: nama bahan)" kalau resep > 1 bahan, oranye kalau ≤ 5; produk tanpa resep yg dipotong stok sendiri = stoknya; tambahan tanpa resep = tidak dibatasi. Dipakai POS (tulisan di baris order, dropdown "- Stok: …", tanda Stok Habis) & `Item::bisaDijualDiCabang()` (dulu mengabaikan bahan Bumbu Pusat → produk terlihat bisa dijual lalu ditolak saat Bayar). Stok bahan 1 query per buka POS. Tidak ada perubahan proses Bayar/potong stok, tanpa migration. Test `StokTersediaPosTest` (5).

**Deploy**: `git pull` → buka `clear-cache.php` → hapus file itu. (`public/deploy-perbaikan438.php` sudah dihapus dari repository — deploy 4.38/4.39 selesai 2026-10-19 di DB `azwc7142_erp_dmentai`; pemecahan juga terjalankan di DB `azwc7142_erp_dimsum` — instalasi lain, menunggu konfirmasi Owner.)
### 4.39 🔴 Data Barang Terpisah per Cabang — Mengikuti Pilihan Cabang di Header (2026-10-19)

**Keputusan Owner** (setelah Tahap E tab per outlet & Tahap F halaman "Harga Outlet" ditolak karena membingungkan orang awam): bahan baku, produk jual, resep, dan Bumbu Pusat **terpisah total per cabang**. Pilihan cabang di switcher pojok kanan atas menentukan data yang tampil di SEMUA menu; "Semua Cabang" (owner/admin pusat) = katalog lengkap + nama cabang.

**Fase 1 — Fondasi & pemecahan data** (migration `2026_10_19_100001`):
- `items.cabang_id` & `resep_bumbu.cabang_id` (pemilik; NULL = data lama belum dipecah → `scopeMilikCabang()` tetap menampilkannya di semua cabang sbg pengaman). Kode unik **per cabang**: `unique(cabang_id, kode_item)` / `unique(cabang_id, kode)`; validasi kode ikut mengecek barang lama tanpa cabang. `down()`: lepas FK dulu baru index (MySQL menolak drop index yang dipakai FK).
- `PemecahanDataCabangService` + `php artisan erp:pecah-cabang {--simulasi} {--batalkan}`: tiap barang lama disalin ke tiap cabang yang **memakainya** (stok/batch/pergerakan/order/PO/transfer/permintaan/pemakaian, item_cabang aktif utk produk POS atau harga beli outlet utk bahan, produk POS tanpa item_cabang = semua cabang, dipakai resep/Bumbu Pusat cabang itu; tidak dipakai siapa pun → Gudang Pusat). Baris asli = milik cabang utama (Gudang kalau ikut memakai), cabang lain dapat salinan (kode sama, harga outlet lama → harga barang salinan, varian ikut disalin). Stok, batch, riwayat (order, PO, transfer, permintaan, pemakaian, item_cabang, pergerakan dgn `COALESCE(asal, tujuan)`) diarahkan ke salinan cabangnya; resep & Bumbu Pusat ikut dipecah dan isinya menunjuk bahan cabang sendiri. Semua dicatat di `pemecahan_cabang_maps` → `batalkan()` mengembalikan persis. Diverifikasi di data dev (71 → 147 barang): total stok, nilai FIFO, order, pergerakan, PO per lokasi identik sebelum/sesudah/setelah batal.

**Fase 2 — Semua menu ikut cabang aktif** (`App\Support\CabangAktif`: `id()`, `bolehAkses()`, `pemilikBaru()` — mode Semua Cabang: pilih di form, default Gudang Pusat):
- Bahan Baku, Produk Jual, Master Bumbu Pusat, Semua Barang, POS, PO (daftar & form ikut Lokasi Tujuan), Stok/Kartu/Adjustment, Transfer, Permintaan, Pemakaian Perlengkapan. Barang baru otomatis milik cabang aktif; manajer cabang hanya cabangnya (403). Guard server: POS/PO/adjustment/pemakaian menolak barang cabang lain.
- `Item::tersediaDiCabang/hargaEfektifDiCabang/hargaBeliDiCabang`: barang milik cabang SELALU pakai harga/status miliknya sendiri (item_cabang hanya utk data lama — mencegah harga outlet lama mengalahkan harga yang baru diedit).
- **Harga resep FIFO** (`HargaResepService`): batch tertua yang masih bersisa di cabang pemilik → stok habis: harga PO terakhir (merah) → belum pernah PO: Harga Standar. Dipakai form Produk Jual (kolom "Harga Bahan" + info "FIFO · batch dd/mm (sisa X)"), kalkulator, preview Bumbu Pusat, picker Import Bumbu Pusat, halaman edit Bumbu Pusat.
- **Kolom Wajib & Mode Harga dihapus** dari UI resep Produk Jual & Bumbu Pusat — POS tidak pernah membacanya; dulu bahan "gratis" (default, semua data) tidak dihitung ke HPP preview sehingga HPP terlihat lebih kecil dari modal riil. Sekarang semua bahan selalu dihitung; kolom DB dibiarkan (disimpan `pakai_master`/wajib).
- Halaman "Harga Outlet", kotak kuning, `OutletTerpilih`, endpoint `hpp-outlet`, `syncCabang`/`syncHargaCabang` dihapus.
- **Bug ditemukan & difix**: daftar/simpan PO membaca kunci sesi salah `cabang_aktif_id` (selalu jatuh ke cabang default); kode resep `RESEP-{kode_item}` > 20 karakter → error 500 saat simpan produk (sekarang `RESEP-{id}` kalau kepanjangan).

**Fase 3 — Alur antar cabang lewat Kode Item** (`PadananBarangService::untukCabang()/cari()`): terima transfer → stok masuk ke barang milik cabang tujuan (dibuat kalau belum ada; modal FIFO tetap); PO ke supplier internal → produk gudang dicatat sbg barang milik cabang pemesan (produk jual gudang jadi `bahan_baku` di cabang), POS gudang "Isi Keranjang" memakai produk gudang; transfer dari Permintaan Stok memakai padanan barang gudang. Guard: transfer hanya barang milik lokasi asal, permintaan hanya barang milik peminta.

**Fase 4**: **FIFO Batch Detail** di form edit Bahan Baku (`<x-fifo-batch-detail>`, batch qty_sisa > 0 di cabang pemilik, ↑ NEXT, total nilai; kosong → info harga PO terakhir). Tambah Cabang "Salin Harga & Pengaturan Barang dari" → `SalinDataCabangService` menyalin semua bahan, produk (+varian), resep, Bumbu Pusat, Qty Minimum (stok tidak).

**Ganti cabang dari header** (`CabangSwitcherController::halamanSetelahGanti()`, temuan test lokal Owner): halaman edit/detail/tambah (route ber-parameter atau `*.create/*.edit/*.show`) → kembali ke daftar menu itu (cari `{prefix}.index`/`{prefix}`, lalu cadangan lewat alamat mis. `/evaluasi/5/detail` → `/evaluasi`, terakhir Dashboard); halaman daftar/laporan → halaman sama tanpa query string (filter barang/lokasi cabang lama dibuang); Profil dikecualikan. Berlaku otomatis utk semua menu (~40 halaman satu-data). `<x-peringatan-beda-cabang>` di edit Bahan Baku/Produk Jual/Bumbu Pusat saat data milik cabang lain dibuka dari cabang aktif berbeda. Test `GantiCabangKembaliKeDaftarTest` (5).

**Fase 5**: panduan `<!-- PEMISAHAN-CABANG-439 -->` (bahan-baku, produk-jual, resep-bumbu, cabang, pembelian, transfer-stok, permintaan-stok, pos, stok-barang, laporan-stok, master-barang) + 4 tooltip di `Perbaikan438PanduanSeeder` (teks Tahap A soal harga per outlet dibersihkan — belum pernah dirilis).

**Test**: `PemecahanDataCabangTest` (5), `DataPerCabangFase2Test` (10), `AlurAntarCabangFase3Test` (6), `FifoDanSalinCabangFase4Test` (3), `StokMinimumDanSalinCabangTest` (5, sisa Tahap E/F yang masih berlaku + panduan). Test lama yang menguji perilaku yang sengaja diubah disesuaikan (Mode Harga, label kolom, item_cabang outlet). Regresi: hanya 11 kegagalan pre-existing ([[4.37]]).

**Deploy production (4.38 + 4.39 sekaligus)** — `git pull` lalu:
1. `https://erp.dmentaiindonesia.com/deploy-perbaikan438.php?confirm=YES-PERBAIKAN&tahap=simulasi` → migrate + tampilkan rencana pemecahan (data barang belum diubah, boleh diulang). Periksa daftarnya.
2. **Backup database** (cPanel → phpMyAdmin → Export).
3. `...?confirm=YES-PERBAIKAN&tahap=jalankan` → pecah data + panduan + cache + lock.
4. Hapus `public/deploy-perbaikan438.php`. Lalu: buat supplier "Gudang Pusat" (Supplier Internal = Gudang Pusat), cek saklar wajib bukti tiap cabang, cek harga barang tiap cabang (pilih cabang di pojok kanan atas).
Kalau perlu membatalkan pemecahan: `php artisan erp:pecah-cabang --batalkan` (atau rollback migration `2026_10_19_100001`, yang otomatis membatalkan dulu). Gladi lengkap sudah dijalankan di salinan DB dev (simulasi → jalankan → lock menolak ulang).
### 4.37 🟢 Closing / Tutup Buku — Snapshot + Kunci Periode, Harian/Mingguan/Bulanan, Per Cabang & Keseluruhan (2026-10-17)

**Latar belakang (audit)**: laporan periode lama bisa berubah karena (a) belum ada closing, (b) transaksi keuangan & order lama masih bisa diedit/dihapus/dibatalkan (pembatalan order soft-delete `TransaksiKeuangan`-nya), (c) `kas` cuma simpan `saldo_sekarang`, (d) stok cuma simpan qty sekarang. `order_items.hpp` sudah snapshot permanen. Keputusan Owner: **snapshot + kunci periode**, bukan snapshot saja (snapshot tanpa kunci → 2 angka beda yang membingungkan).

**Multi-instalasi**: aplikasi yang sama dipasang terpisah per usaha (DB berbeda) — ada usaha 1 cabang TANPA Gudang Pusat (setoran = Transfer Antar Kas) dan usaha pusat + 5 cabang. Closing TIDAK bergantung pada adanya pusat: klasifikasi murni dari kode kategori transaksi. **Temuan terbuka (belum diputuskan Owner, TIDAK diubah)**: `SetoranKasirService::approveSetoran()` mewajibkan Gudang Pusat (gagal di usaha 1 cabang).

**Isi laporan (`ClosingService::hitung()`, murni READ)**: Penjualan (orders status ≠ pending/dibatalkan; per metode via `order_payments`, fallback `tipe_pembayaran`; per tipe transaksi), Laba Rugi (omzet bersih − HPP − beban; transaksi ber-referensi order TIDAK dihitung ulang; PBB/ASET/SALDO/RETUR-BELI dikecualikan → tidak dobel dgn HPP), Arus Kas (hanya `kas_id` terisi, mutasi dikecualikan), Saldo Kas per kas (dihitung MUNDUR: `saldo_sekarang` − net transaksi sesudah tanggal akhir; SETOR-IN `menunggu_diterima` tidak dihitung), Mutasi Internal (MUTASI-*/SETORKSR-*/SETOR-* — ringkasan per jenis + rincian per pasangan dedup, TERMASUK antar kas di dalam 1 cabang; di keseluruhan saling meniadakan), Stok (nilai persediaan SAAT INI, rumus = `NeracaService`), Setoran (status Setoran Kasir + hari belum setor, hanya kalau ada Gudang Pusat), Ringkasan per Cabang (keseluruhan). Kategori anak ikut kode induk khusus; transaksi lama tanpa `kategori_id` dipetakan dari enum.

**Periode**: `pengaturan_umums.tanggal_mulai_periode` (1–28, default 1 = bulan kalender, diatur di Pengaturan Umum). Periode dinamai bulan tempat periode BERAKHIR: tgl 26 → "Oktober" = 26 Sep–25 Okt. Mingguan = Senin–Minggu, rekap dari closing harian (tanpa kunci terpisah).

**Simpan & kunci (`ClosingArsipService`)**: tabel `closings` (1 baris = 1 versi; `data` JSON = hasil `hitung()` beku; status `final`/`dibuka`; `cabang_id` NULL = keseluruhan) + `closing_kas` (saldo per kas + `saldo_fisik`/`selisih`).
- **Closing Harian** (kasir/manajer, opsional — TIDAK memblokir POS): 1 hari 1 cabang, tidak boleh tanggal yang akan datang, wajib isi uang fisik tiap kas tunai aktif.
- **Tutup Buku Bulanan**: periode harus sudah berakhir (boleh hari terakhirnya), tidak boleh tumpang tindih.
- **Tutup Buku Keseluruhan**: hanya setelah SEMUA cabang aktif tutup buku bulanan periode yang sama; tidak mengunci apa pun sendiri.
- **Buka Kunci** (Owner-only, alasan min 10 karakter): status → dibuka, arsip tetap; closing ulang = versi baru. Buka bulanan ikut membuka harian di dalamnya + keseluruhan periode itu; harian tidak bisa dibuka sendiri selama bulanan masih mengunci.

**Penjaga kunci (`App\Support\ClosingLock` + trait `App\Traits\DijagaClosing`)**: dipasang di model `TransaksiKeuangan` & `Order` (event saving/deleting/restoring) → berlaku dari menu mana pun + proses otomatis. Order hanya menjaga kolom angka (status, total, diskon, tanggal, cabang, kas, tipe); TransaksiKeuangan menjaga semua kecuali status alur Transfer Dana, link pasangan, catatan/bukti, `kategori_pengeluaran`. Cek tanggal lama & baru (pindah tanggal ke periode terkunci ditolak). `PeriodeTerkunciException` dirender di `bootstrap/app.php` → redirect back + flash error (JSON 422 utk AJAX), tidak dilaporkan ke log. **Tanpa cache statis** (DatabaseTransactions di test tidak memicu event). Penyesuaian alur existing: (1) `SetoranKasirService::approveSetoran()` — kalau tanggal setoran terkunci (cabang/pusat), transaksi dicatat di tanggal approve; (2) recurring on-request di `AppServiceProvider` — try/catch per item. Delete massal via query builder tidak memicu event; alur yang memakainya (batal order) selalu juga mengubah `Order` dalam transaction yang sama → ikut di-rollback.

**UI**: menu Keuangan → "Closing / Tutup Buku" (`GET /keuangan/closing`): preview + kotak status (terkunci/preview) + tombol Closing Harian (modal uang fisik) / Tutup Buku Bulanan / Keseluruhan + rekap mingguan. `closing.riwayat`, `closing.show` (arsip + versi lain + Buka Kunci), export Excel (`LaporanClosingExport`, multi-sheet) & PDF (`closing/pdf.blade.php`) untuk preview (`closing.export`) & arsip (`closing.show.export`). Tampilan laporan bersama di `closing/_laporan.blade.php`. Komponen `<x-closing-pengingat>` di Dashboard Pusat/Cabang: hari ber-penjualan 7 hari terakhir yang belum closing (hanya user ber-`closing.harian`).

**Permission**: `closing.view/harian/bulanan/konsolidasi/buka_kunci/export` — admin_pusat: view/harian/bulanan/konsolidasi/export; manajer_cabang: view/harian/export; kasir: view/harian; `buka_kunci` Owner-only. Didefinisikan di `ClosingPermissionSeeder::PERMISSIONS/ROLE_DEFAULTS` dan di-spread ke `PermissionSeeder`/`RolePermissionSeeder`. **`ClosingPermissionSeeder` = versi ADDITIVE (insertOrIgnore) utk production — JANGAN jalankan `RolePermissionSeeder` di production** (delete-lalu-insert per role, menghapus kustomisasi Owner). Filter cabang manual: role tanpa akses semua cabang dipaksa ke cabangnya; "Semua Cabang" hanya `canAccessAllBranches()` + `closing.konsolidasi`. Panduan slug `closing` + 11 tooltip di `ClosingPanduanSeeder` (terdaftar di DatabaseSeeder & GoLiveSeeder).

**⚠️ Database test**: test bawaan Breeze (`tests/Feature/Auth/*`, `ExampleTest`, `ProfileTest`) memakai `RefreshDatabase` → **mengosongkan `erp_dimsum_test`**. Regresi WAJIB per folder: `php artisan test tests/Feature/Tahap25 tests/Feature/Tahap5 tests/Feature/Tahap6 tests/Feature/Tahap7`. Kalau DB test terlanjur kosong: jalankan seeder `DatabaseSeeder` satu per satu MINUS `UserSeeder` (bug lama) + ChartOfAccounts/KategoriCoaBackfill/Keamanan/Absensi/AntrianPermission, lalu `APP_ENV=testing php artisan cache:clear`. **11 test gagal pre-existing** (bukan dari closing, diverifikasi via git stash): 6 test masih mengharapkan form Master Barang Lengkap (sudah read-only sejak [[4.34]]) — ItemLegacyRoute ×3, KodeKategoriWajib ×2, RenameJenisMenu ×1; 5 test bergantung data seeder (varian ×4, BEP jasa giling ×1).

**Verifikasi**: `ClosingPreviewTest` (13), `ClosingKunciTest` (17), `ClosingPanduanDashboardTest` (5) — 35 test. Regresi Tahap 2.5/5/6/7: hanya 11 kegagalan pre-existing di atas. Rollback 2 migration diverifikasi manual. Semua halaman closing + dashboard + export dicek 200 di data dev.

**Deploy production**: `git pull` di server → buka `https://erp.dmentaiindonesia.com/deploy-closing.php?confirm=YES-CLOSING` (migrate + ClosingPermissionSeeder + ClosingPanduanSeeder + clear/rebuild cache, ada lock `storage/deploy-closing.lock`) → **hapus `public/deploy-closing.php`** dari server. Lalu isi Tanggal Mulai Periode di Pengaturan Umum. **Sudah dijalankan di production 2026-10-17 (sukses); `public/deploy-closing.php` sudah dihapus dari repository.**

### 4.36 🟢 Sinkron Panduan + Tooltip + Permission + Sidebar untuk Sprint 4.33/4.34/4.35 (2026-10-08)

**Konteks**: setelah 3 sprint hari ini (filter PO/Transfer/Request [[4.33]], Master Barang read-only + Stok exclude produk_jual [[4.34]], 4 field pindahan ke Bahan Baku [[4.35]]), ada 4 hal yang perlu disinkronkan supaya UI/UX konsisten:
1. Panduan existing yang terdampak belum mencerminkan perubahan.
2. 3 field baru di form Bahan Baku belum ada tooltip.
3. Permission `item.create`/`item.edit`/`item.delete` masih "aktif" di seeder padahal UI-nya sudah hilang.
4. Sidebar + heading halaman masih sebut "Master Barang (Lengkap)" tanpa indikasi read-only.

**Fix**:

**A. Append section baru ke 9 panduan existing (seeder baru, idempotent)**
- `database/seeders/Sprint434PanduanUpdateSeeder.php` — baca panduan existing, append section `## 🔄 Update 2026-10-08 — ...` di akhir dengan marker `<!-- SPRINT434 -->` utk cegah dobel append.
- 9 slug diupdate: `master-barang` (read-only + 2 tombol baru), `bahan-baku` (4 fitur baru + tipe tanpa produk_jual), `produk-jual` (track_stok paksa false + konsep POS display + modal kategori), `pembelian` (whitelist 3 tipe + alur beli barang jadi via bahan_baku), `transfer-stok` (whitelist 3 tipe), `permintaan-stok` (whitelist 3 tipe), `dashboard-stok`/`stok-barang`/`laporan-stok` (scope 3 tipe + exclude produk_jual).
- Didaftarkan di `DatabaseSeeder.php` (dev) dan `GoLiveSeeder.php` (production fresh install) setelah `PanduanKontenSeeder`.

**B. 3 Tooltip baru di `TooltipKontenSeeder::seedMasterBahanBakuTooltips()`**
- `master_bahan_baku.jenis` — jelaskan bahan_baku vs perlengkapan + implikasi COA
- `master_bahan_baku.track_stok` — kapan uncheck (ATK yang tidak perlu dihitung ketat)
- `master_bahan_baku.deskripsi` — opsional, catatan tambahan
Embed di `master/bahan-baku/create.blade.php` + `edit.blade.php` di sebelah label 3 field (via `<x-tooltip key="..." />`).

**C. Permission `item.*` CRUD ditandai DEPRECATED**
- `PermissionSeeder.php`: display_name 3 permission (`item.create`/`item.edit`/`item.delete`) ditambah suffix `[DEPRECATED]`. Permission SENGAJA tidak dihapus (reversibel, backward compat utk Role & Hak Akses UI yang mungkin masih di-tweak Owner secara manual).
- `item.view` TIDAK deprecated — menu overview tetap berguna.

**D. Sidebar + heading halaman diperjelas**
- `layouts/app.blade.php`: label menu "Master Barang (Lengkap)" → **"Semua Barang (Overview)"** + `title` attribute menjelaskan read-only.
- `item/index.blade.php`: `@section('title')` + heading halaman + breadcrumb ikut rename, ditambah subtitle `<small>` info "read-only, CRUD lewat Bahan Baku/Produk Jual".
- `pemakaian-perlengkapan/create.blade.php`: link `item.create` yg dulu buka Master Barang Lengkap diarahkan ke `master.bahan-baku.create` dgn hint "pilih Jenis = Perlengkapan".
- `penjualan/pos.blade.php`: empty state "Tambah lewat Master Barang" → "Tambah lewat Produk Jual" (lebih akurat utk konteks POS).

**File yang diedit** (8 file + 1 seeder baru + 1 test baru):
- Baru: `database/seeders/Sprint434PanduanUpdateSeeder.php`
- Baru: `tests/Feature/Tahap7/Sprint434PanduanTooltipTest.php` (5 test)
- Edit: `database/seeders/TooltipKontenSeeder.php` (3 tooltip baru), `PermissionSeeder.php` (deprecated tag), `DatabaseSeeder.php` + `GoLiveSeeder.php` (register seeder baru)
- Edit: `resources/views/master/bahan-baku/create.blade.php` + `edit.blade.php` (embed 3 tooltip)
- Edit: `resources/views/layouts/app.blade.php` (rename menu), `item/index.blade.php` (heading+breadcrumb)
- Edit: `pemakaian-perlengkapan/create.blade.php`, `penjualan/pos.blade.php` (link target)

**Verifikasi**: `Sprint434PanduanTooltipTest.php` (5 test) — seeder append 9 panduan + idempotent (tidak dobel kalau dijalankan 2x), 3 tooltip ada di source seeder, tooltip ter-embed di view, permission `item.*` ber-tag DEPRECATED. All 5 PASS.

Full regresi 6 suite today (Sprint434Panduan + BahanBakuField + MasterBarangReadonly + FilterTipeDapatDibeli + MasterBarangTipeLabel + AnalisisPenjualan) = 53 test PASS, 0 regresi.

**Instruksi deploy production**:
1. `git pull`
2. Akses `https://erp.dmentaiindonesia.com/clear-cache.php` → **hapus file dari server**
3. **WAJIB jalankan 3 seeder** utk apply perubahan di DB production (karena perubahan panduan + tooltip + permission label itu stored di DB, bukan code):
   - Tambah ke `clear-cache.php` sekali-pakai: `Artisan::call('db:seed', ['--class' => 'PermissionSeeder', '--force' => true]); Artisan::call('db:seed', ['--class' => 'TooltipKontenSeeder', '--force' => true]); Artisan::call('db:seed', ['--class' => 'Sprint434PanduanUpdateSeeder', '--force' => true]);`
   - Akses browser → hapus file. Alternatif: pakai akses SSH kalau tersedia.

Zero migration DDL — murni data seed update (idempotent, aman dijalankan ulang).

### 4.35 🟢 Field "Jenis", "Lacak Stok", "Deskripsi" + Modal Tambah Kategori Dipindah ke Form Bahan Baku & Produk Jual (2026-10-08)

**Konteks**: lanjutan [[4.34]]. Audit Owner menemukan 4 fitur yang sebelumnya cuma ada di form Master Barang Lengkap (sekarang read-only) belum dipindah ke 2 menu CRUD penerus:
1. Radio **Jenis Item** (bahan_baku vs perlengkapan — axis orthogonal CLAUDE.md [[4.8]])
2. Checkbox **Lacak Stok** (track_stok)
3. Textarea **Deskripsi**
4. Modal inline **"Tambah Kategori Baru"** (dgn tombol `+` di sebelah dropdown kategori)

**Fix**:
- `BahanBakuRequest` tambah validasi 3 field (`jenis` IN `[bahan_baku, perlengkapan]`, `track_stok` boolean, `deskripsi` string max 1000).
- `MasterBahanBakuController::store()` + `update()`: `track_stok = $request->boolean('track_stok')` — pola checkbox-style (absen = false), konsisten dgn `is_active`.
- `master/bahan-baku/create.blade.php` + `edit.blade.php`: tambah 3 field + tombol `+` di dropdown kategori + modal "Tambah Kategori Baru" sbg SIBLING form utama (bukan nested, lihat [[4.14]]). Edit form pakai `@php $jenisAktif` block utk resolve enum→string (hindari issue `JenisItem` BackedEnum vs string literal di `@checked`).
- `master/produk-jual/_form.blade.php`: tambah tombol `+` di dropdown kategori + modal "Tambah Kategori Baru" sbg sibling (modal cuma ini, 3 field lain tidak relevan utk produk jual — jenis/track_stok tidak dipakai, deskripsi sudah ada).

**Konfirmasi tetap sinkron**: modal POST ke `item.kategori.store` yang sudah ada sejak dulu di routes/web.php — tidak buat route baru. `ItemController::storeKategori()` sudah terima `kode_kategori` wajib ([[4.21]]), masih jalan apa adanya.

**File yang diedit** (5 file + 1 test baru):
- `app/Http/Requests/BahanBakuRequest.php` — 3 rule baru
- `app/Http/Controllers/MasterBahanBakuController.php` — store+update ambil track_stok
- `resources/views/master/bahan-baku/create.blade.php` — 3 field + tombol `+` + modal
- `resources/views/master/bahan-baku/edit.blade.php` — sama, dgn `@php` enum→string
- `resources/views/master/produk-jual/_form.blade.php` — tombol `+` + modal
- `tests/Feature/Tahap7/BahanBakuFieldPindahanTest.php` — 8 test baru

**Verifikasi**: 8 test PASS — form create render 4 field, form edit populate radio+textarea, modal rendered di produk jual, store perlengkapan menyimpan `jenis=perlengkapan` + `track_stok=false` + `deskripsi`, store bahan_baku default menyimpan `jenis=bahan_baku` + `track_stok=true`, update ubah jenis+track_stok, dropdown tipe bahan baku form **tanpa opsi produk_jual** (konfirmasi [[4.33]] jalan), modal kategori submit via `/item/kategori` sukses.

Regresi 36 test lintas 4 suite related (BahanBaku + MasterBarangReadonly + FilterTipeDapatDibeli + MasterBarangTipeLabel) PASS, 0 regresi.

**Instruksi deploy**: `git pull` → `/clear-cache.php` di browser (lalu hapus). Zero migration.

### 4.34 🔴 Master Barang Lengkap READ-ONLY + Dashboard/Laporan Stok Exclude Produk Jual + Harden Track Stok (2026-10-08)

**Konteks**: lanjutan [[4.33]]. Owner laporkan 3 masalah lanjutan:
1. Label kolom "Tipe" di Dashboard Stok + Stok Index masih tampil "Lainnya" utk produk jual (bug sama pattern [[4.33]] tapi di 4 view stok yang kelewat).
2. Produk jual tampil di Dashboard Stok padahal secara konsep dibuat on-demand lewat resep.
3. "Beberapa model yang jadinya membingungkan" — Owner mau **1 model**: barang jadi tetap masuk ke bahan_baku, produk_jual = POS display + resep ke bahan baku. Master Barang Lengkap mau dibikin read-only, Edit redirect ke menu masing-masing.

**Keputusan arsitektur Owner (jadi aturan permanen ke depan)**:
- Produk jual = **POS display entity** saja. Tidak track stok fisik.
- Stok real cuma di `bahan_baku` + `kemasan` + `tambahan_gratis` (= `Item::TIPE_DAPAT_DIBELI`, dari [[4.33]]).
- Kalau Owner beli barang jadi (minuman kaleng, snack kemasan) → tetap input sebagai `bahan_baku` dgn satuan "kaleng"/"pcs" → bikin produk_jual dgn resep "1 bahan Fanta Kaleng". Konsekuensi: harga_beli dari PO tetap akurat, stok terpotong benar saat POS, 1 pattern utk semua tipe barang.
- Master Barang Lengkap jadi **read-only overview**. CRUD cuma 2 menu: Bahan Baku (3 tipe non-POS) + Produk Jual (2 tipe POS).

**Fix (3 bagian, 1 commit)**:

**A. Dashboard & Laporan Stok EXCLUDE produk_jual/produk_tambahan**
- `StokDashboardController`: 7 titik query (alertStok, nilaiStokPerCabang via tipe filter, stokItems list, slowMover, topMovement, agingStok, stokMati) semua tambah `whereIn('tipe', Item::TIPE_DAPAT_DIBELI)`.
- `StokController::index()`: tambah `whereHas('item', ...)` filter.
- `LaporanStokController::index()` + `stokMinimum()`: sama.
- Dropdown filter tipe 3 opsi: Bahan Baku, Kemasan, Tambahan Gratis (buang Produk Jadi legacy).
- View match block label (dashboard.blade.php, index.blade.php) di-expand ikut pola [[4.33]].

**B. Master Barang Lengkap READ-ONLY**
- `item/index.blade.php`:
  - Tombol "Tambah Item" di header → diganti 2 tombol: "+ Bahan/Kemasan" (route `master.bahan-baku.create`) dan "+ Produk Jual" (route `master.produk-jual.create`). Masing-masing di-gate permission create menu-nya.
  - Tombol "Hapus" di tiap baris DIHAPUS total (desktop + mobile).
  - Tombol "Edit" tetap ada, link ke `item.edit` tapi controller sekarang **redirect by tipe**: `produk_jual`/`produk_tambahan` → `master.produk-jual.edit`, sisanya → `master.bahan-baku.edit`.
  - Empty state buttons & mobile view ikut diupdate.
- `ItemController::create()` + `store()` + `destroy()` + `update()` → redirect defensif (bukan abort), biar bookmark lama tidak tampil error page.
- `ItemController::edit()` → redirect by tipe (gate permission diturunkan dari `item.edit` ke `item.view` krn user tidak lagi edit langsung di menu ini).
- Routes `item.*` **TIDAK dihapus** — biar backward compat utk link eksternal/panduan lama. Semua jalur mutasi di controller sudah di-neutralize via redirect.

**C. Harden Produk Jual — `track_stok` dipaksa `false`**
- `MasterProdukJualController::store()` + `update()`: hardcode `$data['track_stok'] = false`, abaikan input user. Alasan: produk jual murni POS display, stok fisik real selalu dihitung dari expand resep ke bahan baku saat POS checkout (`PenjualanService::cekResepCukup`).

**File yang diedit** (8 file):
- `app/Models/Item.php` — tidak diedit sesi ini (constant TIPE_DAPAT_DIBELI dari [[4.33]])
- `app/Http/Controllers/ItemController.php` — 4 method redirect
- `app/Http/Controllers/StokController.php` — filter query + dropdown tipe
- `app/Http/Controllers/StokDashboardController.php` — 7 titik query filter + whitelist $tipe
- `app/Http/Controllers/LaporanStokController.php` — 2 titik query filter
- `app/Http/Controllers/MasterProdukJualController.php` — `track_stok=false` di store+update
- `resources/views/item/index.blade.php` — tombol header + action + empty state + mobile
- `resources/views/stok/dashboard.blade.php` — dropdown + match block
- `resources/views/stok/index.blade.php` — dropdown + match block

**Verifikasi**: `tests/Feature/Tahap7/MasterBarangReadonlyDanStokFilterTest.php` (10 test):
- Master Barang: tombol Tambah Item lama hilang (2 tombol baru muncul), `item.create` redirect ke bahan-baku index, `item.edit` bahan_baku → bahan-baku edit, `item.edit` produk_jual → produk-jual edit, `item.destroy` tidak hapus data.
- Dashboard/Index/Laporan Stok: exclude produk_jual dari list meski ada stok.
- Dashboard dropdown tipe: 3 opsi (bahan_baku/kemasan/tambahan_gratis) tanpa produk_jadi.
- Produk Jual: track_stok dipaksa false meski user kirim `track_stok=1`.

Full regresi related suites 30 test PASS (FilterTipeDapatDibeli + MasterBarangTipeLabel + AnalisisPenjualan), 0 regresi.

**Instruksi deploy**: `git pull` → `/clear-cache.php` di browser (lalu hapus). Zero migration.

**Catatan untuk Owner (data existing)**:
- Produk jual lama yang mungkin punya `track_stok=true` + ada baris `stocks` dgn qty > 0 → tetap tersimpan di DB tapi sekarang **tidak muncul lagi** di UI Dashboard/Laporan Stok. Secara data: aman (tidak dihapus), secara UX: hidden.
- Kalau Owner mau cleanup data (reset `track_stok=false` + hapus baris stocks produk jual) → butuh script 1-time, bilang nanti saya buatkan.

### 4.33 🔴 Filter Tipe Item di PO/StockTransfer/StockRequest — Cegah Produk Jual Dibeli (2026-10-08)

**Laporan Owner**: form PO (`/pembelian/create`) menampilkan item bertipe `produk_jual` (dimsum, dsb) di dropdown pilih barang — padahal produk jual **dibuat sendiri dari resep** di dapur, bukan dibeli dari supplier. Rawan terpilih tidak sengaja → PO masuk DB dgn item produk_jual → stok dobel-count saat PO diterima (nambah dari pembelian + nambah dari produksi via resep), HPP ketimpa harga PO sembarangan.

**Audit menemukan pola yang sama di 3 controller**: `PurchaseOrderController::create()`, `StockTransferController::create()`, `StockRequestController::create()` — SEMUANYA `Item::aktif()` saja, 0 filter tipe. `StokController::adjustmentStore()` sengaja TIDAK difilter (opname fisik butuh semua tipe) — benar, dibiarkan.

**Fix**:
- Constant terpusat `Item::TIPE_DAPAT_DIBELI = ['bahan_baku', 'kemasan', 'tambahan_gratis']` + scope `scopeDapatDibeli()`. `tambahan_gratis` (sumpit/garpu/saus kecil) SENGAJA masuk — genuinely dibeli dari supplier walau gratis utk customer di POS. `produk_jual`/`produk_tambahan`/legacy TIDAK masuk.
- 3 controller `create()` tambah `->dapatDibeli()` sebelum `->get()`.
- 3 controller `store()` tambah guard rail backend: batch-load `Item` by id, kalau ada yang bukan `TIPE_DAPAT_DIBELI` → `back()->withErrors()` dgn pesan jelas. Hindari bypass via devtools (filter dropdown doang bisa di-skip kalau ada yang post direct).

**File yang diedit** (5 file):
- `app/Models/Item.php` (constant + scope baru di bawah `scopeProdukJual` existing)
- `app/Http/Controllers/PurchaseOrderController.php` (create + store)
- `app/Http/Controllers/StockTransferController.php` (create + store)
- `app/Http/Controllers/StockRequestController.php` (create + store)
- `tests/Feature/Tahap7/FilterTipeDapatDibeliTest.php` (10 test)

**Verifikasi**: 10 test — constant isi benar, scope filter benar (bahan_baku/kemasan/tambahan_gratis masuk; produk_jual/produk_tambahan tidak), 3 form create dropdown exclude produk_jual, 3 store tolak item produk_jual (guard rail), PO store terima bahan_baku + tambahan_gratis (regresi). All 10 PASS.

**Data historis**: tidak di-audit otomatis (keputusan Owner — Opsi C "filter + guard rail" dipilih tanpa B "audit data existing"). Kalau nanti ada laporan dobel-count stok produk jual, baru dicek manual via query `purchase_order_items.item_id IN (SELECT id FROM items WHERE tipe = 'produk_jual')`.

**Instruksi deploy**: `git pull` → `/clear-cache.php` di browser (lalu hapus). Zero migration, zero DB touch.

### 4.32 🟢 Analisis Penjualan — 5 Metrik di 3 Halaman (Dashboard Pusat/Cabang + Laporan Penjualan) (2026-10-05)

**Konteks**: Owner minta 5 widget metrik agregat untuk analisis cepat lintas periode (Rata-rata Nilai Transaksi, Rata-rata Jumlah Transaksi, Hari Nilai Tertinggi, Hari Jumlah Tertinggi, Rata-rata Omzet Harian) ditempatkan di 3 lokasi: Dashboard Pusat (scope all cabang), Dashboard Cabang (scope cabang aktif), Laporan Penjualan (section baru "Analisis Periode" sinkron filter cabang+tanggal halaman).

**Arsitektur (1 query optimal + cache 5 menit)**:
- `app/Services/AnalisisPenjualanService.php` — 1 query `selectRaw('DATE(tanggal_order), SUM(total_bayar), COUNT(*)')` group-by date, 5 metrik dihitung di memory dari Collection hasil (tidak ada subquery per metrik). Cache key `analisis_penjualan.{cabangId|all}.{dari}.{sampai}` TTL 300 detik. **Tanpa auto-invalidate** di order create/edit/batal — trade-off disetujui Owner: freshness 5 menit cukup, hindari kompleksitas hook di berbagai jalur mutasi order. Static `presetPeriode($key)` resolve 6 preset ke Carbon range (hari_ini / 7_hari / 30_hari / bulan_ini / bulan_lalu / tahun_ini).
- Semua rata-rata di-cast `(float)` eksplisit — PHP int-division `6/3=2` dikembalikan int, kalau tidak di-cast API return type campur-aduk float|int yang bikin consumer sulit.

**Helper baru** `fmt_rupiah_singkat()` di `app/helpers.php` — 3 tier format untuk card sempit:
- `>= 1.000.000` → `Rp 8,5jt` (1 desimal, trailing zero dibuang: `1000000` → `Rp 1jt`, bukan `Rp 1,0jt`)
- `>= 100.000` → `Rp 850rb` (bulat, no desimal)
- `< 100.000` → `Rp 50.000` (full, delegasi ke `fmt_rupiah()`)
Tooltip Bootstrap pada tiap card tampilkan full angka (`fmt_rupiah()`) via `data-bs-toggle="tooltip"` + `title`.

**Component Blade** `resources/views/components/analisis-penjualan.blade.php` — 5 KPI card dalam 1 row responsive (col-6 mobile, col-lg auto desktop), dropdown preset di header card `onchange="this.form.submit()"`, support `formAction` prop (null = current URL, utk Laporan Penjualan di-set eksplisit ke `route('laporan.penjualan')`). Tooltip initializer via `@once @push('scripts')` biar tidak duplikat kalau component dipakai >1x di halaman.

**Filter periode SCOPE TERPISAH dari widget existing (disetujui Owner)**: dropdown `ap_periode` HANYA mempengaruhi section "Analisis Penjualan" baru — 4 card snapshot existing (Total Hari Ini / Bulan Ini / Uang Belum Disetor / Kas HO) di Dashboard Pusat **TIDAK ikut filter**, tetap real-time snapshot. Konsekuensi: 1 halaman punya 2 basis waktu, label section "Analisis Penjualan" eksplisit menampilkan periode aktif biar user tidak bingung.

**Integrasi Laporan Penjualan**: dropdown preset auto-override `dari`/`sampai` kalau user TIDAK isi 2 date field (`$request->filled('dari') && !$request->filled('sampai')` kondisi): user masih bisa custom date manual, saat itu preset otomatis masuk mode "custom" (ap_periode `null` → label jadi tanggal manual). KPI widget+tabel di bawah ikut filter yang sama (konsisten, 1 scope).

**File yang diedit** (8 file):
- Baru: `app/Services/AnalisisPenjualanService.php`, `resources/views/components/analisis-penjualan.blade.php`, `tests/Feature/Tahap7/AnalisisPenjualanTest.php`
- Edit: `app/helpers.php` (helper baru), `app/Http/Controllers/DashboardController.php` (DI service + compute di `pusat()`/`cabang()`), `app/Http/Controllers/LaporanPenjualanController.php` (preset resolver + compute analisis), `resources/views/dashboard/pusat.blade.php`, `resources/views/dashboard/cabang.blade.php`, `resources/views/laporan/penjualan/index.blade.php` (embed component).

**Verifikasi**: `AnalisisPenjualanTest.php` (12 test) — helper 3 tier format (termasuk edge: 99999→full, 100000→rb, 1jt→"1jt" tanpa koma), service 5 metrik akurat (fixture 3 hari 6 order), data kosong tidak div-by-zero, filter cabang isolate (cabA/cabB/all), cache hit (tambah data setelah populated, hasil kedua tetap sama), preset resolver (6 opsi + fallback invalid→bulan_ini), HTTP render di 3 halaman, dropdown 6 opsi, tooltip markup hadir, preset override tanggal di Laporan Penjualan. All 12 PASS.

**Test manual Owner (5 skenario)**:
1. Dashboard Pusat: cek section "Analisis Penjualan" (dropdown periode kanan atas) di antara "Snapshot" existing dan "Ringkasan Setoran & Kas HO". Ganti dropdown → 5 card update, widget 4 snapshot di atas TETAP (tidak ikut filter — by design).
2. Dashboard Cabang: masuk sbg manajer_cabang → section Analisis muncul paling atas (sebelum STAT CARDS), data cuma dari cabang aktif.
3. Laporan Penjualan: section "Analisis" muncul di antara filter dan Stat Cards. Pilih preset "7 Hari" → `dari`/`sampai` ikut berubah → KPI+tabel di bawah ikut update.
4. Pilih preset lalu custom tanggal manual → preset auto "custom" (dropdown tetap bisa dipakai kembali).
5. Hover 1 card → tooltip tampil full angka (misal `Rp 8.500.000` bukan `Rp 8,5jt`).

**Instruksi deploy production**: `git pull` → akses `/clear-cache.php` di browser (lalu hapus file). Tidak ada migration baru, tidak ada perubahan skema DB — murni code+view.

### 4.31 🟢 Bug harga_jual → harga_beli_terakhir di Master Bumbu Pusat + Enhancement Picker Import (2026-09-29)

**Konteks Owner**: preview harga bahan di halaman edit Master Bumbu Pusat tampil Rp 0 utk semua bahan baku (karena pakai `harga_jual` yang memang kosong utk `bahan_baku`). Sekaligus permintaan enhancement: picker "Import dari Bumbu Pusat" di form Produk Jual tampilkan detail bahan + harga per bumbu, bukan cuma nama.

**Bug A — `harga_jual` → `harga_beli_terakhir` di preview Master Bumbu Pusat**:
- `ResepBumbuItem::getTotalHargaMasterAttribute()` (line 108) pakai `$this->item?->harga_jual` — bahan_baku genuinely tidak punya field itu (`harga_jual` untuk `produk_jual` yang dijual di POS), jadi kolom **"Total /kg" selalu Rp 0** utk semua bahan baku di halaman edit Master Bumbu Pusat.
- Ini adalah TODO yang sudah tercatat di [[4.19]] tapi belum difix. Sekarang selesai.
- **HPP RIIL di POS TIDAK KENA bug ini** — `PenjualanService::potongStokUntukItem()` pakai FIFO batch (`StokService::keluar`), dan `MasterProdukJualController::hitungSubtotalBumbuTunggal()` (kalkulator preview di form Produk Jual) SUDAH benar pakai `harga_beli_terakhir` sejak Fitur Import Bumbu Pusat [[4.16]]. Bug ini murni di 1 halaman preview UI.

**Fix Bug A (4 titik)**:
- `ResepBumbuItem::getTotalHargaMasterAttribute()` — `harga_jual` → `harga_beli_terakhir`. Otomatis footer "Total Bahan per 1 unit produksi" ikut benar (pakai accessor sama).
- `resources/views/master/resep-bumbu/edit.blade.php` — kolom "Harga Master" → **"Harga Beli"**, kolom "Total /kg" → **"Subtotal"**, data source `harga_jual` → `harga_beli_terakhir`, caption info "harga jual" → "**harga beli terakhir**" + ditambah kalimat "HPP riil saat penjualan tetap dihitung FIFO dari batch stok cabang" supaya user tidak salah paham preview ini adalah HPP final.
- Dropdown "Tambah Bahan": `data-harga-jual` → `data-harga-beli` + JS `updatePreview()` `dataset.hargaJual` → `dataset.hargaBeli` + pesan "harga master belum diset" → "harga beli belum diset".

**Bug B (enhancement) — Picker Import Bumbu Pusat tampilkan detail bahan + harga**:
- Sebelumnya: modal picker cuma tampilkan `nama + kode + jumlah bahan` sebagai list button. Owner harus pilih dulu baru tahu isi bumbunya (dan harga baru muncul di baris resep produk setelah dilink).
- Sekarang: **accordion inline** — tiap card bumbu bisa di-expand utk lihat tabel detail bahan (nama, takaran, harga beli/satuan, subtotal) + **Total HPP Bumbu** di footer. Tombol "Pilih" tetap ada di header card (bisa langsung link tanpa expand).
- `listBumbuPusat()` endpoint: eager-load `items.item`, return array `items` per bumbu berisi `{nama_bahan, kode_bahan, qty, satuan, mode_harga, harga_beli, subtotal}` + `total_hpp` per bumbu. **Rumus subtotal SAMA persis** dgn `hitungSubtotalBumbuTunggal()` yang dipakai kalkulator produk jual (`qty_per_unit_dalam_kg × harga_beli_terakhir`, skip kalau `mode_harga=gratis`) — konsistensi lintas 3 tempat: preview picker, preview form Produk Jual, HPP riil POS.
- Modal picker render pakai fungsi JS baru `renderKartuBumbu(b, idx)` — Bootstrap collapse-based accordion, escape HTML aman (`escHtml()`), format Rupiah konsisten (`fmtRupiah()`), qty tanpa trailing zero (`fmtQtyKecil()`).

**Klarifikasi "harga per cabang" (didisclose ke Owner sebelum eksekusi)**: `items.harga_beli_terakhir` sekarang GLOBAL, bukan per cabang. Yang genuinely per-cabang adalah `stock_batches` (FIFO batch cost aktual). Semua preview UI (Master Bumbu, kalkulator Produk Jual, picker Import) pakai `harga_beli_terakhir` global sebagai **estimasi kasar** — HPP RIIL saat POS checkout tetap pakai FIFO batch per cabang. Konsisten dgn keputusan Owner di CLAUDE.md [[12.4]]. Sprint per-cabang preview terpisah (lebih kompleks, butuh context cabang aktif di halaman master).

**Yang TIDAK disentuh** (defensif, sesuai instruksi Owner):
- Master Produk Jual form — `harga_jual` (utk customer di POS) TETAP pakai `harga_jual`. Diverifikasi via test regresi eksplisit (`test_master_produk_jual_form_tetap_pakai_harga_jual`).
- POS view (`penjualan/pos.blade.php`) — TETAP pakai `harga_jual`.
- `hitungSubtotalBumbuTunggal()` di `MasterProdukJualController` — sudah benar sejak awal, tidak disentuh.

**File yang diedit** (4 file):
- Model: `app/Models/ResepBumbuItem.php`
- View: `resources/views/master/resep-bumbu/edit.blade.php`, `resources/views/master/produk-jual/_form.blade.php`
- Controller: `app/Http/Controllers/MasterProdukJualController.php` (`listBumbuPusat()` enhanced)

**Verifikasi**: `tests/Feature/Tahap7/MasterBumbuHargaBeliTest.php` (8 test) — accessor pakai `harga_beli_terakhir` (2 kg × 45k = 90k, bukan 0), bahan tanpa harga_beli subtotal 0 (bukan fallback ke harga_jual), halaman edit tampil kolom "Harga Beli"+"Subtotal" (bukan "Harga Master"/"Total /kg" lama) + caption baru "harga beli terakhir", regresi Master Produk Jual TETAP pakai `harga_jual`, endpoint `listBumbuPusat()` return detail bahan + `total_hpp` benar (0.5kg×30k + 1kg×20k = 35k), bahan mode `gratis` subtotal 0, form Produk Jual render JS `renderKartuBumbu()` + `BUMBU_PUSAT_LIST_URL`, permission ditolak (403) utk role tanpa akses. Full regression 359 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**Test manual utk Owner** (4 skenario, sudah verified di test otomatis, tinggal cek visual):
1. Master Bumbu Pusat → edit 1 bumbu → cek kolom "Harga Beli" tampil harga aktual (bukan Rp 0) + kolom "Subtotal" akurat + footer "Total Bahan per 1 unit produksi" benar.
2. Master Bumbu Pusat → Tambah Bahan → pilih bahan → preview "Perkiraan total" tampil angka benar dari harga_beli_terakhir.
3. Form Produk Jual (create atau edit) → klik "Import dari Bumbu Pusat" → cek accordion: klik nama bumbu → expand tabel bahan (nama, takaran, harga beli/satuan, subtotal) + footer "Total HPP: Rp X". Tombol "Pilih" tetap kerja langsung link ke resep produk.
4. Master Produk Jual → cek field "Harga Jual" masih pakai harga_jual (regresi, tidak ikut ke-fix salah). POS transaksi juga tetap normal.

---

## 5. STRATEGI PENGEMBANGAN

### 5.1 Modul yang DIMODIFIKASI/REPLACE 🔴

| Modul | Perlakuan | Alasan |
|-------|-----------|--------|
| **POS/Transaksi** | REPLACE (bikin baru) | Bisnis retail food beda total dari jasa giling |
| **Produk & Resep** | REPLACE | Fokus dimsum, bukan produk giling |
| **Master Item + Kategori** | REPLACE | Data dimsum, bukan daging |
| **Jenis Olahan** | REPLACE atau hapus | Konsep "olahan daging" tidak relevan |

### 5.2 Modul yang DIMODIF/ADD 🟡

| Modul | Perlakuan | Detail |
|-------|-----------|--------|
| **Setoran & Kas** | MODIFY | Tambah alur cabang→HO dengan approval |
| **Dashboard & Laporan** | MODIFY | Tambah info setoran & selisih |
| **BEP Otomatis** | EXTEND | Support `tipe_order='penjualan'` (bukan cuma `jasa_giling`) |
| **Loyalty Service** | EXTEND | `auto_track` support basis total belanja Rp / jumlah transaksi |
| **Panduan & Tooltip** | ADD | Untuk fitur baru saja |
| **Permission & Role** | ADD | Untuk menu baru saja |
| **Fitur Varian Produk** | ADD | Struktur N-dimensi baru (belum ada di Berkah Mulyo) |

### 5.3 Modul yang DIPAKAI APA ADANYA (REUSE) 🟢

| Modul | Alasan |
|-------|--------|
| **HR** (Karyawan, Absensi, Cuti, Gaji, Shift, Evaluasi) | Universal, pola sama untuk bisnis apapun |
| **Aset & Depresiasi** | Universal, bisnis butuh tracking aset |
| **Keuangan Dasar** (COA, Neraca, Kategori Transaksi) | Universal, akuntansi standar |
| **Stok FIFO + Batch** | Universal, pattern inventory food industry |
| **Notifikasi, Activity Log, Backup** | Infrastruktur, tidak perlu diubah |
| **Pembelian (PO)** | Universal untuk semua bisnis |
| **Transfer Antar Cabang** | Universal |

**Prinsip**: Kalau modulnya universal, JANGAN diubah. Fokus energi ke yang beda.

### 5.4 Roadmap 7 Tahap

| # | Tahap | Kompleksitas | Ketergantungan | Status |
|---|-------|--------------|-----------------|--------|
| 1 | **Branding** (warna, logo, nama, PWA icons) | Low | Independen | ✅ Selesai (2026-09-13) |
| 2 | **Master Data** (kategori, produk dimsum, resep, varian) | Medium | Bergantung keputusan varian (SUDAH TERJAWAB) | ✅ Selesai (2026-09-13) |
| 3 | **POS Modifikasi** (grid gambar, dine-in/takeaway/frozen, auto potong stok, varian) | HIGH | Bergantung Tahap 2 | ✅ Selesai (2026-09-13, dikoreksi: UI rollback ke Berkah Mulyo asli + modifikasi ringan — lihat catatan di bawah) |
| 4 | **Stok/Resep Adaptation** (rename `qty_per_kg` → `qty_per_unit`, resep basis "per porsi") | Medium | Bergantung Tahap 3 | ✅ Selesai (2026-09-13) |
| 2.5 | **Split Master Item** (Bahan Baku &amp; Kemasan vs Produk Jual, foto+resep+varian+outlet dalam 1 form) | High | Bergantung Tahap 2-4 | ✅ Selesai (2026-09-14) |
| 5 | **Setoran Kasir Cabang → HO** (submit auto-hitung, approve/reject, kas HO) | Medium | Independen (bisa paralel Tahap 3-4) | ✅ Selesai (2026-09-15) |
| 6 | **Dashboard & Laporan Owner** (widget setoran+kas HO, laporan Setoran Kasir) | Medium | Bergantung Tahap 5 | ✅ Selesai (2026-09-15) |
| 7 | **Final Polish & Go-Live** (cleanup Tailwind/Alpine, fix BEP+Loyalty hardcode, sinkron logo, error pages branded, E2E testing) | Medium-High | Bergantung SEMUA tahap | ✅ Selesai (2026-09-14) — lihat [[4.12]] |

### 5.5 Strategi Data Awal
- JANGAN langsung `migrate:fresh` — data HR dari Berkah Mulyo masih berguna sebagai template
- Bikin seeder baru khusus D'mentai untuk: outlet, produk, kategori, resep, varian
- Data lama Berkah Mulyo yang tidak relevan (produk giling) soft-delete bertahap sambil ganti dengan data D'mentai
- Nanti bikin tools "Reset Data D'mentai" di menu Settings untuk kemudahan reset ulang saat testing

---

## 6. DESAIN & BRANDING

### 6.1 Palette Warna
| Warna | Hex | Pakai untuk |
|-------|-----|-------------|
| **Hitam** | `#1A1A1A` | Sidebar, header, primary text, background utama |
| **Oranye** | `#FF6B00` | Button primary, accent, highlight, badge |
| **Krim** | `#FFF8E7` | Background section, card, hover state |
| Putih | `#FFFFFF` | Background section kontras |
| Abu terang | `#F5F5F5` | Divider, background secondary |

**Filosofi warna**: Hitam-oranye elegant + krim buat kesan hangat food-friendly. Sesuai dengan logo D'mentai.

### 6.2 Nama & Tagline
- **Nama**: **D'mentai**
- **Tagline**: **Dimsum & Gyoza**
- **APP_NAME** di `.env`: `"ERP D'mentai"`
- **Default `PengaturanUmum.nama_perusahaan`**: `"D'mentai"`
- **Tagline struk default**: `"Dimsum & Gyoza"` (bisa diedit per outlet)

### 6.3 Logo
| Versi | Fungsi | Sumber |
|-------|--------|--------|
| **Logo Full** (bulat, dengan text + tagline + mascot) | Login page, sidebar full, PDF header, PWA icon | File asli dari owner |
| **Logo Icon** (cuma mascot dimsum) | Sidebar collapsed, favicon, notif icon | Generate otomatis (crop mascot dari logo full) |

**PWA Icon**: Generate 8 ukuran (72, 96, 128, 144, 152, 192, 384, 512) dari logo full.

### 6.4 Responsif
- Wajib bisa dipakai di PC (browser desktop) dan Tablet
- Ikuti pola responsif Berkah Mulyo (Bootstrap 5 grid)
- POS khususnya: layout harus optimal di tablet horizontal (kasir pakai tablet)

---

## 7. SPESIFIKASI FITUR UTAMA

### 7.1 POS (Point of Sale)

**Layout**: Grid produk dengan gambar (bukan text list). Sidebar kanan untuk keranjang & bayar.

**Klasifikasi `items.tipe` (5 nilai, sejak Tahap 2.5 — 2026-09-14)**:
| Tipe | Tampil di POS? | Kelola via menu |
|------|----------------|-----------------|
| `bahan_baku` | Tidak (bahan mentah) | Bahan Baku & Kemasan |
| `kemasan` | Tidak | Bahan Baku & Kemasan |
| `tambahan_gratis` | Ya — section "Item Tambahan" (1-klik, gratis) | Bahan Baku & Kemasan |
| `produk_jual` | Ya — grid utama | Produk Jual |
| `produk_tambahan` | Ya — section "Item Tambahan" (berbayar) | Produk Jual |

Nilai lama `produk_jadi`/`lainnya` MASIH VALID di enum DB (backward compat, tidak dihapus) tapi tidak dipakai data manapun lagi setelah reklasifikasi 21 item dummy. Ketersediaan per outlet dikontrol tabel `item_cabang` (pivot item↔cabang, `harga_override` + `is_active`) — item TANPA row sama sekali dianggap aktif di semua cabang (fallback, lihat `Item::tersediaDiCabang()`).

**Fitur wajib**:
1. **Grid produk dengan gambar + nama + harga**
   - Filter kategori: "All Items", "Food", "Drink", "Frozen", dll (dinamis, bisa nambah dari admin)
   - Cari produk cepat (search bar)
2. **Tipe transaksi**: Dine-in / Takeaway / Frozen (dropdown atau tab)
   - Kalau Dine-in: bisa input nomor meja (optional, config per outlet)
   - Kalau Takeaway: mungkin ada fee (config)
   - Kalau Frozen: nanti bisa cetak tanggal produksi/expired di struk
3. **Varian produk** (N-dimensi)
   - Klik produk → muncul modal pilih varian (kalau ada)
   - Contoh: Dimsum Mentai → Size (S/M/L) + Level Pedas (1-5) + Saus (Mayo/Cheese)
   - Bisa juga tidak ada varian (produk simple)
   - Stok: config per produk (ikut induk atau terpisah per varian)
4. **Auto potong stok**
   - Stok dipotong SETELAH klik "Charge/Bayar" (bukan saat klik produk)
   - Produk yang bahan bakunya habis → di-disable dengan warning "Stok habis"
   - Produk lain yang stoknya masih ada tetap bisa dijual
   - Cek stok berdasarkan komposisi resep (per outlet)
5. **Resep opsional per produk**
   - Ada produk yang punya resep (auto potong komposisi seperti bumbu, kemasan, dll)
   - Ada produk yang tidak punya resep (potong 1 unit produk jadi aja)
6. **Item tambahan** (garpu, sumpit, saus, dll)
   - Bisa gratis (Rp 0) atau berbayar (misal Rp 100)
   - Ikut motong stok kalau bahan bakunya di-track
7. **Custom item** (input produk bebas di luar menu)
8. **Add customer** (untuk loyalty/riwayat pelanggan)
9. **Diskon & fee**
   - Diskon manual (kasir input %)
   - Diskon dari voucher/kode
   - Service charge (config per outlet)
   - Take away fee (config per outlet)
10. **Metode pembayaran**: Cash, QRIS, Transfer, Gojek, Grab (bisa split payment)
11. **Multi-action**: Save Bill (bill sementara), Print Bill (preview), Charge (bayar & selesai), Split Bill (bagi tagihan)
12. **Struk cetak**
    - Fleksibel: bisa cetak / tidak cetak / cetak 1-2 rangkap
    - Ikuti pola Berkah Mulyo (footer struk configurable per cabang)

**Prinsip UI/UX POS**:
- Layout harus lebih bagus & informatif dari referensi POS lain
- Optimasi untuk speed kasir (button besar, sedikit klik)
- Tampilan yang jelas: stok, keranjang, total

### 7.2 Struktur Varian Produk (BARU!)

**Model konseptual**:
```
Item (Produk)
  ├─ punya_varian: BOOLEAN (config per item)
  ├─ stok_per_varian: BOOLEAN (config per item)
  │
  └─ Kalau punya_varian = TRUE:
       ├─ ItemAttribute (misal: "Size", "Rasa", "Level Pedas")
       │    └─ ItemAttributeValue (misal Size: "S", "M", "L")
       └─ ItemVariant (kombinasi: Size M + Rasa Mentai + Level 3)
             ├─ harga_override (opsional)
             ├─ stok (kalau stok_per_varian = TRUE)
             └─ resep_override (opsional)
```

Ini fitur BARU (tidak ada di Berkah Mulyo). Perlu migration + model + controller + view.

### 7.3 Setoran Kasir Cabang → HO — ✅ Selesai (2026-09-15)

**Realisasi vs spesifikasi awal (2 penyesuaian disetujui Owner sebelum eksekusi)**:
- **Per HARI, bukan per shift** — tidak ada infrastruktur "buka/tutup shift kasir" di codebase ini (`shifts` murni jadwal HR). 1 baris `setorans` = 1 cabang + 1 tanggal (`unique(cabang_id, tanggal)`).
- **Hybrid dengan modul "Transfer Dana" (`SetoranController`, `setoran.*`) existing**: tabel BARU (`setorans`/`setoran_details`/`setoran_approvals`) untuk konsep "rekonsiliasi harian auto-hitung dari `order_payments`" (beda total dari Transfer Dana yang lump-sum manual) — tapi pergerakan uang saat approve REUSE pola 2-baris `transaksi_keuangans` (kategori `SETORKSR-OUT`/`SETORKSR-IN`, `kode_akun_coa=NULL` sama prinsip `SETOR-IN/OUT`), link via `referensi_type='setoran_kasir'`+`referensi_id` (BUKAN `setoran_pair_id`, supaya tidak nyerempet state machine Transfer Dana). Permission namespace `setoran_kasir.*` (beda dari `setoran.*`) supaya role yang sudah punya akses Transfer Dana tidak otomatis dapat akses modul baru.

**Alur final**:
1. Kasir buka `/setoran-kasir/create` — sistem auto-hitung breakdown per metode (tunai/transfer/qris/gojek/grab) dari `order_payments` hari itu (via `SetoranKasirService::hitungOtomatis()`)
2. **Scope sengaja dibatasi ke KAS TUNAI**: metode non-tunai sudah otomatis settle ke kas non-tunai cabang saat order dibuat (`PenjualanService`) — breakdown non-tunai di `setoran_details` murni informasi transparansi ke HO, TIDAK ada uang yang berpindah untuk metode itu lewat fitur ini
3. Kasir isi "Jumlah Uang Tunai yang Diserahkan" (default = sistem, bisa diedit kalau ada selisih fisik) + bukti foto opsional + catatan → submit → status `menunggu`
4. **Uang BELUM berpindah sama sekali saat submit** (beda dari Transfer Dana yang langsung potong kas pengirim) — `setorans` sebelum approve murni laporan, `Kas` tidak disentuh
5. HO (`admin_pusat`, permission `setoran_kasir.approve`/`.reject`) buka `/setoran-kasir` → Approve (kas cabang -X, kas HO +X, dalam 1 `DB::transaction()`) atau Reject (alasan wajib, kasir bisa revise — submit ulang tanggal yang sama meng-UPDATE baris existing in-place, bukan bikin baris baru, supaya tidak bentrok `unique(cabang_id,tanggal)`)

**Status**: `menunggu` 🟡 / `approved` 🟢 / `rejected` 🔴 (`App\Enums\StatusSetoranKasir`)

**Data tercatat**: `setorans` (tanggal, cabang, kasir submit, total sistem, total disetor, selisih, bukti foto, catatan kasir/HO, link ke 2 `TransaksiKeuangan` saat approved), `setoran_details` (breakdown per metode), `setoran_approvals` (audit trail submit/approve/reject — histori lengkap kalau ada revisi berkali-kali).

**Tabel DB baru**: `setorans`, `setoran_details`, `setoran_approvals` (lihat migration `2026_09_15_500001`-`500003`).

### 7.4 Dashboard Owner — ✅ Selesai (2026-09-15)

**Realisasi**: EXTEND `dashboard/pusat.blade.php` existing (bukan halaman baru) — Owner/admin_pusat sudah otomatis diarahkan ke situ, jadi widget baru langsung terlihat tanpa perlu 2 dashboard terpisah yang membingungkan. Semua widget baru di-gate 1 permission `dashboard.owner.view` (default: admin_pusat + Owner bypass).

**Baris 1 — Card Angka Besar** (persis 4 sesuai spesifikasi):
- Total Penjualan Hari Ini (semua outlet) — `SUM(orders.total_bayar)` hari ini, exclude Dibatalkan
- Total Penjualan Bulan Ini (sudah ada dari widget lama, dipertahankan apa adanya)
- Uang Belum Disetor — `SUM(setorans.total_disetor)` utk status `menunggu`+`rejected`. **Keterbatasan didisclose**: hari yang kasirnya BELUM SUBMIT setoran sama sekali tidak ikut terhitung (bukan bug kalkulasi — datanya memang belum ada untuk dihitung, itu soal kepatuhan submit kasir)
- Kas HO Saat Ini — `SUM(Kas.saldo_sekarang)` untuk cabang bertipe `gudang_pusat`

**Baris 2 — Grafik**: Trend penjualan harian 7 hari (line chart, semua outlet gabungan, widget BARU) + grafik bulanan 6-bulan-per-cabang (SUDAH ADA sebelumnya, dipertahankan — deliver "trend bulanan" dari spesifikasi, walau bukan 12 bulan persis). **Trend mingguan 4-minggu SENGAJA TIDAK dibuat** — dianggap redundant dengan trend harian 7-hari + bulanan 6-bulan yang sudah mencakup rentang pendek dan panjang; bisa ditambah nanti kalau genuinely dibutuhkan.

**Baris 3 — Tabel Alert** (3 tabel sesuai spesifikasi): Setoran Menunggu Approval (link "Proses" ke halaman detail approve/reject, BUKAN quick-action inline — konsisten pola "detail dulu baru aksi" yang sudah dipakai Transfer Dana), Outlet dengan Stok Minimum (item `bahan_baku`/`kemasan`/`produk_jual` di bawah `qty_minimum`, lintas cabang), Selisih Setoran (`ABS(selisih) >= Rp5.000`, threshold arbitrary tapi wajar untuk saring noise pembulatan).

**Filter tanggal/outlet/tipe transaksi dari spesifikasi awal SENGAJA TIDAK diimplementasikan** di widget dashboard (widget tetap fixed "hari ini"/"bulan ini"/real-time) — filter granular lebih cocok di halaman Laporan (`/laporan/setoran-kasir` sudah py filter tanggal+cabang+status) daripada dashboard ringkasan; menambah filter ke dashboard akan signifikan menambah kompleksitas widget tanpa manfaat sepadan untuk use-case "cek cepat kondisi hari ini".

**Bug lama dibersihkan sekalian** (ditemukan pas menyentuh `DashboardController::cabang()` untuk Tahap 6, CLAUDE.md 4.1): card "X order jasa giling hari ini" (hardcode `tipe_order='jasa_giling'`, selalu 0 untuk D'mentai) diganti breakdown **Order per Tipe Transaksi Hari Ini** (Dine-in/Takeaway/Frozen, dari `orders.tipe_transaksi` yang genuinely dipakai Tahap 3) — bukan expand scope, murni cleanup dead-weight widget yang kebetulan ada di file yang sama.

### 7.5 Laporan

**Status per jenis (Tahap 6, 2026-09-15)**:
1. Laporan Penjualan (harian/per outlet/per produk) — **SUDAH ADA sejak sebelumnya** (`LaporanPenjualanController`), tidak disentuh Tahap 6
2. **Laporan Setoran Kasir** — ✅ BARU (`/laporan/setoran-kasir`, permission `laporan.setoran_kasir.view`/`.export`) — filter tanggal+cabang+status, export Excel (CSV-as-.xls, pola sama `LaporanSetoranController` existing). **Beda dari `laporan.setoran` existing** yang melaporkan Transfer Dana generik (`SETOR-OUT`), bukan Setoran Kasir
3. Laporan Selisih — **digabung ke Laporan Setoran Kasir** (kolom `selisih` sudah tampil per baris + widget dashboard "Selisih Setoran vs Sistem"), TIDAK dibuat sebagai laporan/menu terpisah — datanya identik, memecah jadi 2 menu cuma menambah navigasi tanpa manfaat
4. Laporan Stok — **SUDAH ADA sejak sebelumnya**, tidak disentuh
5. Laporan Kas — **SUDAH ADA sejak sebelumnya** (`Kelola Kas & Transaksi`), tidak disentuh

**Export Laporan Setoran Kasir**: Excel ✅ (CSV-as-.xls). **PDF SENGAJA TIDAK dibuat** — `LaporanSetoranController` (rujukan pola terdekat, domain setoran yang sama) sendiri juga tidak punya export PDF, cuma Excel; menambahkan PDF khusus laporan ini akan jadi inkonsistensi pola dibanding laporan setoran lain, bukan penghematan scope yang genuinely dibutuhkan sekarang.

### 7.6 Hak Akses (Role-Based)

**Roles yang sudah ada** (dari Berkah Mulyo, tetap dipakai):
- `admin_pusat` — akses semua
- `admin_gudang` — HO/gudang
- `manajer_cabang` — 1 outlet
- `kasir` — POS + setoran only
- `operator_produksi` — produksi
- `helper` — akses terbatas

**Aturan menu**:
- Menu tampil di sidebar berdasarkan permission user
- Cek dulu permission sebelum tampilkan menu (`@can`)
- Jangan hide via CSS (harus di level backend)

### 7.7 Loyalty

**Untuk sekarang (Tahap awal)**:
- Pakai `event_based` (klaim manual berdasarkan pencapaian yang di-approve admin)
- `auto_track` DINONAKTIFKAN sementara (karena hardcode ke `jasa_giling`)

**Nanti (bisa di Tahap 6)**:
- Extend `LoyaltyService::auto_track` supaya support:
  - Basis "total belanja Rp"
  - Basis "jumlah transaksi"
  - Bukan cuma "berat kg" seperti Berkah Mulyo

---

## 8. KONVENSI CODING

### 8.1 Bahasa
- **Kode**: English (nama variable, function, class)
- **Comment**: Indonesian atau English (konsisten per file)
- **UI text**: Bahasa Indonesia (semua label, message, error)
- **Nama tabel & kolom**: mengikuti pola Berkah Mulyo (Indonesia, snake_case) — misal: `cabangs`, `karyawans`, `stok_masuk`

### 8.2 Naming
| Item | Convention | Contoh |
|------|-----------|--------|
| Model | PascalCase, singular | `Cabang`, `Karyawan`, `Setoran` |
| Controller | PascalCase + Controller | `PosController`, `SetoranController` |
| Table | snake_case, plural | `cabangs`, `karyawans`, `setorans` |
| Column | snake_case | `nama_produk`, `harga_jual` |
| Route | kebab-case | `/pos/kasir`, `/setoran/approve` |
| View | dot-notation, snake_case | `pos.kasir`, `setoran.index` |

### 8.3 Konvensi Rename (dari Berkah Mulyo → D'mentai)
| Kolom lama | Kolom baru | Alasan |
|-----------|-----------|--------|
| `qty_per_kg` (di `resep_bumbu_items`) | `qty_per_unit` | ✅ **SELESAI Tahap 4 (2026-09-13)** — kolom (raw `CHANGE COLUMN`, kompat MariaDB 10.4), accessor `getQtyPerUnitDalamKgAttribute()`, validasi, label UI, dan resep_bumbu sekarang terhubung langsung ke `item_id` (bukan cuma `jenis_olahan_id`) supaya POS otomatis temukan resep tanpa kasir pilih manual. |
| `berat_daging_kg` (di `order_items`) | (biarkan / `qty_pcs`) | Perlu diskusi lebih lanjut saat Tahap 3 |
| `tipe_order='jasa_giling'` | `tipe_order='penjualan'` | Dimsum tidak ada konsep "jasa giling" |
| `items.tipe='produk_jadi'` | `items.tipe='produk_jual'` | ✅ **SELESAI Tahap 2.5 (2026-09-14)** — 1:1 rename (extend enum, nilai lama tetap valid di DB tapi tidak dipakai data manapun lagi). `items.tipe='lainnya'` dipecah jadi `tambahan_gratis` (Garpu Plastik) + `produk_tambahan` (Saus Cabai Extra). Semua whitelist tipe yang dipotong-stok-langsung (`PenjualanService`, `Item::bisaDijualDiCabang()`) ikut di-update ke `produk_jual` — kalau lupa, item drink/frozen tanpa resep akan berhenti kepotong stoknya secara silent (bug nyata yang ditemukan & diperbaiki saat development Tahap 2.5). |

### 8.4 Struktur Folder (ikuti Berkah Mulyo)
```
app/
  Http/Controllers/       # semua controller
  Models/                 # semua model
  Services/               # business logic
  Traits/                 # HasAuditLog, HasCabang, FillsDeletedBy
  Observers/              # 12 observer untuk history/audit
  Enums/                  # RoleUser, dll
resources/views/
  layouts/                # template utama
  pos/                    # view POS
  setoran/                # view setoran
  dashboard/              # view dashboard
database/
  migrations/             # migration files
  seeders/                # seeder files
```

### 8.5 Pattern Coding yang Dipakai
- **Observer pattern**: 12 observer terdaftar di `AppServiceProvider` untuk history/audit
- **Trait `HasAuditLog`**: dipakai di 38 model — verified working
- **Trait `FillsDeletedBy`**: dipakai di 29 model — verified working
- **Gate pattern**: permission di-cache 1 jam (`all_permission_names`), delegasi ke `$user->hasPermission()`
- **DB::transaction()**: WAJIB untuk operasi multi-tabel

---

## 9. TESTING & QUALITY

### 9.1 Sebelum commit/lanjut fitur
- [ ] Semua migration jalan tanpa error (`php artisan migrate:status`)
- [ ] Semua seeder jalan tanpa error
- [ ] Login berhasil pakai user admin
- [ ] Menu terkait fitur baru tampil di sidebar (kalau permission benar)
- [ ] Cek `storage/logs/laravel.log` — tidak ada error baru
- [ ] Manual test alur end-to-end (misal: input transaksi POS → cek stok berkurang → cek masuk laporan)
- [ ] Filter cabang: pastikan query pakai `where('cabang_id', ...)` (SECURITY!)

### 9.1.1 Aturan Scope & Metode Test (WAJIB, sejak Tahap 2.5 — 2026-09-14)

**Scope — apa yang WAJIB ditest:**
1. Semua yang dibuat/diedit/diubah di sesi berjalan (controller/model/migration/view baru maupun diubah)
2. Fitur existing yang **berpotensi terdampak** oleh perubahan struktur (contoh nyata Tahap 2.5: ubah filter `items.tipe` di POS berdampak ke query grid, whitelist potong-stok di `PenjualanService`, bucket persediaan di `NeracaService`, dashboard/laporan Stok — SEMUA itu wajib ditest walau tidak "dibuat baru")

**TIDAK PERLU ditest:** modul yang genuinely tidak terhubung ke perubahan (contoh: ubah POS tidak perlu test menu HR/Aset/Karyawan/Cuti/Penggajian kecuali ada bukti keterkaitan nyata).

**Metode — WAJIB simulasi HTTP request beneran, BUKAN cuma Tinker:**
- Pakai Laravel Feature Test (`tests/Feature/...`) dengan `actingAs($user)->get(...)`/`post(...)`/`put(...)`/`delete(...)` + assertions (`assertOk`, `assertRedirect`, `assertSessionHasErrors`, `assertDatabaseHas`, `assertSoftDeleted`, dll) — bukan cuma `app()->call([...])` via Tinker (itu skip banyak middleware/pipeline nyata: `ConvertEmptyStringsToNull`, session, dll)
- Upload file: `UploadedFile::fake()->image(...)` / `->create(...)` + `Storage::fake('public')`
- Form kompleks (varian/resep terintegrasi): submit multi-field array persis seperti browser beneran akan kirim

**Database test — WAJIB pakai `erp_dimsum_test` (MySQL disposable), BUKAN sqlite in-memory default Laravel:**
- Codebase ini banyak migration raw MySQL-only (`ALTER TABLE ... MODIFY COLUMN ENUM`, dll) yang **tidak jalan di sqlite** — dikonfirmasi 5/5 test bawaan (`ExampleTest`/`ProfileTest`) sudah gagal total di `phpunit.xml` default sebelum Tahap 2.5 (`SQLSTATE[HY000]: ... near "MODIFY": syntax error`)
- Setup: `.env.testing` (`DB_CONNECTION=mysql`, `DB_DATABASE=erp_dimsum_test` — **database terpisah dari `erp_dimsum` dev**, dibuat sekali via `CREATE DATABASE erp_dimsum_test`), `phpunit.xml` TIDAK override `DB_CONNECTION`/`DB_DATABASE` lagi (supaya `.env.testing` yang dipakai)
- Migrate + seed SEKALI ke `erp_dimsum_test` (`APP_ENV=testing php artisan migrate --env=testing --force`, lalu seeder yang relevan) — bukan tiap test run, karena `RefreshDatabase` (migrate ulang tiap test class) lambat & migration DDL MySQL tidak transaction-safe untuk di-toggle bolak-balik
- Test class pakai trait `Illuminate\Foundation\Testing\DatabaseTransactions` (BUKAN `RefreshDatabase`) — tiap test method dibungkus 1 transaction & auto-rollback, data `erp_dimsum_test` tetap bersih antar test TANPA re-migrate
- **Reversibilitas migration** diverifikasi TERPISAH secara manual (`migrate` → `migrate:rollback --step=N` → `migrate` lagi, dicek langsung via `SHOW COLUMNS`/`SHOW TABLES`) — BUKAN di dalam automated test, karena DDL (`ALTER`/`CREATE`/`DROP TABLE`) auto-commit di MySQL sehingga tidak terlindungi oleh transaction rollback
- `UserSeeder` bawaan project py bug pre-existing (`cabang_id` kosong, tidak terkait Tahap 2.5) — test user dibuat langsung via `User::factory()->create(['role'=>...])`, bukan lewat seeder itu

**Kalau ada test FAILED:** STOP, jangan force lanjut ke fitur berikutnya — investigasi dulu apakah itu bug nyata (perbaiki) atau asumsi test yang salah (perbaiki test-nya), baru lanjut.

### 9.2 Bug Berkah Mulyo yang Sudah Difix di erp-dimsum
- Migration `kategori_transaksis` urutan salah → sudah di-fix (rename ke `000001`)
- Migration `fix_kas_soft_delete_unique_constraint` step 1 salah cek index → sudah di-fix (cek by name)

### 9.3 Backup Sebelum Perubahan Besar
Sebelum `migrate:fresh` atau operasi destruktif:
```bash
php artisan backup:run --only-db
```

---

## 10. TROUBLESHOOTING UMUM

### 10.1 Error saat migrate
- Foreign key gagal → cek urutan file migration, tabel yang dirujuk harus dibuat DULU
- Index/constraint conflict → cek migration `fix_*_soft_delete_unique_constraint`

### 10.2 Error saat artisan
- "Table 'cache' doesn't exist" → set `CACHE_STORE=file` di `.env`, jalankan `config:clear`
- ".env invalid" → biasanya `APP_NAME` yang ada spasi tapi tidak diapit `"..."`

### 10.3 Halaman blank / 500
- Cek `storage/logs/laravel.log`
- Cek `APP_DEBUG=true` di `.env` untuk lihat stack trace
- Cek permission folder `storage/` dan `bootstrap/cache/`

### 10.4 Menu tidak muncul
- Cek permission user (`users` → `role` → `role_permissions`)
- Cek blade sidebar (`@can` directive)
- Clear cache: `php artisan cache:clear`

### 10.5b Foto produk/upload tidak tampil (404) padahal sudah ke-upload
- Cek apakah `public/storage` beneran SYMLINK ke `storage/app/public` (`ls -la public/ | grep storage` harus tampil `storage -> ...`, bukan folder biasa)
- Kalau ternyata folder BIASA (bukan symlink) — pernah terjadi di environment ini (2026-09-15), kemungkinan `storage:link` sempat gagal jadi symlink di Windows dan malah bikin folder kosong — cek dulu isinya SAMA dengan `storage/app/public` (jangan asal hapus, pastikan tidak ada data unik yang cuma ada di situ), baru `rm -rf public/storage && php artisan storage:link`
- Setelah fix, `config:clear`+`view:clear`+`cache:clear`, lalu hard refresh browser (Ctrl+Shift+R) — foto lama sering ke-cache browser

### 10.5 Data cabang lain kelihatan (SECURITY BUG!)
- Cek query di controller → apakah ada `where('cabang_id', ...)`
- Jangan pakai `Model::all()` mentah untuk data cabang-spesifik
- CabangScope DORMANT — filter WAJIB manual

---

## 11. KONTAK & GAYA KOMUNIKASI

### 11.1 Yang Berhak Memutuskan
- **Owner project**: user (yang chat dengan Claude)
- Semua keputusan bisnis (fitur, alur, prioritas) harus konfirmasi dulu ke owner
- Jangan asumsi, tanya kalau ragu

### 11.2 Gaya Komunikasi
- Bahasa Indonesia (owner lebih nyaman)
- Step-by-step (owner belum expert developer)
- Jelaskan konteks setiap perubahan besar
- Kalau perlu keputusan, kasih opsi + pro/kontra
- JANGAN langsung eksekusi perubahan besar — konfirmasi dulu

---

## 12. TODO LIST (untuk masa depan)

### 12.1 Cleanup (Tahap 7) — ✅ Selesai (2026-09-14)
- [x] Hapus dead code Tailwind + Alpine dari `package.json`
- [x] Hapus `welcome.blade.php` yang tidak dipakai
- [x] Bersihkan Breeze scaffolding yang tidak dipakai (`layouts/navigation.blade.php`; register/profile dikonfirmasi BUKAN dead code, dibiarkan)
- [x] Bikin custom error page (403/404/500) dengan branding D'mentai

### 12.2 Perbaikan Bug Terwariskan — ✅ Selesai (2026-09-14)
- [x] Extend `BepOtomatisService` supaya support `tipe_order='penjualan'` — lihat [[4.12]]
- [x] Extend `LoyaltyService::auto_track` supaya support basis Rp/transaksi — lihat [[4.12]]
- [x] Sinkronkan 2 mekanisme logo (dinamis + statis) via `PengaturanUmumObserver` — lihat [[4.12]]

### 12.3 Fitur Baru yang Belum Ada di Berkah Mulyo
- [x] Struktur varian produk (N-dimensi) — ✅ Tahap 2 (DB+Model) + Tahap 2.5 (UI penuh di form Produk Jual, sync-safe utk histori order)
- [ ] Sistem shift kasir dengan buka-tutup shift (Setoran Kasir Tahap 5 sengaja per HARI, bukan per shift, karena ini belum ada)
- [x] Setoran cabang ke HO dengan approval workflow — ✅ Tahap 5 (Setoran Kasir, auto-hitung dari `order_payments`, scope kas tunai)
- [x] Dashboard owner dengan info setoran belum/sudah — ✅ Tahap 6 (4 card + trend 7 hari + 2 tabel alert di `dashboard/pusat.blade.php`)

### 12.4 Simplifikasi Disengaja di Tahap 2.5 (Kandidat Penyempurnaan Nanti)
- [ ] Preview kombinasi varian di form Produk Jual masih manual (tombol "Update Preview Kombinasi"), belum live-update tiap keystroke — cukup untuk kebutuhan sekarang, bisa di-upgrade ke reaktif kalau dirasa kurang nyaman dipakai kasir/admin
- [ ] `ItemVariant` yang di-toggle off lalu di-toggle-on lagi dgn kombinasi PERSIS sama akan membuat row BARU (bukan restore row lama yg soft-deleted) — desain sengaja simple demi keamanan data, efek sampingnya `item_variants` bisa sedikit menumpuk row trashed dari siklus tambah-hapus-tambah atribut yang sama berulang kali (harmless, tidak mengganggu fungsi)
- [ ] Kalkulator resep (estimasi HPP per N pcs produksi) di form Produk Jual pakai `harga_beli_terakhir` (snapshot terakhir), bukan FIFO batch cost aktual — cukup akurat utk preview kasar sebelum simpan, HPP order sungguhan tetap dihitung FIFO oleh `PenjualanService` seperti biasa

### 12.5 Panduan & Tooltip — ✅ Backfill Selesai (2026-09-16)
- [x] Panduan Master Bahan Baku, Master Produk Jual, Setoran Kasir, Dashboard Owner, Laporan Setoran Kasir, Pengaturan Umum (Branding) — 6 slug baru
- [x] Rewrite panduan `item-varian` (hapus "Coming Soon", tulis ulang sesuai UI live)
- [x] 20 tooltip baru, di-embed ke `<x-tooltip>` di view terkait (bukan cuma masuk DB)
- [x] Fix bug double-escape di komponen `tooltip.blade.php` (ditemukan saat backfill ini)
- [x] Embed tombol Cara Pakai (`<x-panduan-button>`) di 13 halaman fitur baru — gap ditemukan test manual SETELAH backfill konten selesai, ditutup di sesi terpisah (2026-09-16)
- [ ] Panduan untuk sistem shift kasir (belum relevan — fiturnya sendiri belum ada, lihat 12.3)

### 12.6 Bug Fix Ronde 2 — Temuan Test Manual Final — ✅ Selesai (2026-09-15)
- [x] Bug 1: Dropdown Cabang Aktif — root cause data (pivot cabang_user), bukan CSS — lihat [[4.13]]
- [x] Bug 2: Bill Tersimpan POS — posisi + batalkan (permission baru) + modal bayar lengkap — lihat [[4.13]]
- [x] Bug 3 🔴 KRITIS: Data Ghost Produk Jual — root cause nested `<form>` — lihat [[4.13]], [[4.14]]
- [x] Bug 4: Audit sync menyeluruh (187 route smoke test + guard rail nested-form + Data Terhapus coverage) — lihat [[4.13]]
- [x] Bug 5 (bonus): nested form di `notifikasi/index.blade.php` (silent failure "Tandai Semua Dibaca") — lihat [[4.13]], [[4.14]]
- [x] Bug 6 (bonus): crash 500 Laporan Eksekutif kalau cabang/periode tanpa penjualan (`margin_persen` null) — lihat [[4.13]]

**Tidak ada item yang di-DEFER** — ke-6 bug/temuan di ronde ini semuanya selesai difix + ditest di sesi yang sama, 0 TODO baru tersisa dari ronde ini.

### 12.7 Rename Jenis Menu & Master Bumbu Pusat — ✅ Selesai (2026-09-15)
- [x] Rename label "Jenis Olahan" → "Jenis Menu" (sidebar, halaman, Laporan Laba Rugi, tooltip, panduan) — lihat [[4.15]]
- [x] Rename label "Resep Bumbu Standar" → "Master Bumbu Pusat" + panduan ditulis ulang total (hapus deskripsi alur jasa-giling lama yang sudah mati) — lihat [[4.15]]
- [x] Kolom baru "Produk Terhubung" di tabel Master Bumbu Pusat (display-only, eager-load relasi `item` yang sudah ada)
- [x] Info box modal "Tambah Kategori Baru" (di menu Master Barang Lengkap) — jelaskan aturan visibility kategori di POS
- [x] ~~TODO: tombol "Import dari Bumbu Pusat" di form Produk Jual~~ — **SELESAI DIKERJAKAN 2026-09-17**, lihat [[4.16]]

### 12.8 Fitur Import dari Bumbu Pusat — ✅ Selesai (2026-09-17)
- [x] Migration: `resep_bumbu_items.item_id` nullable + kolom baru `resep_bumbu_ref_id` — lihat [[4.16]]
- [x] Modal picker (search + list Master Bumbu aktif) di form Produk Jual
- [x] HPP & potong stok live-calculated utk baris linked (auto-update tanpa cache/job)
- [x] Anti cyclic-reference by construction (1 level kedalaman maksimal)
- [x] Panduan `produk-jual` + tooltip baru + 21 test (feature+edge case+regresi), 182 test total lintas fase PASS

### 12.9 Improvement Test Manual Production: Rename Label + Hapus Gojek/Grab — ✅ Selesai (2026-09-18)
- [x] Rename label POS "Nama (jika tidak terdaftar)" → "Nama" — lihat [[4.17]]
- [x] Hapus `Gojek`/`Grab` dari `TipePembayaran` enum + migration incremental reversible (data-migrate + alter enum) — lihat [[4.17]]
- [x] Update validasi, breakdown Setoran Kasir, UI POS (~10 titik), UI Setoran Kasir, panduan (6 lokasi)
- [x] 12 test baru + 1 assertion test lama diupdate (perubahan disengaja), 194 test total lintas fase PASS

### 12.10 Fix Foto Produk Tidak Tampil di Production (Rumah Web) — ✅ Selesai (2026-09-16)
- [x] Root cause audit: `.htaccess` bawaan blokir `/storage/*` + URL foto hardcode `asset('storage/...)` — lihat [[4.18]]
- [x] Override `asset()` global via `App\Support\StorageAwareUrlGenerator` + `AppServiceProvider::register()`
- [x] Route baru `/asset/{path}` (`StorageAssetController`) stream file dari `storage/app/public/`
- [x] Script `public/clear-cache.php` utk deploy shared hosting tanpa terminal/SSH
- [x] 11 test baru, 205 test total lintas fase PASS (0 regresi, termasuk smoke-test 187 route)
- [ ] **TODO opsional (tidak dikerjakan, di luar scope)**: `.htaccess` bawaan (`RewriteRule ^storage/ - [L,NC]`) masih ada apa adanya — tidak berbahaya (cuma jadi dead-weight di production krn Root Cause B sudah dihindari via `/asset/` bukan `/storage/`), tapi kalau mau benar-benar rapi bisa ditambah `RewriteCond %{REQUEST_FILENAME} -f` di depan rule itu supaya cuma aktif kalau file/symlink beneran ada (self-healing utk kedua environment). Owner declined edit `.htaccess` production langsung (risk lebih tinggi, sulit diverifikasi tanpa akses server) demi solusi Approach B yang murni level aplikasi.

### 12.11 UI Preview "Harga Master" & "Subtotal" di Section Resep Produk Jual — ✅ Selesai (2026-09-19)
- [x] Kolom "Harga Master" + "Subtotal" (readonly, live JS preview, raw multiply tanpa konversi — sengaja, lihat [[4.19]])
- [x] Placeholder "— (lihat Simulasi Produksi)" utk baris resep ter-link ke Master Bumbu Pusat
- [x] Info alert `alert-warning` + link Master Bahan Baku; rename kolom "Harga" → "Mode Harga"
- [x] Fix bug Blade compile (`@json()` dgn ekspresi kompleks) — dipindah ke `@php` block, lihat [[4.19]]
- [x] 12 test baru, 217 test total lintas fase PASS (0 regresi)
- [x] **Bug Fix Ronde 3 (2026-09-19)**: Simulasi Produksi baca DB bukan form real-time (Bug A) + footer Total HPP hilang (Bug B) — lihat addendum [[4.19]]. 8 test baru, 225 test total lintas fase PASS (0 regresi).
- [x] **Simplifikasi Ronde 4 (2026-09-19)**: format qty tanpa titik-ribuan (`formatQtyInput`) + konsolidasi 2 mode Total HPP jadi 1 — lihat addendum kedua [[4.19]]. 4 test diupdate/ditambah, 227 test total lintas fase PASS.
- [x] **Bug Fix Ronde 5 (2026-09-19)**: subtotal Bumbu Pusat selalu "—" di halaman Create (endpoint `kalkulator-resep` butuh produk tersimpan, fix: endpoint baru `preview-bumbu/{bumbu}` decoupled dari Item) + Total HPP salah jumlah (`parseFloat("1.200")` dibaca 1.2 bukan 1200, fix: buang semua non-digit sebelum parse) — lihat addendum ketiga [[4.19]]. 8 test baru, 235 test total lintas fase PASS (0 regresi).
- [x] **Bug harga_jual di Master Bumbu Pusat SELESAI (2026-09-29)** — `ResepBumbuItem::getTotalHargaMasterAttribute()` sekarang pakai `harga_beli_terakhir`. Lihat [[4.31]].

### 12.12 Sprint 2 (Belum Dikerjakan) — Konversi Satuan Foolproof di Kalkulator Resep

**Root cause** (temuan audit [[4.19]], BELUM difix): `MasterProdukJualController::kalkulatorResep()` mengalikan `qty_per_unit` mentah dgn `harga_beli_terakhir` (Rp/kg) tanpa konversi satuan (gram/ml/ons → kg) — beda dari `expandKeBahanMentah()` (dipakai POS checkout riil) yang SUDAH benar pakai `getQtyPerUnitDalamKgAttribute()`. Root cause fundamentalnya: satuan disimpan sebagai string bebas (`gram`, `ml`, `ons`, `kg`, `pcs`, dst) TANPA konsep "family" — tidak ada cara sistem tahu "gram" itu 1/1000 dari "kg" secara terstruktur, konversi cuma hardcode di 1 accessor.

**Rencana implementasi (Opsi A, ~4 jam estimasi)**:
1. Kolom baru NULLABLE `items.satuan_family` (enum: `berat`/`volume`/`pcs`/null) — nullable supaya data existing tidak perlu di-backfill paksa, default null = behavior lama (unchanged, backward compat).
2. Service baru `KonversiSatuanService` (atau extend `ResepBumbuItem` accessor existing) — mapping unit→base-unit per family (`berat`: gram/ons/kg → kg; `volume`: ml/liter → liter) dgn faktor konversi eksplisit, dipakai KEDUA jalur (`kalkulatorResep()` DAN `expandKeBahanMentah()`) supaya konsisten 1 sumber logic, tidak duplikat seperti sekarang.
3. `kalkulatorResep()` diubah pakai service ini utk hitung qty×harga (bukan raw multiply) — HPP preview jadi akurat sesuai contoh Owner (Isian Ayam 45rb/kg × 35gram = Rp1.575, bukan Rp1.575.000).
4. UI Master Bahan Baku: dropdown satuan dibatasi ke daftar per family (bukan free-text) utk item baru — data existing free-text tetap jalan apa adanya (tidak retroactive).

**Alasan defer**: data existing masih manageable secara manual (Owner bisa cek+benerin via UI), dan UI improvement Opsi B ([[12.11]]) sudah cukup utk kebutuhan jangka pendek (anomali data langsung kelihatan visual sebelum Simpan). Effort ~4 jam dianggap belum prioritas dibanding fitur/bug lain yang lebih mendesak per 2026-09-19.

### 12.13 Sprint 3 (Belum Dikerjakan) — Rapikan Export Excel + Tambah Export PDF di 20+ Menu Laporan

**Latar belakang** (laporan Owner 2026-09-21, lihat [[4.23]] utk 1 item yang SUDAH difix — "Laporan → Keuangan"): audit menyeluruh menu Laporan menemukan pola export Excel yang TIDAK KONSISTEN di seluruh project — sebagian pakai `Excel::download()` (maatwebsite/excel, xlsx sungguhan, RAPI), sebagian pakai `fputcsv()` manual dgn ekstensi `.xls` (CSV bertopeng, "1 kolom banyak isi" kalau locale Excel beda — root cause persis sama dgn [[4.23]]), dan banyak yang BELUM PUNYA export PDF sama sekali walau `barryvdh/laravel-dompdf` sudah jadi dependency project.

**Daftar lengkap dari Owner** (22 menu, per 2026-09-21):
| Menu | Excel | PDF |
|------|-------|-----|
| Penjualan | belum rapi | belum ada |
| Stok | belum rapi | belum ada |
| Keuangan (`laporan.keuangan.*`) | ✅ **SUDAH DIFIX** [[4.23]] | belum ada |
| HR / SDM | belum rapi | belum ada |
| Aset | belum rapi | belum ada |
| BEP | belum ada | belum ada |
| BEP Otomatis | belum ada | ✅ sudah baik |
| Transfer/Perpindahan Dana | belum rapi | belum ada |
| Per Kategori | belum rapi | belum ada |
| Audit Bukti | belum rapi | belum ada |
| Saldo Kas | belum rapi | belum ada |
| Setoran Harian | belum rapi | belum ada |
| Komisi Sales | belum rapi | belum ada |
| Laba Rugi (`laporan.laba-rugi.*`, BEDA dari Keuangan) | belum rapi | belum ada |
| Laba Rugi Formal | belum ada | ✅ sudah baik |
| Neraca | belum ada | ✅ sudah baik |
| Buku Besar | belum ada | belum ada |
| Laporan Eksekutif | belum ada | ✅ sudah baik |
| Simulator BEP | belum ada | belum ada |
| Analisa Jam Ramai | belum ada | belum ada |
| Pemakaian Perlengkapan | belum ada | belum ada |
| Laporan Setoran Kasir | belum rapi | belum ada |
| Cabang vs Cabang | belum rapi | belum ada |

**Kenapa DIDEFER (bukan dikerjakan sekaligus)**: scope terlalu besar utk 1 sesi (22 menu × 2 kemungkinan kerjaan = puluhan file controller+Export class+view baru), tiap menu struktur datanya beda (sebagian tabular sederhana cocok `WithMapping` biasa, sebagian multi-section kayak Laba Rugi butuh custom `FromArray` seperti [[4.23]], sebagian py grafik yang tidak relevan di Excel/PDF). **Rencana kerja**: pola yang SUDAH proven dari [[4.23]] (`TransaksiKeuanganExport`/`LaporanLabaRugiExport` sbg referensi) dipakai ulang per menu, dikerjakan bertahap per-batch (mis. per kelompok "Keuangan" dulu, lalu "Operasional", dst) dgn approval Owner tiap batch — BUKAN big-bang 1 commit raksasa yang susah di-review/di-test.

**Effort awal (rough estimate, perlu di-refine per batch)**: rata-rata ~30-45 menit/menu utk rapikan Excel (kalau struktur data mirip yang sudah ada), ~20-30 menit/menu tambahan utk PDF (kalau ada view print-friendly yang bisa direuse via `dompdf`) — total kasar 15-25 jam utk semua 22 menu, TIDAK termasuk waktu test tiap menu.

**✅ Batch 1 SELESAI SEMUA (2026-09-21)** — 5 dari 5 menu Prioritas 1 (Penjualan, Setoran Kasir [[4.24]]; Setoran Harian/Rekap, Stok 3 sub-view, Laba Rugi Produksi [[4.25]]). Sisa 17 menu Laporan lain (Prioritas 2/3) MENYUSUL sbg Batch 2/3 di sesi terpisah.

**✅ Batch 2 SELESAI 9 dari 12 non-blocker (2026-09-22, lihat [[4.26]])** — Per Kategori, Transfer/Perpindahan Dana, Saldo Kas, Cabang vs Cabang, HR/SDM (3 sub-menu, termasuk Evaluasi yang sebelumnya 0 export), Aset (2 sub-menu), BEP manual (2 sub-menu), BEP Otomatis (Excel), Neraca (Excel), Laba Rugi Formal (Excel), Buku Besar (Excel), Audit Bukti, Analisa Jam Ramai, Pemakaian Perlengkapan. **3 menu di-SKIP sbg blocker genuine** (Komisi Sales/fitur belum ada, Eksekutif/laporan narasi bukan tabular, Simulator BEP/tidak ada data server) — lihat [[12.15]] utk detail & opsi lanjutan.

---

### 12.15 Blocker Sprint 3 — 2 Menu yang TIDAK BISA Diexport Tanpa Keputusan Desain Owner (2026-09-22, diupdate)

Ditemukan saat Sprint 3 Batch 2 ([[4.26]]), bukan gap implementasi tapi genuinely butuh keputusan Owner dulu sebelum bisa dikerjakan:

1. **Laporan Komisi Sales** — fiturnya sendiri **tidak ada** di codebase (0 route/controller/view untuk "Komisi Sales" sbg laporan). Yang ada cuma field input `komisi` di record Penggajian (`penggajians.komisi`), tidak ada laporan/rekap terpisah yang mengagregasi itu. **Pertanyaan utk Owner**: apakah ini request fitur BARU (bikin laporan rekap komisi sales per periode/karyawan dari kolom itu), atau menu ini salah masuk daftar 22 menu awal?
2. **Laporan Eksekutif** — laporan komposit 9-halaman berisi ringkasan bisnis + insight narasi (BUKAN data tabular per-baris seperti laporan lain). PDF-nya sendiri sengaja didesain sbg dokumen presentasi ke Owner/stakeholder, bukan spreadsheet kerja. **Opsi utk Owner**: (a) skip permanen — laporan jenis ini memang tidak natural di-Excel-kan; (b) export PARSIAL — ambil cuma bagian yang genuinely tabular (mis. tabel ringkasan per cabang di dalamnya) jadi 1 sheet Excel terpisah, sisanya tetap PDF-only.

**✅ Laporan Simulator BEP — SUDAH DISELESAIKAN (2026-09-22)**, lihat [[4.27]]: pola "snapshot" — payload input dikirim ke server, server reproduksi ulang logic hitung + generate Excel (3 sheet)/PDF. Bukan lagi blocker.

**Rekomendasi**: tanyakan ke Owner satu-per-satu saat sesi berikutnya utk 2 sisa, jangan diasumsikan sendiri — masing-masing py implikasi scope yang beda jauh.

---

### 12.14 TODO Terpisah — Tracking Running-Balance & Referensi di `stock_movements`

**Latar belakang** (ditemukan saat Sprint 3 Batch 1b/1c, [[4.25]] Blocker 2): Owner minta kolom "Sisa Stok" (running balance setelah tiap movement) dan "Referensi" (link balik ke order/adjustment/PO yang menyebabkan movement itu) di Laporan Pergerakan Stok — KEDUANYA belum ditrack sistem. **SENGAJA DISKIP** dari Sprint 3 (bukan cuma rapikan export, tapi fitur tracking baru genuinely).

**Rencana implementasi (kalau nanti dikerjakan)**:
1. Migration tambah kolom `stock_movements.stok_sesudah` (snapshot qty SETELAH movement itu diterapkan — dicatat SEKALI saat movement dibuat, bukan dihitung ulang tiap kali dibaca, supaya histori tidak berubah kalau ada koreksi data lampau) + `referensi_type`/`referensi_id` (polymorphic, pola sama `transaksi_keuangans.referensi_type/id` yang sudah ada).
2. Backfill data historis: hitung running balance mundur dari `stocks.qty` SAAT INI dikurangi/ditambah tiap movement secara kronologis terbalik — perlu hati-hati kalau ada data movement yang hilang/tidak lengkap (kemungkinan besar ADA krn fitur ini baru ditambah belakangan).
3. Titik-titik yang perlu diisi `referensi_type`/`referensi_id` saat create movement: checkout POS (referensi ke `order`), penerimaan PO (`purchase_order`), adjustment manual (`stock_adjustment` kalau ada modelnya, atau `null` utk manual murni).
4. Update `LaporanStokPergerakanExport`/`pdf-pergerakan.blade.php` tambah 2 kolom ini setelah data tersedia.

**Alasan defer**: effort backfill data historis tidak trivial (tergantung kelengkapan data movement lama), dan Laporan Stok Index sudah cukup utk cek "stok sekarang" (Owner declined tambah kompleksitas di Sprint 3, cukup kasih info alert pengarah ke situ).

---

### 12.16 TODO (Ditunda Owner 2026-11-05) — Diskon per Produk di POS

**Konteks**: saat membuat Laporan Produk ([[4.46]]) Owner bertanya soal diskon untuk produk tertentu saja. POS sekarang hanya punya **diskon 1 order** (`orders.diskon`); kasir bisa mengubah harga di keranjang tapi itu tidak tercatat sebagai diskon. **Owner memutuskan: tunda, kerjakan kalau ada permintaan dari pengguna aplikasi.**

**Rancangan yang sudah dibahas** (belum diputuskan detailnya): simpan per baris `order_items` harga normal + diskon baris + total setelah diskon (`total_harga` = setelah diskon → laporan lama otomatis benar); diskon order tetap ada; struk tampilkan harga normal, diskon, harga akhir; validasi server (diskon ≤ harga, izin). **Pertanyaan terbuka**: manual oleh kasir vs promo otomatis dari master produk (atau keduanya); persen/rupiah; izin baru "Beri diskon per produk"; per baris vs per satuan. Laporan Produk/Kategori [[4.46]] lalu membaca diskon baris + bagian diskon order.

---

## 13. CHECKLIST SEBELUM MULAI FITUR BARU

Setiap kali mulai kerja fitur baru, Claude Code WAJIB:

- [ ] Baca ulang bagian relevan di file ini
- [ ] Cek pola serupa di codebase Berkah Mulyo (yang sudah dicopy)
- [ ] Konfirmasi scope & detail ke user (kalau belum jelas)
- [ ] Bikin plan (list file yang akan diubah/dibuat)
- [ ] Cek `AUDIT_SISTEM.md` bagian yang relevan
- [ ] Eksekusi dengan test bertahap
- [ ] Update section "Status" di roadmap (bagian 5.4)
- [ ] Tambah permission + panduan + tooltip untuk fitur baru
- [ ] Cek filter cabang manual di query (SECURITY!)
- [ ] Kabari user ketika selesai & minta test

---

## 14. REFERENSI FILE PENTING

| File | Isi |
|------|-----|
| `CLAUDE.md` (ini) | Single source of truth — visi, aturan, spesifikasi |
| `CLAUDE.berkahmulyo.backup.md` | Backup CLAUDE.md dari Berkah Mulyo (jangan dihapus, untuk referensi pola) |
| `AUDIT_SISTEM.md` | Audit lengkap sistem base (Berkah Mulyo) — modul, alur, gap |
| `.env` | Config lingkungan (APP_NAME, DB, dll) |
| `routes/web.php` | 817 baris route — peta URL sistem |
| `resources/views/layouts/app.blade.php` | Sidebar utama — peta menu sistem |
| `app/Providers/AppServiceProvider.php` | Observer + Gate + Recurring trigger |
| `bootstrap/app.php` | Middleware alias + routing config |

---

**🎉 PROJECT LIVE DI PRODUCTION** (https://erp.dmentaiindonesia.com) **— Tahap 7 (Final Polish) selesai 2026-09-14, Bug Fix Ronde 2 selesai 2026-09-15, Rename Jenis Menu/Master Bumbu Pusat + Fitur Import dari Bumbu Pusat selesai 2026-09-17, Improvement Test Manual Production selesai 2026-09-18.** Semua 7 tahap roadmap tuntas + 6 bug dari test manual final (termasuk 1 KRITIS: data ghost akibat nested form) sudah difix & diguard-rail + fitur link-resep-antar-produk (anti cyclic by construction, live-calculation tanpa cache/job) sudah dibangun + POS disederhanakan jadi 3 metode pembayaran (Tunai/Transfer/QRIS, Gojek/Grab dihapus) berdasar masukan pemakaian nyata di production. 194 test lintas fase PASS (0 regresi), smoke test 187 halaman clean, dead code dibersihkan, bug BEP+Loyalty terwariskan sudah difix, error page branded, 4 alur bisnis end-to-end terverifikasi.

---

**Tahap 1 (Branding) selesai — 2026-09-13.** Nama, warna, logo, dan PWA icon sudah D'mentai. Siap eksekusi Tahap 2 - Master Data. 🚀
