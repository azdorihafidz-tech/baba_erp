@extends('layouts.app')

@section('title', 'Retur Cabang')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left me-2 text-success"></i>Retur Cabang
        @if($cabang)<span class="text-muted fw-normal small">— {{ $cabang->nama_cabang }}</span>@endif
    </h5>
    <x-panduan-button slug="retur-cabang" />
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ $arah === 'keluar' ? 'active' : '' }}" href="{{ route('retur-cabang.index', ['arah' => 'keluar']) }}"><i class="bi bi-box-arrow-up me-1"></i>Retur Dikirim</a></li>
    <li class="nav-item"><a class="nav-link {{ $arah === 'masuk' ? 'active' : '' }}" href="{{ route('retur-cabang.index', ['arah' => 'masuk']) }}"><i class="bi bi-box-arrow-in-down me-1"></i>Retur Masuk
        @if($menunggu > 0)<span class="badge bg-danger ms-1">{{ $menunggu }}</span>@endif</a></li>
</ul>

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach(['' => 'Semua', 'menunggu' => 'Menunggu', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan'] as $k => $v)
    <a href="{{ route('retur-cabang.index', ['arah' => $arah, 'status' => $k]) }}" class="btn btn-sm {{ $status === $k ? 'btn-dark' : 'btn-outline-dark' }}">{{ $v }}</a>
    @endforeach
</div>

@if($arah === 'keluar')
<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Retur dibuat dari kiriman yang sudah diterima: menu <a href="{{ route('kiriman.index', ['arah' => 'masuk', 'status' => 'diterima']) }}">Kiriman Cabang → Barang Masuk → Diterima</a> → buka kiriman → <strong>Buat Retur</strong>.</div>
@endif

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>No. Retur</th><th>Tanggal</th><th>{{ $arah === 'masuk' ? 'Dari' : 'Ke' }}</th><th>Barang</th><th>Alasan</th><th>Uang</th><th class="text-end">Nilai</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($retur as $r)
    <tr>
        <td class="fw-semibold">{{ $r->kode_retur }}</td>
        <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
        <td>{{ $arah === 'masuk' ? $r->dariCabang?->nama_cabang : $r->keCabang?->nama_cabang }}</td>
        <td class="small">{{ $r->items->map(fn ($i) => ($i->item?->nama_item ?? '?') . ' ×' . \App\Services\ProduksiService::angka((float) $i->qty))->implode(', ') }}</td>
        <td>{{ $r->alasan_label }}</td>
        <td>@if($r->uang_kembali)<span class="badge bg-info">Kembali</span>@else<span class="badge bg-light text-dark border">Tidak</span>@endif</td>
        <td class="text-end">Rp {{ number_format((float) $r->nilai, 0, ',', '.') }}</td>
        <td>@include('kiriman._status', ['s' => $r->status])</td>
        <td class="text-end"><a href="{{ route('retur-cabang.show', $r) }}" class="btn btn-sm {{ $r->status === 'menunggu' && $arah === 'masuk' ? 'btn-primary' : 'btn-outline-primary' }}">{{ $r->status === 'menunggu' && $arah === 'masuk' ? 'Cek & Terima' : 'Detail' }}</a></td>
    </tr>
    @empty
    <tr><td colspan="9" class="text-center text-muted py-4">Belum ada retur.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $retur->links() }}</div>
@endsection
