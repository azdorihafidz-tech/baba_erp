@extends('layouts.app')
@section('title', 'Profil Saya')

@section('content')
@php $user = auth()->user(); @endphp

<div class="d-flex align-items-center gap-3 mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>Profil Saya</h5>
</div>

{{-- Alert status --}}
@if(session('status') === 'profile-updated')
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>Profil berhasil diperbarui.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('status') === 'password-updated')
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>Password berhasil diubah.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">

    {{-- ===== KOLOM KIRI: Avatar + Info + Statistik ===== --}}
    <div class="col-12 col-lg-4">

        {{-- Card Avatar --}}
        <div class="card mb-3 text-center">
            <div class="card-body py-4">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                     style="width:90px;height:90px;background:#3b82f6;font-size:2.2rem;font-weight:700;color:white">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <h5 class="fw-bold mb-1">{{ $user->name }}</h5>
                <div class="mb-2">
                    @php
                        $roleColor = match($user->role?->value) {
                            'owner','admin_pusat'  => 'warning',
                            'admin_gudang'         => 'info',
                            'manajer_cabang'       => 'success',
                            'kasir'                => 'primary',
                            'operator_produksi'    => 'secondary',
                            default                => 'secondary',
                        };
                    @endphp
                    <span class="badge bg-{{ $roleColor }}-subtle text-{{ $roleColor }} border border-{{ $roleColor }}-subtle px-3 py-1">
                        <i class="bi bi-shield-fill me-1"></i>{{ $user->role?->label() ?? 'Tidak ada role' }}
                    </span>
                </div>
                <div class="text-muted small">{{ $user->email }}</div>
                @if($user->telepon)
                <div class="text-muted small mt-1"><i class="bi bi-telephone me-1"></i>{{ $user->telepon }}</div>
                @endif
                <div class="mt-2">
                    <span class="badge {{ $user->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                        <i class="bi bi-{{ $user->is_active ? 'check-circle' : 'x-circle' }} me-1"></i>
                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Card Cabang --}}
        @if($user->cabangs->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header py-2 small fw-semibold">
                <i class="bi bi-diagram-3 me-2 text-primary"></i>Cabang / Lokasi
            </div>
            <div class="card-body p-0">
                @foreach($user->cabangs as $cabang)
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom border-light">
                    <div>
                        <div class="fw-semibold small">{{ $cabang->nama_cabang }}</div>
                        <div class="text-muted" style="font-size:0.75rem">{{ $cabang->kode_cabang }}</div>
                    </div>
                    @if($cabang->pivot->is_default)
                    <span class="badge bg-primary-subtle text-primary" style="font-size:0.7rem">Default</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Statistik --}}
        <div class="card">
            <div class="card-header py-2 small fw-semibold">
                <i class="bi bi-bar-chart me-2 text-success"></i>Statistik Aktivitas
            </div>
            <div class="card-body">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-2 rounded-3 bg-primary-subtle">
                            <div class="fw-bold fs-4 text-primary">{{ $totalOrder }}</div>
                            <div class="text-muted" style="font-size:0.72rem">Order Diproses</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded-3 bg-success-subtle">
                            <div class="fw-bold fs-4 text-success">{{ $user->cabangs->count() }}</div>
                            <div class="text-muted" style="font-size:0.72rem">Cabang Akses</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 small text-muted">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Bergabung</span>
                        <span class="fw-semibold text-dark">{{ $user->created_at->format('d M Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>Terakhir update</span>
                        <span class="fw-semibold text-dark">{{ $user->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ===== KOLOM KANAN: Form Edit ===== --}}
    <div class="col-12 col-lg-8">

        {{-- Tab navigation --}}
        <ul class="nav nav-tabs mb-0" id="profileTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-info">
                    <i class="bi bi-person me-1"></i>Info Akun
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-password">
                    <i class="bi bi-lock me-1"></i>Ganti Password
                </button>
            </li>
            @if($user->karyawan)
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-karyawan">
                    <i class="bi bi-person-badge me-1"></i>Data Karyawan
                </button>
            </li>
            @endif
        </ul>

        <div class="tab-content">

            {{-- TAB: Info Akun --}}
            <div class="tab-pane fade show active" id="tab-info">
                <div class="card border-top-0 rounded-top-0">
                    <div class="card-body p-4">
                        <h6 class="fw-semibold mb-3 text-muted small text-uppercase">Informasi Akun</h6>

                        <form method="POST" action="{{ route('profile.update') }}">
                            @csrf @method('PATCH')

                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name', $user->name) }}" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold small">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                           value="{{ old('email', $user->email) }}" required>
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    @if($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                                    <div class="form-text text-warning">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Email belum terverifikasi.
                                    </div>
                                    @endif
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold small">No. Telepon</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                        <input type="text" name="telepon" class="form-control @error('telepon') is-invalid @enderror"
                                               value="{{ old('telepon', $user->telepon) }}" placeholder="08xx-xxxx-xxxx">
                                    </div>
                                    @error('telepon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold small">Role</label>
                                    <input type="text" class="form-control bg-light" value="{{ $user->role?->label() ?? '-' }}" disabled>
                                    <div class="form-text">Role diatur oleh administrator.</div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex gap-2 justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- TAB: Ganti Password --}}
            <div class="tab-pane fade" id="tab-password">
                <div class="card border-top-0 rounded-top-0">
                    <div class="card-body p-4">
                        <h6 class="fw-semibold mb-3 text-muted small text-uppercase">Ubah Password</h6>

                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf @method('PUT')

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Password Saat Ini <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" id="current_password" name="current_password"
                                               class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                                               autocomplete="current-password">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('current_password')">
                                            <i class="bi bi-eye" id="eye_current_password"></i>
                                        </button>
                                    </div>
                                    @error('current_password', 'updatePassword')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold small">Password Baru <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                                        <input type="password" id="password" name="password"
                                               class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                                               autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('password')">
                                            <i class="bi bi-eye" id="eye_password"></i>
                                        </button>
                                    </div>
                                    @error('password', 'updatePassword')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold small">Konfirmasi Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                        <input type="password" id="password_confirmation" name="password_confirmation"
                                               class="form-control" autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('password_confirmation')">
                                            <i class="bi bi-eye" id="eye_password_confirmation"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info d-flex gap-2 mt-3 mb-0 p-2" style="font-size:0.8rem">
                                <i class="bi bi-info-circle flex-shrink-0 mt-1"></i>
                                <span>Password minimal 8 karakter. Gunakan kombinasi huruf besar, kecil, angka, dan simbol untuk keamanan lebih baik.</span>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-shield-lock me-1"></i>Ubah Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- TAB: Data Karyawan --}}
            @if($user->karyawan)
            <div class="tab-pane fade" id="tab-karyawan">
                <div class="card border-top-0 rounded-top-0">
                    <div class="card-body p-4">
                        <h6 class="fw-semibold mb-3 text-muted small text-uppercase">Data Karyawan</h6>
                        @php $k = $user->karyawan; @endphp
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-muted small">NIK / Kode</dt>
                            <dd class="col-sm-8 fw-semibold">{{ $k->nik ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted small">Jabatan</dt>
                            <dd class="col-sm-8">{{ $k->jabatan ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted small">Cabang</dt>
                            <dd class="col-sm-8">{{ $k->cabang?->nama_cabang ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted small">Tanggal Masuk</dt>
                            <dd class="col-sm-8">{{ $k->tanggal_masuk ? \Carbon\Carbon::parse($k->tanggal_masuk)->format('d M Y') : '-' }}</dd>

                            @if($k->tanggal_masuk)
                            <dt class="col-sm-4 text-muted small">Masa Kerja</dt>
                            <dd class="col-sm-8">{{ \Carbon\Carbon::parse($k->tanggal_masuk)->diffForHumans(null, true) }}</dd>
                            @endif

                            <dt class="col-sm-4 text-muted small">Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-{{ ($k->status ?? 'aktif') === 'aktif' ? 'success' : 'secondary' }}-subtle text-{{ ($k->status ?? 'aktif') === 'aktif' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($k->status ?? 'aktif') }}
                                </span>
                            </dd>

                            @if($k->alamat)
                            <dt class="col-sm-4 text-muted small">Alamat</dt>
                            <dd class="col-sm-8">{{ $k->alamat }}</dd>
                            @endif
                        </dl>

                        <div class="alert alert-light border mt-3 mb-0 p-2" style="font-size:0.8rem">
                            <i class="bi bi-info-circle me-1 text-muted"></i>
                            Untuk mengubah data karyawan, hubungi HRD atau manajer cabang.
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>{{-- end tab-content --}}

        {{-- Hapus Akun --}}
        <div class="card mt-3 border-danger border-opacity-25">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="fw-semibold text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Hapus Akun</div>
                        <div class="text-muted" style="font-size:0.78rem">Setelah dihapus, semua data akun tidak dapat dipulihkan.</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                        <i class="bi bi-trash me-1"></i>Hapus Akun
                    </button>
                </div>
            </div>
        </div>

    </div>{{-- end kolom kanan --}}
</div>

{{-- Modal Konfirmasi Hapus Akun --}}
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Hapus Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('profile.destroy') }}">
                @csrf @method('DELETE')
                <div class="modal-body">
                    <p class="text-muted">Setelah akun dihapus, semua data dan resource akan dihapus permanen. Masukkan password untuk konfirmasi.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password"
                               class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                               placeholder="Masukkan password Anda">
                        @error('password', 'userDeletion')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Ya, Hapus Akun
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function togglePwd(fieldId) {
    const input = document.getElementById(fieldId);
    const icon  = document.getElementById('eye_' + fieldId);
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// Auto-buka tab password jika ada error password
@if($errors->updatePassword->any())
document.addEventListener('DOMContentLoaded', function () {
    const tabEl = document.querySelector('[data-bs-target="#tab-password"]');
    if (tabEl) new bootstrap.Tab(tabEl).show();
});
@endif

// Auto-buka modal hapus akun jika ada error
@if($errors->userDeletion->any())
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('deleteAccountModal')).show();
});
@endif
</script>
@endpush
