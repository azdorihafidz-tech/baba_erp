@extends('layouts.app')

@section('title', $panduan->judul)

@section('content')
<div class="mb-3">
    <a href="{{ route('panduan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Semua Panduan
    </a>
</div>

<div class="card border-0 shadow-sm" style="max-width:800px">
    <div class="card-header bg-transparent border-bottom py-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-book text-info" style="font-size:1.2rem"></i>
            <div>
                <h5 class="mb-0 fw-bold">{{ $panduan->judul }}</h5>
                <small class="text-muted">Modul: {{ $panduan->modul }}</small>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="panduan-content">
            {!! $panduan->konten_html !!}
        </div>
    </div>
</div>

@push('styles')
<style>
.panduan-content h1,.panduan-content h2,.panduan-content h3 {
    font-size:1.1rem;font-weight:700;margin-top:1.2rem;margin-bottom:.4rem;
}
.panduan-content h1 { font-size:1.25rem; }
.panduan-content ul,.panduan-content ol { padding-left:1.4rem;margin-bottom:.8rem; }
.panduan-content li { margin-bottom:.25rem;line-height:1.6; }
.panduan-content p { margin-bottom:.7rem;line-height:1.7; }
.panduan-content strong { font-weight:600; }
.panduan-content code {
    background:#f1f5f9;padding:.1em .35em;border-radius:3px;font-size:.85em;color:#0f172a;
}
.panduan-content hr { margin:1rem 0;border-color:#e2e8f0; }
body.dark-mode .panduan-content code { background:#334155;color:#e2e8f0; }
</style>
@endpush
@endsection
