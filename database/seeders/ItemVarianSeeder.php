<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemVariant;
use Illuminate\Database\Seeder;

/**
 * Tahap 2 - Master Data (2026-09-13) — Dummy varian untuk testing struktur
 * DB Fitur Varian. UI-nya sendiri dikerjakan Tahap 3, jadi ini murni
 * memastikan data contoh ada untuk verifikasi model+relasi.
 *
 * "Dimsum Mentai" (PJ-DIM-003) diberi 1 atribut "Size" dengan 3 nilai
 * (S/M/L), masing-masing jadi 1 ItemVariant dengan harga_override beda.
 */
class ItemVarianSeeder extends Seeder
{
    public function run(): void
    {
        $item = Item::where('kode_item', 'PJ-DIM-003')->first();

        if (! $item) {
            $this->command?->warn('  - PJ-DIM-003 (Dimsum Mentai) tidak ditemukan, skip ItemVarianSeeder.');
            return;
        }

        $attribute = ItemAttribute::updateOrCreate(
            ['item_id' => $item->id, 'nama' => 'Size'],
            ['urutan' => 1]
        );

        $sizes = [
            ['nilai' => 'S', 'urutan' => 1, 'harga' => 12000],
            ['nilai' => 'M', 'urutan' => 2, 'harga' => 15000],
            ['nilai' => 'L', 'urutan' => 3, 'harga' => 18000],
        ];

        foreach ($sizes as $size) {
            $value = ItemAttributeValue::updateOrCreate(
                ['item_attribute_id' => $attribute->id, 'nilai' => $size['nilai']],
                ['urutan' => $size['urutan']]
            );

            $variant = ItemVariant::updateOrCreate(
                ['item_id' => $item->id, 'sku' => "PJ-DIM-003-{$size['nilai']}"],
                [
                    'harga_override' => $size['harga'],
                    'stok'           => 0,
                    'is_active'      => true,
                    'urutan'         => $size['urutan'],
                ]
            );

            $variant->attributeValues()->syncWithoutDetaching([$value->id]);
        }

        $this->command?->info('  - 1 atribut (Size) + 3 varian (S/M/L) untuk Dimsum Mentai dibuat.');
    }
}
