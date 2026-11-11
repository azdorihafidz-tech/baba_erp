@extends('layouts.app')

@section('title', 'Daftar Pelanggan')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-heart me-2 text-success"></i>Daftar Pelanggan</h5>
    <div class="d-flex gap-2 align-items-center">
        @can('pelanggan.create')
        <a href="{{ route('pelanggan.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Tambah Pelanggan
        </a>
        @endcan
        <x-panduan-button slug="pelanggan" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-sm-7 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                    placeholder="Cari nama / kode / telepon..." value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i>Cari
                </button>
            </div>
            @if(request('search'))
            <div class="col-6 col-md-2">
                <a href="{{ route('pelanggan.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
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
                        <th>Nama Pelanggan</th>
                        <th>Telepon</th>
                        <th>Kota</th>
                        <th class="text-center">Jml Order</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pelanggans as $p)
                    <tr>
                        <td><code class="text-success">{{ $p->kode_pelanggan }}</code></td>
                        <td class="fw-semibold">{{ $p->nama_pelanggan }}</td>
                        <td>{{ $p->telepon ?? '-' }}</td>
                        <td>{{ $p->kota ?? '-' }}</td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark">{{ $p->orders_count }}</span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('pelanggan.show', $p) }}" class="btn btn-outline-secondary" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('pelanggan.edit')
                                <a href="{{ route('pelanggan.edit', $p) }}" class="btn btn-outline-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @endcan
                                @can('pelanggan.delete')
                                <button type="button" class="btn btn-outline-danger" title="Hapus"
                                    onclick="confirmHapus('pelanggan', {{ $p->id }}, '{{ addslashes($p->nama_pelanggan) }}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada data pelanggan
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
    @forelse($pelanggans as $p)
    <div class="card mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="fw-semibold">{{ $p->nama_pelanggan }}</div>
                    <code class="text-success small">{{ $p->kode_pelanggan }}</code>
                </div>
                <span class="badge bg-light text-dark">{{ $p->orders_count }} order</span>
            </div>
            <div class="text-muted small mb-2">
                @if($p->telepon)<div><i class="bi bi-telephone me-1"></i>{{ $p->telepon }}</div>@endif
                @if($p->kota)<div><i class="bi bi-geo-alt me-1"></i>{{ $p->kota }}</div>@endif
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('pelanggan.show', $p) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                </a>
                @can('pelanggan.edit')
                <a href="{{ route('pelanggan.edit', $p) }}" class="btn btn-sm btn-outline-warning flex-fill">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                @endcan
                @can('pelanggan.delete')
                <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="confirmHapus('pelanggan', {{ $p->id }}, '{{ addslashes($p->nama_pelanggan) }}')">
                    <i class="bi bi-trash"></i>
                </button>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada data pelanggan
    </div>
    @endforelse
</div>

<div class="mt-3">{{ $pelanggans->links() }}</div>

@include('components.cascade-delete-modal', ['entity' => 'Pelanggan', 'childList' => ['Riwayat order/transaksi pelanggan', 'Detail item dalam setiap order']])

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
