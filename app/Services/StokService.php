<?php

namespace App\Services;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\TipeStockMovement;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\KategoriTransaksi;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TransaksiKeuangan;
use App\Notifications\AdjustmentNotification;
use App\Notifications\StokHabisNotification;
use App\Notifications\StokMinimumNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class StokService
{
    /**
     * Tambah stok ke lokasi (masuk).
     * Jika hargaBeli > 0, buat batch FIFO untuk tracking HPP.
     */
    public function masuk(
        int $itemId,
        int $lokasiId,
        float $qty,
        string $catatan = null,
        string $referensiType = null,
        int $referensiId = null,
        float $hargaBeli = 0.0
    ): void {
        $this->updateStok($itemId, $lokasiId, $qty);

        StockMovement::create([
            'item_id'          => $itemId,
            'lokasi_asal_id'   => null,
            'lokasi_tujuan_id' => $lokasiId,
            'qty'              => $qty,
            'tipe'             => TipeStockMovement::Masuk,
            'referensi_type'   => $referensiType,
            'referensi_id'     => $referensiId,
            'catatan'          => $catatan,
            'user_id'          => auth()->id(),
        ]);

        if ($hargaBeli > 0) {
            StockBatch::create([
                'item_id'             => $itemId,
                'lokasi_id'           => $lokasiId,
                'referensi_type'      => $referensiType,
                'referensi_id'        => $referensiId,
                'qty_awal'            => $qty,
                'qty_sisa'            => $qty,
                'harga_beli_per_unit' => $hargaBeli,
                'tanggal_masuk'       => today(),
            ]);
        }
    }

    /**
     * Kurangi stok dari lokasi (keluar).
     * Consume batches FIFO dan return total HPP.
     */
    public function keluar(
        int $itemId,
        int $lokasiId,
        float $qty,
        string $catatan = null,
        string $referensiType = null,
        int $referensiId = null
    ): float {
        $stokSaat = $this->getStok($itemId, $lokasiId);

        if ($stokSaat < $qty) {
            $itemNama = Item::find($itemId)?->nama_item ?? "item #{$itemId}";
            throw new \Exception(
                "Stok '{$itemNama}' tidak mencukupi. " .
                "Tersedia: {$stokSaat}, dibutuhkan: {$qty}."
            );
        }

        $this->updateStok($itemId, $lokasiId, -$qty);

        StockMovement::create([
            'item_id'          => $itemId,
            'lokasi_asal_id'   => $lokasiId,
            'lokasi_tujuan_id' => null,
            'qty'              => $qty,
            'tipe'             => TipeStockMovement::Keluar,
            'referensi_type'   => $referensiType,
            'referensi_id'     => $referensiId,
            'catatan'          => $catatan,
            'user_id'          => auth()->id(),
        ]);

        return $this->consumeBatchesFifo($itemId, $lokasiId, $qty);
    }

    /**
     * Proses pengiriman transfer: kurangi stok asal + consume batches FIFO.
     */
    public function prosesKirimTransfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $stokSaat = $this->getStok($item->item_id, $transfer->dari_lokasi_id);
                if ($stokSaat < $item->qty_kirim) {
                    throw new \Exception("Stok {$item->item->nama_item} tidak mencukupi. Stok: {$stokSaat}, Kirim: {$item->qty_kirim}");
                }
                $this->updateStok($item->item_id, $transfer->dari_lokasi_id, -$item->qty_kirim);

                StockMovement::create([
                    'item_id'          => $item->item_id,
                    'lokasi_asal_id'   => $transfer->dari_lokasi_id,
                    'lokasi_tujuan_id' => $transfer->ke_lokasi_id,
                    'qty'              => $item->qty_kirim,
                    'tipe'             => TipeStockMovement::Transfer,
                    'referensi_type'   => 'stock_transfer',
                    'referensi_id'     => $transfer->id,
                    'catatan'          => "Transfer #{$transfer->nomor_transfer} - dikirim",
                    'user_id'          => auth()->id(),
                ]);

                // Consume batches FIFO di lokasi asal. Modal rata-rata yang benar-benar
                // keluar disimpan di item transfer, dipakai sbg harga batch di lokasi
                // tujuan (dulu memakai harga master global — CLAUDE.md 4.38).
                $modal = $this->consumeBatchesFifo($item->item_id, $transfer->dari_lokasi_id, $item->qty_kirim);
                if ((float) $item->qty_kirim > 0) {
                    $item->update(['harga_per_unit' => round($modal / (float) $item->qty_kirim, 4)]);
                }
            }
        });
    }

    /**
     * Proses penerimaan transfer: tambah stok tujuan + buat batch FIFO di tujuan.
     * Harga batch = modal FIFO yang keluar dari lokasi asal (harga_per_unit).
     */
    public function prosesTerimaTransfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $qtyTerima = $item->qty_terima ?? $item->qty_kirim;
                if ($qtyTerima <= 0) continue;

                // Modal FIFO dari lokasi asal (transfer lama sebelum kolom ini ada:
                // fallback Harga Standar seperti perilaku lama).
                $hargaBeli = $item->harga_per_unit !== null
                    ? (float) $item->harga_per_unit
                    : (float) (Item::find($item->item_id)?->harga_beli_terakhir ?? 0);

                // 4.39 — barang di cabang tujuan = padanan Kode Item (dibuat kalau belum ada)
                $barangTujuan = $item->item
                    ? app(PadananBarangService::class)->untukCabang($item->item, (int) $transfer->ke_lokasi_id)
                    : null;

                $this->masuk(
                    $barangTujuan?->id ?? $item->item_id,
                    $transfer->ke_lokasi_id,
                    $qtyTerima,
                    "Transfer #{$transfer->nomor_transfer} - diterima",
                    'stock_transfer',
                    $transfer->id,
                    $hargaBeli,
                );
            }
        });
    }

    /**
     * Adjustment stok manual (koreksi).
     *
     * Backward compatible: caller lama cukup kirim 4 param (FIFO otomatis).
     * Caller baru bisa kirim $batchDistribusi untuk tentukan batch mana yang dikurangi.
     *
     * @param array|null $batchDistribusi  [['batch_id'=>1,'qty'=>2],...]  null = FIFO otomatis
     * @param string     $alasan           alasan koreksi, ditampilkan di notif & keuangan.
     *                                     'salah_hitung'/'audit' TIDAK bikin transaksi_keuangan
     *                                     (murni koreksi data, stok fisik tidak pernah berubah).
     */
    /**
     * @param array $options  mode_distribusi (batch_baru|batch_existing), harga_custom, batch_id_target,
     *                        catat_sebagai_biaya (bool, khusus alasan='lainnya' — default false/aman)
     */
    public function adjustment(
        int     $itemId,
        int     $lokasiId,
        float   $qtyBaru,
        string  $catatan,
        ?array  $batchDistribusi = null,
        string  $alasan = 'lainnya',
        array   $options = []
    ): void {
        DB::transaction(function () use ($itemId, $lokasiId, $qtyBaru, $catatan, $batchDistribusi, $alasan, $options) {
            $stokSaat = $this->getStok($itemId, $lokasiId);
            $selisih  = $qtyBaru - $stokSaat;

            if (abs($selisih) < 0.001) {
                return;
            }

            $item   = Item::findOrFail($itemId);
            $cabang = Cabang::findOrFail($lokasiId);

            $movement       = null;
            $nilaiTransaksi = 0.0;

            // ── Tentukan apakah adjustment ini catat transaksi keuangan ──
            // 'salah_hitung'/'audit' = murni koreksi data, stok fisik sebenarnya
            // tidak pernah berubah → JANGAN catat sebagai biaya/pemasukan.
            // 'susut'/'rusak'/'hilang' = kejadian ekonomi riil → tetap dicatat.
            // 'lainnya' ambigu → default TIDAK dicatat (aman), kecuali user
            // eksplisit centang "Catat sebagai biaya rugi?" di form.
            $alasanTanpaKeuangan = ['salah_hitung', 'audit'];
            $catatKeKeuangan = !in_array($alasan, $alasanTanpaKeuangan, true);
            if ($alasan === 'lainnya') {
                $catatKeKeuangan = (bool) ($options['catat_sebagai_biaya'] ?? false);
            }

            // ── TURUN: consume batches FIFO (atau manual), catat beban kerugian ──
            if ($qtyBaru < $stokSaat) {
                $qtyTurun       = $stokSaat - $qtyBaru;
                $nilaiTransaksi = $this->consumeBatchesForAdjustment($itemId, $lokasiId, $qtyTurun, $batchDistribusi);

                $movement = StockMovement::create([
                    'item_id'          => $itemId,
                    'lokasi_asal_id'   => $lokasiId,
                    'lokasi_tujuan_id' => null,
                    'qty'              => $qtyTurun,
                    'tipe'             => TipeStockMovement::Adjustment,
                    'catatan'          => "Koreksi turun: {$stokSaat} → {$qtyBaru}. Alasan: {$alasan}. {$catatan}",
                    'user_id'          => auth()->id(),
                ]);

                if ($nilaiTransaksi > 0 && $catatKeKeuangan) {
                    $kategori = KategoriTransaksi::firstOrCreate(
                        ['kode' => 'BEBAN-STOK'],
                        ['nama' => 'Beban Kerugian Stok', 'tipe' => 'pengeluaran',
                         'is_system' => true, 'is_active' => true, 'urutan' => 17]
                    );
                    TransaksiKeuangan::create([
                        'cabang_id'         => $lokasiId,
                        'kas_id'            => null,
                        'nomor_transaksi'   => $this->generateNomorAdjustment(),
                        'tanggal_transaksi' => today()->toDateString(),
                        'tipe'              => TipeTransaksiKeuangan::Pengeluaran,
                        'kategori'          => KategoriEnum::Lainnya,
                        'kategori_id'       => $kategori->id,
                        'keterangan'        => "Adj. stok {$item->nama_item}: {$alasan} — {$catatan}",
                        'jumlah'            => $nilaiTransaksi,
                        'referensi_type'    => 'stock_movement',
                        'referensi_id'      => $movement->id,
                        'created_by'        => auth()->id(),
                    ]);
                }

            // ── NAIK: buat/update batch, catat koreksi pemasukan ──
            } else {
                $qtyNaik  = $qtyBaru - $stokSaat;
                $modeDist = $options['mode_distribusi'] ?? 'batch_baru';

                // Movement buat dulu (dipakai kedua mode)
                $movement = StockMovement::create([
                    'item_id'          => $itemId,
                    'lokasi_asal_id'   => null,
                    'lokasi_tujuan_id' => $lokasiId,
                    'qty'              => $qtyNaik,
                    'tipe'             => TipeStockMovement::Adjustment,
                    'catatan'          => "Koreksi naik: {$stokSaat} → {$qtyBaru}. Alasan: {$alasan}. {$catatan}",
                    'user_id'          => auth()->id(),
                ]);

                if ($modeDist === 'batch_existing') {
                    // Tambah qty ke batch yang dipilih
                    $targetBatch = StockBatch::where('id', (int) ($options['batch_id_target'] ?? 0))
                        ->where('item_id', $itemId)
                        ->where('lokasi_id', $lokasiId)
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->firstOrFail();

                    $hargaBeli = (float) $targetBatch->harga_beli_per_unit;

                    DB::table('stock_batches')
                        ->where('id', $targetBatch->id)
                        ->update([
                            'qty_awal'   => DB::raw("qty_awal + {$qtyNaik}"),
                            'qty_sisa'   => DB::raw("qty_sisa + {$qtyNaik}"),
                            'updated_at' => now(),
                        ]);
                } else {
                    // batch_baru (default)
                    // 4.38-E — tanpa harga custom: harga beli outlet ini (fallback Harga Standar)
                    $hargaBeli = (float) ($options['harga_custom'] ?? $item->hargaBeliDiCabang($lokasiId));

                    StockBatch::create([
                        'item_id'             => $itemId,
                        'lokasi_id'           => $lokasiId,
                        'referensi_type'      => 'adjustment_masuk',
                        'referensi_id'        => $movement->id,
                        'qty_awal'            => $qtyNaik,
                        'qty_sisa'            => $qtyNaik,
                        'harga_beli_per_unit' => $hargaBeli,
                        'tanggal_masuk'       => today(),
                    ]);
                }

                $nilaiTransaksi = $qtyNaik * $hargaBeli;

                if ($nilaiTransaksi > 0 && $catatKeKeuangan) {
                    $kategori = KategoriTransaksi::firstOrCreate(
                        ['kode' => 'KOR-STOK'],
                        ['nama' => 'Koreksi Stok Masuk', 'tipe' => 'pemasukan',
                         'is_system' => true, 'is_active' => true, 'urutan' => 6]
                    );
                    TransaksiKeuangan::create([
                        'cabang_id'         => $lokasiId,
                        'kas_id'            => null,
                        'nomor_transaksi'   => $this->generateNomorAdjustment(),
                        'tanggal_transaksi' => today()->toDateString(),
                        'tipe'              => TipeTransaksiKeuangan::Pemasukan,
                        'kategori'          => KategoriEnum::Lainnya,
                        'kategori_id'       => $kategori->id,
                        'keterangan'        => "Adj. stok {$item->nama_item}: koreksi naik — {$catatan}",
                        'jumlah'            => $nilaiTransaksi,
                        'referensi_type'    => 'stock_movement',
                        'referensi_id'      => $movement->id,
                        'created_by'        => auth()->id(),
                    ]);
                }
            }

            // ── Update qty di tabel stocks ke nilai yang diminta ──
            Stock::withTrashed()
                ->where('item_id', $itemId)
                ->where('lokasi_id', $lokasiId)
                ->update(['qty' => $qtyBaru, 'updated_at' => now()]);

            // ── Cek notifikasi stok minimum/habis jika turun ──
            if ($qtyBaru < $stokSaat) {
                $stok = Stock::withTrashed()
                    ->where('item_id', $itemId)
                    ->where('lokasi_id', $lokasiId)
                    ->first();
                if ($stok) {
                    $this->cekNotifikasiStok($stok, $itemId, $lokasiId);
                }
            }

            // ── Notifikasi ke Owner/Admin Pusat ──
            try {
                NotificationService::send(
                    NotificationService::getOwnerAndAdminPusat(),
                    new AdjustmentNotification($item, $selisih, $nilaiTransaksi, $alasan, $cabang)
                );
            } catch (\Exception $e) {
                \Log::warning('Gagal kirim notif adjustment: ' . $e->getMessage());
            }
        });
    }

    /**
     * Reset stok ke 0 — Owner-only, untuk cleanup data test / fresh start,
     * BUKAN via jalur Adjustment biasa (fitur terpisah, tidak menyentuh
     * logic adjustment() sama sekali).
     *
     * - stock_batches: SOFT DELETE (reversibel via fitur Data Terhapus existing)
     * - stock_movements: TIDAK dihapus — riwayat fisik lama dibiarkan utuh,
     *   ditambah 1 movement baru sebagai jejak kapan/siapa yang reset
     * - TIDAK PERNAH membuat transaksi_keuangan
     */
    public function resetStok(Stock $stock): void
    {
        DB::transaction(function () use ($stock) {
            $qtyLama  = (float) $stock->qty;
            $itemId   = $stock->item_id;
            $lokasiId = $stock->lokasi_id;

            StockBatch::where('item_id', $itemId)
                ->where('lokasi_id', $lokasiId)
                ->whereNull('deleted_at')
                ->get()
                ->each(fn (StockBatch $batch) => $batch->delete());

            StockMovement::create([
                'item_id'          => $itemId,
                'lokasi_asal_id'   => $lokasiId,
                'lokasi_tujuan_id' => null,
                'qty'              => $qtyLama,
                'tipe'             => TipeStockMovement::Adjustment,
                'catatan'          => "RESET STOK oleh Owner: {$qtyLama} → 0 (via tombol Hapus Stok, bukan Adjustment biasa)",
                'user_id'          => auth()->id(),
            ]);

            $stock->update(['qty' => 0]);

            activity('Stock')
                ->performedOn($stock)
                ->causedBy(auth()->user())
                ->withProperties([
                    'qty_lama' => $qtyLama,
                    'item'     => $stock->item?->nama_item,
                    'lokasi'   => $stock->lokasi?->nama_cabang,
                ])
                ->log(
                    (auth()->user()?->name ?? 'User') . ' reset stok ' .
                    ($stock->item?->nama_item ?? 'item') . ' di ' .
                    ($stock->lokasi?->nama_cabang ?? 'lokasi') . ' dari ' .
                    rtrim(rtrim(number_format($qtyLama, 3, ',', '.'), '0'), ',') . ' ke 0'
                );
        });
    }

    /**
     * Ambil qty stok item di lokasi tertentu.
     * withTrashed() agar soft-deleted record (akibat cascade cabang/item) tetap terbaca.
     */
    public function getStok(int $itemId, int $lokasiId): float
    {
        return (float) Stock::withTrashed()
            ->where('item_id', $itemId)
            ->where('lokasi_id', $lokasiId)
            ->value('qty') ?? 0;
    }

    /**
     * Consume batches FIFO untuk qty yang keluar dari lokasi.
     * Return total HPP (Harga Pokok Produksi/Penjualan) dari batch yang dikonsumsi.
     * Jika batch tidak cukup (data lama tanpa batch), fallback ke harga_beli_terakhir item.
     */
    private function consumeBatchesFifo(int $itemId, int $lokasiId, float $qty): float
    {
        $sisaQty  = $qty;
        $totalHpp = 0.0;

        $batches = StockBatch::where('item_id', $itemId)
            ->where('lokasi_id', $lokasiId)
            ->where('qty_sisa', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('tanggal_masuk', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($sisaQty <= 0) break;

            $consume   = min((float) $batch->qty_sisa, $sisaQty);
            $totalHpp += $consume * (float) $batch->harga_beli_per_unit;
            $batch->decrement('qty_sisa', $consume);
            $sisaQty  -= $consume;
        }

        // Fallback: qty tanpa batch (data lama sebelum FIFO) → harga beli outlet (fallback Harga Standar)
        if ($sisaQty > 0) {
            $hargaFallback = (float) (Item::find($itemId)?->hargaBeliDiCabang($lokasiId) ?? 0);
            $totalHpp     += $sisaQty * $hargaFallback;
        }

        return $totalHpp;
    }

    /**
     * Consume batches untuk adjustment turun.
     * Jika $batchDistribusi null → FIFO otomatis (delegate ke consumeBatchesFifo).
     * Jika manual → iterasi $batchDistribusi, consume qty per batch yang ditentukan.
     * Return total nilai (HPP) batch yang dikonsumsi.
     */
    private function consumeBatchesForAdjustment(int $itemId, int $lokasiId, float $qty, ?array $batchDistribusi): float
    {
        if ($batchDistribusi === null) {
            return $this->consumeBatchesFifo($itemId, $lokasiId, $qty);
        }

        $sisaQty    = $qty;
        $totalNilai = 0.0;

        foreach ($batchDistribusi as $bd) {
            if ($sisaQty <= 0.001) break;

            $batch = StockBatch::where('id', (int) $bd['batch_id'])
                ->where('item_id', $itemId)
                ->where('lokasi_id', $lokasiId)
                ->where('qty_sisa', '>', 0)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$batch) continue;

            $consume     = min((float) $batch->qty_sisa, (float) $bd['qty'], $sisaQty);
            $totalNilai += $consume * (float) $batch->harga_beli_per_unit;
            $batch->decrement('qty_sisa', $consume);
            $sisaQty   -= $consume;
        }

        // Sisa yang tidak teralokasi oleh distribusi manual → fallback harga_beli_terakhir
        if ($sisaQty > 0.001) {
            $totalNilai += $sisaQty * (float) (Item::find($itemId)?->hargaBeliDiCabang($lokasiId) ?? 0);
        }

        return $totalNilai;
    }

    /**
     * Generate nomor transaksi untuk adjustment keuangan (ADJ-YYYYMMDD-XXXX).
     */
    private function generateNomorAdjustment(): string
    {
        $prefix  = 'ADJ-' . date('Ymd');
        $lastSeq = DB::table('transaksi_keuangans')
            ->where('nomor_transaksi', 'like', $prefix . '-%')
            ->max(DB::raw("CAST(SUBSTRING_INDEX(nomor_transaksi, '-', -1) AS UNSIGNED)"));

        return $prefix . '-' . str_pad(((int) $lastSeq) + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Update qty stok (delta bisa + atau -).
     * Pakai withTrashed() untuk hindari UNIQUE CONSTRAINT VIOLATION saat ada
     * soft-deleted record dengan key yang sama (akibat cascade delete yang sudah di-restore).
     */
    private function updateStok(int $itemId, int $lokasiId, float $delta): void
    {
        $stok = Stock::withTrashed()
            ->where('item_id', $itemId)
            ->where('lokasi_id', $lokasiId)
            ->first();

        if ($stok) {
            if ($stok->trashed()) {
                $stok->restore();
            }
        } else {
            // Inherit threshold dari master Item (qty_minimum global) supaya
            // alert stok minimum langsung aktif tanpa perlu setting manual
            // per lokasi — kalau item belum ada qty_minimum-nya (0), stocks
            // baru juga 0 (bisa di-set manual lewat "Set Minimum" nanti).
            $item = Item::find($itemId);

            $stok = new Stock();
            $stok->item_id     = $itemId;
            $stok->lokasi_id   = $lokasiId;
            $stok->qty         = 0;
            $stok->qty_minimum = $item->qty_minimum ?? 0;
            $stok->updated_at  = now();
            $stok->save();
        }

        $qtySebelum = (float) $stok->qty;
        $qtyBaru    = max(0, $qtySebelum + $delta);

        $stok->qty        = $qtyBaru;
        $stok->updated_at = now();
        $stok->save();

        // Cek notifikasi stok minimum/habis hanya saat pengurangan
        if ($delta < 0 && $stok->qty_minimum > 0) {
            $this->cekNotifikasiStok($stok, $itemId, $lokasiId);
        }
    }

    /**
     * Kirim notifikasi bila stok habis atau di bawah minimum
     */
    private function cekNotifikasiStok(Stock $stok, int $itemId, int $lokasiId): void
    {
        try {
            $item   = Item::find($itemId);
            $cabang = Cabang::find($lokasiId);
            if (!$item || !$cabang) return;

            $namaItem   = $item->nama_item;
            $namaCabang = $cabang->nama_cabang;
            $recipients = NotificationService::getManagementAndGudang($lokasiId);

            if ($stok->qty <= 0) {
                NotificationService::send($recipients, new StokHabisNotification($namaItem, $namaCabang, $lokasiId));
            } elseif ($stok->qty <= $stok->qty_minimum) {
                NotificationService::send($recipients, new StokMinimumNotification(
                    $namaItem, $namaCabang, $stok->qty, $stok->qty_minimum, $lokasiId
                ));
            }
        } catch (\Exception $e) {
            \Log::warning('Gagal cek notifikasi stok: ' . $e->getMessage());
        }
    }
}
