@extends('layouts.app')

@section('title', 'Detail Aset — ' . $asset->kode_aset)

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0">
            <i class="bi bi-building-gear me-2 text-primary"></i>{{ $asset->nama_aset }}
        </h5>
        <div class="d-flex gap-2 mt-1">
            <code class="small">{{ $asset->kode_aset }}</code>
            <span class="badge {{ $asset->kondisi?->badgeClass() ?? 'bg-secondary' }}">{{ $asset->kondisi?->label() ?? $asset->getRawOriginal('kondisi') ?? '-' }}</span>
            <span class="badge {{ $asset->status?->badgeClass() ?? 'bg-secondary' }}">{{ $asset->status?->label() ?? $asset->getRawOriginal('status') ?? '-' }}</span>
        </div>
    </div>
    <div class="d-flex gap-2">
        @can('aset.manage')
        <a href="{{ route('aset.edit', $asset) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endcan
        <a href="{{ route('aset.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

{{-- Info Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="text-muted small">Harga Perolehan</div>
            <div class="fw-bold">Rp {{ number_format($asset->harga_perolehan, 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="text-muted small">Nilai Buku Sekarang</div>
            <div class="fw-bold text-primary fs-5">Rp {{ number_format($asset->nilai_buku ?? 0, 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="text-muted small">Akumulasi Penyusutan</div>
            <div class="fw-bold text-warning">Rp {{ number_format($asset->depreciations->sum('jumlah_penyusutan'), 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="text-muted small">Nilai Residu</div>
            <div class="fw-bold">Rp {{ number_format($asset->nilai_residu, 0, ',', '.') }}</div>
        </div>
    </div>
</div>

{{-- Progress Nilai Buku --}}
@if($asset->harga_perolehan > 0)
@php $pct = min(100, ($asset->nilai_buku / $asset->harga_perolehan) * 100); @endphp
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-1">
            <small class="text-muted">Sisa Nilai Buku</small>
            <small class="fw-semibold">{{ number_format($pct, 1) }}%</small>
        </div>
        <div class="progress" style="height:10px">
            <div class="progress-bar {{ $pct > 60 ? 'bg-success' : ($pct > 30 ? 'bg-warning' : 'bg-danger') }}"
                style="width:{{ $pct }}%"></div>
        </div>
    </div>
</div>
@endif

{{-- Info Detail --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header">Informasi Umum</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" width="45%">Kategori</td><td>{{ $asset->kategori?->nama_kategori ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Lokasi</td><td>{{ $asset->lokasi?->nama_cabang ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Tanggal Perolehan</td><td>{{ $asset->tanggal_perolehan->format('d/m/Y') }}</td></tr>
                    <tr><td class="text-muted">Umur Ekonomis</td><td>{{ $asset->umur_ekonomis_bulan }} bulan</td></tr>
                    <tr><td class="text-muted">Metode Penyusutan</td><td>{{ $asset->metode_penyusutan?->label() ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Dicatat oleh</td><td>{{ $asset->createdBy?->name ?? '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    @if($asset->catatan)
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header">Catatan</div>
            <div class="card-body">
                <p class="mb-0">{{ $asset->catatan }}</p>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Tabs --}}
<ul class="nav nav-tabs mb-3" id="asetTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPenyusutan">
            <i class="bi bi-graph-down me-1"></i>Penyusutan
            <span class="badge bg-secondary ms-1">{{ $asset->depreciations->count() }}</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMaintenance">
            <i class="bi bi-tools me-1"></i>Perawatan
            <span class="badge bg-secondary ms-1">{{ $asset->maintenances->count() }}</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDisposal">
            <i class="bi bi-trash me-1"></i>Disposal
        </button>
    </li>
</ul>

<div class="tab-content">

    {{-- TAB PENYUSUTAN --}}
    <div class="tab-pane fade show active" id="tabPenyusutan">
        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header">Riwayat Penyusutan</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Periode</th>
                                    <th class="text-end">Nilai Buku Awal</th>
                                    <th class="text-end">Penyusutan</th>
                                    <th class="text-end">Akumulasi</th>
                                    <th class="text-end">Nilai Buku Akhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($asset->depreciations->sortByDesc('periode') as $dep)
                                <tr>
                                    <td>{{ $dep->periode }}</td>
                                    <td class="text-end">Rp {{ number_format($dep->nilai_buku_awal, 0, ',', '.') }}</td>
                                    <td class="text-end text-warning">Rp {{ number_format($dep->jumlah_penyusutan, 0, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format($dep->akumulasi_penyusutan, 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">Rp {{ number_format($dep->nilai_buku_akhir, 0, ',', '.') }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat penyusutan</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                @can('aset.manage')
                @if(!$asset->disposal && $asset->nilai_buku > $asset->nilai_residu)
                <div class="card">
                    <div class="card-header">Hitung Penyusutan</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('aset.hitung-penyusutan', $asset) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Periode (Bulan)</label>
                                <input type="month" name="periode" class="form-control" value="{{ date('Y-m') }}" required>
                            </div>
                            @if($asset->metode_penyusutan?->value === 'satuan_produksi')
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Produksi Aktual (unit)</label>
                                <input type="number" name="produksi_aktual" class="form-control" value="0" min="0">
                            </div>
                            @endif
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bi bi-calculator me-1"></i>Hitung Penyusutan
                            </button>
                        </form>
                    </div>
                </div>
                @else
                <div class="alert alert-secondary">
                    <i class="bi bi-info-circle me-1"></i>
                    @if($asset->disposal)
                    Aset sudah di-disposal. Penyusutan tidak dapat dihitung.
                    @else
                    Nilai buku sudah sama dengan nilai residu. Tidak ada penyusutan lagi.
                    @endif
                </div>
                @endif
                @endcan
            </div>
        </div>
    </div>

    {{-- TAB MAINTENANCE --}}
    <div class="tab-pane fade" id="tabMaintenance">
        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header">Riwayat Perawatan</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jenis</th>
                                    <th>Deskripsi</th>
                                    <th>Vendor</th>
                                    <th class="text-end">Biaya</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($asset->maintenances->sortByDesc('tanggal_maintenance') as $m)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($m->tanggal_maintenance)->format('d/m/Y') }}</td>
                                    <td>
                                        @php $warna = match($m->jenis) { 'overhaul' => 'danger', 'perbaikan' => 'warning', default => 'success' }; @endphp
                                        <span class="badge bg-{{ $warna }}">{{ ucfirst(str_replace('_', ' ', $m->jenis)) }}</span>
                                    </td>
                                    <td>{{ $m->deskripsi }}</td>
                                    <td>{{ $m->vendor_maintenance ?? '-' }}</td>
                                    <td class="text-end">Rp {{ number_format($m->biaya, 0, ',', '.') }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat perawatan</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                @can('aset.manage')
                <div class="card">
                    <div class="card-header">Tambah Perawatan</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('aset.maintenance', $asset) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Tanggal</label>
                                <input type="date" name="tanggal_maintenance" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Jenis</label>
                                <select name="jenis" class="form-select form-select-sm">
                                    <option value="perawatan_rutin">Perawatan Rutin</option>
                                    <option value="perbaikan">Perbaikan</option>
                                    <option value="overhaul">Overhaul</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Deskripsi</label>
                                <textarea name="deskripsi" class="form-control form-control-sm" rows="2" required></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Biaya (Rp)</label>
                                <input type="number" name="biaya" class="form-control form-control-sm" value="0" min="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Vendor</label>
                                <input type="text" name="vendor_maintenance" class="form-control form-control-sm" placeholder="Nama vendor...">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-plus-circle me-1"></i>Tambah
                            </button>
                        </form>
                    </div>
                </div>
                @endcan
            </div>
        </div>
    </div>

    {{-- TAB DISPOSAL --}}
    <div class="tab-pane fade" id="tabDisposal">
        @if($asset->disposal)
        <div class="card">
            <div class="card-header">Data Disposal</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <table class="table table-sm">
                            <tr><td class="text-muted">Tanggal Disposal</td><td>{{ \Carbon\Carbon::parse($asset->disposal->tanggal_disposal)->format('d/m/Y') }}</td></tr>
                            <tr><td class="text-muted">Tipe</td><td>{{ ucfirst($asset->disposal->tipe) }}</td></tr>
                            <tr><td class="text-muted">Pembeli</td><td>{{ $asset->disposal->pembeli ?? '-' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-12 col-md-6">
                        <table class="table table-sm">
                            <tr><td class="text-muted">Nilai Buku Saat Disposal</td><td>Rp {{ number_format($asset->disposal->nilai_buku_saat_disposal, 0, ',', '.') }}</td></tr>
                            <tr><td class="text-muted">Nilai Jual</td><td>Rp {{ number_format($asset->disposal->nilai_jual, 0, ',', '.') }}</td></tr>
                            <tr>
                                <td class="text-muted">{{ $asset->disposal->keuntungan_kerugian >= 0 ? 'Keuntungan' : 'Kerugian' }}</td>
                                <td class="{{ $asset->disposal->keuntungan_kerugian >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                    Rp {{ number_format(abs($asset->disposal->keuntungan_kerugian), 0, ',', '.') }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    @if($asset->disposal->catatan)
                    <div class="col-12"><p class="text-muted mb-0">{{ $asset->disposal->catatan }}</p></div>
                    @endif
                </div>
            </div>
        </div>
        @elseif(in_array($asset->status?->value, ['aktif', 'tidak_aktif']) && auth()->user()->can('aset.manage'))
        <div class="card">
            <div class="card-header">Proses Disposal Aset</div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Disposal akan mengubah status aset secara permanen.
                </div>
                <form method="POST" action="{{ route('aset.disposal', $asset) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Tanggal Disposal</label>
                            <input type="date" name="tanggal_disposal" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Tipe</label>
                            <select name="tipe" class="form-select" required>
                                <option value="dijual">Dijual</option>
                                <option value="dibuang">Dibuang / Dihapus</option>
                                <option value="dihibahkan">Dihibahkan</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Nilai Jual (Rp)</label>
                            <input type="number" name="nilai_jual" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Pembeli</label>
                            <input type="text" name="pembeli" class="form-control" placeholder="Nama pembeli/penerima hibah...">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="1"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Yakin proses disposal aset ini?')">
                                <i class="bi bi-trash me-1"></i>Proses Disposal
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @else
        <div class="alert alert-secondary mt-2">
            <i class="bi bi-info-circle me-1"></i>Aset ini sudah berstatus {{ $asset->status?->label() ?? '-' }}.
        </div>
        @endif
    </div>

</div>
@endsection
