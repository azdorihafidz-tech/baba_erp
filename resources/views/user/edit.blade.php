@extends('layouts.app')
@section('title', 'Edit User - ' . $user->name)

@push('styles')
<style>
.cabang-check-item {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.6rem 0.75rem; border-radius: 8px;
    border: 1px solid #e2e8f0; cursor: pointer;
    transition: border-color .15s, background .15s;
}
.cabang-check-item:has(input:checked) {
    border-color: #3b82f6; background: #eff6ff;
}
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit User</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0" style="font-size:0.8rem">
            <li class="breadcrumb-item"><a href="{{ route('user.index') }}" class="text-decoration-none">User</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('user.toggle-aktif', $user) }}">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                onclick="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} user ini?')">
                <i class="bi bi-{{ $user->is_active ? 'pause-circle' : 'play-circle' }} me-1"></i>
                <span class="d-none d-sm-inline">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</span>
            </button>
        </form>
        <a href="{{ route('user.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
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

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Terdapat kesalahan input:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('user.update', $user) }}" id="editForm">
@csrf @method('PUT')
<div class="row g-4">
    <div class="col-12 col-lg-8">
        {{-- Info Dasar --}}
        <div class="card mb-4">
            <div class="card-header py-3 px-4"><i class="bi bi-person me-2 text-primary"></i><span class="fw-semibold">Informasi User</span></div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email', $user->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">No. Telepon</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="telepon" class="form-control"
                                value="{{ old('telepon', $user->telepon) }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" id="roleSelect">
                            @foreach($roles as $role)
                            <option value="{{ $role->value }}" {{ old('role', $user->role?->value) === $role->value ? 'selected' : '' }}>
                                {{ $role->label() }}
                            </option>
                            @endforeach
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Assign Cabang --}}
        <div class="card mb-4">
            <div class="card-header py-3 px-4"><i class="bi bi-geo-alt me-2 text-primary"></i><span class="fw-semibold">Assign Cabang</span></div>
            <div class="card-body p-4">
                @if($cabangs->isEmpty())
                <p class="text-muted text-center py-3" style="font-size:0.875rem">Belum ada cabang aktif yang tersedia.</p>
                @else
                <div class="row g-2">
                    @foreach($cabangs as $cabang)
                    <div class="col-12 col-md-6">
                        <label class="cabang-check-item w-100">
                            <input type="checkbox" name="cabang_ids[]"
                                value="{{ $cabang->id }}"
                                class="form-check-input flex-shrink-0 cabang-checkbox"
                                data-id="{{ $cabang->id }}"
                                data-name="{{ $cabang->nama_cabang }}"
                                {{ in_array($cabang->id, old('cabang_ids', $userCabangIds)) ? 'checked' : '' }}>
                            <div style="width:32px;height:32px;border-radius:7px;background:{{ $cabang->tipe->value === 'gudang_pusat' ? '#fef3c7' : '#dbeafe' }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="bi bi-{{ $cabang->tipe->value === 'gudang_pusat' ? 'building' : 'shop' }}" style="color:{{ $cabang->tipe->value === 'gudang_pusat' ? '#92400e' : '#1e40af' }}"></i>
                            </div>
                            <div class="min-w-0">
                                <div style="font-size:0.85rem;font-weight:600">{{ $cabang->nama_cabang }}</div>
                                <div style="font-size:0.75rem;color:#94a3b8">{{ $cabang->kode_cabang }} &bull; {{ $cabang->tipe->label() }}</div>
                            </div>
                        </label>
                    </div>
                    @endforeach
                </div>
                <div class="mt-3" id="defaultCabangSection">
                    <label class="form-label fw-medium" style="font-size:0.875rem">
                        <i class="bi bi-star-fill text-warning me-1"></i>Cabang Default
                    </label>
                    <select name="default_cabang_id" class="form-select form-select-sm" id="defaultCabangSelect">
                        <option value="">— Pilih cabang default —</option>
                    </select>
                    <div class="form-text">Cabang default digunakan saat user login.</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Reset Password --}}
        <div class="card">
            <div class="card-header py-3 px-4"><i class="bi bi-key me-2 text-warning"></i><span class="fw-semibold">Ganti Password</span> <small class="text-muted">(kosongkan jika tidak ingin ganti)</small></div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Password Baru</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Min. 8 karakter">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('password','eyePwd')">
                                <i class="bi bi-eye" id="eyePwd"></i>
                            </button>
                        </div>
                        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Konfirmasi Password</label>
                        <div class="input-group">
                            <input type="password" name="password_confirmation" id="pwdConfirm" class="form-control" placeholder="Ulangi password baru">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('pwdConfirm','eyeConf')">
                                <i class="bi bi-eye" id="eyeConf"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Kolom Kanan --}}
    <div class="col-12 col-lg-4">
        {{-- Info user --}}
        <div class="card mb-3">
            <div class="card-body p-4 text-center">
                <div style="width:64px;height:64px;border-radius:50%;background:{{ $user->is_active ? '#3b82f6' : '#94a3b8' }};color:white;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;margin:0 auto .75rem">
                    {{ strtoupper(substr($user->name,0,1)) }}
                </div>
                <div class="fw-semibold" style="font-size:0.9rem">{{ $user->name }}</div>
                <div class="text-muted" style="font-size:0.8rem">{{ $user->email }}</div>
                <div class="mt-2">
                    @if($user->is_active)
                        <span class="badge bg-success-subtle text-success">Aktif</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                    @endif
                </div>
                <div class="mt-3 pt-3 border-top text-start">
                    <div class="d-flex justify-content-between mb-1" style="font-size:0.8rem">
                        <span class="text-muted">Dibuat</span>
                        <span>{{ $user->created_at->format('d M Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:0.8rem">
                        <span class="text-muted">Diperbarui</span>
                        <span>{{ $user->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body p-4">
                <button type="submit" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-check-lg me-2"></i>Simpan Perubahan
                </button>
                <a href="{{ route('user.show', $user) }}" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="bi bi-eye me-2"></i>Lihat Profil
                </a>
                <a href="{{ route('user.index') }}" class="btn btn-light w-100">
                    <i class="bi bi-x-lg me-2"></i>Batal
                </a>
            </div>
        </div>
    </div>
</div>
</form>{{-- akhir editForm --}}

{{-- Danger zone — HARUS di luar editForm --}}
@if(auth()->id() !== $user->id)
<div class="row mt-0">
    <div class="col-12 col-lg-4 offset-lg-8">
        <div class="card border-danger-subtle">
            <div class="card-header py-3 px-4 bg-danger-subtle">
                <i class="bi bi-exclamation-triangle me-2 text-danger"></i>
                <span class="fw-semibold text-danger">Zona Berbahaya</span>
            </div>
            <div class="card-body p-4">
                @can('user.delete')
                <form method="POST" action="{{ route('user.destroy', $user) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger w-100 btn-sm"
                        onclick="return confirm('Hapus user {{ addslashes($user->name) }}?\nTindakan ini tidak dapat dibatalkan.')">
                        <i class="bi bi-trash3 me-2"></i>Hapus User Ini
                    </button>
                </form>
                @endcan
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
function togglePwd(fId, iId) {
    const f = document.getElementById(fId), i = document.getElementById(iId);
    if(f.type==='password'){f.type='text';i.className='bi bi-eye-slash';}
    else{f.type='password';i.className='bi bi-eye';}
}
const defaultCabangId = {{ $defaultCabangId ? $defaultCabangId : 'null' }};
function updateDefaultOptions() {
    const checked = [...document.querySelectorAll('.cabang-checkbox:checked')];
    const select = document.getElementById('defaultCabangSelect');
    if (!select) return;
    const current = select.value || defaultCabangId;
    select.innerHTML = '<option value="">— Pilih cabang default —</option>';
    checked.forEach(cb => {
        const opt = document.createElement('option');
        opt.value = cb.dataset.id;
        opt.textContent = cb.dataset.name;
        if (cb.dataset.id == current) opt.selected = true;
        select.appendChild(opt);
    });
    if (checked.length === 1) select.value = checked[0].dataset.id;
}
document.querySelectorAll('.cabang-checkbox').forEach(cb => cb.addEventListener('change', updateDefaultOptions));
updateDefaultOptions();
</script>
@endpush
