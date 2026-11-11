@extends('layouts.app')
@section('title', 'Riwayat Closing')
@section('content')

@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Riwayat Closing</h4>
        <small class="text-muted">Arsip closing yang sudah disimpan — angka di sini tidak berubah walau data diedit belakangan.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('closing.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Ke Closing</a>
        <x-panduan-button slug="closing" />
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('closing.riwayat') }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua yang bisa saya lihat</option>
                    @if($bolehKonsolidasi)
                    <option value="semua" @selected(request('cabang_id') === 'semua')>Keseluruhan saja</option>
                    @endif
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected((string) request('cabang_id') === (string) $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1">Jenis</label>
                <select name="jenis" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="harian" @selected(request('jenis') === 'harian')>Harian</option>
                    <option value="bulanan" @selected(request('jenis') === 'bulanan')>Bulanan</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="final" @selected(request('status') === 'final')>Terkunci</option>
                    <option value="dibuka" @selected(request('status') === 'dibuka')>Dibuka</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-grid">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Periode</th><th>Jenis</th><th>Cabang</th><th class="text-end">Omzet</th>
                    <th class="text-end">Laba Bersih</th><th class="text-end">Selisih Fisik</th><th>Status</th><th>Ditutup</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($closings as $c)
                <tr>
                    <td>{{ $c->label_periode }} <span class="text-muted small">v{{ $c->versi }}</span></td>
                    <td class="small">{{ $c->jenis_label }}</td>
                    <td class="small">{{ $c->nama_cabang }}</td>
                    <td class="text-end">{{ $rp($c->omzet) }}</td>
                    <td class="text-end {{ (float) $c->laba_bersih < 0 ? 'text-danger' : '' }}">{{ $rp($c->laba_bersih) }}</td>
                    <td class="text-end small">
                        @if($c->total_selisih_fisik !== null)
                        <span class="{{ (float) $c->total_selisih_fisik < 0 ? 'text-danger' : ((float) $c->total_selisih_fisik > 0 ? 'text-success' : '') }}">{{ $rp($c->total_selisih_fisik) }}</span>
                        @else — @endif
                    </td>
                    <td>
                        @if($c->isFinal())
                        <span class="badge bg-success"><i class="bi bi-lock-fill"></i> Terkunci</span>
                        @else
                        <span class="badge bg-secondary" title="{{ $c->alasan_buka }}"><i class="bi bi-unlock"></i> Dibuka</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $c->ditutup_pada->format('d/m/Y H:i') }}<br>{{ $c->ditutupOleh?->name ?? '-' }}</td>
                    <td><a href="{{ route('closing.show', $c) }}" class="btn btn-outline-primary btn-sm">Lihat</a></td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada closing yang disimpan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $closings->links() }}</div>
@endsection
