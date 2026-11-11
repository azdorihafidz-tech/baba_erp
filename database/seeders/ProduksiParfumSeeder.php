<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\ParfumKonsentrasi;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * ERP BABA Tahap 1 — produksi parfum: konsentrasi bawaan (contoh, bisa diubah Owner),
 * panduan "produksi" ditulis ulang untuk parfum, tooltip pemeraman & konsentrasi.
 * Idempotent. Harus SETELAH Porting449Seeder (yang juga menulis panduan "produksi").
 */
class ProduksiParfumSeeder extends Seeder
{
    /** Contoh awal — angka asli diisi Owner di Produksi › Konsentrasi. */
    public const KONSENTRASI = [
        ['nama' => 'EDP', 'keterangan' => 'Eau de Parfum', 'persen_bibit' => 20, 'persen_fixative' => 5, 'lama_peram_hari' => 14, 'urutan' => 1],
        ['nama' => 'EDT', 'keterangan' => 'Eau de Toilette', 'persen_bibit' => 10, 'persen_fixative' => 5, 'lama_peram_hari' => 7, 'urutan' => 2],
        ['nama' => 'EDC', 'keterangan' => 'Eau de Cologne', 'persen_bibit' => 4, 'persen_fixative' => 2, 'lama_peram_hari' => 3, 'urutan' => 3],
        ['nama' => 'Extrait', 'keterangan' => 'Extrait de Parfum', 'persen_bibit' => 30, 'persen_fixative' => 5, 'lama_peram_hari' => 21, 'urutan' => 4],
    ];

    public function run(): void
    {
        foreach (self::KONSENTRASI as $k) {
            ParfumKonsentrasi::firstOrCreate(['nama' => $k['nama']], $k + ['is_active' => true]);
        }

        Panduan::updateOrCreate(['slug' => 'produksi'], [
            'judul'  => 'Produksi Parfum',
            'konten' => self::PANDUAN,
            'modul'  => 'stok',
            'urutan' => 45,
            'aktif'  => true,
        ]);

        foreach ([
            ['key' => 'produksi.barang_hasil', 'title' => 'Barang hasil',
                'content' => 'Yang dibuat: cairan parfum (mis. Cairan Ocean Breeze EDP, satuan ml) atau parfum botol (mis. Ocean Breeze EDP 50 ml, satuan pcs). Daftarkan dulu di Bahan Baku & Kemasan milik gudang ini.'],
            ['key' => 'produksi.qty_hasil_resep', 'title' => 'Hasil per resep',
                'content' => 'Jumlah hasil dari 1 kali resep. Contoh racik: bahan untuk 1.000 ml cairan. Contoh isi botol: bahan untuk 1 botol.'],
            ['key' => 'produksi.qty_rencana', 'title' => 'Rencana dibuat',
                'content' => 'Jumlah yang diracik/diisi. Bahan dipotong dari stok gudang sebanyak resep × rencana ini.'],
            ['key' => 'produksi.qty_hasil', 'title' => 'Hasil nyata',
                'content' => 'Jumlah yang benar-benar jadi (mis. rencana 50 botol jadi 49 karena 1 pecah). Untuk resep yang diperam, hasil akhir diisi saat Selesai Peram & Saring.'],
            ['key' => 'produksi.lama_peram', 'title' => 'Lama pemeraman',
                'content' => 'Berapa hari cairan didiamkan supaya aroma menyatu. 0 = hasil langsung masuk stok. Lebih dari 0 = produksi berstatus Diperam; bahan langsung dipotong, cairan masuk stok setelah Selesai Peram & Saring.'],
            ['key' => 'produksi.pemeraman', 'title' => 'Pemeraman',
                'content' => 'Tanggal racik sampai tanggal siap. Selama diperam, cairan belum bisa dipakai mengisi botol.'],
            ['key' => 'produksi.hasil_akhir', 'title' => 'Hasil akhir setelah disaring',
                'content' => 'Volume cairan yang benar-benar didapat setelah diperam dan disaring. Selisih dengan yang diracik tercatat sebagai susut (penguapan/ampas). Modal per ml = biaya bahan ÷ hasil akhir.'],
            ['key' => 'produksi.batalkan', 'title' => 'Batalkan produksi',
                'content' => 'Bahan dikembalikan ke stok. Kalau hasil sudah masuk stok, hasil ditarik dulu — hanya bisa kalau stok hasil di gudang masih cukup (belum terpakai).'],
            ['key' => 'parfum.nama_aroma', 'title' => 'Nama aroma',
                'content' => 'Dipakai untuk nama barang yang dibuat, mis. aroma Ocean Breeze + EDP → Cairan Ocean Breeze EDP dan Ocean Breeze EDP 50 ml. Terisi otomatis dari nama bibit, boleh diganti.'],
            ['key' => 'parfum.ukuran', 'title' => 'Ukuran botol',
                'content' => 'Setiap ukuran menjadi satu barang parfum botol (pcs) dengan resep isi botol: cairan sebanyak ukurannya + 1 botol + 1 dari setiap kemasan tambahan.'],
            ['key' => 'konsentrasi.persen_bibit', 'title' => '% bibit, fixative, alkohol',
                'content' => 'Takaran racik dari volume cairan. Contoh EDP 20% bibit + 5% fixative → alkohol 75%. Untuk 1.000 ml: bibit 200 ml, fixative 50 ml, alkohol 750 ml.'],
        ] as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'], 'modul' => 'stok', 'aktif' => true, 'urutan' => 80 + $i,
            ]);
        }
    }

    public const PANDUAN = <<<'MARKDOWN'
## Tujuan
Mencatat pembuatan parfum di gudang dalam **dua tingkat**:
1. **Racik cairan** — bibit + fixative + alkohol menjadi cairan parfum (ml). Bisa **diperam** dulu beberapa hari.
2. **Isi botol** — cairan + botol + sprayer + label + box menjadi parfum botol (pcs).

Setiap produksi memotong bahan dari stok gudang (modal FIFO) dan menambah stok hasil dengan **modal per unit otomatis** (biaya bahan ÷ hasil).

## Langkah-langkah
0. **Cara cepat — Buat Parfum** (tab Buat Parfum): pilih Konsentrasi, Bibit (aroma), Fixative, Alkohol, ukuran botol beserta barang botolnya, dan kemasan tambahan (sprayer, label, box). Tekan **Buat Parfum** → sistem membuat cairan + resep racik dan parfum botol + resep isi botol untuk setiap ukuran. Langkah 2 tidak perlu dikerjakan manual.
1. **Konsentrasi** (tab Konsentrasi): atur % bibit, % fixative (sisanya alkohol) dan lama pemeraman untuk EDP, EDT, dan lainnya.
2. **Resep Produksi** (tab Resep Produksi): satu resep untuk setiap barang hasil.
   - Resep racik: hasil per resep mis. 1.000 ml, bahan bibit/fixative/alkohol sesuai persen, isi **Lama pemeraman** (hari).
   - Resep isi botol: hasil 1 botol, bahan mis. 50 ml cairan + 1 botol + 1 sprayer + 1 label + 1 box, lama pemeraman 0.
3. **Catat Produksi**: pilih barang yang dibuat, isi **Rencana dibuat**.
   - Resep tanpa pemeraman: isi **Hasil nyata**, simpan → stok hasil langsung bertambah.
   - Resep dengan pemeraman: simpan → status **Diperam** sampai tanggal siap. Bahan sudah terpotong, cairan belum masuk stok.
4. **Selesai Peram & Saring**: setelah tanggal siap, buka produksi itu, isi **Hasil akhir setelah disaring**, lalu tekan **Selesai Peram & Saring**. Cairan masuk stok, susut tercatat.
5. Setelah cairan tersedia, catat produksi **isi botol**.

## Catatan Penting
- Daftar Riwayat Produksi menandai batch **Diperam · siap tgl** dan **Siap disaring** (sudah lewat tanggal siap).
- Tombol Selesai Peram & Saring baru aktif mulai tanggal siap.
- Hasil akhir tidak boleh lebih dari jumlah yang diracik.
- Harga Standar barang hasil hanya diisi otomatis kalau masih kosong; HPP penjualan tetap memakai modal FIFO.

## Troubleshooting
- **Stok bahan tidak cukup**: pesan menyebut semua bahan yang kurang. Tambah stok lewat Pembelian (PO) dulu.
- **Salah racik**: buka produksinya → Batalkan Produksi (isi alasan). Bahan dikembalikan ke stok.
- **Barang hasil tidak muncul di daftar resep**: daftarkan dulu di Bahan Baku & Kemasan milik gudang ini.
- **Buat Parfum ditolak "Sudah ada barang dengan nama …"**: aroma + konsentrasi itu sudah pernah dibuat. Pakai resep yang ada, atau ganti nama aroma.
MARKDOWN;
}
