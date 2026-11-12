# BABA Printer Agent

Program kecil untuk **PC kasir Windows** supaya ERP BABA PARFUME bisa mencetak struk
langsung ke printer thermal, **tanpa memilih printer** setiap transaksi.
(Untuk tablet/HP Android dipakai APK Kasir — lihat panduan `printer-kasir`.) Port 17331 (beda dengan agen Berkah Mulyo 17321, jadi bisa berdampingan).

- Hanya mendengar di `127.0.0.1:17331` (tidak bisa diakses komputer lain)
- Printer: **Bluetooth / serial lewat port COM** (mis. IWARE C58BT yang sudah di-pairing)
  atau **printer USB yang terpasang di Windows** (dikirim RAW, melewati driver grafis)
- Data struk = byte ESC/POS yang sama persis dengan jalur Bluetooth browser
  (`buildReceipt()` di `resources/views/penjualan/_struk_modal_content.blade.php`)
- Hanya alamat ERP yang terdaftar (default `https://erp.babaparfume.id`,
  `http://127.0.0.1:8003`, `http://localhost:8003`) yang boleh mencetak; Host asing ditolak

## API (dipanggil `window.BMAgent` di `components/apk-printer.blade.php`)

| Endpoint | Keterangan |
|---|---|
| `GET /status` | `{agent, versi, siap, tipe, printer}` |
| `POST /print` | body `{"data": "<base64 ESC/POS>"}` |
| `POST /drawer` | buka laci (`1B 70 00 19 FA`) |
| `GET /` | halaman pengaturan (hanya dari PC itu sendiri) |
| `/api/*` | pengaturan, tes cetak, jalan otomatis, hentikan — hanya dari halaman agen |

## Pemasangan (di PC kasir)

1. Login ERP di PC kasir, unduh `https://erp.babaparfume.id/unduh/baba-printer-agent.exe` (route `printer-agent.unduh`, wajib login; file disimpan di `public/downloads/`), jalankan
2. Agen menyalin dirinya ke `%LOCALAPPDATA%\BABAPrinterAgent\`, didaftarkan jalan otomatis
   saat Windows menyala (`HKCU\...\Run\BABAPrinterAgent`), lalu membuka halaman pengaturan
3. Menjalankan exe versi baru = memperbarui (agen lama dihentikan otomatis)
4. Copot: jalankan `baba-printer-agent.exe --hapus`, lalu hapus folder `%LOCALAPPDATA%\BABAPrinterAgent`

Pengaturan & log: `%LOCALAPPDATA%\BABAPrinterAgent\config.json` dan `agent.log`.

## Build

Hanya pustaka standar Go (tanpa dependensi luar). Dari folder ini:

```
go vet ./...
go build -trimpath -ldflags "-H=windowsgui -s -w" -o ../../public/downloads/baba-printer-agent.exe .
```

`-H=windowsgui` = tanpa jendela konsol (berjalan di latar). Exe tidak ditandatangani,
jadi Windows SmartScreen memberi peringatan sekali (More info → Run anyway).

## Tes

- `tests/browser/printer-agent-e2e.cjs` — E2E ERP ↔ agen asli dengan printer Windows uji
  ("Generic / Text Only" ke port file), membandingkan byte yang sampai ke printer
