@extends('layouts.app')

@section('title', 'Penilaian Karyawan 360°')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Penilaian Karyawan 360°</h4>
        <small class="text-muted">Manajemen periode penilaian triwulanan</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @if(isset($authUser) && $authUser->can('evaluasi.manage'))
        <a href="{{ route('evaluasi.period-create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Buat Periode Baru</span>
        </a>
        @endif
        <x-panduan-button slug="evaluasi" />
    </div>
</div>

{{-- Filter --}}
@if(isset($authUser) && $authUser->canAccessAllBranches())
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <div style="width:200px;max-width:100%">
                <x-search-box placeholder="Nama periode..." col="" />
            </div>
            <select name="cabang_id" class="form-select form-select-sm" style="width:auto">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $c)
                <option value="{{ $c->id }}" {{ request('cabang_id') == $c->id ? 'selected' : '' }}>{{ $c->nama_cabang }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
        </form>
    </div>
</div>
@endif

{{-- Tabel Desktop --}}
<div class="card d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th class="d-none d-lg-table-cell">Cabang</th>
                    <th>Tanggal</th>
                    <th>Deadline</th>
                    <th class="text-center">Karyawan</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($periods as $p)
                <tr>
                    <td><div class="fw-semibold">{{ $p->nama_periode }}</div></td>
                    <td class="d-none d-lg-table-cell text-muted small">{{ $p->cabang?->nama_cabang }}</td>
                    <td class="small">
                        {{ $p->tanggal_mulai?->format('d/m/Y') }} &mdash; {{ $p->tanggal_selesai?->format('d/m/Y') }}
                    </td>
                    <td class="small {{ $p->deadline_pengisian?->isPast() ? 'text-danger' : '' }}">
                        {{ $p->deadline_pengisian?->format('d/m/Y') }}
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border">{{ $p->evaluations_count }}</span>
                    </td>
                    <td>
                        @php $statusVal = is_object($p->status) ? $p->status->value : $p->status; @endphp
                        @if($statusVal === 'final')
                            <span class="badge bg-success">Final</span>
                        @elseif($statusVal === 'dibuka')
                            <span class="badge bg-primary">Dibuka</span>
                        @elseif($statusVal === 'ditutup')
                            <span class="badge bg-warning text-dark">Ditutup</span>
                        @else
                            <span class="badge bg-secondary">Draft</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if(isset($authUser) && $authUser->can('evaluasi.manage'))
                        <a href="{{ route('evaluasi.period-detail', $p) }}" class="btn btn-xs btn-outline-secondary btn-sm me-1" title="Detail">
                            <i class="bi bi-people"></i>
                        </a>
                        @if($statusVal === 'draft')
                        <form action="{{ route('evaluasi.period-open', $p) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-xs btn-success btn-sm me-1" title="Buka Periode">
                                <i class="bi bi-play-fill"></i>
                            </button>
                        </form>
                        @elseif($statusVal === 'dibuka')
                        <form action="{{ route('evaluasi.period-close', $p) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-xs btn-warning btn-sm me-1" title="Tutup Periode">
                                <i class="bi bi-stop-fill"></i>
                            </button>
                        </form>
                        @elseif($statusVal === 'ditutup')
                        <form action="{{ route('evaluasi.period-finalize', $p) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Finalisasi semua penilaian di periode ini?')">
                            @csrf
                            <button class="btn btn-xs btn-primary btn-sm me-1" title="Finalisasi">
                                <i class="bi bi-check2-all"></i>
                            </button>
                        </form>
                        @endif
                        <a href="{{ route('evaluasi.ranking', $p) }}" class="btn btn-xs btn-outline-info btn-sm me-1" title="Ranking">
                            <i class="bi bi-trophy"></i>
                        </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada periode penilaian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Card Mobile --}}
<div class="d-md-none">
    @forelse($periods as $p)
    @php $statusVal = is_object($p->status) ? $p->status->value : $p->status; @endphp
    <div class="card mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">{{ $p->nama_periode }}</div>
                    <small class="text-muted">{{ $p->cabang?->nama_cabang }}</small><br>
                    <small class="text-muted">Deadline: {{ $p->deadline_pengisian?->format('d/m/Y') }}</small><br>
                    <small class="text-muted">{{ $p->evaluations_count }} karyawan dinilai</small>
                </div>
                <div class="text-end">
                    @if($statusVal === 'final')
                        <span class="badge bg-success">Final</span>
                    @elseif($statusVal === 'dibuka')
                        <span class="badge bg-primary">Dibuka</span>
                    @elseif($statusVal === 'ditutup')
                        <span class="badge bg-warning text-dark">Ditutup</span>
                    @else
                        <span class="badge bg-secondary">Draft</span>
                    @endif
                    <div class="mt-1">
                        <a href="{{ route('evaluasi.ranking', $p) }}" class="btn btn-xs btn-outline-primary btn-sm">
                            <i class="bi bi-trophy"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-4">Belum ada periode penilaian.</div>
    @endforelse
</div>

<div class="mt-3">{{ $periods->links() }}</div>
@endsection
