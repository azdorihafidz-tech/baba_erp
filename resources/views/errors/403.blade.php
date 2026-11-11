@php
    $authUser = auth()->user();
@endphp

@extends('layouts.app')

@section('title', '403 - Akses Ditolak')

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
            <i class="bi bi-shield-x" style="font-size:80px;color:var(--brand-utama);opacity:0.9;line-height:1"></i>
        </div>

        {{-- HTTP Code + Heading --}}
        <div class="fw-bold text-muted mb-1" style="font-size:.8rem;letter-spacing:3px;text-transform:uppercase">Error 403</div>
        <h2 class="fw-bold mb-2" style="font-size:1.8rem;color:#1A1A1A">Akses Ditolak</h2>
        <p class="text-muted mb-4" style="font-size:.95rem;line-height:1.6">
            Kamu tidak punya akses ke halaman ini.<br>
            Hubungi admin jika kamu butuh akses ke fitur ini.
        </p>

        @auth
        {{-- Info user yang sedang login --}}
        @if($authUser)
        <div class="card border-0 shadow-sm text-start mb-4"
             style="background:var(--brand-latar);border-left:4px solid var(--brand-utama) !important">
            <div class="card-body py-3 px-4">
                <div class="fw-semibold mb-2" style="font-size:.75rem;color:var(--brand-utama-gelap);text-transform:uppercase;letter-spacing:1px">
                    <i class="bi bi-person-circle me-1"></i>Akun yang sedang login
                </div>
                <table class="table table-sm table-borderless mb-0" style="font-size:.83rem">
                    <tbody>
                        <tr>
                            <td class="text-muted ps-0 py-1" style="width:70px;vertical-align:middle">User</td>
                            <td class="fw-semibold py-1">{{ $authUser->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0 py-1" style="vertical-align:middle">Email</td>
                            <td class="py-1">{{ $authUser->email }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0 py-1" style="vertical-align:middle">Role</td>
                            <td class="py-1">
                                <span class="badge bg-warning text-dark">
                                    {{ $authUser->role?->label() ?? ($authUser->role?->value ?? '—') }}
                                </span>
                            </td>
                        </tr>
                        @php
                            try {
                                $userCabangs = $authUser->cabangs ?? collect();
                            } catch (\Throwable) {
                                $userCabangs = collect();
                            }
                        @endphp
                        @if($userCabangs->isNotEmpty())
                        <tr>
                            <td class="text-muted ps-0 py-1" style="vertical-align:middle">Cabang</td>
                            <td class="py-1">{{ $userCabangs->pluck('nama_cabang')->join(', ') }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <a href="{{ route('dashboard') }}" class="btn text-white px-4"
           style="min-height:44px;padding-top:10px;padding-bottom:10px;background:linear-gradient(90deg,var(--brand-sidebar),var(--brand-utama));border:none">
            <i class="bi bi-arrow-left me-2"></i>Kembali ke Dashboard
        </a>

        @else
        {{-- Belum login --}}
        <div class="alert d-flex align-items-center gap-2 text-start mb-4"
             style="font-size:.85rem;background:var(--brand-latar);border:1px solid rgba(var(--brand-utama-rgb),0.2);color:#1A1A1A">
            <i class="bi bi-info-circle-fill flex-shrink-0" style="color:var(--brand-utama)"></i>
            <span>
                Kamu belum masuk. Silakan
                <a href="{{ route('login') }}" class="fw-semibold" style="color:var(--brand-utama)">login terlebih dahulu</a>.
            </span>
        </div>
        <a href="{{ route('login') }}" class="btn text-white px-4"
           style="min-height:44px;padding-top:10px;padding-bottom:10px;background:linear-gradient(90deg,var(--brand-sidebar),var(--brand-utama));border:none">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login
        </a>
        @endauth

    </div>
</div>
@endsection
