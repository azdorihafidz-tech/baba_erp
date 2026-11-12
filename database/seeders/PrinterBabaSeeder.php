<?php

namespace Database\Seeders;

use App\Models\Panduan;
use Illuminate\Database\Seeder;

/**
 * ERP BABA Tahap 6b — panduan cetak struk tanpa memilih printer: PC Windows (BABA Printer Agent, port 17331)
 * dan tablet/HP Android (APK kasir). Idempotent.
 */
class PrinterBabaSeeder extends Seeder
{
    public function run(): void
    {
        Panduan::updateOrCreate(['slug' => 'printer-kasir'], [
            'judul' => 'Cetak Struk Tanpa Pilih Printer (PC & Tablet)',
            'konten' => self::PANDUAN,
            'modul' => 'penjualan',
            'urutan' => 101,
            'aktif' => true,
        ]);
    }

    public const PANDUAN = <<<'MARKDOWN'
## Tujuan
Di browser biasa (Chrome), cetak struk Bluetooth sering meminta **pilih printer** lagi. Ada cara supaya printer cukup diatur **sekali**, lalu struk langsung tercetak:
- **PC / laptop Windows** → pakai **Agen Printer PC** (BABA Printer Agent)
- **Tablet / HP Android** → pakai **Aplikasi Kasir (APK)**

Isi struk, jumlah salinan, dan buka laci untuk pembayaran **Tunai** tetap sama. Kalau agen/aplikasi tidak dipakai, cara lama (pilih printer Bluetooth / **Cetak Browser**) tetap bisa dipakai.

## PC / Laptop Windows — Agen Printer (BABA Printer Agent)
Program kecil yang berjalan diam-diam di PC kasir. Bisa untuk printer **Bluetooth** (sudah di-pairing ke PC) maupun printer **USB** yang terpasang di Windows.

### Pasang sekali per PC
1. **Pairing printer** dulu ke PC:
   - Bluetooth: **Settings → Bluetooth & devices → Add device → Bluetooth** → pilih printer (PIN biasanya `0000` atau `1234`)
   - USB: colok kabel, pasang driver printer kalau diminta
2. Login ERP di PC kasir (Chrome/Edge), lalu unduh agen: **[baba-printer-agent.exe](/unduh/baba-printer-agent.exe)**
3. Jalankan file tersebut. Kalau muncul **"Windows protected your PC"**, klik **More info → Run anyway** (muncul sekali karena programnya buatan sendiri)
4. Halaman **BABA Printer Agent** terbuka di browser:
   - **Bluetooth**: pilih **Bluetooth / Serial (port COM)**, lalu pilih port printer. Port yang benar adalah port **Outgoing / Keluar** milik printer — cek di **Settings → Bluetooth & devices → Devices → More Bluetooth settings → tab COM Ports**
   - **USB**: pilih **Printer USB terpasang di Windows**, lalu pilih printernya (jangan printer PDF)
   - Tekan **Tes Cetak** (dan **Tes Laci**) sampai kertas tes keluar
5. Di ERP, buka struk transaksi mana saja (mis. dari **Riwayat Order**) → tekan **🖥️ Pakai Agen Printer PC**. Kalau browser menanyakan izin **akses perangkat di jaringan lokal**, pilih **Izinkan / Allow**

Agen otomatis jalan setiap Windows menyala. Pengaturannya bisa dibuka lagi dari menu sidebar **Pengaturan Printer PC**.

### Sehari-hari
- Setelah **Proses** di POS, struk langsung tercetak tanpa memilih printer
- Atau tekan **🖨️ Cetak Struk (Printer PC)** di jendela struk
- Cetak ulang dari Riwayat tidak tercetak otomatis (supaya laci tidak ikut terbuka)
- **Buka Laci** di POS juga lewat agen
- Kalau agen mati/printer bermasalah, tombol Bluetooth browser yang lama tetap tersedia. Tombol **Pakai Bluetooth Browser** mematikan agen di browser itu

## Siapa yang Bisa Akses
Tidak ada hak akses baru. Semua yang bisa memakai POS bisa memakai agen ini; **Buka Laci** tetap butuh hak `kas.buka_laci`. Agen hanya menerima perintah cetak dari alamat ERP BABA (`erp.babaparfume.id`); website lain ditolak.

## Troubleshooting (PC)
- **"Agen printer PC tidak menyala di komputer ini"** → jalankan lagi `baba-printer-agent.exe` (atau restart PC). Kalau tetap muncul, cek izin **akses jaringan lokal** di browser: klik ikon gembok di kiri alamat ERP → **Site settings** → **Local network access** → **Allow**
- **"COM… tidak bisa dibuka"** → printer mati / di luar jangkauan / sedang tersambung ke perangkat lain. Pastikan yang dipilih port **Outgoing**
- **"Printer belum diatur"** → buka **Pengaturan Printer PC**, pilih printer, Simpan, Tes Cetak
- **Hurufnya aneh** (printer USB) → pilih printer thermal yang benar; jangan printer PDF/biasa
- **Mengganti PC / mencopot agen** → buka Pengaturan Printer PC → **Hentikan Agen** dan matikan **Jalankan otomatis**
- Satu PC boleh memakai agen BABA (port 17331) bersama agen usaha lain — tidak bentrok
MARKDOWN;
}
