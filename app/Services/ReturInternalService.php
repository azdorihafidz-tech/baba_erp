<?php

namespace App\Services;

use App\Enums\KategoriTransaksi;
use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Enums\TipePembayaran;
use App\Enums\TipeTransaksi;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Kas;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderKirimanItem;
use App\Models\ReturInternal;
use App\Models\StockBatch;
use App\Models\TransaksiKeuangan;
use App\Support\CabangAktif;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4.49 Tahap B3 — Retur cabang → Gudang (porting erp-coffeeshop 40bd55e).
 *
 * - Kirim Retur (cabang): stok BARANG MILIK CABANG keluar (FIFO), status menunggu.
 * - Terima Retur (Gudang): stok barang milik Gudang masuk lagi.
 *     uang kembali = Ya  → modal = modal asli Gudang, kas Gudang → kas cabang (kas yang dulu
 *                          membayar kiriman), + 1 order koreksi bernilai minus di Gudang.
 *     uang kembali = Tidak → modal Rp0 (rugi ditanggung cabang), tidak ada uang berpindah.
 * - Tolak (Gudang) / Batalkan (cabang) selama menunggu: stok kembali ke cabang.
 *
 * Uang retur dicatat sebagai CERMIN MINUS dari transaksi aslinya (Pemasukan/Penjualan −nilai di
 * Gudang, Pengeluaran/Pembelian Bahan −nilai di cabang) — temuan coffeeshop: laporan keuangan
 * menjumlah per kategori, jadi tipe kebalikan malah menambah angka.
 */
class ReturInternalService
{
    public function __construct(private StokService $stokService, private PenjualanService $penjualanService) {}

    /** Sisa yang masih boleh diretur per baris kiriman. */
    public static function sisaBolehRetur(OrderKirimanItem $r): float
    {
        return max(0, round((float) $r->qty_terima - (float) $r->qty_retur, 3));
    }

    /**
     * @param array<int, float|string|null> $qty order_kiriman_item_id => qty diretur
     */
    public function buat(Order $kiriman, array $qty, string $alasan, bool $uangKembali, ?string $catatan, int $userId): ReturInternal
    {
        return DB::transaction(function () use ($kiriman, $qty, $alasan, $uangKembali, $catatan, $userId) {
            $kiriman = Order::with(['kirimanItems.item', 'kirimanItems.itemTujuan', 'cabang', 'outletTujuan'])->lockForUpdate()->findOrFail($kiriman->id);

            if (! $kiriman->isKirimanCabang() || $kiriman->status_kirim !== 'diterima' || $kiriman->status !== StatusOrder::Selesai) {
                throw new \Exception('Retur hanya bisa dari kiriman yang sudah diterima.');
            }
            if (! array_key_exists($alasan, ReturInternal::ALASAN)) {
                throw new \Exception('Alasan retur tidak valid.');
            }

            $baris = [];
            foreach ($kiriman->kirimanItems as $r) {
                $isi = $qty[$r->id] ?? null;
                $q = ($isi === null || $isi === '') ? 0.0 : round((float) $isi, 3);
                if ($q <= 0) continue;
                $barang = $r->itemTujuan ?? $r->item;
                $sisa = self::sisaBolehRetur($r);
                if ($q > $sisa + 0.0005) {
                    throw new \Exception("Retur {$barang?->nama_item} maksimal " . ProduksiService::angka($sisa) . " {$barang?->satuan} (sisa yang diterima dan belum diretur).");
                }
                $baris[] = [$r, min($q, $sisa)];
            }
            if (! $baris) {
                throw new \Exception('Isi jumlah retur minimal 1 barang.');
            }

            $retur = ReturInternal::create([
                'kode_retur'     => $this->nomorBerikutnya(),
                'order_id'       => $kiriman->id,
                'dari_cabang_id' => $kiriman->outlet_tujuan_id,
                'ke_cabang_id'   => $kiriman->cabang_id,
                'alasan'         => $alasan,
                'catatan'        => $catatan,
                'uang_kembali'   => $uangKembali,
                'status'         => 'menunggu',
                'dibuat_by'      => $userId,
            ]);

            $nilai = 0.0;
            foreach ($baris as [$r, $q]) {
                $itemCabang = (int) ($r->item_tujuan_id ?: $r->item_id);
                $hppCabang = $this->stokService->keluar(
                    $itemCabang, $kiriman->outlet_tujuan_id, $q,
                    "Retur {$retur->kode_retur} ke {$kiriman->cabang?->nama_cabang} ({$retur->alasan_label})",
                    'retur_internal', $retur->id
                );
                $harga = $r->harga_terima_per_unit;
                $retur->items()->create([
                    'order_kiriman_item_id' => $r->id,
                    'item_id'               => $itemCabang,
                    'qty'                   => $q,
                    'harga_per_unit'        => $harga,
                    'hpp_per_unit'          => $r->hpp_per_unit,
                    'hpp_cabang'            => round($hppCabang, 2),
                ]);
                $r->increment('qty_retur', $q);
                $nilai += round($q * $harga, 2);
            }
            $retur->update(['nilai' => round($nilai, 2)]);

            return $retur->fresh('items.item');
        });
    }

    public function terima(ReturInternal $retur, int $userId): ReturInternal
    {
        return DB::transaction(function () use ($retur, $userId) {
            $retur = ReturInternal::with(['items.kirimanItem.orderItem', 'order', 'dariCabang', 'keCabang'])->lockForUpdate()->findOrFail($retur->id);
            if ($retur->status !== 'menunggu') {
                throw new \Exception('Retur ini sudah diproses sebelumnya.');
            }

            $nilai = (float) $retur->nilai;
            $kasPengirim = $kasCabang = null;
            if ($retur->uang_kembali && $nilai > 0) {
                $kasPengirim = Kas::where('cabang_id', $retur->ke_cabang_id)->where('default_untuk', 'tunai')->where('is_active', true)->first();
                // Uang kembali ke kas yang dulu membayar kiriman (dipilih saat Terima); fallback Kas Tunai
                $kasBayarId = TransaksiKeuangan::where('referensi_type', 'order_internal')->where('referensi_id', $retur->order_id)
                    ->where('cabang_id', $retur->dari_cabang_id)->where('jumlah', '>', 0)->value('kas_id');
                $kasCabang = Kas::where('cabang_id', $retur->dari_cabang_id)->where('is_active', true)
                    ->when($kasBayarId, fn ($q) => $q->whereKey($kasBayarId), fn ($q) => $q->where('default_untuk', 'tunai'))->first()
                    ?? Kas::where('cabang_id', $retur->dari_cabang_id)->where('default_untuk', 'tunai')->where('is_active', true)->first();
                if (! $kasPengirim || ! $kasCabang) {
                    throw new \Exception('Kas Tunai aktif Gudang dan kas aktif cabang wajib ada untuk retur dengan uang kembali.');
                }
            }

            foreach ($retur->items as $it) {
                $q = (float) $it->qty;
                $itemGudang = (int) ($it->kirimanItem?->item_id ?: $it->item_id);
                $catatan = "Terima retur {$retur->kode_retur} dari {$retur->dariCabang?->nama_cabang}";
                if ($retur->uang_kembali) {
                    $this->stokService->masuk($itemGudang, $retur->ke_cabang_id, $q, $catatan, 'retur_internal', $retur->id, (float) $it->hpp_per_unit);
                } else {
                    // Rugi ditanggung cabang: barang masuk bernilai Rp0 (batch FIFO harga 0, bukan fallback harga beli)
                    $this->stokService->masuk($itemGudang, $retur->ke_cabang_id, $q, $catatan . ' (tanpa uang kembali)', 'retur_internal', $retur->id, 0.0);
                    StockBatch::create([
                        'item_id' => $itemGudang, 'lokasi_id' => $retur->ke_cabang_id,
                        'referensi_type' => 'retur_internal', 'referensi_id' => $retur->id,
                        'qty_awal' => $q, 'qty_sisa' => $q, 'harga_beli_per_unit' => 0, 'tanggal_masuk' => today(),
                    ]);
                }
            }

            if ($kasPengirim && $kasCabang) {
                $koreksi = $this->buatOrderKoreksi($retur, $kasPengirim->id);

                $kasPengirim->decrement('saldo_sekarang', $nilai);
                TransaksiKeuangan::create([
                    'cabang_id' => $retur->ke_cabang_id, 'kas_id' => $kasPengirim->id,
                    'nomor_transaksi' => 'TRX-' . $koreksi->nomor_order, 'tanggal_transaksi' => today(),
                    'tipe' => TipeTransaksiKeuangan::Pemasukan, 'kategori' => KategoriTransaksi::Penjualan,
                    'keterangan' => "Retur {$retur->kode_retur} dari {$retur->dariCabang?->nama_cabang} — uang dikembalikan",
                    'jumlah' => -$nilai, 'referensi_type' => 'order', 'referensi_id' => $koreksi->id, 'created_by' => $userId,
                ]);

                $kasCabang->increment('saldo_sekarang', $nilai);
                TransaksiKeuangan::create([
                    'cabang_id' => $retur->dari_cabang_id, 'kas_id' => $kasCabang->id,
                    'nomor_transaksi' => 'TRX-RTR-' . $retur->kode_retur, 'tanggal_transaksi' => today(),
                    'tipe' => TipeTransaksiKeuangan::Pengeluaran, 'kategori' => KategoriTransaksi::PembelianBahan,
                    'keterangan' => "Uang retur {$retur->kode_retur} dari {$retur->keCabang?->nama_cabang} (pembelian berkurang)",
                    'jumlah' => -$nilai, 'referensi_type' => 'order_internal', 'referensi_id' => $retur->order_id, 'created_by' => $userId,
                ]);
            }

            $retur->update(['status' => 'diterima', 'diproses_by' => $userId, 'diproses_at' => now()]);

            return $retur->fresh(['items.item', 'orderKoreksi']);
        });
    }

    /** Tolak (Gudang) atau batalkan (cabang) retur yang belum diterima: stok kembali ke cabang. */
    public function tolak(ReturInternal $retur, string $status, string $alasan, int $userId): ReturInternal
    {
        return DB::transaction(function () use ($retur, $status, $alasan, $userId) {
            $retur = ReturInternal::with(['items.kirimanItem'])->lockForUpdate()->findOrFail($retur->id);
            if ($retur->status !== 'menunggu') {
                throw new \Exception('Retur ini sudah diproses sebelumnya.');
            }

            foreach ($retur->items as $it) {
                $q = (float) $it->qty;
                $this->stokService->masuk(
                    $it->item_id, $retur->dari_cabang_id, $q,
                    "Retur {$retur->kode_retur} " . ($status === 'ditolak' ? 'ditolak Gudang' : 'dibatalkan') . ' — kembali ke stok',
                    'retur_internal_batal', $retur->id,
                    $q > 0 ? round((float) $it->hpp_cabang / $q, 2) : 0.0
                );
                $it->kirimanItem?->decrement('qty_retur', $q);
            }

            $retur->update(['status' => $status, 'alasan_tolak' => $alasan, 'diproses_by' => $userId, 'diproses_at' => now()]);

            return $retur->fresh();
        });
    }

    /**
     * 1 order koreksi bernilai minus di Gudang: laporan penjualan/produk/kategori & keuntungan
     * Gudang ikut turun di tanggal retur. Di Semua Cabang ikut dieliminasi (customer_type internal).
     */
    private function buatOrderKoreksi(ReturInternal $retur, int $kasId): Order
    {
        $kiriman = $retur->order;
        $pengirim = $retur->keCabang;
        $nilai = (float) $retur->nilai;

        $order = Order::create([
            'cabang_id'         => $pengirim->id,
            'nomor_order'       => $this->penjualanService->generateNomorOrder($pengirim),
            'tanggal_order'     => today(),
            'tipe_order'        => TipeOrder::Penjualan,
            'tipe_transaksi'    => TipeTransaksi::Takeaway,
            'customer_type'     => 'internal',
            'outlet_tujuan_id'  => $retur->dari_cabang_id,
            'retur_internal_id' => $retur->id,
            'nama_pelanggan'    => 'Retur ' . $retur->kode_retur . ' — ' . $retur->dariCabang?->nama_cabang,
            'total_harga'       => -$nilai,
            'diskon'            => 0,
            'service_charge'    => 0,
            'take_away_fee'     => 0,
            'total_bayar'       => -$nilai,
            'jumlah_bayar'      => -$nilai,
            'kembalian'         => 0,
            'tipe_pembayaran'   => TipePembayaran::Tunai,
            'kas_id'            => $kasId,
            'status'            => StatusOrder::Selesai,
            'catatan'           => "Koreksi otomatis retur {$retur->kode_retur} dari kiriman {$kiriman->nomor_order} ({$retur->alasan_label})",
            'kasir_id'          => auth()->id(),
            'tampil_di_antrian' => false,
        ]);

        foreach ($retur->items as $it) {
            $r = $it->kirimanItem;
            $oi = $r?->orderItem;
            $q = (float) $it->qty;
            // Retur dihitung dalam satuan barang yang diterima; ubah ke satuan Produk Jual kiriman
            $qtyProduk = ($oi && (float) $r->qty_terima > 0) ? round($q * (float) $oi->qty / (float) $r->qty_terima, 3) : $q;
            OrderItem::create([
                'order_id'     => $order->id,
                'item_id'      => $oi?->item_id ?? $r?->item_id,
                'nama_item'    => ($oi?->nama_item ?? $it->item?->nama_item ?? 'Retur') . ' (retur)',
                'qty'          => -$qtyProduk,
                'satuan'       => $oi?->satuan ?? 'pcs',
                'harga_satuan' => $qtyProduk > 0 ? round($it->nilai / $qtyProduk, 2) : 0,
                'total_harga'  => -$it->nilai,
                'hpp'          => -round($q * (float) $it->hpp_per_unit, 2),
                'catatan'      => 'Retur ' . $retur->kode_retur,
            ]);
        }

        return $order;
    }

    private function nomorBerikutnya(): string
    {
        $prefix = 'RTR-' . now()->format('Ym') . '-';
        $last = ReturInternal::where('kode_retur', 'like', $prefix . '%')->lockForUpdate()->max('kode_retur');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** Retur menunggu diterima oleh Gudang di cabang aktif (badge sidebar). */
    public static function jumlahMenunggu(): int
    {
        // Aman sebelum migration retur dijalankan (badge sidebar tampil di semua halaman)
        static $siap = null;
        if (! ($siap ??= Schema::hasTable('retur_internals'))) {
            return 0;
        }
        $cabangId = CabangAktif::untukLaporan();

        return ReturInternal::where('status', 'menunggu')->when($cabangId, fn ($q) => $q->where('ke_cabang_id', $cabangId))->count();
    }
}
