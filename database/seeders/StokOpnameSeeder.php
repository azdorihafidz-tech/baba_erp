<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use App\Services\StokOpnameService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 7 — Stok Opname: izin (Role & Hak Akses), akun COA & kategori transaksi per penyebab, panduan, tooltip.
 * Idempotent; centangan bawaan peran hanya dipasang saat izin pertama kali dibuat (pilihan Owner sesudahnya tidak ditimpa).
 */
class StokOpnameSeeder extends Seeder
{
    public const PERMISSIONS = [
        ['name' => 'stok_opname.lihat', 'display_name' => 'Lihat Stok Opname & berita acara', 'group' => 'stok_opname'],
        ['name' => 'stok_opname.hitung', 'display_name' => 'Mulai opname & menghitung stok fisik', 'group' => 'stok_opname'],
        ['name' => 'stok_opname.ajukan', 'display_name' => 'Periksa selisih, isi penyebab & ajukan opname', 'group' => 'stok_opname'],
        ['name' => 'stok_opname.setujui', 'display_name' => 'Setujui / tolak opname (stok & keuangan disesuaikan)', 'group' => 'stok_opname'],
        ['name' => 'stok_opname.batal', 'display_name' => 'Batalkan opname yang belum disetujui', 'group' => 'stok_opname'],
    ];

    public const ROLE_DEFAULTS = [
        'admin_pusat'    => ['stok_opname.lihat', 'stok_opname.hitung', 'stok_opname.ajukan', 'stok_opname.setujui', 'stok_opname.batal'],
        'admin_gudang'   => ['stok_opname.lihat', 'stok_opname.hitung', 'stok_opname.ajukan'],
        'manajer_cabang' => ['stok_opname.lihat', 'stok_opname.hitung', 'stok_opname.ajukan'],
        'kasir'          => ['stok_opname.lihat', 'stok_opname.hitung'],
        'helper'         => ['stok_opname.lihat', 'stok_opname.hitung'],
        'pemilik_toko'   => ['stok_opname.lihat', 'stok_opname.hitung', 'stok_opname.ajukan', 'stok_opname.setujui', 'stok_opname.batal'],
    ];

    /** Akun beban per penyebab (di bawah 6-0000 BEBAN OPERASIONAL). */
    public const COA = [
        ['6-1304', 'Beban Promosi (Tester)', 512],
        ['6-1402', 'Kerugian Stok - Rusak', 521],
        ['6-1403', 'Kerugian Stok - Hilang', 522],
        ['6-1404', 'Penyusutan Stok (Susut)', 523],
        ['6-1405', 'Kerugian Stok - Kedaluwarsa', 524],
        ['6-1406', 'Selisih Persediaan', 525],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $p) {
            $perm = Permission::firstOrCreate(['name' => $p['name']], ['display_name' => $p['display_name'], 'group' => $p['group']]);
            if ($perm->wasRecentlyCreated) {
                $rows = [];
                foreach (self::ROLE_DEFAULTS as $role => $names) {
                    if (in_array($p['name'], $names, true)) {
                        $rows[] = ['role' => $role, 'permission_id' => $perm->id];
                    }
                }
                DB::table('role_permissions')->insertOrIgnore($rows);
            }
        }
        foreach (array_keys(self::ROLE_DEFAULTS) as $role) {
            Cache::forget("role_permissions_{$role}");
        }
        Cache::forget('all_permission_names');

        foreach (self::COA as [$kode, $nama, $urutan]) {
            if (! DB::table('chart_of_accounts')->where('kode', $kode)->exists()) {
                DB::table('chart_of_accounts')->insert(['kode' => $kode, 'nama' => $nama, 'tipe' => 'beban_operasional', 'parent_kode' => '6-0000',
                    'saldo_normal' => 'debet', 'level' => 2, 'is_leaf' => true, 'urutan' => $urutan, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach (array_keys(StokOpnameService::PENYEBAB) as $penyebab) {
            StokOpnameService::kategori($penyebab);
        }

        Panduan::updateOrCreate(['slug' => 'stok-opname'], [
            'judul' => 'Stok Opname', 'konten' => self::PANDUAN, 'modul' => 'stok', 'urutan' => 4, 'aktif' => true,
        ]);
        Cache::forget('panduan:stok-opname');

        // Penyesuaian Stok lama: audit/salah hitung turun kini tercatat sebagai Selisih Persediaan
        $adj = Panduan::where('slug', 'stok-adjustment')->first();
        if ($adj && ! str_contains((string) $adj->konten, 'Selisih Persediaan')) {
            $adj->update(['konten' => rtrim((string) $adj->konten) . "\n\n## Catatan BABA\n- Untuk menghitung seluruh barang satu lokasi sekaligus, pakai menu **Stok Opname** (ada berita acara & persetujuan).\n"
                . "- Stok **turun** dengan alasan *Audit Berkala (Stock Opname)* atau *Salah Hitung* kini tercatat di keuangan sebagai **Selisih Persediaan** (laba turun, kas tidak berubah). Untuk barang yang benar-benar pecah atau hilang, pilih alasan **Rusak** / **Hilang**.\n"]);
            Cache::forget('panduan:stok-adjustment');
        }

        foreach (self::TOOLTIP as $key => [$title, $content]) {
            Tooltip::updateOrCreate(['key' => $key], ['title' => $title, 'content' => $content, 'modul' => 'stok', 'aktif' => true]);
            Cache::forget("tooltip:{$key}");
        }
    }

    public const TOOLTIP = [
        'stok_opname.hitung_buta' => ['Hitung buta', 'Penghitung tidak melihat angka stok sistem, hanya mengisi jumlah yang ada di rak. Angka sistem & selisih hanya terlihat oleh pemeriksa dan penyetuju — supaya hitungan jujur, bukan menyalin angka sistem.'],
        'stok_opname.cakupan' => ['Cakupan opname', 'Semua barang di lokasi, barang dalam kategori tertentu, atau beberapa barang saja (mis. cek mendadak barang mahal). Satu barang hanya boleh ada di satu opname yang sedang berjalan.'],
        'stok_opname.penyebab' => ['Penyebab selisih', 'Kurang 5 bisa dipecah, mis. 2 rusak + 2 hilang + 1 tester. Setiap penyebab dicatat ke pos beban sendiri di Laba Rugi. Barang rusak wajib difoto; barang hilang wajib difoto bila nilainya di atas batas di Pengaturan.'],
        'stok_opname.sistem' => ['Stok sistem', 'Stok menurut ERP pada saat barang itu dihitung. Toko tetap boleh berjualan selama opname: saat disetujui hanya SELISIH yang diterapkan, jadi penjualan sesudah barang dihitung tidak terhapus.'],
    ];

    public const PANDUAN = <<<'MD'
## Untuk apa?
Mencocokkan **stok fisik** di rak dengan **stok sistem** ERP, menyesuaikan stok sesuai **penyebab** selisih, dan mencatat kerugiannya di keuangan. Barang rusak/hilang **tidak memotong kas** — yang turun adalah nilai persediaan, dicatat sebagai beban sehingga laba turun.

## Langkah
1. Buka **Stok & Gudang › Stok Opname**, tekan **Mulai Opname**. Pilih lokasi dan cakupan: **Semua barang**, **Per kategori**, atau **Barang tertentu**.
2. **Hitung fisik**: isi jumlah di kolom **Fisik** setiap barang, atau scan barcode di kotak **Scan / cari barang** (setiap scan = +1). Barang yang tidak ada di rak diisi **0**. Hitungan tersimpan otomatis.
3. Dengan **hitung buta** (bawaan), penghitung tidak melihat stok sistem. Pemeriksa membuka tab **Selisih** untuk melihat barang yang tidak cocok.
4. Barang yang meragukan: centang lalu tekan **Minta hitung ulang**. Penghitung menghitung lagi barang yang ditandai.
5. Untuk barang yang **kurang**, tekan **Isi penyebab**: pecah jumlahnya ke Rusak / Hilang / Susut / Tester / Kedaluwarsa / Salah catat, lampirkan **foto** (wajib untuk rusak; untuk hilang bila nilainya di atas batas). Barang yang **lebih** tercatat otomatis.
6. Tekan **Ajukan**. Opname terkunci (tidak bisa dihitung) sambil menunggu persetujuan.
7. Penyetuju memeriksa lalu **Setujui** — stok & keuangan langsung disesuaikan dan opname dikunci — atau **Tolak** dengan alasan (opname kembali ke tahap hitung).

## Pencatatan keuangan
| Penyebab | Pos di Laba Rugi |
|---|---|
| Rusak / pecah / bocor | Kerugian Stok - Rusak |
| Hilang | Kerugian Stok - Hilang |
| Susut / menguap | Penyusutan Stok (Susut) |
| Tester / promosi | Beban Promosi (Tester) |
| Kedaluwarsa | Kerugian Stok - Kedaluwarsa |
| Salah catat | Selisih Persediaan |
| Lebih (fisik > sistem) | Koreksi Stok Masuk |

Nilai = **harga pokok** (harga beli FIFO), bukan harga jual.

## Aturan penting
- Toko **tetap boleh berjualan** selama opname: stok sistem dicatat saat barang dihitung, dan saat disetujui hanya **selisihnya** yang diterapkan.
- Orang yang ikut **menghitung** tidak bisa menyetujui opname itu (kecuali Owner).
- **Toko Kemitraan / Toko Refill Member**: opname dimulai dan disetujui pemilik toko itu sendiri. Owner BABA hanya melihat, dan kerugiannya tidak masuk Laba Rugi BABA.
- Siapa boleh menghitung, mengajukan, menyetujui, membatalkan → atur di **Role & Hak Akses** (grup STOK_OPNAME).
- Opname yang sudah disetujui tidak bisa diubah. Yang belum disetujui bisa **Dibatalkan** dengan alasan.

## Pertanyaan umum
- **Tombol Setujui tidak ada?** Anda ikut menghitung opname itu, tidak punya izin setujui, atau itu opname toko mitra.
- **Ditolak "stok sekarang tinggal …"?** Ada penjualan/transfer setelah barang dihitung sehingga stok tidak cukup untuk dikurangi. Hitung ulang barang itu, lalu ajukan lagi.
MD;
}
