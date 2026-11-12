<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 3 — produk kasir parfum untuk satu toko, dibuat dari parfum gudang:
 *  - Botol  : produk jual "<nama botol>" (kode JL-<kode>) ber-resep 1 pcs botol (bahan padanan di toko).
 *  - Isi ulang: produk jual "Isi Ulang <aroma> <konsentrasi> (per ml)" (kode RF-<kode>, satuan ml) ber-resep 1 ml cairan.
 * Bahan padanan dibuat lewat PadananBarangService (kode sama dengan gudang) sehingga stok masuk lewat
 * Permintaan / Transfer Stok dari gudang. Kasir memotong stok bahan itu saat bayar (resep).
 */
class ProdukTokoService
{
    public const KODE_BOTOL = 'PFM';
    public const KODE_CAIRAN = 'CRN';

    public function __construct(private PadananBarangService $padanan, private StokService $stok)
    {
    }

    /** Toko penjualan yang boleh diakses (tipe cabang, aktif). */
    public function daftarToko(): Collection
    {
        return Cabang::where('tipe', 'cabang')->where('is_active', true)->orderBy('nama_cabang')->get()
            ->filter(fn (Cabang $c) => \App\Support\CabangAktif::bolehAkses($c->id))->values();
    }

    /** Parfum gudang (botol & cairan) + keadaan produk kasir di toko. @return array{botol: array, cairan: array} */
    public function rencana(Cabang $toko): array
    {
        $gudangIds = ProduksiService::gudangUntuk()->pluck('id');
        $kategori = ItemCategory::whereIn('kode_kategori', [self::KODE_BOTOL, self::KODE_CAIRAN])->pluck('kode_kategori', 'id');
        $sumber = Item::whereIn('cabang_id', $gudangIds)->whereIn('item_category_id', $kategori->keys())->aktif()
            ->orderBy('nama_item')->get();
        $produk = Item::where('cabang_id', $toko->id)->where('tipe', 'produk_jual')
            ->where(fn ($q) => $q->where('kode_item', 'like', 'JL-%')->orWhere('kode_item', 'like', 'RF-%'))->get()->keyBy('kode_item');
        $bahanToko = Item::where('cabang_id', $toko->id)->whereIn('kode_item', $sumber->pluck('kode_item'))->get()->keyBy('kode_item');

        $hasil = ['botol' => [], 'cairan' => []];
        foreach ($sumber as $s) {
            $jenis = $kategori[$s->item_category_id] === self::KODE_BOTOL ? 'botol' : 'cairan';
            $kode = ($jenis === 'botol' ? 'JL-' : 'RF-') . $s->kode_item;
            $p = $produk->get($kode);
            $b = $bahanToko->get($s->kode_item);
            $hasil[$jenis][] = [
                'sumber' => $s, 'kode' => $kode, 'produk' => $p, 'aktif' => (bool) ($p?->is_active),
                'harga' => $p?->harga_jual !== null ? (float) $p->harga_jual : null,
                'barcode' => $jenis === 'botol' ? $s->barcode : null,
                'stok_toko' => $b ? $this->stok->getStok($b->id, $toko->id) : 0.0,
                'nama' => $jenis === 'botol' ? $s->nama_item : $this->namaIsiUlang($s->nama_item),
            ];
        }

        return $hasil;
    }

    /**
     * Barcode isian (hanya baris botol yang dikirim). Kosong = hapus. Huruf/angka/titik/strip 3–64 karakter, tidak boleh
     * sama dengan parfum lain. @return array<int, ?string> [item_id gudang => barcode]
     */
    private function periksaBarcode(array $botol, array $pilih): array
    {
        $hasil = [];
        foreach ($botol as $r) {
            if (! array_key_exists('barcode', $pilih[$r['sumber']->id] ?? [])) {
                continue;
            }
            $kode = trim((string) $pilih[$r['sumber']->id]['barcode']);
            if ($kode !== '' && ! preg_match('/^[A-Za-z0-9.\-]{3,64}$/', $kode)) {
                throw new \InvalidArgumentException("Barcode {$r['nama']} hanya boleh huruf, angka, titik, atau strip (3–64 karakter).");
            }
            $hasil[$r['sumber']->id] = $kode === '' ? null : $kode;
        }
        $isi = array_filter($hasil);
        if (count($isi) !== count(array_unique($isi))) {
            throw new \InvalidArgumentException('Ada barcode yang sama untuk dua parfum berbeda.');
        }
        foreach ($isi as $id => $kode) {
            $lain = Item::where('barcode', $kode)->where('tipe', '!=', 'produk_jual')->where('id', '!=', $id)
                ->where('kode_item', '!=', collect($botol)->firstWhere('sumber.id', $id)['sumber']->kode_item)->first();
            if ($lain) {
                throw new \InvalidArgumentException("Barcode {$kode} sudah dipakai {$lain->nama_item}.");
            }
        }

        return $hasil;
    }

    public function namaIsiUlang(string $namaCairan): string
    {
        return 'Isi Ulang ' . trim(preg_replace('/^Cairan\s+/i', '', $namaCairan)) . ' (per ml)';
    }

    /**
     * @param array<int, array{aktif?:bool|string, harga?:mixed, barcode?:string}> $pilih [item_id gudang => {aktif, harga, barcode (botol)}]
     * @return array{dibuat:int, diubah:int, dimatikan:int}
     */
    public function simpan(Cabang $toko, array $pilih, ?int $userId): array
    {
        $rencana = $this->rencana($toko);
        $hasil = ['dibuat' => 0, 'diubah' => 0, 'dimatikan' => 0];
        $barcode = $this->periksaBarcode($rencana['botol'], $pilih);
        DB::transaction(function () use ($toko, $pilih, $userId, $rencana, $barcode, &$hasil) {
            // Tahap 6a: barcode melekat pada parfum botol gudang → disalin ke produk kasir JL- di SEMUA toko
            foreach ($barcode as $sumberId => $kode) {
                $sumber = collect($rencana['botol'])->firstWhere('sumber.id', $sumberId)['sumber'];
                if ($sumber->barcode !== $kode) {
                    $sumber->update(['barcode' => $kode]);
                    Item::where('tipe', 'produk_jual')->where('kode_item', 'JL-' . $sumber->kode_item)->update(['barcode' => $kode]);
                }
            }
            foreach (['botol', 'cairan'] as $jenis) {
                foreach ($rencana[$jenis] as $r) {
                    $p = $pilih[$r['sumber']->id] ?? [];
                    $aktif = filter_var($p['aktif'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $harga = is_numeric($p['harga'] ?? null) ? round((float) $p['harga'], 2) : null;
                    if (! $aktif) {
                        if ($r['produk'] && $r['produk']->is_active) {
                            $r['produk']->update(['is_active' => false]);
                            $hasil['dimatikan']++;
                        }
                        continue;
                    }
                    if ($harga === null || $harga <= 0) {
                        throw new \InvalidArgumentException("Isi harga jual untuk {$r['nama']}.");
                    }
                    $bahan = $this->padanan->untukCabang($r['sumber'], $toko->id, true);
                    $produk = $r['produk'] ?? new Item(['cabang_id' => $toko->id, 'kode_item' => $r['kode']]);
                    $baru = ! $produk->exists;
                    $produk->fill([
                        'nama_item' => $r['nama'], 'item_category_id' => $r['sumber']->item_category_id, 'tipe' => 'produk_jual',
                        'jenis' => 'bahan_baku', 'satuan' => $jenis === 'botol' ? 'pcs' : 'ml', 'harga_jual' => $harga,
                        'track_stok' => false, 'is_active' => true, 'barcode' => $jenis === 'botol' ? ($barcode[$r['sumber']->id] ?? $r['sumber']->barcode) : null, 'deskripsi' => 'Produk kasir parfum (dibuat dari ' . $r['sumber']->nama_item . ')',
                    ])->save();
                    $resep = ResepBumbu::firstOrCreate(['item_id' => $produk->id], [
                        'cabang_id' => $toko->id, 'nama' => $produk->nama_item,
                        'kode' => strlen('RESEP-' . $produk->kode_item) <= 20 ? 'RESEP-' . $produk->kode_item : 'RESEP-' . $produk->id,
                        'is_active' => true, 'dibuat_oleh' => $userId,
                    ]);
                    $resep->items()->delete();
                    ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $bahan->id, 'qty_per_unit' => 1,
                        'satuan' => $jenis === 'botol' ? 'pcs' : 'ml', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);
                    $baru ? $hasil['dibuat']++ : $hasil['diubah']++;
                }
            }
        });

        return $hasil;
    }
}
