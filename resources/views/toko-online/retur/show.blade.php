@extends('layouts.app')

@section('title', 'Retur ' . $r->nomor)

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); $lunas = $r->pesanan?->status_bayar === 'lunas'; @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left me-2 text-success"></i>Retur {{ $r->nomor }}
        <span class="badge bg-{{ $r->status_warna }} align-middle ms-1">{{ $r->status_label }}</span></h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="retur-online" />
        <a href="{{ route('toko-online.retur.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
<div class="col-12 col-lg-7">
    <div class="card mb-3"><div class="card-body small">
        <div class="row g-2">
            <div class="col-6 col-md-4"><div class="text-muted">Pembeli</div><div class="fw-semibold">{{ $r->member?->nama }}</div><div>{{ $r->member?->no_id }} · {{ $r->member?->jabatan }}</div></div>
            <div class="col-6 col-md-4"><div class="text-muted">Pesanan</div>
                @if($r->pesanan && ($r->sumber === 'gudang' || auth()->user()->canAccessAllBranches()))<a href="{{ route('toko-online.pesanan.show', $r->pesanan) }}">{{ $r->pesanan->nomor }}</a>@else{{ $r->pesanan?->nomor }}@endif
                <div>{{ $r->pesanan?->cara_bayar === 'tagihan' ? 'Tagihan' : ($r->pesanan?->cara_bayar === 'tunai' ? 'Tunai' : 'Transfer') }} · {{ $r->pesanan?->status_bayar_label }}</div></div>
            <div class="col-6 col-md-4"><div class="text-muted">Diajukan</div><div>{{ $r->created_at->format('d/m/Y H:i') }}</div></div>
            <div class="col-12"><div class="text-muted">Alasan</div><div style="white-space:pre-line">{{ $r->alasan }}</div></div>
            @if($r->catatan_penjual)<div class="col-12"><div class="text-muted">{{ $r->status === 'ditolak' ? 'Alasan ditolak' : 'Catatan penjual' }}</div><div>{{ $r->catatan_penjual }}</div></div>@endif
            @if($r->pengembalian)<div class="col-12"><div class="text-muted">Pengembalian nilai</div><div>{{ \App\Models\ReturOnline::PENGEMBALIAN[$r->pengembalian] }}</div></div>@endif
            @if($r->diputus_at)<div class="col-12 text-muted">Diputuskan {{ $r->diputus_at->format('d/m/Y H:i') }} oleh {{ $r->diputus_oleh_nama }}</div>@endif
        </div>
    </div></div>
    <div class="card mb-3"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Barang</th><th class="text-end">Jumlah</th><th class="text-end">Harga</th><th class="text-end">Nilai</th></tr></thead>
            <tbody>@foreach($r->item as $i)<tr><td>{{ $i->nama }}</td><td class="text-end">{{ $i->qty }}</td><td class="text-end">{{ $rp($i->harga) }}</td><td class="text-end">{{ $rp($i->subtotal) }}</td></tr>@endforeach</tbody>
            <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Nilai retur</td><td class="text-end">{{ $rp($r->nilai) }}</td></tr></tfoot>
        </table>
    </div></div>
</div>
<div class="col-12 col-lg-5">
    @if($r->foto)
    <div class="card mb-3"><div class="card-body">
        <div class="small fw-semibold mb-2">Foto dari pembeli</div>
        <a href="{{ route('toko-online.retur.foto', $r) }}" target="_blank"><img src="{{ route('toko-online.retur.foto', $r) }}" alt="Foto retur" class="img-fluid rounded border" style="max-height:300px"></a>
    </div></div>
    @endif

    @if($r->status === 'diajukan' && $r->sumber === 'gudang')
    <div class="card mb-3 border-warning"><div class="card-body">
        <h6 class="fw-bold">Putuskan retur</h6>
        <p class="small text-muted mb-2">Setujui setelah barang retur <strong>sudah kembali ke gudang</strong> dan diperiksa. Stok gudang bertambah, stok member berkurang.
            {{ $lunas ? 'Pesanan sudah lunas: kembalikan dana ke member.' : 'Pesanan belum lunas: nilai retur memotong tagihan.' }}</p>
        <form method="POST" action="{{ route('toko-online.retur.setujui', $r) }}" onsubmit="return confirm('Barang retur sudah kembali dan disetujui?')">
            @csrf
            @if($lunas)
            <select name="pengembalian" class="form-select form-select-sm mb-2" required>
                <option value="transfer">Dana ditransfer kembali</option>
                <option value="tunai">Dana dikembalikan tunai</option>
            </select>
            @endif
            <input type="text" name="catatan" maxlength="255" class="form-control form-control-sm mb-2" placeholder="Catatan (opsional, mis. barang sudah dicek)">
            <button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Setujui retur</button>
        </form>
        <hr>
        <form method="POST" action="{{ route('toko-online.retur.tolak', $r) }}" class="d-flex gap-2" onsubmit="return confirm('Tolak retur ini?')">
            @csrf
            <input type="text" name="alasan" maxlength="255" class="form-control form-control-sm" placeholder="Alasan ditolak (dikirim ke member)" required minlength="3">
            <button class="btn btn-outline-danger btn-sm text-nowrap">Tolak</button>
        </form>
    </div></div>
    @elseif($r->status === 'diajukan')
    <div class="alert alert-info small">Retur pesanan ke upline — diputuskan upline di Toko Saya aplikasi member.</div>
    @endif
</div>
</div>
@endsection
