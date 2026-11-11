<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Salin semua data barang dari 1 cabang ke cabang lain (CLAUDE.md 4.39) —
 * dipakai saat menambah cabang baru ("Salin Harga & Pengaturan Barang dari").
 * Yang disalin: bahan/kemasan, produk jual (+ atribut & varian), resep
 * produk, Bumbu Pusat, dan Qty Minimum (baris stok qty 0). Kode sama dgn
 * cabang sumber (padanan antar cabang). Stok & transaksi TIDAK disalin.
 */
class SalinDataCabangService
{
    /** @return array{barang: int, resep: int} */
    public function salin(int $sumberId, int $tujuanId): array
    {
        return DB::transaction(function () use ($sumberId, $tujuanId) {
            $now = now();
            $petaItem = [];
            $petaNilai = [];

            // 1) Barang (kode yang sudah ada di cabang tujuan dilewati)
            $sudahAda = DB::table('items')->where('cabang_id', $tujuanId)->pluck('id', 'kode_item');
            foreach (DB::table('items')->where('cabang_id', $sumberId)->whereNull('deleted_at')->get() as $item) {
                if (isset($sudahAda[$item->kode_item])) {
                    $petaItem[$item->id] = (int) $sudahAda[$item->kode_item];
                    continue;
                }
                $row = (array) $item;
                unset($row['id']);
                $row = array_merge($row, ['cabang_id' => $tujuanId, 'harga_po_terakhir' => null, 'harga_po_terakhir_at' => null, 'created_at' => $now, 'updated_at' => $now]);
                $petaItem[$item->id] = DB::table('items')->insertGetId($row);

                foreach (DB::table('item_attributes')->where('item_id', $item->id)->whereNull('deleted_at')->get() as $attr) {
                    $a = (array) $attr; unset($a['id']);
                    $attrBaru = DB::table('item_attributes')->insertGetId(array_merge($a, ['item_id' => $petaItem[$item->id], 'created_at' => $now, 'updated_at' => $now]));
                    foreach (DB::table('item_attribute_values')->where('item_attribute_id', $attr->id)->whereNull('deleted_at')->get() as $val) {
                        $v = (array) $val; unset($v['id']);
                        $petaNilai[$val->id] = DB::table('item_attribute_values')->insertGetId(array_merge($v, ['item_attribute_id' => $attrBaru, 'created_at' => $now, 'updated_at' => $now]));
                    }
                }
                foreach (DB::table('item_variants')->where('item_id', $item->id)->whereNull('deleted_at')->get() as $var) {
                    $v = (array) $var; unset($v['id']);
                    $varBaru = DB::table('item_variants')->insertGetId(array_merge($v, ['item_id' => $petaItem[$item->id], 'created_at' => $now, 'updated_at' => $now]));
                    foreach (DB::table('item_variant_values')->where('item_variant_id', $var->id)->get() as $vv) {
                        if (isset($petaNilai[$vv->item_attribute_value_id])) {
                            DB::table('item_variant_values')->insert(['item_variant_id' => $varBaru, 'item_attribute_value_id' => $petaNilai[$vv->item_attribute_value_id], 'created_at' => $now, 'updated_at' => $now]);
                        }
                    }
                }
            }

            // 2) Resep: Bumbu Pusat dulu (direferensikan resep produk), lalu resep produk
            $petaResep = [];
            $resepSumber = DB::table('resep_bumbu')->where('cabang_id', $sumberId)->orderByRaw('item_id IS NOT NULL')->get();
            $kodeResepAda = DB::table('resep_bumbu')->where('cabang_id', $tujuanId)->pluck('id', 'kode');
            foreach ($resepSumber as $resep) {
                if ($resep->item_id !== null && ! isset($petaItem[$resep->item_id])) continue;
                if (isset($kodeResepAda[$resep->kode])) {
                    $petaResep[$resep->id] = (int) $kodeResepAda[$resep->kode];
                    continue;
                }
                $r = (array) $resep; unset($r['id']);
                $petaResep[$resep->id] = DB::table('resep_bumbu')->insertGetId(array_merge($r, [
                    'cabang_id' => $tujuanId, 'item_id' => $resep->item_id !== null ? $petaItem[$resep->item_id] : null,
                    'created_at' => $now, 'updated_at' => $now,
                ]));
                foreach (DB::table('resep_bumbu_items')->where('resep_bumbu_id', $resep->id)->get() as $ri) {
                    $row = (array) $ri; unset($row['id']);
                    DB::table('resep_bumbu_items')->insert(array_merge($row, [
                        'resep_bumbu_id' => $petaResep[$resep->id],
                        'item_id' => $ri->item_id !== null ? ($petaItem[$ri->item_id] ?? $ri->item_id) : null,
                        'resep_bumbu_ref_id' => $ri->resep_bumbu_ref_id !== null ? ($petaResep[$ri->resep_bumbu_ref_id] ?? $ri->resep_bumbu_ref_id) : null,
                        'created_at' => $now, 'updated_at' => $now,
                    ]));
                }
            }

            // 3) Qty Minimum → baris stok qty 0 di cabang tujuan
            foreach (DB::table('stocks')->where('lokasi_id', $sumberId)->where('qty_minimum', '>', 0)->whereNull('deleted_at')->get() as $st) {
                $itemBaru = $petaItem[$st->item_id] ?? null;
                if (! $itemBaru || DB::table('stocks')->where('item_id', $itemBaru)->where('lokasi_id', $tujuanId)->exists()) continue;
                DB::table('stocks')->insert(['item_id' => $itemBaru, 'lokasi_id' => $tujuanId, 'qty' => 0, 'qty_minimum' => $st->qty_minimum, 'updated_at' => $now]);
            }

            return ['barang' => count($petaItem), 'resep' => count($petaResep)];
        });
    }
}
