@extends('layouts.app')

@section('title', 'Stok ' . $akun->nama)

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-success"></i>Stok {{ $akun->nama }}</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="stok-member" />
        <a href="{{ route('toko-online.stok-member.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>
<p class="small text-muted">{{ $akun->no_id }} · {{ $akun->no_member }} · {{ $akun->jabatan }} · {{ $akun->cabang }}</p>

<div class="card mb-3"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Barang</th><th class="text-end">Stok</th><th class="text-end">Modal rata-rata</th></tr></thead>
    <tbody>
    @forelse($stok as $s)
    <tr class="{{ $s->qty > 0 ? '' : 'text-muted' }}"><td>{{ $s->item?->nama_item }}</td><td class="text-end fw-semibold">{{ $s->qty }} {{ $s->item?->satuan }}</td><td class="text-end">{{ $rp($s->nilai_rata) }}</td></tr>
    @empty
    <tr><td colspan="3" class="text-center text-muted py-3">Belum ada stok.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>

<div class="card"><div class="card-header small fw-semibold">Riwayat stok</div><div class="table-responsive">
<table class="table table-sm align-middle mb-0 small">
    <thead><tr><th>Tanggal</th><th>Barang</th><th>Jenis</th><th class="text-end">Jumlah</th><th class="text-end">Sisa</th><th class="d-none d-md-table-cell">Catatan</th></tr></thead>
    <tbody>
    @foreach($mutasi as $m)
    <tr>
        <td class="text-nowrap">{{ $m->created_at?->format('d/m/Y H:i') }}</td>
        <td>{{ $m->item?->nama_item }}</td>
        <td>{{ \App\Models\StokMemberMutasi::JENIS[$m->jenis] ?? $m->jenis }}</td>
        <td class="text-end fw-semibold {{ $m->qty > 0 ? 'text-success' : 'text-danger' }}">{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}</td>
        <td class="text-end">{{ $m->sisa }}</td>
        <td class="d-none d-md-table-cell">{{ $m->catatan }}</td>
    </tr>
    @endforeach
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $mutasi->links() }}</div>
@endsection
