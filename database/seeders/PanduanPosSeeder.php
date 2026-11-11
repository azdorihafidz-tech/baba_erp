<?php

namespace Database\Seeders;

use App\Models\Panduan;
use Illuminate\Database\Seeder;

class PanduanPosSeeder extends Seeder
{
    public function run(): void
    {
        $konten = <<<'MARKDOWN'
## Tujuan
Mencatat transaksi penjualan harian dari pelanggan ke sistem secara otomatis.

## Alur Normal

1. Buka menu **POS** dari sidebar
2. Pilih cabang (otomatis terisi kalau kamu cuma pegang 1 cabang)
3. Pilih pelanggan:
   - Klik dropdown **Pelanggan**
   - Pilih dari daftar, **ATAU** ketik nama baru kalau pelanggan belum terdaftar
4. Tambah item produk atau jasa giling
5. *(Opsional)* Tambah diskon
6. Pilih metode bayar: **Tunai / QRIS / Transfer**
7. Klik tombol **Proses Order** → cek ringkasan di dialog konfirmasi → klik **Ya, Proses**
8. Struk otomatis tercetak kalau centang "Cetak Struk Otomatis" aktif ✅

## Satuan Item (Kg vs Pcs)

Saat kamu pilih item di baris jasa giling/produk, tampilan form otomatis menyesuaikan satuan asli item itu di Master Barang:

- **Item satuan Kg** (mis. daging, bumbu jasa giling) — label tetap "Berat (kg)", dropdown satuan **kg/ons/gram** aktif bisa dipilih, label tarif "Tarif/kg"
- **Item satuan Pcs** (mis. kemasan, produk jadi hitungan biji) — label otomatis berubah jadi "Qty (Pcs)", dropdown terkunci ke Pcs saja (tidak bisa salah pilih kg/ons/gram), label tarif "Tarif/Pcs"

Kalau ada baris yang tampil peringatan merah "satuan tidak sesuai", tombol **Proses Order** otomatis terkunci sampai baris itu diperbaiki (pilih ulang item-nya) — ini jaga-jaga supaya satuan yang tersimpan selalu sesuai master item, tidak salah hitung.

## Cara Buat Order Tambahan

Dipakai kalau ada pelanggan minta tambah item **saat order sedang dikerjakan bareng** (bukan order baru yang terpisah antriannya):

1. Buat order baru seperti biasa untuk item tambahan
2. **Uncheck** centang **"Tampilkan di Antrian Produksi"** sebelum klik Proses
3. Order tambahan tetap tersimpan & tercatat normal di riwayat, tapi TIDAK dapat nomor antrian baru dan TIDAK muncul di TV Antrian / Mode Produksi — supaya tidak membingungkan operator produksi

## Cara Kurangi Item / Pelanggan Ganti Pesanan

Dipakai kalau pelanggan minta kurangi item atau ganti pesanan **setelah** order sudah diproses:

1. Buka **Riwayat Order** → cari order yang mau diubah → klik **Batalkan**
2. Pilih kategori alasan pembatalan (wajib)
3. Buat **order baru** dengan pesanan yang sudah benar
4. Kalau pelanggan sama & masih hari yang sama, sistem **otomatis tanya**: "Order ini pengganti order #XXX?" — klik **Ya** supaya order baru terhubung ke order lama
5. Kalau popup ke-lewat, buka order baru → klik tombol **"Tandai sebagai Pengganti"** → pilih order lama secara manual

## Aturan Penting

- **Pembatalan order hanya bisa di hari yang sama** — order dari hari sebelumnya cuma bisa dibatalkan oleh Owner
- Alasan pembatalan **wajib diisi** setiap kali batalkan order (untuk audit)
- Stok berkurang otomatis sesuai item terjual (FIFO), dan otomatis dikembalikan kalau order dibatalkan
- Kalau pakai QRIS/Transfer, wajib upload bukti pembayaran
- Transaksi besar (≥ Rp 1.000.000) otomatis kirim notifikasi ke management

## Troubleshooting

- **Produk tidak muncul?** Cek menu Master Data → Produk, pastikan produk aktif & stok > 0
- **Pelanggan tidak ada?** Bisa langsung ketik nama baru di dropdown, sistem otomatis daftarkan
- **Tombol Batalkan tidak bisa diklik?** Order sudah lewat hari ini — minta Owner untuk membatalkan
- **Tombol Proses Order terkunci padahal semua sudah diisi?** Cek apakah ada baris dengan peringatan merah "satuan tidak sesuai" — pilih ulang item di baris itu supaya satuannya kembali otomatis sesuai master
MARKDOWN;

        Panduan::updateOrCreate(
            ['slug' => 'pos'],
            [
                'judul'  => 'Cara Pakai Menu POS',
                'konten' => $konten,
                'modul'  => 'pos',
                'urutan' => 1,
                'aktif'  => true,
            ]
        );
    }
}
