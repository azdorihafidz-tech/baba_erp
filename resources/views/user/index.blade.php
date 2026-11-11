@extends('layouts.app')

@section('title', 'Manajemen User')

@push('styles')
<style>
.role-badge {
    font-size: 0.7rem; padding: 0.2rem 0.55rem;
    border-radius: 20px; font-weight: 600;
}
.role-owner     { background:#fef3c7; color:#92400e; }
.role-admin     { background:#dbeafe; color:#1e40af; }
.role-manajer   { background:#d1fae5; color:#065f46; }
.role-kasir     { background:#ede9fe; color:#5b21b6; }
.role-operator  { background:#fce7f3; color:#9d174d; }
.role-gudang    { background:#e0f2fe; color:#0369a1; }
.user-card { border:1px solid #e2e8f0; border-radius:12px; background:white; transition:box-shadow .15s; }
.user-card:hover { box-shadow:0 4px 14px rgba(0,0,0,.07); }
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-people me-2 text-primary"></i>Manajemen User
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">User</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()->canAccessAllBranches())
        <a href="{{ route('role.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="bi bi-shield-check"></i>
            <span class="d-none d-sm-inline">Kelola Role</span>
        </a>
        @endif
        @can('user.create')
        <a href="{{ route('user.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
            <i class="bi bi-person-plus"></i>
            <span>Tambah User</span>
        </a>
        @endcan
    </div>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="p-3 rounded-3 d-flex align-items-center gap-3" style="background:white;border:1px solid #e2e8f0">
            <div style="width:40px;height:40px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-people text-primary" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['total'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Total User</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-3 rounded-3 d-flex align-items-center gap-3" style="background:white;border:1px solid #e2e8f0">
            <div style="width:40px;height:40px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-check-circle text-success" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['aktif'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Aktif</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-3 rounded-3 d-flex align-items-center gap-3" style="background:white;border:1px solid #e2e8f0">
            <div style="width:40px;height:40px;border-radius:8px;background:#fefce8;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-shield-fill text-warning" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['owner'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Owner/Admin</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-3 rounded-3 d-flex align-items-center gap-3" style="background:white;border:1px solid #e2e8f0">
            <div style="width:40px;height:40px;border-radius:8px;background:#fdf4ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-person-badge text-purple" style="font-size:1.1rem;color:#7c3aed"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['staff'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Staff</div>
            </div>
        </div>
    </div>
</div>

{{-- Flash Messages --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show d-flex gap-2 align-items-center mb-4" role="alert">
    <i class="bi bi-check-circle-fill flex-shrink-0"></i>
    <span>{!! session('success') !!}</span>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show d-flex gap-2 align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
    <span>{!! session('error') !!}</span>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Filter --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('user.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label form-label-sm mb-1">Cari</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                        placeholder="Nama, email, telepon..."
                        value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Role</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">Semua Role</option>
                    @foreach($roles as $role)
                    <option value="{{ $role->value }}" {{ request('role') === $role->value ? 'selected' : '' }}>
                        {{ $role->label() }}
                    </option>
                    @endforeach
                </select>
            </div>
            @if(auth()->user()->canAccessAllBranches())
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $cabang)
                    <option value="{{ $cabang->id }}" {{ request('cabang_id') == $cabang->id ? 'selected' : '' }}>
                        {{ $cabang->nama_cabang }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-sm-2 col-md-2">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 col-sm-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                @if(request()->hasAny(['search','role','cabang_id','status']))
                <a href="{{ route('user.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-x-lg"></i>
                </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Tabel Desktop --}}
<div class="card d-none d-lg-block">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-list-ul me-2 text-primary"></i>Daftar User</span>
        <small class="text-muted">{{ $users->total() }} user ditemukan</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="px-4" style="width:50px">#</th>
                    <th>Nama & Email</th>
                    <th>Role</th>
                    <th>Cabang</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Terakhir Login</th>
                    <th class="text-center px-4" style="width:120px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td class="px-4 text-muted" style="font-size:0.8rem">{{ $users->firstItem() + $loop->index }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:38px;height:38px;border-radius:50%;background:{{ $user->is_active ? '#3b82f6' : '#94a3b8' }};color:white;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:700;flex-shrink:0">
                                {{ strtoupper(substr($user->name,0,1)) }}
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size:0.875rem;color:#1e293b">{{ $user->name }}</div>
                                <div class="text-muted" style="font-size:0.775rem">{{ $user->email }}</div>
                                @if($user->telepon)
                                <div class="text-muted" style="font-size:0.75rem"><i class="bi bi-telephone me-1"></i>{{ $user->telepon }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        @php
                            $roleClass = match($user->role?->value) {
                                'owner','admin_pusat' => 'role-owner',
                                'admin_gudang'        => 'role-gudang',
                                'manajer_cabang'      => 'role-manajer',
                                'kasir'               => 'role-kasir',
                                'operator_produksi'   => 'role-operator',
                                default               => 'role-admin',
                            };
                        @endphp
                        <span class="role-badge {{ $roleClass }}">{{ $user->role?->label() }}</span>
                    </td>
                    <td>
                        @if($user->cabangs->isEmpty())
                            <span class="text-muted" style="font-size:0.8rem">—</span>
                        @else
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($user->cabangs->take(2) as $cabang)
                            <span class="badge {{ $cabang->pivot->is_default ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}"
                                  style="font-size:0.7rem">
                                {{ $cabang->nama_cabang }}
                                @if($cabang->pivot->is_default)<i class="bi bi-star-fill ms-1" style="font-size:0.55rem"></i>@endif
                            </span>
                            @endforeach
                            @if($user->cabangs->count() > 2)
                            <span class="badge bg-light text-secondary" style="font-size:0.7rem">
                                +{{ $user->cabangs->count() - 2 }}
                            </span>
                            @endif
                        </div>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($user->is_active)
                            <span class="badge bg-success-subtle text-success"><i class="bi bi-circle-fill me-1" style="font-size:0.4rem"></i>Aktif</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-circle me-1" style="font-size:0.4rem"></i>Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center text-muted" style="font-size:0.775rem">
                        {{ $user->updated_at?->diffForHumans() }}
                    </td>
                    <td class="text-center px-4">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <a href="{{ route('user.show', $user) }}" class="btn btn-sm btn-outline-info px-2 py-1" title="Detail" style="min-width:30px"><i class="bi bi-eye"></i></a>
                            @can('user.edit')
                            <a href="{{ route('user.edit', $user) }}" class="btn btn-sm btn-outline-primary px-2 py-1" title="Edit" style="min-width:30px"><i class="bi bi-pencil"></i></a>
                            @endcan
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary px-2 py-1" data-bs-toggle="dropdown" style="min-width:30px"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li>
                                        @can('user.edit')
                                        <form method="POST" action="{{ route('user.toggle-aktif', $user) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="dropdown-item"
                                                onclick="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} user ini?')">
                                                <i class="bi bi-{{ $user->is_active ? 'pause-circle text-warning' : 'play-circle text-success' }} me-2"></i>
                                                {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                        @endcan
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button type="button" class="dropdown-item text-danger"
                                            onclick="confirmHapus({{ $user->id }}, '{{ addslashes($user->name) }}')">
                                            <i class="bi bi-trash3 me-2"></i>Hapus
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
                        <p class="mt-2 mb-0">Tidak ada user ditemukan.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between">
        <small class="text-muted">Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari {{ $users->total() }}</small>
        {{ $users->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- Card view Mobile/Tablet --}}
<div class="d-lg-none">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <small class="text-muted">{{ $users->total() }} user ditemukan</small>
    </div>
    @forelse($users as $user)
    @php
        $roleClass = match($user->role?->value) {
            'owner','admin_pusat' => 'role-owner',
            'admin_gudang'        => 'role-gudang',
            'manajer_cabang'      => 'role-manajer',
            'kasir'               => 'role-kasir',
            'operator_produksi'   => 'role-operator',
            default               => 'role-admin',
        };
    @endphp
    <div class="user-card p-3 mb-3">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="d-flex align-items-start gap-3 flex-grow-1 min-w-0">
                <div style="width:44px;height:44px;border-radius:50%;background:{{ $user->is_active ? '#3b82f6' : '#94a3b8' }};color:white;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;flex-shrink:0">
                    {{ strtoupper(substr($user->name,0,1)) }}
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="fw-semibold" style="color:#1e293b;font-size:0.9rem">{{ $user->name }}</span>
                        <span class="role-badge {{ $roleClass }}">{{ $user->role?->label() }}</span>
                        @if(!$user->is_active)<span class="badge bg-secondary-subtle text-secondary" style="font-size:0.65rem">Nonaktif</span>@endif
                    </div>
                    <div class="text-muted" style="font-size:0.775rem">{{ $user->email }}</div>
                    @if($user->telepon)<div class="text-muted" style="font-size:0.75rem"><i class="bi bi-telephone me-1"></i>{{ $user->telepon }}</div>@endif
                    @if($user->cabangs->isNotEmpty())
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($user->cabangs->take(3) as $c)
                        <span class="badge {{ $c->pivot->is_default ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}" style="font-size:0.7rem">
                            {{ $c->nama_cabang }}
                        </span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            <div class="dropdown flex-shrink-0">
                <button class="btn btn-sm btn-outline-secondary rounded-circle" style="width:34px;height:34px;padding:0" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical" style="font-size:0.9rem"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a href="{{ route('user.show', $user) }}" class="dropdown-item"><i class="bi bi-eye me-2 text-info"></i>Detail</a></li>
                    @can('user.edit')
                    <li><a href="{{ route('user.edit', $user) }}" class="dropdown-item"><i class="bi bi-pencil me-2 text-primary"></i>Edit</a></li>
                    @endcan
                    <li>
                        @can('user.edit')
                        <form method="POST" action="{{ route('user.toggle-aktif', $user) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="dropdown-item" onclick="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} user ini?')">
                                <i class="bi bi-{{ $user->is_active ? 'pause-circle text-warning' : 'play-circle text-success' }} me-2"></i>
                                {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                        @endcan
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                            onclick="confirmHapus({{ $user->id }}, '{{ addslashes($user->name) }}')">
                            <i class="bi bi-trash3 me-2"></i>Hapus
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
        <p class="mt-2">Tidak ada user ditemukan.</p>
    </div>
    @endforelse
    @if($users->hasPages())
    <div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
    @endif
</div>

@include('components.cascade-delete-modal', ['entity' => 'User', 'childList' => ['Penugasan ke cabang dihapus', 'Sesi aktif dihapus', 'Hubungan ke profil karyawan diputus (karyawan tidak ikut terhapus)', 'Jabatan kepala cabang dikosongkan (cabang tidak ikut terhapus)']])

@push('scripts')
<script>
function confirmHapus(id, nama) {
    document.getElementById('cascadeModalEntityName').textContent = nama;
    document.getElementById('cascadeModalForm').action = '/user/' + id;
    new bootstrap.Modal(document.getElementById('cascadeDeleteModal')).show();
}
</script>
@endpush

@endsection
