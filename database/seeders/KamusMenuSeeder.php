<?php

namespace Database\Seeders;

use App\Models\KamusMenu;
use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * 4.50 Kamus Menu — memasukkan isi dari database/seeders/kamus/*.php.
 *
 * Isi kamus dimiliki KODE (tidak diketik lewat UI): entri yang tidak ada lagi di
 * file dihapus dari tabel. Aman dijalankan berulang (deploy, instalasi baru).
 */
class KamusMenuSeeder extends Seeder
{
    public const WAJIB = ['slug', 'judul', 'kelompok', 'urutan', 'letak', 'ringkasan', 'konten'];

    /** Semua entri dari file kamus (dipakai juga oleh test penjaga). */
    public static function entri(): array
    {
        $semua = [];
        foreach (glob(database_path('seeders/kamus/*.php')) as $file) {
            foreach (require $file as $e) {
                foreach (self::WAJIB as $k) {
                    if (! isset($e[$k]) || $e[$k] === '') {
                        throw new RuntimeException("Kamus Menu: '{$k}' kosong di " . basename($file) . ' (' . ($e['slug'] ?? '?') . ')');
                    }
                }
                if (! in_array($e['kelompok'], KamusMenu::KELOMPOK, true)) {
                    throw new RuntimeException("Kamus Menu: kelompok '{$e['kelompok']}' tidak dikenal ({$e['slug']}).");
                }
                if (isset($semua[$e['slug']])) {
                    throw new RuntimeException("Kamus Menu: slug '{$e['slug']}' dobel.");
                }
                $semua[$e['slug']] = $e;
            }
        }

        return $semua;
    }

    public function run(): void
    {
        if (! Schema::hasTable('kamus_menus')) {
            $this->command?->warn('Tabel kamus_menus belum ada — jalankan migrate dulu.');

            return;
        }

        $entri = self::entri();
        foreach ($entri as $slug => $e) {
            KamusMenu::updateOrCreate(['slug' => $slug], [
                'judul'       => $e['judul'],
                'kelompok'    => $e['kelompok'],
                'urutan'      => (int) $e['urutan'],
                'letak'       => $e['letak'],
                'ringkasan'   => $e['ringkasan'],
                'konten'      => trim($e['konten']),
                'kata_kunci'  => $e['kata_kunci'] ?? null,
                'izin'        => $e['izin'] ?? null,
                'route_names' => $e['route_names'] ?? [],
                'terkait'     => $e['terkait'] ?? [],
                'aktif'       => true,
            ]);
        }
        KamusMenu::whereNotIn('slug', array_keys($entri))->delete();
        Cache::forget(KamusMenu::CACHE_KEY);

        $this->panduan();
        $this->tooltip();

        $this->command?->info('Kamus Menu: ' . count($entri) . ' entri.');
    }

    private function panduan(): void
    {
        Panduan::updateOrCreate(['slug' => 'kamus-menu'], [
            'judul'  => 'Kamus Menu',
            'konten' => <<<'MARKDOWN'
## Tujuan
Kamus Menu menjelaskan **setiap menu** di aplikasi: untuk apa, apa yang harus diaktifkan supaya menu itu muncul, alurnya, dan **apa akibatnya ke stok, kas, dan laporan** setiap kali sebuah tombol diklik. Ada juga bagian **Cara Melakukan** untuk pertanyaan yang punya lebih dari satu cara (mis. memindahkan barang gudang ke cabang).

Bedanya dengan Panduan: Panduan = langkah klik. Kamus Menu = fungsi, syarat, dan dampak.

## Langkah-langkah
1. Buka **Bantuan → Kamus Menu**, atau klik tombol **Kamus** di bagian atas halaman mana pun — langsung terbuka penjelasan menu yang sedang dibuka.
2. Ketik yang ingin diketahui di kotak pencarian, pakai kata sehari-hari, mis. "pindah barang", "batal order", "stok habis di POS".
3. Klik hasil untuk membaca penjelasan lengkap. Kotak kanan menunjukkan **siapa yang bisa membuka menu itu** (centangan di Role & Hak Akses) dan **menu terkait**.
4. Tombol **Buka menu ini** langsung membuka menunya.

## Catatan Penting
- Yang tampil hanya penjelasan menu yang **boleh Anda buka**. Owner melihat semuanya.
- Setiap bagian memakai urutan yang sama: Fungsi → Supaya menu ini muncul & bisa dipakai → Alur → Apa yang terjadi kalau tombol diklik → Wajib → Jangan → Menu yang mirip.
- Tanda ⚠️ = hal yang sering membuat angka stok/kas/laporan tidak cocok.

## Troubleshooting
- **Pencarian tidak menemukan apa-apa**: pakai kata yang lebih umum (stok, kas, order, harga).
- **Tombol Kamus membuka daftar, bukan penjelasan**: halaman itu belum punya penjelasan khusus; cari namanya di kotak pencarian.
MARKDOWN
            ,
            'modul'  => 'bantuan',
            'urutan' => 20,
            'aktif'  => true,
        ]);
    }

    private function tooltip(): void
    {
        Tooltip::updateOrCreate(['key' => 'kamus_menu.cari'], [
            'title'   => 'Cari di Kamus Menu',
            'content' => 'Ketik dengan kata sehari-hari, mis. "pindah barang ke cabang" atau "batal order". Semua kata dicari di judul, kata kunci, dan isi penjelasan; hasil yang memuat semua kata ditampilkan paling atas.',
            'modul'   => 'bantuan',
            'aktif'   => true,
            'urutan'  => 1,
        ]);
    }
}
