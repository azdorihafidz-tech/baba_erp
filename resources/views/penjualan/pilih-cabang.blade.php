@extends('layouts.app')

@section('title', 'POS — Pilih Cabang')

@section('content')
{{-- POS dibuka saat cabang aktif = "Semua Cabang": penjualan harus tercatat di 1 cabang --}}
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <h5 class="fw-bold mb-0"><i class="bi bi-shop me-2 text-primary"></i>Pilih cabang untuk berjualan</h5>
                    <x-panduan-button slug="pos" />
                </div>
                <p class="text-muted small mb-3">
                    Cabang aktif saat ini <strong>Semua Cabang</strong>. Setiap penjualan harus tercatat di satu cabang
                    (stok, kas, dan laporan cabang itu), jadi pilih cabangnya dulu.
                </p>

                @forelse($pilihanCabang as $c)
                    <form method="POST" action="{{ route('cabang.switch') }}" class="mb-2">
                        @csrf
                        <input type="hidden" name="cabang_id" value="{{ $c->id }}">
                        <button type="submit" class="btn btn-outline-primary w-100 text-start d-flex justify-content-between align-items-center py-2">
                            <span><i class="bi bi-geo-alt-fill me-2"></i>{{ $c->nama_cabang }}</span>
                            <span class="badge bg-light text-dark border">{{ $c->kode_cabang }}</span>
                        </button>
                    </form>
                @empty
                    <div class="alert alert-warning mb-0">
                        Belum ada cabang aktif yang bisa berjualan. Hubungi Owner untuk mengatur cabang Anda.
                    </div>
                @endforelse

                <p class="text-muted small mt-3 mb-0">
                    Cabang aktif juga bisa diganti kapan saja lewat tombol cabang di bagian atas layar.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
