@extends('layouts.app')

@section('title', 'Program Loyalty')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-award me-2 text-primary"></i>Program Loyalty</h4>
        <small class="text-muted">Tracking otomatis kumulatif kg giling pelanggan — bandingkan progress ke target hadiah</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('loyalty.klaim.view')
        <a href="{{ route('loyalty-klaim.index') }}" class="btn btn-outline-info btn-sm">
            <i class="bi bi-megaphone me-1"></i>Klaim Event
        </a>
        @endcan
        @can('loyalty.manage')
        <a href="{{ route('loyalty-program.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Program
        </a>
        @endcan
        <x-panduan-button slug="program-loyalty" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Nama Program</th>
                    <th class="d-none d-md-table-cell">Target</th>
                    <th class="d-none d-md-table-cell">Hadiah</th>
                    <th class="text-center">Pencapaian</th>
                    <th class="text-center" style="min-width:90px">Status</th>
                    <th class="text-center" style="min-width:100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $i => $program)
                <tr class="{{ $program->status !== 'aktif' ? 'text-muted' : '' }}">
                    <td class="small text-muted">{{ $programs->firstItem() + $i }}</td>
                    <td class="fw-semibold">
                        {{ $program->nama }}
                        @if($program->tipe_program === 'event_based')
                        <span class="badge bg-info-subtle text-info">Event</span>
                        @else
                        <span class="badge bg-primary-subtle text-primary">Auto</span>
                        @endif
                    </td>
                    <td class="d-none d-md-table-cell">
                        @if($program->tipe_program === 'event_based')
                        <span class="text-muted small">Klaim manual</span>
                        @else
                        {{ number_format($program->target_qty_kg, 0, ',', '.') }} {{ $program->satuan_qty }}
                        @endif
                    </td>
                    <td class="d-none d-md-table-cell small">{{ \Illuminate\Support\Str::limit($program->hadiah, 40) }}</td>
                    <td class="text-center">{{ $program->pencapaian_count }} pelanggan</td>
                    <td class="text-center">
                        @if($program->status === 'aktif')
                        <span class="badge bg-success-subtle text-success">Aktif</span>
                        @else
                        <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <a href="{{ route('loyalty-program.show', $program) }}" class="btn btn-sm btn-outline-primary px-2 py-1" title="Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('loyalty.manage')
                            <a href="{{ route('loyalty-program.edit', $program) }}" class="btn btn-sm btn-outline-warning px-2 py-1" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Belum ada program loyalty.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($programs->hasPages())
    <div class="card-footer">{{ $programs->links() }}</div>
    @endif
</div>
@endsection
