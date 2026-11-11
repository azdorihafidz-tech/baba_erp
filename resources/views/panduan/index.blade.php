@extends('layouts.app')

@section('title', 'Panduan Pengguna')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-book text-info me-2"></i>Panduan Pengguna</h5>
        <small class="text-muted">Panduan cara pakai fitur-fitur sistem</small>
    </div>
</div>

{{-- Search --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('panduan.index') }}" class="d-flex gap-2">
            <input type="text" name="search" class="form-control"
                   placeholder="Cari panduan..." value="{{ $search ?? '' }}"
                   style="max-width:320px">
            <button type="submit" class="btn btn-primary px-3">
                <i class="bi bi-search"></i>
            </button>
            @if($search)
            <a href="{{ route('panduan.index') }}" class="btn btn-outline-secondary">Reset</a>
            @endif
        </form>
    </div>
</div>

@if($byModul->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-book" style="font-size:3rem;opacity:.3"></i>
        <p class="mt-2">
            @if($search)
                Tidak ada panduan yang cocok dengan "<strong>{{ $search }}</strong>"
            @else
                Belum ada panduan tersedia.
            @endif
        </p>
    </div>
@else
    {{-- Group by modul --}}
    @foreach($byModul as $modul => $items)
    <div class="mb-4">
        <h6 class="fw-bold text-uppercase text-muted mb-2"
            style="font-size:.7rem;letter-spacing:2px;border-bottom:2px solid #e2e8f0;padding-bottom:.4rem">
            <i class="bi bi-folder2-open me-1"></i>{{ $modul }}
        </h6>
        <div class="row g-3">
            @foreach($items as $panduan)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <i class="bi bi-file-text text-info mt-1 flex-shrink-0" style="font-size:1.1rem"></i>
                            <div>
                                <div class="fw-semibold" style="font-size:.9rem">{{ $panduan->judul }}</div>
                                <code class="text-muted" style="font-size:.72rem">{{ $panduan->slug }}</code>
                            </div>
                        </div>
                        <div class="mt-auto pt-2">
                            <x-panduan-button slug="{{ $panduan->slug }}" label="Baca Panduan" variant="outline-info" />
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
@endif
@endsection
