@extends('layouts.app')

@section('title', 'Pesanan Online')

@section('content')
@php $rp = fn ($n) => $n === null ? '—' : 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-bag-check me-2 text-success"></i>Pesanan Online</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="pesanan-online" />
    </div>
</div>

<div class="d-flex flex-wrap gap-1 mb-2">
    @foreach(\App\Http\Controllers\PesananOnlineController::TAB as $k => [$label])
    @continue(! isset($jumlah[$k]))
    <a href="{{ route('toko-online.pesanan.index', ['tab' => $k] + ($cari !== '' ? ['cari' => $cari] : [])) }}"
       class="btn btn-sm {{ $tab === $k ? 'btn-dark' : 'btn-outline-secondary' }}">
        {{ $label }} <span class="badge {{ $k === 'perlu' && $jumlah[$k] ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $jumlah[$k] }}</span>
    </a>
    @endforeach
</div>

<form method="GET" class="mb-3" style="max-width:420px">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="input-group input-group-sm">
        <input type="search" name="cari" value="{{ $cari }}" class="form-control" placeholder="Cari nomor pesanan, nama, No ID, BP-xxxxx">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </div>
</form>

@if($tab === 'antar')
<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Pesanan member ke <strong>upline</strong>. Diproses upline di menu Toko Saya aplikasi member; di sini hanya dipantau (admin bisa membatalkan pesanan yang macet). Angka = pesanan yang masih berjalan.</div>
@endif

{{-- Desktop: tabel --}}
<div class="card d-none d-md-block"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Nomor</th><th>Tanggal</th><th>Pembeli</th><th>Barang</th><th>Terima</th><th>Bayar</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($pesanan as $p)
    <tr>
        <td class="fw-semibold text-nowrap">{{ $p->nomor }}</td>
        <td class="small text-nowrap">{{ $p->created_at->format('d/m/Y H:i') }}</td>
        <td class="small">{{ $p->member?->nama ?? '#' . $p->member_id }}<div class="text-muted">{{ $p->member?->no_id }} · {{ $p->member?->jabatan }}</div></td>
        <td class="small">{{ $p->item->map(fn ($i) => $i->nama . ' ×' . $i->qty)->implode(', ') }}</td>
        <td class="small text-nowrap">{{ $p->cara_ambil === 'kirim' ? 'Dikirim' : 'Ambil sendiri' }}</td>
        <td class="small text-nowrap">{{ $p->cara_bayar === 'tagihan' ? 'Tagihan' : 'Transfer' }}<div class="{{ $p->status_bayar === 'lunas' ? 'text-success' : ($p->status_bayar === 'menunggu_cek' ? 'text-info fw-semibold' : 'text-muted') }}">{{ $p->status_bayar_label }}</div></td>
        <td class="text-end text-nowrap">{{ $rp($p->ongkir === null ? null : $p->total) }}@if($p->ongkir === null)<div class="small text-muted">{{ $rp($p->subtotal) }} + ongkir</div>@endif</td>
        <td><span class="badge bg-{{ $p->status_warna }}">{{ $p->status_label }}</span></td>
        <td class="text-end"><a href="{{ route('toko-online.pesanan.show', $p) }}" class="btn btn-outline-primary btn-sm">Buka</a></td>
    </tr>
    @empty
    <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada pesanan di daftar ini.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>

{{-- HP: kartu --}}
<div class="d-md-none d-flex flex-column gap-2">
    @forelse($pesanan as $p)
    <a href="{{ route('toko-online.pesanan.show', $p) }}" class="card text-reset text-decoration-none"><div class="card-body p-2">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="fw-semibold">{{ $p->nomor }}</div>
            <span class="badge bg-{{ $p->status_warna }}">{{ $p->status_label }}</span>
        </div>
        <div class="small">{{ $p->member?->nama ?? '#' . $p->member_id }} <span class="text-muted">· {{ $p->member?->jabatan }}</span></div>
        <div class="small text-muted text-truncate">{{ $p->item->map(fn ($i) => $i->nama . ' ×' . $i->qty)->implode(', ') }}</div>
        <div class="d-flex justify-content-between small mt-1">
            <span>{{ $p->cara_ambil === 'kirim' ? 'Dikirim' : 'Ambil sendiri' }} · {{ $p->cara_bayar === 'tagihan' ? 'Tagihan' : 'Transfer' }} · {{ $p->status_bayar_label }}</span>
            <span class="fw-semibold">{{ $p->ongkir === null ? $rp($p->subtotal) . ' + ongkir' : $rp($p->total) }}</span>
        </div>
    </div></a>
    @empty
    <div class="text-center text-muted py-4">Tidak ada pesanan di daftar ini.</div>
    @endforelse
</div>

<div class="mt-3">{{ $pesanan->links() }}</div>
@endsection
