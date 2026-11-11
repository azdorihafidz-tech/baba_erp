@extends('layouts.app')

@section('title', 'Pemakaian Perlengkapan')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0">
            <i class="bi bi-box-arrow-up me-2 text-primary"></i>Pemakaian Perlengkapan
        </h5>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Pemakaian Perlengkapan</li>
            </ol>
        </nav>
    </div>
    @can('pemakaian_perlengkapan.create')
    <a href="{{ route('pemakaian-perlengkapan.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Catat Pemakaian
    </a>
    @endcan
</div>

{{-- FILTER --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Nama item..." col="col-12 col-sm-6 col-md-4" label="Cari Item" />
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Dari</label>
                <input type="date" name="dari" class="form-control form-control-sm" value="{{ request('dari') }}">
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Sampai</label>
                <input type="date" name="sampai" class="form-control form-control-sm" value="{{ request('sampai') }}">
            </div>
            <div class="col-6 col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            @if(request()->hasAny(['search','dari','sampai']))
            <div class="col-6 col-md-1">
                <a href="{{ route('pemakaian-perlengkapan.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- TABEL - Desktop --}}
<div class="card d-none d-md-block">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Item</th>
                        <th>Cabang</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Nilai</th>
                        <th>Keterangan</th>
                        <th>Dicatat oleh</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pemakaians as $p)
                    <tr>
                        <td>{{ $p->tanggal_pemakaian->format('d/m/Y') }}</td>
                        <td class="fw-semibold">{{ $p->item?->nama_item ?? '—' }}</td>
                        <td>{{ $p->cabang?->nama_cabang ?? '—' }}</td>
                        <td class="text-end">{{ number_format($p->qty, 3) }} {{ $p->item?->satuan }}</td>
                        <td class="text-end">Rp {{ number_format($p->nilai, 0, ',', '.') }}</td>
                        <td class="small text-muted">{{ Str::limit($p->keterangan, 40) ?: '—' }}</td>
                        <td class="small text-muted">{{ $p->createdBy?->name ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('pemakaian-perlengkapan.show', $p) }}" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Belum ada pemakaian perlengkapan tercatat
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MOBILE CARD --}}
<div class="d-md-none">
    @forelse($pemakaians as $p)
    <div class="card mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                    <div class="fw-semibold">{{ $p->item?->nama_item ?? '—' }}</div>
                    <div class="text-muted small">{{ $p->tanggal_pemakaian->format('d/m/Y') }} — {{ $p->cabang?->nama_cabang ?? '—' }}</div>
                </div>
                <a href="{{ route('pemakaian-perlengkapan.show', $p) }}" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-eye"></i>
                </a>
            </div>
            <div class="small">
                <span class="text-muted">Qty:</span> {{ number_format($p->qty, 3) }} {{ $p->item?->satuan }}
                <span class="text-muted ms-2">Nilai:</span> Rp {{ number_format($p->nilai, 0, ',', '.') }}
            </div>
            @if($p->keterangan)
            <div class="text-muted small mt-1">{{ Str::limit($p->keterangan, 60) }}</div>
            @endif
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-3 d-block mb-2"></i>Belum ada pemakaian perlengkapan tercatat
    </div>
    @endforelse
</div>

<div class="mt-3">{{ $pemakaians->links() }}</div>
@endsection
