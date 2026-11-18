@extends('layouts.app')

@section('title', 'Aktivitas Tim IT')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Aktivitas Tim IT</h5>
    <x-panduan-button slug="aktivitas-tim-it" />
</div>

<div class="alert alert-info small py-2">
    Semua login dan tindakan (simpan, ubah, hapus) yang dilakukan tim IT di instalasi ini. Hanya Tim IT yang bisa melihat halaman ini (Owner tidak). Catatan tidak bisa dihapus.
</div>

<form method="GET" class="card card-body py-2 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Dari</label><input type="date" name="dari" value="{{ $dari->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Sampai</label><input type="date" name="sampai" value="{{ $sampai->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-12 col-md-auto"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button></div>
    </div>
</form>

<div class="card"><div class="list-group list-group-flush">
@forelse($log as $a)
    @php $p = $a->properties ?? collect(); @endphp
    <div class="list-group-item py-2 small">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div><strong>{{ $a->causer?->name ?? '—' }}</strong> — {{ $a->description }}
                @if($p->get('method'))<span class="text-muted">({{ $p->get('method') }} /{{ $p->get('url') }})</span>@endif
            </div>
            <div class="text-muted">{{ $a->created_at->format('d/m/Y H:i:s') }}@if($p->get('ip')) · IP {{ $p->get('ip') }}@endif</div>
        </div>
    </div>
@empty
    <div class="list-group-item text-center text-muted py-4">Tidak ada aktivitas Tim IT pada periode ini.</div>
@endforelse
</div></div>
<div class="mt-2">{{ $log->links() }}</div>
@endsection
