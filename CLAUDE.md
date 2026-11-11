# ERP BABA PARFUME

ERP usaha parfum BABA: produk & bahan, produksi parfum, stok, pembelian bahan baku, order antar Pemegang Stok, POS, keuangan, HR, absensi wajah, aset.

- **Rancangan yang disetujui Owner (11 Nov 2026):** `../_rancangan/RANCANGAN-ERP-BABA.md` — wajib dibaca sebelum membangun fitur.
- **Aturan workspace BABA** (cara kerja per tahap, panduan wajib, istilah Upline/Downline, hosting lewat terminal): `../CLAUDE.md`.
- **Asal kode:** salinan erp-dimsum (commit `11fd5b5`, 10 Nov 2026), dirapikan untuk BABA. Catatan teknis lengkap dimsum (temuan audit, pola, jebakan): `docs/referensi-dimsum/CLAUDE.dimsum.md` — **aturan teknisnya tetap berlaku** kecuali ditulis lain di sini.

## Lingkungan lokal

| | |
|---|---|
| Folder | `D:\xampp\htdocs\BABA\baba_erp` |
| Database | `db_baba_erp` (tes: `db_baba_erp_test`, dipakai `.env.testing`) |
| Jalankan | `php artisan serve --port=8003` → `http://localhost:8003` |
| Login awal | Owner `owner@babaparfume.local`, password di `.env` `GOLIVE_OWNER_PASSWORD` (lokal saja) |
| Repo | `https://github.com/azdorihafidz-tech/baba_erp` (private), branch `main` |
| Stack | Laravel 12, PHP 8.2, Blade + Bootstrap 5 (sama dengan dimsum) |

Port lain di komputer ini: 8000 = API aplikasi member, 8001 = erp-dimsum, 8002 = erp-coffeeshop, 3000 = web member, 3001 = chat.

## Aturan wajib

1. **JANGAN menyentuh** folder/DB project lain: `D:\xampp\htdocs\erp-dimsum` (`erp_dimsum`, produksi D'mentai), `erp-coffeeshop`, `erp-manajemenberkahmulyo` (`erp_berkahmulyo`, produksi). Hanya baca untuk referensi/porting.
2. Database ERP **baru** (bukan salinan produksi): migration boleh dijalankan di `db_baba_erp`. Data awal dari `GoLiveSeeder` (Owner + referensi, tanpa dummy). Jangan `migrate:fresh` setelah ada data yang diisi user tanpa izin.
3. Setiap fitur/menu baru: permission (`PermissionSeeder` + `RolePermissionSeeder`), panduan + tooltip (seeder), tombol **Cara Pakai** — pola dimsum §3.6.
4. Filter cabang/Pemegang Stok **manual di setiap query** (dimsum §3.4, `CabangScope` dormant) — security-critical.
5. Istilah tampilan: **Upline/Downline**, **Pemegang Stok**, **RO** (jumlah botol).
6. Kerja per tahap → uji (HP & desktop, Owner/karyawan/member) → commit & push → laporan.

## Status

- **Tahap 0 bagian 1 (11 Nov 2026):** folder & `.env` diarahkan ke `db_baba_erp`, APP_KEY baru, migration + `GoLiveSeeder` (110 tabel, 1 Owner, 216 permission, 59 COA, Gudang Pusat `HO-01`, 90 panduan), skrip deploy satu-kali dimsum & `.cpanel.yml` dihapus, riwayat git baru (tanpa riwayat dimsum).
- **Tahap 0 bagian 2 — tampilan BABA & buang menu dimsum (11 Nov 2026):** `Branding::WARNA_BAWAAN` = hitam-emas (`#E6B422` / `#141414` / `#F6F4EF`, sama dengan aplikasi member; tema Oranye lama tetap bisa dipilih). Logo/favicon/ikon PWA 8 ukuran/splash iPhone = logo BABA (`public/images/`, dibuat dari `baba_web/public/logo.png`). `config/laravelpwa.php` & `config/golive.php` BABA. `GoLiveSeeder` → `Gudang Pusat BABA` + identitas `BABA PARFUME Indonesia` / `BABA PARFUME` / `Indonesia`. Sidebar: **Jenis Menu, Master Bumbu Pusat** (diganti Resep Parfum di Tahap 1) dan grup **Antrian Produksi** (Cek/Kelola Antrian, Mode Produksi, Display TV) dihapus; migration `2026_11_11_000001` = antrian mati bawaan & di semua cabang (kode antrian di POS masih ada → dibereskan Tahap 3). Program Loyalty **tetap** (dipakai toko BABA). Database tes `db_baba_erp_test`: migrate + seeder DatabaseSeeder minus `UserSeeder` + COA/Keamanan/Absensi/Antrian/Perlengkapan. Regresi: `php artisan test tests/Feature/Tahap25 tests/Feature/Tahap5 tests/Feature/Tahap6 tests/Feature/Tahap7` (jangan folder Auth/ExampleTest/ProfileTest: `RefreshDatabase` mengosongkan DB tes).
- Data dimsum yang ikut tersalin di `storage/` (bukti bayar, foto wajah, foto produk, logs, cache Excel) sudah dihapus atas izin user (11 Nov 2026).
- **Revisi rancangan 11 Nov 2026:** member belanja di **aplikasi member** (bukan halaman ERP); gudang memproses Pesanan Online di ERP; ERP ↔ aplikasi member lewat **API antar-server berkunci** (bukan login satu pintu). Mode RO di Pengaturan Umum (lunas / lunas + approve MVO / approve MVO saja).
- Berikutnya (Tahap 0 bagian 3): jalur API aman ERP ↔ backend aplikasi member + menu "Segera hadir" di aplikasi member.
