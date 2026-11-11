@extends('layouts.app')

@section('title', 'Permintaan Stok')

@push('styles')
<style>
    .stat-mini {
        background: #f8fafc;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
    }
    .request-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: white;
        transition: box-shadow 0.15s;
    }
    .request-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
    .tab-filter .nav-link {
        font-size: 0.83rem;
        padding: 0.4rem 0.85rem;
        color: #64748b;
        border-radius: 6px;
    }
    .tab-filter .nav-link.active {
        background: #3b82f6;
        color: white;
        font-weight: 600;
    }
    .tab-filter .nav-link:not(.active):hover {
        background: #f1f5f9;
        color: #1e293b;
    }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-cart-plus me-2 text-primary"></i>Permintaan Stok
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('stok.index') }}" class="text-decoration-none">Stok</a></li>
                <li class="breadcrumb-item active">Permintaan</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        @can('stok.request')
        <a href="{{ route('stock-request.create') }}" class="btn btn-primary d-flex align-items-center gap-2" style="min-height:40px">
            <i class="bi bi-plus-lg"></i>
            <span>Buat Permintaan</span>
        </a>
        @endcan
        <x-panduan-button slug="permintaan-stok" />
    </div>
</div>

{{-- STAT CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:10px;background:#fefce8;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-hourglass-split text-warning" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['pending'] ?? 0 }}</div>
                <div class="text-muted" style="font-size:0.73rem">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-check-circle text-primary" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['disetujui'] ?? 0 }}</div>
                <div class="text-muted" style="font-size:0.73rem">Disetujui</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:10px;background:#f0f9ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-truck text-info" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['dikirim'] ?? 0 }}</div>
                <div class="text-muted" style="font-size:0.73rem">Dikirim</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-box-seam text-success" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['diterima'] ?? 0 }}</div>
                <div class="text-muted" style="font-size:0.73rem">Diterima</div>
            </div>
        </div>
    </div>
</div>

{{-- TAB FILTER --}}
<div class="card mb-4">
    <div class="card-body py-2 px-3">
        <ul class="nav tab-filter flex-wrap gap-1">
            @php
                $statusTabs = [
                    ''           => 'Semua',
                    'pending'    => 'Pending',
                    'disetujui'  => 'Disetujui',
                    'dikirim'    => 'Dikirim',
                    'diterima'   => 'Diterima',
                    'ditolak'    => 'Ditolak',
                    'dibatalkan' => 'Dibatalkan',
                ];
            @endphp
            @foreach($statusTabs as $val => $lbl)
            <li class="nav-item">
                <a href="{{ route('stock-request.index', array_filter(['status' => $val, 'search' => request('search')])) }}"
                    class="nav-link {{ request('status', '') === $val ? 'active' : '' }}">
                    {{ $lbl }}
                    @if($val && isset($stats[$val]))
                        <span class="badge {{ request('status') === $val ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary' }} ms-1" style="font-size:0.68rem">
                            {{ $stats[$val] }}
                        </span>
                    @endif
                </a>
            </li>
            @endforeach
        </ul>
        <form method="GET" class="row g-2 mt-2">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <x-search-box placeholder="Nomor request / cabang / catatan / dibuat oleh..." col="col-12 col-sm-6 col-md-4" />
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            </div>
            @if(request('search'))
            <div class="col-auto">
                <a href="{{ route('stock-request.index', array_filter(['status' => request('status')])) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- TABEL - Desktop --}}
<div class="card d-none d-md-block">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-list-ul me-2 text-primary"></i>Daftar Permintaan
        </span>
        <small class="text-muted">{{ $stockRequests->total() }} permintaan</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="px-4">No. Request</th>
                    <th>Cabang</th>
                    <th>Tanggal</th>
                    <th class="text-center">Jml Item</th>
                    <th class="text-center">Status</th>
                    <th class="d-none d-lg-table-cell">Dibuat Oleh</th>
                    <th class="text-center px-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stockRequests as $sr)
                <tr>
                    <td class="px-4">
                        <code style="background:#f1f5f9;color:#475569;font-size:0.8rem;padding:0.2rem 0.5rem;border-radius:4px">
                            {{ $sr->nomor_request }}
                        </code>
                    </td>
                    <td>
                        <span style="font-size:0.88rem">{{ $sr->cabang?->nama_cabang ?? '—' }}</span>
                    </td>
                    <td>
                        <span style="font-size:0.85rem">{{ $sr->tanggal_request?->format('d/m/Y') }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary-subtle text-secondary">
                            {{ $sr->items_count ?? $sr->stockRequestItems?->count() ?? 0 }}
                        </span>
                    </td>
                    <td class="text-center">
                        @php
                            $statusVal = is_object($sr->status) ? $sr->status->value : $sr->status;
                            $statusBadge = match($statusVal) {
                                'pending'   => 'bg-warning-subtle text-warning',
                                'disetujui' => 'bg-primary-subtle text-primary',
                                'ditolak'   => 'bg-danger-subtle text-danger',
                                'dikirim'   => 'bg-info-subtle text-info',
                                'diterima'  => 'bg-success-subtle text-success',
                                default     => 'bg-secondary-subtle text-secondary',
                            };
                            $statusLabel = match($statusVal) {
                                'pending'   => 'Pending',
                                'disetujui' => 'Disetujui',
                                'ditolak'   => 'Ditolak',
                                'dikirim'   => 'Dikirim',
                                'diterima'  => 'Diterima',
                                default     => ucfirst($statusVal ?? '—'),
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }}" style="font-size:0.72rem">{{ $statusLabel }}</span>
                    </td>
                    <td class="d-none d-lg-table-cell">
                        <span style="font-size:0.85rem">{{ $sr->createdBy?->name ?? '—' }}</span>
                    </td>
                    <td class="text-center px-4">
                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                        <a href="{{ route('stock-request.show', $sr) }}"
                            class="btn btn-sm btn-outline-primary px-2 py-1"
                            title="Lihat Detail" style="min-width:32px;min-height:32px">
                            <i class="bi bi-eye"></i>
                        </a>
                        @canany(['stok.request', 'stok.transfer'])
                        @if(in_array($statusVal, ['pending','disetujui']))
                        <form method="POST" action="{{ route('stock-request.batalkan', $sr) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning px-2 py-1"
                                title="Batalkan" style="min-width:32px;min-height:32px"
                                onclick="return confirm('Batalkan permintaan {{ $sr->nomor_request }}?')">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </form>
                        @endif
                        @endcanany
                        @if(in_array($statusVal, ['ditolak','dibatalkan']) && $authUser->canAccessAllBranches())
                        <form method="POST" action="{{ route('stock-request.destroy', $sr) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1"
                                title="Hapus" style="min-width:32px;min-height:32px"
                                onclick="return confirm('Hapus permintaan {{ $sr->nomor_request }} permanen?')">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                        @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
                            <p class="mt-2 mb-0">Belum ada permintaan stok.</p>
                            @can('stok.request')
                            <a href="{{ route('stock-request.create') }}" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-plus-lg me-1"></i>Buat Permintaan
                            </a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($stockRequests->hasPages())
    <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <small class="text-muted">
            Menampilkan {{ $stockRequests->firstItem() }}–{{ $stockRequests->lastItem() }} dari {{ $stockRequests->total() }} data
        </small>
        {{ $stockRequests->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- MOBILE CARD VIEW --}}
<div class="d-md-none">
    <div class="mb-3">
        <small class="text-muted">{{ $stockRequests->total() }} permintaan</small>
    </div>

    @forelse($stockRequests as $sr)
    @php
        $statusVal = is_object($sr->status) ? $sr->status->value : $sr->status;
        $statusBadge = match($statusVal) {
            'pending'   => 'bg-warning-subtle text-warning',
            'disetujui' => 'bg-primary-subtle text-primary',
            'ditolak'   => 'bg-danger-subtle text-danger',
            'dikirim'   => 'bg-info-subtle text-info',
            'diterima'  => 'bg-success-subtle text-success',
            default     => 'bg-secondary-subtle text-secondary',
        };
        $statusLabel = match($statusVal) {
            'pending'   => 'Pending',
            'disetujui' => 'Disetujui',
            'ditolak'   => 'Ditolak',
            'dikirim'   => 'Dikirim',
            'diterima'  => 'Diterima',
            default     => ucfirst($statusVal ?? '—'),
        };
    @endphp
    <div class="request-card p-3 mb-3">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <code style="background:#f1f5f9;color:#475569;font-size:0.78rem;padding:0.15rem 0.45rem;border-radius:4px">
                        {{ $sr->nomor_request }}
                    </code>
                    <span class="badge {{ $statusBadge }}" style="font-size:0.7rem">{{ $statusLabel }}</span>
                </div>
                <div class="text-muted" style="font-size:0.82rem">
                    <i class="bi bi-shop me-1"></i>{{ $sr->cabang?->nama_cabang ?? '—' }}
                    &nbsp;·&nbsp;
                    <i class="bi bi-calendar me-1"></i>{{ $sr->tanggal_request?->format('d/m/Y') }}
                </div>
                <div class="text-muted mt-1" style="font-size:0.78rem">
                    <i class="bi bi-boxes me-1"></i>{{ $sr->items_count ?? $sr->stockRequestItems?->count() ?? 0 }} item
                    &nbsp;·&nbsp;
                    <i class="bi bi-person me-1"></i>{{ $sr->createdBy?->name ?? '—' }}
                </div>
            </div>
            <div class="d-flex flex-column gap-1 flex-shrink-0">
                <a href="{{ route('stock-request.show', $sr) }}"
                    class="btn btn-sm btn-outline-primary"
                    style="min-height:36px;min-width:36px;padding:0;display:flex;align-items:center;justify-content:center">
                    <i class="bi bi-eye"></i>
                </a>
                @canany(['stok.request', 'stok.transfer'])
                @if(in_array($statusVal, ['pending','disetujui']))
                <form method="POST" action="{{ route('stock-request.batalkan', $sr) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-warning w-100"
                        style="min-height:36px"
                        onclick="return confirm('Batalkan permintaan ini?')">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </form>
                @endif
                @endcanany
                @if(in_array($statusVal, ['ditolak','dibatalkan']) && $authUser->canAccessAllBranches())
                <form method="POST" action="{{ route('stock-request.destroy', $sr) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                        style="min-height:36px"
                        onclick="return confirm('Hapus permanen?')">
                        <i class="bi bi-trash3"></i>
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
        <p class="mt-2">Belum ada permintaan stok.</p>
        @can('stok.request')
        <a href="{{ route('stock-request.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Buat Permintaan
        </a>
        @endcan
    </div>
    @endforelse

    @if($stockRequests->hasPages())
    <div class="mt-3">
        {{ $stockRequests->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

@endsection
