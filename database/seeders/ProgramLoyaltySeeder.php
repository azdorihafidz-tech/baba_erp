<?php

namespace Database\Seeders;

use App\Models\LoyaltyProgram;
use Illuminate\Database\Seeder;

/**
 * Program loyalty pertama: "pelanggan yang mencapai total belanja
 * Rp 500.000 (all-time) → dapat hadiah". Periode all-time
 * (periode_mulai/akhir NULL). Idempotent — dicek by nama, tidak menimpa
 * kalau Owner sudah edit manual via UI (mis. ganti target/hadiah).
 *
 * Bug fix 2026-09-21: sebelumnya seed ini basis kg giling (warisan Berkah
 * Mulyo, tidak relevan sama sekali utk D'mentai) -- diubah ke basis Total
 * Belanja Rp (sumber_data orders.total_bayar), sinkron dgn default baru
 * form Tambah Program Loyalty. Catatan: seeder ini TIDAK terdaftar di
 * DatabaseSeeder.php (dead code, tidak pernah dieksekusi otomatis) --
 * tetap disinkronkan isinya kalau suatu saat dijalankan manual.
 */
class ProgramLoyaltySeeder extends Seeder
{
    public function run(): void
    {
        $nama = 'Hadiah Loyalty Pelanggan Setia';

        if (LoyaltyProgram::withTrashed()->where('nama', $nama)->exists()) {
            $this->command?->info("  [Loyalty] Program \"{$nama}\" sudah ada — tidak ada aksi (idempotent).");
            return;
        }

        LoyaltyProgram::create([
            'nama'          => $nama,
            'deskripsi'     => 'Pelanggan yang mencapai kumulatif total belanja Rp 500.000 (all-time) mendapatkan hadiah.',
            'target_qty_kg' => 500000,
            'satuan_qty'    => 'Rp',
            'sumber_data'   => 'orders.total_bayar',
            'tipe_item'     => 'penjualan',
            'periode_mulai' => null,
            'periode_akhir' => null,
            'hadiah'        => 'Voucher Rp 25.000',
            'berulang'      => false,
            'status'        => 'aktif',
        ]);

        $this->command?->info("  [Loyalty] Program \"{$nama}\" berhasil dibuat.");
    }
}
