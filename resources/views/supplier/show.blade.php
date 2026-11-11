@extends('layouts.app')

@section('title', 'Detail Supplier')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Detail Supplier</h5>
    <div class="d-flex gap-2">
        @can('supplier.manage')
        <a href="{{ route('supplier.edit', $supplier) }}" class="btn btn-sm btn-warning">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endcan
        <a href="{{ route('supplier.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-md-5">
        <div class="card h-100">
            <div class="card-header">Informasi Supplier</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:40%">Kode</td><td><code class="text-primary fw-bold">{{ $supplier->kode_supplier }}</code></td></tr>
                    <tr><td class="text-muted">Nama</td><td class="fw-semibold">{{ $supplier->nama_supplier }}</td></tr>
                    <tr><td class="text-muted">Kontak</td><td>{{ $supplier->kontak_person ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Telepon</td><td>{{ $supplier->telepon ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Email</td><td>{{ $supplier->email ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Kota</td><td>{{ $supplier->kota ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Alamat</td><td>{{ $supplier->alamat ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Status</td>
                        <td>
                            @if($supplier->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Non-Aktif</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td class="text-muted">Total PO</td><td><span class="badge bg-primary">{{ $supplier->purchase_orders_count }}</span></td></tr>
                </table>
                @if($supplier->catatan)
                <hr>
                <p class="text-muted small mb-0"><i class="bi bi-chat-left-text me-1"></i>{{ $supplier->catatan }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-md-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>5 PO Terakhir</span>
                <a href="{{ route('pembelian.index') }}?search={{ $supplier->nama_supplier }}" class="btn btn-sm btn-outline-primary">
                    Lihat Semua PO
                </a>
            </div>
            <div class="card-body p-0">
                @if($recentPo->isEmpty())
                <div class="text-center text-muted py-4">Belum ada Purchase Order</div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nomor PO</th>
                                <th>Tanggal</th>
                                <th>Cabang</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentPo as $po)
                            <tr>
                                <td>
                                    <a href="{{ route('pembelian.show', $po) }}" class="text-decoration-none">
                                        {{ $po->nomor_po }}
                                    </a>
                                </td>
                                <td>{{ $po->tanggal_po->format('d/m/Y') }}</td>
                                <td>{{ $po->cabang?->nama_cabang ?? '-' }}</td>
                                <td class="text-end">Rp {{ number_format($po->total_harga, 0, ',', '.') }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $po->status->badgeClass() }}">{{ $po->status->label() }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
