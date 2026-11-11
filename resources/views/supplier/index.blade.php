@extends('layouts.app')

@section('title', 'Daftar Supplier')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Daftar Supplier</h5>
    <div class="d-flex gap-2 align-items-center">
        @can('supplier.manage')
        <a href="{{ route('supplier.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Tambah Supplier
        </a>
        @endcan
        <x-panduan-button slug="supplier" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                    placeholder="Cari nama / kode..." value="{{ request('search') }}">
            </div>
            <div class="col-12 col-sm-4 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status')=='aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status')=='nonaktif')>Non-Aktif</option>
                </select>
            </div>
            <div class="col-12 col-sm-2 col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Cari
                </button>
            </div>
            @if(request()->hasAny(['search','status']))
            <div class="col-12 col-sm-2 col-md-2">
                <a href="{{ route('supplier.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
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
                        <th>Kode</th>
                        <th>Nama Supplier</th>
                        <th>Kontak</th>
                        <th>Telepon</th>
                        <th>Kota</th>
                        <th class="text-center">Jml PO</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $s)
                    <tr>
                        <td><code class="text-primary">{{ $s->kode_supplier }}</code></td>
                        <td class="fw-semibold">{{ $s->nama_supplier }}</td>
                        <td>{{ $s->kontak_person ?? '-' }}</td>
                        <td>{{ $s->telepon ?? '-' }}</td>
                        <td>{{ $s->kota ?? '-' }}</td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark">{{ $s->purchase_orders_count }}</span>
                        </td>
                        <td class="text-center">
                            @if($s->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Non-Aktif</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('supplier.show', $s) }}" class="btn btn-outline-info" title="Lihat">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('supplier.manage')
                                <a href="{{ route('supplier.edit', $s) }}" class="btn btn-outline-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @endcan
                                @can('supplier.manage')
                                <form method="POST" action="{{ route('supplier.toggle-aktif', $s) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-outline-{{ $s->is_active ? 'secondary' : 'success' }}" title="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <i class="bi bi-{{ $s->is_active ? 'toggle-on' : 'toggle-off' }}"></i>
                                    </button>
                                </form>
                                @endcan
                                <button type="button" class="btn btn-outline-danger" title="Hapus"
                                    onclick="confirmHapus('supplier', {{ $s->id }}, '{{ addslashes($s->nama_supplier) }}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada data supplier
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
    @forelse($suppliers as $s)
    <div class="card mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="fw-semibold">{{ $s->nama_supplier }}</div>
                    <code class="text-primary small">{{ $s->kode_supplier }}</code>
                </div>
                @if($s->is_active)
                    <span class="badge bg-success">Aktif</span>
                @else
                    <span class="badge bg-secondary">Non-Aktif</span>
                @endif
            </div>
            <div class="text-muted small mb-2">
                @if($s->kontak_person)<div><i class="bi bi-person me-1"></i>{{ $s->kontak_person }}</div>@endif
                @if($s->telepon)<div><i class="bi bi-telephone me-1"></i>{{ $s->telepon }}</div>@endif
                @if($s->kota)<div><i class="bi bi-geo-alt me-1"></i>{{ $s->kota }}</div>@endif
                <div><i class="bi bi-receipt me-1"></i>{{ $s->purchase_orders_count }} PO</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('supplier.show', $s) }}" class="btn btn-sm btn-outline-info flex-fill">
                    <i class="bi bi-eye"></i> Lihat
                </a>
                @can('supplier.manage')
                <a href="{{ route('supplier.edit', $s) }}" class="btn btn-sm btn-outline-warning flex-fill">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                @endcan
                <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="confirmHapus('supplier', {{ $s->id }}, '{{ addslashes($s->nama_supplier) }}')">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada data supplier
    </div>
    @endforelse
</div>

{{-- Pagination --}}
<div class="mt-3">
    {{ $suppliers->links() }}
</div>
@include('components.cascade-delete-modal', ['entity' => 'Supplier', 'childList' => ['Purchase Order dari supplier ini']])

@push('scripts')
<script>
function confirmHapus(routePrefix, id, nama) {
    document.getElementById('cascadeModalEntityName').textContent = nama;
    document.getElementById('cascadeModalForm').action = '/' + routePrefix + '/' + id;
    new bootstrap.Modal(document.getElementById('cascadeDeleteModal')).show();
}
</script>
@endpush

@endsection
