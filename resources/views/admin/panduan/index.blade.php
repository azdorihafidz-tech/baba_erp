@extends('layouts.app')

@section('title', 'Kelola Panduan')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-book text-info me-2"></i>Kelola Panduan</h5>
        <small class="text-muted">Manage konten panduan yang tampil di modal Cara Pakai</small>
    </div>
    <a href="{{ route('admin.panduan.create') }}" class="btn btn-success"
       style="min-height:40px;padding-top:8px">
        <i class="bi bi-plus-lg me-1"></i>Tambah Panduan
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Filter --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label form-label-sm mb-1">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       style="width:200px" placeholder="Judul / slug..."
                       value="{{ $search ?? '' }}">
            </div>
            <div>
                <label class="form-label form-label-sm mb-1">Modul</label>
                <select name="modul" class="form-select form-select-sm" style="width:140px">
                    <option value="">Semua</option>
                    @foreach($moduls as $m)
                    <option value="{{ $m }}" {{ ($modul ?? '') === $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-3">
                <i class="bi bi-search me-1"></i>Filter
            </button>
            @if($search || $modul)
            <a href="{{ route('admin.panduan.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            @endif
        </form>
    </div>
</div>

{{-- Desktop Table --}}
<div class="card border-0 shadow-sm d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">#</th>
                    <th>Judul</th>
                    <th style="width:110px">Modul</th>
                    <th style="width:60px" class="text-center">Urutan</th>
                    <th style="width:80px" class="text-center">Status</th>
                    <th style="width:140px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($panduan as $p)
                <tr>
                    <td class="text-muted small">{{ $p->id }}</td>
                    <td>
                        <div class="fw-semibold" style="font-size:.88rem">{{ $p->judul }}</div>
                        <code class="text-muted" style="font-size:.72rem">{{ $p->slug }}</code>
                    </td>
                    <td><span class="badge bg-light text-dark border">{{ $p->modul }}</span></td>
                    <td class="text-center">{{ $p->urutan }}</td>
                    <td class="text-center">
                        @if($p->aktif)
                        <span class="badge bg-success">Aktif</span>
                        @else
                        <span class="badge bg-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <a href="{{ route('admin.panduan.edit', $p) }}"
                               class="btn btn-xs btn-outline-warning py-1 px-2" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.panduan.toggle-aktif', $p) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="btn btn-xs {{ $p->aktif ? 'btn-outline-secondary' : 'btn-outline-success' }} py-1 px-2"
                                        title="{{ $p->aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                    <i class="bi bi-toggle-{{ $p->aktif ? 'on' : 'off' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.panduan.destroy', $p) }}"
                                  onsubmit="return confirm('Hapus panduan \'{{ addslashes($p->judul) }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger py-1 px-2" title="Hapus">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Belum ada panduan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($panduan->hasPages())
    <div class="card-footer bg-transparent">
        {{ $panduan->links() }}
    </div>
    @endif
</div>

{{-- Mobile Card --}}
<div class="d-md-none">
    @forelse($panduan as $p)
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold" style="font-size:.88rem">{{ $p->judul }}</div>
                    <code class="text-muted" style="font-size:.72rem">{{ $p->slug }}</code>
                    <div class="mt-1 d-flex gap-1 flex-wrap">
                        <span class="badge bg-light text-dark border" style="font-size:.7rem">{{ $p->modul }}</span>
                        @if($p->aktif)
                        <span class="badge bg-success" style="font-size:.7rem">Aktif</span>
                        @else
                        <span class="badge bg-secondary" style="font-size:.7rem">Nonaktif</span>
                        @endif
                    </div>
                </div>
                <div class="d-flex gap-1 flex-shrink-0">
                    <a href="{{ route('admin.panduan.edit', $p) }}" class="btn btn-sm btn-outline-warning p-1" style="width:34px;height:34px">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="{{ route('admin.panduan.toggle-aktif', $p) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-sm {{ $p->aktif ? 'btn-outline-secondary' : 'btn-outline-success' }} p-1" style="width:34px;height:34px">
                            <i class="bi bi-toggle-{{ $p->aktif ? 'on' : 'off' }}"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.panduan.destroy', $p) }}"
                          onsubmit="return confirm('Hapus panduan ini?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger p-1" style="width:34px;height:34px">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-4 text-muted">Belum ada panduan.</div>
    @endforelse
    @if($panduan->hasPages())
    <div class="mt-3">{{ $panduan->links() }}</div>
    @endif
</div>
@endsection
