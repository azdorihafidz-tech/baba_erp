<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 2 — Toko Online: izin, panduan, tooltip. Idempotent.
 * Centangan role bawaan hanya dipasang saat izin pertama kali dibuat (pola Porting449Seeder).
 */
class TokoOnlineSeeder extends Seeder
{
    public const PERMISSIONS = [
        ['name' => 'toko_online.kelola', 'display_name' => 'Kelola Toko Online (produk & harga menu Belanja aplikasi member)', 'group' => 'toko_online'],
    ];

    public const ROLE_DEFAULTS = [
        'admin_pusat' => ['toko_online.kelola'],
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
                foreach (array_keys(self::ROLE_DEFAULTS) as $role) {
                    Cache::forget("role_permissions_{$role}");
                }
            }
        }
        Cache::forget('all_permission_names');

        Panduan::updateOrCreate(['slug' => 'produk-online'], [
            'judul'  => 'Produk Online',
            'konten' => self::PANDUAN,
            'modul'  => 'toko_online',
            'urutan' => 60,
            'aktif'  => true,
        ]);

        foreach ([
            ['key' => 'produk_online.barang', 'title' => 'Barang yang dijual',
                'content' => 'Parfum botol milik gudang (dibuat di Produksi › Buat Parfum). Satu barang hanya bisa didaftarkan sekali. Stok yang dijual = stok barang ini di gudangnya.'],
            ['key' => 'produk_online.harga_tetap', 'title' => 'Harga tetap',
                'content' => 'Harga dasar untuk semua member bila tidak ada harga khusus akun, harga jabatan, atau harga gudang.'],
            ['key' => 'produk_online.harga_gudang', 'title' => 'Harga gudang',
                'content' => 'Harga bila member membeli langsung ke gudang (bukan lewat upline). Dipakai sebelum harga tetap.'],
            ['key' => 'produk_online.harga_jabatan', 'title' => 'Harga per jabatan',
                'content' => 'Harga untuk jabatan tertentu (mis. Distributor lebih murah dari Reseller). Kosongkan bila jabatan itu memakai harga gudang/tetap. Daftar jabatan diambil dari aplikasi member.'],
            ['key' => 'produk_online.harga_akun', 'title' => 'Harga khusus akun',
                'content' => 'Harga untuk akun tertentu saja, paling diutamakan. Cari akun dengan nama, No ID, atau No Member (BP-xxxxx). Hapus barisnya untuk mengembalikan ke harga biasa.'],
        ] as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'], 'modul' => 'toko_online', 'aktif' => true, 'urutan' => 10 + $i,
            ]);
        }
    }

    public const PANDUAN = <<<'MARKDOWN'
## Tujuan
Mengatur parfum yang dijual di menu **Belanja** aplikasi member beserta harganya.

## Langkah-langkah
1. Buka **Toko Online › Produk Online**, tekan **Tambah Produk Online**.
2. Pilih **Barang yang dijual** (parfum botol dari Produksi › Buat Parfum). Nama di aplikasi member terisi otomatis, boleh diganti.
3. Isi kategori (Pria / Wanita / Unisex), deskripsi, foto, dan **Harga tetap** dan/atau **Harga gudang**. Tekan **Simpan**.
4. Di halaman ubah, atur **Harga per jabatan** (mis. Distributor, Reseller) dan **Harga khusus akun** bila perlu.
5. Matikan **Tampil di aplikasi** untuk menyembunyikan produk tanpa menghapusnya.

## Catatan Penting
- Urutan harga untuk seorang member: **harga khusus akun → harga jabatan → harga gudang** (beli langsung ke gudang) **→ harga tetap**.
- Produk yang tidak punya harga yang berlaku untuk seorang member tidak tampil untuk member itu.
- Stok yang terlihat member = stok barang itu di gudangnya.
- Setiap perubahan harga tercatat di Log Aktivitas.

## Troubleshooting
- **Daftar jabatan belum bisa diambil**: sambungan ke aplikasi member belum diatur atau sedang terputus. Harga per jabatan yang sudah tersimpan tetap berlaku.
- **Barang tidak ada di pilihan**: barang harus milik gudang Anda, aktif, dan belum didaftarkan sebagai produk online.
MARKDOWN;
}
