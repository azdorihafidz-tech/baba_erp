<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\ItemVariant;
use App\Models\Order;
use App\Support\Branding;
use Illuminate\Support\Collection;

/**
 * 4.44 Poin 2 — data Label Produk (stiker), porting erp-coffeeshop
 * "Label Cup" (nama class/kolom/permission sengaja sama supaya perbaikan
 * berikutnya mudah di-porting). 1 stiker = 1 unit qty (1 box/pack/porsi).
 *
 * Hanya item yang kategorinya dicentang di Cabang → Edit → Label Produk yang
 * dicetak; cabang dengan label MATI (bawaan) tidak menghasilkan label sama
 * sekali. Dimsum tidak punya Bill meja terpisah (Bill Tersimpan = order
 * pending), jadi label selalu dibuat dari Order.
 */
class CupLabelService
{
    public const MAKS_LABEL = 100;

    public function untukOrder(Order $order): array
    {
        $order->loadMissing(['cabang', 'items.item.category', 'pelanggan']);

        $nomor = $order->nomor_antrian
            ? str_pad((string) $order->nomor_antrian, 3, '0', STR_PAD_LEFT)
            : $this->nomorPendek($order->nomor_order);

        $tipeEnum = $order->tipe_transaksi;
        $tipe = $tipeEnum?->label() ?? 'Takeaway';
        if ($tipeEnum?->value === 'dine_in' && $order->nomor_meja) {
            $tipe .= ' · Meja ' . $order->nomor_meja;
        }

        // Frozen: tanggal kedaluwarsa ikut tercetak di stiker kemasan
        $exp = ($tipeEnum?->value === 'frozen' && $order->tanggal_expired_frozen)
            ? $order->tanggal_expired_frozen->format('d/m/Y')
            : null;

        $rows = $order->items->map(fn ($oi) => [
            'item'       => $oi->item,
            'nama'       => $oi->item?->nama_item ?? $oi->nama_item,
            'variant_id' => $oi->item_variant_id,
            'qty'        => (float) $oi->qty,
            'catatan'    => $oi->catatan,
        ]);

        return $this->susun($order->cabang, $rows, [
            'nomor'     => $nomor,
            'pelanggan' => $order->nama_pelanggan ?: $order->pelanggan?->nama_pelanggan,
            'tipe'      => $tipe,
            'jam'       => ($order->created_at ?? now())->format('H:i'),
            'tanggal'   => ($order->created_at ?? now())->format('d/m/Y'), // 4.49 Tahap D
            'exp'       => $exp,
            'catatan_order' => $order->catatan,
        ]);
    }

    /** Jumlah stiker yang akan dicetak (0 = tidak perlu tombol label). */
    public function jumlah(array $hasil): int
    {
        return count($hasil['labels']);
    }

    private function susun(?Cabang $cabang, Collection $rows, array $info): array
    {
        $aktif = (bool) ($cabang?->label_cup_aktif ?? false);
        $kategori = ($cabang && $aktif) ? $cabang->kategoriLabelCup() : [];

        $layak = $rows->filter(function ($r) use ($kategori) {
            $item = $r['item'];

            return $item && in_array($item->category?->kode_kategori, $kategori, true);
        })->values();

        $total = (int) $layak->sum(fn ($r) => $this->jumlahStiker($r['qty']));
        $total = min($total, self::MAKS_LABEL);

        $labels = [];
        $ke = 0;
        $toko = app(Branding::class)->namaSingkat();
        foreach ($layak as $r) {
            $opsi = $this->opsiVarian($r['variant_id']);
            for ($i = 0; $i < $this->jumlahStiker($r['qty']) && $ke < $total; $i++) {
                $ke++;
                $labels[] = [
                    'toko'      => $toko,
                    'nomor'     => $info['nomor'],
                    'ke'        => $ke,
                    'dari'      => $total,
                    'nama'      => mb_strtoupper($r['nama']),
                    'opsi'      => $opsi,
                    'catatan'   => $r['catatan'] ? trim($r['catatan']) : null,
                    'pelanggan' => $info['pelanggan'] ? trim($info['pelanggan']) : null,
                    'exp'       => $info['exp'],
                    // 4.49 Tahap D — catatan pesanan (kolom Catatan di POS) ikut di setiap stiker
                    'catatan_order' => trim((string) ($info['catatan_order'] ?? '')) ?: null,
                    // tanggal di sebelah jam
                    'info'      => $info['tipe'] . ' · ' . $info['jam'] . ' ' . ($info['tanggal'] ?? ''),
                ];
            }
        }

        return [
            'aktif'  => $aktif,
            'ukuran' => [
                'lebar'  => (int) ($cabang?->label_cup_lebar_mm ?: 40),
                'tinggi' => (int) ($cabang?->label_cup_tinggi_mm ?: 30),
            ],
            // 4.49 Tahap D — geser posisi per cabang (mm); perangkat bisa menambah sendiri
            'geser' => [
                'x' => (float) ($cabang?->label_cup_geser_x ?? 0),
                'y' => (float) ($cabang?->label_cup_geser_y ?? 0),
            ],
            'labels' => $aktif ? $labels : [],
        ];
    }

    private function jumlahStiker(float $qty): int
    {
        return max(1, (int) round($qty));
    }

    /** Nilai varian sebagai baris opsi, mis. ["Isi: 5 pcs", "Saus: Mentai"]. */
    private function opsiVarian($variantId): array
    {
        if (! $variantId) {
            return [];
        }
        $variant = ItemVariant::with('attributeValues.attribute')->find((int) $variantId);
        if (! $variant) {
            return [];
        }

        return $variant->attributeValues
            ->map(fn ($v) => ($v->attribute?->nama ? $v->attribute->nama . ': ' : '') . $v->nilai)
            ->values()->all();
    }

    private function nomorPendek(?string $nomor): string
    {
        if (! $nomor) {
            return '-';
        }

        // Ambil segmen angka terakhir (mis. ORD-20261103-0025 → 0025)
        return preg_match('/(\d+)\s*$/', $nomor, $m) ? $m[1] : $nomor;
    }
}
