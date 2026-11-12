@extends('layouts.app')

@section('title', 'Stok Member')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-success"></i>Stok Member</h5>
    <div class="d-flex gap-2"><a href="{{ route('toko-online.stok-member.opname') }}" class="btn btn-sm btn-outline-danger" data-uji="opname-member"><i class="bi bi-clipboard-check me-1"></i>Stok opname member</a>
    <x-panduan-button slug="stok-member" /></div>
</div>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>
    Stok parfum milik member. Bertambah otomatis saat pesanan member <strong>diterima</strong> (dari gudang atau upline), berkurang saat member menjual ke downline. Hanya untuk dilihat.</div>

<form method="GET" class="mb-3" style="max-width:420px">
    <div class="input-group input-group-sm">
        <input type="search" name="cari" value="{{ $cari }}" class="form-control" placeholder="Cari nama, No ID, BP-xxxxx">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Member</th><th class="d-none d-md-table-cell">Jabatan · Cabang</th><th class="text-end">Jenis</th><th class="text-end">Botol</th><th class="text-end d-none d-sm-table-cell">Nilai (modal)</th><th></th></tr></thead>
    <tbody>
    @forelse($baris as $b)
    @php $a = $akun[$b->member_id] ?? null; @endphp
    <tr>
        <td><div class="fw-semibold">{{ $a?->nama ?? '#' . $b->member_id }}</div><div class="small text-muted">{{ $a?->no_id }} · {{ $a?->no_member }}</div></td>
        <td class="small d-none d-md-table-cell">{{ $a?->jabatan }} · {{ $a?->cabang }}</td>
        <td class="text-end">{{ $b->jenis }}</td>
        <td class="text-end fw-semibold">{{ (int) $b->total_qty }}</td>
        <td class="text-end d-none d-sm-table-cell">{{ $rp($b->total_nilai) }}</td>
        <td class="text-end"><a href="{{ route('toko-online.stok-member.show', $b->member_id) }}" class="btn btn-outline-primary btn-sm">Rinci</a></td>
    </tr>
    @empty
    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada member yang memegang stok.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $baris->links() }}</div>
@endsection
