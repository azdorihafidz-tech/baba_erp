@extends('layouts.app')

@section('title', 'Detail Periode: ' . $period->nama_periode)

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-people me-2 text-primary"></i>{{ $period->nama_periode }}
        </h4>
        <p class="text-muted mb-0 small">
            {{ $period->cabang?->nama_cabang }} &mdash;
            {{ $period->tanggal_mulai?->format('d/m/Y') }} s/d {{ $period->tanggal_selesai?->format('d/m/Y') }}
            &mdash; Deadline: <span class="{{ $period->deadline_pengisian?->isPast() ? 'text-danger fw-semibold' : '' }}">{{ $period->deadline_pengisian?->format('d/m/Y') }}</span>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @php $statusVal = is_object($period->status) ? $period->status->value : $period->status; @endphp
        @if($statusVal === 'draft')
            <form action="{{ route('evaluasi.period-open', $period) }}" method="POST">
                @csrf
                <button class="btn btn-sm btn-success"><i class="bi bi-play-fill me-1"></i>Buka Periode</button>
            </form>
        @elseif($statusVal === 'dibuka')
            <form action="{{ route('evaluasi.period-close', $period) }}" method="POST">
                @csrf
                <button class="btn btn-sm btn-warning"><i class="bi bi-stop-fill me-1"></i>Tutup Periode</button>
            </form>
        @elseif($statusVal === 'ditutup')
            <form action="{{ route('evaluasi.period-finalize', $period) }}" method="POST"
                  onsubmit="return confirm('Finalisasi semua penilaian di periode ini? Skor akan dihitung otomatis.')">
                @csrf
                <button class="btn btn-sm btn-primary"><i class="bi bi-check2-all me-1"></i>Finalisasi</button>
            </form>
        @endif
        <a href="{{ route('evaluasi.ranking', $period) }}" class="btn btn-sm btn-outline-info">
            <i class="bi bi-trophy me-1"></i>Ranking
        </a>
        <a href="{{ route('evaluasi.periods') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Ringkasan --}}
@php
    $totalEval     = $evaluations->count();
    $selesai       = $evaluations->where('status', 'selesai')->count() + $evaluations->where('status', 'final')->count();
    $totalReviewer = $evaluations->sum(fn($e) => $e->reviewers->count());
    $sudahIsi      = $evaluations->sum(fn($e) => $e->reviewers->where('status', 'sudah_isi')->count());
@endphp
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-primary bg-opacity-10 text-center py-3">
            <div class="fw-bold fs-3 text-primary">{{ $totalEval }}</div>
            <div class="small text-muted">Karyawan Dinilai</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-info bg-opacity-10 text-center py-3">
            <div class="fw-bold fs-3 text-info">{{ $totalReviewer }}</div>
            <div class="small text-muted">Total Penilai</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-success bg-opacity-10 text-center py-3">
            <div class="fw-bold fs-3 text-success">{{ $sudahIsi }}</div>
            <div class="small text-muted">Sudah Mengisi</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-warning bg-opacity-10 text-center py-3">
            <div class="fw-bold fs-3 text-warning">{{ $totalReviewer - $sudahIsi }}</div>
            <div class="small text-muted">Belum Mengisi</div>
        </div>
    </div>
</div>

{{-- Tabel Karyawan + Penilai --}}
@forelse($evaluations as $eval)
@php
    $karyawan     = $eval->karyawan;
    $reviewers    = $eval->reviewers;
    $evalStatus   = is_object($eval->status) ? $eval->status->value : $eval->status;
    $atasan       = $reviewers->first(fn($r) => (is_object($r->tipe_reviewer) ? $r->tipe_reviewer->value : $r->tipe_reviewer) === 'atasan');
    $rekans       = $reviewers->filter(fn($r) => (is_object($r->tipe_reviewer) ? $r->tipe_reviewer->value : $r->tipe_reviewer) === 'rekan_kerja');
    $self         = $reviewers->first(fn($r) => (is_object($r->tipe_reviewer) ? $r->tipe_reviewer->value : $r->tipe_reviewer) === 'self_assessment');
    $allFilled    = $reviewers->count() > 0 && $reviewers->where('status', 'sudah_isi')->count() === $reviewers->count();
@endphp
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
        <div class="d-flex align-items-center gap-3">
            <div>
                <span class="fw-semibold">{{ $karyawan?->nama_lengkap }}</span>
                <span class="text-muted ms-2 small">{{ $karyawan?->jabatan }}</span>
            </div>
            {{-- Status badge --}}
            @if($evalStatus === 'final')
                <span class="badge bg-success">Final</span>
            @elseif($evalStatus === 'selesai')
                <span class="badge bg-primary">Selesai Dinilai</span>
            @elseif($allFilled)
                <span class="badge bg-info">Semua Sudah Isi</span>
            @else
                @php $filledCount = $reviewers->where('status','sudah_isi')->count(); @endphp
                <span class="badge bg-warning text-dark">{{ $filledCount }}/{{ $reviewers->count() }} Mengisi</span>
            @endif
        </div>
        <div class="d-flex gap-1 flex-wrap">
            @if($evalStatus === 'final' || $evalStatus === 'selesai')
            <a href="{{ route('evaluasi.result', $eval) }}" class="btn btn-xs btn-outline-success btn-sm">
                <i class="bi bi-bar-chart me-1"></i>Hasil
            </a>
            @endif
            @if($statusVal !== 'final')
            <button class="btn btn-xs btn-outline-secondary btn-sm" data-bs-toggle="collapse"
                    data-bs-target="#assignCollapse{{ $eval->id }}">
                <i class="bi bi-person-plus me-1"></i>Tambah Rekan
            </button>
            @endif
        </div>
    </div>

    <div class="card-body p-0">
        {{-- Daftar Penilai --}}
        <div class="table-responsive d-none d-sm-block">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Penilai</th>
                        <th>Tipe</th>
                        <th class="text-center">Bobot</th>
                        <th class="text-center">Status</th>
                        <th>Dikumpulkan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reviewers as $rv)
                    @php $rvTipe = is_object($rv->tipe_reviewer) ? $rv->tipe_reviewer->value : $rv->tipe_reviewer; @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold small">{{ $rv->reviewer?->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:.7rem">{{ $rv->reviewer?->email }}</div>
                        </td>
                        <td>
                            @if($rvTipe === 'atasan')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Atasan</span>
                            @elseif($rvTipe === 'rekan_kerja')
                                <span class="badge bg-info-subtle text-info border border-info-subtle">Rekan Kerja</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Self</span>
                            @endif
                        </td>
                        <td class="text-center text-muted small">{{ $rv->bobot_reviewer_persen }}%</td>
                        <td class="text-center">
                            @if($rv->status === 'sudah_isi')
                                <span class="badge bg-success"><i class="bi bi-check2"></i> Sudah Isi</span>
                            @else
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Belum Isi</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $rv->submitted_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                    @endforeach
                    @if($reviewers->isEmpty())
                    <tr><td colspan="5" class="text-center text-muted py-2 small">Belum ada penilai ditetapkan.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Mobile: cards --}}
        <div class="d-sm-none p-2">
            @foreach($reviewers as $rv)
            @php $rvTipe = is_object($rv->tipe_reviewer) ? $rv->tipe_reviewer->value : $rv->tipe_reviewer; @endphp
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <div class="small fw-semibold">{{ $rv->reviewer?->name ?? '—' }}</div>
                    @if($rvTipe === 'atasan')
                        <span class="badge bg-danger-subtle text-danger" style="font-size:.65rem">Atasan</span>
                    @elseif($rvTipe === 'rekan_kerja')
                        <span class="badge bg-info-subtle text-info" style="font-size:.65rem">Rekan Kerja</span>
                    @else
                        <span class="badge bg-warning-subtle text-warning" style="font-size:.65rem">Self</span>
                    @endif
                </div>
                @if($rv->status === 'sudah_isi')
                    <span class="badge bg-success small"><i class="bi bi-check2"></i> Sudah</span>
                @else
                    <span class="badge bg-warning text-dark small"><i class="bi bi-clock"></i> Belum</span>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Form Tambah Rekan Kerja --}}
        @if($statusVal !== 'final')
        <div class="collapse" id="assignCollapse{{ $eval->id }}">
            <div class="p-3 border-top bg-light">
                <form action="{{ route('evaluasi.assign-rekan', $eval) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-sm-8 col-md-6">
                        <label class="form-label fw-semibold small mb-1">Tambah Rekan Kerja Penilai</label>
                        <select name="reviewer_id" class="form-select form-select-sm" required>
                            <option value="">-- Pilih User --</option>
                            @foreach($cabangUsers as $u)
                            @php $alreadyAssigned = $reviewers->contains('reviewer_id', $u->id); @endphp
                            <option value="{{ $u->id }}" {{ $alreadyAssigned ? 'disabled' : '' }}>
                                {{ $u->name }} {{ $alreadyAssigned ? '(sudah ditambahkan)' : '' }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-4 col-md-3">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-person-plus me-1"></i>Tambahkan
                        </button>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Bobot rekan kerja (30%) akan dibagi rata antar semua rekan yang ditambahkan.</small>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>

    @if($eval->skor_akhir)
    <div class="card-footer py-2 d-flex justify-content-between align-items-center">
        <small class="text-muted">Skor Akhir</small>
        <div>
            <span class="fw-bold text-primary">{{ number_format($eval->skor_akhir, 2) }}</span>
            @if($eval->predikat)
            <span class="badge ms-2 {{ method_exists($eval->predikat, 'badgeClass') ? $eval->predikat->badgeClass() : 'bg-secondary' }}">
                {{ method_exists($eval->predikat, 'label') ? $eval->predikat->label() : $eval->predikat }}
            </span>
            @endif
        </div>
    </div>
    @endif
</div>
@empty
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-people" style="font-size:2.5rem"></i>
        <p class="mt-3">Belum ada karyawan yang ditetapkan untuk periode ini.</p>
        @if($statusVal === 'draft')
        <p class="small">Klik <strong>"Buka Periode"</strong> untuk membuat evaluasi otomatis bagi semua karyawan aktif di cabang ini.</p>
        @endif
    </div>
</div>
@endforelse
@endsection
