@extends('layouts.app')

@section('title', 'Konsentrasi Parfum')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-droplet me-2 text-success"></i>Produksi Parfum</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="produksi" />
    </div>
</div>

@include('produksi._tabs')

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    Konsentrasi menentukan <strong>takaran racik cairan</strong>: % bibit dan % fixative dari volume cairan, <strong>sisanya alkohol</strong>,
    serta <strong>lama pemeraman</strong>. Dipakai saat membuat parfum baru. Mengubah angka di sini tidak mengubah resep yang sudah dibuat.
</div>

<div class="card mb-3"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Nama</th><th>Keterangan</th><th class="text-end">% Bibit <x-tooltip key="konsentrasi.persen_bibit" /></th><th class="text-end">% Fixative</th><th class="text-end">% Alkohol</th><th class="text-end">Lama peram <x-tooltip key="produksi.lama_peram" /></th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($konsentrasi as $k)
    <tr class="{{ $k->is_active ? '' : 'text-muted' }}">
        <td class="fw-semibold">{{ $k->nama }}</td>
        <td class="small">{{ $k->keterangan }}</td>
        <td class="text-end">{{ rtrim(rtrim(number_format($k->persen_bibit, 2, ',', '.'), '0'), ',') }}%</td>
        <td class="text-end">{{ rtrim(rtrim(number_format($k->persen_fixative, 2, ',', '.'), '0'), ',') }}%</td>
        <td class="text-end">{{ rtrim(rtrim(number_format($k->persen_alkohol, 2, ',', '.'), '0'), ',') }}%</td>
        <td class="text-end">{{ $k->lama_peram_hari }} hari</td>
        <td><span class="badge bg-{{ $k->is_active ? 'success' : 'secondary' }}">{{ $k->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td class="text-end"><button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#ubah{{ $k->id }}">Ubah</button></td>
    </tr>
    <tr class="collapse" id="ubah{{ $k->id }}"><td colspan="8" class="bg-light">
        @include('produksi._form-konsentrasi', ['k' => $k])
    </td></tr>
    @empty
    <tr><td colspan="8" class="text-center text-muted py-3">Belum ada konsentrasi.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>

<div class="card"><div class="card-header bg-white small fw-semibold"><i class="bi bi-plus-circle me-1"></i>Tambah Konsentrasi</div>
<div class="card-body">@include('produksi._form-konsentrasi', ['k' => null])</div></div>
@endsection
