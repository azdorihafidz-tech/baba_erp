<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * 4.47 — izin "Ubah Harga Jual di POS". ADDITIVE & idempotent, aman di
 * production: hanya membuat izinnya. Keputusan Owner: TIDAK dicentang untuk
 * role mana pun (Owner selalu bisa lewat Gate::before) — Owner mencentang
 * sendiri di Role & Hak Akses kalau perlu. Tanpa izin ini kolom Harga/Unit
 * di POS terkunci dan server selalu memakai harga produk di cabang itu.
 * Juga menambah catatan panduan POS (gabung baris produk sama, panel bayar
 * mode Tablet) + tooltip.
 */
class PosUbahHargaSeeder extends Seeder
{
    public const PERMISSION = ['name' => 'order.ubah_harga', 'display_name' => 'Ubah Harga Jual di POS (kolom Harga/Unit)', 'group' => 'penjualan'];

    private const MARKER = '<!-- POS-447 -->';

    public function run(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISSION['name']],
            ['display_name' => self::PERMISSION['display_name'], 'group' => self::PERMISSION['group']]
        );
        Cache::forget('all_permission_names');

        $this->panduan();
        Tooltip::updateOrCreate(['key' => 'pos.harga_unit'], [
            'title'   => 'Harga/Unit',
            'content' => 'Harga diambil otomatis dari harga produk di cabang ini. Hanya akun yang punya izin "Ubah Harga Jual di POS" (diatur Owner di Role & Hak Akses) yang bisa mengubahnya; selain itu kolom ini terkunci.',
            'modul'   => 'pos',
            'aktif'   => true,
            'urutan'  => 60,
        ]);

        $this->command?->info('✅ Izin Ubah Harga Jual di POS (tidak dicentang untuk role mana pun) + panduan & tooltip (idempotent).');
    }

    private function panduan(): void
    {
        $p = Panduan::where('slug', 'pos')->first();
        if (! $p || str_contains((string) $p->konten, self::MARKER)) {
            return;
        }
        $p->konten = rtrim((string) $p->konten) . "\n\n" . self::MARKER . "\n" . <<<'MARKDOWN'
## Update — Harga Terkunci, Produk Sama Digabung, Panel Bayar Tablet

- **Harga/Unit terkunci.** Harga di keranjang selalu harga produk di cabang ini. Hanya akun yang punya izin **Ubah Harga Jual di POS** (Owner mencentangnya di **Role & Hak Akses → Penjualan**) yang bisa mengubahnya. Untuk potongan harga, pakai kolom **Diskon** di panel pembayaran.
- **Produk yang sama digabung.** Klik produk (dan varian) yang sudah ada di Item Order → **Qty bertambah 1**, tidak membuat baris baru. Kalau baris itu sudah diisi **Catatan item**, produk yang sama dibuat di baris baru (mis. 1 pedas, 1 tidak pedas).
- **Mode Tablet.** Panel **Ringkasan & Pembayaran** tidak ikut tergeser saat daftar produk di-scroll. Isinya bisa di-scroll sendiri; **Total, PROSES ORDER, dan Save Bill** selalu terlihat di bawah panel.
MARKDOWN;
        $p->save();
    }
}
