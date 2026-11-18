@extends('layouts.app')

@section('title', 'Tidak Termasuk Paket')

@section('content')
<div class="card mx-auto mt-4" style="max-width:560px">
    <div class="card-body text-center py-5">
        <i class="bi bi-box2-heart display-5 text-primary"></i>
        <h5 class="fw-bold mt-3">Fitur {{ $label }} tidak termasuk paket Anda</h5>
        <p class="text-muted mb-4">Menu ini belum aktif di paket aplikasi usaha Anda. Data yang pernah dibuat tetap aman.
            Untuk mengaktifkannya, hubungi tim IT penyedia aplikasi.</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="bi bi-house me-1"></i>Kembali ke Dashboard</a>
        @if(Route::has('kamus-menu.show'))<a href="{{ route('kamus-menu.show', 'cara-fitur-tidak-ada') }}" class="btn btn-outline-secondary ms-1">Kenapa menu tidak ada?</a>@endif
    </div>
</div>
@endsection
