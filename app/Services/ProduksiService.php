<?php

namespace App\Services;

use App\Enums\TipeCabang;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ProduksiBatch;
use App\Models\ResepProduksi;
use App\Support\CabangAktif;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 4.49 Tahap B1 — Produksi bahan setengah jadi di gudang (porting
 * erp-coffeeshop 1139fe5): bahan dipotong sesuai Resep Produksi, barang hasil
 * masuk stok dengan modal = biaya bahan ÷ hasil nyata (bisa diubah manual).
 * Semua mutasi stok lewat StokService (FIFO + kartu stok, CLAUDE.md 3.3).
 *
 * Penyesuaian Dimsum:
 * - Data barang terpisah per cabang (4.39): barang hasil & bahan resep harus
 *   milik gudang yang sama (`items.cabang_id`; NULL = data lama, boleh).
 * - Harga Standar (`harga_beli_terakhir`, 4.38) TIDAK ditimpa — hanya diisi
 *   kalau masih kosong, sama seperti penerimaan PO. HPP penjualan tetap FIFO.
 */
class ProduksiService
{
    /** Tipe barang yang boleh jadi hasil maupun bahan resep produksi. */
    public const TIPE_BARANG = ['bahan_baku', 'kemasan', 'tambahan_gratis'];

    public function __construct(private StokService $stokService) {}

    /** Gudang (cabang tipe Gudang Pusat, aktif) yang boleh dikelola user yang login. */
    public static function gudangUntuk(): Collection
    {
        return Cabang::where('tipe', TipeCabang::GudangPusat->value)
            ->where('is_active', true)
            ->orderBy('nama_cabang')
            ->get()
            ->filter(fn (Cabang $c) => CabangAktif::bolehAkses($c->id))
            ->values();
    }

    /** Gudang bawaan: cabang aktif di header kalau itu gudang, selain itu gudang pertama. */
    public static function gudangDefault(Collection $gudang): ?Cabang
    {
        return $gudang->firstWhere('id', CabangAktif::id()) ?? $gudang->first();
    }

    /** Barang milik gudang ini yang bisa dipakai di resep produksi (hasil & bahan). */
    public static function barangOptions(int $gudangId): Collection
    {
        return Item::whereIn('tipe', self::TIPE_BARANG)->milikCabang($gudangId)
            ->with('category')->aktif()->orderBy('nama_item')->get();
    }

    /** Barang ini boleh dipakai di gudang itu? (milik gudang itu, atau data lama tanpa pemilik) */
    private static function milikGudang(?Item $item, int $gudangId): bool
    {
        return $item && ($item->cabang_id === null || (int) $item->cabang_id === $gudangId);
    }

    /**
     * Simpan (buat/ubah) Resep Produksi untuk satu barang hasil di satu gudang.
     *
     * @param array{cabang_id:int, item_id:int, qty_hasil:float, catatan?:?string, is_active?:bool, bahan: array<int, array{item_id:int, qty:float, satuan:string}>} $data
     */
    public function simpanResep(array $data, ?ResepProduksi $resep, int $userId): ResepProduksi
    {
        $gudangId = (int) $data['cabang_id'];
        if (! self::gudangUntuk()->contains('id', $gudangId)) {
            throw new \Exception('Resep produksi hanya untuk gudang (cabang tipe Gudang Pusat) yang Anda kelola.');
        }

        $hasilId = (int) $data['item_id'];
        $bahan = collect($data['bahan'] ?? [])->filter(fn ($b) => ! empty($b['item_id']) && (float) ($b['qty'] ?? 0) > 0)->values();

        if ($bahan->isEmpty()) {
            throw new \Exception('Resep produksi minimal berisi 1 bahan dengan takaran lebih dari 0.');
        }
        if ($bahan->contains(fn ($b) => (int) $b['item_id'] === $hasilId)) {
            throw new \Exception('Barang hasil tidak boleh dipakai sebagai bahan di resepnya sendiri.');
        }
        if ($bahan->pluck('item_id')->map(fn ($id) => (int) $id)->duplicates()->isNotEmpty()) {
            throw new \Exception('Ada bahan yang dipilih lebih dari sekali — gabungkan takarannya dalam satu baris.');
        }

        $boleh = self::barangOptions($gudangId)->keyBy('id');
        if (! $boleh->has($hasilId) || $bahan->contains(fn ($b) => ! $boleh->has((int) $b['item_id']))) {
            throw new \Exception('Barang hasil dan semua bahan harus barang milik gudang ini (Bahan Baku & Kemasan).');
        }

        $lain = ResepProduksi::where('item_id', $hasilId)->when($resep, fn ($q) => $q->where('id', '!=', $resep->id))->exists();
        if ($lain) {
            throw new \Exception('Barang ini sudah punya Resep Produksi. Ubah resep yang sudah ada.');
        }

        return DB::transaction(function () use ($data, $resep, $userId, $hasilId, $bahan) {
            $resep ??= new ResepProduksi(['created_by' => $userId]);
            $resep->fill([
                'item_id'   => $hasilId,
                'qty_hasil' => (float) $data['qty_hasil'],
                'catatan'   => $data['catatan'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            $resep->items()->delete();
            foreach ($bahan as $i => $b) {
                $resep->items()->create([
                    'item_id' => (int) $b['item_id'],
                    'qty'     => (float) $b['qty'],
                    'satuan'  => trim((string) $b['satuan']),
                    'urutan'  => $i,
                ]);
            }

            return $resep->fresh('items.bahan');
        });
    }

    /**
     * Catat satu produksi: potong bahan (resep × rencana), tambah stok hasil
     * sebanyak hasil nyata.
     *
     * @param array{cabang_id:int, resep_produksi_id:int, tanggal:string, qty_rencana:float, qty_hasil:float, modal_manual?:?float, catatan?:?string} $data
     */
    public function produksi(array $data, int $userId): ProduksiBatch
    {
        $cabangId = (int) $data['cabang_id'];
        if (! self::gudangUntuk()->contains('id', $cabangId)) {
            throw new \Exception('Produksi hanya bisa dicatat di gudang (cabang tipe Gudang Pusat) yang Anda kelola.');
        }

        $resep = ResepProduksi::with(['item', 'items.bahan'])->findOrFail((int) $data['resep_produksi_id']);
        if (! self::milikGudang($resep->item, $cabangId) || $resep->items->contains(fn ($b) => ! self::milikGudang($b->bahan, $cabangId))) {
            throw new \Exception("Resep produksi '{$resep->item?->nama_item}' bukan milik gudang ini.");
        }
        if (! $resep->is_active) {
            throw new \Exception("Resep produksi '{$resep->item->nama_item}' sedang nonaktif.");
        }
        if ($resep->items->isEmpty()) {
            throw new \Exception('Resep produksi ini belum punya bahan.');
        }

        $rencana = (float) $data['qty_rencana'];
        $hasil = (float) $data['qty_hasil'];
        if ($rencana <= 0 || $hasil <= 0) {
            throw new \Exception('Jumlah rencana dan hasil nyata harus lebih dari 0.');
        }
        $manual = isset($data['modal_manual']) && $data['modal_manual'] !== '' && $data['modal_manual'] !== null
            ? (float) $data['modal_manual'] : null;
        if ($manual !== null && $manual < 0) {
            throw new \Exception('Modal per unit tidak boleh minus.');
        }

        $kebutuhan = array_filter($resep->kebutuhan($rencana), fn ($q) => $q > 0);
        if (empty($kebutuhan)) {
            throw new \Exception('Takaran bahan terlalu kecil untuk jumlah rencana ini (dibulatkan menjadi 0).');
        }

        return DB::transaction(function () use ($data, $userId, $cabangId, $resep, $rencana, $hasil, $manual, $kebutuhan) {
            // Cek semua bahan dulu supaya pesan menyebut SEMUA yang kurang, bukan satu per satu
            $kurang = [];
            foreach ($kebutuhan as $itemId => $butuh) {
                $stok = $this->stokService->getStok($itemId, $cabangId);
                if ($stok < $butuh) {
                    $b = $resep->items->firstWhere('item_id', $itemId)?->bahan;
                    $kurang[] = ($b?->nama_item ?? "#{$itemId}") . ' (butuh ' . self::angka($butuh) . ' ' . $b?->satuan . ', ada ' . self::angka($stok) . ')';
                }
            }
            if ($kurang) {
                throw new \Exception('Stok bahan di gudang tidak cukup: ' . implode('; ', $kurang) . '.');
            }

            $batch = ProduksiBatch::create([
                'kode_batch'        => $this->nomorBerikutnya(),
                'tanggal'           => $data['tanggal'],
                'cabang_id'         => $cabangId,
                'item_id'           => $resep->item_id,
                'resep_produksi_id' => $resep->id,
                'qty_rencana'       => $rencana,
                'qty_hasil'         => $hasil,
                'status'            => 'selesai',
                'catatan'           => $data['catatan'] ?? null,
                'user_id'           => $userId,
            ]);

            $biaya = 0.0;
            foreach ($kebutuhan as $itemId => $butuh) {
                $hpp = $this->stokService->keluar(
                    $itemId, $cabangId, $butuh,
                    "Bahan produksi {$batch->kode_batch} — {$resep->item->nama_item}",
                    'produksi', $batch->id
                );
                $batch->bahan()->create(['item_id' => $itemId, 'qty' => $butuh, 'biaya' => round($hpp, 2)]);
                $biaya += $hpp;
            }

            $otomatis = round($biaya / $hasil, 2);
            $modal = $manual ?? $otomatis;

            $this->stokService->masuk(
                $resep->item_id, $cabangId, $hasil,
                "Hasil produksi {$batch->kode_batch}",
                'produksi', $batch->id, $modal
            );
            // Harga Standar diisi hanya kalau masih kosong (aturan 4.38, sama dengan penerimaan PO)
            if ($modal > 0 && ! (float) $resep->item->harga_beli_terakhir) {
                $resep->item->update(['harga_beli_terakhir' => $modal]);
            }

            $batch->update([
                'biaya_bahan'    => round($biaya, 2),
                'modal_otomatis' => $otomatis,
                'modal_per_unit' => $modal,
                'modal_manual'   => $manual !== null,
            ]);

            return $batch->fresh(['item', 'bahan.item', 'cabang']);
        });
    }

    /**
     * Batalkan produksi: hanya kalau stok hasil di gudang masih ≥ hasil
     * produksi ini (belum terpakai/terkirim). Stok hasil ditarik, bahan
     * dikembalikan dengan modal yang terpakai saat produksi.
     */
    public function batalkan(ProduksiBatch $batch, string $alasan, int $userId): ProduksiBatch
    {
        return DB::transaction(function () use ($batch, $alasan, $userId) {
            $batch = ProduksiBatch::with(['item', 'bahan'])->lockForUpdate()->findOrFail($batch->id);

            if ($batch->status !== 'selesai') {
                throw new \Exception('Produksi ini sudah dibatalkan.');
            }
            if (! self::gudangUntuk()->contains('id', $batch->cabang_id)) {
                throw new \Exception('Anda tidak mengelola gudang produksi ini.');
            }

            $stokHasil = $this->stokService->getStok($batch->item_id, $batch->cabang_id);
            if ($stokHasil < (float) $batch->qty_hasil) {
                throw new \Exception(
                    "Tidak bisa dibatalkan: stok {$batch->item->nama_item} di gudang tinggal " . self::angka($stokHasil) .
                    ', sedangkan hasil produksi ini ' . self::angka((float) $batch->qty_hasil) . ' (sebagian sudah terpakai/terkirim). ' .
                    'Kalau perlu, koreksi lewat Penyesuaian Stok.'
                );
            }

            $this->stokService->keluar(
                $batch->item_id, $batch->cabang_id, (float) $batch->qty_hasil,
                "Batal produksi {$batch->kode_batch} — hasil ditarik", 'produksi_batal', $batch->id
            );

            foreach ($batch->bahan as $b) {
                $qty = (float) $b->qty;
                $this->stokService->masuk(
                    $b->item_id, $batch->cabang_id, $qty,
                    "Batal produksi {$batch->kode_batch} — bahan dikembalikan", 'produksi_batal', $batch->id,
                    $qty > 0 ? round((float) $b->biaya / $qty, 2) : 0.0
                );
            }

            $batch->update([
                'status'        => 'dibatalkan',
                'dibatalkan_by' => $userId,
                'dibatalkan_at' => now(),
                'alasan_batal'  => $alasan,
            ]);

            return $batch->fresh();
        });
    }

    private function nomorBerikutnya(): string
    {
        $prefix = 'PRD-' . now()->format('Ym') . '-';
        $last = ProduksiBatch::where('kode_batch', 'like', $prefix . '%')->lockForUpdate()->max('kode_batch');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public static function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }
}
