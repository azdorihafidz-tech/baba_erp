@extends('layouts.app')

@section('title', 'Manajemen Karyawan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Karyawan</h4>
        <small class="text-muted">Daftar seluruh karyawan</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('karyawan.create')
        <a href="{{ route('karyawan.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Tambah Karyawan</span>
        </a>
        @endcan
        <x-panduan-button slug="karyawan" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-sm-4 col-md-3">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Cari nama / NIK / jabatan..." value="{{ request('search') }}">
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="tidak_aktif" {{ request('status') === 'tidak_aktif' ? 'selected' : '' }}>Tidak Aktif</option>
                    <option value="keluar" {{ request('status') === 'keluar' ? 'selected' : '' }}>Keluar</option>
                </select>
            </div>
            @if(isset($authUser) && $authUser->canAccessAllBranches())
            <div class="col-6 col-sm-3 col-md-2">
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" {{ request('cabang_id') == $c->id ? 'selected' : '' }}>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-search"></i></button>
                <a href="{{ route('karyawan.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Tabel Desktop --}}
<div class="card d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>NIK</th>
                    <th>Nama Lengkap</th>
                    <th>Jabatan</th>
                    <th class="d-none d-lg-table-cell">Cabang</th>
                    <th class="d-none d-lg-table-cell">Tipe</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($karyawans as $k)
                <tr>
                    <td><code class="small">{{ $k->nik ?? '-' }}</code></td>
                    <td>
                        <div class="fw-semibold">{{ $k->nama_lengkap }}</div>
                        <small class="text-muted">{{ $k->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</small>
                    </td>
                    <td>{{ $k->jabatan }}</td>
                    <td class="d-none d-lg-table-cell">{{ $k->cabang?->nama_cabang ?? '-' }}</td>
                    <td class="d-none d-lg-table-cell">
                        <span class="badge bg-light text-dark border">
                            {{ match($k->tipe_karyawan) {
                                'tetap' => 'Tetap',
                                'kontrak' => 'Kontrak',
                                'harian' => 'Harian',
                                default => $k->tipe_karyawan
                            } }}
                        </span>
                    </td>
                    <td>
                        @if($k->status === 'aktif')
                            <span class="badge bg-success">Aktif</span>
                        @elseif($k->status === 'tidak_aktif')
                            <span class="badge bg-warning text-dark">Tidak Aktif</span>
                        @else
                            <span class="badge bg-secondary">Keluar</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('karyawan.show', $k) }}" class="btn btn-xs btn-outline-primary btn-sm me-1">
                            <i class="bi bi-eye"></i>
                        </a>
                        @can('karyawan.edit')
                        <a href="{{ route('karyawan.edit', $k) }}" class="btn btn-xs btn-outline-secondary btn-sm me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @endcan
                        @can('karyawan.delete')
                        <button type="button" class="btn btn-xs btn-outline-danger btn-sm"
                            onclick="confirmHapus('karyawan', {{ $k->id }}, '{{ addslashes($k->nama_lengkap) }}')">
                            <i class="bi bi-person-dash"></i>
                        </button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data karyawan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Card View Mobile --}}
<div class="d-md-none">
    @forelse($karyawans as $k)
    <div class="card mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">{{ $k->nama_lengkap }}</div>
                    <small class="text-muted">{{ $k->jabatan }} &bull; {{ $k->nik ?? '-' }}</small><br>
                    <small class="text-muted">{{ $k->cabang?->nama_cabang }}</small>
                </div>
                <div class="text-end">
                    @if($k->status === 'aktif')
                        <span class="badge bg-success mb-1">Aktif</span>
                    @elseif($k->status === 'tidak_aktif')
                        <span class="badge bg-warning text-dark mb-1">Tidak Aktif</span>
                    @else
                        <span class="badge bg-secondary mb-1">Keluar</span>
                    @endif
                    <div class="mt-1">
                        <a href="{{ route('karyawan.show', $k) }}" class="btn btn-xs btn-outline-primary btn-sm me-1"><i class="bi bi-eye"></i></a>
                        @can('karyawan.edit')
                        <a href="{{ route('karyawan.edit', $k) }}" class="btn btn-xs btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-4">Tidak ada data karyawan.</div>
    @endforelse
</div>

<div class="mt-3">{{ $karyawans->links() }}</div>

@include('components.cascade-delete-modal', ['entity' => 'Karyawan', 'childList' => ['Rekap absensi & absensi wajah', 'Riwayat penggajian', 'Status diubah ke "keluar"']])

@push('scripts')
<script>
function confirmHapus(routePrefix, id, nama) {
    document.getElementById('cascadeModalEntityName').textContent = nama;
    document.getElementById('cascadeModalForm').action = '/' + routePrefix + '/' + id;
    new bootstrap.Modal(document.getElementById('cascadeDeleteModal')).show();
}
</script>
@endpush

@endsection
