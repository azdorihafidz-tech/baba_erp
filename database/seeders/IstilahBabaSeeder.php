<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 4–5 — merapikan istilah warisan dimsum / Berkah Mulyo di teks panduan & tooltip
 * (bakso, jasa giling, kulit dimsum, sumpit, …) menjadi istilah parfum. Penggantian frasa demi frasa,
 * idempotent (frasa yang sudah diganti tidak ditemukan lagi). Isi panduan yang dibuat Owner tidak disentuh
 * kecuali memuat frasa lama yang persis sama.
 */
class IstilahBabaSeeder extends Seeder
{
    /** Urutan penting: frasa panjang dulu. */
    public const GANTI = [
        'bisnis jasa giling dan produksi olahan daging seperti Berkah Mulyo' => 'bisnis produksi & penjualan parfum seperti BABA PARFUME',
        'Berkah Mulyo' => 'BABA PARFUME',
        "di konsep D'mentai" => 'di ERP BABA',
        "D'mentai" => 'BABA',
        'Uang masuk dari jasa giling' => 'Uang masuk dari penjualan parfum',
        '**4-1101** (Penjualan Jasa Giling)' => '**4-1102** (Penjualan Parfum)',
        '| Terima uang dari pelanggan jasa giling | Jasa Giling | 4-1101 |' => '| Jual parfum (kasir / pesanan online — otomatis) | Penjualan | 4-1102 |',
        '| Jual produk jadi (bakso/sosis/tempura) | Penjualan Produk | 4-1102 |' => '| Terima uang jasa (mis. racik pesanan khusus) | Lainnya | 4-1199 |',
        'mesin bakso' => 'mesin produksi',
        'mesin giling' => 'mesin produksi',
        'Beli tepung/daging (bahan baku)' => 'Beli bibit parfum / alkohol (bahan baku)',
        'kulit dimsum, isian, saus' => 'bibit parfum, alkohol, fixative',
        'kemasan (kotak, plastik, sumpit)' => 'kemasan (botol, tutup, kotak)',
        'kotak/plastik/sumpit' => 'botol/tutup/kotak',
        '(tepung, daging, saus, dsb)' => '(bibit parfum, alkohol, fixative, dsb)',
        'BB-001 Kulit Dimsum' => 'BB-001 Alkohol Parfum',
        '"Kulit Dimsum"' => '"Alkohol Parfum"',
        'Produk Jual (dimsum/gyoza/drink matang)' => 'Produk Jual (produk kasir)',
        'Produk Jual (dimsum/gyoza matang)' => 'Produk Jual (produk kasir)',
        'Dimsum Mentai' => 'Vanilla Oud',
        '`Dimsum`, `Gyoza`, `Frozen Food`' => '`Parfum Pria`, `Parfum Wanita`, `Isi Ulang`',
        '"Gyoza" dan "gyoza"' => '"Isi Ulang" dan "isi ulang"',
        '`dimsum`, `frozen_food`' => '`parfum_pria`, `isi_ulang`',
        '(mis. Dimsum, Gyoza, Minuman, Frozen)' => '(mis. Parfum Pria, Parfum Wanita, Unisex)',
        '(mis. Dimsum, Gyoza)' => '(mis. Parfum Pria, Parfum Wanita)',
        '(mis. Dimsum Ayam butuh Kulit Dimsum + Daging Ayam + Bumbu)' => '(mis. Isi Ulang Vanilla Oud butuh Cairan Vanilla Oud per ml)',
        'produk jual (dimsum, gyoza, drink)' => 'produk jual (parfum untuk kasir)',
        'sumpit/garpu' => 'kantong/kotak',
        '(mis. daging kehilangan berat air saat disimpan)' => '(mis. cairan parfum menguap saat diperam / disimpan)',
        'kurang bahan untuk bikin dimsum' => 'kurang barang untuk dijual',
        '(tepung, daging, kulit), bukan dimsum jadi' => '(botol parfum, cairan isi ulang), bukan produk kasir',
        'penjualan dimsum/gyoza harian' => 'penjualan parfum harian',
        '(dimsum, gyoza, minuman, frozen)' => '(parfum botol, isi ulang per ml)',
        '(box dimsum, gyoza, frozen, minuman)' => '(botol, kotak parfum)',
        'susut per kg output (cocok: mesin produksi)' => 'susut per jumlah hasil produksi (cocok: mesin produksi)',
        'Contoh: Mesin giling' => 'Contoh: Mesin produksi',
        '(mis. Dimsum, Gyoza, Frozen)' => '(mis. Parfum Botol, Isi Ulang)',
        '— Bakso vs Sosis vs Tempura' => '— mis. Parfum Pria vs Parfum Wanita',
        '(minuman kaleng, snack kemasan, dimsum frozen impor)' => '(mis. parfum jadi dari pemasok)',
        '(sumpit, garpu, saus kecil — dibeli dari suppli' => '(kantong, kartu ucapan, tester — dibeli dari suppli',
        '(tepung, daging, saus, bumbu, dsb)' => '(bibit parfum, alkohol, fixative, dsb)',
        '1 porsi Dimsum Ayam butuh 5 pcs Kulit Dimsum' => '1 botol Vanilla Oud 50 ml butuh 50 ml Cairan Vanilla Oud',
        'bahan + kemasan + sumpit sebagai' => 'bahan + kemasan + kotak sebagai',
        '"Bumbu Kecap Manis Standar" dipakai Dimsum Ayam, Dimsum Udang, dan Gyoza sekaligus' => '"Campuran Standar" dipakai beberapa produk sekaligus',
        '(mis. 1 porsi untuk Dimsum Ayam, 1.5 porsi untuk Dimsum Udang yang' => '(mis. 1 porsi untuk produk A, 1.5 porsi untuk produk B yang',
        '3 produk dimsum semua pakai "Bumbu Kecap Manis Standar"' => '3 produk semua pakai "Campuran Standar"',
        '(jasa giling, penjualan produk)' => '(penjualan produk)',
        // Tahap 5: gaji tercatat di keuangan saat slip ditandai dibayar
        '- Slip gaji otomatis terhitung masuk sebagai **Pengeluaran Gaji** di modul Keuangan'
            => '- Setelah gaji diserahkan, buka slip lalu pilih kas pembayaran dan tekan **Tandai Dibayar**: gaji dicatat sebagai **Pengeluaran Gaji** (Beban Gaji 6-1101) dan saldo kas berkurang — sekali per slip. Tanpa kas yang dipilih, dipakai Kas Transfer lokasi itu.',
        // kunci memuat baris sesudahnya supaya tidak cocok lagi dengan hasilnya (seeder aman dijalankan berulang)
        "7. Klik **ikon printer** untuk cetak slip gaji\n\n## Catatan Penting"
            => "7. Klik **ikon printer** untuk cetak slip gaji\n8. Setelah gaji dibayarkan, buka slip → pilih kas → **Tandai Dibayar** (pengeluaran gaji tercatat otomatis di Keuangan & Laba Rugi)\n\n## Catatan Penting",
        '(contoh: "Dimsum & Gyoza")' => '(contoh: "Parfum Isi Ulang & Botol")',
        // tooltip
        'Contoh: Daging Segar, Bumbu, Kemasan Plastik.' => 'Contoh: Bibit Parfum, Alkohol, Kemasan Botol.',
        'Produk = bakso, sosis, tempura (Berkah Mulyo produksi & jual sendiri). Jasa Giling = pelanggan bawa daging sendiri untuk digiling (tarif per kg).'
            => 'Produk = parfum yang diproduksi & dijual sendiri. Jasa = layanan bertarif (mis. racik pesanan khusus).',
        'Produk = bakso, sosis, tempura (BABA PARFUME produksi & jual sendiri). Jasa Giling = pelanggan bawa daging sendiri untuk digiling (tarif per kg).'
            => 'Produk = parfum yang diproduksi & dijual sendiri. Jasa = layanan bertarif (mis. racik pesanan khusus).',
        '(mis. Kulit Dimsum, Isian)' => '(mis. Cairan Parfum, Botol)',
        '(mis. minuman kemasan jadi)' => '(mis. parfum jadi dari pemasok)',
        '(tepung, daging, saus, bumbu)' => '(bibit parfum, alkohol, fixative)',
    ];

    /** Tambahan per panduan (ditambahkan sekali di akhir bila belum ada). */
    public const TAMBAH = [
        'pos' => "\n## Toko parfum BABA\n- **Scan barcode:** arahkan scanner ke barcode botol — botol langsung masuk keranjang (kursor boleh di kotak **Cari produk / scan barcode** atau di mana saja). Barcode diisi di **Produk Parfum Toko**.\n- **Isi ulang per ml:** pilih produk *Isi Ulang … (per ml)*, lalu isi jumlah = ml.\n- Tombol **Cek Antrian** & centang **Tampilkan di Antrian Produksi** hanya muncul bila antrian dinyalakan untuk toko itu (Cabang › Edit).\n- **Bukti QRIS / Transfer** wajib atau tidak diatur per toko di Cabang › Edit.",
        'karyawan' => "\n- **Akun login karyawan:** centang **Buat akun login**, isi email & peran. Password awal dibuat **acak** dan hanya ditampilkan sekali setelah disimpan — catat, berikan ke karyawan, lalu minta ia menggantinya di Profil.",
    ];

    public function run(): void
    {
        foreach (self::TAMBAH as $slug => $teks) {
            $p = DB::table('panduan')->where('slug', $slug)->first(['id', 'konten']);
            if ($p && ! str_contains((string) $p->konten, trim($teks))) {
                DB::table('panduan')->where('id', $p->id)->update(['konten' => rtrim((string) $p->konten) . "\n" . $teks, 'updated_at' => now()]);
                Cache::forget("panduan:{$slug}");
            }
        }
        // [tabel, kolom isi, kolom kunci cache, awalan cache] — DB::table tidak memicu event model, jadi cache dihapus di sini
        foreach ([['panduan', 'konten', 'slug', 'panduan:'], ['tooltips', 'content', 'key', 'tooltip:']] as [$tabel, $kolom, $kunci, $cache]) {
            foreach (DB::table($tabel)->get(['id', $kolom, $kunci]) as $baris) {
                $baru = strtr((string) $baris->{$kolom}, self::GANTI);
                if ($baru !== $baris->{$kolom}) {
                    DB::table($tabel)->where('id', $baris->id)->update([$kolom => $baru, 'updated_at' => now()]);
                    Cache::forget($cache . $baris->{$kunci});
                }
            }
        }
    }
}
