@extends('layouts.app')

@section('title', 'Riwayat Penilaian - ' . $karyawan->nama_lengkap)

@push('styles')
<style>
.chart-container { position: relative; height: 300px; }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Riwayat Penilaian Karyawan</h4>
    </div>
    <a href="{{ route('evaluasi.periods') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

{{-- Pilih Karyawan --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <label class="form-label mb-0">Karyawan:</label>
            <select name="karyawan_id" class="form-select form-select-sm" style="width:auto"
                    onchange="this.form.submit()">
                @foreach($karyawans as $k)
                <option value="{{ $k->id }}" {{ $karyawan->id == $k->id ? 'selected' : '' }}>
                    {{ $k->nama_lengkap }}
                </option>
                @endforeach
            </select>
        </form>
    </div>
</div>

{{-- Info Karyawan --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-3">
            <div>
                <div class="fw-semibold">{{ $karyawan->nama_lengkap }}</div>
                <small class="text-muted">{{ $karyawan->jabatan }} &bull; {{ $karyawan->cabang?->nama_cabang }}</small>
            </div>
        </div>
    </div>
</div>

@if($evaluations->count() > 0)
<div class="row g-3">
    {{-- Grafik Trend --}}
    <div class="col-12 col-md-7">
        <div class="card">
            <div class="card-header">Perkembangan Skor</div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="col-12 col-md-5">
        <div class="card">
            <div class="card-header">Riwayat Penilaian</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th class="text-center">Skor</th>
                            <th>Predikat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($evaluations as $e)
                        <tr>
                            <td>
                                <a href="{{ route('evaluasi.result', $e) }}" class="text-decoration-none">
                                    {{ $e->period?->nama_periode }}
                                </a>
                            </td>
                            <td class="text-center fw-bold">
                                {{ $e->skor_akhir ? number_format($e->skor_akhir, 2) : '-' }}
                            </td>
                            <td>
                                @if($e->predikat)
                                <span class="badge {{ $e->predikat->badgeClass() }} small">
                                    {{ $e->predikat->label() }}
                                </span>
                                @else
                                <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                @php $statusVal = is_object($e->status) ? $e->status->value : $e->status; @endphp
                                <span class="badge {{ $statusVal === 'final' ? 'bg-success' : 'bg-secondary' }} small">
                                    {{ ucfirst($statusVal) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@else
<div class="text-center text-muted py-5">
    <i class="bi bi-clipboard-x" style="font-size:3rem"></i>
    <p class="mt-2">Belum ada riwayat penilaian untuk karyawan ini.</p>
</div>
@endif

@push('scripts')
<script>
@php
$labels = $evaluations->map(fn($e) => $e->period?->nama_periode ?? '-')->toArray();
$scores = $evaluations->map(fn($e) => $e->skor_akhir ? round($e->skor_akhir, 2) : null)->toArray();
@endphp

@if($evaluations->count() > 0)
const ctx = document.getElementById('trendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: @json($labels),
        datasets: [{
            label: 'Skor Akhir',
            data: @json($scores),
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointBackgroundColor: '#3b82f6',
            pointRadius: 5,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                min: 0,
                max: 5,
                ticks: { stepSize: 1 },
                title: { display: true, text: 'Skor' }
            }
        },
        plugins: {
            legend: { display: false },
            annotation: {
                annotations: {
                    line1: { type: 'line', yMin: 3.5, yMax: 3.5, borderColor: '#22c55e', borderWidth: 1, borderDash: [5,5], label: { content: 'Baik (3.5)', display: true, position: 'end', font: {size:10} } }
                }
            }
        }
    }
});
@endif
</script>
@endpush
@endsection
