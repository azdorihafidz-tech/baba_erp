<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * 4.48 — panduan + tooltip Warna Aplikasi (Pengaturan Umum). Idempotent &
 * aman di production: tidak menyentuh izin/role, tidak mengubah warna.
 * Jalankan SETELAH IdentitasUsahaSeeder (seeder itu menulis ulang panduan
 * pengaturan-umum; bagian ini ditempel di belakangnya dgn marker).
 */
class WarnaAplikasiSeeder extends Seeder
{
    private const MARKER = '<!-- WARNA-448 -->';

    public function run(): void
    {
        Tooltip::updateOrCreate(['key' => 'pengaturan_umum.warna'], [
            'title'   => 'Warna Aplikasi',
            'content' => 'Pilih tema siap pakai atau warna sendiri. Berlaku untuk semua cabang: sidebar, menu aktif, latar halaman, halaman login, grafik, dan judul laporan PDF/Excel. Warna tombol tidak berubah. Kosong/bawaan = oranye & hitam.',
            'modul'   => 'pengaturan',
            'aktif'   => true,
            'urutan'  => 60,
        ]);

        $p = Panduan::where('slug', 'pengaturan-umum')->first();
        if ($p && ! str_contains((string) $p->konten, self::MARKER)) {
            $p->konten = rtrim((string) $p->konten) . "\n\n" . self::MARKER . "\n" . <<<'MARKDOWN'
## Warna Aplikasi

Atur warna aplikasi sesuai identitas usaha. Berlaku untuk **semua cabang** dan semua pengguna.

1. Buka **Pengaturan Umum** → kotak **Warna Aplikasi**.
2. Klik salah satu **Tema siap pakai** (Oranye & Hitam, Kopi, Hijau, Biru, Merah, Ungu), atau atur sendiri 3 warna:
   - **Warna Utama** — menu yang sedang dibuka, sorotan, grafik, judul laporan PDF.
   - **Warna Sidebar** — latar menu di kiri dan halaman login.
   - **Warna Latar Halaman** — latar di belakang kartu, juga header tabel Excel/PDF. **Harus warna terang** supaya tulisan terbaca.
3. Lihat **Pratinjau** di sebelahnya, lalu klik **Simpan Pengaturan**.
4. Mau kembali seperti semula? Klik **Kembalikan ke warna bawaan**, lalu Simpan.

**Catatan:**
- Tulisan di sidebar dan menu aktif otomatis jadi putih atau hitam supaya tetap terbaca.
- Warna tombol (hijau Simpan, merah Hapus, biru, dll) **tidak berubah** — warnanya punya arti.
- Struk printer tetap hitam-putih.
- Kalau tampilan belum berubah di tablet/HP, muat ulang halaman (tarik ke bawah atau Ctrl+Shift+R).
MARKDOWN;
            $p->save();
        }

        $this->command?->info('✅ Panduan & tooltip Warna Aplikasi (idempotent).');
    }
}
