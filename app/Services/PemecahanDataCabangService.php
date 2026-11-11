<?php

namespace App\Services;

use App\Models\Cabang;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pemecahan data barang per cabang (CLAUDE.md 4.39, keputusan Owner 2026-10-19).
 *
 * Data lama (items / resep_bumbu dgn cabang_id NULL) disalin ke tiap cabang
 * yang MEMAKAINYA. Baris asli menjadi milik cabang utama (Gudang Pusat kalau
 * ikut memakai, else cabang id terkecil); cabang lain mendapat salinan dgn
 * kode sama. Stok, batch FIFO, qty minimum, harga outlet, resep & riwayat
 * transaksi tiap cabang diarahkan ke salinan milik cabang itu.
 *
 * "Memakai" = punya baris stok/batch/pergerakan, order, PO, transfer, permintaan
 * stok, pemakaian perlengkapan, item_cabang aktif (produk) / harga beli outlet
 * (bahan), produk POS tanpa item_cabang (= aktif semua cabang), atau dipakai
 * resep/Bumbu Pusat milik cabang itu. Tidak dipakai siapa pun → Gudang Pusat.
 *
 * Semua dicatat di pemecahan_cabang_maps supaya bisa dibatalkan (batalkan()).
 */
class PemecahanDataCabangService
{
    private const TIPE_POS = ['produk_jual', 'produk_tambahan', 'tambahan_gratis'];

    /** Tabel transaksi yang diarahkan ke salinan, dgn cara menentukan cabang barisnya. */
    private function tabelRiwayat(): array
    {
        return [
            // [tabel, kolom item, SQL ekspresi cabang baris (alias t)]
            ['stocks', 'item_id', 't.lokasi_id'],
            ['stock_batches', 'item_id', 't.lokasi_id'],
            ['stock_movements', 'item_id', 'COALESCE(t.lokasi_asal_id, t.lokasi_tujuan_id)'],
            ['order_items', 'item_id', '(SELECT o.cabang_id FROM orders o WHERE o.id = t.order_id)'],
            ['purchase_order_items', 'item_id', '(SELECT p.cabang_id FROM purchase_orders p WHERE p.id = t.purchase_order_id)'],
            ['stock_transfer_items', 'item_id', '(SELECT s.dari_lokasi_id FROM stock_transfers s WHERE s.id = t.stock_transfer_id)'],
            ['stock_request_items', 'item_id', '(SELECT r.cabang_id FROM stock_requests r WHERE r.id = t.stock_request_id)'],
            ['pemakaian_perlengkapans', 'item_id', 't.cabang_id'],
            ['item_cabang', 'item_id', 't.cabang_id'],
        ];
    }

    /** Semua kolom FK ke items (dipakai saat membatalkan). */
    private const FK_ITEMS = [
        ['stocks', 'item_id'], ['stock_batches', 'item_id'], ['stock_movements', 'item_id'], ['order_items', 'item_id'],
        ['purchase_order_items', 'item_id'], ['stock_transfer_items', 'item_id'], ['stock_request_items', 'item_id'],
        ['pemakaian_perlengkapans', 'item_id'], ['item_cabang', 'item_id'], ['resep_bumbu_items', 'item_id'],
        ['resep_bumbu', 'item_id'], ['item_histories', 'item_id'],
    ];

    public function perluDipecah(): bool
    {
        return DB::table('items')->whereNull('cabang_id')->exists()
            || DB::table('resep_bumbu')->whereNull('cabang_id')->exists();
    }

    public function cabangUtamaDefault(): int
    {
        return (int) (Cabang::where('tipe', 'gudang_pusat')->orderBy('id')->value('id')
            ?? Cabang::orderBy('id')->value('id'));
    }

    /**
     * Hitung rencana pemecahan TANPA mengubah data.
     * @return array{items: array<int, array>, bumbu: array<int, array>, cabang: array<int,string>}
     */
    public function rencana(): array
    {
        $gudang = $this->cabangUtamaDefault();
        $cabangAktif = DB::table('cabangs')->where('is_active', true)->whereNull('deleted_at')->pluck('id')->map(fn ($v) => (int) $v)->all();
        $namaCabang = DB::table('cabangs')->pluck('nama_cabang', 'id')->all();

        $items = DB::table('items')->whereNull('cabang_id')->get(['id', 'kode_item', 'nama_item', 'tipe', 'is_active', 'deleted_at'])->keyBy('id');
        $pakai = $items->map(fn () => [])->all(); // item_id => [cabang_id => true]

        $tambah = function (int $itemId, $cabangId) use (&$pakai) {
            if ($cabangId && isset($pakai[$itemId])) {
                $pakai[$itemId][(int) $cabangId] = true;
            }
        };

        // 1) Pemakaian langsung dari tabel transaksi
        foreach ($this->tabelRiwayat() as [$tabel, $kolom, $exprCabang]) {
            if ($tabel === 'item_cabang') continue;
            $rows = DB::select("SELECT DISTINCT t.$kolom AS item_id, $exprCabang AS cabang_id FROM $tabel t");
            foreach ($rows as $r) {
                $tambah((int) $r->item_id, $r->cabang_id);
            }
            if ($tabel === 'stock_movements') {
                foreach (DB::select('SELECT DISTINCT item_id, lokasi_tujuan_id AS cabang_id FROM stock_movements WHERE lokasi_tujuan_id IS NOT NULL') as $r) {
                    $tambah((int) $r->item_id, $r->cabang_id);
                }
            }
        }

        // 2) item_cabang: produk POS yg aktif di outlet / bahan dgn harga beli outlet
        $ic = DB::table('item_cabang')->get(['item_id', 'cabang_id', 'is_active', 'harga_beli']);
        $punyaBarisIc = $ic->pluck('item_id')->unique()->flip();
        foreach ($ic as $r) {
            $item = $items[$r->item_id] ?? null;
            if (! $item) continue;
            if (in_array($item->tipe, self::TIPE_POS, true) ? (bool) $r->is_active : $r->harga_beli !== null) {
                $tambah((int) $r->item_id, $r->cabang_id);
            }
        }
        // Produk POS aktif tanpa baris item_cabang = tersedia di semua cabang (Item::tersediaDiCabang)
        foreach ($items as $item) {
            if ($item->deleted_at === null && $item->is_active && in_array($item->tipe, self::TIPE_POS, true) && ! isset($punyaBarisIc[$item->id])) {
                foreach ($cabangAktif as $c) $tambah((int) $item->id, $c);
            }
        }

        // 3) Resep: bahan ikut cabang produk pemilik resep; Bumbu Pusat ikut cabang produk yg memakainya
        $resep = DB::table('resep_bumbu')->whereNull('cabang_id')->get(['id', 'kode', 'nama', 'item_id'])->keyBy('id');
        $resepItems = DB::table('resep_bumbu_items')->whereIn('resep_bumbu_id', $resep->keys())->get(['resep_bumbu_id', 'item_id', 'resep_bumbu_ref_id']);
        $bumbuPakai = $resep->whereNull('item_id')->map(fn () => [])->all();

        for ($putaran = 0; $putaran < 5; $putaran++) {
            $berubah = false;
            foreach ($resepItems as $ri) {
                $pemilik = $resep[$ri->resep_bumbu_id];
                $cabangPemilik = $pemilik->item_id !== null
                    ? array_keys($pakai[$pemilik->item_id] ?? [])
                    : array_keys($bumbuPakai[$pemilik->id] ?? []);
                foreach ($cabangPemilik as $c) {
                    if ($ri->item_id && isset($pakai[$ri->item_id]) && ! isset($pakai[$ri->item_id][$c])) {
                        $pakai[$ri->item_id][$c] = true; $berubah = true;
                    }
                    if ($ri->resep_bumbu_ref_id && isset($bumbuPakai[$ri->resep_bumbu_ref_id]) && ! isset($bumbuPakai[$ri->resep_bumbu_ref_id][$c])) {
                        $bumbuPakai[$ri->resep_bumbu_ref_id][$c] = true; $berubah = true;
                    }
                }
            }
            if (! $berubah) break;
        }

        $susun = function (array $set) use ($gudang) {
            $ids = array_map('intval', array_keys($set));
            if (! $ids) return [$gudang];
            sort($ids);
            if (in_array($gudang, $ids, true)) {
                $ids = array_values(array_diff($ids, [$gudang]));
                array_unshift($ids, $gudang);
            }
            return $ids;
        };

        $hasilItem = [];
        foreach ($items as $item) {
            $hasilItem[$item->id] = ['kode' => $item->kode_item, 'nama' => $item->nama_item, 'tipe' => $item->tipe,
                'cabang' => $item->deleted_at !== null ? [$susun($pakai[$item->id])[0]] : $susun($pakai[$item->id])];
        }
        $hasilBumbu = [];
        foreach ($resep->whereNull('item_id') as $b) {
            $hasilBumbu[$b->id] = ['kode' => $b->kode, 'nama' => $b->nama, 'cabang' => $susun($bumbuPakai[$b->id])];
        }

        return ['items' => $hasilItem, 'bumbu' => $hasilBumbu, 'cabang' => $namaCabang];
    }

    /** Laporan rencana dalam teks (dipakai mode simulasi script deploy / command). */
    public function laporanRencana(): string
    {
        $r = $this->rencana();
        $nama = fn ($id) => $r['cabang'][$id] ?? "#$id";
        $baris = [];
        $salinan = 0;
        foreach ($r['items'] as $id => $it) {
            $salinan += count($it['cabang']) - 1;
            $baris[] = sprintf('[%s] %s (%s) → %s%s', $it['kode'], $it['nama'], $it['tipe'],
                implode(', ', array_map($nama, $it['cabang'])), count($it['cabang']) > 1 ? '  (dipecah ' . count($it['cabang']) . ')' : '');
        }
        foreach ($r['bumbu'] as $id => $b) {
            $salinan += count($b['cabang']) - 1;
            $baris[] = sprintf('[Bumbu Pusat %s] %s → %s', $b['kode'], $b['nama'], implode(', ', array_map($nama, $b['cabang'])));
        }

        return 'Barang: ' . count($r['items']) . ', Bumbu Pusat: ' . count($r['bumbu']) . ", salinan baru: $salinan\n" . implode("\n", $baris);
    }

    /** Jalankan pemecahan (idempotent: hanya data yg cabang_id-nya masih NULL). */
    public function jalankan(): array
    {
        $rencana = $this->rencana();

        return DB::transaction(function () use ($rencana) {
            $now = now();
            $petaItem = []; // [item_lama][cabang] = item_id milik cabang itu
            $petaVarian = []; // [varian_lama][cabang] = varian_id
            $petaBumbu = []; // [bumbu_lama][cabang] = bumbu_id
            $jumlahSalinan = 0;

            $ic = DB::table('item_cabang')->get()->groupBy('item_id');

            // ===== 1) Barang =====
            foreach ($rencana['items'] as $itemId => $info) {
                $asli = DB::table('items')->where('id', $itemId)->first();
                $utama = $info['cabang'][0];
                $dataLama = ['harga_jual' => $asli->harga_jual, 'harga_beli_terakhir' => $asli->harga_beli_terakhir, 'is_active' => $asli->is_active];

                foreach ($info['cabang'] as $cabangId) {
                    $sesuai = $this->nilaiPerCabang($asli, $ic->get($itemId, collect())->firstWhere('cabang_id', $cabangId));
                    if ($cabangId === $utama) {
                        DB::table('items')->where('id', $itemId)->update(array_merge($sesuai, ['cabang_id' => $cabangId]));
                        $petaItem[$itemId][$cabangId] = $itemId;
                        DB::table('pemecahan_cabang_maps')->insert(['tipe' => 'item', 'lama_id' => $itemId, 'baru_id' => $itemId, 'cabang_id' => $cabangId, 'data_lama' => json_encode($dataLama), 'created_at' => $now]);
                        continue;
                    }
                    $baris = (array) $asli;
                    unset($baris['id']);
                    $baris = array_merge($baris, $sesuai, ['cabang_id' => $cabangId, 'created_at' => $now, 'updated_at' => $now]);
                    $baruId = DB::table('items')->insertGetId($baris);
                    $petaItem[$itemId][$cabangId] = $baruId;
                    $jumlahSalinan++;
                    DB::table('pemecahan_cabang_maps')->insert(['tipe' => 'item', 'lama_id' => $itemId, 'baru_id' => $baruId, 'cabang_id' => $cabangId, 'created_at' => $now]);
                    $petaVarian += $this->salinVarian($itemId, $baruId, $cabangId, $now);
                }
            }

            // ===== 2) Bumbu Pusat (resep tanpa item) =====
            foreach ($rencana['bumbu'] as $bumbuId => $info) {
                foreach ($info['cabang'] as $i => $cabangId) {
                    if ($i === 0) {
                        DB::table('resep_bumbu')->where('id', $bumbuId)->update(['cabang_id' => $cabangId]);
                        $petaBumbu[$bumbuId][$cabangId] = $bumbuId;
                        DB::table('pemecahan_cabang_maps')->insert(['tipe' => 'resep_bumbu', 'lama_id' => $bumbuId, 'baru_id' => $bumbuId, 'cabang_id' => $cabangId, 'created_at' => $now]);
                        continue;
                    }
                    $petaBumbu[$bumbuId][$cabangId] = $this->salinResepBaris($bumbuId, null, $cabangId, $now);
                    $jumlahSalinan++;
                }
            }

            // ===== 3) Resep produk (resep dgn item_id): ikut salinan produk =====
            foreach (DB::table('resep_bumbu')->whereNull('cabang_id')->whereNotNull('item_id')->get() as $resep) {
                if (! isset($petaItem[$resep->item_id])) {
                    // Produknya sudah punya cabang (bukan data lama) → resep ikut cabang produk
                    DB::table('resep_bumbu')->where('id', $resep->id)->update(['cabang_id' => DB::table('items')->where('id', $resep->item_id)->value('cabang_id')]);
                    continue;
                }
                foreach ($petaItem[$resep->item_id] as $cabangId => $produkId) {
                    if ($produkId === (int) $resep->item_id) {
                        DB::table('resep_bumbu')->where('id', $resep->id)->update(['cabang_id' => $cabangId]);
                        DB::table('pemecahan_cabang_maps')->insert(['tipe' => 'resep_bumbu', 'lama_id' => $resep->id, 'baru_id' => $resep->id, 'cabang_id' => $cabangId, 'created_at' => $now]);
                    } else {
                        $this->salinResepBaris($resep->id, $produkId, $cabangId, $now);
                    }
                }
            }

            // ===== 4) Isi resep → bahan & bumbu milik cabang resep =====
            $resepBaru = DB::table('resep_bumbu')->whereIn('id', DB::table('pemecahan_cabang_maps')->where('tipe', 'resep_bumbu')->pluck('baru_id'))->get(['id', 'cabang_id']);
            foreach ($resepBaru as $resep) {
                foreach (DB::table('resep_bumbu_items')->where('resep_bumbu_id', $resep->id)->get() as $ri) {
                    $ubah = [];
                    if ($ri->item_id && isset($petaItem[$ri->item_id][$resep->cabang_id])) {
                        $ubah['item_id'] = $petaItem[$ri->item_id][$resep->cabang_id];
                    }
                    if ($ri->resep_bumbu_ref_id && isset($petaBumbu[$ri->resep_bumbu_ref_id][$resep->cabang_id])) {
                        $ubah['resep_bumbu_ref_id'] = $petaBumbu[$ri->resep_bumbu_ref_id][$resep->cabang_id];
                    }
                    if ($ubah) DB::table('resep_bumbu_items')->where('id', $ri->id)->update($ubah);
                }
            }

            // ===== 5) Riwayat & stok tiap cabang → salinan cabangnya =====
            $dipindah = 0;
            foreach ($petaItem as $lama => $perCabang) {
                foreach ($perCabang as $cabangId => $baru) {
                    if ($baru === $lama) continue;
                    foreach ($this->tabelRiwayat() as [$tabel, $kolom, $exprCabang]) {
                        $dipindah += DB::update("UPDATE $tabel t SET t.$kolom = ? WHERE t.$kolom = ? AND $exprCabang = ?", [$baru, $lama, $cabangId]);
                    }
                    // Varian di order cabang ini ikut salinan varian
                    foreach ($petaVarian as $varLama => $perCabangVar) {
                        if (isset($perCabangVar[$cabangId])) {
                            DB::update('UPDATE order_items t SET t.item_variant_id = ? WHERE t.item_variant_id = ? AND t.item_id = ?', [$perCabangVar[$cabangId], $varLama, $baru]);
                        }
                    }
                }
            }

            return ['barang' => count($rencana['items']), 'bumbu' => count($rencana['bumbu']), 'salinan' => $jumlahSalinan, 'baris_dipindah' => $dipindah];
        });
    }

    /** Harga & status khusus cabang dari item_cabang lama → kolom barang milik cabang itu. */
    private function nilaiPerCabang(object $asli, ?object $ic): array
    {
        $nilai = [];
        if (! $ic) return $nilai;
        if (in_array($asli->tipe, self::TIPE_POS, true)) {
            if ($ic->harga_override !== null) $nilai['harga_jual'] = $ic->harga_override;
            if (! $ic->is_active) $nilai['is_active'] = false;
        }
        if ($ic->harga_beli !== null) $nilai['harga_beli_terakhir'] = $ic->harga_beli;

        return $nilai;
    }

    /** Salin atribut/nilai/varian barang. @return array [varian_lama => [cabang => varian_baru]] */
    private function salinVarian(int $itemLama, int $itemBaru, int $cabangId, $now): array
    {
        $petaNilai = [];
        foreach (DB::table('item_attributes')->where('item_id', $itemLama)->get() as $attr) {
            $a = (array) $attr; unset($a['id']);
            $attrBaru = DB::table('item_attributes')->insertGetId(array_merge($a, ['item_id' => $itemBaru, 'created_at' => $now, 'updated_at' => $now]));
            foreach (DB::table('item_attribute_values')->where('item_attribute_id', $attr->id)->get() as $val) {
                $v = (array) $val; unset($v['id']);
                $petaNilai[$val->id] = DB::table('item_attribute_values')->insertGetId(array_merge($v, ['item_attribute_id' => $attrBaru, 'created_at' => $now, 'updated_at' => $now]));
            }
        }
        $peta = [];
        foreach (DB::table('item_variants')->where('item_id', $itemLama)->get() as $var) {
            $v = (array) $var; unset($v['id']);
            $varBaru = DB::table('item_variants')->insertGetId(array_merge($v, ['item_id' => $itemBaru, 'created_at' => $now, 'updated_at' => $now]));
            foreach (DB::table('item_variant_values')->where('item_variant_id', $var->id)->get() as $vv) {
                if (! isset($petaNilai[$vv->item_attribute_value_id])) continue;
                DB::table('item_variant_values')->insert(['item_variant_id' => $varBaru, 'item_attribute_value_id' => $petaNilai[$vv->item_attribute_value_id], 'created_at' => $now, 'updated_at' => $now]);
            }
            $peta[$var->id][$cabangId] = $varBaru;
            DB::table('pemecahan_cabang_maps')->insert(['tipe' => 'item_variant', 'lama_id' => $var->id, 'baru_id' => $varBaru, 'cabang_id' => $cabangId, 'created_at' => $now]);
        }

        return $peta;
    }

    /** Salin 1 resep_bumbu (+ isinya apa adanya; diarahkan ulang di langkah 4). */
    private function salinResepBaris(int $resepId, ?int $itemId, int $cabangId, $now): int
    {
        $r = (array) DB::table('resep_bumbu')->where('id', $resepId)->first();
        unset($r['id']);
        $baru = DB::table('resep_bumbu')->insertGetId(array_merge($r, ['item_id' => $itemId, 'cabang_id' => $cabangId, 'created_at' => $now, 'updated_at' => $now]));
        foreach (DB::table('resep_bumbu_items')->where('resep_bumbu_id', $resepId)->get() as $ri) {
            $row = (array) $ri; unset($row['id']);
            DB::table('resep_bumbu_items')->insert(array_merge($row, ['resep_bumbu_id' => $baru, 'created_at' => $now, 'updated_at' => $now]));
        }
        DB::table('pemecahan_cabang_maps')->insert(['tipe' => 'resep_bumbu', 'lama_id' => $resepId, 'baru_id' => $baru, 'cabang_id' => $cabangId, 'created_at' => $now]);

        return $baru;
    }

    /**
     * Batalkan pemecahan: arahkan semua riwayat kembali ke barang asli, hapus
     * salinan, kembalikan harga asli & cabang_id NULL.
     */
    public function batalkan(): array
    {
        return DB::transaction(function () {
            $maps = DB::table('pemecahan_cabang_maps')->get();

            $salinanVarian = $maps->where('tipe', 'item_variant');
            foreach ($salinanVarian as $m) {
                DB::table('order_items')->where('item_variant_id', $m->baru_id)->update(['item_variant_id' => $m->lama_id]);
            }

            $salinanBumbu = $maps->where('tipe', 'resep_bumbu')->filter(fn ($m) => (int) $m->lama_id !== (int) $m->baru_id);
            foreach ($salinanBumbu as $m) {
                DB::table('resep_bumbu_items')->where('resep_bumbu_ref_id', $m->baru_id)->update(['resep_bumbu_ref_id' => $m->lama_id]);
            }

            $salinanItem = $maps->where('tipe', 'item')->filter(fn ($m) => (int) $m->lama_id !== (int) $m->baru_id);
            foreach ($salinanItem as $m) {
                foreach (self::FK_ITEMS as [$tabel, $kolom]) {
                    if (in_array($tabel, ['resep_bumbu', 'item_histories'], true)) continue;
                    DB::table($tabel)->where($kolom, $m->baru_id)->update([$kolom => $m->lama_id]);
                }
            }

            // Hapus resep salinan (isi resep ikut terhapus) lalu barang salinan
            foreach ($salinanBumbu as $m) {
                DB::table('resep_bumbu_items')->where('resep_bumbu_id', $m->baru_id)->delete();
                DB::table('resep_bumbu')->where('id', $m->baru_id)->delete();
            }
            foreach ($salinanItem as $m) {
                $variantIds = DB::table('item_variants')->where('item_id', $m->baru_id)->pluck('id');
                DB::table('item_variant_values')->whereIn('item_variant_id', $variantIds)->delete();
                DB::table('item_variants')->where('item_id', $m->baru_id)->delete();
                $attrIds = DB::table('item_attributes')->where('item_id', $m->baru_id)->pluck('id');
                DB::table('item_attribute_values')->whereIn('item_attribute_id', $attrIds)->delete();
                DB::table('item_attributes')->where('item_id', $m->baru_id)->delete();
                DB::table('item_histories')->where('item_id', $m->baru_id)->delete();
                DB::table('items')->where('id', $m->baru_id)->delete();
            }

            foreach ($maps->where('tipe', 'item')->filter(fn ($m) => (int) $m->lama_id === (int) $m->baru_id) as $m) {
                $data = $m->data_lama ? (array) json_decode($m->data_lama, true) : [];
                DB::table('items')->where('id', $m->lama_id)->update(array_merge($data, ['cabang_id' => null]));
            }
            DB::table('resep_bumbu')->whereIn('id', $maps->where('tipe', 'resep_bumbu')->pluck('lama_id'))->update(['cabang_id' => null]);
            DB::table('pemecahan_cabang_maps')->delete();

            return ['salinan_dihapus' => $salinanItem->count() + $salinanBumbu->count()];
        });
    }
}
