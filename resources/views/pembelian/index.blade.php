@extends('layouts.app')

@section('title', 'Purchase Order')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-bag-check me-2 text-primary"></i>Purchase Order</h5>
    <div class="d-flex gap-2 flex-wrap">
        @can('pembelian.create')
        <a href="{{ route('pembelian.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Buat PO Normal
        </a>
        <a href="{{ route('pembelian.create') }}?pembelian_langsung=1" class="btn btn-warning btn-sm">
            <i class="bi bi-exclamation-triangle me-1"></i>PO Mendesak
        </a>
        @endcan
        <x-panduan-button slug="pembelian" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Nomor PO / supplier / catatan..." col="col-12 col-sm-6 col-md-4" />
            <div class="col-6 col-sm-3 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    @foreach($statuses as $s)
                    <option value="{{ $s->value }}" @selected(request('status') == $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <select name="tipe" class="form-select form-select-sm">
                    <option value="">Semua Jenis</option>
                    <option value="normal" @selected(request('tipe')=='normal')>Normal</option>
                    <option value="langsung" @selected(request('tipe')=='langsung')>Mendesak/Langsung</option>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <select name="status_bayar" class="form-select form-select-sm">
                    <option value="">Semua Pembayaran</option>
                    <option value="sudah" @selected(request('status_bayar')==='sudah')>Sudah Dibayar</option>
                    <option value="belum" @selected(request('status_bayar')==='belum')>Belum Dibayar</option>
                </select>
            </div>
            @if(isset($authUser) && $authUser->canAccessAllBranches() && $cabangs->isNotEmpty())
            <div class="col-12 col-sm-4 col-md-2">
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Lokasi</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected(request('cabang_id') == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            @if(request()->hasAny(['search','status','tipe','cabang_id','status_bayar']))
            <div class="col-6 col-md-1">
                <a href="{{ route('pembelian.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- Desktop Table --}}
<div class="card d-none d-md-block">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nomor PO</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Lokasi</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Jenis</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Pembayaran</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pembelians as $po)
                    <tr>
                        <td>
                            <a href="{{ route('pembelian.show', $po) }}" class="text-decoration-none fw-semibold">
                                {{ $po->nomor_po }}
                            </a>
                        </td>
                        <td>{{ $po->tanggal_po->format('d/m/Y') }}</td>
                        <td>{{ $po->supplier?->nama_supplier ?? '-' }}</td>
                        <td>{{ $po->cabang?->nama_cabang ?? '-' }}</td>
                        <td class="text-end">Rp {{ number_format($po->total_harga, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if($po->pembelian_langsung)
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Langsung
                                </span>
                            @else
                                <span class="badge bg-light text-dark">Normal</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $po->status->badgeClass() }}">{{ $po->status->label() }}</span>
                        </td>
                        <td class="text-center">
                            @if($po->status === \App\Enums\StatusPurchaseOrder::Diterima)
                                @php $sudahBayar = $statusBayar[$po->id] ?? false; @endphp
                                <span class="badge bg-{{ $sudahBayar ? 'success' : 'warning' }} {{ $sudahBayar ? '' : 'text-dark' }}">
                                    {{ $sudahBayar ? 'Sudah Dibayar' : 'Belum Dibayar' }}
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="{{ route('pembelian.show', $po) }}" class="btn btn-sm btn-outline-primary" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('pembelian.delete')
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                    onclick="confirmHapus('{{ addslashes($po->nomor_po) }}', '{{ route('pembelian.destroy', $po->id) }}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada Purchase Order
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Mobile Card View --}}
<div class="d-md-none">
    @forelse($pembelians as $po)
    <div class="card mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <a href="{{ route('pembelian.show', $po) }}" class="fw-semibold text-decoration-none">
                        {{ $po->nomor_po }}
                    </a>
                    <div class="text-muted small">{{ $po->tanggal_po->format('d/m/Y') }}</div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                    <span class="badge {{ $po->status->badgeClass() }}">{{ $po->status->label() }}</span>
                    @if($po->status === \App\Enums\StatusPurchaseOrder::Diterima)
                        @php $sudahBayarMobile = $statusBayar[$po->id] ?? false; @endphp
                        <span class="badge bg-{{ $sudahBayarMobile ? 'success' : 'warning' }} {{ $sudahBayarMobile ? '' : 'text-dark' }}">
                            {{ $sudahBayarMobile ? 'Sudah Dibayar' : 'Belum Dibayar' }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="text-muted small mb-2">
                <div><i class="bi bi-person me-1"></i>{{ $po->supplier?->nama_supplier ?? '-' }}</div>
                <div><i class="bi bi-geo-alt me-1"></i>{{ $po->cabang?->nama_cabang ?? '-' }}</div>
                <div><i class="bi bi-cash me-1"></i>Rp {{ number_format($po->total_harga, 0, ',', '.') }}</div>
            </div>
            <div class="d-flex gap-2 align-items-center">
                @if($po->pembelian_langsung)
                <span class="badge bg-warning text-dark me-auto">
                    <i class="bi bi-exclamation-triangle me-1"></i>Mendesak
                </span>
                @else
                <span class="me-auto"></span>
                @endif
                <a href="{{ route('pembelian.show', $po) }}" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-eye me-1"></i>Detail
                </a>
                @can('pembelian.delete')
                <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="confirmHapus('{{ addslashes($po->nomor_po) }}', '{{ route('pembelian.destroy', $po->id) }}')">
                    <i class="bi bi-trash"></i>
                </button>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada Purchase Order
    </div>
    @endforelse
</div>

<div class="mt-3">{{ $pembelians->links() }}</div>

@include('components.cascade-delete-modal', ['entity' => 'Purchase Order', 'childList' => ['Detail item / barang yang dipesan dalam PO ini']])

@push('scripts')
<script>
function confirmHapus(nama, deleteUrl) {
    document.getElementById('cascadeModalEntityName').textContent = nama;
    document.getElementById('cascadeModalForm').action = deleteUrl;
    new bootstrap.Modal(document.getElementById('cascadeDeleteModal')).show();
}
</script>
@endpush

@endsection
