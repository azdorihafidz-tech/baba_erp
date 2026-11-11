<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 4.44 Poin 2 — permission + panduan + tooltip Label Produk (stiker),
 * porting erp-coffeeshop "Label Cup". ADDITIVE & idempotent — aman di
 * production: hanya menambah permission `label_cup.print` dan mencentangnya
 * untuk role yang SUDAH punya izin transaksi POS (`order.create`, termasuk
 * role buatan). Mapping role lain tidak disentuh (beda dengan
 * RolePermissionSeeder yang delete-lalu-insert). Fiturnya sendiri tetap mati
 * sampai Owner menyalakan per cabang, jadi izin ini tidak mengubah tampilan.
 */
class LabelProdukSeeder extends Seeder
{
    public const PERMISSION = ['name' => 'label_cup.print', 'display_name' => 'Cetak Label Produk (stiker)', 'group' => 'penjualan'];

    /** Default mapping instalasi baru — sama dengan entry di RolePermissionSeeder. */
    public const ROLE_DEFAULTS = ['admin_pusat', 'manajer_cabang', 'kasir'];

    public function run(): void
    {
        $perm = Permission::firstOrCreate(
            ['name' => self::PERMISSION['name']],
            ['display_name' => self::PERMISSION['display_name'], 'group' => self::PERMISSION['group']]
        );

        $roles = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.name', 'order.create')
            ->pluck('role_permissions.role')
            ->merge(self::ROLE_DEFAULTS)
            ->unique()->values();

        DB::table('role_permissions')->insertOrIgnore(
            $roles->map(fn ($r) => ['role' => $r, 'permission_id' => $perm->id])->all()
        );

        Cache::forget('all_permission_names');
        foreach ($roles as $role) {
            Cache::forget("role_permissions_{$role}");
        }

        $this->panduan();
        $this->tooltip();

        $this->command?->info('✅ Label Produk: izin untuk ' . $roles->implode(', ') . ' + panduan & tooltip (idempotent).');
    }

    private function panduan(): void
    {
        Panduan::updateOrCreate(['slug' => 'label-produk'], [
            'judul'  => 'Label Produk (Stiker Kemasan)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Mencetak stiker untuk kemasan pesanan (box dimsum, gyoza, frozen, minuman) — 1 stiker untuk 1 qty item. Stiker berisi nama usaha, nomor pesanan, nama produk, varian, catatan item, nama pelanggan, tanggal kedaluwarsa (untuk Frozen), tipe pesanan, dan jam.

Fitur ini **mati** di semua cabang sampai dinyalakan. Selama mati, POS dan struk tidak berubah sama sekali.

## Langkah-langkah

### 1. Nyalakan di cabang
1. Buka **Cabang → Edit** cabang yang mau memakai label.
2. Di kotak **Label Produk (stiker)**, aktifkan **Aktifkan cetak label produk di cabang ini**.
3. Centang **kategori** yang perlu stiker (mis. Dimsum, Gyoza, Frozen). Item di kategori yang tidak dicentang tidak dicetak labelnya.
4. Isi **ukuran stiker** sesuai gulungan stiker (bawaan 40 × 30 mm).
5. Klik **Simpan**.

### 2. Saat transaksi
1. Di POS, setiap baris produk punya kolom **Catatan item** (mis. "saus dipisah", "tanpa cabe") — catatan ini ikut tercetak di stiker.
2. Setelah **Bayar**, di jendela struk muncul tombol **Cetak Label (N stiker)**.
3. Pilih cara cetak:
   - **Aplikasi kasir di tablet**: tekan **Cetak Label** — langsung ke printer label yang diatur di aplikasi. Kalau **Cetak label otomatis** dinyalakan di aplikasi, label tercetak sendiri setelah struk.
   - **Chrome/PC**: **Cetak Browser** (pilih printer label di dialog print) atau **Pilih Printer Bluetooth**.

### 3. Cetak ulang
Buka **Riwayat Penjualan → Detail Order** → tombol **Cetak Label**.

## Printer
- Printer label disimpan **terpisah** dari printer struk, jadi satu tablet bisa memakai printer struk dan printer label sekaligus.
- Di Chrome/PC pilih bahasa printer: **TSPL** (printer label, mis. Blueprint ECO80BT — disarankan) atau **ESC/POS** (printer struk). Kalau hasil cetak berupa huruf acak, ganti pilihannya.
- Di aplikasi kasir, bahasa printer label diatur di **Pengaturan Printer Aplikasi**.

## Catatan Penting
- Order dengan qty 3 menghasilkan 3 stiker (qty pecahan dibulatkan, minimal 1). Maksimal 100 stiker per order.
- Kategori baru yang ditambahkan nanti perlu dicentang dulu di Cabang → Edit supaya ikut dicetak.
- Tanggal Exp hanya tercetak untuk pesanan **Frozen** yang tanggal kedaluwarsanya diisi.
- Izin **Cetak Label Produk** diatur di menu Role & Hak Akses.

## Troubleshooting
- **Tombol Cetak Label tidak muncul?** Cek label sudah aktif di cabang itu, kategori produknya dicentang, dan role Anda punya izin Cetak Label Produk.
- **Stiker terpotong / bergeser?** Samakan ukuran stiker di Cabang → Edit dengan gulungan stiker yang terpasang.
- **Popup diblokir saat Cetak Browser?** Izinkan popup untuk alamat ERP di pengaturan Chrome.
MARKDOWN
            ,
            'modul'  => 'penjualan',
            'urutan' => 60,
            'aktif'  => true,
        ]);
    }

    private function tooltip(): void
    {
        $tooltips = [
            ['key' => 'cabang.label_cup_aktif', 'title' => 'Label Produk',
                'content' => 'Nyalakan untuk mencetak stiker kemasan (1 stiker per qty item) dari jendela struk dan Detail Order. Selama mati, POS dan struk tidak berubah.'],
            ['key' => 'cabang.label_cup_kategori', 'title' => 'Kategori Berlabel',
                'content' => 'Hanya item dari kategori yang dicentang yang dicetak stikernya. Kategori baru perlu dicentang di sini.'],
            ['key' => 'cabang.label_cup_ukuran', 'title' => 'Ukuran Stiker',
                'content' => 'Samakan dengan ukuran gulungan stiker di printer label (contoh 40 × 30 mm).'],
        ];

        foreach ($tooltips as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title'   => $t['title'],
                'content' => $t['content'],
                'modul'   => 'cabang',
                'aktif'   => true,
                'urutan'  => 40 + $i,
            ]);
        }
    }
}
