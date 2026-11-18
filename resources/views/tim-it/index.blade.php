@extends('layouts.app')

@section('title', 'Tim IT')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-gear me-2 text-primary"></i>Tim IT</h5>
    <x-panduan-button slug="tim-it" />
</div>

<div class="alert alert-info small py-2">
    Akun Tim IT tidak terlihat oleh pengguna BABA (tidak ada di Manajemen User maupun Audit Log), tidak bisa diubah Owner, dan tidak ada di aplikasi member.
    Semua login & tindakan Tim IT tercatat di <a href="{{ route('aktivitas-tim-it.index') }}">Keamanan → Aktivitas Tim IT</a> — hanya Tim IT yang bisa melihatnya.
    Kata sandi minimal 12 karakter (huruf + angka) dan <strong>berbeda di setiap instalasi</strong>.
</div>

<div class="card mb-3">
    <div class="list-group list-group-flush">
    @foreach($tim as $u)
        <div class="list-group-item py-2">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <div class="fw-semibold">{{ $u->name }} @if($u->id === auth()->id())<span class="badge bg-primary">Anda</span>@endif
                        @unless($u->is_active)<span class="badge bg-secondary">Nonaktif</span>@endunless</div>
                    <div class="small text-muted">{{ $u->email }} · login terakhir {{ isset($loginTerakhir[$u->id]) ? \Carbon\Carbon::parse($loginTerakhir[$u->id])->format('d/m/Y H:i') : '—' }}</div>
                </div>
                <div class="d-flex gap-1 flex-wrap">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#sandi{{ $u->id }}">Ganti kata sandi</button>
                    @if($u->id !== auth()->id())
                    <form method="POST" action="{{ route('tim-it.toggle', $u) }}">@csrf
                        <button class="btn btn-sm {{ $u->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                    </form>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('tim-it.password', $u) }}" class="collapse mt-2" id="sandi{{ $u->id }}">
                @csrf
                <div class="row g-2">
                    <div class="col-12 col-md-4"><input type="password" name="password" class="form-control form-control-sm" placeholder="Kata sandi baru (min. 12)" required minlength="12" autocomplete="new-password"></div>
                    <div class="col-12 col-md-4"><input type="password" name="password_confirmation" class="form-control form-control-sm" placeholder="Ulangi kata sandi" required autocomplete="new-password"></div>
                    <div class="col-12 col-md-auto"><button class="btn btn-sm btn-primary">Simpan</button></div>
                </div>
            </form>
        </div>
    @endforeach
    </div>
</div>

<div class="card">
    <div class="card-header py-2 fw-semibold">Tambah anggota Tim IT</div>
    <form method="POST" action="{{ route('tim-it.store') }}" class="card-body">
        @csrf
        <div class="row g-2">
            <div class="col-12 col-md-3"><input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-sm" placeholder="Nama" required maxlength="100"></div>
            <div class="col-12 col-md-3"><input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-sm" placeholder="Email" required></div>
            <div class="col-6 col-md-2"><input type="password" name="password" class="form-control form-control-sm" placeholder="Kata sandi (min. 12)" required minlength="12" autocomplete="new-password"></div>
            <div class="col-6 col-md-2"><input type="password" name="password_confirmation" class="form-control form-control-sm" placeholder="Ulangi" required autocomplete="new-password"></div>
            <div class="col-12 col-md-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-person-plus me-1"></i>Tambah</button></div>
        </div>
        @if($errors->any())<div class="text-danger small mt-2">{{ $errors->first() }}</div>@endif
    </form>
</div>
@endsection
