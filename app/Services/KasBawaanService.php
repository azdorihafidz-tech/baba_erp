<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\Kas;

/**
 * ERP BABA Tahap 3 — kas bawaan sebuah lokasi: Kas Tunai (laci), Kas Transfer & Kas QRIS (bank).
 * Kasir (PenjualanService::prosesPembayaran) wajib punya kas default per metode bayar; toko baru dulu
 * langsung gagal "belum punya Kas default". Idempotent: hanya membuat yang belum ada (default_untuk).
 */
class KasBawaanService
{
    /** @return array<string, Kas> [tunai|transfer|qris => Kas] */
    public function pastikan(Cabang $c): array
    {
        $hasil = [];
        foreach (['tunai' => ['Kas Tunai', 'tunai'], 'transfer' => ['Kas Transfer', 'bank'], 'qris' => ['Kas QRIS', 'bank']] as $metode => [$nama, $tipe]) {
            $hasil[$metode] = Kas::where('cabang_id', $c->id)->where('default_untuk', $metode)->first()
                ?? Kas::create(['cabang_id' => $c->id, 'nama_kas' => "{$nama} {$c->nama_cabang}", 'tipe_kas' => $tipe, 'default_untuk' => $metode,
                    'saldo_awal' => 0, 'saldo_sekarang' => 0, 'saldo_minimum' => 0, 'is_active' => true]);
        }

        return $hasil;
    }
}
