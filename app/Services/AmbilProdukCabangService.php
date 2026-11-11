<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemVariant;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Ambil Produk Jual dari cabang lain (CLAUDE.md 4.42) — produk disalin jadi
 * milik cabang tujuan, komposisi resep menunjuk bahan milik cabang tujuan
 * sendiri (stok dipotong dari cabang itu). Data tetap terpisah per cabang
 * (4.39); tidak ada sinkron otomatis setelah disalin.
 *
 * Pencocokan bahan & Bumbu Pusat (keputusan Owner):
 *  - cocok      : kode sama & nama sama → langsung dipakai
 *  - nama_sama  : kode tidak ada tapi ada nama sama → peringatan, bawaan pakai yg bernama sama
 *  - kode_sama  : kode sama tapi nama beda → peringatan, user WAJIB memilih
 *  - baru       : kode & nama tidak ada → dibuat baru (bahan stok 0)
 *  - umum       : data lama tanpa cabang (dipakai semua cabang) → dipakai apa adanya
 *
 * Produk: kode sudah ada di cabang tujuan → dilewati; nama sama (kode beda)
 * → peringatan, bawaan tidak disalin.
 */
class AmbilProdukCabangService
{
    public const TIPE_PRODUK = ['produk_jual', 'produk_tambahan'];
    public const TIPE_BAHAN  = ['bahan_baku', 'kemasan', 'tambahan_gratis'];

    /** Produk milik cabang sumber yang bisa diambil. */
    public function produkSumber(int $sumberId): Collection
    {
        return Item::with(['category', 'resep' => fn ($q) => $q->withCount('items')])->where('cabang_id', $sumberId)
            ->whereIn('tipe', self::TIPE_PRODUK)->orderBy('nama_item')->get();
    }

    /**
     * Rencana pengambilan untuk 1 cabang tujuan (tidak mengubah data).
     *
     * @return array{tujuan: Cabang, produk: array, bahan: array, bumbu: array, opsi_bahan: Collection, opsi_bumbu: Collection, perlu_pilih: int}
     */
    public function rencana(int $sumberId, array $produkIds, int $tujuanId): array
    {
        $tujuan = Cabang::findOrFail($tujuanId);
        $produk = Item::with(['resep.items.item', 'resep.items.resepBumbuRef.items.item'])
            ->where('cabang_id', $sumberId)->whereIn('tipe', self::TIPE_PRODUK)
            ->whereIn('id', array_map('intval', $produkIds))->orderBy('nama_item')->get();

        $produkTujuan = Item::withTrashed()->where('cabang_id', $tujuanId)->whereIn('tipe', self::TIPE_PRODUK)->get();
        $opsiBahan = Item::whereIn('tipe', self::TIPE_BAHAN)->milikCabang($tujuanId)->aktif()->orderBy('nama_item')->get();
        $opsiBumbu = ResepBumbu::whereNull('item_id')->milikCabang($tujuanId)->orderBy('nama')->get();
        $kodeTerpakai = Item::withTrashed()->where('cabang_id', $tujuanId)->pluck('kode_item')->map(fn ($k) => $this->kunci($k));

        $hasilProduk = [];
        $bahan = [];
        $bumbu = [];
        foreach ($produk as $p) {
            $kodeSama = $produkTujuan->first(fn ($t) => $this->kunci($t->kode_item) === $this->kunci($p->kode_item));
            if ($kodeSama || $kodeTerpakai->contains($this->kunci($p->kode_item))) {
                $hasilProduk[$p->id] = ['sumber' => $p, 'status' => 'kode_ada', 'target' => $kodeSama, 'salin' => false];
                continue;
            }
            $namaSama = $produkTujuan->first(fn ($t) => ! $t->trashed() && $this->kunci($t->nama_item) === $this->kunci($p->nama_item));
            $hasilProduk[$p->id] = ['sumber' => $p, 'status' => $namaSama ? 'nama_ada' : 'baru', 'target' => $namaSama, 'salin' => ! $namaSama];

            foreach ($p->resep?->items ?? [] as $ri) {
                if ($ri->resep_bumbu_ref_id && $ri->resepBumbuRef) {
                    $b = $ri->resepBumbuRef;
                    $bumbu[$b->id] ??= $this->cocokkan($b, $opsiBumbu, 'kode', 'nama') + ['dipakai' => []];
                    $bumbu[$b->id]['dipakai'][] = $p->nama_item;
                    foreach ($b->items as $bi) {
                        if ($bi->item) {
                            $bahan[$bi->item->id] ??= $this->cocokkan($bi->item, $opsiBahan, 'kode_item', 'nama_item') + ['dipakai' => []];
                            $bahan[$bi->item->id]['dipakai'][] = 'Bumbu ' . $b->nama;
                        }
                    }
                } elseif ($ri->item) {
                    $bahan[$ri->item->id] ??= $this->cocokkan($ri->item, $opsiBahan, 'kode_item', 'nama_item') + ['dipakai' => []];
                    $bahan[$ri->item->id]['dipakai'][] = $p->nama_item;
                }
            }
        }
        $rapikan = fn (array $daftar) => array_map(fn ($row) => ['dipakai' => array_values(array_unique($row['dipakai']))] + $row, $daftar);
        $bahan = $rapikan($bahan);
        $bumbu = $rapikan($bumbu);

        $perluPilih = collect(array_merge(array_values($bahan), array_values($bumbu)))->filter(fn ($row) => $row['default'] === null)->count();

        return [
            'tujuan' => $tujuan, 'produk' => $hasilProduk, 'bahan' => $bahan, 'bumbu' => $bumbu,
            'opsi_bahan' => $opsiBahan, 'opsi_bumbu' => $opsiBumbu, 'perlu_pilih' => $perluPilih,
        ];
    }

    /**
     * Salin produk ke cabang tujuan sesuai keputusan pemeriksaan.
     *
     * @param array<int|string, bool|string|int>|null $produkDipilih  [produkId => 1] (null = bawaan rencana)
     * @param array<int|string, string|int>           $keputusanBahan [bahanSumberId => 'baru' | idBahanTujuan]
     * @param array<int|string, string|int>           $keputusanBumbu [bumbuSumberId => 'baru' | idBumbuTujuan]
     * @return array{produk: array, dilewati: array, bahan_baru: array, bumbu_baru: array}
     */
    public function jalankan(int $sumberId, array $produkIds, int $tujuanId, ?array $produkDipilih, array $keputusanBahan, array $keputusanBumbu): array
    {
        return DB::transaction(function () use ($sumberId, $produkIds, $tujuanId, $produkDipilih, $keputusanBahan, $keputusanBumbu) {
            $r = $this->rencana($sumberId, $produkIds, $tujuanId);
            $hasil = ['produk' => [], 'dilewati' => [], 'bahan_baru' => [], 'bumbu_baru' => []];

            $disalin = [];
            foreach ($r['produk'] as $id => $row) {
                $pilih = $produkDipilih === null ? $row['salin'] : ! empty($produkDipilih[$id]);
                if ($row['status'] === 'kode_ada' || ! $pilih) {
                    $hasil['dilewati'][] = $row['sumber']->nama_item;
                    continue;
                }
                $disalin[] = $row['sumber'];
            }
            if (! $disalin) {
                return $hasil;
            }

            // Bumbu & bahan yang benar-benar dibutuhkan produk yang disalin
            $bumbuDipakai = [];
            $bahanDipakai = [];
            foreach ($disalin as $p) {
                foreach ($p->resep?->items ?? [] as $ri) {
                    if ($ri->resep_bumbu_ref_id && $ri->resepBumbuRef) {
                        $bumbuDipakai[$ri->resepBumbuRef->id] = $ri->resepBumbuRef;
                    } elseif ($ri->item) {
                        $bahanDipakai[$ri->item->id] = $ri->item;
                    }
                }
            }
            $petaBumbu = [];
            foreach ($bumbuDipakai as $id => $b) {
                $petaBumbu[$id] = $this->putuskan($r['bumbu'][$id], $keputusanBumbu[$id] ?? null, $r['opsi_bumbu'], 'Bumbu Pusat ' . $b->nama);
                if ($petaBumbu[$id] === 'baru') {
                    foreach ($b->items as $bi) {
                        if ($bi->item) $bahanDipakai[$bi->item->id] = $bi->item;
                    }
                }
            }

            $petaBahan = [];
            foreach ($bahanDipakai as $id => $bahan) {
                $pilihan = $this->putuskan($r['bahan'][$id], $keputusanBahan[$id] ?? null, $r['opsi_bahan'], 'bahan ' . $bahan->nama_item);
                if ($pilihan === 'baru') {
                    $baru = $this->salinBahan($bahan, $tujuanId);
                    $hasil['bahan_baru'][] = $baru->nama_item . ' (' . $baru->kode_item . ')';
                    $pilihan = $baru->id;
                }
                $petaBahan[$id] = (int) $pilihan;
            }

            foreach ($petaBumbu as $id => $pilihan) {
                if ($pilihan === 'baru') {
                    $baru = $this->salinBumbu($bumbuDipakai[$id], $tujuanId, $petaBahan);
                    $hasil['bumbu_baru'][] = $baru->nama;
                    $pilihan = $baru->id;
                }
                $petaBumbu[$id] = (int) $pilihan;
            }

            foreach ($disalin as $p) {
                $baru = $this->salinProduk($p, $tujuanId, $petaBahan, $petaBumbu);
                $hasil['produk'][] = $baru->nama_item;
            }

            return $hasil;
        });
    }

    // ===== Helper =====

    /** Kunci pembanding: tanpa beda huruf besar/kecil & spasi berlebih. */
    public function kunci(?string $teks): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $teks)));
    }

    /** @return array{sumber: mixed, status: string, target: mixed, default: string|int|null} */
    private function cocokkan($sumber, Collection $kandidat, string $kolomKode, string $kolomNama): array
    {
        if ($sumber->cabang_id === null) {
            return ['sumber' => $sumber, 'status' => 'umum', 'target' => $sumber, 'default' => $sumber->id];
        }
        // Utamakan data milik cabang tujuan sendiri, baru data lama tanpa cabang
        $kandidat = $kandidat->sortBy(fn ($k) => $k->cabang_id === null ? 1 : 0)->values();
        $kode = $kandidat->first(fn ($k) => $this->kunci($k->{$kolomKode}) === $this->kunci($sumber->{$kolomKode}));
        if ($kode) {
            return $this->kunci($kode->{$kolomNama}) === $this->kunci($sumber->{$kolomNama})
                ? ['sumber' => $sumber, 'status' => 'cocok', 'target' => $kode, 'default' => $kode->id]
                : ['sumber' => $sumber, 'status' => 'kode_sama', 'target' => $kode, 'default' => null];
        }
        $nama = $kandidat->first(fn ($k) => $this->kunci($k->{$kolomNama}) === $this->kunci($sumber->{$kolomNama}));
        if ($nama) {
            return ['sumber' => $sumber, 'status' => 'nama_sama', 'target' => $nama, 'default' => $nama->id];
        }

        return ['sumber' => $sumber, 'status' => 'baru', 'target' => null, 'default' => 'baru'];
    }

    /** Keputusan final 1 baris: 'baru' atau id milik cabang tujuan (divalidasi). */
    private function putuskan(array $baris, $input, Collection $opsi, string $label): string|int
    {
        if ($baris['status'] === 'umum') {
            return $baris['default'];
        }
        $input = $input === null || $input === '' ? $baris['default'] : $input;
        if ($input === 'baru') {
            return 'baru';
        }
        if ($input === null) {
            throw ValidationException::withMessages(['keputusan' => "Pilih dulu yang dipakai untuk {$label} (kode sama tapi nama berbeda)."]);
        }
        if (! $opsi->contains('id', (int) $input)) {
            throw ValidationException::withMessages(['keputusan' => "Pilihan untuk {$label} bukan milik cabang tujuan."]);
        }

        return (int) $input;
    }

    /** Kode unik di cabang tujuan: kode asli, atau kode-2, kode-3 ... (maks $maks karakter). */
    private function kodeUnik(string $kode, callable $sudahDipakai, int $maks): string
    {
        $kode = mb_substr($kode, 0, $maks);
        for ($i = 2; $sudahDipakai($kode); $i++) {
            $akhiran = '-' . $i;
            $kode = mb_substr(preg_replace('/-\d+$/', '', $kode), 0, $maks - strlen($akhiran)) . $akhiran;
        }

        return $kode;
    }

    private function kodeItemDipakai(int $cabangId): callable
    {
        return fn ($kode) => Item::withTrashed()->where('cabang_id', $cabangId)->where('kode_item', $kode)->exists();
    }

    private function salinBahan(Item $bahan, int $tujuanId): Item
    {
        $baru = $bahan->replicate(['cabang_id', 'harga_po_terakhir', 'harga_po_terakhir_at', 'deleted_by']);
        $baru->cabang_id = $tujuanId;
        $baru->kode_item = $this->kodeUnik($bahan->kode_item, $this->kodeItemDipakai($tujuanId), 30);
        $baru->qty_minimum = 0;
        $baru->is_active = true;
        $baru->foto = $this->salinFoto($bahan->foto);
        $baru->save();

        return $baru;
    }

    private function salinBumbu(ResepBumbu $bumbu, int $tujuanId, array $petaBahan): ResepBumbu
    {
        $baru = ResepBumbu::create([
            'cabang_id'       => $tujuanId,
            'nama'            => $bumbu->nama,
            'kode'            => $this->kodeUnik($bumbu->kode, fn ($k) => ResepBumbu::where('cabang_id', $tujuanId)->where('kode', $k)->exists(), 20),
            'jenis_olahan_id' => $bumbu->jenis_olahan_id,
            'item_id'         => null,
            'is_active'       => $bumbu->is_active,
            'catatan'         => $bumbu->catatan,
            'dibuat_oleh'     => auth()->id(),
        ]);
        foreach ($bumbu->items as $bi) {
            if (! $bi->item_id || ! isset($petaBahan[$bi->item_id])) continue;
            ResepBumbuItem::create([
                'resep_bumbu_id' => $baru->id, 'item_id' => $petaBahan[$bi->item_id], 'resep_bumbu_ref_id' => null,
                'qty_per_unit' => $bi->qty_per_unit, 'satuan' => $bi->satuan, 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => $bi->urutan,
            ]);
        }

        return $baru;
    }

    private function salinProduk(Item $produk, int $tujuanId, array $petaBahan, array $petaBumbu): Item
    {
        $baru = $produk->replicate(['cabang_id', 'harga_po_terakhir', 'harga_po_terakhir_at', 'deleted_by']);
        $baru->cabang_id = $tujuanId;
        $baru->foto = $this->salinFoto($produk->foto);
        $baru->track_stok = false;
        $baru->save();

        // Varian: atribut → nilai → kombinasi
        $petaNilai = [];
        foreach (ItemAttribute::where('item_id', $produk->id)->orderBy('urutan')->get() as $attr) {
            $attrBaru = ItemAttribute::create(['item_id' => $baru->id, 'nama' => $attr->nama, 'urutan' => $attr->urutan]);
            foreach (ItemAttributeValue::where('item_attribute_id', $attr->id)->orderBy('urutan')->get() as $val) {
                $petaNilai[$val->id] = ItemAttributeValue::create(['item_attribute_id' => $attrBaru->id, 'nilai' => $val->nilai, 'urutan' => $val->urutan])->id;
            }
        }
        foreach (ItemVariant::with('attributeValues')->where('item_id', $produk->id)->orderBy('urutan')->get() as $var) {
            $varBaru = ItemVariant::create([
                'item_id' => $baru->id, 'sku' => $var->sku, 'harga_override' => $var->harga_override,
                'stok' => 0, 'is_active' => $var->is_active, 'urutan' => $var->urutan,
            ]);
            $varBaru->attributeValues()->sync($var->attributeValues->pluck('id')->map(fn ($id) => $petaNilai[$id] ?? null)->filter()->values()->all());
        }

        // Resep: bahan & Bumbu Pusat menunjuk milik cabang tujuan
        if ($produk->resep) {
            $resep = ResepBumbu::create([
                'cabang_id'   => $tujuanId,
                'nama'        => $baru->nama_item,
                'kode'        => $this->kodeUnik(strlen('RESEP-' . $baru->kode_item) <= 20 ? 'RESEP-' . $baru->kode_item : 'RESEP-' . $baru->id,
                    fn ($k) => ResepBumbu::where('cabang_id', $tujuanId)->where('kode', $k)->exists(), 20),
                'item_id'     => $baru->id,
                'is_active'   => true,
                'catatan'     => $produk->resep->catatan,
                'dibuat_oleh' => auth()->id(),
            ]);
            foreach ($produk->resep->items as $ri) {
                $bumbuId = $ri->resep_bumbu_ref_id ? ($petaBumbu[$ri->resep_bumbu_ref_id] ?? null) : null;
                $bahanId = $ri->resep_bumbu_ref_id ? null : ($petaBahan[$ri->item_id] ?? null);
                if (! $bumbuId && ! $bahanId) continue;
                ResepBumbuItem::create([
                    'resep_bumbu_id' => $resep->id, 'item_id' => $bahanId, 'resep_bumbu_ref_id' => $bumbuId,
                    'qty_per_unit' => $ri->qty_per_unit, 'satuan' => $ri->satuan, 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => $ri->urutan,
                ]);
            }
        }

        return $baru;
    }

    /** Foto disalin jadi file sendiri — supaya ganti/hapus foto di 1 cabang tidak menghilangkan foto cabang lain. */
    private function salinFoto(?string $foto): ?string
    {
        if (! $foto || ! Storage::disk('public')->exists($foto)) {
            return null;
        }
        $ext = pathinfo($foto, PATHINFO_EXTENSION) ?: 'jpg';
        $baru = 'produk/' . uniqid('produk_', true) . '.' . $ext;
        Storage::disk('public')->copy($foto, $baru);

        return $baru;
    }
}
