@extends('layouts.app')
@section('title', 'Tambah User')

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
        <h4 class="fw-bold mb-0" style="color:#1e293b"><i class="bi bi-person-plus me-2 text-primary"></i>Tambah User</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0" style="font-size:0.8rem">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('user.index') }}" class="text-decoration-none">User</a></li>
            <li class="breadcrumb-item active">Tambah</li>
        </ol></nav>
    </div>
    <a href="{{ route('user.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
</div>

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

<form method="POST" action="{{ route('user.store') }}">
@csrf
<div class="row g-4">
    {{-- Kolom Kiri --}}
    <div class="col-12 col-lg-8">
        {{-- Info Dasar --}}
        <div class="card mb-4">
            <div class="card-header py-3 px-4"><i class="bi bi-person me-2 text-primary"></i><span class="fw-semibold">Informasi User</span></div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}" placeholder="Nama lengkap user" autofocus>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email') }}" placeholder="email@dmentai.com">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">No. Telepon</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="telepon" class="form-control @error('telepon') is-invalid @enderror"
                                value="{{ old('telepon') }}" placeholder="08xxxxxxxxxx">
                        </div>
                        @error('telepon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Role <span class="text-danger">*</span> <x-tooltip key="user.role" /></label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" id="roleSelect">
                            <option value="">— Pilih Role —</option>
                            @foreach($roles as $role)
                            <option value="{{ $role->value }}" {{ old('role') === $role->value ? 'selected' : '' }}>
                                {{ $role->label() }}
                            </option>
                            @endforeach
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Min. 8 karakter">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password','eyePassword')">
                                <i class="bi bi-eye" id="eyePassword"></i>
                            </button>
                        </div>
                        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-medium">Konfirmasi Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                class="form-control" placeholder="Ulangi password">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password_confirmation','eyeConfirm')">
                                <i class="bi bi-eye" id="eyeConfirm"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Assign Cabang --}}
        <div class="card" id="cabangSection">
            <div class="card-header py-3 px-4">
                <i class="bi bi-geo-alt me-2 text-primary"></i>
                <span class="fw-semibold">Assign Cabang</span>
                <small class="text-muted ms-2">Pilih satu atau lebih cabang</small>
            </div>
            <div class="card-body p-4">
                @error('cabang_ids')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                @if($cabangs->isEmpty())
                <p class="text-muted text-center py-3" style="font-size:0.875rem">Belum ada cabang aktif yang tersedia.</p>
                @else
                <div class="row g-2" id="cabangList">
                    @foreach($cabangs as $cabang)
                    <div class="col-12 col-md-6">
                        <label class="cabang-check-item w-100">
                            <input type="checkbox" name="cabang_ids[]"
                                value="{{ $cabang->id }}"
                                class="form-check-input flex-shrink-0 cabang-checkbox"
                                data-id="{{ $cabang->id }}"
                                data-name="{{ $cabang->nama_cabang }}"
                                {{ in_array($cabang->id, old('cabang_ids', [])) ? 'checked' : '' }}>
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

                {{-- Default cabang --}}
                <div class="mt-3" id="defaultCabangSection" style="display:none">
                    <label class="form-label fw-medium" style="font-size:0.875rem">
                        <i class="bi bi-star-fill text-warning me-1"></i>Cabang Default
                        <x-tooltip key="user.default_cabang_id" />
                    </label>
                    <select name="default_cabang_id" class="form-select form-select-sm" id="defaultCabangSelect">
                        <option value="">— Pilih cabang default —</option>
                    </select>
                    <div class="form-text">Cabang default digunakan saat user pertama kali login.</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Kolom Kanan --}}
    <div class="col-12 col-lg-4">
        <div class="card mb-3">
            <div class="card-header py-3 px-4"><i class="bi bi-info-circle me-2 text-info"></i><span class="fw-semibold">Panduan Role</span></div>
            <div class="card-body p-4">
                <div class="d-flex flex-column gap-2" style="font-size:0.8rem">
                    <div class="d-flex gap-2"><span class="badge" style="background:#fef3c7;color:#92400e;flex-shrink:0">Owner</span><span class="text-muted">Akses penuh semua cabang</span></div>
                    <div class="d-flex gap-2"><span class="badge" style="background:#dbeafe;color:#1e40af;flex-shrink:0">Admin Pusat</span><span class="text-muted">Akses penuh semua cabang</span></div>
                    <div class="d-flex gap-2"><span class="badge" style="background:#e0f2fe;color:#0369a1;flex-shrink:0">Admin Gudang</span><span class="text-muted">Kelola stok gudang pusat</span></div>
                    <div class="d-flex gap-2"><span class="badge" style="background:#d1fae5;color:#065f46;flex-shrink:0">Manajer</span><span class="text-muted">Operasional 1 cabang</span></div>
                    <div class="d-flex gap-2"><span class="badge" style="background:#ede9fe;color:#5b21b6;flex-shrink:0">Kasir</span><span class="text-muted">POS & penjualan</span></div>
                    <div class="d-flex gap-2"><span class="badge" style="background:#fce7f3;color:#9d174d;flex-shrink:0">Operator</span><span class="text-muted">Input produksi & stok</span></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body p-4">
                <button type="submit" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-check-lg me-2"></i>Simpan User
                </button>
                <a href="{{ route('user.index') }}" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-lg me-2"></i>Batal
                </a>
            </div>
        </div>
    </div>
</div>
</form>
@endsection

@push('scripts')
<script>
function togglePassword(fieldId, iconId) {
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}

function updateDefaultOptions() {
    const checked = [...document.querySelectorAll('.cabang-checkbox:checked')];
    const section = document.getElementById('defaultCabangSection');
    const select = document.getElementById('defaultCabangSelect');
    if (!section || !select) return;
    const current = select.value;

    section.style.display = checked.length > 1 ? 'block' : 'none';
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

// Sembunyikan assign cabang untuk owner/admin_pusat
const roleSelect = document.getElementById('roleSelect');
if (roleSelect) {
    roleSelect.addEventListener('change', function() {
        const noAssign = ['owner','admin_pusat'];
        const section = document.getElementById('cabangSection');
        if (section) section.style.opacity = noAssign.includes(this.value) ? '0.5' : '1';
    });
}
</script>
@endpush
