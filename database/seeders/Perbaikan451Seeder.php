<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * Porting Dimsum 4.51 (temuan B–G; A bayar gaji ke kas sudah ada di BABA Tahap 5) — Perbaikan temuan audit Kamus Menu: bagian panduan & tooltip yang berubah.
 * Idempotent: bagian panduan ditempel sekali (marker), tooltip updateOrCreate.
 */
class Perbaikan451Seeder extends Seeder
{
    public const MARKER = '<!-- PERBAIKAN-451 -->';

    public function run(): void
    {
        // BABA (keputusan Owner P6, 18 Nov 2026): Backup Database menampilkan cadangan harian dari cron server.
        Panduan::updateOrCreate(['slug' => 'backup-database'], [
            'judul' => 'Backup Database', 'modul' => 'keamanan', 'urutan' => 30, 'aktif' => true,
            'konten' => <<<'MD'
## Tujuan
Melihat dan mengunduh **cadangan database ERP** yang dibuat otomatis setiap hari.

## Langkah-langkah
1. Buka **Keamanan → Backup Database**.
2. Lihat status: **Aman** bila cadangan terbaru dibuat kurang dari 26 jam yang lalu.
3. Klik **Unduh** untuk menyimpan salinan di komputer atau penyimpanan lain (sesekali, mis. seminggu sekali).

## Catatan Penting
- Cadangan dibuat oleh **cron server** setiap hari jam 02.00 dan disimpan 14 hari (yang lebih lama terhapus otomatis). Cara memasangnya ada di **Pengaturan → Jadwal Otomatis** (Owner).
- Tidak ada lagi tombol "Buat backup" — cara lama itu tidak bisa berjalan di server hosting.
- File cadangan berisi seluruh data ERP. Jangan dibagikan.
MD,
        ]);

        foreach ($this->sections() as $slug => $konten) {
            $panduan = Panduan::where('slug', $slug)->first();
            if (! $panduan || str_contains((string) $panduan->konten, self::MARKER)) {
                continue;
            }
            $panduan->konten = rtrim((string) $panduan->konten) . "\n\n" . self::MARKER . "\n" . trim($konten) . "\n";
            $panduan->save();
        }

        foreach ([
            ['recurring.frekuensi', 'Frekuensi', 'Harian = setiap hari; Mingguan = setiap hari yang sama dengan Tanggal Mulai; Bulanan = setiap tanggal jatuh tempo (29–31 → hari terakhir bulan pendek); Tahunan = tanggal jatuh tempo di bulan Tanggal Mulai. Dibuat 1 kali per periode — tidak akan dobel.', 'recurring'],
            ['recurring.tanggal_jatuh_tempo', 'Tanggal Jatuh Tempo', 'Untuk frekuensi Bulanan & Tahunan. Kalau hari itu tidak ada yang membuka aplikasi, transaksi tetap dibuat saat aplikasi dibuka berikutnya (bertanggal jatuh tempo).', 'recurring'],
        ] as $i => [$key, $title, $content, $modul]) {
            Tooltip::updateOrCreate(['key' => $key], [
                'title' => $title, 'content' => $content, 'modul' => $modul, 'aktif' => true, 'urutan' => 90 + $i,
            ]);
        }
    }

    private function sections(): array
    {
        return [
            'penggajian' => <<<'MD'
## 🔄 Update 2026-11-18 — Potongan telat & slip terkunci
- Slip yang sudah **Disetujui/Dibayar** tidak ikut dihitung ulang saat Generate (dilewati dan disebutkan namanya). Dulu nominalnya tertimpa dan statusnya kembali Draft.
- **Potongan telat** = total menit telat (dari Scan Absensi) × "Potongan telat per menit" di Pengaturan Penggajian. Tampil di slip sebagai baris tersendiri.
MD,
            'recurring' => <<<'MD'
## 🔄 Update 2026-11-18 — Frekuensi berfungsi & tidak dobel
- Frekuensi **Harian, Mingguan, Bulanan, Tahunan** sekarang benar-benar dipakai (dulu semua berjalan bulanan).
- Setiap template dibuat **1 kali per periode** — dari jadwal otomatis, saat aplikasi dibuka, maupun tombol **Generate** (tombol menolak kalau periode itu sudah dibuat).
- Tanggal 29–31 dipakai sebagai hari terakhir di bulan pendek. Jatuh tempo yang terlewat (tidak ada yang membuka aplikasi) dibuat saat aplikasi dibuka berikutnya, bertanggal jatuh temponya.
- Pilihan "Auto-approve" dihapus karena tidak berfungsi.
MD,
            'cabang' => <<<'MD'
## 🔄 Update 2026-11-18 — Hapus cabang
- Cabang yang sudah punya order, PO, transaksi kas, pergerakan stok, absensi, atau slip gaji **tidak bisa dihapus** (dulu semua data itu ikut tersembunyi dan hilang dari laporan). Pakai **Nonaktifkan**. Hapus hanya untuk cabang yang salah dibuat dan masih kosong.
MD,
            'laporan-keuangan' => <<<'MD'
## 🔄 Update 2026-11-18 — Transfer internal tidak dihitung
- **Laba Rugi** di laporan ini tidak lagi menghitung transfer antar kas, setoran kasir, transfer dana, dan saldo awal kas (uang yang hanya pindah tempat). Jumlahnya ditampilkan sebagai keterangan di bawah kotak ringkasan.
- **Arus Kas** tetap menghitung semua uang yang bergerak, tetapi bagian transfer internal & saldo awal ditampilkan terpisah.
- User yang hanya boleh melihat cabangnya sendiri selalu melihat cabangnya (tidak bisa membuka cabang lain lewat alamat).
MD,
            'keuangan-laporan' => <<<'MD'
## 🔄 Update 2026-11-18 — Pemasukan & Pengeluaran Usaha
- Halaman ini sekarang berjudul **Pemasukan & Pengeluaran Usaha** dan tidak menghitung transfer antar kas, setoran kasir, transfer dana, serta saldo awal kas (ditampilkan sebagai keterangan). Angkanya berbasis kas — untuk laba dengan modal barang pakai Laba Rugi Formal atau Closing.
MD,
            'laporan-cabang' => <<<'MD'
## 🔄 Update 2026-11-18 — Pemasukan/pengeluaran usaha
- Kolom pemasukan & pengeluaran per cabang tidak lagi ikut menghitung transfer antar kas, setoran kasir, transfer dana, dan saldo awal kas. Setoran tetap tampil di kolom setoran.
MD,
            'laporan-neraca' => <<<'MD'
## 🔄 Update 2026-11-18 — Persediaan barang yang dihapus
- Persediaan tidak lagi menghitung sisa stok milik barang yang sudah dihapus (sama dengan Dashboard & Laporan Stok). Kalau selisih neraca berubah setelah pembaruan ini, sesuaikan Modal Owner.
MD,
        ];
    }
}
