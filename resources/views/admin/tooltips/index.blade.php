@extends('layouts.app')

@section('title', 'Tooltip Helper')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="bi bi-question-circle me-2 text-primary"></i>Tooltip Helper
            </h4>
            <p class="text-muted mb-0" style="font-size:.82rem">
                Kelola teks tooltip yang muncul di ikon ❓ pada form. Edit isi tanpa deploy ulang.
            </p>
        </div>
        <a href="{{ route('admin.tooltips.create') }}" class="btn btn-primary" style="min-height:44px">
            <i class="bi bi-plus-lg me-1"></i> Tambah Tooltip
        </a>
    </div>

    {{-- Alert --}}
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Filter --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="{{ route('admin.tooltips.index') }}"
                  class="row g-2 align-items-center">
                <div class="col-12 col-sm-5 col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Cari key tooltip..." value="{{ request('search') }}">
                </div>
                <div class="col-12 col-sm-4 col-md-3">
                    <select name="modul" class="form-select form-select-sm">
                        <option value="">— Semua Modul —</option>
                        @foreach($moduls as $m)
                            <option value="{{ $m }}" {{ request('modul') === $m ? 'selected' : '' }}>
                                {{ $m }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.tooltips.index') }}" class="btn btn-sm btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            {{-- Desktop --}}
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Key</th>
                            <th>Modul</th>
                            <th>Judul</th>
                            <th>Konten</th>
                            <th style="width:80px">Urutan</th>
                            <th style="width:80px">Status</th>
                            <th style="width:140px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tooltips as $tip)
                        <tr>
                            <td class="text-muted" style="font-size:.8rem">{{ $loop->iteration }}</td>
                            <td>
                                <code style="font-size:.8rem">{{ $tip->key }}</code>
                            </td>
                            <td>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle">
                                    {{ $tip->modul }}
                                </span>
                            </td>
                            <td style="font-size:.85rem">{{ $tip->title ?? '—' }}</td>
                            <td style="font-size:.82rem;max-width:240px">
                                <span class="text-truncate d-block" title="{{ $tip->content }}">
                                    {{ Str::limit($tip->content, 60) }}
                                </span>
                            </td>
                            <td class="text-center">{{ $tip->urutan }}</td>
                            <td>
                                @if($tip->aktif)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('admin.tooltips.edit', $tip) }}"
                                       class="btn btn-xs btn-outline-warning py-0 px-2"
                                       data-bs-toggle="tooltip" data-bs-title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST"
                                          action="{{ route('admin.tooltips.toggle-aktif', $tip) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="btn btn-xs {{ $tip->aktif ? 'btn-outline-secondary' : 'btn-outline-success' }} py-0 px-2"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="{{ $tip->aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                            <i class="bi {{ $tip->aktif ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST"
                                          action="{{ route('admin.tooltips.destroy', $tip) }}"
                                          onsubmit="return confirm('Hapus tooltip {{ addslashes($tip->key) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-xs btn-outline-danger py-0 px-2"
                                                data-bs-toggle="tooltip" data-bs-title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                Belum ada tooltip.
                                <a href="{{ route('admin.tooltips.create') }}">Tambah sekarang</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile card view --}}
            <div class="d-md-none">
                @forelse($tooltips as $tip)
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1" style="min-width:0">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <code style="font-size:.78rem">{{ $tip->key }}</code>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle"
                                      style="font-size:.7rem">{{ $tip->modul }}</span>
                                @if($tip->aktif)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"
                                          style="font-size:.7rem">Aktif</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border"
                                          style="font-size:.7rem">Nonaktif</span>
                                @endif
                            </div>
                            @if($tip->title)
                            <div class="fw-semibold" style="font-size:.82rem">{{ $tip->title }}</div>
                            @endif
                            <div class="text-muted" style="font-size:.8rem">{{ Str::limit($tip->content, 80) }}</div>
                        </div>
                        <div class="d-flex flex-column gap-1" style="flex-shrink:0">
                            <a href="{{ route('admin.tooltips.edit', $tip) }}"
                               class="btn btn-xs btn-outline-warning py-1 px-2">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST"
                                  action="{{ route('admin.tooltips.toggle-aktif', $tip) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="btn btn-xs {{ $tip->aktif ? 'btn-outline-secondary' : 'btn-outline-success' }} py-1 px-2 w-100">
                                    <i class="bi {{ $tip->aktif ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                                </button>
                            </form>
                            <form method="POST"
                                  action="{{ route('admin.tooltips.destroy', $tip) }}"
                                  onsubmit="return confirm('Hapus tooltip ini?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="btn btn-xs btn-outline-danger py-1 px-2 w-100">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                    Belum ada tooltip.
                </div>
                @endforelse
            </div>

        </div>
        @if($tooltips->hasPages())
        <div class="card-footer bg-white border-top-0">
            {{ $tooltips->appends(request()->query())->links() }}
        </div>
        @endif
    </div>

    {{-- Info box --}}
    <div class="alert alert-info alert-sm mt-3 d-flex align-items-start gap-2" style="font-size:.82rem">
        <i class="bi bi-lightbulb-fill mt-1 flex-shrink-0"></i>
        <div>
            <strong>Tips:</strong> Edit isi tooltip → klik Perbarui → cache otomatis terhapus → buka halaman
            terkait untuk melihat perubahan (tanpa restart server). Toggle "Nonaktif" untuk menyembunyikan
            ikon ❓ sementara tanpa menghapus data.
        </div>
    </div>

</div>
@endsection
