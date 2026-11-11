@extends('layouts.app')

@section('title', 'Ranking Karyawan - ' . $period->nama_periode)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">Ranking Karyawan</h4>
        <small class="text-muted">{{ $period->nama_periode }} &bull; {{ $period->cabang?->nama_cabang }}</small>
    </div>
    <a href="{{ route('evaluasi.periods') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

@if($evaluations->isEmpty())
<div class="text-center text-muted py-5">
    <i class="bi bi-trophy" style="font-size:3rem;color:#e2e8f0"></i>
    <p class="mt-2">Belum ada evaluasi yang selesai untuk periode ini.</p>
</div>
@else

{{-- Top 3 --}}
@if($evaluations->count() >= 1)
<div class="row g-3 mb-4">
    @php
        $top3 = $evaluations->take(3);
        $medals = [
            0 => ['color' => '#FFD700', 'bg' => 'bg-warning bg-opacity-10', 'icon' => '🥇', 'label' => 'Terbaik'],
            1 => ['color' => '#C0C0C0', 'bg' => 'bg-secondary bg-opacity-10', 'icon' => '🥈', 'label' => '2nd'],
            2 => ['color' => '#CD7F32', 'bg' => 'bg-danger bg-opacity-10', 'icon' => '🥉', 'label' => '3rd'],
        ];
    @endphp
    @foreach($top3 as $idx => $e)
    <div class="col-12 col-md-4">
        <div class="card {{ $medals[$idx]['bg'] }} border-0">
            <div class="card-body text-center py-3">
                <div style="font-size:2.5rem">{{ $medals[$idx]['icon'] }}</div>
                <div class="fw-bold mt-1">{{ $e->karyawan?->nama_lengkap }}</div>
                <small class="text-muted d-block">{{ $e->karyawan?->jabatan }}</small>
                @if($e->skor_akhir)
                <div class="mt-2" style="font-size:1.8rem;font-weight:900;color:{{ $medals[$idx]['color'] }}">
                    {{ number_format($e->skor_akhir, 2) }}
                </div>
                @endif
                @if($e->predikat)
                <span class="badge {{ $e->predikat->badgeClass() }} mt-1">{{ $e->predikat->label() }}</span>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Tabel Lengkap --}}
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>Daftar Lengkap Ranking</span>
        <div class="input-group input-group-sm" style="max-width:220px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="filterRankingKaryawan" class="form-control form-control-sm" placeholder="Cari nama / jabatan...">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:50px">No.</th>
                    <th>Karyawan</th>
                    <th class="d-none d-md-table-cell">Jabatan</th>
                    <th class="text-center">Skor</th>
                    <th>Predikat</th>
                    <th class="text-end">Detail</th>
                </tr>
            </thead>
            <tbody>
                @foreach($evaluations as $rank => $e)
                <tr class="{{ $rank < 3 ? 'table-' . (['warning','light','light'][$rank] ?? '') : '' }}"
                    data-search-row="{{ strtolower($e->karyawan?->nama_lengkap . ' ' . $e->karyawan?->jabatan) }}">
                    <td class="fw-bold text-muted">
                        @if($rank === 0) 🥇
                        @elseif($rank === 1) 🥈
                        @elseif($rank === 2) 🥉
                        @else {{ $rank + 1 }}
                        @endif
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $e->karyawan?->nama_lengkap }}</div>
                        <small class="text-muted d-md-none">{{ $e->karyawan?->jabatan }}</small>
                    </td>
                    <td class="d-none d-md-table-cell text-muted">{{ $e->karyawan?->jabatan }}</td>
                    <td class="text-center">
                        <span class="fw-bold fs-6">{{ $e->skor_akhir ? number_format($e->skor_akhir, 2) : '-' }}</span>
                    </td>
                    <td>
                        @if($e->predikat)
                        <span class="badge {{ $e->predikat->badgeClass() }}">{{ $e->predikat->label() }}</span>
                        @else
                        <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('evaluasi.result', $e) }}" class="btn btn-xs btn-outline-primary btn-sm">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('evaluasi.history', $e->karyawan) }}" class="btn btn-xs btn-outline-secondary btn-sm ms-1">
                            <i class="bi bi-graph-up"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@push('scripts')
<script>
(function() {
    var input = document.getElementById('filterRankingKaryawan');
    if (!input) return;
    input.addEventListener('input', function() {
        var keyword = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-search-row]').forEach(function(row) {
            row.style.display = row.dataset.searchRow.includes(keyword) ? '' : 'none';
        });
    });
})();
</script>
@endpush
@endsection
