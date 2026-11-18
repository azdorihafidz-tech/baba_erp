@extends('layouts.app')

@section('title', 'Kamus Menu')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-search-heart me-2" style="color:var(--brand-utama)"></i>Kamus Menu</h5>
        <small class="text-muted">Penjelasan setiap menu: fungsinya, syarat supaya muncul, alurnya, dan apa akibatnya ke stok, kas &amp; laporan.</small>
    </div>
    <x-panduan-button slug="kamus-menu" />
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('kamus-menu.index') }}" class="d-flex gap-2 flex-wrap">
            <input type="text" name="q" class="form-control" autofocus
                   placeholder="Ketik yang ingin diketahui, mis. pindah barang ke cabang, batal order, untung..."
                   value="{{ $q }}" style="max-width:520px;min-width:0;flex:1 1 240px">
            <button type="submit" class="btn btn-primary px-3"><i class="bi bi-search me-1"></i>Cari</button>
            <span class="align-self-center"><x-tooltip key="kamus_menu.cari" /></span>
            @if($q !== '')
            <a href="{{ route('kamus-menu.index') }}" class="btn btn-outline-secondary">Reset</a>
            @endif
        </form>
        @if($q === '')
        <div class="mt-2 d-flex flex-wrap gap-1 align-items-center">
            <small class="text-muted me-1">Contoh:</small>
            @foreach(['pindah barang ke toko', 'tambah stok', 'batal order', 'parfum habis di POS', 'pesanan member', 'ro belum masuk', 'harga member', 'untung', 'menu tidak muncul'] as $contoh)
            <a href="{{ route('kamus-menu.index', ['q' => $contoh]) }}" class="badge rounded-pill text-decoration-none border"
               style="background:var(--brand-latar);color:#1A1A1A;font-weight:500">{{ $contoh }}</a>
            @endforeach
        </div>
        @endif
    </div>
</div>

@if($q !== '')
    <h6 class="fw-bold mb-2">Hasil untuk "{{ $q }}" <span class="text-muted fw-normal">({{ $hasil->count() }})</span></h6>
    @forelse($hasil as $h)
        @php $k = $h['kamus']; @endphp
        <a href="{{ route('kamus-menu.show', ['slug' => $k->slug, 'q' => $q]) }}" class="card border-0 shadow-sm mb-2 text-decoration-none kamus-hasil">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between flex-wrap gap-1">
                    <span class="fw-semibold text-body">{{ $k->judul }}</span>
                    <small class="text-muted">{{ $k->letak }}</small>
                </div>
                <div class="small text-body-secondary">{{ $k->ringkasan }}</div>
                @if($h['cuplikan'])
                <div class="small mt-1 text-muted fst-italic">… {{ $h['cuplikan'] }}</div>
                @endif
            </div>
        </a>
    @empty
        <div class="text-center py-5 text-muted">
            <i class="bi bi-search" style="font-size:2.5rem;opacity:.3"></i>
            <p class="mt-2 mb-0">Tidak ada penjelasan yang cocok. Coba kata lain yang lebih umum, mis. "stok", "kas", "order".</p>
        </div>
    @endforelse
@else
    @foreach($perKelompok as $kelompok => $daftar)
    <div class="mb-4">
        <h6 class="fw-bold text-uppercase text-muted mb-2"
            style="font-size:.7rem;letter-spacing:2px;border-bottom:2px solid #e2e8f0;padding-bottom:.4rem">
            {{ $kelompok }}
        </h6>
        <div class="row g-2">
            @foreach($daftar as $k)
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ route('kamus-menu.show', $k->slug) }}" class="card border-0 shadow-sm h-100 text-decoration-none kamus-hasil">
                    <div class="card-body py-2">
                        <div class="fw-semibold text-body" style="font-size:.9rem">{{ $k->judul }}</div>
                        <div class="small text-body-secondary">{{ $k->ringkasan }}</div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
@endif

<style>
.kamus-hasil { transition: box-shadow .15s, transform .15s; }
.kamus-hasil:hover { box-shadow: 0 .35rem .9rem rgba(var(--brand-utama-rgb), .18) !important; transform: translateY(-1px); }
</style>
@endsection
