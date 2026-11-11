<?php

namespace App\Http\Controllers;

use App\Enums\StatusPurchaseOrder;
use App\Enums\TipeTransaksiKeuangan;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\TransaksiKeuangan;
use App\Notifications\PurchaseOrderNotification;
use App\Services\CascadeDeleteService;
use App\Services\NotificationService;
use App\Services\PoDashboardService;
use App\Services\PurchaseOrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseOrderService $poService) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('pembelian.view'), 403);

        $authUser = auth()->user();
        $query = PurchaseOrder::with(['supplier', 'cabang']);

        // 4.39 — ikut cabang aktif di header (dulu membaca kunci sesi yang salah
        // 'cabang_aktif_id' sehingga selalu jatuh ke cabang default)
        if ($cabangAktifId = \App\Support\CabangAktif::id()) {
            $query->where('cabang_id', $cabangAktifId);
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tipe')) {
            $query->where('pembelian_langsung', $request->tipe === 'langsung');
        }

        // Filter Status Pembayaran (Fase 3) — cuma relevan untuk PO status
        // Diterima (status lain memang belum punya konsep bayar, lihat kolom
        // "Pembayaran" di tabel yang tampil em-dash untuk status selain itu).
        // WAJIB pakai whereExists/whereNotExists di query builder (bukan filter
        // Collection setelah paginate) supaya pagination tetap hitung dari total
        // row yang benar-benar match, bukan dari 15 row per halaman yang sudah
        // di-fetch duluan.
        if ($request->filled('status_bayar')) {
            $query->where('status', StatusPurchaseOrder::Diterima->value);

            if ($request->status_bayar === 'sudah') {
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('transaksi_keuangans')
                        ->whereColumn('transaksi_keuangans.referensi_id', 'purchase_orders.id')
                        ->where('transaksi_keuangans.referensi_type', 'purchase_order')
                        ->whereNull('transaksi_keuangans.deleted_at');
                });
            } elseif ($request->status_bayar === 'belum') {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('transaksi_keuangans')
                        ->whereColumn('transaksi_keuangans.referensi_id', 'purchase_orders.id')
                        ->where('transaksi_keuangans.referensi_type', 'purchase_order')
                        ->whereNull('transaksi_keuangans.deleted_at');
                });
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_po', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhere('alasan_langsung', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn($s) => $s->where('nama_supplier', 'like', "%{$search}%"));
            });
        }

        $pembelians = $query->orderByDesc('tanggal_po')->paginate(15)->withQueryString();
        $cabangs    = $authUser->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();
        $statuses   = StatusPurchaseOrder::cases();

        // Poin 1 — badge status pembayaran per baris, batch query (anti N+1),
        // dihitung SETELAH paginate supaya tidak masuk scope query utama /
        // tidak mengganggu filter search/cabang/status existing di atas.
        $statusBayar = [];
        if ($pembelians->isNotEmpty()) {
            $statusBayar = app(PoDashboardService::class)
                ->getStatusPembayaranBatch($pembelians->pluck('id')->toArray());
        }

        return view('pembelian.index', compact('pembelians', 'cabangs', 'statuses', 'statusBayar'));
    }

    public function create(Request $request)
    {
        abort_unless(auth()->user()->can('pembelian.create'), 403);

        $suppliers = Supplier::aktif()->orderBy('nama_supplier')->get();
        $cabangs   = Cabang::aktif()->orderBy('nama_cabang')->get();

        $isPembelianLangsung = $request->boolean('pembelian_langsung');

        // Cabang aktif saat ini
        $authUser    = auth()->user();
        $cabangAktif = \App\Support\CabangAktif::id() ?? $authUser->defaultCabangId();
        // 4.39 — lokasi tujuan hanya cabang yang boleh diakses
        $cabangs = \App\Support\CabangAktif::pilihan();

        // Sprint 4.33 — HANYA tampilkan tipe yang genuinely dibeli dari supplier
        // (bahan_baku + kemasan + tambahan_gratis). produk_jual/produk_tambahan
        // dibuat sendiri lewat resep, bukan dibeli — lihat Item::TIPE_DAPAT_DIBELI.
        // Tahap C 4.38 — produk jual/tambahan ikut dimuat, tapi di form hanya
        // muncul kalau supplier-nya internal (lokasi kita, mis. Gudang Pusat)
        // dan produk itu dijual di lokasi tsb (lihat JS itemsUntukSupplier).
        // 4.39 — barang milik cabang-cabang yang boleh diakses (+ produk lokasi supplier internal);
        // form memfilter per Lokasi Tujuan / supplier internal.
        $items = Item::where('is_active', true)
            ->whereIn('tipe', array_merge(Item::TIPE_DAPAT_DIBELI, Item::TIPE_PRODUK_INTERNAL))
            ->with(['stocks.lokasi', 'itemCabang'])
            ->orderBy('nama_item')
            ->get()
            ->map(function ($item) {
                $item->stok_per_lokasi = $item->stocks->map(fn($s) => [
                    'lokasi_id'   => $s->lokasi_id,
                    'nama_lokasi' => $s->lokasi?->nama_cabang ?? '-',
                    'qty'         => (float) $s->qty,
                    'satuan'      => $item->satuan,
                ])->values()->toArray();
                $item->total_stok = $item->stocks->sum('qty');
                return $item;
            });

        return view('pembelian.create', compact('suppliers', 'items', 'cabangs', 'isPembelianLangsung', 'cabangAktif'));
    }

    public function store(PurchaseOrderRequest $request)
    {
        abort_unless(auth()->user()->can('pembelian.create'), 403);

        $data     = $request->validated();
        $authUser = auth()->user();

        $cabangId = $request->input('cabang_id')
            ?: (\App\Support\CabangAktif::id() ?? $authUser->defaultCabangId());
        abort_unless(\App\Support\CabangAktif::bolehAkses((int) $cabangId), 403);

        // Sprint Unit Family (2026-09-27): kalau unit_input = unit_beli item,
        // konversi qty & harga ke unit pakai (satuan) SEBELUM disimpan supaya
        // qty_pesan/harga_satuan tetap dalam unit pakai (backward compat penuh
        // dgn PO lama). qty_input + unit_input disimpan sbg audit trail.
        $itemIds = collect($data['items'])->pluck('item_id')->unique()->all();
        $itemMap = Item::whereIn('id', $itemIds)->get()->keyBy('id');

        // Sprint 4.33 — guard rail: tolak item dgn tipe yang tidak boleh dibeli
        // (produk_jual/produk_tambahan/legacy). Hindari bypass via devtools.
        // Tahap C 4.38 — pengecualian: PO ke supplier INTERNAL boleh berisi
        // produk jual/tambahan yang dijual di lokasi supplier itu.
        $supplierInternal = \App\Models\Supplier::find($data['supplier_id'])?->cabang_id;
        foreach ($itemMap as $it) {
            $produkInternalOk = $supplierInternal
                && in_array($it->tipe, Item::TIPE_PRODUK_INTERNAL, true)
                && $it->tersediaDiCabang((int) $supplierInternal);
            // 4.39 — barang biasa harus milik cabang tujuan PO
            if (!$produkInternalOk && $it->cabang_id !== null && (int) $it->cabang_id !== (int) $cabangId) {
                return back()->withInput()->withErrors([
                    'items' => "Item '{$it->nama_item}' bukan milik cabang tujuan PO ini.",
                ]);
            }
            if (!$produkInternalOk && !in_array($it->tipe, Item::TIPE_DAPAT_DIBELI, true)) {
                return back()->withInput()->withErrors([
                    'items' => "Item '{$it->nama_item}' bertipe '{$it->tipe}' tidak bisa dibeli lewat PO. Hanya bahan baku, kemasan, dan tambahan gratis yang dibeli dari supplier.",
                ]);
            }
        }

        // 4.39 — produk gudang (supplier internal) dicatat sbg barang milik cabang
        // pemesan (Kode Item sama, dibuat kalau belum ada) → stok masuk ke cabang itu.
        if ($supplierInternal) {
            $padanan = app(\App\Services\PadananBarangService::class);
            foreach ($data['items'] as $i => $row) {
                $it = $itemMap[$row['item_id']] ?? null;
                if ($it && $it->cabang_id !== null && (int) $it->cabang_id !== (int) $cabangId) {
                    $milikPemesan = $padanan->untukCabang($it, (int) $cabangId);
                    $data['items'][$i]['item_id'] = $milikPemesan->id;
                    $itemMap[$milikPemesan->id] = $milikPemesan;
                }
            }
        }

        $data['items'] = array_map(function ($row) use ($itemMap) {
            $item = $itemMap[$row['item_id']] ?? null;
            $qtyInput = (float) $row['qty_pesan'];
            $unitInput = $row['unit_input'] ?? null;

            if ($item && $item->hasUnitBeli() && $unitInput
                && strcasecmp($unitInput, (string) $item->unit_beli) === 0) {
                $rasio = (float) $item->isi_per_unit_beli;
                $row['qty_pesan']    = $qtyInput * $rasio;
                $row['harga_satuan'] = (float) $row['harga_satuan'] / $rasio;
                $row['unit_input']   = $item->unit_beli;
                $row['qty_input']    = $qtyInput;
            } else {
                $row['unit_input'] = null;
                $row['qty_input']  = null;
            }
            return $row;
        }, $data['items']);

        $newPo = null;
        DB::transaction(function () use ($data, $cabangId, &$newPo) {
            $totalHarga = 0;
            foreach ($data['items'] as $row) {
                $totalHarga += (float) $row['qty_pesan'] * (float) $row['harga_satuan'];
            }

            $newPo = PurchaseOrder::create([
                'cabang_id'          => $cabangId,
                'supplier_id'        => $data['supplier_id'],
                'nomor_po'           => $this->poService->generateNomorPo(),
                'tanggal_po'         => $data['tanggal_po'],
                'pembelian_langsung' => (bool) ($data['pembelian_langsung'] ?? false),
                'alasan_langsung'    => $data['alasan_langsung'] ?? null,
                'catatan'            => $data['catatan'] ?? null,
                'status'             => StatusPurchaseOrder::Draft,
                'total_harga'        => $totalHarga,
                'created_by'         => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $newPo->id,
                    'item_id'           => $row['item_id'],
                    'qty_pesan'         => $row['qty_pesan'],
                    'harga_satuan'      => $row['harga_satuan'],
                    'total_harga'       => (float) $row['qty_pesan'] * (float) $row['harga_satuan'],
                    'unit_input'        => $row['unit_input'] ?? null,
                    'qty_input'         => $row['qty_input'] ?? null,
                ]);
            }
        });

        // Kirim notifikasi setelah transaksi sukses
        if ($newPo) {
            $event = $newPo->pembelian_langsung ? 'mendesak' : 'baru';
            $recipients = $newPo->pembelian_langsung
                ? NotificationService::getManagement($cabangId)
                : NotificationService::getOwnerAndAdminPusat()->merge(NotificationService::getAdminGudang())->unique('id');
            NotificationService::send($recipients, new PurchaseOrderNotification($newPo, $event));
        }

        return redirect()->route('pembelian.index')
            ->with('success', 'Purchase Order berhasil dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder, \App\Services\PoDashboardService $poDashboardService)
    {
        abort_unless(auth()->user()->can('pembelian.view'), 403);

        $purchaseOrder->load(['supplier', 'cabang', 'items.item', 'approvedBy', 'createdBy']);

        // Status pembayaran — murni tampilan tambahan, tidak mengubah data PO apapun.
        $transaksiPembayaran = $purchaseOrder->status === StatusPurchaseOrder::Diterima
            ? $poDashboardService->cekSudahDibayar($purchaseOrder->id)
            : null;

        return view('pembelian.show', ['po' => $purchaseOrder, 'transaksiPembayaran' => $transaksiPembayaran]);
    }

    /**
     * Cleanup Tool B1 — cari transaksi keuangan pengeluaran yang belum
     * ter-link ke referensi apapun, kandidat kemungkinan pembayaran PO ini
     * yang dicatat manual tanpa dropdown "Pilih PO" (Bug 2, audit produksi
     * 2026-07-26). Murni READ, tidak mengubah data apapun — Owner verifikasi
     * manual per baris sebelum klik "Link".
     */
    public function cariTransaksi(PurchaseOrder $purchaseOrder, PoDashboardService $poDashboardService)
    {
        abort_unless(auth()->user()->can('transaksi.link_po.action'), 403);

        if ($poDashboardService->cekSudahDibayar($purchaseOrder->id)) {
            return response()->json(['data' => [], 'message' => 'PO ini sudah tercatat dibayar.']);
        }

        $tanggalAcuan = $purchaseOrder->tanggal_terima ?? $purchaseOrder->created_at;
        $dari   = Carbon::parse($tanggalAcuan)->subDays(3)->startOfDay();
        $sampai = Carbon::parse($tanggalAcuan)->addDays(3)->endOfDay();
        $totalPo = (float) $purchaseOrder->total_harga;

        $kandidat = TransaksiKeuangan::with('kategoriDinamis')
            ->where('cabang_id', $purchaseOrder->cabang_id)
            ->where('tipe', TipeTransaksiKeuangan::Pengeluaran)
            ->whereNull('referensi_type')
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
            ->orderByDesc('tanggal_transaksi')
            ->get();

        $data = $kandidat->map(function ($t) use ($totalPo) {
            $selisih = abs((float) $t->jumlah - $totalPo);
            return [
                'id'                => $t->id,
                'nomor_transaksi'   => $t->nomor_transaksi,
                'tanggal_transaksi' => $t->tanggal_transaksi->format('d/m/Y'),
                'jumlah'            => (float) $t->jumlah,
                'keterangan'        => $t->keterangan,
                'kategori'          => $t->kategoriDinamis?->nama ?? $t->kategori?->label() ?? '-',
                'selisih_nominal'   => $selisih,
                'exact_match'       => $selisih < 0.01,
            ];
        })->sortBy('selisih_nominal')->values();

        return response()->json(['data' => $data, 'total_po' => $totalPo]);
    }

    /**
     * Cleanup Tool B1 — link transaksi keuangan existing ke PO ini (isi
     * referensi_type/referensi_id). Tidak menyentuh saldo kas (transaksi
     * sudah pernah mengurangi saldo kas saat pertama kali dibuat) — murni
     * menambah metadata link supaya status pembayaran PO terdeteksi benar.
     */
    public function linkTransaksi(Request $request, PurchaseOrder $purchaseOrder, PoDashboardService $poDashboardService)
    {
        abort_unless(auth()->user()->can('transaksi.link_po.action'), 403);

        $request->validate([
            'transaksi_id' => 'required|exists:transaksi_keuangans,id',
        ]);

        if ($poDashboardService->cekSudahDibayar($purchaseOrder->id)) {
            return back()->with('error', 'PO ini sudah tercatat dibayar sebelumnya.');
        }

        $transaksi = TransaksiKeuangan::where('id', $request->transaksi_id)
            ->where('cabang_id', $purchaseOrder->cabang_id)
            ->whereNull('referensi_type')
            ->first();

        if (!$transaksi) {
            return back()->with('error', 'Transaksi tidak ditemukan atau sudah ter-link ke sumber lain.');
        }

        // Defense-in-depth (Rule #40): re-verify exact_match di server — pasangan
        // dari tombol "Link" yang di-disable di frontend utk baris non-exact.
        // Form ini POST biasa (bukan fetch/AJAX), jadi respons tetap redirect +
        // flash message, konsisten dgn guard "sudah dibayar" di atas — BUKAN
        // JSON, supaya user tidak melihat raw JSON kalau tombol di-bypass.
        if ((int) $transaksi->jumlah !== (int) $purchaseOrder->total_harga) {
            return back()->with('error', 'Nominal transaksi Rp '
                . number_format($transaksi->jumlah, 0, ',', '.')
                . ' tidak sama persis dengan total PO Rp '
                . number_format($purchaseOrder->total_harga, 0, ',', '.')
                . '. Verifikasi manual dulu.');
        }

        $transaksi->update([
            'referensi_type' => 'purchase_order',
            'referensi_id'   => $purchaseOrder->id,
        ]);

        activity('PurchaseOrder')
            ->performedOn($purchaseOrder)
            ->causedBy(auth()->user())
            ->withProperties([
                'transaksi_id'      => $transaksi->id,
                'nomor_transaksi'   => $transaksi->nomor_transaksi,
                'jumlah'            => (float) $transaksi->jumlah,
            ])
            ->log(auth()->user()->name . " link transaksi {$transaksi->nomor_transaksi} ke PO {$purchaseOrder->nomor_po} (cleanup data historis)");

        return redirect()->route('pembelian.show', $purchaseOrder)
            ->with('success', "Transaksi {$transaksi->nomor_transaksi} berhasil di-link ke PO {$purchaseOrder->nomor_po}. Status pembayaran otomatis terupdate.");
    }

    /**
     * Batal Bayar PO (Fase 4) — rollback pembayaran untuk kasus salah kas,
     * salah PO, atau PO cancelled (1-2x/bulan). Restore saldo kas + hard
     * delete transaksi (bukan soft — biar bersih, tidak ninggalin jejak
     * ambigu di Data Terhapus) + status PO otomatis balik "Belum Dibayar"
     * (murni dari absennya transaksi ber-referensi, tidak ada kolom yang
     * diubah di purchase_orders sendiri).
     *
     * KRITIS: TransaksiKeuangan::withoutEvents() WAJIB — TransaksiKeuanganObserver::deleted()
     * (bug pre-existing, Rule #63) cascade-soft-delete PurchaseOrder manapun
     * yang jadi referensi_id transaksi yang dihapus. Tanpa withoutEvents()
     * di sini, hapus transaksi pembayaran = PO ini ikut ter-soft-delete.
     */
    public function batalBayar(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->user()->can('po.batal_bayar.action'), 403);

        if ($purchaseOrder->status !== StatusPurchaseOrder::Diterima) {
            return back()->with('error', 'Hanya PO status Diterima yang bisa dibatalkan pembayarannya.');
        }

        $transaksi = TransaksiKeuangan::where('referensi_type', 'purchase_order')
            ->where('referensi_id', $purchaseOrder->id)
            ->whereNull('deleted_at')
            ->first();

        if (!$transaksi) {
            return back()->with('error', 'PO ini belum ada transaksi pembayaran yang ter-link.');
        }

        $jumlah  = (float) $transaksi->jumlah;
        $nomorTx = $transaksi->nomor_transaksi;
        $kasNama = $transaksi->kas?->nama_kas;

        try {
            DB::transaction(function () use ($transaksi, $purchaseOrder, $jumlah, $nomorTx, $kasNama) {
                TransaksiKeuangan::withoutEvents(function () use ($transaksi) {
                    $kas = $transaksi->kas;
                    if ($kas) {
                        $kas->increment('saldo_sekarang', (float) $transaksi->jumlah);
                    }
                    $transaksi->forceDelete();
                });

                activity('PurchaseOrder')
                    ->performedOn($purchaseOrder)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'transaksi_id'      => $transaksi->id,
                        'nomor_transaksi'   => $nomorTx,
                        'jumlah'            => $jumlah,
                        'kas_id'            => $transaksi->kas_id,
                        'kas_nama'          => $kasNama,
                        'po_nomor'          => $purchaseOrder->nomor_po,
                    ])
                    ->log(auth()->user()->name . " batal bayar PO {$purchaseOrder->nomor_po} (transaksi {$nomorTx} dihapus, saldo dikembalikan)");
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('batalBayar PO error', [
                'po_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Gagal batal bayar: ' . $e->getMessage());
        }

        return redirect()->route('pembelian.show', $purchaseOrder)
            ->with('success', "Pembayaran PO {$purchaseOrder->nomor_po} berhasil dibatalkan. Saldo kas dikembalikan Rp " . number_format($jumlah, 0, ',', '.') . '.');
    }

    public function approve(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->user()->can('pembelian.approve'), 403);

        if ($purchaseOrder->status !== StatusPurchaseOrder::Draft) {
            return back()->with('error', 'Hanya PO berstatus Draft yang bisa disetujui.');
        }

        $this->poService->approve($purchaseOrder);

        // Notifikasi ke pembuat PO
        $pembuat = $purchaseOrder->createdBy ?? \App\Models\User::find($purchaseOrder->created_by);
        if ($pembuat) {
            $event = $purchaseOrder->pembelian_langsung ? 'mendesak_disetujui' : 'disetujui';
            NotificationService::send(collect([$pembuat]), new PurchaseOrderNotification($purchaseOrder, $event));
        }

        return back()->with('success', 'Purchase Order berhasil disetujui.');
    }

    public function kirimSupplier(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->user()->can('pembelian.kirim-supplier'), 403);

        if ($purchaseOrder->status !== StatusPurchaseOrder::Disetujui) {
            return back()->with('error', 'Hanya PO berstatus Disetujui yang bisa dikirim ke supplier.');
        }

        $this->poService->kirimSupplier($purchaseOrder);

        return back()->with('success', 'Status PO diperbarui ke Dikirim Supplier.');
    }

    public function terima(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->user()->can('pembelian.terima'), 403);

        if ($purchaseOrder->status !== StatusPurchaseOrder::DikirimSupplier) {
            return back()->with('error', 'Hanya PO berstatus Dikirim Supplier yang bisa diterima.');
        }

        $qtyTerimaPerItem = $request->input('qty_terima', []);

        try {
            $this->poService->terima($purchaseOrder, $qtyTerimaPerItem);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        // Notifikasi barang diterima
        $recipients = NotificationService::getOwnerAndAdminPusat()->merge(NotificationService::getAdminGudang())->unique('id');
        NotificationService::send($recipients, new PurchaseOrderNotification($purchaseOrder, 'diterima'));

        return redirect()->route('pembelian.show', $purchaseOrder)
            ->with('success', 'Barang berhasil diterima dan stok diperbarui.');
    }

    public function batalkan(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->user()->can('pembelian.delete'), 403);

        if (!in_array($purchaseOrder->status, [StatusPurchaseOrder::Draft, StatusPurchaseOrder::Disetujui])) {
            return back()->with('error', 'PO ini tidak dapat dibatalkan pada status saat ini.');
        }

        $this->poService->batalkan($purchaseOrder);

        return back()->with('success', 'Purchase Order berhasil dibatalkan.');
    }

    public function destroy(PurchaseOrder $purchaseOrder, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('pembelian.delete'), 403, 'Anda tidak memiliki akses untuk menghapus Purchase Order.');

        try {
            $nomor   = $purchaseOrder->nomor_po;
            $deleted = $cascadeService->deletePurchaseOrderCascade($purchaseOrder);
            $ringkasan = collect($deleted)->filter()->map(fn($c, $k) => "{$c} {$k}")->join(', ');

            return redirect()->route('pembelian.index')
                ->with('success', "PO <strong>{$nomor}</strong> berhasil dihapus." . ($ringkasan ? " ({$ringkasan})" : '') . ' Data masih bisa dipulihkan di <a href="' . route('trash.index', ['model' => 'purchase_orders']) . '">Data Terhapus</a>.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus PO: ' . $e->getMessage());
        }
    }
}
