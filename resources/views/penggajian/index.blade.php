@extends('layouts.app')

@section('title', 'Penggajian')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Penggajian</h4>
        <small class="text-muted">Manajemen slip gaji karyawan</small>
    </div>
    <div class="d-flex gap-2">
        @can('penggajian.manage')
        <a href="{{ route('penggajian.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-person-plus me-1"></i><span class="d-none d-sm-inline">Buat Per Karyawan</span>
        </a>
        @endcan
        @can('penggajian.manage')
        <a href="{{ route('penggajian.generate') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-gear me-1"></i><span class="d-none d-sm-inline">Generate Massal</span>
        </a>
        @endcan
        <x-panduan-button slug="penggajian" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Nama / NIK karyawan..." col="col-12 col-md-3" />
            <div class="col-6 col-md-3">
                <input type="month" name="periode" class="form-control form-control-sm"
                       value="{{ request('periode') }}" placeholder="Periode">
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="disetujui" {{ request('status') === 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="dibayar" {{ request('status') === 'dibayar' ? 'selected' : '' }}>Dibayar</option>
                </select>
            </div>
            @if(isset($authUser) && $authUser->canAccessAllBranches())
            <div class="col-12 col-md-3">
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" {{ request('cabang_id') == $c->id ? 'selected' : '' }}>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-search"></i></button>
                <a href="{{ route('penggajian.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Tabel Desktop --}}
<div class="card d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th class="d-none d-lg-table-cell">Cabang</th>
                    <th>Periode</th>
                    <th class="text-end">Gaji Pokok</th>
                    <th class="text-end">Total Gaji</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($penggajians as $p)
                <tr>
                    <td>
                        <div class="fw-semibold small">{{ $p->karyawan?->nama_lengkap }}</div>
                        <small class="text-muted">{{ $p->karyawan?->jabatan }}</small>
                    </td>
                    <td class="d-none d-lg-table-cell text-muted small">{{ $p->cabang?->nama_cabang }}</td>
                    <td>{{ $p->periode }}</td>
                    <td class="text-end">Rp {{ number_format($p->gaji_pokok, 0, ',', '.') }}</td>
                    <td class="text-end fw-bold">Rp {{ number_format($p->total_gaji, 0, ',', '.') }}</td>
                    <td>
                        @if($p->status === 'dibayar')
                            <span class="badge bg-success">Dibayar</span>
                        @elseif($p->status === 'disetujui')
                            <span class="badge bg-primary">Disetujui</span>
                        @else
                            <span class="badge bg-secondary">Draft</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('penggajian.show', $p) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if($p->status === 'draft')
                        @can('penggajian.manage')
                        <a href="{{ route('penggajian.edit', $p) }}" class="btn btn-sm btn-outline-warning me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @endcan
                        @can('penggajian.manage')
                        <form action="{{ route('penggajian.destroy', $p) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Hapus slip gaji ini?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        @endcan
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data penggajian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Card Mobile --}}
<div class="d-md-none">
    @forelse($penggajians as $p)
    <div class="card mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">{{ $p->karyawan?->nama_lengkap }}</div>
                    <small class="text-muted">{{ $p->periode }} &bull; {{ $p->cabang?->nama_cabang }}</small>
                </div>
                <div class="text-end">
                    @if($p->status === 'dibayar')
                        <span class="badge bg-success">Dibayar</span>
                    @elseif($p->status === 'disetujui')
                        <span class="badge bg-primary">Disetujui</span>
                    @else
                        <span class="badge bg-secondary">Draft</span>
                    @endif
                    <div class="fw-bold mt-1">Rp {{ number_format($p->total_gaji, 0, ',', '.') }}</div>
                    <a href="{{ route('penggajian.show', $p) }}" class="btn btn-xs btn-outline-primary btn-sm mt-1">
                        <i class="bi bi-eye"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-4">Tidak ada data penggajian.</div>
    @endforelse
</div>

<div class="mt-3">{{ $penggajians->links() }}</div>
@endsection
