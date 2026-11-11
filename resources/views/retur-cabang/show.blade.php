@extends('layouts.app')

@section('title', 'Retur ' . $retur->kode_retur)

@section('content')
@php
    $angka = fn ($n) => \App\Services\ProduksiService::angka((float) $n);
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $arahKembali = (\App\Support\CabangAktif::bolehAkses($retur->ke_cabang_id) && ! \App\Support\CabangAktif::bolehAkses($retur->dari_cabang_id)) ? 'masuk' : 'keluar';
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left me-2 text-success"></i>Retur {{ $retur->kode_retur }}
        <span class="ms-2">@include('kiriman._status', ['s' => $retur->status])</span></h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="retur-cabang" />
        <a href="{{ route('retur-cabang.index', ['arah' => $arahKembali]) }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card mb-3"><div class="card-body">
            <div class="row small g-2">
                <div class="col-6 col-md-3"><div class="text-muted">Dari</div><div class="fw-semibold">{{ $retur->dariCabang?->nama_cabang }}</div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Ke</div><div class="fw-semibold">{{ $retur->keCabang?->nama_cabang }}</div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Kiriman asal</div><a href="{{ route('kiriman.show', $retur->order_id) }}">{{ $retur->order?->nomor_order }}</a></div>
                <div class="col-6 col-md-3"><div class="text-muted">Alasan</div><div>{{ $retur->alasan_label }}</div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Uang dikembalikan</div><div>{{ $retur->uang_kembali ? 'Ya' : 'Tidak' }}</div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Dibuat</div><div>{{ $retur->created_at->format('d/m/Y H:i') }}<br><span class="text-muted">{{ $retur->dibuatOleh?->name }}</span></div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Diproses</div><div>@if($retur->diproses_at){{ $retur->diproses_at->format('d/m/Y H:i') }}<br><span class="text-muted">{{ $retur->diprosesOleh?->name }}</span>@else - @endif</div></div>
                @if($retur->orderKoreksi)
                <div class="col-6 col-md-3"><div class="text-muted">Koreksi penjualan <x-tooltip key="retur_cabang.koreksi" /></div><a href="{{ route('penjualan.show', $retur->orderKoreksi) }}">{{ $retur->orderKoreksi->nomor_order }}</a></div>
                @endif
            </div>
            @if($retur->catatan)<div class="small mt-3">Catatan: {{ $retur->catatan }}</div>@endif
            @if($retur->alasan_tolak)<div class="alert alert-secondary small mt-3 mb-0">Alasan {{ $retur->status }}: {{ $retur->alasan_tolak }}</div>@endif
        </div></div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Barang</th><th class="text-end">Qty</th><th class="text-end">Harga / unit</th><th class="text-end">Nilai</th></tr></thead>
                    <tbody>
                    @foreach($retur->items as $it)
                    <tr><td>{{ $it->item?->nama_item }}</td><td class="text-end">{{ $angka($it->qty) }} {{ $it->item?->satuan }}</td><td class="text-end">{{ $rp($it->harga_per_unit) }}</td><td class="text-end">{{ $rp($it->nilai) }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr class="fw-semibold"><td colspan="3" class="text-end">Total {{ $retur->uang_kembali ? 'uang kembali' : '(tanpa uang kembali)' }}</td><td class="text-end">{{ $rp($retur->nilai) }}</td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card"><div class="card-body">
            @if($bisaTerima)
                <h6 class="fw-bold">Terima retur</h6>
                <p class="small text-muted">Cek barang yang datang lalu klik Terima. Stok masuk ke {{ $retur->keCabang?->nama_cabang }}
                    @if($retur->uang_kembali)
                    dan kas tunai berkurang {{ $rp($retur->nilai) }} (dikembalikan ke {{ $retur->dariCabang?->nama_cabang }}).
                    @else
                    tanpa uang kembali (nilai stok Rp0).
                    @endif
                    Barang basi/rusak lalu dibuang lewat <strong>Penyesuaian Stok</strong>.</p>
                <form method="POST" action="{{ route('retur-cabang.terima', $retur) }}" onsubmit="return confirm('Terima retur ini?')">
                    @csrf <button class="btn btn-success w-100 mb-3"><i class="bi bi-check2-circle me-1"></i>Terima Retur</button>
                </form>
                <form method="POST" action="{{ route('retur-cabang.tolak', $retur) }}" onsubmit="return confirm('Tolak retur? Barang kembali ke stok cabang.')">
                    @csrf
                    <textarea name="alasan" rows="2" maxlength="500" required class="form-control form-control-sm mb-2" placeholder="Alasan menolak"></textarea>
                    <button class="btn btn-outline-danger btn-sm w-100">Tolak Retur</button>
                </form>
            @elseif($bisaBatal)
                <div class="small text-muted mb-3"><i class="bi bi-hourglass-split me-1"></i>Menunggu {{ $retur->keCabang?->nama_cabang }} menerima. Stok sudah keluar dari cabang ini.</div>
                <form method="POST" action="{{ route('retur-cabang.batalkan', $retur) }}" onsubmit="return confirm('Batalkan retur? Barang kembali ke stok cabang.')">
                    @csrf
                    <textarea name="alasan" rows="2" maxlength="500" required class="form-control form-control-sm mb-2" placeholder="Alasan membatalkan"></textarea>
                    <button class="btn btn-outline-danger btn-sm w-100">Batalkan Retur</button>
                </form>
            @elseif($retur->status === 'menunggu')
                <div class="small text-muted">Menunggu diproses {{ $retur->keCabang?->nama_cabang }}.</div>
            @elseif($retur->status === 'diterima')
                <div class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Retur diterima.
                    @if($retur->uang_kembali) Uang {{ $rp($retur->nilai) }} sudah kembali ke kas tunai {{ $retur->dariCabang?->nama_cabang }}. @endif
                </div>
            @else
                <div class="small text-muted">Retur {{ $retur->status }} — barang sudah kembali ke stok {{ $retur->dariCabang?->nama_cabang }}.</div>
            @endif
        </div></div>
    </div>
</div>
@endsection
