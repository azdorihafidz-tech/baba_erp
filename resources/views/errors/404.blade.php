@php
    $authUser = auth()->user();
@endphp

@extends('layouts.app')

@section('title', '404 - Halaman Tidak Ditemukan')

@section('content')
<div class="d-flex justify-content-center align-items-center py-5" style="min-height:60vh">
    <div class="text-center px-3" style="max-width:520px;width:100%">

        {{-- Logo D'mentai --}}
        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
             style="width:64px;height:64px;border-radius:16px;background:var(--brand-latar);box-shadow:0 4px 16px rgba(var(--brand-utama-rgb),0.2)">
            <img src="{{ $brand->logoUrl() }}" alt="{{ $brand->namaSingkat() }}" style="width:44px;height:44px;object-fit:contain">
        </div>

        {{-- Icon --}}
        <div class="mb-3">
            <i class="bi bi-search" style="font-size:80px;color:var(--brand-utama);opacity:0.85;line-height:1"></i>
        </div>

        {{-- HTTP Code + Heading --}}
        <div class="fw-bold text-muted mb-1" style="font-size:.8rem;letter-spacing:3px;text-transform:uppercase">Error 404</div>
        <h2 class="fw-bold mb-2" style="font-size:1.8rem;color:#1A1A1A">Halaman Tidak Ditemukan</h2>
        <p class="text-muted mb-4" style="font-size:.95rem;line-height:1.6">
            Halaman yang kamu cari tidak ditemukan.<br>
            Periksa kembali URL-nya atau kembali ke dashboard.
        </p>

        {{-- URL yang dicari --}}
        <div class="card border-0 shadow-sm text-start mb-4"
             style="background:var(--brand-latar);border-left:4px solid var(--brand-utama) !important">
            <div class="card-body py-3 px-4">
                <div class="fw-semibold mb-2" style="font-size:.75rem;color:var(--brand-utama-gelap);text-transform:uppercase;letter-spacing:1px">
                    <i class="bi bi-link-45deg me-1"></i>URL yang dicari
                </div>
                <code class="text-break" style="font-size:.8rem;color:#374151;word-break:break-all">
                    {{ request()->fullUrl() }}
                </code>
            </div>
        </div>

        <a href="{{ route('dashboard') }}" class="btn text-white px-4"
           style="min-height:44px;padding-top:10px;padding-bottom:10px;background:linear-gradient(90deg,var(--brand-sidebar),var(--brand-utama));border:none">
            <i class="bi bi-arrow-left me-2"></i>Kembali ke Dashboard
        </a>

    </div>
</div>
@endsection
