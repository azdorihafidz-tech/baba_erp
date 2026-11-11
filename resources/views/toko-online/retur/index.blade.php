@extends('layouts.app')

@section('title', 'Retur Online')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left me-2 text-success"></i>Retur Online</h5>
    <x-panduan-button slug="retur-online" />
</div>

<div class="d-flex flex-wrap gap-1 mb-3">
    @foreach(\App\Http\Controllers\ReturOnlineController::TAB as $k => $label)
    @continue(! isset($jumlah[$k]))
    <a href="{{ route('toko-online.retur.index', ['tab' => $k]) }}" class="btn btn-sm {{ $tab === $k ? 'btn-dark' : 'btn-outline-secondary' }}">
        {{ $label }} <span class="badge {{ $k === 'diajukan' && $jumlah[$k] ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $jumlah[$k] }}</span>
    </a>
    @endforeach
</div>
@if($tab === 'antar')
<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Retur pesanan member ke <strong>upline</strong> — diputuskan upline di Toko Saya aplikasi member. Di sini hanya dipantau. Angka = retur yang masih diajukan.</div>
@endif

<div class="d-flex flex-column gap-2">
@forelse($retur as $r)
<a href="{{ route('toko-online.retur.show', $r) }}" class="card text-reset text-decoration-none"><div class="card-body p-2 px-3">
    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
        <div><span class="fw-semibold">{{ $r->nomor }}</span> <span class="small text-muted">· pesanan {{ $r->pesanan?->nomor }} · {{ $r->created_at->format('d/m/Y H:i') }}</span></div>
        <span class="badge bg-{{ $r->status_warna }}">{{ $r->status_label }}</span>
    </div>
    <div class="small">{{ $r->member?->nama ?? '#' . $r->member_id }} <span class="text-muted">· {{ $r->member?->no_id }} · {{ $r->member?->jabatan }}</span></div>
    <div class="small text-muted text-truncate">{{ $r->item->map(fn ($i) => $i->nama . ' ×' . $i->qty)->implode(', ') }} — {{ $r->alasan }}</div>
    <div class="small fw-semibold text-end">{{ $rp($r->nilai) }}</div>
</div></a>
@empty
<div class="text-center text-muted py-4">Tidak ada retur di daftar ini.</div>
@endforelse
</div>
<div class="mt-3">{{ $retur->links() }}</div>
@endsection
