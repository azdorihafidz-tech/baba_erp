@extends('layouts.app')

@section('title', 'Transfer Stok')

@push('styles')
<style>
    .transfer-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: white;
        transition: box-shadow 0.15s;
    }
    .transfer-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
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
            <i class="bi bi-arrow-left-right me-2 text-primary"></i>Transfer Stok
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('stok.index') }}" class="text-decoration-none">Stok</a></li>
                <li class="breadcrumb-item active">Transfer</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        @if($authUser->can('stok.transfer'))
        <a href="{{ route('stock-transfer.create') }}" class="btn btn-primary d-flex align-items-center gap-2" style="min-height:40px">
            <i class="bi bi-plus-lg"></i>
            <span>Buat Transfer</span>
        </a>
        @endif
        <x-panduan-button slug="transfer-stok" />
    </div>
</div>

{{-- TAB FILTER STATUS --}}
<div class="card mb-4">
    <div class="card-body py-2 px-3">
        <ul class="nav tab-filter flex-wrap gap-1">
            @php
                $statusTabs = [
                    ''           => 'Semua',
                    'draft'      => 'Draft',
                    'dikirim'    => 'Dikirim',
                    'diterima'   => 'Diterima',
                    'diterima_sebagian' => 'Sebagian',
                    'dibatalkan' => 'Dibatalkan',
                ];
            @endphp
            @foreach($statusTabs as $val => $lbl)
            <li class="nav-item">
                <a href="{{ route('stock-transfer.index', array_filter(['status' => $val, 'search' => request('search')])) }}"
                    class="nav-link {{ request('status', '') === $val ? 'active' : '' }}">
                    {{ $lbl }}
                </a>
            </li>
            @endforeach
        </ul>
        <form method="GET" class="row g-2 mt-2">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <x-search-box placeholder="Nomor transfer / cabang asal-tujuan / catatan..." col="col-12 col-sm-6 col-md-4" />
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            </div>
            @if(request('search'))
            <div class="col-auto">
                <a href="{{ route('stock-transfer.index', array_filter(['status' => request('status')])) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- TABEL - Desktop --}}
<div class="card d-none d-md-block">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-list-ul me-2 text-primary"></i>Daftar Transfer
        </span>
        <small class="text-muted">{{ $transfers->total() }} transfer</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="px-4">No. Transfer</th>
                    <th>Dari</th>
                    <th>Ke</th>
                    <th class="d-none d-lg-table-cell">Tgl Kirim</th>
                    <th class="d-none d-xl-table-cell">Tgl Terima</th>
                    <th class="text-center d-none d-lg-table-cell">Jml Item</th>
                    <th class="text-center">Status</th>
                    <th class="text-center px-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $tr)
                @php
                    $statusVal = is_object($tr->status) ? $tr->status->value : $tr->status;
                    $statusBadge = match($statusVal) {
                        'draft'             => 'bg-secondary-subtle text-secondary',
                        'dikirim'           => 'bg-info-subtle text-info',
                        'diterima_sebagian' => 'bg-warning-subtle text-warning',
                        'diterima'          => 'bg-success-subtle text-success',
                        'dibatalkan'        => 'bg-danger-subtle text-danger',
                        default             => 'bg-secondary-subtle text-secondary',
                    };
                    $statusLabel = match($statusVal) {
                        'draft'             => 'Draft',
                        'dikirim'           => 'Dikirim',
                        'diterima_sebagian' => 'Sebagian',
                        'diterima'          => 'Diterima',
                        'dibatalkan'        => 'Dibatalkan',
                        default             => ucfirst($statusVal ?? '—'),
                    };
                @endphp
                <tr>
                    <td class="px-4">
                        <code style="background:#f1f5f9;color:#475569;font-size:0.8rem;padding:0.2rem 0.5rem;border-radius:4px">
                            {{ $tr->nomor_transfer }}
                        </code>
                    </td>
                    <td>
                        <span style="font-size:0.88rem">{{ $tr->dariLokasi?->nama_cabang ?? '—' }}</span>
                    </td>
                    <td>
                        <span style="font-size:0.88rem">{{ $tr->keLokasi?->nama_cabang ?? '—' }}</span>
                    </td>
                    <td class="d-none d-lg-table-cell">
                        <span style="font-size:0.85rem">{{ $tr->tanggal_kirim?->format('d/m/Y') ?? '—' }}</span>
                    </td>
                    <td class="d-none d-xl-table-cell">
                        <span style="font-size:0.85rem">{{ $tr->tanggal_terima?->format('d/m/Y') ?? '—' }}</span>
                    </td>
                    <td class="text-center d-none d-lg-table-cell">
                        <span class="badge bg-secondary-subtle text-secondary">
                            {{ $tr->items_count ?? $tr->stockTransferItems?->count() ?? 0 }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $statusBadge }}" style="font-size:0.72rem">{{ $statusLabel }}</span>
                    </td>
                    <td class="text-center px-4">
                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                        <a href="{{ route('stock-transfer.show', $tr) }}"
                            class="btn btn-sm btn-outline-primary px-2 py-1"
                            title="Lihat Detail" style="min-width:32px;min-height:32px">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if(in_array($statusVal, ['draft','dikirim']) && $authUser->can('stok.transfer'))
                        <form method="POST" action="{{ route('stock-transfer.batalkan', $tr) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning px-2 py-1"
                                title="Batalkan" style="min-width:32px;min-height:32px"
                                onclick="return confirm('Batalkan transfer {{ $tr->nomor_transfer }}?{{ $statusVal === "dikirim" ? " Stok akan dikembalikan ke lokasi asal." : "" }}')">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </form>
                        @endif
                        @if($statusVal === 'dibatalkan' && $authUser->canAccessAllBranches())
                        <form method="POST" action="{{ route('stock-transfer.destroy', $tr) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1"
                                title="Hapus" style="min-width:32px;min-height:32px"
                                onclick="return confirm('Hapus transfer {{ $tr->nomor_transfer }} permanen?')">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                        @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
                            <p class="mt-2 mb-0">Belum ada data transfer stok.</p>
                            @if($authUser->can('stok.transfer'))
                            <a href="{{ route('stock-transfer.create') }}" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-plus-lg me-1"></i>Buat Transfer
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transfers->hasPages())
    <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <small class="text-muted">
            Menampilkan {{ $transfers->firstItem() }}–{{ $transfers->lastItem() }} dari {{ $transfers->total() }} data
        </small>
        {{ $transfers->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- MOBILE CARD VIEW --}}
<div class="d-md-none">
    <div class="mb-3">
        <small class="text-muted">{{ $transfers->total() }} transfer</small>
    </div>

    @forelse($transfers as $tr)
    @php
        $statusVal = is_object($tr->status) ? $tr->status->value : $tr->status;
        $statusBadge = match($statusVal) {
            'draft'             => 'bg-secondary-subtle text-secondary',
            'dikirim'           => 'bg-info-subtle text-info',
            'diterima_sebagian' => 'bg-warning-subtle text-warning',
            'diterima'          => 'bg-success-subtle text-success',
            'dibatalkan'        => 'bg-danger-subtle text-danger',
            default             => 'bg-secondary-subtle text-secondary',
        };
        $statusLabel = match($statusVal) {
            'draft'             => 'Draft',
            'dikirim'           => 'Dikirim',
            'diterima_sebagian' => 'Sebagian',
            'diterima'          => 'Diterima',
            'dibatalkan'        => 'Dibatalkan',
            default             => ucfirst($statusVal ?? '—'),
        };
    @endphp
    <div class="transfer-card p-3 mb-3">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <code style="background:#f1f5f9;color:#475569;font-size:0.78rem;padding:0.15rem 0.45rem;border-radius:4px">
                        {{ $tr->nomor_transfer }}
                    </code>
                    <span class="badge {{ $statusBadge }}" style="font-size:0.7rem">{{ $statusLabel }}</span>
                </div>
                <div class="d-flex align-items-center gap-1 text-muted" style="font-size:0.82rem">
                    <span>{{ $tr->dariLokasi?->nama_cabang ?? '—' }}</span>
                    <i class="bi bi-arrow-right"></i>
                    <span>{{ $tr->keLokasi?->nama_cabang ?? '—' }}</span>
                </div>
                <div class="text-muted mt-1" style="font-size:0.78rem">
                    <i class="bi bi-calendar me-1"></i>{{ $tr->tanggal_kirim?->format('d/m/Y') ?? '—' }}
                    &nbsp;·&nbsp;
                    <i class="bi bi-boxes me-1"></i>{{ $tr->items_count ?? $tr->stockTransferItems?->count() ?? 0 }} item
                </div>
            </div>
            <div class="d-flex flex-column gap-1 flex-shrink-0">
                <a href="{{ route('stock-transfer.show', $tr) }}"
                    class="btn btn-sm btn-outline-primary"
                    style="min-height:36px;min-width:36px;padding:0;display:flex;align-items:center;justify-content:center">
                    <i class="bi bi-eye"></i>
                </a>
                @if(in_array($statusVal, ['draft','dikirim']) && $authUser->can('stok.transfer'))
                <form method="POST" action="{{ route('stock-transfer.batalkan', $tr) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-warning w-100" style="min-height:36px"
                        onclick="return confirm('Batalkan transfer ini?')">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </form>
                @endif
                @if($statusVal === 'dibatalkan' && $authUser->canAccessAllBranches())
                <form method="POST" action="{{ route('stock-transfer.destroy', $tr) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100" style="min-height:36px"
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
        <p class="mt-2">Belum ada data transfer stok.</p>
        @if($authUser->can('stok.transfer'))
        <a href="{{ route('stock-transfer.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Buat Transfer
        </a>
        @endif
    </div>
    @endforelse

    @if($transfers->hasPages())
    <div class="mt-3">
        {{ $transfers->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

@endsection
