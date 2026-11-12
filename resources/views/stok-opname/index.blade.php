@extends('layouts.app')

@section('title', 'Stok Opname')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2 text-primary"></i>Stok Opname</h5>
    <div class="d-flex gap-2 flex-wrap">
        <x-panduan-button slug="stok-opname" />
        @if($bisaMulai)
        <a href="{{ route('stok-opname.mulai') }}" class="btn btn-sm btn-primary" data-uji="mulai-opname"><i class="bi bi-plus-lg me-1"></i>Mulai Opname</a>
        @endif
    </div>
</div>

<div class="d-flex flex-wrap gap-1 mb-2">
    @foreach(\App\Http\Controllers\StokOpnameController::TAB as $k => $label)
    <a href="{{ route('stok-opname.index', array_filter(['tab' => $k, 'lokasi' => $pilihLokasi])) }}" data-uji="tab-{{ $k }}"
       class="btn btn-sm {{ $tab === $k ? 'btn-dark' : 'btn-outline-secondary' }}">
        {{ $label }} <span class="badge {{ $k === 'menunggu' && $jumlah[$k] ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $jumlah[$k] }}</span>
    </a>
    @endforeach
</div>
@if($lokasi->count() > 1)
<form method="GET" class="mb-3" style="max-width:320px">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <select name="lokasi" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Lokasi">
        <option value="">Semua lokasi</option>
        @foreach($lokasi as $l)<option value="{{ $l->id }}" @selected($pilihLokasi === $l->id)>{{ $l->nama_cabang }}</option>@endforeach
    </select>
</form>
@endif

<div class="d-flex flex-column gap-2">
@forelse($opname as $o)
<a href="{{ route('stok-opname.show', $o) }}" class="card text-reset text-decoration-none" data-uji="opname-{{ $o->nomor }}"><div class="card-body p-2 px-3">
    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
        <div><span class="fw-semibold">{{ $o->nomor }}</span> <span class="small text-muted">· {{ $o->cabang?->nama_cabang }} · {{ $o->created_at->format('d/m/Y H:i') }}</span></div>
        <span class="badge bg-{{ $o->status_warna }}">{{ $o->status_label }}</span>
    </div>
    <div class="small text-muted">{{ \App\Models\StokOpname::CAKUPAN[$o->cakupan] ?? $o->cakupan }}@if($o->cakupan_ket) — {{ \Illuminate\Support\Str::limit($o->cakupan_ket, 80) }}@endif · dimulai {{ $o->pembuat?->name ?? '-' }}</div>
    @if($o->status === 'selesai' && $o->ringkasan)
    <div class="small">Akurasi <strong>{{ $o->ringkasan['akurasi'] ?? 0 }}%</strong> · kurang <span class="text-danger">Rp {{ number_format($o->ringkasan['nilai_kurang'] ?? 0, 0, ',', '.') }}</span>
        · lebih <span class="text-success">Rp {{ number_format($o->ringkasan['nilai_lebih'] ?? 0, 0, ',', '.') }}</span></div>
    @else
    @php $pct = $o->item_count ? round($o->dihitung_count / $o->item_count * 100) : 0; @endphp
    <div class="d-flex align-items-center gap-2 small"><div class="progress flex-grow-1" style="height:6px"><div class="progress-bar" style="width:{{ $pct }}%"></div></div>
        <span class="text-muted text-nowrap">{{ $o->dihitung_count }}/{{ $o->item_count }} dihitung</span></div>
    @endif
</div></a>
@empty
<div class="text-center text-muted py-4">Belum ada opname di daftar ini.@if($bisaMulai) Tekan <strong>Mulai Opname</strong> untuk menghitung stok fisik.@endif</div>
@endforelse
</div>
<div class="mt-3">{{ $opname->links() }}</div>
@endsection
