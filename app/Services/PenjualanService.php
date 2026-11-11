<?php

namespace App\Services;

use App\Enums\KategoriTransaksi;
use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Enums\TipePembayaran;
use App\Enums\TipeStockMovement;
use App\Enums\TipeTransaksi;
use App\Enums\TipeTransaksiKeuangan;
use App\Events\AntrianCreated;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Kas;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\StockMovement;
use App\Models\TransaksiKeuangan;
use Illuminate\Support\Facades\DB;

class PenjualanService
{
    /**
     * 4.49 Tahap B2 — rincian stok yang dipotong oleh potongStokUntukItem()
     * terakhir: [[item_id, qty, hpp], ...] — dipakai Kirim ke Cabang (order_kiriman_items).
     */
    private array $rincianPotong = [];

    public function __construct(private StokService $stokService) {}

    /**
     * Tahap 3 D'mentai — "Charge langsung": buat order BARU dan langsung
     * finalisasi (stok+kas) dalam 1 pemanggilan, dipakai kalau kasir tidak
     * lewat Save Bill dulu. Secara internal cuma gabungan simpanBill()+
     * chargeBill() supaya logic stok/kas/pembayaran cuma ada SATU sumber
     * (tidak diduplikasi antara 2 alur).
     */
    public function buatOrder(array $data, int $cabangId): Order
    {
        $order = DB::transaction(function () use ($data, $cabangId) {
            $order = $this->simpanBillInternal($data, $cabangId, StatusOrder::Pending);

            return $this->chargeBillInternal($order, $data['payments'], $data);
        });

        if ($order->nomor_antrian !== null) {
            AntrianCreated::dispatch($order->load('items'));
        }

        return $order;
    }

    /**
     * Tahap 3 D'mentai — Save Bill: simpan keranjang sebagai bill (status
     * Pending), TIDAK menyentuh stok/kas sama sekali. Bisa dilanjut bayar
     * kapan saja lewat chargeBill().
     */
    public function simpanBill(array $data, int $cabangId): Order
    {
        return DB::transaction(fn () => $this->simpanBillInternal($data, $cabangId, StatusOrder::Pending));
    }

    /**
     * Tahap 3 D'mentai — finalisasi bill yang sudah tersimpan (dari
     * simpanBill() atau dari buatOrder() secara internal): cek+potong stok,
     * proses split payment, catat kas, set status Selesai.
     */
    public function chargeBill(Order $order, array $paymentData): Order
    {
        if ($order->status !== StatusOrder::Pending) {
            throw new \Exception('Order ini sudah diproses sebelumnya (bukan lagi berstatus Pending).');
        }

        $order = DB::transaction(fn () => $this->chargeBillInternal($order, $paymentData['payments'], $paymentData));

        if ($order->nomor_antrian !== null) {
            AntrianCreated::dispatch($order->load('items'));
        }

        return $order;
    }

    // =========================================================================
    // INTERNAL — dipanggil dari dalam DB::transaction() milik caller
    // =========================================================================

    private function simpanBillInternal(array $data, int $cabangId, StatusOrder $status): Order
    {
        $cabang = Cabang::findOrFail($cabangId);
        // 4.49 Tahap B2 — Kirim ke Cabang: penjualan Gudang ke cabang sendiri. Bukan
        // transaksi pelanggan → tanpa meja, antrian, service charge, take away fee.
        $isKirim = ($data['customer_type'] ?? null) === 'internal';
        if ($isKirim) {
            $this->validasiKirimCabang($cabang, $data);
            $data['tipe_transaksi'] = TipeTransaksi::Takeaway->value;
            $data['tampil_di_antrian'] = false;
            $data['nomor_meja'] = null;
        }
        $tipeTransaksi = TipeTransaksi::from($data['tipe_transaksi']);

        if (! $isKirim && ! $cabang->tipeTransaksiAktif($tipeTransaksi)) {
            throw new \Exception("Tipe transaksi \"{$tipeTransaksi->label()}\" tidak aktif untuk outlet ini.");
        }

        if ($tipeTransaksi === TipeTransaksi::DineIn && $cabang->nomor_meja_aktif && empty($data['nomor_meja'])) {
            throw new \Exception('Nomor meja wajib diisi untuk transaksi Dine-in di outlet ini.');
        }

        [$itemRows, $totalHarga] = $this->resolveItemRows($data['items'], (int) $cabang->id);

        $diskon = (float) ($data['diskon'] ?? 0);
        $subtotalSetelahDiskon = max(0, $totalHarga - $diskon);

        $serviceCharge = $isKirim ? 0.0 : round($subtotalSetelahDiskon * ((float) $cabang->service_charge_persen / 100), 2);
        $takeAwayFee = (! $isKirim && $tipeTransaksi === TipeTransaksi::Takeaway) ? (float) $cabang->take_away_fee : 0.0;
        $totalBayar = $subtotalSetelahDiskon + $serviceCharge + $takeAwayFee;

        // Validasi stok SEMUA item dulu sebelum buat record apapun (Approach
        // B, dipertahankan dari alur lama) — resep-aware: item dengan resep
        // dicek per-bahan, tanpa resep dicek langsung stok item itu sendiri.
        $this->cekStokSemuaItem($itemRows, $cabangId);

        $today = today();
        $nomorAntrian = null;
        $statusProduksi = null;
        $tampilDiAntrian = array_key_exists('tampil_di_antrian', $data)
            ? (bool) $data['tampil_di_antrian']
            : true;

        // Frozen sengaja TIDAK masuk antrian produksi (siap saji instan,
        // beda dari dine-in/takeaway yang perlu disiapkan dulu).
        if ($cabang->antrian_produksi_aktif && $tampilDiAntrian && $tipeTransaksi !== TipeTransaksi::Frozen) {
            $nomorAntrian = Order::generateNomorAntrian($cabangId, $today);
            $statusProduksi = 'menunggu';
        }

        $order = Order::create([
            'cabang_id'         => $cabangId,
            'nomor_order'       => $this->generateNomorOrder($cabang),
            'tanggal_order'     => $today,
            'tipe_order'        => TipeOrder::Penjualan,
            'tipe_transaksi'    => $tipeTransaksi,
            'nomor_meja'        => $tipeTransaksi === TipeTransaksi::DineIn ? ($data['nomor_meja'] ?? null) : null,
            'tanggal_expired_frozen' => $tipeTransaksi === TipeTransaksi::Frozen ? ($data['tanggal_expired_frozen'] ?? null) : null,
            'pelanggan_id'      => $data['pelanggan_id'] ?? null,
            'nama_pelanggan'    => $data['nama_pelanggan'] ?? null,
            'telepon_pelanggan' => $data['telepon_pelanggan'] ?? null,
            'total_harga'       => $totalHarga,
            'diskon'            => $diskon,
            'service_charge'    => $serviceCharge,
            'take_away_fee'     => $takeAwayFee,
            'total_bayar'       => $totalBayar,
            'jumlah_bayar'      => 0,
            'kembalian'         => 0,
            'tipe_pembayaran'   => TipePembayaran::Tunai, // placeholder, diisi ulang saat charge
            'status'            => $status,
            'catatan'           => $data['catatan'] ?? null,
            'kasir_id'          => auth()->id(),
            'bukti_pembayaran'  => $data['bukti_pembayaran'] ?? null,
            'nomor_antrian'     => $nomorAntrian,
            'status_produksi'   => $statusProduksi,
            'tampil_di_antrian' => $tampilDiAntrian,
            'parent_order_id'   => $data['parent_order_id'] ?? null,
            'purchase_order_id' => $isKirim ? null : $this->poInternalValid($data['purchase_order_id'] ?? null, $cabangId),
        ] + ($isKirim ? [
            // Kolom kiriman hanya ditulis untuk kiriman — penjualan biasa tetap jalan walau
            // program baru sudah terpasang dan migration 4.49 belum dijalankan.
            'customer_type'    => 'internal',
            'outlet_tujuan_id' => (int) $data['outlet_tujuan_id'],
        ] : []));

        foreach ($itemRows as $row) {
            OrderItem::create([
                'order_id'        => $order->id,
                'item_id'         => $row['item_id'],
                'item_variant_id' => $row['item_variant_id'],
                'nama_item'       => $row['nama_item'],
                'qty'             => $row['qty'],
                'satuan'          => $row['satuan'],
                'harga_satuan'    => $row['harga_satuan'],
                'total_harga'     => $row['total_harga'],
                'hpp'             => 0, // diisi saat charge (stok baru dipotong saat itu)
                'berat_daging'    => $row['berat_daging'] ?? null,
                'jenis_olahan'    => $row['jenis_olahan'] ?? null,
                'catatan'         => $row['catatan'] ?? null,
            ]);
        }

        return $order;
    }

    /**
     * Tahap C 4.38 — tautan nota ke PO cabang hanya disimpan kalau PO itu
     * memang ditujukan ke supplier internal lokasi ini (cegah salah tautan).
     */
    private function poInternalValid($poId, int $cabangId): ?int
    {
        if (! $poId) {
            return null;
        }
        $po = \App\Models\PurchaseOrder::with('supplier:id,cabang_id')->find((int) $poId);

        return $po && (int) $po->supplier?->cabang_id === $cabangId ? $po->id : null;
    }

    /**
     * Tahap B 4.38 — bukti pembayaran wajib untuk Transfer/QRIS kalau saklar
     * cabang aktif (default aktif). Berlaku juga utk split payment: cukup
     * ada 1 bagian Transfer/QRIS yang wajib, maka bukti wajib.
     */
    private function pastikanBuktiPembayaran(?Cabang $cabang, array $payments, ?string $bukti): void
    {
        if (! $cabang || ! empty($bukti)) {
            return;
        }

        $metode = collect($payments)->map(fn ($p) => is_object($p['metode'] ?? null) ? $p['metode']->value : (string) ($p['metode'] ?? ''));
        $wajib = [];
        if ($metode->contains('transfer') && ($cabang->wajib_bukti_transfer ?? true)) {
            $wajib[] = 'Transfer';
        }
        if ($metode->contains('qris') && ($cabang->wajib_bukti_qris ?? true)) {
            $wajib[] = 'QRIS';
        }

        if ($wajib) {
            throw new \Exception('Bukti pembayaran wajib diupload untuk metode ' . implode(' / ', $wajib) . ' di outlet ini.');
        }
    }

    private function chargeBillInternal(Order $order, array $payments, array $data): Order
    {
        $cabangId = $order->cabang_id;
        $this->pastikanBuktiPembayaran(Cabang::find($cabangId), $payments, $order->bukti_pembayaran);
        $items = $order->items()->get();

        // Cek ulang stok tepat sebelum potong — bisa saja berubah sejak
        // Save Bill dibuat (order lain menghabiskan stok duluan).
        $itemRowsUntukCek = $items->map(fn ($oi) => [
            'item_id' => $oi->item_id,
            'qty'     => (float) $oi->qty,
        ])->all();
        $this->cekStokSemuaItem($itemRowsUntukCek, $cabangId);

        $isKirim = $order->customer_type === 'internal';
        if ($isKirim) {
            // Cek ulang aturan kirim tepat sebelum stok keluar
            $this->validasiKirimCabang(Cabang::findOrFail($cabangId), [
                'outlet_tujuan_id' => $order->outlet_tujuan_id,
                'items' => $items->map(fn ($oi) => ['item_id' => $oi->item_id, 'nama_item' => $oi->nama_item])->all(),
            ]);
        }

        foreach ($items as $orderItem) {
            if (empty($orderItem->item_id)) continue;

            $masterItem = Item::find($orderItem->item_id);
            if (! $masterItem) continue;

            $hpp = $this->potongStokUntukItem($masterItem, (float) $orderItem->qty, $cabangId, $order);
            $orderItem->update(['hpp' => $hpp]);

            if ($isKirim) {
                if (empty($this->rincianPotong)) {
                    throw new \Exception("'{$masterItem->nama_item}' tidak memotong stok apa pun (belum punya komposisi) — Produk Jual yang dikirim ke cabang wajib punya komposisi barang.");
                }
                foreach ($this->rincianPotong as $r) {
                    $order->kirimanItems()->create([
                        'order_item_id' => $orderItem->id,
                        'item_id'       => $r['item_id'],
                        'qty_kirim'     => $r['qty'],
                        'hpp_kirim'     => round($r['hpp'], 2),
                    ]);
                }
            }
        }

        if ($isKirim) {
            // 4.49 Tahap B2 — stok sudah keluar dari gudang. Uang, pendapatan, dan stok masuk di
            // cabang baru terjadi saat cabang menekan Terima (terimaKiriman). Status Proses =
            // tidak dihitung di laporan mana pun (StatusOrder::bukanPenjualan).
            $order->update([
                'jumlah_bayar'    => 0,
                'kembalian'       => 0,
                'tipe_pembayaran' => TipePembayaran::Tunai,
                'status'          => StatusOrder::Proses,
                'status_kirim'    => 'menunggu',
            ]);

            return $order->fresh(['items', 'payments']);
        }

        $totalPayments = array_sum(array_map(fn ($p) => (float) $p['jumlah'], $payments));
        if ($totalPayments < (float) $order->total_bayar) {
            $kurang = number_format((float) $order->total_bayar - $totalPayments, 0, ',', '.');
            throw new \Exception("Jumlah bayar kurang Rp {$kurang}.");
        }

        [$kasIdUtama, $tipePembayaranSummary, $totalDibayar] = $this->prosesPembayaran($order, $payments, $cabangId);

        $kembalian = max(0, $totalDibayar - (float) $order->total_bayar);

        $order->update([
            'jumlah_bayar'    => $totalDibayar,
            'kembalian'       => $kembalian,
            'kas_id'          => $kasIdUtama,
            'tipe_pembayaran' => $tipePembayaranSummary,
            'status'          => StatusOrder::Selesai,
        ]);

        return $order->fresh(['items', 'payments']);
    }

    /**
     * Resolve baris item mentah dari request jadi array siap-simpan: harga
     * varian di-override server-side (defense-in-depth, cegah manipulasi
     * harga dari client), subtotal dihitung, total keseluruhan diakumulasi.
     *
     * @return array{0: array, 1: float} [$itemRows, $totalHarga]
     */
    private function resolveItemRows(array $items, ?int $cabangId = null): array
    {
        $totalHarga = 0.0;
        $itemRows = [];
        // 4.47 — tanpa izin "Ubah Harga Jual di POS" harga dari form diabaikan,
        // selalu pakai harga produk di cabang ini (Owner lolos lewat Gate::before).
        $bolehUbahHarga = (bool) auth()->user()?->can('order.ubah_harga');

        foreach ($items as $row) {
            $masterItem = ! empty($row['item_id']) ? Item::find((int) $row['item_id']) : null;
            $variant = ! empty($row['item_variant_id']) ? ItemVariant::find((int) $row['item_variant_id']) : null;

            // 4.39 — barang milik cabang lain tidak boleh dijual di cabang ini
            if ($masterItem && $cabangId && $masterItem->cabang_id !== null && (int) $masterItem->cabang_id !== (int) $cabangId) {
                throw new \Exception("Item '{$masterItem->nama_item}' bukan milik cabang ini. Muat ulang halaman POS.");
            }

            $hargaSatuan = (float) $row['harga_satuan'];
            if ($masterItem && ! $bolehUbahHarga) {
                $hargaSatuan = $cabangId ? $masterItem->hargaEfektifDiCabang($cabangId) : (float) ($masterItem->harga_jual ?? 0);
            }
            if ($variant && $variant->item_id === $masterItem?->id && ! $bolehUbahHarga) {
                // Harga varian ikut harga outlet bila varian tanpa harga khusus (4.38)
                $hargaSatuan = $variant->hargaEfektifDiCabang($cabangId);
            }

            // Defense-in-depth satuan (dipertahankan dari alur lama) — satuan
            // SELALU ikut master item kalau item_id ada.
            if ($masterItem) {
                $masterSatuanLower = strtolower(trim($masterItem->satuan));
                $submittedSatuanLower = strtolower(trim($row['satuan'] ?? ''));
                $isMasterBeratBased = $masterSatuanLower === 'kg';
                $submittedKlaimBerat = in_array($submittedSatuanLower, ['kg', 'ons', 'gram'], true);

                if (! $isMasterBeratBased && $submittedKlaimBerat) {
                    throw new \Exception(
                        "Item '{$masterItem->nama_item}' satuannya {$masterItem->satuan} (bukan satuan berat), " .
                        'tapi dikirim dengan satuan berat (' . ($row['satuan'] ?? '?') . '). ' .
                        'Muat ulang halaman POS dan input ulang baris ini.'
                    );
                }
            }

            $qty = (float) $row['qty'];
            $subtotal = $qty * $hargaSatuan;
            $totalHarga += $subtotal;

            $itemRows[] = [
                'item_id'         => $row['item_id'] ?? null,
                'item_variant_id' => $variant?->id,
                'nama_item'       => $row['nama_item'],
                'qty'             => $qty,
                'satuan'          => $masterItem->satuan ?? ($row['satuan'] ?? 'pcs'),
                'harga_satuan'    => $hargaSatuan,
                'total_harga'     => $subtotal,
                'berat_daging'    => $row['berat_daging'] ?? null,
                'jenis_olahan'    => $row['jenis_olahan'] ?? null,
                'catatan'         => $row['catatan'] ?? null,
            ];
        }

        return [$itemRows, $totalHarga];
    }

    /**
     * Cek stok cukup untuk SEMUA baris item, resep-aware:
     *  - Item PUNYA resep produksi -> cek tiap bahan baku komposisinya.
     *  - Item TIDAK punya resep -> cek stok item itu sendiri (kalau
     *    tipe-nya memang dipotong stoknya: bahan_baku/kemasan/produk_jual).
     * Dipanggil SEBELUM ada write apapun ke DB (Approach B).
     */
    private function cekStokSemuaItem(array $itemRows, int $cabangId): void
    {
        foreach ($itemRows as $row) {
            if (empty($row['item_id'])) continue;

            $masterItem = Item::find((int) $row['item_id']);
            if (! $masterItem) continue;

            $qty = (float) $row['qty'];

            if ($masterItem->resep) {
                $this->cekResepCukup($masterItem, $qty, $cabangId);
                continue;
            }

            // Tahap 2.5 D'mentai — 'produk_jadi' -> 'produk_jual' (1:1 rename).
            if (! in_array($masterItem->tipe, ['bahan_baku', 'kemasan', 'produk_jual'], true)) continue;

            $stokTersedia = $this->stokService->getStok($masterItem->id, $cabangId);
            if ($stokTersedia < $qty) {
                throw new \Exception(
                    "Stok '{$masterItem->nama_item}' tidak mencukupi. " .
                    "Tersedia: {$stokTersedia} {$masterItem->satuan}, dibutuhkan: {$qty} {$masterItem->satuan}."
                );
            }
        }
    }

    /** Cek stok tiap bahan baku komposisi resep 1 item, throw pesan spesifik kalau ada yang kurang. */
    /**
     * Cek stok cukup, resep-aware. Baris resep "linked" (Import dari Bumbu
     * Pusat, lihat ResepBumbuItem::expandKeBahanMentah()) di-expand jadi
     * kebutuhan bahan MENTAH-nya sebelum dicek — bumbu sendiri tidak punya
     * stok, yang genuinely dipotong adalah bahan di dalamnya.
     */
    private function cekResepCukup(Item $item, float $qty, int $cabangId): void
    {
        $resep = $item->resep;
        if (! $resep) return;

        // Akumulasi per item_id dulu (2 baris resep bisa kebetulan pakai
        // bahan mentah yang sama, mis. langsung + lewat 2 bumbu berbeda).
        $kebutuhan = [];
        foreach ($resep->items as $resepItem) {
            foreach ($resepItem->expandKeBahanMentah() as $bahanMentah) {
                $kebutuhan[$bahanMentah['item_id']] = ($kebutuhan[$bahanMentah['item_id']] ?? 0) + $bahanMentah['butuh_kg'] * $qty;
            }
        }

        foreach ($kebutuhan as $itemId => $butuh) {
            $bahan = Item::find($itemId);
            if (! $bahan) continue;

            $tersedia = $this->stokService->getStok($itemId, $cabangId);
            if ($tersedia < $butuh) {
                throw new \Exception(
                    "Stok '{$bahan->nama_item}' tidak mencukupi untuk memproses '{$item->nama_item}'. " .
                    "Tersedia: {$tersedia} {$bahan->satuan}, dibutuhkan: " . round($butuh, 3) . " {$bahan->satuan}."
                );
            }
        }
    }

    /**
     * Potong stok utk 1 item (resep-aware) dan kembalikan HPP-nya.
     *  - Punya resep -> potong tiap bahan baku, HPP = jumlah HPP semua bahan
     *    (representasi COGS yang lebih akurat daripada harga item tunggal).
     *  - Tidak punya resep, tipe dipotong stok -> potong item itu sendiri.
     *  - Selain itu (mis. Item Tambahan tanpa resep) -> tidak potong stok, HPP 0.
     */
    private function potongStokUntukItem(Item $item, float $qty, int $cabangId, Order $order): float
    {
        $this->rincianPotong = [];

        if ($item->resep) {
            // Sama seperti cekResepCukup(): akumulasi per item_id dulu
            // (expand baris linked ke bahan mentahnya) supaya bahan yang
            // kebetulan dipakai 2x (langsung + lewat bumbu) cuma 1x panggilan
            // StokService::keluar() per item -- movement/HPP FIFO tetap benar.
            $kebutuhan = [];
            foreach ($item->resep->items as $resepItem) {
                foreach ($resepItem->expandKeBahanMentah() as $bahanMentah) {
                    $kebutuhan[$bahanMentah['item_id']] = ($kebutuhan[$bahanMentah['item_id']] ?? 0) + $bahanMentah['butuh_kg'] * $qty;
                }
            }

            $totalHpp = 0.0;
            foreach ($kebutuhan as $itemId => $butuh) {
                if ($butuh <= 0) continue;

                $hpp = $this->stokService->keluar(
                    $itemId,
                    $cabangId,
                    $butuh,
                    "Komposisi resep '{$item->nama_item}' — Order {$order->nomor_order}",
                    'order',
                    $order->id
                );
                $totalHpp += $hpp;
                $this->rincianPotong[] = ['item_id' => (int) $itemId, 'qty' => round($butuh, 3), 'hpp' => $hpp];
            }

            return $totalHpp;
        }

        // Tahap 2.5 D'mentai — 'produk_jadi' -> 'produk_jual' (1:1 rename).
        if (in_array($item->tipe, ['bahan_baku', 'kemasan', 'produk_jual'], true)) {
            $hpp = $this->stokService->keluar(
                $item->id,
                $cabangId,
                $qty,
                'Penjualan Order ' . $order->nomor_order,
                'order',
                $order->id
            );
            $this->rincianPotong[] = ['item_id' => $item->id, 'qty' => round($qty, 3), 'hpp' => $hpp];

            return $hpp;
        }

        return 0.0;
    }

    /**
     * Split Payment: proses N metode bayar untuk 1 order. Tiap metode
     * resolve ke Kas kategori masing-masing (TipePembayaran::kasKategori()),
     * bikin 1 OrderPayment + 1 TransaksiKeuangan per baris, kas
     * ter-increment sesuai bagiannya.
     *
     * @return array{0: ?int, 1: TipePembayaran, 2: float} [$kasIdUtama, $tipePembayaranSummary, $totalDibayar]
     */
    private function prosesPembayaran(Order $order, array $payments, int $cabangId): array
    {
        $kasIdUtama = null;
        $tipePembayaranSummary = null;
        $totalDibayar = 0.0;
        $isSplit = count($payments) > 1;

        // Kembalian keluar dari laci (tunai), jadi kas/transaksi/order_payments
        // hanya mencatat uang yang BENAR-BENAR tinggal (diterima - kembalian).
        // orders.jumlah_bayar tetap uang yang diserahkan pelanggan (utk struk).
        $sisaKembalian = max(0.0, array_sum(array_map(fn ($p) => (float) $p['jumlah'], $payments)) - (float) $order->total_bayar);

        foreach ($payments as $index => $payment) {
            $metode = TipePembayaran::from($payment['metode']);
            $diserahkan = (float) $payment['jumlah'];
            $totalDibayar += $diserahkan;

            $jumlah = $diserahkan;
            if ($metode === TipePembayaran::Tunai && $sisaKembalian > 0) {
                $potong = min($sisaKembalian, $diserahkan);
                $jumlah = $diserahkan - $potong;
                $sisaKembalian -= $potong;
            }

            $kas = Kas::where('cabang_id', $cabangId)
                ->where('default_untuk', $metode->kasKategori())
                ->where('is_active', true)
                ->first();

            if (! $kas) {
                $kategoriLabel = TipePembayaran::tryFrom($metode->kasKategori())?->label() ?? $metode->kasKategori();
                throw new \Exception(
                    "Cabang belum punya Kas default untuk pembayaran {$kategoriLabel}, hubungi Admin/Owner untuk membuat atau mengaktifkan Kas tersebut."
                );
            }

            if ($index === 0) {
                $kasIdUtama = $kas->id;
                $tipePembayaranSummary = $metode;
            }

            if ($jumlah <= 0) {
                continue;
            }

            OrderPayment::create([
                'order_id'   => $order->id,
                'metode'     => $metode,
                'jumlah'     => $jumlah,
                'keterangan' => $isSplit ? 'Split payment' : null,
            ]);

            $kas->increment('saldo_sekarang', $jumlah);

            TransaksiKeuangan::create([
                'cabang_id'         => $cabangId,
                'kas_id'            => $kas->id,
                'nomor_transaksi'   => $this->generateNomorTransaksi($order->nomor_order) . ($isSplit ? '-' . ($index + 1) : ''),
                'tanggal_transaksi' => today(),
                'tipe'              => TipeTransaksiKeuangan::Pemasukan,
                'kategori'          => KategoriTransaksi::Penjualan,
                'keterangan'        => 'Order ' . $order->nomor_order . ($order->nama_pelanggan ? ' — ' . $order->nama_pelanggan : '') . ($isSplit ? ' (' . $metode->label() . ')' : ''),
                'jumlah'            => $jumlah,
                'referensi_type'    => 'order',
                'referensi_id'      => $order->id,
                'created_by'        => auth()->id(),
            ]);
        }

        return [$kasIdUtama, $tipePembayaranSummary, $totalDibayar];
    }

    public function batalkan(Order $order): void
    {
        if ($order->isKoreksiRetur()) {
            throw new \Exception('Order ini koreksi otomatis dari Retur cabang, jadi tidak bisa dibatalkan.');
        }
        // 4.49 Tahap B2 — Kirim ke Cabang: belum diterima → batal = stok kembali ke gudang
        // dengan modal semula. Sudah diterima → stok sudah di cabang & uang sudah pindah: pakai Retur.
        if ($order->isKirimanCabang()) {
            if ($order->status === StatusOrder::Proses) {
                $this->batalkanKiriman($order, 'dibatalkan', null);

                return;
            }
            if ($order->status === StatusOrder::Selesai) {
                throw new \Exception('Kiriman ini sudah diterima cabang tujuan, jadi tidak bisa dibatalkan. Gunakan Retur dari cabang tujuan.');
            }
        }

        DB::transaction(function () use ($order) {
            if ($order->status === StatusOrder::Selesai) {
                // Gunakan StockMovement sebagai source of truth:
                // hanya kembalikan stok yang BENAR-BENAR pernah dikurangi saat order dibuat.
                // Ini mencegah stok dikembalikan 2x (jika batalkan dipanggil ulang)
                // dan mencegah stok dikembalikan untuk item jasa_giling yang tidak pernah dikurangi.
                $keluarMovements = StockMovement::where('referensi_type', 'order')
                    ->where('referensi_id', $order->id)
                    ->where('tipe', TipeStockMovement::Keluar->value)
                    ->orderBy('id')
                    ->get();

                // Antrian order_items per item_id (urut id) — dipasangkan 1:1 ke
                // movement per posisi kemunculan, supaya benar walau 1 order
                // punya item_id yang sama berulang di baris berbeda.
                $orderItemQueue = OrderItem::where('order_id', $order->id)
                    ->whereNotNull('item_id')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('item_id')
                    ->map(fn ($group) => $group->values());
                $orderItemCursor = [];

                foreach ($keluarMovements as $movement) {
                    $lokasiId = $movement->lokasi_asal_id ?? $order->cabang_id;
                    if (!$lokasiId) continue;

                    // stock_movements TIDAK menyimpan batch_id yang dikonsumsi
                    // (relasi cuma via item_id+lokasi_id, lihat Rule bisnis #34),
                    // jadi tidak mungkin restore ke batch ASAL yang persis sama.
                    // Solusi: bikin 1 batch BARU per item, senilai HPP yang
                    // BENAR-BENAR tercatat di order_item saat penjualan asli
                    // (order_items.hpp / order_items.qty = harga rata-rata per
                    // unit) — ini akurat ke nilai (bukan cuma qty) TANPA perlu
                    // tahu batch mana persisnya, dan otomatis benar juga untuk
                    // kasus original sale sempat kena fallback harga_beli_terakhir
                    // di consumeBatchesFifo() (sudah ke-blend di hpp tersimpan).
                    //
                    // CATATAN Tahap 3: dengan resep-aware deduction, movement
                    // 'keluar' sekarang tercatat per BAHAN BAKU (bukan per
                    // item jadi) kalau item itu punya resep — pairing ke
                    // order_items di bawah tetap valid karena kita pairing
                    // by item_id persis yang tercatat di movement itu sendiri
                    // (bisa jadi item bahan baku, bukan item jadi yang dibeli
                    // pelanggan) — qty/hpp movement tetap dikembalikan benar
                    // per bahan, terlepas dari order_items mana asalnya.
                    $itemIdKey = $movement->item_id;
                    $cursorIdx = $orderItemCursor[$itemIdKey] ?? 0;
                    $orderItem = $orderItemQueue->get($itemIdKey)?->get($cursorIdx);
                    $orderItemCursor[$itemIdKey] = $cursorIdx + 1;

                    $hargaBeli = ($orderItem && (float) $orderItem->qty > 0)
                        ? (float) $orderItem->hpp / (float) $orderItem->qty
                        : (float) (Item::find($movement->item_id)?->harga_beli_terakhir ?? 0);

                    $this->stokService->masuk(
                        $movement->item_id,
                        $lokasiId,
                        (float) $movement->qty,
                        'Pembatalan Order ' . $order->nomor_order,
                        'order',
                        $order->id,
                        $hargaBeli
                    );
                }

                // Balikkan saldo kas utk SEMUA payment (split payment aware)
                // sebelum hapus catatannya.
                $trxList = TransaksiKeuangan::where('referensi_type', 'order')
                    ->where('referensi_id', $order->id)
                    ->get();
                foreach ($trxList as $trxKeuangan) {
                    if ($trxKeuangan->kas_id) {
                        Kas::where('id', $trxKeuangan->kas_id)
                            ->decrement('saldo_sekarang', (float) $trxKeuangan->jumlah);
                    }
                }

                // Hapus catatan keuangan terkait order ini
                TransaksiKeuangan::where('referensi_type', 'order')
                    ->where('referensi_id', $order->id)
                    ->delete();
            }
            $order->update(['status' => StatusOrder::Dibatalkan]);
        });
    }

    public function kembalikan(Order $order): void
    {
        if ($order->status !== StatusOrder::Dibatalkan) {
            throw new \Exception('Hanya order dengan status Dibatalkan yang bisa dikembalikan.');
        }
        if ($order->customer_type === 'internal') {
            throw new \Exception('Kiriman ke cabang yang sudah dibatalkan/ditolak tidak bisa dikembalikan. Buat kiriman baru dari POS Gudang.');
        }

        DB::transaction(function () use ($order) {
            // LANGKAH 1: Pre-validasi stok sebelum ubah apapun
            $pembatalanMovements = StockMovement::where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->where('tipe', TipeStockMovement::Masuk->value)
                ->where('catatan', 'like', 'Pembatalan Order %')
                ->get();

            foreach ($pembatalanMovements as $m) {
                $lokasiId = $m->lokasi_tujuan_id ?? $order->cabang_id;
                $stokAda  = $this->stokService->getStok($m->item_id, $lokasiId);
                if ($stokAda < (float) $m->qty) {
                    $nama = Item::find($m->item_id)?->nama_item ?? "item #{$m->item_id}";
                    throw new \Exception(
                        "Stok '{$nama}' tidak cukup untuk kembalikan order. " .
                        "Tersedia: {$stokAda}, dibutuhkan: {$m->qty}. " .
                        "Stok mungkin sudah dipakai order lain setelah pembatalan."
                    );
                }
            }

            // LANGKAH 2: Kurangi stok kembali (undo pembatalan movements)
            foreach ($pembatalanMovements as $m) {
                $lokasiId = $m->lokasi_tujuan_id ?? $order->cabang_id;
                $this->stokService->keluar(
                    $m->item_id, $lokasiId, (float) $m->qty,
                    'Kembalikan Order ' . $order->nomor_order, 'order', $order->id
                );
            }

            // LANGKAH 3: Restore SEMUA TransaksiKeuangan (split payment aware)
            // + kembalikan saldo kas masing-masing.
            // withoutGlobalScopes() wajib: withTrashed() saja tidak cukup karena CabangScope
            // tetap aktif dan bisa memfilter record berdasarkan session('active_cabang_id').
            // Defensive: tambah cabang_id dari $order (bukan dari session) sebagai filter eksplisit.
            $trxList = TransaksiKeuangan::withoutGlobalScopes()
                ->where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->where('cabang_id', $order->cabang_id)
                ->whereNotNull('deleted_at')
                ->get();

            foreach ($trxList as $trxKeuangan) {
                if ($trxKeuangan->kas_id) {
                    Kas::withoutGlobalScopes()
                        ->where('id', $trxKeuangan->kas_id)
                        ->increment('saldo_sekarang', (float) $trxKeuangan->jumlah);
                }
                $trxKeuangan->restore();
            }

            // LANGKAH 4: Update status order
            $order->update(['status' => StatusOrder::Selesai]);
        });
    }

    // =========================================================================
    // 4.49 Tahap B2 — KIRIM KE CABANG (porting erp-coffeeshop 40bd55e)
    // Gudang Pusat menjual Produk Jual ke cabang sendiri: stok keluar saat Kirim,
    // cabang menekan Terima → stok masuk cabang + uang pindah + pendapatan gudang.
    // Dimsum: barang masuk ke barang MILIK cabang tujuan (padanan Kode Item, 4.39).
    // =========================================================================

    /** Aturan kirim: penjual Gudang Pusat, tujuan cabang aktif lain, barang dari master, kas tunai gudang ada. */
    private function validasiKirimCabang(Cabang $penjual, array $data): void
    {
        $tipePenjual = $penjual->tipe?->value ?? (string) $penjual->tipe;
        if ($tipePenjual !== 'gudang_pusat') {
            throw new \Exception('Kirim ke Cabang hanya bisa dari cabang bertipe Gudang Pusat.');
        }
        if (! $penjual->kirim_cabang_aktif) {
            throw new \Exception("Kirim ke Cabang belum dinyalakan untuk '{$penjual->nama_cabang}'. Nyalakan di Cabang → Edit → Config POS.");
        }
        if (! empty($data['purchase_order_id'])) {
            throw new \Exception('Keranjang ini berasal dari Pesanan PO cabang — proses sebagai nota biasa (cabang menerima lewat PO), bukan Kirim ke Cabang.');
        }

        $tujuan = ! empty($data['outlet_tujuan_id']) ? Cabang::find((int) $data['outlet_tujuan_id']) : null;
        if (! $tujuan) {
            throw new \Exception('Pilih cabang tujuan kiriman.');
        }
        if ((int) $tujuan->id === (int) $penjual->id) {
            throw new \Exception('Cabang tujuan tidak boleh sama dengan cabang pengirim.');
        }
        if (! $tujuan->is_active || ($tujuan->tipe?->value ?? (string) $tujuan->tipe) !== 'cabang') {
            throw new \Exception("Cabang tujuan '{$tujuan->nama_cabang}' tidak aktif atau bukan cabang penjualan.");
        }

        foreach ($data['items'] ?? [] as $row) {
            if (empty($row['item_id'])) {
                throw new \Exception("Barang '" . ($row['nama_item'] ?? '?') . "' bukan dari master Produk Jual — kiriman ke cabang harus memakai Produk Jual yang terdaftar.");
            }
        }

        $adaKas = Kas::where('cabang_id', $penjual->id)->where('default_untuk', 'tunai')->where('is_active', true)->exists();
        if (! $adaKas) {
            throw new \Exception("'{$penjual->nama_cabang}' belum punya Kas Tunai aktif untuk mencatat pendapatan kiriman. Buat dulu di menu Kas.");
        }
    }

    /** Kas aktif cabang tujuan yang boleh dipakai membayar kiriman (bawaan: Kas Tunai). */
    public static function kasPembayarKiriman(int $cabangTujuanId)
    {
        return Kas::where('cabang_id', $cabangTujuanId)->where('is_active', true)
            ->orderByRaw("default_untuk = 'tunai' DESC")->orderBy('nama_kas')->get();
    }

    /**
     * Cabang tujuan menerima kiriman. $qtyDiterima = [order_item_id => qty yang benar-benar
     * sampai]; kosong = semua sampai. Kekurangan kembali ke stok gudang dengan modal semula,
     * baris order & total disesuaikan dengan yang diterima. $kasId = kas cabang yang membayar
     * (null = Kas Tunai cabang).
     */
    public function terimaKiriman(Order $order, array $qtyDiterima, int $userId, ?int $kasId = null): Order
    {
        return DB::transaction(function () use ($order, $qtyDiterima, $userId, $kasId) {
            $order = Order::with(['items', 'kirimanItems.item', 'cabang', 'outletTujuan'])->lockForUpdate()->findOrFail($order->id);

            if (! $order->isKirimanCabang() || $order->status !== StatusOrder::Proses || $order->status_kirim !== 'menunggu') {
                throw new \Exception('Kiriman ini sudah diproses sebelumnya (diterima, ditolak, atau dibatalkan).');
            }

            $diterima = [];
            foreach ($order->items as $oi) {
                $qty = (float) $oi->qty;
                $isi = $qtyDiterima[$oi->id] ?? null;
                $d = ($isi === null || $isi === '') ? $qty : round((float) $isi, 3);
                if ($d < 0 || $d > $qty + 0.0005) {
                    throw new \Exception("Jumlah diterima '{$oi->nama_item}' harus antara 0 dan " . rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',') . '.');
                }
                $diterima[$oi->id] = min($d, $qty);
            }
            if (array_sum($diterima) <= 0) {
                throw new \Exception('Semua jumlah diterima 0. Kalau barang tidak datang sama sekali, gunakan tombol Tolak.');
            }

            $kasTujuan = self::kasPembayarKiriman((int) $order->outlet_tujuan_id)
                ->first(fn ($k) => $kasId ? (int) $k->id === $kasId : $k->default_untuk === 'tunai');
            if (! $kasTujuan) {
                throw new \Exception($kasId
                    ? 'Kas yang dipilih bukan kas aktif cabang tujuan.'
                    : 'Cabang tujuan belum punya Kas Tunai aktif. Buat dulu di menu Kas, atau pilih kas lain.');
            }

            $padanan = app(PadananBarangService::class);
            $totalHargaLama = (float) $order->total_harga;
            $faktorDiskon = $totalHargaLama > 0 ? max(0, $totalHargaLama - (float) $order->diskon) / $totalHargaLama : 1.0;
            $penjual = $order->cabang;
            $nilaiTotal = 0.0;
            $totalHargaBaru = 0.0;

            foreach ($order->items as $oi) {
                $qty = (float) $oi->qty;
                $d = $diterima[$oi->id];
                $f = $qty > 0 ? $d / $qty : 0.0;
                $nilaiItem = round((float) $oi->harga_satuan * $d * $faktorDiskon, 2);
                $rows = $order->kirimanItems->where('order_item_id', $oi->id)->values();

                // Nilai dibagi ke tiap bahan menurut porsi modal gudang (fallback porsi qty)
                $bobot = $rows->map(fn ($r) => (float) $r->hpp_kirim)->all();
                if (array_sum($bobot) <= 0) {
                    $bobot = $rows->map(fn ($r) => (float) $r->qty_kirim)->all();
                }
                $totalBobot = array_sum($bobot);
                $teralokasi = 0.0;

                foreach ($rows as $i => $r) {
                    $qtyKirim = (float) $r->qty_kirim;
                    $qtyTerima = round($qtyKirim * $f, 3);
                    $kembali = round($qtyKirim - $qtyTerima, 3);
                    $nilai = $i === $rows->count() - 1
                        ? round($nilaiItem - $teralokasi, 2)
                        : ($totalBobot > 0 ? round($nilaiItem * $bobot[$i] / $totalBobot, 2) : 0.0);
                    $teralokasi += $nilai;

                    $itemTujuan = null;
                    if ($qtyTerima > 0) {
                        $itemTujuan = $padanan->untukCabang($r->item, (int) $order->outlet_tujuan_id);
                        $this->stokService->masuk(
                            $itemTujuan->id, $order->outlet_tujuan_id, $qtyTerima,
                            "Terima kiriman {$order->nomor_order} dari {$penjual->nama_cabang}",
                            'kiriman_cabang', $order->id,
                            round($nilai / $qtyTerima, 2)
                        );
                    }
                    if ($kembali > 0) {
                        $this->stokService->masuk(
                            $r->item_id, $order->cabang_id, $kembali,
                            "Kiriman {$order->nomor_order} kurang — kembali ke stok",
                            'kiriman_kurang', $order->id, $r->hpp_per_unit
                        );
                    }
                    $r->update([
                        'item_tujuan_id' => $itemTujuan?->id,
                        'qty_terima'     => $qtyTerima,
                        'nilai_terima'   => $qtyTerima > 0 ? $nilai : 0,
                    ]);
                }

                if (abs($d - $qty) > 0.0005) {
                    $oi->update([
                        'qty'         => $d,
                        'total_harga' => round((float) $oi->harga_satuan * $d, 2),
                        'hpp'         => round((float) $rows->sum(fn ($r) => (float) $r->hpp_kirim) * $f, 2),
                    ]);
                }
                $nilaiTotal += $nilaiItem;
                $totalHargaBaru += round((float) $oi->harga_satuan * $d, 2);
            }

            $nilaiTotal = round($nilaiTotal, 2);
            $order->update([
                'total_harga'   => $totalHargaBaru,
                'diskon'        => max(0, round($totalHargaBaru - $nilaiTotal, 2)),
                'total_bayar'   => $nilaiTotal,
                'tanggal_order' => today(), // pendapatan diakui di tanggal Terima
            ]);

            $kasPenjualId = null;
            if ($nilaiTotal > 0) {
                // Pendapatan gudang: masuk Kas Tunai gudang (pola pembayaran POS biasa)
                [$kasPenjualId] = $this->prosesPembayaran($order->fresh(), [['metode' => 'tunai', 'jumlah' => $nilaiTotal]], $order->cabang_id);

                $kasTujuan->decrement('saldo_sekarang', $nilaiTotal);
                TransaksiKeuangan::create([
                    'cabang_id'         => $order->outlet_tujuan_id,
                    'kas_id'            => $kasTujuan->id,
                    'nomor_transaksi'   => 'TRX-KRM-' . $order->nomor_order,
                    'tanggal_transaksi' => today(),
                    'tipe'              => TipeTransaksiKeuangan::Pengeluaran,
                    'kategori'          => KategoriTransaksi::PembelianBahan,
                    'keterangan'        => "Pembelian dari {$penjual->nama_cabang} — kiriman {$order->nomor_order}",
                    'jumlah'            => $nilaiTotal,
                    'referensi_type'    => 'order_internal',
                    'referensi_id'      => $order->id,
                    'created_by'        => $userId,
                ]);
            }

            $order->update([
                'status'          => StatusOrder::Selesai,
                'status_kirim'    => 'diterima',
                'diterima_at'     => now(),
                'diterima_by'     => $userId,
                'jumlah_bayar'    => $nilaiTotal,
                'kembalian'       => 0,
                'kas_id'          => $kasPenjualId,
                'tipe_pembayaran' => TipePembayaran::Tunai,
            ]);

            return $order->fresh(['items', 'kirimanItems.item', 'kirimanItems.itemTujuan', 'payments']);
        });
    }

    /** Cabang tujuan menolak kiriman: semua barang kembali ke stok gudang, tidak ada uang berpindah. */
    public function tolakKiriman(Order $order, string $alasan, int $userId): Order
    {
        return $this->batalkanKiriman($order, 'ditolak', $alasan, $userId);
    }

    private function batalkanKiriman(Order $order, string $statusKirim, ?string $alasan, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $statusKirim, $alasan, $userId) {
            $order = Order::with('kirimanItems')->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== StatusOrder::Proses || $order->status_kirim !== 'menunggu') {
                throw new \Exception('Kiriman ini sudah diproses sebelumnya (diterima, ditolak, atau dibatalkan).');
            }

            $label = $statusKirim === 'ditolak' ? 'ditolak cabang tujuan' : 'dibatalkan pengirim';
            foreach ($order->kirimanItems as $r) {
                if ((float) $r->qty_kirim <= 0) continue;
                $this->stokService->masuk(
                    $r->item_id, $order->cabang_id, (float) $r->qty_kirim,
                    "Kiriman {$order->nomor_order} {$label} — kembali ke stok",
                    'kiriman_batal', $order->id, $r->hpp_per_unit
                );
            }

            $order->update([
                'status'       => StatusOrder::Dibatalkan,
                'status_kirim' => $statusKirim,
                'alasan_tolak' => $alasan,
                'diterima_at'  => $statusKirim === 'ditolak' ? now() : null,
                'diterima_by'  => $statusKirim === 'ditolak' ? $userId : null,
            ]);

            return $order->fresh();
        });
    }

    public function generateNomorOrder(Cabang $cabang): string
    {
        $tahun = (int) date('Y');

        // Ambil order terakhir cabang ini di tahun ini (urutkan by ID untuk akurasi)
        // withTrashed() wajib: soft-deleted orders masih punya unique constraint
        // di transaksi_keuangans, skip = sequence restart = duplicate entry
        $lastOrder = Order::withTrashed()
            ->where('cabang_id', $cabang->id)
            ->whereYear('created_at', $tahun)
            ->orderByDesc('id')
            ->value('nomor_order');

        $nextSeq = 1;
        if ($lastOrder && preg_match('/(\d+)$/', $lastOrder, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        // Format baru: KODE-YYYY-NNNNN (tanpa prefix INV-)
        return $cabang->kode_cabang . '-' . $tahun . '-' . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
    }

    public function generateNomorTransaksi(string $nomorOrder): string
    {
        return 'TRX-' . $nomorOrder;
    }
}
