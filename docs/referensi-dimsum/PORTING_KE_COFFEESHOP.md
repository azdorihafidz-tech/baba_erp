# Porting dari erp-dimsum ke erp-coffeeshop — 3 Laporan di Menu Penjualan (Dimsum 4.46)

> **Untuk Claude Code yang bekerja di `D:\xampp\htdocs\erp-coffeeshop`.**
> Folder `D:\xampp\htdocs\erp-dimsum` **hanya boleh DIBACA** (buka file / `git -C D:/xampp/htdocs/erp-dimsum show ...`). Jangan menulis apa pun ke sana, jangan sentuh database `erp_dimsum`.
> Coffeeshop sudah go-live: **diskusi dulu dengan Owner sebelum mengubah**, kerjakan per tahap dengan test, dan **JANGAN deploy ke production sebelum Owner setuju**.

## 1. Sumber di erp-dimsum

Bagian penjelasan lengkap: `D:\xampp\htdocs\erp-dimsum\CLAUDE.md` → **section 4.46** (dan TODO 12.16).

| Commit dimsum | Isi |
|---|---|
| `089d1e7` | Laporan Transaksi (tahun → bulan → hari → transaksi → struk), izin, keuntungan tersembunyi |
| `111a0bf` | Laporan Transaksi: rentang tanggal (bisa diklik turun), grafik batang/garis, `CabangAktif::untukLaporan()` |
| `75c12ae` | Laporan Penjualan Produk (qty, diskon dibagi per porsi harga, varian, grafik 10 teratas) |
| `f7cd541` | Laporan Penjualan Kategori (grafik donat, produk per kategori) |
| `3b711b2` | Script deploy `public/deploy-446.php` + catatan CLAUDE.md |

Lihat semua perubahan: `git -C D:/xampp/htdocs/erp-dimsum diff cab667c 3b711b2`

File yang dibuat/diubah di dimsum:

- `app/Support/PeriodeLaporan.php`, `app/Support/CabangAktif.php` (method `untukLaporan()`)
- `app/Services/LaporanTransaksiKasirService.php`, `app/Services/LaporanBarangKasirService.php`
- `app/Http/Controllers/LaporanTransaksiKasirController.php`, `LaporanProdukKasirController.php`, `LaporanKategoriKasirController.php`
- `app/Exports/LaporanTransaksiKasirExport.php`, `LaporanBarangKasirExport.php`
- `resources/views/penjualan/laporan/{transaksi,produk,kategori,_periode,transaksi-pdf,barang-pdf}.blade.php`
- `database/seeders/LaporanPenjualanKasirSeeder.php` (+ didaftarkan di `PermissionSeeder`, `RolePermissionSeeder`, `DatabaseSeeder`, `GoLiveSeeder`)
- `routes/web.php` (3 route, diletakkan SEBELUM `penjualan.index` / route `penjualan/{order}`)
- `resources/views/layouts/app.blade.php` (3 link di bagian sidebar **Penjualan**, `@canany` bagian itu ikut 3 izin baru)
- `app/Http/Controllers/PenjualanController.php` (`pastikanBolehLihat()` menerima izin Laporan Transaksi supaya struk bisa dibuka dari laporan)
- `public/deploy-446.php`
- `tests/Feature/Tahap7/Laporan{Transaksi,Produk,Kategori}KasirTest.php`

## 2. Yang harus dibuat (keputusan Owner — sama dengan dimsum)

1. **Laporan Transaksi** (sidebar Penjualan, bisa dibuka kasir)
   - Pilihan cepat Hari ini / Bulan ini / Tahun ini / Selama ini + **rentang tanggal**.
   - Bertingkat, mulai sesuai pilihan: Selama ini (per tahun) → Tahun (per bulan) → Bulan (per hari) → Hari (daftar transaksi + grafik per jam) → klik transaksi = **struk** (modal struk yang sudah ada).
   - Jejak (breadcrumb) + tombol **Kembali**. Rentang < 92 hari tampil per hari, lebih panjang per bulan; klik bulan = sub-rentang yang dipotong ke rentang asal.
   - Tiap tingkat: grafik (batang/garis, pilihan disimpan di localStorage), total transaksi, pendapatan, keuntungan, tabel per periode. Periode yang belum terjadi tidak ditampilkan.
   - Pendapatan = `orders.total_bayar`. Keuntungan = pendapatan − Σ `order_items.hpp`.
2. **Laporan Penjualan Produk**: qty, penjualan (sebelum diskon), diskon, pendapatan (setelah diskon), modal, keuntungan, margin per produk. Filter kategori + cari + urut. Grafik 10 teratas (ukuran qty/pendapatan/keuntungan). Varian digabung per produk, bisa dibuka (dirinci kalau > 1 varian terjual).
   - **Diskon order dibagi per porsi harga**: `orders.diskon × item.total_harga / orders.total_harga`.
   - Service charge + take away fee ditampilkan sebagai **baris terpisah**, sehingga Σ pendapatan produk + biaya tambahan = pendapatan Laporan Transaksi (selisih harus 0).
3. **Laporan Penjualan Kategori**: angka per kategori = jumlah produk di dalamnya (selalu sama dengan Laporan Produk). Grafik donat porsi per kategori (total di tengah, legenda), klik kategori → daftar produknya, tautan ke Laporan Produk yang tersaring kategori itu. "Tanpa kategori" = filter `kategori=-1`.
4. **Export Excel & PDF** di ketiga laporan.
5. **Panduan** (tombol Cara Pakai di tiap halaman) + **tooltip**.
6. **Diskon per produk di POS TIDAK dibuat** (ditunda Owner sampai ada permintaan pengguna — dimsum CLAUDE.md 12.16).

## 3. Hak akses (aturan penting)

Seeder ADDITIVE (`firstOrCreate` + `insertOrIgnore`, jangan menghapus centangan yang sudah diatur Owner). **Jangan jalankan `RolePermissionSeeder` di production.**

| Izin | Bawaan |
|---|---|
| `penjualan.laporan_transaksi.view` | role yang sudah punya `order.view` (termasuk role buatan) + kasir, manajer_cabang, admin_pusat |
| `penjualan.laporan_produk.view` | sama |
| `penjualan.laporan_kategori.view` | sama |
| `penjualan.laporan.keuntungan` ("Lihat Keuntungan di Laporan Penjualan (Transaksi/Produk/Kategori)") | admin_pusat, manajer_cabang (Owner selalu bisa) |

- **Keuntungan tidak boleh terlihat kasir**: controller **membuang** kunci `hpp`/`keuntungan`/`margin` dari data (baris, rincian varian/produk, total) sebelum dikirim ke view & export — bukan sekadar disembunyikan di HTML. Bagian JS grafik yang menyebut keuntungan dibungkus `@if($lihatUntung)`. Urut berdasarkan keuntungan ditolak untuk yang tidak berhak. Kolom Modal/Keuntungan/Margin tidak ada di Excel/PDF.
- **Cabang**: semua query wajib filter cabang manual. Owner/admin pusat ikut cabang aktif di header ("Semua Cabang" = semua). Role yang tidak boleh semua cabang **selalu dibatasi ke cabangnya sendiri** — kalau sesinya menunjuk cabang lain (sesi basi), kembalikan ke cabangnya (di dimsum: `CabangAktif::untukLaporan()`; kalau tidak punya cabang → id -1 = kosong).
- Izin di-cache (`all_permission_names`) → seeder & deploy wajib membuang cache.

## 4. Perbedaan coffeeshop yang SUDAH terlihat (cek lagi sebelum menulis kode)

1. **Order sah**: dimsum memakai scope `Order::penjualanSah()` / `StatusOrder::bukanPenjualan()`. Coffeeshop sudah punya padanannya sendiri: **`Order::terhitung()` / `STATUS_TIDAK_DIHITUNG`** (coffeeshop 4.48). Pakai milik coffeeshop, jangan bawa scope dimsum. Untuk query `DB::table` pakai kondisi yang sama dengan `scopeTerhitung` (status + `orders.deleted_at IS NULL`).
2. **Status `proses`** ada di coffeeshop (tidak ada di dimsum). Ikuti aturan `STATUS_TIDAK_DIHITUNG` yang sudah berlaku; kalau ragu apakah order `proses` dihitung sebagai penjualan, **tanya Owner**.
3. **Tidak ada `app/Support/CabangAktif.php`** di coffeeshop. Cabang aktif ada di session `active_cabang_id` (`CabangMiddleware`). Buat helper kecil setara `untukLaporan()` atau pakai pola cabang yang sudah dipakai laporan coffeeshop lain — jangan membawa seluruh `CabangAktif` dimsum.
4. **Data barang**: dimsum memecah barang per cabang (4.39) sehingga produk dikelompokkan per `kode_item`. Coffeeshop memakai pivot `item_cabang` (satu barang untuk banyak cabang) → kelompokkan per `item_id` (atau `kode_item` kalau itu yang benar di data coffeeshop — periksa).
5. **Bill meja / QR Order / Layout Meja** hanya ada di coffeeshop. Pastikan bill yang belum dibayar tidak terhitung, dan nilai order yang dibayar lewat bill/QR tetap masuk dengan benar (cek kolom `total_bayar`, `diskon`, `service_charge`, `take_away_fee`, `order_items.hpp` di data coffeeshop).
6. **Modal struk**: coffeeshop punya `window.openStrukModal(orderId, copies, tampilHargaStruk)` (`layouts/app.blade.php`). Cek gerbang akses endpoint strukenya dan izinkan pemegang izin Laporan Transaksi, dengan cek cabang.
7. **Bahasa tanggal**: dimsum `APP_LOCALE=en` → `translatedFormat()` keluar bahasa Inggris, jadi dimsum memakai nama hari/bulan Indonesia sendiri (`LaporanTransaksiKasirService::tanggal()/labelRentang()`). Cek locale coffeeshop.
8. Nama role & role buatan (`custom_roles`), struktur sidebar, layout PDF laporan, trait style Excel — ikuti yang ada di coffeeshop.
9. Nomor section CLAUDE.md coffeeshop berikutnya kemungkinan **4.49** — cek dulu.

## 5. Urutan kerja yang disarankan

0. Baca CLAUDE.md coffeeshop + dimsum 4.46, audit perbedaan di atas, laporkan ke Owner & tunggu persetujuan.
1. Tahap 1 — Izin + Laporan Transaksi (termasuk rentang, struk, export) + test.
2. Tahap 2 — Laporan Produk + test (cek di data: Σ produk + biaya tambahan = Laporan Transaksi).
3. Tahap 3 — Laporan Kategori + test (total = Laporan Produk).
4. Tahap 4 — panduan + tooltip + script deploy sekali pakai (seeder izin/panduan/tooltip + cache, ada konfirmasi `?confirm=...` dan file lock, mencetak role yang mendapat izin) + catatan CLAUDE.md.
5. Uji dengan akun kasir (keuntungan tidak tampil di halaman, sumber HTML, Excel, PDF), manajer, admin pusat, owner. Regresi test lengkap.
6. Commit lokal → minta izin Owner → push → deploy.
