<?php

namespace App\Support;

/**
 * 4.49 Tahap B1 — konversi takaran Resep Produksi ke satuan stok bahan
 * (porting erp-coffeeshop ResepBumbuItem::konversiKeSatuanStok).
 *
 * SENGAJA kelas terpisah: komposisi Produk Jual di POS tetap memakai aturan
 * lamanya sendiri (ResepBumbuItem::getQtyPerUnitDalamKgAttribute) — POS yang
 * sudah go-live tidak berubah. Aturan JS di form produksi harus sama persis.
 *
 * Kalau takaran & satuan stok satu kelompok (massa/volume) dihitung sesuai
 * faktornya (10 g dari stok gram = 10, dari stok kg = 0,01). Satuan stok tak
 * dikenal/beda kelompok → aturan lama (g/ml ÷1000, ons ÷10).
 */
class KonversiSatuan
{
    /** Faktor ke satuan dasar (gram / ml) per kelompok satuan. */
    public const FAKTOR = [
        'massa'  => ['mg' => 0.001, 'g' => 1, 'gr' => 1, 'gram' => 1, 'ons' => 100, 'kg' => 1000, 'kilogram' => 1000],
        'volume' => ['ml' => 1, 'cc' => 1, 'l' => 1000, 'lt' => 1000, 'ltr' => 1000, 'liter' => 1000],
    ];

    public static function keSatuanStok(float $qty, ?string $satuan, ?string $satuanStok = null): float
    {
        $takaran = strtolower(trim((string) $satuan));
        $stok = strtolower(trim((string) $satuanStok));

        foreach (self::FAKTOR as $faktor) {
            if (isset($faktor[$takaran], $faktor[$stok])) {
                return $qty * $faktor[$takaran] / $faktor[$stok];
            }
        }

        return match ($takaran) {
            'g', 'gr', 'gram', 'ml' => $qty / 1000,
            'ons'                   => $qty / 10,
            default                 => $qty, // 'kg', 'liter', 'pcs', atau satuan tak dikenal
        };
    }
}
