@extends('layouts.app')

@section('title', 'Kelola Shift')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-clock me-2 text-primary"></i>Kelola Shift</h4>
        <small class="text-muted">Atur jam kerja, toleransi telat, dan aktifkan/nonaktifkan shift per cabang</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('shift.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Shift
        </a>
        <x-panduan-button slug="shift" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            @if(auth()->user()->canAccessAllBranches())
            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label form-label-sm mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" {{ request('cabang_id') == $c->id ? 'selected' : '' }}>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12 col-md-3 col-lg-2">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="semua" {{ request('status','semua') === 'semua' ? 'selected' : '' }}>Semua</option>
                    <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label form-label-sm mb-1">Cari Nama Shift</label>
                <input type="text" name="cari" class="form-control form-control-sm"
                       value="{{ request('cari') }}" placeholder="contoh: Shift Pagi...">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary btn-sm">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="{{ route('shift.index') }}" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Info --}}
<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Shift menentukan jam kerja karyawan, jam mulai dihitung telat, dll.
    Satu karyawan terikat ke satu shift. Hapus shift hanya bisa jika tidak ada karyawan aktif yang menggunakannya.
</div>

{{-- Tabel --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:36px">#</th>
                    <th>Nama Shift</th>
                    @if(auth()->user()->canAccessAllBranches())
                    <th class="d-none d-md-table-cell">Cabang</th>
                    @endif
                    <th style="min-width:100px">Jam Masuk</th>
                    <th style="min-width:100px">Jam Keluar</th>
                    <th class="d-none d-md-table-cell text-center" style="min-width:100px">Toleransi</th>
                    <th class="text-center d-none d-sm-table-cell">Karyawan</th>
                    <th class="text-center" style="min-width:90px">Status</th>
                    <th class="text-center" style="min-width:110px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shifts as $i => $shift)
                <tr class="{{ !$shift->is_active ? 'text-muted' : '' }}">
                    <td class="small text-muted">{{ $shifts->firstItem() + $i }}</td>
                    <td>
                        <div class="fw-semibold">{{ $shift->nama_shift }}</div>
                        @if($shift->deskripsi)
                        <small class="text-muted">{{ Str::limit($shift->deskripsi, 60) }}</small>
                        @endif
                    </td>
                    @if(auth()->user()->canAccessAllBranches())
                    <td class="d-none d-md-table-cell small text-muted">{{ $shift->cabang?->nama_cabang ?? '-' }}</td>
                    @endif
                    <td><span class="badge bg-success-subtle text-success border">{{ substr($shift->jam_masuk, 0, 5) }}</span></td>
                    <td><span class="badge bg-secondary-subtle text-secondary border">{{ substr($shift->jam_keluar, 0, 5) }}</span></td>
                    <td class="d-none d-md-table-cell text-center small">{{ $shift->toleransi_telat_menit }} menit</td>
                    <td class="text-center d-none d-sm-table-cell">
                        <span class="badge {{ $shift->karyawans_count > 0 ? 'bg-primary-subtle text-primary' : 'bg-light text-muted' }} border">
                            {{ $shift->karyawans_count }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if($shift->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                        @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <a href="{{ route('shift.edit', $shift) }}"
                               class="btn btn-xs btn-outline-primary btn-sm"
                               title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('shift.toggle-aktif', $shift) }}" method="POST" class="d-inline">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="btn btn-xs btn-sm {{ $shift->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                        title="{{ $shift->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                    <i class="bi bi-{{ $shift->is_active ? 'pause-circle' : 'play-circle' }}"></i>
                                </button>
                            </form>
                            <button type="button"
                                    class="btn btn-xs btn-outline-danger btn-sm"
                                    title="Hapus"
                                    data-bs-toggle="modal" data-bs-target="#modalHapusShift"
                                    data-id="{{ $shift->id }}"
                                    data-nama="{{ $shift->nama_shift }}"
                                    data-count="{{ $shift->karyawans_count }}"
                                    onclick="konfirmasiHapusShift(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="bi bi-clock display-6 d-block mb-2 opacity-25"></i>
                        Belum ada shift.
                        <a href="{{ route('shift.create') }}">Tambah shift baru</a>.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($shifts->hasPages())
    <div class="card-footer">{{ $shifts->links() }}</div>
    @endif
</div>

{{-- Modal Hapus --}}
<div class="modal fade" id="modalHapusShift" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="bi bi-trash me-2"></i>Hapus Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="pesanHapusShift">Yakin hapus shift ini?</p>
                <div id="warningKaryawan" class="alert alert-warning d-none">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="pesanWarning"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="formHapusShift" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger" id="btnHapusShift">
                        <i class="bi bi-trash me-1"></i>Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function konfirmasiHapusShift(btn) {
    const id    = btn.dataset.id;
    const nama  = btn.dataset.nama;
    const count = parseInt(btn.dataset.count) || 0;

    document.getElementById('pesanHapusShift').textContent =
        'Yakin ingin menghapus shift "' + nama + '"?';

    const warning = document.getElementById('warningKaryawan');
    const btnHapus = document.getElementById('btnHapusShift');
    if (count > 0) {
        document.getElementById('pesanWarning').textContent =
            'Shift ini masih digunakan oleh ' + count + ' karyawan. Hapus akan gagal.';
        warning.classList.remove('d-none');
        btnHapus.disabled = true;
    } else {
        warning.classList.add('d-none');
        btnHapus.disabled = false;
    }

    document.getElementById('formHapusShift').action = '/shift/' + id;
}
</script>
@endpush
@endsection
