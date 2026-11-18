<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * 4.50 — Perbaikan 5 temuan audit Kamus Menu: panduan & tooltip yang berubah.
 * Idempotent: bagian panduan ditempel sekali (marker), tooltip updateOrCreate.
 */
class Perbaikan450Seeder extends Seeder
{
    public const MARKER = '<!-- PERBAIKAN-450 -->';

    public function run(): void
    {
        foreach ($this->sections() as $slug => $konten) {
            $panduan = Panduan::where('slug', $slug)->first();
            if (! $panduan || str_contains((string) $panduan->konten, self::MARKER)) {
                continue;
            }
            $panduan->konten = rtrim((string) $panduan->konten) . "\n\n" . self::MARKER . "\n" . trim($konten) . "\n";
            $panduan->save();
        }

        Tooltip::updateOrCreate(['key' => 'master_produk_jual.jenis_menu'], [
            'title'   => 'Jenis Produk (opsional)',
            'content' => 'Kelompok produk untuk Laporan Laba Rugi pilihan "Per Jenis Produk" (mis. Parfum Botol, Refill). Daftar Jenis Produk diatur di menu Penjualan → Jenis Produk. Tidak mengubah harga, stok, atau tampilan POS.',
            'modul'   => 'master_produk_jual',
            'aktif'   => true,
            'urutan'  => 90,
        ]);
    }

    private function sections(): array
    {
        return [
            'riwayat-order' => <<<'MD'
## 🔄 Update 2026-11-18 — Edit order yang sudah dibayar
- Order yang sudah **Selesai** (sudah dibayar) atau **Dibatalkan**: di halaman Edit hanya **nama/telepon pelanggan dan catatan** yang bisa diubah. **Diskon dan metode bayar dikunci** — uangnya sudah tercatat di kas dan Setoran Kasir.
- Kalau diskon/metode bayar salah: **Batalkan** order, buat order baru yang benar di POS, lalu **Tandai Pengganti**.
- Bill yang belum dibayar (Pending) tetap bisa diubah diskon & metode bayarnya; service charge ikut menyesuaikan diskon.
MD,
            'pelanggan' => <<<'MD'
## 🔄 Update 2026-11-18 — Nonaktifkan pelanggan
- Di **Edit Pelanggan** ada saklar **Aktif**. Matikan kalau pelanggan sudah tidak dipakai: tidak muncul lagi di pilihan pelanggan POS, tetapi riwayat belanja, laporan, dan progres loyalty tetap ada.
- Kalau data pelanggan dihapus, **order-order pelanggan itu tidak ikut terhapus** dan tetap dihitung di laporan.
MD,
            'bahan-baku' => <<<'MD'
## 🔄 Update 2026-11-18 — Aturan hapus barang
- Hapus hanya bisa untuk barang yang **belum pernah dipakai**. Ditolak kalau barang masih punya stok, dipakai di komposisi produk / Resep Parfum / Resep Produksi, atau ada di PO yang belum selesai — pesan menyebut alasannya. Untuk barang seperti itu, hilangkan centang **Aktif** saja.
- Riwayat barang (baris di order dan PO lama) tidak ikut terhapus.
- **Tambahan Gratis** (mis. kantong, stiker) yang **Lacak Stok**-nya dicentang sekarang **terpotong stoknya saat dipilih di POS**, dan bertanda "Habis" kalau stok di cabang itu kosong. Hilangkan centang Lacak Stok kalau tidak ingin dihitung.
MD,
            'produk-jual' => <<<'MD'
## 🔄 Update 2026-11-18 — Jenis Produk, Produk Tambahan, hapus produk
- Pilihan **Jenis Produk** (opsional) di form produk — dipakai Laporan Laba Rugi "Per Jenis Produk". Daftarnya diatur di Penjualan → Jenis Produk.
- **Produk Tambahan** sekarang diperlakukan seperti Produk Jual: kalau punya komposisi, bahannya dipotong saat terjual; tanpa komposisi, stoknya sendiri yang dipotong (dan "Habis" kalau 0).
- Hapus produk hanya untuk produk yang belum pernah dipakai; riwayat order tidak ikut terhapus dan **foto tidak dihapus** (produk bisa dipulihkan dari Data Terhapus lengkap dengan fotonya).
MD,
            'pos' => <<<'MD'
## 🔄 Update 2026-11-18 — Item Tambahan memotong stok
- **Tambahan Gratis** yang Lacak Stok-nya dicentang dan **Produk Tambahan** sekarang memotong stok saat order dibayar, dan bertanda "Habis" di POS kalau stoknya kurang — sama seperti produk jual.
MD,
            'laporan-laba-rugi' => <<<'MD'
## 🔄 Update 2026-11-18 — Per Jenis Produk terisi
- Pilihan **Per Jenis Produk** sekarang mengelompokkan penjualan berdasarkan **Jenis Produk** produknya (Produk Jual → Jenis Produk). Order baru menyimpan nama Jenis Produk saat dijual; order lama memakai Jenis Produk produknya yang sekarang. Produk tanpa Jenis Produk masuk "-".
MD,
            'jenis-olahan' => <<<'MD'
## 🔄 Update 2026-11-18 — Jenis Produk di Produk Jual
- Jenis Produk sekarang juga bisa dipilih di form **Produk Jual** — dipakai Laporan Laba Rugi "Per Jenis Produk". Tetap tidak mengubah harga, stok, atau tampilan POS.
MD,
        ];
    }
}
