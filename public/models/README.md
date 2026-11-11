# face-api.js Models

Folder ini berisi file model untuk face-api.js yang dipakai di halaman
`/face-attendance/scan` (absensi wajah dengan head movement liveness detection).

> **File model TIDAK di-track Git** karena total ukuran ~7MB.
> Setiap environment (local dev & server) harus punya file ini secara terpisah.

## File yang Dibutuhkan (7 files, total ~7MB)

| File | Ukuran | Fungsi |
|------|--------|--------|
| `tiny_face_detector_model-weights_manifest.json` | ~3KB | Manifest TinyFaceDetector |
| `tiny_face_detector_model-shard1` | ~189KB | Weights TinyFaceDetector |
| `face_landmark_68_model-weights_manifest.json` | ~8KB | Manifest landmark 68-point |
| `face_landmark_68_model-shard1` | ~349KB | Weights landmark (EAR + nose tracking) |
| `face_recognition_model-weights_manifest.json` | ~18KB | Manifest face recognition |
| `face_recognition_model-shard1` | ~4MB | Weights face recognition (bagian 1) |
| `face_recognition_model-shard2` | ~2.2MB | Weights face recognition (bagian 2) |

## Cara Download (Local Development)

### Windows
```
scripts\download-face-models.bat
```
Double-click atau jalankan dari Command Prompt di root project.

### Linux / Mac
```bash
chmod +x scripts/download-face-models.sh
./scripts/download-face-models.sh
```

Script akan download otomatis semua 7 file ke folder ini.

## Cara Upload ke Server Hosting (cPanel — tanpa SSH)

1. Buka **cPanel → File Manager**
2. Navigate ke folder public ERP, lalu masuk ke subfolder `models/`
   - Contoh path: `/public_html/models/` atau `/home/user/public_html/erp/public/models/`
3. Klik tombol **Upload**
4. Drag-drop semua 7 file dari folder lokal `public/models/`
   (jangan upload README.md — sudah ada, dan sudah di-track Git)
5. Tunggu upload selesai (total ~7MB, tergantung koneksi)
6. Verifikasi via URL browser:
   ```
   https://domain-anda.com/models/tiny_face_detector_model-weights_manifest.json
   ```
   Harus tampil JSON (bukan 404 atau redirect)

### Cek di Browser Console
Buka `/face-attendance/scan`, tekan F12, pilih tab **Network**.
Filter dengan kata `models/` — semua 7 request harus **200 OK**.
Jika masih 404, periksa path upload di cPanel.

## Sumber Resmi

Download manual (jika script gagal) tersedia di:
- https://github.com/justadudewhohacks/face-api.js/tree/master/weights

CDN fallback (lambat, hanya untuk darurat — dipakai otomatis jika lokal 404):
- `https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/model/`
