# Porting dari erp-dimsum ke erp-coffeeshop — Perbaikan POS (Dimsum 4.47)

> **Untuk Claude Code yang bekerja di `D:\xampp\htdocs\erp-coffeeshop`.**
> Folder `D:\xampp\htdocs\erp-dimsum` **hanya boleh DIBACA** (buka file / `git -C D:/xampp/htdocs/erp-dimsum show ...`). Jangan menulis apa pun ke sana, jangan sentuh database `erp_dimsum`.
> Coffeeshop sudah go-live: **diskusi dulu dengan Owner sebelum mengubah**, kerjakan per tahap dengan test, dan **JANGAN push/deploy ke production sebelum Owner setuju**.

## 1. Sumber di erp-dimsum

- Commit **`a6d9ba4`** — lihat dengan `git -C D:/xampp/htdocs/erp-dimsum show a6d9ba4`
- Penjelasan: `D:\xampp\htdocs\erp-dimsum\CLAUDE.md` → **section 4.47**
- File utama: `resources/views/penjualan/pos.blade.php`, `app/Services/PenjualanService.php` (`resolveItemRows`), `database/seeders/PosUbahHargaSeeder.php`, `public/deploy-447.php`, `tests/Feature/Tahap7/PosTabletPanelBayarTest.php`, `tests/Feature/Tahap7/PosUbahHargaDanGabungTest.php`

## 2. Yang harus dibuat (keputusan Owner — sama dengan dimsum)

### A. Panel "Ringkasan & Pembayaran" di mode Tablet tidak ikut ter-scroll
- HANYA saat `body.pos-tablet-mode` dan layar ≥ 768px. **Mode Desktop tidak boleh berubah.**
- Kolom produk (kiri) dan panel bayar (kanan) selalu berdampingan (±58% / 42%), juga di tablet yang lebarnya < 992px.
- Panel bayar `position: sticky` setinggi layar (`max-height: calc(100vh - 1rem)`, flex column). Isinya (subtotal, diskon, metode bayar, jumlah bayar, kembalian, opsi struk) **scroll sendiri**.
- Bagian bawah panel dipisah jadi "kaki" yang **selalu terlihat**: TOTAL BAYAR (angka disalin dari tampilan total lewat MutationObserver, supaya fungsi hitung total tidak perlu diubah), tombol PROSES ORDER, Save Bill, Lihat Riwayat. Tombol harus tetap di dalam `<form>` transaksi.
- Di Desktop, kaki itu tampil persis di posisi lama (di bawah isi panel).

### B. Harga/Unit di Item Order terkunci — izin baru di Role & Hak Akses
- Izin baru `order.ubah_harga` — "Ubah Harga Jual di POS (kolom Harga/Unit)", grup penjualan. Seeder ADDITIVE (`firstOrCreate`), **tidak dicentang untuk role mana pun** (keputusan Owner dimsum — tanyakan Owner coffeeshop apakah sama). Owner selalu bisa lewat `Gate::before`. **Jangan jalankan `RolePermissionSeeder` di production.**
- Form POS: tanpa izin → input Harga/Unit `readonly` + ikon gembok + tooltip penjelasan. Potongan harga tetap lewat kolom Diskon.
- **Server wajib mengunci juga** (`PenjualanService::resolveItemRows`): tanpa izin, harga dari form DIABAIKAN → pakai harga produk (harga per cabang kalau ada) / harga varian. Dengan izin, harga dari form dipakai (termasuk baris varian). Tidak ada user login = dianggap tidak berizin.
- Test lama yang memanggil service dengan harga berbeda dari harga produk perlu `actingAs(owner)` (perubahan disengaja).

### C. Produk yang sama digabung jadi qty
- Klik produk + varian yang sama yang sudah ada di Item Order dan **catatan item-nya kosong** → qty +1 (baris berkedip sebentar), bukan baris baru. Kalau baris itu sudah punya catatan item → baris baru (mis. 1 less sugar, 1 normal).
- Varian berbeda = baris berbeda.
- Pengisian keranjang otomatis yang men-set qty sendiri (di dimsum: "Isi Keranjang dari PO") tetap membuat baris sendiri.

### D. Ringkasan & Pembayaran menampilkan harga satuan seperti struk
```
Es Kopi Susu
2 cup x Rp 18.000                 Rp 36.000
```

### E. Panduan + tooltip + deploy
- Tambah bagian ke panduan POS (pakai marker agar idempotent) + tooltip harga.
- Script deploy sekali pakai (seeder izin/panduan/tooltip + clear & rebuild cache, `?confirm=...`, file lock, mencetak role pemegang izin) — contoh `public/deploy-447.php` dimsum.

## 3. Perbedaan coffeeshop yang SUDAH terlihat (cek lagi sebelum menulis kode)

1. Panel bayar coffeeshop ber-id **`cardRingkasan`** (dimsum: `panelBayar`); mode Tablet sudah ada (`body.pos-tablet-mode`). Sesuaikan id & struktur.
2. `resolveItemRows(array $items)` coffeeshop **tidak menerima cabang**, dan varian memakai `$variant->harga_efektif`. Cek bagaimana harga per cabang ditentukan di coffeeshop (pivot `item_cabang` / harga override) supaya harga yang dipaksa server = harga yang tampil di POS cabang itu.
3. Coffeeshop punya **Bill meja, QR Order, dan Layout Meja**. Periksa SEMUA jalur yang membuat/menambah item order (simpan bill, tambah item ke bill meja, QR Order dari pelanggan, edit order) — pastikan penguncian harga berlaku di semuanya dan tidak merusak alur yang sudah jalan. QR Order dari pelanggan (tanpa login) seharusnya selalu harga produk.
4. Fungsi keranjang coffeeshop: `tambahkanItemKeForm(item, variant)`, `hitungTotal()` — mirip dimsum, tapi cek apakah ada modifier/add-on (gula, es, topping) yang ikut menentukan "baris sama" selain varian & catatan. Kalau ada, tanyakan Owner aturan gabungnya.
5. Nomor section CLAUDE.md coffeeshop berikutnya kemungkinan **4.52** — cek dulu.

## 4. Urutan kerja yang disarankan

0. Baca CLAUDE.md coffeeshop + dimsum 4.47 + commit `a6d9ba4`, audit perbedaan di atas, jelaskan rencana & pertanyaan ke Owner, tunggu persetujuan.
1. Tahap A (panel tablet) → minta Owner coba di tablet.
2. Tahap B (izin + kunci harga form & server, semua jalur order) + test.
3. Tahap C (gabung baris) + D (harga satuan di ringkasan) + test / simulasi JS.
4. Panduan, tooltip, script deploy, catatan CLAUDE.md. Regresi test lengkap.
5. Commit lokal → minta izin Owner → push → deploy.
