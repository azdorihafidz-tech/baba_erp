@extends('layouts.app')

@section('title', 'Manajemen Aset')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-building-gear me-2 text-primary"></i>Manajemen Aset</h5>
        <p class="text-muted mb-0 small">Aset tetap per lokasi / cabang</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('aset.depresiasi.auto')
        <form method="POST" action="{{ route('aset.generate-depresiasi') }}"
              onsubmit="return confirm('Generate penyusutan bulan {{ now()->translatedFormat('F Y') }} untuk semua aset aktif? Aset yang sudah dihitung otomatis dilewati.')">
            @csrf
            <button type="submit" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-calculator me-1"></i><span class="d-none d-sm-inline">Generate Depresiasi Bulan Ini</span>
            </button>
        </form>
        @endcan
        @can('aset.view')
        <a href="{{ route('aset.export', request()->query()) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </a>
        @endcan
        @can('aset.manage')
        <a href="{{ route('aset.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Tambah Aset</span>
        </a>
        @endcan
        <x-panduan-button slug="aset" />
    </div>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-boxes"></i></div>
                <div>
                    <div class="text-muted small">Aset Aktif</div>
                    <div class="fw-bold fs-4">{{ number_format($totalAktif) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <div class="text-muted small">Total Nilai Buku</div>
                    <div class="fw-bold fs-5">Rp {{ number_format($totalNilaiBuku, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-graph-down"></i></div>
                <div>
                    <div class="text-muted small">Penyusutan Bulan Ini</div>
                    <div class="fw-bold fs-5">Rp {{ number_format($totalPenyusutan, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Kode / nama aset..." col="col-12 col-sm-4 col-md-3" label="Cari" />
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label small mb-1">Lokasi</label>
                <select name="lokasi_id" class="form-select form-select-sm">
                    <option value="">Semua Lokasi</option>
                    @foreach($lokasis as $l)
                    <option value="{{ $l->id }}" @selected(request('lokasi_id') == $l->id)>{{ $l->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label small mb-1">Kategori</label>
                <select name="kategori_id" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    @foreach($kategoris as $k)
                    <option value="{{ $k->id }}" @selected(request('kategori_id') == $k->id)>{{ $k->nama_kategori }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <label class="form-label small mb-1">Kondisi</label>
                <select name="kondisi" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($kondisis as $k)
                    <option value="{{ $k->value }}" @selected(request('kondisi') === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($statuses as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('aset.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Desktop Table --}}
<div class="card d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Aset</th>
                    <th>Kategori</th>
                    <th>Lokasi</th>
                    <th>Tgl Perolehan</th>
                    <th class="text-end">Harga Perolehan</th>
                    <th class="text-end">Nilai Buku</th>
                    <th>Kondisi</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($assets as $a)
                <tr>
                    <td><code>{{ $a->kode_aset }}</code></td>
                    <td class="fw-semibold">{{ $a->nama_aset }}</td>
                    <td>{{ $a->kategori?->nama_kategori ?? '-' }}</td>
                    <td>{{ $a->lokasi?->nama_cabang ?? '-' }}</td>
                    <td>{{ $a->tanggal_perolehan->format('d/m/Y') }}</td>
                    <td class="text-end">Rp {{ number_format($a->harga_perolehan, 0, ',', '.') }}</td>
                    <td class="text-end fw-semibold">Rp {{ number_format($a->nilai_buku, 0, ',', '.') }}</td>
                    <td><span class="badge {{ $a->kondisi?->badgeClass() ?? 'bg-secondary' }}">{{ $a->kondisi?->label() ?? '-' }}</span></td>
                    <td><span class="badge {{ $a->status?->badgeClass() ?? 'bg-secondary' }}">{{ $a->status?->label() ?? '-' }}</span></td>
                    <td>
                        <div class="d-flex gap-1">
                        <a href="{{ route('aset.show', $a) }}" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:.2rem .5rem">
                            <i class="bi bi-eye"></i>
                        </a>
                        @can('aset.manage')
                        <button type="button" class="btn btn-xs btn-outline-danger" style="font-size:.75rem;padding:.2rem .5rem"
                            onclick="confirmHapus({{ $a->id }}, '{{ addslashes($a->nama_aset) }}')">
                            <i class="bi bi-trash3"></i>
                        </button>
                        @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data aset</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Mobile Cards --}}
<div class="d-md-none">
    @forelse($assets as $a)
    <div class="card mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">{{ $a->nama_aset }}</div>
                    <code class="small">{{ $a->kode_aset }}</code>
                    <div class="text-muted small">{{ $a->lokasi?->nama_cabang ?? '-' }}</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-primary">Rp {{ number_format($a->nilai_buku, 0, ',', '.') }}</div>
                    <span class="badge {{ $a->kondisi?->badgeClass() ?? 'bg-secondary' }} small">{{ $a->kondisi?->label() ?? '-' }}</span>
                </div>
            </div>
            <div class="mt-2 d-flex gap-2">
                <a href="{{ route('aset.show', $a) }}" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-eye me-1"></i>Detail
                </a>
                @can('aset.manage')
                <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="confirmHapus({{ $a->id }}, '{{ addslashes($a->nama_aset) }}')">
                    <i class="bi bi-trash3"></i>
                </button>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-4"><i class="bi bi-building-gear" style="font-size:2rem"></i><p class="mt-2">Belum ada data aset</p></div>
    @endforelse
</div>

<div class="mt-3">{{ $assets->links() }}</div>

@include('components.cascade-delete-modal', ['entity' => 'Aset', 'childList' => ['Riwayat penyusutan, maintenance, mutasi, & disposal dipertahankan di database (untuk audit keuangan)', 'Hanya record aset yang dihapus']])

@push('scripts')
<script>
function confirmHapus(id, nama) {
    document.getElementById('cascadeModalEntityName').textContent = nama;
    document.getElementById('cascadeModalForm').action = '/aset/' + id;
    new bootstrap.Modal(document.getElementById('cascadeDeleteModal')).show();
}
</script>
@endpush

@endsection
