<?php

namespace App\Http\Controllers;

use App\Http\Requests\PelangganRequest;
use App\Models\LoyaltyProgram;
use App\Models\Pelanggan;
use App\Services\CascadeDeleteService;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;

class PelangganController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('pelanggan.view'), 403);

        $query = Pelanggan::withCount('orders');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_pelanggan', 'like', "%{$search}%")
                  ->orWhere('kode_pelanggan', 'like', "%{$search}%")
                  ->orWhere('telepon', 'like', "%{$search}%");
            });
        }

        $pelanggans = $query->orderBy('nama_pelanggan')->paginate(15)->withQueryString();

        return view('pelanggan.index', compact('pelanggans'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('pelanggan.create'), 403);

        $last    = Pelanggan::orderByDesc('id')->first();
        $seq     = $last ? ((int) substr($last->kode_pelanggan, 4)) + 1 : 1;
        $kodeHint = 'PLG-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

        return view('pelanggan.create', compact('kodeHint'));
    }

    public function store(PelangganRequest $request)
    {
        abort_unless(auth()->user()->can('pelanggan.create'), 403);

        $data = $request->validated();

        if (empty($data['kode_pelanggan'])) {
            $last = Pelanggan::orderByDesc('id')->first();
            $seq  = $last ? ((int) substr($last->kode_pelanggan, 4)) + 1 : 1;
            $data['kode_pelanggan'] = 'PLG-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
        }

        $data['is_active'] = true;
        Pelanggan::create($data);

        return redirect()->route('pelanggan.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function show(Pelanggan $pelanggan, Request $request)
    {
        abort_unless(auth()->user()->can('pelanggan.view'), 403);

        $pelanggan->loadCount('orders');

        $ordersQuery = $pelanggan->orders()
            ->with('cabang')
            ->orderByDesc('tanggal_order')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $ordersQuery->where('status', $request->status);
        }

        $orders = $ordersQuery->paginate(10)->withQueryString();

        // Bug fix 2026-09-21: dulu ada 2 widget beda definisi ("Total
        // Belanja" tanpa filter status/tanggal, vs "Total Kg Giling" yang
        // selalu 0 krn D'mentai tidak pernah pakai basis kg) -- diselaraskan
        // jadi 1 angka via accessor Pelanggan::getTotalPembelianAttribute()
        // (order status selesai + tanggal < hari ini).
        $totalPembelian = $pelanggan->total_pembelian;
        $orderTerakhir  = $pelanggan->orders()->orderByDesc('tanggal_order')->value('tanggal_order');

        $loyaltyProgress = collect();
        $loyaltyKlaims = collect();
        if (auth()->user()->can('loyalty.view')) {
            $loyaltyService = app(LoyaltyService::class);
            // hitungProgressPelanggan() cuma valid utk program auto_track (progress
            // kg) — event_based (target_qty_kg=0) punya jalur sendiri (LoyaltyKlaim).
            $loyaltyProgress = LoyaltyProgram::aktif()->autoTrack()->get()->map(
                fn ($program) => $loyaltyService->hitungProgressPelanggan($pelanggan->id, $program->id)
            );
        }
        if (auth()->user()->can('loyalty.klaim.view')) {
            $loyaltyKlaims = \App\Models\LoyaltyKlaim::with('loyaltyProgram')
                ->where('pelanggan_id', $pelanggan->id)
                ->orderByDesc('created_at')
                ->get();
        }

        $statuses = \App\Enums\StatusOrder::cases();

        return view('pelanggan.show', compact(
            'pelanggan', 'orders', 'totalPembelian', 'orderTerakhir', 'statuses',
            'loyaltyProgress', 'loyaltyKlaims'
        ));
    }

    public function edit(Pelanggan $pelanggan)
    {
        abort_unless(auth()->user()->can('pelanggan.edit'), 403);

        return view('pelanggan.edit', compact('pelanggan'));
    }

    public function update(PelangganRequest $request, Pelanggan $pelanggan)
    {
        abort_unless(auth()->user()->can('pelanggan.edit'), 403);

        $pelanggan->update($request->validated());

        return redirect()->route('pelanggan.index')
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Pelanggan $pelanggan, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('pelanggan.delete'), 403);

        try {
            $nama    = $pelanggan->nama_pelanggan;
            $deleted = $cascadeService->deletePelangganCascade($pelanggan);

            $labels = ['order' => 'Order', 'order_item' => 'Item Order', 'pelanggan' => 'Pelanggan'];
            $ringkasan = collect($deleted)->map(fn($c, $k) => "{$c} " . ($labels[$k] ?? $k))->filter()->join(', ');

            return redirect()->route('pelanggan.index')
                ->with('success', "Pelanggan <strong>{$nama}</strong> beserta data terkait berhasil dihapus. ({$ringkasan})");
        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus pelanggan: " . $e->getMessage());
        }
    }
}
