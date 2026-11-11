@extends('layouts.app')

@section('title', 'Hari Libur')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-calendar-x me-2 text-primary"></i>Hari Libur</h4>
        <small class="text-muted">Kelola hari libur nasional & khusus cabang</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('hari-libur.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Libur
        </a>
        <x-panduan-button slug="hari-libur" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label form-label-sm mb-1">Tahun</label>
                <select name="tahun" class="form-select form-select-sm" style="width:auto">
                    @for($y = now()->year + 1; $y >= now()->year - 2; $y--)
                    <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="form-label form-label-sm mb-1">Tipe</label>
                <select name="tipe" class="form-select form-select-sm" style="width:auto">
                    <option value="">Semua</option>
                    <option value="nasional" {{ $tipe === 'nasional' ? 'selected' : '' }}>Nasional</option>
                    <option value="cabang" {{ $tipe === 'cabang' ? 'selected' : '' }}>Cabang</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Nama Hari Libur</th>
                    <th>Tipe</th>
                    <th class="d-none d-md-table-cell">Cabang</th>
                    <th class="text-center" style="width:90px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liburs as $libur)
                <tr>
                    <td class="fw-semibold small" style="white-space:nowrap">
                        {{ $libur->tanggal->translatedFormat('D, d M Y') }}
                    </td>
                    <td>{{ $libur->nama }}</td>
                    <td>
                        @if($libur->tipe === 'nasional')
                        <span class="badge text-bg-primary" style="font-size:.72rem">Nasional</span>
                        @else
                        <span class="badge text-bg-warning text-dark" style="font-size:.72rem">Cabang</span>
                        @endif
                    </td>
                    <td class="d-none d-md-table-cell small text-muted">
                        {{ $libur->cabang?->nama_cabang ?? '—' }}
                    </td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <a href="{{ route('hari-libur.edit', $libur) }}" class="btn btn-outline-secondary btn-sm" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('hari-libur.destroy', $libur) }}"
                                  onsubmit="return confirm('Hapus hari libur {{ addslashes($libur->nama) }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-x display-6 d-block mb-2 opacity-25"></i>
                        Tidak ada data hari libur untuk tahun {{ $tahun }}.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($liburs->hasPages())
    <div class="card-footer py-2 px-3">{{ $liburs->links() }}</div>
    @endif
</div>
@endsection
