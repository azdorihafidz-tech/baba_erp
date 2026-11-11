<?php

namespace App\Http\Controllers;

use App\Enums\StatusStockRequest;
use App\Enums\StatusTransfer;
use App\Http\Requests\StockTransferRequest;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Stock;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Notifications\StockTransferNotification;
use App\Services\NotificationService;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function __construct(private StokService $stokService) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $user        = auth()->user();
        $activeLokId = session('active_cabang_id');

        $query = StockTransfer::with(['dariLokasi','keLokasi','createdBy','items'])
            ->latest();

        if (!$user->canAccessAllBranches()) {
            $lokasiId = $activeLokId ?? $user->defaultCabangId();
            $query->where(fn($q) => $q->where('dari_lokasi_id',$lokasiId)->orWhere('ke_lokasi_id',$lokasiId));
        } elseif ($activeLokId) {
            $query->where(fn($q) => $q->where('dari_lokasi_id',$activeLokId)->orWhere('ke_lokasi_id',$activeLokId));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search multi-field — TAMBAHAN, tidak mengganti filter status di atas
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_transfer', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhereHas('dariLokasi', fn ($c) => $c->where('nama_cabang', 'like', "%{$search}%"))
                  ->orWhereHas('keLokasi', fn ($c) => $c->where('nama_cabang', 'like', "%{$search}%"));
            });
        }

        $transfers = $query->paginate(20)->withQueryString();
        $statuses  = StatusTransfer::cases();
        $authUser  = $user;

        return view('stok.transfer.index', compact('transfers','statuses','authUser'));
    }

    public function create(Request $request)
    {
        abort_unless(auth()->user()->can('stok.transfer'), 403);

        $lokasiList   = Cabang::aktif()->get();
        // Sprint 4.33 — filter 3 tipe yang dibeli/dipindah antar cabang.
        // produk_jual/produk_tambahan dibuat on-demand di tiap cabang via resep,
        // tidak di-transfer antar cabang.
        // 4.39 — barang milik cabang aktif (pengirim); di cabang tujuan dipasangkan lewat Kode Item
        $items        = Item::aktif()->dapatDibeli()->milikCabang(\App\Support\CabangAktif::id())->with('category', 'cabang')->orderBy('nama_item')->get();
        $stockRequest = null;
        $prefillItems = [];
        $defaultDariLokasiId = null;

        if ($request->filled('dari_request')) {
            $stockRequest = StockRequest::with('items.item')->findOrFail($request->dari_request);

            // Auto-select gudang pusat sebagai lokasi asal
            $gudangPusat = Cabang::where('tipe', 'gudang_pusat')->orderBy('id')->first();
            $defaultDariLokasiId = $gudangPusat?->id;

            // 4.39 — barang peminta dipasangkan ke barang milik gudang (Kode Item sama);
            // yang belum ada di gudang dilewati.
            $padanan = app(\App\Services\PadananBarangService::class);
            if ($defaultDariLokasiId) {
                $items = Item::aktif()->dapatDibeli()->milikCabang($defaultDariLokasiId)->with('category', 'cabang')->orderBy('nama_item')->get();
            }
            $prefillItems = $stockRequest->items->map(function ($sri) use ($padanan, $defaultDariLokasiId) {
                $barang = ($sri->item && $defaultDariLokasiId) ? $padanan->cari($sri->item, $defaultDariLokasiId) : $sri->item;
                return $barang ? [
                'item_id'   => $barang->id,
                'qty_kirim' => $sri->qty_disetujui ?? $sri->qty_diminta,
                'catatan'   => $sri->catatan ?? '',
                'satuan'    => $sri->item?->satuan ?? '',
                ] : null;
            })->filter()->values()->toArray();
        }

        // Ambil stok per item per lokasi untuk validasi client-side
        $stokData = Stock::join('items','stocks.item_id','=','items.id')
            ->select('stocks.item_id','stocks.lokasi_id','stocks.qty','items.satuan')
            ->get()
            ->groupBy('lokasi_id');

        return view('stok.transfer.create', compact(
            'lokasiList','items','stockRequest','stokData','prefillItems','defaultDariLokasiId'
        ));
    }

    public function store(StockTransferRequest $request)
    {
        abort_unless(auth()->user()->can('stok.transfer'), 403);

        if ($request->dari_lokasi_id === $request->ke_lokasi_id) {
            return back()->with('error','Lokasi asal dan tujuan tidak boleh sama.')->withInput();
        }

        // Sprint 4.33 — guard rail tipe item (hindari bypass devtools).
        $itemIds = collect($request->items)->pluck('item_id')->unique()->all();
        $invalidItems = Item::whereIn('id', $itemIds)->whereNotIn('tipe', Item::TIPE_DAPAT_DIBELI)->pluck('nama_item');
        if ($invalidItems->isNotEmpty()) {
            return back()->withInput()->with('error', "Item berikut tidak bisa ditransfer antar cabang (dibuat on-demand lewat resep): " . $invalidItems->join(', '));
        }
        // 4.39 — barang yang dikirim harus milik lokasi asal (di tujuan dipasangkan lewat Kode Item)
        $bukanAsal = Item::whereIn('id', $itemIds)->whereNotNull('cabang_id')->where('cabang_id', '!=', $request->dari_lokasi_id)->pluck('nama_item');
        if ($bukanAsal->isNotEmpty()) {
            return back()->withInput()->with('error', 'Barang berikut bukan milik lokasi asal: ' . $bukanAsal->join(', '));
        }

        $trfPrefix = 'TRF-' . date('Ymd');
        $lastTrf   = StockTransfer::withTrashed()
            ->where('nomor_transfer', 'like', $trfPrefix . '-%')
            ->orderByDesc('id')
            ->value('nomor_transfer');
        $trfSeq = 1;
        if ($lastTrf && preg_match('/(\d+)$/', $lastTrf, $mt)) {
            $trfSeq = ((int) $mt[1]) + 1;
        }
        $nomor = $trfPrefix . '-' . str_pad($trfSeq, 3, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($request, $nomor) {
            $transfer = StockTransfer::create([
                'nomor_transfer'   => $nomor,
                'dari_lokasi_id'   => $request->dari_lokasi_id,
                'ke_lokasi_id'     => $request->ke_lokasi_id,
                'tanggal_kirim'    => $request->tanggal_kirim,
                'status'           => StatusTransfer::Draft,
                'stock_request_id' => $request->stock_request_id,
                'catatan'          => $request->catatan,
                'created_by'       => auth()->id(),
            ]);

            foreach ($request->items as $baris) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id'           => $baris['item_id'],
                    'qty_kirim'         => $baris['qty_kirim'],
                    'catatan'           => $baris['catatan'] ?? null,
                ]);
            }
        });

        return redirect()->route('stock-transfer.index')->with('success', "Transfer {$nomor} berhasil dibuat.");
    }

    public function show(StockTransfer $stockTransfer)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $stockTransfer->load(['dariLokasi','keLokasi','createdBy','receivedBy','stockRequest','items.item.category']);
        return view('stok.transfer.show', ['transfer' => $stockTransfer]);
    }

    /**
     * Kirim transfer: kurangi stok asal
     */
    public function kirim(StockTransfer $stockTransfer)
    {
        abort_unless(auth()->user()->can('stok.transfer'), 403);

        if ($stockTransfer->status !== StatusTransfer::Draft) {
            return back()->with('error', 'Transfer bukan dalam status Draft.');
        }

        try {
            DB::transaction(function () use ($stockTransfer) {
                $this->stokService->prosesKirimTransfer($stockTransfer);
                $stockTransfer->update([
                    'status'        => StatusTransfer::Dikirim,
                    'tanggal_kirim' => today(),
                ]);

                // Update stock_request jika ada
                if ($stockTransfer->stock_request_id) {
                    StockRequest::where('id', $stockTransfer->stock_request_id)
                        ->update(['status' => StatusStockRequest::Dikirim]);
                }
            });
            // Notifikasi ke Manajer Cabang tujuan
            $manajer = NotificationService::getManajerCabang($stockTransfer->ke_lokasi_id);
            NotificationService::send($manajer, new StockTransferNotification($stockTransfer, 'dikirim'));

            return redirect()->route('stock-transfer.show', $stockTransfer)
                ->with('success', "Transfer {$stockTransfer->nomor_transfer} berhasil dikirim.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Terima transfer: tambah stok tujuan
     */
    public function terima(Request $request, StockTransfer $stockTransfer)
    {
        abort_unless(auth()->user()->can('stok.request') || auth()->user()->can('stok.transfer'), 403);

        if (!in_array($stockTransfer->status->value, ['dikirim','diterima_sebagian'])) {
            return back()->with('error', 'Transfer belum dikirim.');
        }

        $request->validate([
            'qty_terima'   => 'required|array',
            'qty_terima.*' => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($request, $stockTransfer) {
                // Update qty_terima di setiap item
                foreach ($stockTransfer->items as $item) {
                    $qty = (float) ($request->qty_terima[$item->id] ?? 0);
                    $item->update(['qty_terima' => $qty]);
                }

                // Reload items setelah update
                $stockTransfer->load('items');
                $this->stokService->prosesTerimaTransfer($stockTransfer);

                // Tentukan status
                $semua    = $stockTransfer->items->every(fn($i) => $i->qty_terima >= $i->qty_kirim);
                $sebagian = $stockTransfer->items->some(fn($i) => ($i->qty_terima ?? 0) > 0);

                $status = $semua ? StatusTransfer::Diterima
                    : ($sebagian ? StatusTransfer::DiterimaSebagian : StatusTransfer::DiterimaSebagian);

                $stockTransfer->update([
                    'status'        => $status,
                    'tanggal_terima'=> today(),
                    'received_by'   => auth()->id(),
                ]);

                if ($semua && $stockTransfer->stock_request_id) {
                    StockRequest::where('id', $stockTransfer->stock_request_id)
                        ->update(['status' => StatusStockRequest::Diterima]);
                }
            });

            // Notifikasi ke Admin Gudang & Owner bahwa transfer diterima
            $recipients = NotificationService::getAdminGudang()->merge(NotificationService::getOwnerAndAdminPusat())->unique('id');
            NotificationService::send($recipients, new StockTransferNotification($stockTransfer, 'diterima'));

            return redirect()->route('stock-transfer.show', $stockTransfer)
                ->with('success', "Transfer {$stockTransfer->nomor_transfer} berhasil diterima.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Batalkan transfer — Draft (langsung) atau Dikirim (kembalikan stok asal)
     */
    public function batalkan(Request $request, StockTransfer $stockTransfer)
    {
        abort_unless(auth()->user()->can('stok.request') || auth()->user()->can('stok.transfer'), 403);

        $allowedStatus = [StatusTransfer::Draft, StatusTransfer::Dikirim];

        if (!in_array($stockTransfer->status, $allowedStatus)) {
            return back()->with('error', 'Hanya transfer berstatus Draft atau Dikirim yang bisa dibatalkan.');
        }

        try {
            DB::transaction(function () use ($request, $stockTransfer) {
                // Jika sudah dikirim, kembalikan stok ke lokasi asal
                if ($stockTransfer->status === StatusTransfer::Dikirim) {
                    $stockTransfer->load('items');
                    foreach ($stockTransfer->items as $item) {
                        // Dikembalikan dgn modal FIFO yang dulu keluar (dulu tanpa
                        // harga → batch Rp 0, nilai persediaan jadi salah).
                        $this->stokService->masuk(
                            $item->item_id,
                            $stockTransfer->dari_lokasi_id,
                            $item->qty_kirim,
                            "Pembatalan transfer #{$stockTransfer->nomor_transfer} — stok dikembalikan",
                            'stock_transfer',
                            $stockTransfer->id,
                            $item->harga_per_unit !== null
                                ? (float) $item->harga_per_unit
                                : (float) ($item->item?->harga_beli_terakhir ?? 0),
                        );
                    }
                }

                $stockTransfer->update(['status' => StatusTransfer::Dibatalkan]);

                // Kembalikan status stock_request jika ada
                if ($stockTransfer->stock_request_id) {
                    StockRequest::where('id', $stockTransfer->stock_request_id)
                        ->whereIn('status', [StatusStockRequest::Dikirim->value, StatusStockRequest::Disetujui->value])
                        ->update(['status' => StatusStockRequest::Disetujui->value]);
                }
            });

            return redirect()->route('stock-transfer.show', $stockTransfer)
                ->with('success', "Transfer {$stockTransfer->nomor_transfer} dibatalkan." .
                    ($stockTransfer->status === StatusTransfer::Dikirim ? ' Stok lokasi asal telah dikembalikan.' : ''));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Hapus transfer (hanya yang sudah dibatalkan)
     */
    public function destroy(StockTransfer $stockTransfer)
    {
        abort_unless(auth()->user()->can('stok.request') || auth()->user()->can('stok.transfer'), 403);

        if ($stockTransfer->status !== StatusTransfer::Dibatalkan) {
            return back()->with('error', 'Hanya transfer berstatus Dibatalkan yang bisa dihapus.');
        }

        $nomor = $stockTransfer->nomor_transfer;
        $stockTransfer->items()->delete();
        $stockTransfer->delete();

        return redirect()->route('stock-transfer.index')
            ->with('success', "Transfer {$nomor} berhasil dihapus.");
    }
}
