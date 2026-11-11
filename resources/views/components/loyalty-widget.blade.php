{{--
    Widget "Pelanggan Loyalty Progress" — dipakai dashboard/cabang.blade.php
    dan dashboard/pusat.blade.php. Murni tampilan, data dari
    LoyaltyService::getWidgetData(). Loyalty TIDAK di-scope per cabang
    (kumulatif pelanggan lintas cabang by design), jadi widget ini tampil
    sama di kedua dashboard.

    Props:
        data – array (pelanggan_mendekati_target, jumlah_tercapai_belum_hadiah)
--}}
@props(['data'])
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span><i class="bi bi-award me-2 text-primary"></i>Pelanggan Loyalty Progress</span>
        <div class="d-flex align-items-center gap-2">
            @if($data['jumlah_tercapai_belum_hadiah'] > 0)
            <span class="badge bg-warning text-dark">
                <i class="bi bi-gift me-1"></i>{{ $data['jumlah_tercapai_belum_hadiah'] }} belum diberi hadiah
            </span>
            @endif
            <a href="{{ route('loyalty-program.index') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem">
                Lihat Semua
            </a>
        </div>
    </div>
    <div class="card-body">
        @if($data['pelanggan_mendekati_target']->isEmpty())
        <p class="text-muted text-center mb-0 py-2">Belum ada pelanggan yang mendekati target program loyalty (≥80%).</p>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Pelanggan</th>
                        <th class="d-none d-md-table-cell">Program</th>
                        <th style="min-width:160px">Progress</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['pelanggan_mendekati_target'] as $row)
                    <tr>
                        <td class="fw-semibold">
                            <a href="{{ route('pelanggan.show', $row['pelanggan_id']) }}">{{ $row['nama_pelanggan'] }}</a>
                        </td>
                        <td class="d-none d-md-table-cell small text-muted">{{ $row['program_nama'] }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar {{ $row['tercapai'] ? 'bg-success' : 'bg-primary' }}"
                                         style="width:{{ $row['persen_progress'] }}%"></div>
                                </div>
                                <small class="text-nowrap">{{ $row['persen_progress'] }}%</small>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
