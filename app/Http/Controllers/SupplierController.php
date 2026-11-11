<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Services\CascadeDeleteService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::withCount('purchaseOrders');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_supplier', 'like', "%{$search}%")
                  ->orWhere('kode_supplier', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        $suppliers = $query->orderBy('nama_supplier')->paginate(15)->withQueryString();

        return view('supplier.index', compact('suppliers'));
    }

    public function create()
    {
        // Generate kode supplier berikutnya
        $last = Supplier::orderByDesc('id')->first();
        $seq  = $last ? ((int) substr($last->kode_supplier, 4)) + 1 : 1;
        $kodeHint = 'SUP-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

        return view('supplier.create', compact('kodeHint'));
    }

    public function store(SupplierRequest $request)
    {
        $data = $request->validated();

        // Auto-generate kode jika kosong
        if (empty($data['kode_supplier'])) {
            $last = Supplier::orderByDesc('id')->first();
            $seq  = $last ? ((int) substr($last->kode_supplier, 4)) + 1 : 1;
            $data['kode_supplier'] = 'SUP-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
        }

        $data['is_active'] = true;
        Supplier::create($data);

        return redirect()->route('supplier.index')
            ->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function show(Supplier $supplier)
    {
        $supplier->loadCount('purchaseOrders');
        $recentPo = $supplier->purchaseOrders()
            ->with('cabang')
            ->orderByDesc('tanggal_po')
            ->limit(5)
            ->get();

        return view('supplier.show', compact('supplier', 'recentPo'));
    }

    public function edit(Supplier $supplier)
    {
        return view('supplier.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());

        return redirect()->route('supplier.index')
            ->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier, CascadeDeleteService $cascadeService)
    {
        try {
            $nama    = $supplier->nama_supplier;
            $deleted = $cascadeService->deleteSupplierCascade($supplier);

            $labels = ['purchase_order' => 'Purchase Order', 'supplier' => 'Supplier'];
            $ringkasan = collect($deleted)->map(fn($c, $k) => "{$c} " . ($labels[$k] ?? $k))->filter()->join(', ');

            return redirect()->route('supplier.index')
                ->with('success', "Supplier <strong>{$nama}</strong> beserta data terkait berhasil dihapus. ({$ringkasan})");
        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus supplier: " . $e->getMessage());
        }
    }

    public function toggleAktif(Supplier $supplier)
    {
        $supplier->update(['is_active' => !$supplier->is_active]);
        $status = $supplier->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Supplier berhasil {$status}.");
    }
}
