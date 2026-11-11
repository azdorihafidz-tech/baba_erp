<?php

namespace Database\Seeders;

use App\Models\Panduan;
use Illuminate\Database\Seeder;

/**
 * Sprint 4.33 + 4.34 + 4.35 (2026-10-08) — append section baru ke 9 panduan
 * existing untuk mencerminkan perubahan: Master Barang read-only, PO/Transfer/
 * Request tolak produk_jual, Dashboard/Laporan Stok exclude produk_jual,
 * track_stok produk jual paksa false, 4 field pindahan ke Bahan Baku form.
 *
 * Idempotent: cek marker `<!-- SPRINT434 -->` di konten; kalau sudah ada,
 * skip (tidak dobel append). Kalau konten diubah manual tanpa marker, append
 * tambahan tetap aman — append cuma tambah di akhir, bukan replace.
 */
class Sprint434PanduanUpdateSeeder extends Seeder
{
    public function run(): void
    {
        $marker = '<!-- SPRINT434 -->';

        $updates = [
            'master-barang' => <<<'MD'
## 🔄 Update 2026-10-08 — Menu ini sekarang READ-ONLY

Mulai 2026-10-08, menu **Master Barang (Lengkap)** menjadi **overview read-only** — tidak lagi dipakai untuk menambah/mengedit/menghapus barang langsung. Alasannya: supaya tidak ada data duplikat/kacau antara barang yang dibuat lewat menu ini vs menu khusus.

**Yang berubah:**
- Tombol **"Tambah Item"** tunggal **diganti 2 tombol**: `+ Bahan/Kemasan` (ke menu Bahan Baku) dan `+ Produk Jual` (ke menu Produk Jual)
- Tombol **"Hapus"** di tiap baris **dihapus total** — hapus barang lewat menu masing-masing
- Tombol **"Edit"** sekarang **redirect** otomatis ke menu yang bersangkutan sesuai tipe barang

**Alur kerja baru:**
- Mau buat bahan baku, kemasan, atau sumpit/garpu → klik **+ Bahan/Kemasan** atau buka menu **Bahan Baku & Kemasan** langsung
- Mau buat produk jual (dimsum, gyoza, drink) → klik **+ Produk Jual** atau buka menu **Produk Jual** langsung
- Menu ini tetap berguna untuk **lihat overview semua barang** lintas tipe dalam 1 tabel

MD,

            'bahan-baku' => <<<'MD'
## 🔄 Update 2026-10-08 — 4 Fitur Baru di Form

Mulai 2026-10-08, form Tambah/Edit Bahan Baku & Kemasan punya tambahan:

### 1. Dropdown "Jenis Item"
- **Bahan Baku (produksi)** — item yang masuk ke produksi (tepung, daging, saus, dsb). Default.
- **Perlengkapan (ATK/habis pakai)** — item yang tidak masuk produksi (nota kertas, label print, dsb). Dicatat beda di akuntansi sbg beban langsung.

### 2. Checkbox "Lacak Stok"
Default **centang** (track stok ketat). Uncheck untuk ATK yang tidak perlu dihitung qty-nya satu-satu (mis. isolasi, pena).

### 3. Textarea "Deskripsi"
Opsional. Catatan tambahan tentang barang (ukuran, merek pilihan, dsb).

### 4. Tombol `+` di dropdown Kategori
Klik tombol `+` di sebelah dropdown Kategori → modal **"Tambah Kategori Baru"** muncul → isi nama + kode → simpan → kategori langsung muncul di dropdown tanpa reload halaman. Sebelumnya modal ini cuma ada di menu Master Barang Lengkap.

### Catatan tentang Tipe Item
Dropdown "Tipe" di form ini sekarang **cuma 3 opsi**: Bahan Baku / Kemasan / Tambahan Gratis — opsi "Produk Jual" dihapus karena sudah ada menu Produk Jual yang khusus untuk itu.

MD,

            'produk-jual' => <<<'MD'
## 🔄 Update 2026-10-08 — Produk Jual = POS Display Only

Mulai 2026-10-08, konsep **Produk Jual** diperjelas: ini **hanya untuk tampilan POS + definisi resep**, bukan untuk melacak stok fisik.

**Konsekuensi arsitektur:**
- Field **"Lacak Stok" (track_stok) dipaksa `false` otomatis** — meski ada input/toggle di form, backend selalu simpan `false`
- Produk Jual **TIDAK muncul lagi** di: Dashboard Stok, Laporan Stok (Index/Minimum/Pergerakan), menu Stok Barang
- Produk Jual **TIDAK bisa dibeli lewat PO** — kalau Owner mau beli minuman kaleng/snack jadi, inputkan sbg **bahan_baku** (misal "Fanta Kaleng" satuan kaleng), lalu bikin produk jual "Fanta" dengan resep 1 kaleng Fanta Kaleng
- Produk Jual **TIDAK bisa di-transfer antar cabang / di-request stok** — stok fisik real ada di bahan baku yang dipakai resepnya

**Alur kerja yang benar untuk D'mentai:**
1. Input semua bahan + kemasan + sumpit sebagai `bahan_baku` / `kemasan` / `tambahan_gratis` di menu **Bahan Baku & Kemasan**
2. Beli bahan baku lewat **PO** → stok terisi di cabang
3. Buat produk jual di menu ini dengan **resep** yang expand ke bahan baku
4. Di POS, kasir klik produk jual → sistem auto-cek stok bahan baku di cabang itu → kalau cukup, checkout → bahan baku terpotong (bukan produk jual)

### Tombol `+` di dropdown Kategori
Modal "Tambah Kategori Baru" sekarang juga tersedia di form Produk Jual — tombol `+` di sebelah dropdown Kategori.

MD,

            'pembelian' => <<<'MD'
## 🔄 Update 2026-10-08 — Yang Bisa Dibeli Lewat PO

Mulai 2026-10-08, dropdown "Pilih Barang" di form PO **hanya menampilkan 3 tipe item**:
- **Bahan Baku** (tepung, daging, saus, bumbu, dsb)
- **Kemasan** (box, plastik, dsb)
- **Tambahan Gratis** (sumpit, garpu, saus kecil — dibeli dari supplier walau gratis utk customer di POS)

**Produk Jual (dimsum/gyoza/drink matang) tidak muncul lagi** di dropdown PO — karena produk jual dibuat on-demand lewat resep di dapur, bukan dibeli jadi dari supplier.

Kalau Owner mau beli **barang jadi** (minuman kaleng, snack kemasan, dimsum frozen impor):
1. Input sebagai **bahan_baku** di menu Bahan Baku (satuan: kaleng/pack/dsb)
2. Beli lewat PO seperti biasa
3. Buat **produk jual** di menu Produk Jual dengan resep 1 bahan (1 kaleng = 1 produk jual)

Pattern ini membuat 1 model yang konsisten: stok fisik selalu di bahan_baku, produk_jual murni POS display.

Tombol submit juga dilindungi guard rail — kalau ada yang coba inject tipe produk_jual lewat devtools, backend akan menolak dengan pesan error jelas.

MD,

            'transfer-stok' => <<<'MD'
## 🔄 Update 2026-10-08 — Yang Bisa Ditransfer Antar Cabang

Mulai 2026-10-08, dropdown "Pilih Barang" di form Transfer Stok cuma menampilkan **3 tipe**: Bahan Baku, Kemasan, Tambahan Gratis.

Produk Jual (dimsum/gyoza matang) **tidak lagi muncul** — karena produk jual dibuat on-demand di tiap cabang lewat resep, bukan di-transfer antar cabang. Kalau outlet A kurang bahan untuk bikin dimsum, yang di-transfer adalah **bahan bakunya** (tepung, daging, kulit), bukan dimsum jadi.

Guard rail backend aktif: tipe item yang tidak boleh ditransfer akan ditolak submit, meski dipaksa via devtools.

MD,

            'permintaan-stok' => <<<'MD'
## 🔄 Update 2026-10-08 — Yang Bisa Di-Request

Mulai 2026-10-08, dropdown "Pilih Barang" di form Permintaan Stok cuma menampilkan **3 tipe**: Bahan Baku, Kemasan, Tambahan Gratis.

Produk Jual tidak lagi muncul — pola sama dgn Transfer Stok ([[transfer-stok]]). Request stok selalu untuk bahan baku yang akan dipakai di dapur, bukan produk jadi.

MD,

            'dashboard-stok' => <<<'MD'
## 🔄 Update 2026-10-08 — Scope Dashboard Stok

Mulai 2026-10-08, **Dashboard Stok** cuma menampilkan barang bertipe:
- Bahan Baku
- Kemasan
- Tambahan Gratis

**Produk Jual (dimsum/gyoza/drink matang) tidak muncul lagi** — karena di konsep D'mentai, produk jual murni POS display (dibuat lewat resep). Stok fisik real yang perlu dipantau cuma 3 tipe di atas.

Dropdown filter "Tipe" di atas tabel juga ikut hanya 3 opsi (tidak ada "Produk Jadi" lagi).

Kalau Owner mau cek apakah suatu produk jual masih "bisa dijual" di cabang tertentu, cek lewat POS — sistem otomatis disable produk jual yang bahan bakunya habis di cabang itu.

MD,

            'stok-barang' => <<<'MD'
## 🔄 Update 2026-10-08 — Scope Stok Barang

Mulai 2026-10-08, menu **Stok Barang** cuma menampilkan 3 tipe: Bahan Baku, Kemasan, Tambahan Gratis. Produk Jual tidak muncul (lihat [[dashboard-stok]] untuk alasan arsitekturnya).

Dropdown filter "Tipe" juga ikut hanya 3 opsi.

MD,

            'laporan-stok' => <<<'MD'
## 🔄 Update 2026-10-08 — Scope Laporan Stok

Mulai 2026-10-08, **Laporan Stok** (Index, Minimum, Pergerakan — semua sub-laporan) cuma menampilkan 3 tipe: Bahan Baku, Kemasan, Tambahan Gratis. Produk Jual di-exclude secara global, konsisten dgn Dashboard Stok.

Export Excel/PDF juga ikut filter ini — kalau Owner butuh rekap pergerakan produk jadi, lihat Laporan Penjualan (per produk) sebagai gantinya.

MD,
        ];

        $jumlahUpdate = 0;
        $jumlahSkip = 0;

        foreach ($updates as $slug => $sectionBaru) {
            $panduan = Panduan::where('slug', $slug)->first();
            if (!$panduan) {
                continue; // panduan belum ada — biarkan, bukan tugas seeder ini bikin baru
            }

            // Idempotent check
            if (str_contains($panduan->konten, $marker)) {
                $jumlahSkip++;
                continue;
            }

            $panduan->konten = rtrim($panduan->konten) . "\n\n" . $marker . "\n" . trim($sectionBaru) . "\n";
            $panduan->save();
            $jumlahUpdate++;
        }

        if ($this->command) {
            $this->command->info("Sprint 4.34 Panduan: {$jumlahUpdate} di-update, {$jumlahSkip} di-skip (sudah pernah).");
        }
    }
}
