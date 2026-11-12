@extends('layouts.app')

@section('title', 'Stok Opname Member')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2 text-danger"></i>Stok Opname Member</h5>
    <div class="d-flex gap-2"><x-panduan-button slug="stok-member" /><a href="{{ route('toko-online.stok-member.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-people"></i> Stok Member</a></div>
</div>
<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Stok opname yang dicatat member sendiri di <strong>Toko Saya › Stok Saya</strong> (hanya mengurangi stok, tanpa persetujuan). Nilai = modal member; tidak masuk keuangan BABA dan tidak mengubah RO. Hanya untuk dipantau.</div>

<form method="GET" class="row g-2 mb-3 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label small mb-0" for="dari">Dari</label><input type="date" name="dari" id="dari" class="form-control form-control-sm" value="{{ $dari->toDateString() }}"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-0" for="sampai">Sampai</label><input type="date" name="sampai" id="sampai" class="form-control form-control-sm" value="{{ $sampai->toDateString() }}"></div>
    <div class="col-12 col-md-4"><label class="form-label small mb-0" for="level">Rekap</label>
        <select name="level" id="level" class="form-select form-select-sm" data-uji="level"><option value="">Per member</option>
            @foreach(\App\Services\LaporanOnlineService::LEVEL_TIM as $k => $l)<option value="{{ $k }}" @selected($level === $k)>Per tim {{ $l }}</option>@endforeach</select></div>
    <div class="col-12 col-md-2"><button class="btn btn-sm btn-primary w-100">Tampilkan</button></div>
</form>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="card-body p-2"><div class="small text-muted">Opname</div><div class="fw-semibold">{{ $opname->count() }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body p-2"><div class="small text-muted">Member</div><div class="fw-semibold">{{ $perMember->count() }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body p-2"><div class="small text-muted">Barang berkurang</div><div class="fw-semibold">{{ $perMember->sum('botol') }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body p-2"><div class="small text-muted">Nilai (modal member)</div><div class="fw-semibold text-danger" data-uji="total-opname-member">{{ $rp($perMember->sum('nilai')) }}</div></div></div></div>
</div>

@if($galat)<div class="alert alert-warning small py-2">{{ $galat }} Rekap per member tetap tampil di bawah.</div>@endif
<div class="card"><div class="card-body p-2 table-responsive">
@if($perTim)
    <table class="table table-sm small mb-0" data-uji="per-tim">
        <thead><tr><th>Tim {{ \App\Services\LaporanOnlineService::LEVEL_TIM[$level] }}</th><th class="text-end">Member</th><th class="text-end">Opname</th><th class="text-end">Barang</th><th class="text-end">Nilai</th></tr></thead>
        <tbody>@foreach($perTim as $t)<tr><td>{{ $t['nama'] }}</td><td class="text-end">{{ $t['anggota'] }}</td><td class="text-end">{{ $t['kali'] }}</td><td class="text-end">{{ $t['botol'] }}</td><td class="text-end text-danger">{{ $rp($t['nilai']) }}</td></tr>@endforeach</tbody>
    </table>
@else
    <table class="table table-sm small mb-0" data-uji="per-member">
        <thead><tr><th>Member</th><th class="text-end">Opname</th><th class="text-end">Barang</th><th>Penyebab</th><th class="text-end">Nilai</th></tr></thead>
        <tbody>
        @forelse($perMember as $r)
            <tr><td><a href="{{ route('toko-online.stok-member.show', $r['member_id']) }}">{{ $r['akun']?->nama ?? '#' . $r['member_id'] }}</a> <span class="text-muted">· {{ $r['akun']?->no_id }} · {{ $r['akun']?->jabatan }}</span></td>
                <td class="text-end">{{ $r['kali'] }}</td><td class="text-end">{{ $r['botol'] }}</td>
                <td>{{ collect($r['penyebab'])->map(fn ($q, $k) => (\App\Services\StokOpnameService::PENYEBAB[$k]['label'] ?? $k) . ' ' . $q)->implode(', ') }}</td>
                <td class="text-end text-danger">{{ $rp($r['nilai']) }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada stok opname member pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
@endif
</div></div>
@endsection
