@extends('layouts.app')

@section('title', 'Catatan RO')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-star me-2 text-success"></i>Catatan RO Belanja Online</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="catatan-ro" />
        @if(($jumlah['menunggu'] ?? 0) + ($jumlah['gagal'] ?? 0) > 0)
        <form method="POST" action="{{ route('toko-online.ro.kirim-ulang') }}">@csrf<button class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Kirim ulang yang tertunda</button></form>
        @endif
    </div>
</div>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>
    RO = jumlah botol. Mode RO saat ini: <strong>{{ \App\Services\RoService::MODE[$mode] }}</strong>
    @can('pengaturan.umum_edit') (ubah di <a href="{{ route('pengaturan.umum') }}">Pengaturan Umum</a>)@endcan.
    Setiap catatan menjadi dokumen RO di aplikasi member; yang dipicu dari aplikasi member atau gagal dikirim dikirim otomatis tiap menit.</div>

<div class="d-flex flex-wrap gap-1 mb-2">
    <a href="{{ route('toko-online.ro.index') }}" class="btn btn-sm {{ ! $status ? 'btn-dark' : 'btn-outline-secondary' }}">Semua</a>
    @foreach(\App\Models\RoCatatan::STATUS as $k => $label)
    <a href="{{ route('toko-online.ro.index', ['status' => $k]) }}" class="btn btn-sm {{ $status === $k ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $label }}
        <span class="badge {{ $k === 'gagal' && ($jumlah[$k] ?? 0) ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $jumlah[$k] ?? 0 }}</span></a>
    @endforeach
</div>
<form method="GET" class="mb-3" style="max-width:420px">
    @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
    <div class="input-group input-group-sm"><input type="search" name="cari" value="{{ $cari }}" class="form-control" placeholder="Cari nama, No ID, nomor pesanan/retur"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></div>
</form>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0 small">
    <thead><tr><th>Tanggal</th><th>Member</th><th>Asal</th><th class="text-end">RO</th><th class="d-none d-md-table-cell">Approve</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($catatan as $c)
    <tr>
        <td class="text-nowrap">{{ $c->tanggal->format('d/m/Y') }}</td>
        <td>{{ $c->member?->nama ?? '#' . $c->member_id }}<div class="text-muted">{{ $c->member?->no_id }}</div></td>
        <td>{{ \App\Models\RoCatatan::JENIS[$c->jenis] ?? $c->jenis }}<div class="text-muted">{{ $c->retur?->nomor ?? $c->pesanan?->nomor }}</div></td>
        <td class="text-end fw-semibold {{ $c->qty < 0 ? 'text-danger' : 'text-success' }}">{{ $c->qty > 0 ? '+' : '' }}{{ $c->qty }}</td>
        <td class="d-none d-md-table-cell">{{ $c->perlu_approve ? 'Menunggu approve dokumen' : 'Langsung dihitung' }}</td>
        <td><span class="badge bg-{{ $c->status === 'terkirim' ? 'success' : ($c->status === 'gagal' ? 'danger' : 'warning') }}">{{ \App\Models\RoCatatan::STATUS[$c->status] }}</span>
            @if($c->status === 'gagal')<div class="text-danger">{{ $c->pesan_galat }} ({{ $c->percobaan }}×)</div>@endif</td>
    </tr>
    @empty
    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada catatan RO.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $catatan->links() }}</div>
@endsection
