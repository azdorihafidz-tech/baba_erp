<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ketersediaan produk di POS (CLAUDE.md 4.40) — "Stok tersedia" & tanda
 * "Stok Habis" memakai rumus yang SAMA dgn pengecekan saat Bayar
 * (PenjualanService::cekResepCukup → ResepBumbuItem::expandKeBahanMentah):
 * - produk ber-resep: porsi = bahan paling sedikit (stok ÷ kebutuhan per
 *   porsi), termasuk bahan di dalam Bumbu Pusat;
 * - produk tanpa resep yang dipotong stoknya sendiri: stok produk itu;
 * - lainnya (mis. tambahan tanpa resep): tidak dibatasi stok.
 * Stok semua bahan diambil 1 query per halaman POS.
 */
class KetersediaanProdukService
{
    private const TIPE_POTONG_SENDIRI = ['bahan_baku', 'kemasan', 'produk_jual'];
    public const BATAS_MENIPIS = 5;

    /**
     * @param Collection<int, Item> $produk
     * @return array<int, array{bisa_dijual: bool, jumlah: float|null, satuan: string, terbatas: string|null, info: string, menipis: bool}>
     */
    public function untuk(Collection $produk, int $cabangId): array
    {
        $produk = new \Illuminate\Database\Eloquent\Collection($produk->all());
        $produk->loadMissing(['resep.items.item', 'resep.items.resepBumbuRef.items.item']);

        // Kebutuhan bahan per 1 porsi tiap produk ber-resep
        $kebutuhan = [];
        $semuaId = [];
        foreach ($produk as $p) {
            $semuaId[] = $p->id;
            if (! $p->resep) continue;
            $kebutuhan[$p->id] = [];
            foreach ($p->resep->items as $ri) {
                foreach ($ri->expandKeBahanMentah() as $b) {
                    $kebutuhan[$p->id][$b['item_id']] = ($kebutuhan[$p->id][$b['item_id']] ?? 0) + $b['butuh_kg'];
                    $semuaId[] = $b['item_id'];
                }
            }
        }

        $stok = DB::table('stocks')->where('lokasi_id', $cabangId)->whereNull('deleted_at')
            ->whereIn('item_id', array_unique($semuaId))->pluck('qty', 'item_id');
        $namaBahan = Item::whereIn('id', collect($kebutuhan)->flatMap(fn ($k) => array_keys($k))->unique())->pluck('nama_item', 'id');

        $hasil = [];
        foreach ($produk as $p) {
            if ($p->resep) {
                $porsi = null;
                $terbatas = null;
                foreach ($kebutuhan[$p->id] as $itemId => $butuh) {
                    if ($butuh <= 0) continue;
                    $bisa = floor(((float) ($stok[$itemId] ?? 0)) / $butuh + 1e-9);
                    if ($porsi === null || $bisa < $porsi) {
                        $porsi = $bisa;
                        $terbatas = $namaBahan[$itemId] ?? null;
                    }
                }
                $satuan = trim((string) $p->satuan) ?: 'porsi';
                if ($porsi === null) { // resep tanpa bahan berisi
                    $hasil[$p->id] = $this->baris(true, null, $satuan, null, 'Pakai resep');
                    continue;
                }
                // "terbatas" hanya berguna kalau resep berisi lebih dari 1 bahan
                $terbatas = count($kebutuhan[$p->id]) > 1 ? $terbatas : null;
                $info = 'Stok tersedia: ' . fmt_qty($porsi) . ' ' . $satuan . ($terbatas ? " (terbatas: {$terbatas})" : '');
                $hasil[$p->id] = $this->baris($porsi >= 1, $porsi, $satuan, $terbatas, $info);
                continue;
            }

            if (in_array($p->tipe, self::TIPE_POTONG_SENDIRI, true)) {
                $qty = (float) ($stok[$p->id] ?? 0);
                $hasil[$p->id] = $this->baris($qty > 0, $qty, (string) $p->satuan, null, 'Stok tersedia: ' . fmt_qty($qty) . ' ' . $p->satuan);
                continue;
            }

            $hasil[$p->id] = $this->baris(true, null, (string) $p->satuan, null, '');
        }

        return $hasil;
    }

    private function baris(bool $bisa, ?float $jumlah, string $satuan, ?string $terbatas, string $info): array
    {
        return [
            'bisa_dijual' => $bisa,
            'jumlah'      => $jumlah,
            'satuan'      => $satuan,
            'terbatas'    => $terbatas,
            'info'        => $info,
            'menipis'     => $jumlah !== null && $jumlah >= 1 && $jumlah <= self::BATAS_MENIPIS,
        ];
    }
}
