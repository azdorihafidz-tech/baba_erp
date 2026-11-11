@extends('layouts.app')

@section('title', 'Penilaian Saya')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-clipboard2-check me-2 text-primary"></i>Penilaian Saya</h4>
        <p class="text-muted mb-0 small">Daftar karyawan yang harus Anda nilai</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @canany(['evaluasi.view'])
        <a href="{{ route('evaluasi.periods') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i>Daftar Periode
        </a>
        @endcanany
        <x-panduan-button slug="evaluasi-saya" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('warning'))
<div class="alert alert-warning alert-dismissible fade show">{{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@php
    $belumIsi = $reviewers->where('status', 'belum_isi');
    $sudahIsi = $reviewers->where('status', 'sudah_isi');
@endphp

{{-- Ringkasan --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-warning bg-opacity-10">
            <div class="card-body py-3">
                <div class="fw-bold fs-2 text-warning">{{ $belumIsi->count() }}</div>
                <div class="small text-muted">Belum Diisi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-success bg-opacity-10">
            <div class="card-body py-3">
                <div class="fw-bold fs-2 text-success">{{ $sudahIsi->count() }}</div>
                <div class="small text-muted">Sudah Diisi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-primary bg-opacity-10">
            <div class="card-body py-3">
                <div class="fw-bold fs-2 text-primary">{{ $reviewers->count() }}</div>
                <div class="small text-muted">Total Tugas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-secondary bg-opacity-10">
            <div class="card-body py-3">
                <div class="fw-bold fs-2 text-secondary">
                    {{ $reviewers->count() > 0 ? round($sudahIsi->count() / $reviewers->count() * 100) : 0 }}%
                </div>
                <div class="small text-muted">Selesai</div>
            </div>
        </div>
    </div>
</div>

@if($reviewers->isEmpty())
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-clipboard2-check text-muted" style="font-size:3rem"></i>
        <p class="mt-3 text-muted">Tidak ada penilaian yang harus Anda isi saat ini.</p>
        <p class="small text-muted">Penilaian akan muncul di sini ketika Anda ditugaskan sebagai penilai oleh manajer.</p>
    </div>
</div>
@else

{{-- Perlu Diisi --}}
@if($belumIsi->count() > 0)
<h6 class="fw-semibold text-warning mb-2"><i class="bi bi-clock me-1"></i>Perlu Diisi ({{ $belumIsi->count() }})</h6>

{{-- Desktop Table --}}
<div class="card mb-4 d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Karyawan Dinilai</th>
                    <th>Periode</th>
                    <th>Tipe Penilai</th>
                    <th>Bobot</th>
                    <th>Deadline</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($belumIsi as $rv)
                @php
                    $eval   = $rv->evaluation;
                    $period = $eval?->period;
                    $tipe   = is_object($rv->tipe_reviewer) ? $rv->tipe_reviewer->value : $rv->tipe_reviewer;
                    $isDeadlinePast = $period?->deadline_pengisian?->isPast();
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $eval?->karyawan?->nama_lengkap }}</div>
                        <small class="text-muted">{{ $eval?->karyawan?->jabatan }}</small>
                    </td>
                    <td>
                        <div>{{ $period?->nama_periode }}</div>
                        <small class="text-muted">{{ $period?->cabang?->nama_cabang }}</small>
                    </td>
                    <td>
                        @if($tipe === 'atasan')
                            <span class="badge bg-danger">Atasan Langsung</span>
                        @elseif($tipe === 'rekan_kerja')
                            <span class="badge bg-info">Rekan Kerja</span>
                        @else
                            <span class="badge bg-warning text-dark">Self Assessment</span>
                        @endif
                    </td>
                    <td><span class="text-muted">{{ $rv->bobot_reviewer_persen }}%</span></td>
                    <td class="{{ $isDeadlinePast ? 'text-danger' : '' }}">
                        {{ $period?->deadline_pengisian?->format('d/m/Y') ?? '-' }}
                        @if($isDeadlinePast)
                            <br><small class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Lewat deadline</small>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($eval)
                        <a href="{{ route('evaluasi.form-penilaian', $eval) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil-square me-1"></i>Isi Sekarang
                        </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Mobile Cards --}}
<div class="d-md-none mb-4">
    @foreach($belumIsi as $rv)
    @php
        $eval   = $rv->evaluation;
        $period = $eval?->period;
        $tipe   = is_object($rv->tipe_reviewer) ? $rv->tipe_reviewer->value : $rv->tipe_reviewer;
        $isDeadlinePast = $period?->deadline_pengisian?->isPast();
    @endphp
    <div class="card mb-2 border-warning border-opacity-50">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="fw-semibold">{{ $eval?->karyawan?->nama_lengkap }}</div>
                    <small class="text-muted">{{ $eval?->karyawan?->jabatan }}</small>
                </div>
                @if($tipe === 'atasan')
                    <span class="badge bg-danger">Atasan</span>
                @elseif($tipe === 'rekan_kerja')
                    <span class="badge bg-info">Rekan</span>
                @else
                    <span class="badge bg-warning text-dark">Self</span>
                @endif
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted d-block">{{ $period?->nama_periode }}</small>
                    <small class="{{ $isDeadlinePast ? 'text-danger' : 'text-muted' }}">
                        Deadline: {{ $period?->deadline_pengisian?->format('d/m/Y') ?? '-' }}
                    </small>
                </div>
                @if($eval)
                <a href="{{ route('evaluasi.form-penilaian', $eval) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil-square"></i> Isi
                </a>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Sudah Diisi --}}
@if($sudahIsi->count() > 0)
<h6 class="fw-semibold text-success mb-2"><i class="bi bi-check-circle me-1"></i>Sudah Diisi ({{ $sudahIsi->count() }})</h6>

{{-- Desktop Table --}}
<div class="card d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Karyawan Dinilai</th>
                    <th>Periode</th>
                    <th>Tipe Penilai</th>
                    <th>Dikumpulkan</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sudahIsi as $rv)
                @php
                    $eval   = $rv->evaluation;
                    $period = $eval?->period;
                    $tipe   = is_object($rv->tipe_reviewer) ? $rv->tipe_reviewer->value : $rv->tipe_reviewer;
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $eval?->karyawan?->nama_lengkap }}</div>
                        <small class="text-muted">{{ $eval?->karyawan?->jabatan }}</small>
                    </td>
                    <td>{{ $period?->nama_periode }}</td>
                    <td>
                        @if($tipe === 'atasan')
                            <span class="badge bg-danger">Atasan Langsung</span>
                        @elseif($tipe === 'rekan_kerja')
                            <span class="badge bg-info">Rekan Kerja</span>
                        @else
                            <span class="badge bg-warning text-dark">Self Assessment</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $rv->submitted_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td class="text-end">
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check2 me-1"></i>Selesai
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Mobile Cards --}}
<div class="d-md-none">
    @foreach($sudahIsi as $rv)
    @php
        $eval   = $rv->evaluation;
        $period = $eval?->period;
        $tipe   = is_object($rv->tipe_reviewer) ? $rv->tipe_reviewer->value : $rv->tipe_reviewer;
    @endphp
    <div class="card mb-2 border-success border-opacity-25">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">{{ $eval?->karyawan?->nama_lengkap }}</div>
                    <small class="text-muted">{{ $period?->nama_periode }}</small>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-check2 me-1"></i>Selesai
                </span>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endif
@endsection
