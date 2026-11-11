<?php

namespace App\Http\Controllers;

use App\Enums\StatusStockRequest;
use App\Http\Requests\StockRequestFormRequest;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Notifications\StockRequestNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockRequestController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $user        = auth()->user();
        $activeLokId = session('active_cabang_id');

        $query = StockRequest::with(['cabang','createdBy','approvedBy','items'])
            ->latest();

        // Filter berdasarkan role: cabang hanya lihat miliknya, gudang/owner lihat semua
        if (!$user->canAccessAllBranches()) {
            $cabangId = $activeLokId ?? $user->defaultCabangId();
            $query->where('cabang_id', $cabangId);
        } elseif ($activeLokId) {
            // Owner sedang di cabang tertentu
            $query->where('cabang_id', $activeLokId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('cabang') && $user->canAccessAllBranches()) {
            $query->where('cabang_id', $request->cabang);
        }

        // Search multi-field — TAMBAHAN, tidak mengganti filter status/cabang di atas
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_request', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhereHas('cabang', fn ($c) => $c->where('nama_cabang', 'like', "%{$search}%"))
                  ->orWhereHas('createdBy', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $stockRequests = $query->paginate(20)->withQueryString();
        $cabangs       = $user->canAccessAllBranches() ? Cabang::aktif()->get() : collect();

        $stats = [
            'pending'   => StockRequest::where('status','pending')->count(),
            'disetujui' => StockRequest::where('status','disetujui')->count(),
            'dikirim'   => StockRequest::where('status','dikirim')->count(),
            'diterima'  => StockRequest::where('status','diterima')->count(),
        ];

        $authUser = $user;

        return view('stok.request.index', compact('stockRequests','cabangs','stats','authUser'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('stok.request'), 403);

        // Sprint 4.33 — filter tipe barang yang bisa di-request antar cabang
        // (sama pola PO & Transfer: bahan baku + kemasan + tambahan gratis).
        // 4.39 — barang milik cabang peminta (cabang aktif)
        $items = Item::aktif()->dapatDibeli()->milikCabang(\App\Support\CabangAktif::id())->with('category', 'cabang')->orderBy('nama_item')->get();
        return view('stok.request.create', compact('items'));
    }

    public function store(StockRequestFormRequest $request)
    {
        abort_unless(auth()->user()->can('stok.request'), 403);

        $activeLokId = session('active_cabang_id') ?? auth()->user()->defaultCabangId();

        if (!$activeLokId) {
            return back()->with('error', 'Pilih cabang aktif terlebih dahulu.')->withInput();
        }

        // Sprint 4.33 — guard rail tipe item (hindari bypass devtools).
        $itemIds = collect($request->items ?? [])->pluck('item_id')->filter()->unique()->all();
        $invalidItems = Item::whereIn('id', $itemIds)->whereNotIn('tipe', Item::TIPE_DAPAT_DIBELI)->pluck('nama_item');
        if ($invalidItems->isNotEmpty()) {
            return back()->withInput()->with('error', "Item berikut tidak bisa di-request antar cabang: " . $invalidItems->join(', '));
        }
        // 4.39 — barang yang diminta = barang milik cabang peminta (gudang memasangkan lewat Kode Item)
        $bukanMilik = Item::whereIn('id', $itemIds)->whereNotNull('cabang_id')->where('cabang_id', '!=', $activeLokId)->pluck('nama_item');
        if ($bukanMilik->isNotEmpty()) {
            return back()->withInput()->with('error', 'Barang berikut bukan milik cabang ini: ' . $bukanMilik->join(', '));
        }

        $reqPrefix = 'REQ-' . date('Ymd');
        $lastReq   = StockRequest::withTrashed()
            ->where('nomor_request', 'like', $reqPrefix . '-%')
            ->orderByDesc('id')
            ->value('nomor_request');
        $reqSeq = 1;
        if ($lastReq && preg_match('/(\d+)$/', $lastReq, $mr)) {
            $reqSeq = ((int) $mr[1]) + 1;
        }
        $nomor = $reqPrefix . '-' . str_pad($reqSeq, 3, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($request, $activeLokId, $nomor) {
            $sr = StockRequest::create([
                'cabang_id'        => $activeLokId,
                'nomor_request'    => $nomor,
                'tanggal_request'  => today(),
                'status'           => StatusStockRequest::Pending,
                'catatan'          => $request->catatan,
                'created_by'       => auth()->id(),
            ]);

            foreach ($request->items as $baris) {
                StockRequestItem::create([
                    'stock_request_id' => $sr->id,
                    'item_id'          => $baris['item_id'],
                    'qty_diminta'      => $baris['qty_diminta'],
                    'catatan'          => $baris['catatan'] ?? null,
                ]);
            }
        });

        // Notifikasi ke Admin Gudang
        $sr = StockRequest::where('nomor_request', $nomor)->latest()->first();
        if ($sr) {
            $recipients = NotificationService::getAdminGudang()->merge(NotificationService::getOwnerAndAdminPusat())->unique('id');
            NotificationService::send($recipients, new StockRequestNotification($sr, 'baru'));
        }

        return redirect()->route('stock-request.index')->with('success', "Permintaan stok {$nomor} berhasil dikirim.");
    }

    public function show(StockRequest $stockRequest)
    {
        abort_unless(auth()->user()->can('stok.view'), 403);

        $stockRequest->load(['cabang','createdBy','approvedBy','items.item.category']);
        return view('stok.request.show', compact('stockRequest'));
    }

    /**
     * Gudang pusat setujui/tolak permintaan
     */
    public function approve(Request $request, StockRequest $stockRequest)
    {
        abort_unless(auth()->user()->can('stok.transfer'), 403);

        if ($stockRequest->status !== StatusStockRequest::Pending) {
            return back()->with('error', 'Permintaan sudah diproses.');
        }

        $request->validate([
            'aksi'                    => 'required|in:disetujui,ditolak',
            'catatan_gudang'          => 'nullable|string|max:300',
            'qty_disetujui'           => 'required_if:aksi,disetujui|array',
            'qty_disetujui.*'         => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $stockRequest) {
            if ($request->aksi === 'disetujui') {
                foreach ($stockRequest->items as $item) {
                    $item->update([
                        'qty_disetujui' => $request->qty_disetujui[$item->id] ?? $item->qty_diminta,
                    ]);
                }
                $stockRequest->update([
                    'status'      => StatusStockRequest::Disetujui,
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'catatan'     => $request->catatan_gudang ?? $stockRequest->catatan,
                ]);
            } else {
                $stockRequest->update([
                    'status'      => StatusStockRequest::Ditolak,
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'catatan'     => $request->catatan_gudang,
                ]);
            }
        });

        // Notifikasi ke Manajer Cabang peminta
        $event = $request->aksi === 'disetujui' ? 'disetujui' : 'ditolak';
        $manajer = NotificationService::getManajerCabang($stockRequest->cabang_id);
        NotificationService::send($manajer, new StockRequestNotification(
            $stockRequest,
            $event,
            $request->catatan_gudang
        ));

        $label = $request->aksi === 'disetujui' ? 'disetujui' : 'ditolak';
        return redirect()->route('stock-request.show', $stockRequest)
            ->with('success', "Permintaan {$stockRequest->nomor_request} berhasil {$label}.");
    }

    /**
     * Cabang konfirmasi terima barang
     */
    public function terima(Request $request, StockRequest $stockRequest)
    {
        abort_unless(auth()->user()->can('stok.request'), 403);

        if ($stockRequest->status !== StatusStockRequest::Dikirim) {
            return back()->with('error', 'Status permintaan bukan "Dikirim".');
        }

        $stockRequest->update(['status' => StatusStockRequest::Diterima]);

        return redirect()->route('stock-request.show', $stockRequest)
            ->with('success', 'Permintaan ditandai sebagai Diterima.');
    }

    /**
     * Batalkan permintaan (pending atau disetujui yang belum dikirim)
     */
    public function batalkan(Request $request, StockRequest $stockRequest)
    {
        abort_unless(auth()->user()->can('stok.request') || auth()->user()->can('stok.transfer'), 403);

        $allowedStatus = [StatusStockRequest::Pending, StatusStockRequest::Disetujui];

        if (!in_array($stockRequest->status, $allowedStatus)) {
            return back()->with('error', 'Hanya permintaan berstatus Pending atau Disetujui yang bisa dibatalkan.');
        }

        $request->validate(['alasan' => 'nullable|string|max:300']);

        $stockRequest->update([
            'status'  => StatusStockRequest::Dibatalkan,
            'catatan' => $request->alasan
                ? ($stockRequest->catatan ? $stockRequest->catatan . ' | Dibatalkan: ' . $request->alasan : 'Dibatalkan: ' . $request->alasan)
                : $stockRequest->catatan,
        ]);

        return redirect()->route('stock-request.index')
            ->with('success', "Permintaan {$stockRequest->nomor_request} berhasil dibatalkan.");
    }

    /**
     * Hapus permintaan (hanya yang sudah ditolak atau dibatalkan)
     */
    public function destroy(StockRequest $stockRequest)
    {
        abort_unless(auth()->user()->can('stok.request') || auth()->user()->can('stok.transfer'), 403);

        $allowedStatus = [StatusStockRequest::Ditolak, StatusStockRequest::Dibatalkan];

        if (!in_array($stockRequest->status, $allowedStatus)) {
            return back()->with('error', 'Hanya permintaan berstatus Ditolak atau Dibatalkan yang bisa dihapus.');
        }

        $nomor = $stockRequest->nomor_request;
        $stockRequest->items()->delete();
        $stockRequest->delete();

        return redirect()->route('stock-request.index')
            ->with('success', "Permintaan {$nomor} berhasil dihapus.");
    }
}
