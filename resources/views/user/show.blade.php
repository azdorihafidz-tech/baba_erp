@extends('layouts.app')
@section('title', 'Profil ' . $user->name)

@push('styles')
<style>
.role-badge {
    font-size: 0.75rem; padding: 0.25rem 0.65rem;
    border-radius: 20px; font-weight: 600;
}
.role-owner     { background:#fef3c7; color:#92400e; }
.role-admin     { background:#dbeafe; color:#1e40af; }
.role-manajer   { background:#d1fae5; color:#065f46; }
.role-kasir     { background:#ede9fe; color:#5b21b6; }
.role-operator  { background:#fce7f3; color:#9d174d; }
.role-gudang    { background:#e0f2fe; color:#0369a1; }
.info-row { display:flex; justify-content:space-between; align-items:start; padding:.55rem 0; border-bottom:1px solid #f1f5f9; font-size:.875rem; }
.info-row:last-child { border-bottom:none; }
.info-label { color:#64748b; flex-shrink:0; margin-right:1rem; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b"><i class="bi bi-person-circle me-2 text-primary"></i>Profil User</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0" style="font-size:0.8rem">
            <li class="breadcrumb-item"><a href="{{ route('user.index') }}" class="text-decoration-none">User</a></li>
            <li class="breadcrumb-item active">{{ $user->name }}</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        @can('user.edit')
        <a href="{{ route('user.edit', $user) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i><span class="d-none d-sm-inline">Edit</span>
        </a>
        @endcan
        <a href="{{ route('user.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>{!! session('success') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>{!! session('error') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">
    {{-- Profil card --}}
    <div class="col-12 col-md-4 col-lg-3">
        <div class="card text-center mb-3">
            <div class="card-body p-4">
                <div style="width:80px;height:80px;border-radius:50%;background:{{ $user->is_active ? '#3b82f6' : '#94a3b8' }};color:white;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;margin:0 auto 1rem">
                    {{ strtoupper(substr($user->name,0,1)) }}
                </div>
                <h5 class="fw-bold mb-1">{{ $user->name }}</h5>
                <p class="text-muted mb-2" style="font-size:0.875rem">{{ $user->email }}</p>
                @php
                    $rc = match($user->role?->value) {
                        'owner','admin_pusat'  => 'role-owner',
                        'admin_gudang'         => 'role-gudang',
                        'manajer_cabang'       => 'role-manajer',
                        'kasir'                => 'role-kasir',
                        'operator_produksi'    => 'role-operator',
                        default                => 'role-admin',
                    };
                @endphp
                <span class="role-badge {{ $rc }}">{{ $user->role?->label() }}</span>
                <div class="mt-2">
                    @if($user->is_active)
                        <span class="badge bg-success-subtle text-success">Aktif</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                    @endif
                </div>
            </div>
        </div>
        {{-- Aksi cepat --}}
        <div class="card">
            <div class="card-body p-3 d-flex flex-column gap-2">
                @can('user.edit')
                <a href="{{ route('user.edit', $user) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-2"></i>Edit User
                </a>
                @endcan
                @can('user.edit')
                <form method="POST" action="{{ route('user.toggle-aktif', $user) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm w-100 {{ $user->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                        onclick="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} user ini?')">
                        <i class="bi bi-{{ $user->is_active ? 'pause-circle' : 'play-circle' }} me-2"></i>
                        {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                </form>
                @endcan
                @if(auth()->id() !== $user->id)
                <hr class="my-1">
                @can('user.delete')
                <form method="POST" action="{{ route('user.destroy', $user) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                        onclick="return confirm('Hapus user {{ addslashes($user->name) }}?\nTindakan ini tidak dapat dibatalkan.')">
                        <i class="bi bi-trash3 me-2"></i>Hapus User
                    </button>
                </form>
                @endcan
                @endif
            </div>
        </div>
    </div>

    {{-- Detail info --}}
    <div class="col-12 col-md-8 col-lg-9">
        <div class="row g-4">
            {{-- Info detail --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header py-3 px-4"><i class="bi bi-person-lines-fill me-2 text-primary"></i><span class="fw-semibold">Data Diri</span></div>
                    <div class="card-body p-4">
                        <div class="info-row">
                            <span class="info-label">Nama</span>
                            <span class="text-end">{{ $user->name }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="text-end" style="word-break:break-all">{{ $user->email }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Telepon</span>
                            <span class="text-end">{{ $user->telepon ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Role</span>
                            <span><span class="role-badge {{ $rc }}">{{ $user->role?->label() }}</span></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Akses Semua Cabang</span>
                            <span>
                                @if($user->canAccessAllBranches())
                                    <span class="badge bg-success-subtle text-success">Ya</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Tidak</span>
                                @endif
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status</span>
                            <span>
                                @if($user->is_active)
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                                @endif
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Bergabung</span>
                            <span>{{ $user->created_at->format('d M Y') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Diperbarui</span>
                            <span>{{ $user->updated_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cabang yang di-assign --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-geo-alt me-2 text-primary"></i><span class="fw-semibold">Cabang Terassign</span></span>
                        <span class="badge bg-primary-subtle text-primary">{{ $user->cabangs->count() }}</span>
                    </div>
                    <div class="card-body p-4">
                        @if($user->cabangs->isEmpty())
                            <div class="text-center py-4">
                                <i class="bi bi-geo-alt" style="font-size:2rem;opacity:0.2"></i>
                                <p class="text-muted mt-2 mb-0" style="font-size:0.875rem">Belum ada cabang yang di-assign.</p>
                                @if(auth()->user()->canAccessAllBranches())
                                @can('user.edit')
                                <a href="{{ route('user.edit', $user) }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="bi bi-plus-lg me-1"></i>Assign Cabang
                                </a>
                                @endcan
                                @endif
                            </div>
                        @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($user->cabangs as $cabang)
                            <div class="d-flex align-items-center gap-3 p-2 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0">
                                <div style="width:36px;height:36px;border-radius:8px;background:{{ $cabang->tipe->value === 'gudang_pusat' ? '#fef3c7' : '#dbeafe' }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <i class="bi bi-{{ $cabang->tipe->value === 'gudang_pusat' ? 'building' : 'shop' }}" style="color:{{ $cabang->tipe->value === 'gudang_pusat' ? '#92400e' : '#1e40af' }}"></i>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div style="font-size:0.85rem;font-weight:600">{{ $cabang->nama_cabang }}</div>
                                    <div style="font-size:0.75rem;color:#94a3b8">{{ $cabang->kode_cabang }} &bull; {{ $cabang->tipe->label() }}</div>
                                </div>
                                @if($cabang->pivot->is_default)
                                <span class="badge bg-warning-subtle text-warning" style="font-size:0.65rem">
                                    <i class="bi bi-star-fill me-1"></i>Default
                                </span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Reset Password --}}
            @if(auth()->user()->canAccessAllBranches() || auth()->id() === $user->id)
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-3 px-4">
                        <i class="bi bi-key me-2 text-warning"></i>
                        <span class="fw-semibold">Reset Password</span>
                    </div>
                    <div class="card-body p-4">
                        @can('user.edit')
                        <form method="POST" action="{{ route('user.reset-password', $user) }}" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-medium" style="font-size:0.875rem">Password Baru <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="new_password" id="newPwd"
                                        class="form-control @error('new_password') is-invalid @enderror"
                                        placeholder="Min. 8 karakter">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePwd2('newPwd','eyeNew')">
                                        <i class="bi bi-eye" id="eyeNew"></i>
                                    </button>
                                </div>
                                @error('new_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-medium" style="font-size:0.875rem">Konfirmasi Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="new_password_confirmation" id="newPwdConf"
                                        class="form-control" placeholder="Ulangi password">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePwd2('newPwdConf','eyeNewConf')">
                                        <i class="bi bi-eye" id="eyeNewConf"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <button type="submit" class="btn btn-warning"
                                    onclick="return confirm('Reset password untuk {{ addslashes($user->name) }}?')">
                                    <i class="bi bi-key me-2"></i>Reset Password
                                </button>
                            </div>
                        </form>
                        @endcan
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function togglePwd2(fId, iId) {
    const f = document.getElementById(fId), i = document.getElementById(iId);
    if(f.type==='password'){f.type='text';i.className='bi bi-eye-slash';}
    else{f.type='password';i.className='bi bi-eye';}
}
</script>
@endpush
