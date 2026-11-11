@props([
    'data'       => [],
    'periodeAktif' => 'bulan_ini',
    'labelPeriode' => 'Bulan Ini',
    'presetList' => [],
    'formAction' => null,  // null = no form (dashboard, pakai GET query string)
    'cabangId'   => null,  // untuk hidden input kalau dibutuhkan
    'showHeader' => true,
])

@php
    $rrNilai   = $data['rata_rata_nilai_transaksi'] ?? 0;
    $rrJumlah  = $data['rata_rata_jumlah_transaksi'] ?? 0;
    $topNilai  = $data['hari_nilai_tertinggi'] ?? ['tanggal' => null, 'nilai' => 0];
    $topJumlah = $data['hari_jumlah_tertinggi'] ?? ['tanggal' => null, 'nilai' => 0];
    $rrOmzet   = $data['rata_rata_omzet_harian'] ?? 0;
    $hariAktif = $data['hari_aktif'] ?? 0;

    $fmtTgl = function ($t) {
        if (!$t) return '—';
        try { return \Carbon\Carbon::parse($t)->translatedFormat('d M Y'); }
        catch (\Throwable $e) { return (string) $t; }
    };

    $kartu = [
        [
            'label' => 'Rata-rata Nilai Transaksi',
            'nilai' => fmt_rupiah_singkat($rrNilai),
            'full'  => fmt_rupiah($rrNilai),
            'icon'  => 'bi-cash-coin',
            'warna' => 'success',
            'sub'   => 'Per order dalam periode',
        ],
        [
            'label' => 'Rata-rata Jumlah Transaksi',
            'nilai' => number_format($rrJumlah, 1, ',', '.') . ' / hari',
            'full'  => number_format($rrJumlah, 2, ',', '.') . ' transaksi/hari aktif',
            'icon'  => 'bi-receipt',
            'warna' => 'primary',
            'sub'   => "{$hariAktif} hari aktif",
        ],
        [
            'label' => 'Hari Nilai Tertinggi',
            'nilai' => fmt_rupiah_singkat($topNilai['nilai']),
            'full'  => $fmtTgl($topNilai['tanggal']) . ' — ' . fmt_rupiah($topNilai['nilai']),
            'icon'  => 'bi-graph-up-arrow',
            'warna' => 'warning',
            'sub'   => $fmtTgl($topNilai['tanggal']),
        ],
        [
            'label' => 'Hari Jumlah Tertinggi',
            'nilai' => number_format($topJumlah['nilai'], 0, ',', '.') . ' trx',
            'full'  => $fmtTgl($topJumlah['tanggal']) . ' — ' . number_format($topJumlah['nilai'], 0, ',', '.') . ' transaksi',
            'icon'  => 'bi-bar-chart-steps',
            'warna' => 'info',
            'sub'   => $fmtTgl($topJumlah['tanggal']),
        ],
        [
            'label' => 'Rata-rata Omzet Harian',
            'nilai' => fmt_rupiah_singkat($rrOmzet),
            'full'  => fmt_rupiah($rrOmzet) . ' / hari aktif',
            'icon'  => 'bi-calendar3',
            'warna' => 'secondary',
            'sub'   => "{$hariAktif} hari aktif",
        ],
    ];
@endphp

<div class="card mb-4 analisis-penjualan-section">
    @if($showHeader)
        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Analisis Penjualan</h5>
                <small class="text-muted">Periode: <strong>{{ $labelPeriode }}</strong></small>
            </div>
            <form method="GET" action="{{ $formAction ?? url()->current() }}" class="d-flex gap-2 align-items-center">
                @if($cabangId)
                    <input type="hidden" name="cabang_id" value="{{ $cabangId }}">
                @endif
                <label for="apPeriode" class="form-label mb-0 small text-muted">Periode:</label>
                <select name="ap_periode" id="apPeriode" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    @foreach($presetList as $key => $label)
                        <option value="{{ $key }}" @selected($key === $periodeAktif)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    @endif
    <div class="card-body">
        <div class="row g-3">
            @foreach($kartu as $k)
                <div class="col-6 col-md-4 col-lg">
                    <div class="p-3 rounded border bg-light-subtle h-100"
                         data-bs-toggle="tooltip"
                         data-bs-placement="top"
                         title="{{ $k['full'] }}">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <i class="bi {{ $k['icon'] }} text-{{ $k['warna'] }}" style="font-size:1.25rem;"></i>
                            <div class="small text-muted lh-sm">{{ $k['label'] }}</div>
                        </div>
                        <div class="fw-bold fs-5 lh-1 analisis-nilai">{{ $k['nilai'] }}</div>
                        <div class="small text-muted mt-1">{{ $k['sub'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.bootstrap && bootstrap.Tooltip) {
                document.querySelectorAll('.analisis-penjualan-section [data-bs-toggle="tooltip"]').forEach(function (el) {
                    new bootstrap.Tooltip(el);
                });
            }
        });
    </script>
    @endpush
@endonce
