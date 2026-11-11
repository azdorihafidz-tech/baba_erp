@extends('layouts.app')

@section('title', 'Kiriman Cabang')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-truck me-2 text-success"></i>Kiriman Cabang
        @if($cabang)<span class="text-muted fw-normal small">— {{ $cabang->nama_cabang }}</span>@endif
    </h5>
    <x-panduan-button slug="kiriman-cabang" />
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $arah === 'masuk' ? 'active' : '' }}" href="{{ route('kiriman.index', ['arah' => 'masuk']) }}">
            <i class="bi bi-box-arrow-in-down me-1"></i>Barang Masuk
            @if($menunggu > 0)<span class="badge bg-danger ms-1">{{ $menunggu }}</span>@endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $arah === 'keluar' ? 'active' : '' }}" href="{{ route('kiriman.index', ['arah' => 'keluar']) }}">
            <i class="bi bi-box-arrow-up me-1"></i>Barang Keluar
        </a>
    </li>
</ul>

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach(['menunggu' => 'Menunggu diterima', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan', '' => 'Semua'] as $k => $v)
    <a href="{{ route('kiriman.index', ['arah' => $arah, 'status' => $k]) }}" class="btn btn-sm {{ $status === $k ? 'btn-dark' : 'btn-outline-dark' }}">{{ $v }}</a>
    @endforeach
</div>

@if($arah === 'keluar')
<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Kiriman dibuat dari <strong>POS Gudang Pusat</strong>: pilih <strong>Jenis Pembeli → Kirim ke Cabang</strong> lalu cabang tujuannya.</div>
@endif

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>No. Kiriman</th><th>Tanggal Kirim</th><th>{{ $arah === 'masuk' ? 'Dari' : 'Ke' }}</th><th>Barang</th><th class="text-end">Nilai</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($kiriman as $k)
    <tr>
        <td class="fw-semibold">{{ $k->nomor_order }}</td>
        <td>{{ $k->created_at->format('d/m/Y H:i') }}</td>
        <td>{{ $arah === 'masuk' ? $k->cabang?->nama_cabang : $k->outletTujuan?->nama_cabang }}</td>
        <td class="small">{{ $k->items->map(fn ($i) => $i->nama_item . ' ×' . rtrim(rtrim(number_format((float) $i->qty, 3, ',', '.'), '0'), ','))->implode(', ') }}</td>
        <td class="text-end">Rp {{ number_format((float) $k->total_bayar, 0, ',', '.') }}</td>
        <td>@include('kiriman._status', ['s' => $k->status_kirim])</td>
        <td class="text-end"><a href="{{ route('kiriman.show', $k) }}" class="btn btn-sm {{ $k->status_kirim === 'menunggu' && $arah === 'masuk' ? 'btn-primary' : 'btn-outline-primary' }}">{{ $k->status_kirim === 'menunggu' && $arah === 'masuk' ? 'Cek & Terima' : 'Detail' }}</a></td>
    </tr>
    @empty
    <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada kiriman{{ $status ? ' dengan status ini' : '' }}.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $kiriman->links() }}</div>
@endsection
