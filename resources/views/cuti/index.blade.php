@extends('layouts.app')

@section('title', 'Cuti & Izin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Cuti & Izin</h4>
        <small class="text-muted">Manajemen pengajuan cuti karyawan</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('cuti.create')
        <a href="{{ route('cuti.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Ajukan Cuti</span>
        </a>
        @endcan
        <x-panduan-button slug="cuti" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Nama / NIK / alasan..." col="col-12 col-md-3" />
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="disetujui" {{ request('status') === 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="ditolak" {{ request('status') === 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="karyawan_id" class="form-select form-select-sm">
                    <option value="">Semua Karyawan</option>
                    @foreach($karyawans as $k)
                    <option value="{{ $k->id }}" {{ request('karyawan_id') == $k->id ? 'selected' : '' }}>
                        {{ $k->nama_lengkap }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-search"></i></button>
                <a href="{{ route('cuti.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
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
                    <th>Tipe</th>
                    <th>Tanggal Mulai</th>
                    <th>Tanggal Selesai</th>
                    <th class="text-center">Hari</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cutis as $c)
                <tr>
                    <td>
                        <div class="fw-semibold small">{{ $c->karyawan?->nama_lengkap }}</div>
                        <small class="text-muted">{{ $c->cabang?->nama_cabang }}</small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $c->label_tipe }}</span>
                    </td>
                    <td>{{ $c->tanggal_mulai?->format('d/m/Y') }}</td>
                    <td>{{ $c->tanggal_selesai?->format('d/m/Y') }}</td>
                    <td class="text-center fw-bold">{{ $c->jumlah_hari }}</td>
                    <td>
                        @if($c->status === 'disetujui')
                            <span class="badge bg-success">Disetujui</span>
                        @elseif($c->status === 'ditolak')
                            <span class="badge bg-danger">Ditolak</span>
                        @else
                            <span class="badge bg-warning text-dark">Pending</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($c->status === 'pending')
                        @if(isset($authUser) && $authUser->can('cuti.approve'))
                        <form action="{{ route('cuti.approve', $c) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-xs btn-success btn-sm me-1" title="Setujui">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <form action="{{ route('cuti.tolak', $c) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-xs btn-danger btn-sm me-1" title="Tolak">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                        @endif
                        @can('cuti.create')
                        <a href="{{ route('cuti.edit', $c) }}" class="btn btn-xs btn-outline-secondary btn-sm me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @endcan
                        @can('cuti.delete')
                        <form action="{{ route('cuti.destroy', $c) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Hapus pengajuan cuti ini?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                        @endcan
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data cuti.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Card Mobile --}}
<div class="d-md-none">
    @forelse($cutis as $c)
    <div class="card mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">{{ $c->karyawan?->nama_lengkap }}</div>
                    <small class="text-muted">{{ $c->label_tipe }} &bull; {{ $c->jumlah_hari }} hari</small><br>
                    <small class="text-muted">{{ $c->tanggal_mulai?->format('d/m/Y') }} - {{ $c->tanggal_selesai?->format('d/m/Y') }}</small>
                </div>
                <div class="text-end">
                    @if($c->status === 'disetujui')
                        <span class="badge bg-success">Disetujui</span>
                    @elseif($c->status === 'ditolak')
                        <span class="badge bg-danger">Ditolak</span>
                    @else
                        <span class="badge bg-warning text-dark">Pending</span>
                    @endif
                    @if($c->status === 'pending' && isset($authUser) && $authUser->can('cuti.approve'))
                    <div class="mt-1 d-flex gap-1 justify-content-end">
                        <form action="{{ route('cuti.approve', $c) }}" method="POST">
                            @csrf
                            <button class="btn btn-xs btn-success btn-sm"><i class="bi bi-check-lg"></i></button>
                        </form>
                        <form action="{{ route('cuti.tolak', $c) }}" method="POST">
                            @csrf
                            <button class="btn btn-xs btn-danger btn-sm"><i class="bi bi-x-lg"></i></button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
            @if($c->alasan)
            <small class="text-muted d-block mt-1">{{ Str::limit($c->alasan, 80) }}</small>
            @endif
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-4">Tidak ada data cuti.</div>
    @endforelse
</div>

<div class="mt-3">{{ $cutis->links() }}</div>
@endsection
