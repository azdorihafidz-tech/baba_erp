@extends('layouts.app')

@section('title', 'Laporan Evaluasi Karyawan')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Penilaian Karyawan 360°</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Ringkasan hasil penilaian per periode</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>
            <span class="d-none d-sm-inline">Export Excel</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i>
            <span class="d-none d-sm-inline">Export PDF</span>
        </a>
        <a href="{{ route('evaluasi.periods') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-star-half me-1"></i>
            <span class="d-none d-sm-inline">Kelola Evaluasi</span>
        </a>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.hr.evaluasi') }}">
            <div class="row g-2 align-items-end">
                @if(auth()->user()->canAccessAllBranches())
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label form-label-sm">Cabang</label>
                    <select name="cabang_id" class="form-select form-select-sm">
                        <option value="">Semua Cabang</option>
                        @foreach($cabangs as $cab)
                        <option value="{{ $cab->id }}" {{ $cabangId == $cab->id ? 'selected' : '' }}>{{ $cab->nama_cabang }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label form-label-sm">Periode Evaluasi</label>
                    <select name="period_id" class="form-select form-select-sm">
                        <option value="">-- Pilih Periode --</option>
                        @foreach($periods as $period)
                        <option value="{{ $period->id }}" {{ $selectedPeriod?->id == $period->id ? 'selected' : '' }}>
                            {{ $period->nama_periode }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@if($selectedPeriod)
<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-people text-primary"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalEvaluasi }}</div>
            <div class="text-muted" style="font-size:0.8rem">Karyawan Dinilai</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-star-fill text-success"></i></div>
            <div class="fw-bold text-success" style="font-size:1.5rem">{{ number_format($rataRataSkor, 2, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Rata-rata Skor</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-trophy text-warning"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $sangat_baik }}</div>
            <div class="text-muted" style="font-size:0.8rem">Predikat Sangat Baik</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-hand-thumbs-up text-info"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $baik }}</div>
            <div class="text-muted" style="font-size:0.8rem">Predikat Baik</div>
        </div>
    </div>
</div>

<!-- Tabel Ranking -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span>
            <i class="bi bi-trophy me-2 text-warning"></i>
            Ranking Penilaian — {{ $selectedPeriod->nama_periode }}
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4" style="width:60px">Rank</th>
                        <th>Karyawan</th>
                        <th class="d-none d-md-table-cell">Cabang</th>
                        <th class="text-center">Skor</th>
                        <th class="text-center">Predikat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($evaluations as $idx => $eval)
                    @php
                        $predikatBadge = $eval->predikat?->badgeClass() ?? 'bg-secondary';
                        $predikatLabel = $eval->predikat?->label() ?? ucfirst($eval->predikat ?? '-');
                    @endphp
                    <tr>
                        <td class="px-4">
                            @if($idx === 0)
                                <span class="badge bg-warning text-dark">1</span>
                            @elseif($idx === 1)
                                <span class="badge bg-secondary">2</span>
                            @elseif($idx === 2)
                                <span class="badge bg-danger">3</span>
                            @else
                                <span class="text-muted" style="font-size:0.875rem">{{ $idx + 1 }}</span>
                            @endif
                        </td>
                        <td class="fw-medium" style="font-size:0.875rem">{{ $eval->karyawan?->nama_lengkap ?? '-' }}</td>
                        <td class="d-none d-md-table-cell text-muted" style="font-size:0.875rem">{{ $eval->cabang?->nama_cabang ?? '-' }}</td>
                        <td class="text-center">
                            <div class="fw-bold" style="font-size:1rem">{{ number_format($eval->skor_akhir ?? 0, 2, ',', '.') }}</div>
                            <div class="progress mt-1" style="height:4px">
                                <div class="progress-bar bg-success" style="width:{{ ($eval->skor_akhir / 5) * 100 }}%"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $predikatBadge }}" style="font-size:0.7rem">{{ $predikatLabel }}</span>
                        </td>
                        <td>
                            <a href="{{ route('evaluasi.result', $eval) }}" class="btn btn-xs btn-outline-primary" style="font-size:0.7rem;padding:2px 8px">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox me-2"></i>Belum ada hasil penilaian
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
<div class="text-center text-muted py-5">
    <i class="bi bi-search" style="font-size:3rem"></i>
    <p class="mt-3">Pilih periode evaluasi untuk melihat data</p>
    @if($periods->isEmpty())
    <p><a href="{{ route('evaluasi.period-create') }}" class="btn btn-primary btn-sm">Buat Periode Evaluasi</a></p>
    @endif
</div>
@endif

@endsection
