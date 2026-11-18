@extends('layouts.app')

@section('title', 'Kamus: ' . $kamus->judul)

@section('content')
<div class="mb-2">
    <a href="{{ route('kamus-menu.index', $q !== '' ? ['q' => $q] : []) }}" class="small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>{{ $q !== '' ? 'Kembali ke hasil "' . $q . '"' : 'Semua Kamus Menu' }}
    </a>
</div>

<div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <small class="text-muted text-uppercase" style="letter-spacing:1px;font-size:.7rem">{{ $kamus->kelompok }}</small>
        <h5 class="mb-1 fw-bold"><i class="bi bi-search-heart me-2" style="color:var(--brand-utama)"></i>{{ $kamus->judul }}</h5>
        <div class="text-body-secondary">{{ $kamus->ringkasan }}</div>
    </div>
    @if($urlMenu)
    <a href="{{ $urlMenu }}" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>Buka menu ini</a>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body kamus-isi">
                {!! $kamus->konten_html !!}
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body small">
                <div class="fw-semibold mb-1"><i class="bi bi-signpost-2 me-1"></i>Letak</div>
                <div class="mb-3">{{ $kamus->letak }}</div>
                <div class="fw-semibold mb-1"><i class="bi bi-key me-1"></i>Siapa yang bisa membuka</div>
                @if(empty($izin))
                    <div>Semua user yang sudah login.</div>
                @else
                    <div class="mb-1">User yang role-nya punya <strong>salah satu</strong> centangan ini:</div>
                    <ul class="mb-1 ps-3">
                        @foreach($izin as $i)<li>{{ $i }}</li>@endforeach
                    </ul>
                    <div class="text-muted">Owner selalu bisa. Centangan diatur Owner di Pengaturan → Role &amp; Hak Akses.</div>
                @endif
            </div>
        </div>
        @if($terkait->isNotEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body small">
                <div class="fw-semibold mb-2"><i class="bi bi-diagram-3 me-1"></i>Menu terkait</div>
                @foreach($terkait as $t)
                <a href="{{ route('kamus-menu.show', $t->slug) }}" class="d-block text-decoration-none mb-2">
                    <span class="fw-semibold">{{ $t->judul }}</span><br>
                    <span class="text-muted">{{ $t->ringkasan }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

<style>
.kamus-isi h2 { font-size:1rem; font-weight:700; margin-top:1.3rem; margin-bottom:.5rem; padding-bottom:.25rem; border-bottom:2px solid rgba(var(--brand-utama-rgb), .25); }
.kamus-isi h2:first-child { margin-top:0; }
.kamus-isi h3 { font-size:.92rem; font-weight:700; margin-top:1rem; }
.kamus-isi p, .kamus-isi li { line-height:1.65; }
.kamus-isi ul, .kamus-isi ol { padding-left:1.3rem; }
.kamus-isi table { width:100%; border-collapse:collapse; margin-bottom:1rem; font-size:.85rem; display:block; overflow-x:auto; }
.kamus-isi th, .kamus-isi td { border:1px solid #e2e8f0; padding:.35rem .5rem; vertical-align:top; }
.kamus-isi th { background:var(--brand-latar); color:#1A1A1A; font-weight:600; }
.kamus-isi blockquote { border-left:4px solid var(--brand-utama); background:rgba(var(--brand-utama-rgb), .06); padding:.5rem .8rem; margin:0 0 1rem; border-radius:4px; }
.kamus-isi blockquote p:last-child { margin-bottom:0; }
.kamus-isi code { background:#f1f5f9; padding:.1em .35em; border-radius:3px; font-size:.85em; }
body.dark-mode .kamus-isi th, body.dark-mode .kamus-isi td { border-color:#334155; }
body.dark-mode .kamus-isi code { background:#334155; color:#e2e8f0; }
</style>
@endsection
