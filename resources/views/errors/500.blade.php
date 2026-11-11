@php
    $authUser = auth()->user();
    $errorRef  = strtoupper(substr(md5(microtime() . rand()), 0, 8));
    $errorTime = now()->format('d M Y H:i:s');
@endphp

@extends('layouts.app')

@section('title', '500 - Error Sistem')

@section('content')
<div class="d-flex justify-content-center align-items-center py-5" style="min-height:60vh">
    <div class="text-center px-3" style="max-width:500px;width:100%">

        {{-- Logo D'mentai --}}
        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
             style="width:64px;height:64px;border-radius:16px;background:var(--brand-latar);box-shadow:0 4px 16px rgba(var(--brand-utama-rgb),0.2)">
            <img src="{{ $brand->logoUrl() }}" alt="{{ $brand->namaSingkat() }}" style="width:44px;height:44px;object-fit:contain">
        </div>

        {{-- Icon --}}
        <div class="mb-3">
            <i class="bi bi-exclamation-triangle-fill"
               style="font-size:80px;color:var(--brand-utama);opacity:0.9;line-height:1"></i>
        </div>

        {{-- HTTP Code + Heading --}}
        <div class="fw-bold text-muted mb-1" style="font-size:.8rem;letter-spacing:3px;text-transform:uppercase">Error 500</div>
        <h2 class="fw-bold mb-2" style="font-size:1.8rem;color:#1A1A1A">Ada Masalah di Server</h2>
        <p class="text-muted mb-4" style="font-size:.95rem;line-height:1.6">
            Tim teknis sudah otomatis dinotifikasi.<br>
            Kalau butuh bantuan cepat, sertakan kode referensi di bawah ke Tim IT.
        </p>

        {{-- Info card: waktu + kode referensi --}}
        <div class="card border-0 shadow-sm text-start mb-4"
             style="background:var(--brand-latar);border-left:4px solid var(--brand-utama) !important">
            <div class="card-body py-3 px-4">
                <div class="fw-semibold mb-2" style="font-size:.75rem;color:var(--brand-utama-gelap);text-transform:uppercase;letter-spacing:1px">
                    <i class="bi bi-info-circle me-1"></i>Detail Error
                </div>
                <table class="table table-sm table-borderless mb-0" style="font-size:.83rem">
                    <tbody>
                        <tr>
                            <td class="text-muted ps-0 py-1" style="width:110px;vertical-align:middle">Waktu</td>
                            <td class="py-1">{{ $errorTime }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0 py-1" style="vertical-align:middle">Kode Referensi</td>
                            <td class="py-1">
                                <code class="fw-bold" style="color:var(--brand-utama);font-size:.9rem;letter-spacing:1px">
                                    {{ $errorRef }}
                                </code>
                                <button type="button"
                                        class="btn btn-xs btn-outline-secondary py-0 px-1 ms-2"
                                        style="font-size:.7rem"
                                        onclick="navigator.clipboard.writeText('{{ $errorRef }}').then(()=>this.textContent='Tersalin!').catch(()=>{})"
                                        title="Salin kode referensi">
                                    <i class="bi bi-copy"></i>
                                </button>
                            </td>
                        </tr>
                        @if($authUser)
                        <tr>
                            <td class="text-muted ps-0 py-1" style="vertical-align:middle">User</td>
                            <td class="py-1">{{ $authUser->name }} ({{ $authUser->email }})</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <a href="{{ route('dashboard') }}" class="btn text-white px-4"
           style="min-height:44px;padding-top:10px;padding-bottom:10px;background:linear-gradient(90deg,var(--brand-sidebar),var(--brand-utama));border:none">
            <i class="bi bi-arrow-left me-2"></i>Kembali ke Dashboard
        </a>

    </div>
</div>
@endsection
