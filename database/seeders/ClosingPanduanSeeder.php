<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * Panduan + tooltip menu Closing / Tutup Buku (2026-10-17). Idempotent
 * (updateOrCreate) — aman dijalankan ulang di production.
 */
class ClosingPanduanSeeder extends Seeder
{
    public function run(): void
    {
        Panduan::updateOrCreate(['slug' => 'closing'], [
            'judul'  => 'Closing / Tutup Buku',
            'konten' => <<<'MARKDOWN'
## Tujuan
Membekukan angka keuangan per periode (harian, bulanan, atau keseluruhan semua cabang) supaya laporan bulan lalu **tidak berubah lagi** walau dibuka kapan pun. Saat closing disimpan, sistem menyimpan salinan angka (penjualan, omzet, HPP, laba, arus kas, saldo tiap kas, mutasi, nilai stok, setoran) dan **mengunci** periode itu sehingga transaksi & order di dalamnya tidak bisa ditambah, diubah, atau dihapus.

## Isi Laporan Closing
1. **Penjualan** — jumlah transaksi, omzet kotor, diskon, omzet bersih, per metode bayar & per tipe (dine-in/takeaway/frozen)
2. **Laba Rugi** — omzet − HPP = laba kotor, lalu − beban operasional (gaji, sewa, listrik, dll) = laba bersih
3. **Arus Kas** — semua uang masuk & keluar, termasuk pembelian bahan
4. **Mutasi Internal** — perpindahan uang di dalam usaha: transfer antar kas di 1 cabang, Setoran Kasir ke pusat, Transfer Dana antar cabang. **Bukan** omzet atau pengeluaran
5. **Saldo Kas** — per kas: saldo awal, masuk, keluar, mutasi, saldo akhir (dikelompokkan Tunai vs Bank)
6. **Nilai Persediaan Stok** dan **Setoran**
7. Laporan keseluruhan juga menampilkan **ringkasan per cabang**

## Langkah-langkah

### Closing Harian (kasir / manajer cabang, tiap tutup toko)
1. Buka **Keuangan → Closing / Tutup Buku**, pilih Jenis **Harian**, tanggal hari ini, dan cabang Anda, klik **Tampilkan**
2. Periksa angkanya. Kalau masih ada transaksi yang belum diinput, input dulu
3. Klik **Closing Harian**, hitung uang di laci, isi **jumlah uang fisik** untuk setiap kas tunai
4. Klik **Simpan & Kunci**. Selisih uang fisik vs sistem tercatat otomatis (minus = kurang, plus = lebih)

### Tutup Buku Bulanan (admin pusat / Owner)
1. Pilih Jenis **Bulanan** dan **Bulan Periode**. Periode mengikuti setting **Tanggal Mulai Periode** di Pengaturan Umum (mis. 26 → periode "Oktober" = 26 Sep s/d 25 Okt)
2. Tombol **Tutup Buku Bulanan** muncul mulai hari terakhir periode. Klik, isi catatan bila perlu, **Simpan & Kunci**
3. Untuk laporan gabungan, pilih Cabang **Semua Cabang** lalu **Tutup Buku Keseluruhan** — bisa dilakukan setelah **semua cabang aktif** tutup buku bulanan periode itu

### Rekap Mingguan
Pilih Jenis **Mingguan** (Senin–Minggu). Tampil tabel status closing harian tiap hari per cabang: ✔ sudah, ⚠ belum, plus selisih uang fisik. Mingguan tidak dikunci terpisah.

### Melihat Closing Lama
Klik **Riwayat Closing**, lalu **Lihat**. Angka di arsip adalah angka saat closing disimpan dan tidak berubah. Tombol **Excel/PDF** untuk mengunduh.

## Catatan Penting
- **Halaman preview = angka real-time**, bisa berubah. **Angka resmi = arsip closing.**
- Setelah closing, transaksi/order tanggal itu **ditolak** dengan pesan "sudah di-closing" dari menu mana pun (Kas & Transaksi, POS, batal order, transfer, dll).
- Pembelian bahan tidak dihitung dua kali: di Laba Rugi yang dihitung **HPP** (bahan terpakai), di Arus Kas yang dihitung **pembelian** (uang keluar).
- Usaha 1 cabang tanpa Gudang Pusat: setoran dicatat lewat **Transfer Antar Kas** dan tampil di Mutasi Internal.
- Setoran Kasir yang disetujui setelah tanggalnya di-closing dicatat pada **tanggal persetujuan**.
- Nilai stok di preview adalah stok **saat ini**; di arsip = stok saat closing disimpan.

## Buka Kunci (khusus Owner)
Kalau ada koreksi: buka arsip closing → **Buka Kunci** → isi alasan (min. 10 karakter) → koreksi transaksinya → lakukan closing ulang (tersimpan sebagai **versi baru**, versi lama tetap ada di riwayat). Membuka Tutup Buku Bulanan otomatis ikut membuka closing harian di dalamnya dan Tutup Buku Keseluruhan periode itu.

## Troubleshooting
- **Muncul pesan "sudah di-closing" saat input transaksi?** Tanggal itu terkunci. Input di tanggal lain yang benar, atau minta Owner buka kunci.
- **Tombol Closing tidak muncul?** Cek hak akses (Role & Hak Akses → Closing), atau periode belum berakhir / tanggal masih di masa depan.
- **Tutup Buku Keseluruhan tidak bisa?** Masih ada cabang yang belum tutup buku bulanan — namanya tampil di halaman.
- **Saldo kas preview periode lama terasa beda?** Saldo masa lalu dihitung mundur dari saldo sekarang; gunakan arsip closing untuk angka resmi.
MARKDOWN
            ,
            'modul'  => 'keuangan',
            'urutan' => 22,
            'aktif'  => true,
        ]);

        $tooltips = [
            ['key' => 'closing.jenis', 'title' => 'Jenis Closing',
                'content' => 'Harian = 1 hari (dikunci per hari, wajib hitung uang fisik laci). Mingguan = rekap Senin–Minggu dari closing harian (tanpa kunci). Bulanan = tutup buku 1 periode (mengikuti Tanggal Mulai Periode di Pengaturan Umum).'],
            ['key' => 'closing.bulan_periode', 'title' => 'Bulan Periode',
                'content' => 'Periode dinamai menurut bulan tempat periode berakhir. Contoh tanggal mulai 26: "Oktober" = 26 September s/d 25 Oktober.'],
            ['key' => 'closing.cabang', 'title' => 'Cabang',
                'content' => 'Closing dilakukan per cabang. "Semua Cabang" = laporan gabungan; mutasi antar cabang (setoran ke pusat, transfer dana) saling meniadakan.'],
            ['key' => 'closing.laba_rugi', 'title' => 'Laba Rugi',
                'content' => 'Omzet bersih − HPP (bahan terpakai) − beban operasional. Pembelian bahan & pembelian aset tidak dihitung di sini (sudah diwakili HPP & penyusutan).'],
            ['key' => 'closing.arus_kas', 'title' => 'Arus Kas',
                'content' => 'Uang yang benar-benar masuk/keluar dari kas, termasuk pembelian bahan. Mutasi internal tidak dihitung di sini.'],
            ['key' => 'closing.mutasi', 'title' => 'Mutasi Internal',
                'content' => 'Perpindahan uang di dalam usaha: antar kas dalam 1 cabang (laci → bank), Setoran Kasir ke pusat, Transfer Dana antar cabang. Bukan omzet dan bukan pengeluaran.'],
            ['key' => 'closing.saldo_kas', 'title' => 'Saldo Kas',
                'content' => 'Saldo awal + masuk − keluar + mutasi masuk − mutasi keluar = saldo akhir, per kas. Di preview periode lama, saldo dihitung mundur dari saldo sekarang; angka resmi ada di arsip closing.'],
            ['key' => 'closing.stok', 'title' => 'Nilai Persediaan',
                'content' => 'Nilai stok (qty sisa × harga beli batch FIFO). Sistem hanya menyimpan stok saat ini, jadi nilai ini dibekukan saat closing disimpan.'],
            ['key' => 'closing.uang_fisik', 'title' => 'Uang Fisik',
                'content' => 'Hitung uang tunai di laci saat tutup toko lalu isi jumlahnya. Selisih dengan saldo sistem dicatat (minus = kurang, plus = lebih).'],
            ['key' => 'closing.alasan_buka', 'title' => 'Alasan Buka Kunci',
                'content' => 'Jelaskan apa yang akan dikoreksi. Alasan tercatat di riwayat closing dan log aktivitas.'],
            ['key' => 'pengaturan_umum.tanggal_mulai_periode', 'title' => 'Tanggal Mulai Periode',
                'content' => 'Tanggal awal periode bulanan untuk Closing / Tutup Buku. 1 = bulan kalender. 26 = periode 26 bulan lalu s/d 25 bulan ini.'],
        ];

        foreach ($tooltips as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title'   => $t['title'],
                'content' => $t['content'],
                'modul'   => str_starts_with($t['key'], 'pengaturan') ? 'pengaturan' : 'closing',
                'aktif'   => true,
                'urutan'  => $i + 1,
            ]);
        }

        $this->command?->info('✅ Panduan & tooltip Closing / Tutup Buku tersimpan (idempotent).');
    }
}
