@extends('layouts.app')

@section('title', 'Manajemen Cabang')

@push('styles')
<style>
    .cabang-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        transition: box-shadow 0.15s, transform 0.15s;
        background: white;
    }
    .cabang-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        transform: translateY(-1px);
    }
    .cabang-card .tipe-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.6rem;
        border-radius: 20px;
        font-weight: 600;
        letter-spacing: 0.03em;
    }
    .badge-gudang { background: #fef3c7; color: #92400e; }
    .badge-cabang { background: #dbeafe; color: #1e40af; }
    .stat-mini { background: #f8fafc; border-radius: 8px; padding: 0.6rem 0.75rem; }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-diagram-3 me-2 text-primary"></i>Manajemen Cabang
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Cabang</li>
            </ol>
        </nav>
    </div>
    <x-panduan-button slug="cabang" />
    @can('cabang.create')
    <a href="{{ route('cabang.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i>
        <span>Tambah Cabang</span>
    </a>
    @endcan
</div>

{{-- STAT CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:40px;height:40px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-buildings text-primary" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['total'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Total Lokasi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
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
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:40px;height:40px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-shop text-primary" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['cabang'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Cabang</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:40px;height:40px;border-radius:8px;background:#fefce8;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-building text-warning" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['gudang_pusat'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Gudang Pusat</div>
            </div>
        </div>
    </div>
    @if(($stats['head_office'] ?? 0) > 0)
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:40px;height:40px;border-radius:8px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-building-fill-gear text-info" style="font-size:1.1rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['head_office'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Head Office</div>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- FILTER & SEARCH --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('cabang.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-sm-5 col-md-4">
                <label class="form-label form-label-sm mb-1">Cari</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                        placeholder="Nama atau kode cabang..."
                        value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Tipe</label>
                <select name="tipe" class="form-select form-select-sm">
                    <option value="">Semua Tipe</option>
                    <option value="cabang"       {{ request('tipe') === 'cabang'       ? 'selected' : '' }}>Cabang</option>
                    <option value="gudang_pusat" {{ request('tipe') === 'gudang_pusat' ? 'selected' : '' }}>Gudang Pusat</option>
                    <option value="head_office"  {{ request('tipe') === 'head_office'  ? 'selected' : '' }}>Head Office</option>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 col-sm-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                @if(request()->hasAny(['search','tipe','status']))
                <a href="{{ route('cabang.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-x-lg me-1"></i>Reset
                </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- TABEL - Desktop --}}
<div class="card d-none d-lg-block">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-list-ul me-2 text-primary"></i>Daftar Cabang & Gudang
        </span>
        <small class="text-muted">{{ $cabangs->total() }} lokasi ditemukan</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="px-4" style="width:50px">#</th>
                    <th>Nama Cabang</th>
                    <th>Kode</th>
                    <th>Tipe</th>
                    <th>Kepala</th>
                    <th class="text-center">Karyawan</th>
                    <th class="text-center">Status</th>
                    <th class="text-center d-none d-xl-table-cell">GPS</th>
                    <th class="text-center px-4" style="width:130px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cabangs as $cabang)
                <tr>
                    <td class="px-4 text-muted" style="font-size:0.8rem">
                        {{ $cabangs->firstItem() + $loop->index }}
                    </td>
                    <td>
                        <div class="fw-semibold" style="color:#1e293b">{{ $cabang->nama_cabang }}</div>
                        @if($cabang->alamat)
                        <div class="text-muted" style="font-size:0.75rem">
                            <i class="bi bi-geo-alt me-1"></i>{{ Str::limit($cabang->alamat, 50) }}
                        </div>
                        @endif
                    </td>
                    <td>
                        <code class="px-2 py-1 rounded" style="background:#f1f5f9;color:#475569;font-size:0.8rem">
                            {{ $cabang->kode_cabang }}
                        </code>
                    </td>
                    <td>
                        @php $tipeObj = $cabang->tipe instanceof \App\Enums\TipeCabang ? $cabang->tipe : \App\Enums\TipeCabang::tryFrom($cabang->tipe ?? ''); @endphp
                        @if($tipeObj)
                            <span class="badge {{ $tipeObj->badgeClass() }}">
                                {{ $tipeObj->icon() }} {{ $tipeObj->label() }}
                            </span>
                        @else
                            <span class="badge bg-secondary">{{ $cabang->tipe }}</span>
                        @endif
                    </td>
                    <td>
                        @if($cabang->kepalaCabang)
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:28px;height:28px;border-radius:50%;background:#3b82f6;color:white;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:600;flex-shrink:0">
                                    {{ substr($cabang->kepalaCabang->name, 0, 1) }}
                                </div>
                                <span style="font-size:0.85rem">{{ $cabang->kepalaCabang->name }}</span>
                            </div>
                        @else
                            <span class="text-muted" style="font-size:0.8rem">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary-subtle text-secondary">
                            {{ $cabang->karyawans_count }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if($cabang->is_active)
                            <span class="badge bg-success-subtle text-success">
                                <i class="bi bi-circle-fill me-1" style="font-size:0.4rem"></i>Aktif
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">
                                <i class="bi bi-circle me-1" style="font-size:0.4rem"></i>Nonaktif
                            </span>
                        @endif
                    </td>
                    <td class="text-center d-none d-xl-table-cell">
                        @if($cabang->latitude && $cabang->longitude)
                        <a href="{{ route('cabang.edit', $cabang) }}#gps"
                           class="badge bg-success-subtle text-success border border-success-subtle text-decoration-none"
                           title="Lat: {{ $cabang->latitude }}, Lng: {{ $cabang->longitude }}">
                            <i class="bi bi-geo-alt-fill me-1"></i>Tersetting
                        </a>
                        @else
                        <a href="{{ route('cabang.edit', $cabang) }}#gps"
                           class="badge bg-warning-subtle text-warning border border-warning-subtle text-decoration-none">
                            <i class="bi bi-geo-alt me-1"></i>Belum diset
                        </a>
                        @endif
                    </td>
                    <td class="text-center px-4">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            {{-- Edit --}}
                            @can('cabang.edit')
                            <a href="{{ route('cabang.edit', $cabang) }}"
                                class="btn btn-sm btn-outline-primary px-2 py-1"
                                title="Edit" style="min-width:32px">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan

                            {{-- Toggle Aktif --}}
                            @can('cabang.edit')
                            <form method="POST" action="{{ route('cabang.toggle-aktif', $cabang) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                    class="btn btn-sm {{ $cabang->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} px-2 py-1"
                                    title="{{ $cabang->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                    style="min-width:32px"
                                    onclick="return confirm('{{ $cabang->is_active ? 'Nonaktifkan' : 'Aktifkan' }} cabang ini?')">
                                    <i class="bi bi-{{ $cabang->is_active ? 'pause-circle' : 'play-circle' }}"></i>
                                </button>
                            </form>
                            @endcan

                            {{-- Hapus --}}
                            <button type="button"
                                class="btn btn-sm btn-outline-danger px-2 py-1"
                                title="Hapus" style="min-width:32px"
                                onclick="confirmHapusCabang({{ $cabang->id }}, '{{ addslashes($cabang->nama_cabang) }}')">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
                            <p class="mt-2 mb-0">Tidak ada cabang ditemukan.</p>
                            @if(request()->hasAny(['search','tipe','status']))
                                <a href="{{ route('cabang.index') }}" class="btn btn-sm btn-outline-primary mt-2">Reset Filter</a>
                            @else
                                @can('cabang.create')
                                <a href="{{ route('cabang.create') }}" class="btn btn-sm btn-primary mt-2">
                                    <i class="bi bi-plus-lg me-1"></i>Tambah Cabang
                                </a>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($cabangs->hasPages())
    <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between">
        <small class="text-muted">
            Menampilkan {{ $cabangs->firstItem() }}–{{ $cabangs->lastItem() }}
            dari {{ $cabangs->total() }} data
        </small>
        {{ $cabangs->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- CARD VIEW - Mobile & Tablet --}}
<div class="d-lg-none">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <small class="text-muted">{{ $cabangs->total() }} lokasi ditemukan</small>
    </div>

    @forelse($cabangs as $cabang)
    <div class="cabang-card p-3 mb-3">
        <div class="d-flex align-items-start justify-content-between gap-2">
            {{-- Icon + Info --}}
            <div class="d-flex align-items-start gap-3 flex-grow-1 min-w-0">
                @php
                    $tipeCard = $cabang->tipe instanceof \App\Enums\TipeCabang ? $cabang->tipe : \App\Enums\TipeCabang::tryFrom($cabang->tipe ?? '');
                    $cardBg = match($tipeCard) {
                        \App\Enums\TipeCabang::GudangPusat => ['bg'=>'#fef3c7','color'=>'#92400e','icon'=>'building'],
                        \App\Enums\TipeCabang::HeadOffice  => ['bg'=>'#e0f2fe','color'=>'#0369a1','icon'=>'building-fill-gear'],
                        default                            => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'shop'],
                    };
                @endphp
                <div style="width:44px;height:44px;border-radius:10px;background:{{ $cardBg['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi bi-{{ $cardBg['icon'] }}"
                       style="font-size:1.2rem;color:{{ $cardBg['color'] }}"></i>
                </div>
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-semibold" style="color:#1e293b">{{ $cabang->nama_cabang }}</span>
                        @if($tipeCard)
                            <span class="badge {{ $tipeCard->badgeClass() }}" style="font-size:0.7rem">
                                {{ $tipeCard->icon() }} {{ $tipeCard->label() }}
                            </span>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                        <code style="background:#f1f5f9;color:#475569;font-size:0.75rem;padding:0.1rem 0.4rem;border-radius:4px">
                            {{ $cabang->kode_cabang }}
                        </code>
                        @if($cabang->is_active)
                            <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">Aktif</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem">Nonaktif</span>
                        @endif
                        <small class="text-muted">{{ $cabang->karyawans_count }} karyawan</small>
                        @if($cabang->latitude && $cabang->longitude)
                        <span class="badge bg-success-subtle text-success border border-success-subtle"
                              style="font-size:0.68rem">
                            <i class="bi bi-geo-alt-fill me-1"></i>GPS ✓
                        </span>
                        @else
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle"
                              style="font-size:0.68rem">
                            <i class="bi bi-geo-alt me-1"></i>GPS?
                        </span>
                        @endif
                    </div>
                    @if($cabang->alamat)
                    <div class="text-muted mt-1" style="font-size:0.75rem">
                        <i class="bi bi-geo-alt me-1"></i>{{ Str::limit($cabang->alamat, 60) }}
                    </div>
                    @endif
                    @if($cabang->kepalaCabang)
                    <div class="text-muted mt-1" style="font-size:0.75rem">
                        <i class="bi bi-person me-1"></i>{{ $cabang->kepalaCabang->name }}
                    </div>
                    @endif
                </div>
            </div>

            {{-- Dropdown aksi mobile --}}
            <div class="dropdown flex-shrink-0">
                <button class="btn btn-sm btn-outline-secondary rounded-circle"
                    style="width:34px;height:34px;padding:0"
                    data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical" style="font-size:0.9rem"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        @can('cabang.edit')
                        <a href="{{ route('cabang.edit', $cabang) }}" class="dropdown-item">
                            <i class="bi bi-pencil me-2 text-primary"></i>Edit
                        </a>
                        @endcan
                    </li>
                    <li>
                        @can('cabang.edit')
                        <form method="POST" action="{{ route('cabang.toggle-aktif', $cabang) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="dropdown-item"
                                onclick="return confirm('{{ $cabang->is_active ? 'Nonaktifkan' : 'Aktifkan' }} cabang ini?')">
                                <i class="bi bi-{{ $cabang->is_active ? 'pause-circle text-warning' : 'play-circle text-success' }} me-2"></i>
                                {{ $cabang->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                        @endcan
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                            onclick="confirmHapusCabang({{ $cabang->id }}, '{{ addslashes($cabang->nama_cabang) }}')">
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
        <p class="mt-2">Tidak ada cabang ditemukan.</p>
        @can('cabang.create')
        <a href="{{ route('cabang.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Cabang
        </a>
        @endcan
    </div>
    @endforelse

    {{-- Pagination mobile --}}
    @if($cabangs->hasPages())
    <div class="mt-3">
        {{ $cabangs->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- Modal Konfirmasi Hapus Cascade --}}
<div class="modal fade" id="modalHapusCabang" tabindex="-1" aria-labelledby="modalHapusCabangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-danger" id="modalHapusCabangLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Cabang
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-2">Anda akan menghapus cabang:</p>
                <div class="alert alert-danger py-2 px-3 mb-3">
                    <strong id="modalCabangNama"></strong>
                </div>
                <p class="text-muted mb-2" style="font-size:0.875rem">
                    <i class="bi bi-info-circle me-1"></i>
                    Seluruh data terkait cabang ini akan ikut dihapus secara otomatis, termasuk:
                </p>
                <ul class="list-unstyled mb-3 ps-1" style="font-size:0.8rem;color:#64748b">
                    <li><i class="bi bi-dot"></i> Karyawan & penggajian</li>
                    <li><i class="bi bi-dot"></i> Absensi & rekap kehadiran</li>
                    <li><i class="bi bi-dot"></i> Stok, order, & purchase order</li>
                    <li><i class="bi bi-dot"></i> Transfer stok & permintaan bahan</li>
                    <li><i class="bi bi-dot"></i> Transaksi keuangan & aset</li>
                    <li><i class="bi bi-dot"></i> Shift kerja & hari libur</li>
                </ul>
                <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:0.8rem">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                    Data yang dihapus dapat dipulihkan melalui menu <strong>Data Terhapus</strong>.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="formHapusCabang" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Ya, Hapus Cabang
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function confirmHapusCabang(id, nama) {
    document.getElementById('modalCabangNama').textContent = nama;
    document.getElementById('formHapusCabang').action = '/cabang/' + id;
    var modal = new bootstrap.Modal(document.getElementById('modalHapusCabang'));
    modal.show();
}
</script>
@endpush

@endsection
