@extends('layouts.app')

@section('title', 'Hasil Penilaian - ' . $evaluation->karyawan?->nama_lengkap)

@push('styles')
<style>
.radar-container { position: relative; height: 320px; max-width: 380px; margin: 0 auto; }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Hasil Penilaian</h4>
        <small class="text-muted">{{ $evaluation->period?->nama_periode }}</small>
    </div>
    <div class="d-flex gap-2">
        @if($evaluation->status->value !== 'final' && isset($authUser) && $authUser->can('evaluasi.manage'))
        <form action="{{ route('evaluasi.period-finalize', $evaluation->period) }}" method="POST">
            @csrf
            <button class="btn btn-sm btn-primary" onclick="return confirm('Finalisasi evaluasi ini?')">
                <i class="bi bi-check2-all me-1"></i>Finalisasi
            </button>
        </form>
        @endif
        <a href="{{ route('karyawan.show', $evaluation->karyawan) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-person me-1"></i>Profil Karyawan
        </a>
        <a href="{{ route('evaluasi.periods') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

{{-- Header Hasil --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="text-center" style="min-width:80px">
                @if($evaluation->skor_akhir)
                <div style="font-size:2.5rem;font-weight:900;line-height:1;color:#3b82f6">
                    {{ number_format($evaluation->skor_akhir, 2) }}
                </div>
                <small class="text-muted">dari 5.00</small>
                @else
                <div class="text-muted">Belum dihitung</div>
                @endif
            </div>
            <div>
                <div class="h5 mb-1">{{ $evaluation->karyawan?->nama_lengkap }}</div>
                <div class="text-muted small">{{ $evaluation->karyawan?->jabatan }}</div>
                @if($evaluation->predikat)
                <div class="mt-1">
                    <span class="badge fs-6 {{ $evaluation->predikat->badgeClass() }}">
                        {{ $evaluation->predikat->label() }}
                    </span>
                </div>
                @endif
            </div>
            <div class="ms-auto">
                @php $statusVal = is_object($evaluation->status) ? $evaluation->status->value : $evaluation->status; @endphp
                @if($statusVal === 'final')
                    <span class="badge bg-success">Final</span>
                @elseif($statusVal === 'selesai')
                    <span class="badge bg-primary">Selesai Dihitung</span>
                @else
                    <span class="badge bg-warning text-dark">Dalam Proses</span>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Radar Chart --}}
    <div class="col-12 col-md-5">
        <div class="card">
            <div class="card-header">Grafik Radar 5 Aspek</div>
            <div class="card-body">
                <div class="radar-container">
                    <canvas id="radarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Skor per Aspek --}}
    <div class="col-12 col-md-7">
        <div class="card">
            <div class="card-header">Detail Skor per Aspek</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Aspek</th>
                            <th class="text-center">Atasan</th>
                            <th class="text-center">Rekan (rata)</th>
                            <th class="text-center">Self</th>
                            <th class="text-center">Tertimbang</th>
                            <th class="text-center">× Bobot</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($aspects as $aspect)
                        @php
                            $summary = $evaluation->summaries->where('evaluation_aspect_id', $aspect->id)->first();
                        @endphp
                        <tr>
                            <td>
                                <div class="small fw-semibold">{{ $aspect->nama_aspek }}</div>
                                <small class="text-muted">Bobot {{ $aspect->bobot_persen }}%</small>
                            </td>
                            <td class="text-center small">{{ $summary ? number_format($summary->skor_atasan, 1) : '-' }}</td>
                            <td class="text-center small">{{ $summary ? number_format($summary->skor_rekan_rata, 1) : '-' }}</td>
                            <td class="text-center small">{{ $summary ? number_format($summary->skor_self, 1) : '-' }}</td>
                            <td class="text-center small">{{ $summary ? number_format($summary->skor_tertimbang, 2) : '-' }}</td>
                            <td class="text-center fw-bold small">{{ $summary ? number_format($summary->skor_final, 4) : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    @if($evaluation->skor_akhir)
                    <tfoot class="table-primary">
                        <tr>
                            <td colspan="5" class="fw-bold">SKOR AKHIR</td>
                            <td class="text-center fw-bold fs-6">{{ number_format($evaluation->skor_akhir, 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Status Reviewer --}}
        <div class="card mt-3">
            <div class="card-header">Status Penilai</div>
            <div class="card-body p-0">
                @foreach($evaluation->reviewers as $r)
                @php $tipeVal = is_object($r->tipe_reviewer) ? $r->tipe_reviewer->value : $r->tipe_reviewer; @endphp
                <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge {{ $tipeVal === 'atasan' ? 'bg-danger' : ($tipeVal === 'self_assessment' ? 'bg-warning text-dark' : 'bg-info') }} me-2">
                            {{ match($tipeVal) {'atasan'=>'Atasan','rekan_kerja'=>'Rekan','self_assessment'=>'Self',default=>$tipeVal} }}
                        </span>
                        @if($tipeVal !== 'rekan_kerja')
                        <span class="small">{{ $r->reviewer?->name }}</span>
                        @else
                        <span class="small text-muted">(anonim)</span>
                        @endif
                    </div>
                    @if($r->status === 'sudah_isi')
                        <span class="badge bg-success"><i class="bi bi-check-lg"></i> Sudah Isi</span>
                    @else
                        <span class="badge bg-secondary">Belum Isi</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@if($jumlahRekan > 0)
<div class="alert alert-info mt-3">
    <i class="bi bi-info-circle me-2"></i>
    Skor rekan kerja merupakan rata-rata dari <strong>{{ $jumlahRekan }} penilai</strong>.
    Identitas penilai rekan kerja dirahasiakan untuk menjaga objektivitas.
</div>
@endif

@if($evaluation->catatan_manajer)
<div class="card mt-3">
    <div class="card-header">Catatan Manajer</div>
    <div class="card-body">{{ $evaluation->catatan_manajer }}</div>
</div>
@endif

@push('scripts')
<script>
@php
$aspectLabels = $aspects->pluck('nama_aspek')->toArray();
$radarData = $aspects->map(function($aspect) use ($evaluation) {
    $summary = $evaluation->summaries->where('evaluation_aspect_id', $aspect->id)->first();
    return $summary ? round($summary->skor_tertimbang, 2) : 0;
})->toArray();
@endphp

const ctx = document.getElementById('radarChart').getContext('2d');
new Chart(ctx, {
    type: 'radar',
    data: {
        labels: @json($aspectLabels),
        datasets: [{
            label: '{{ $evaluation->karyawan?->nama_lengkap }}',
            data: @json($radarData),
            backgroundColor: 'rgba(59, 130, 246, 0.15)',
            borderColor: 'rgba(59, 130, 246, 0.8)',
            borderWidth: 2,
            pointBackgroundColor: 'rgba(59, 130, 246, 1)',
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            r: {
                min: 0,
                max: 5,
                ticks: { stepSize: 1, font: { size: 10 } },
                pointLabels: { font: { size: 11 } }
            }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.label + ': ' + ctx.raw.toFixed(2)
                }
            }
        }
    }
});
</script>
@endpush
@endsection
