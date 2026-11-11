# Icon PWA – ERP Berkah Mulyo

File-file PNG di folder ini adalah **placeholder** sementara (teks "BM" di atas lingkaran merah).
Ganti dengan logo resmi Berkah Mulyo ketika siap.

## Cara Upload Logo

1. **Siapkan** logo PNG ukuran **512×512 px** (background transparan atau solid sesuai brand).
2. **Generate** semua ukuran via salah satu tool gratis:
   - https://www.pwabuilder.com/imageGenerator
   - https://realfavicongenerator.net
3. **Download** hasil generate, ambil file-file berikut:
   ```
   icon-72x72.png
   icon-96x96.png
   icon-128x128.png
   icon-144x144.png
   icon-152x152.png
   icon-192x192.png   ← wajib (launcher Android)
   icon-384x384.png
   icon-512x512.png   ← wajib (splash screen)
   ```
4. **Upload** 8 file PNG ke folder `public/images/icons/` (overwrite placeholder).
5. **Tidak perlu restart** server — refresh halaman & re-install PWA dari browser.

## Catatan
- Ukuran 192×192 dan 512×512 adalah **wajib** untuk Chrome Android.
- Gunakan background **solid** (bukan transparan) untuk tampilan yang baik di semua launcher.
- Warna brand: Background `#472c1d`, Aksen `#d0371b`.
