{{--
    Widget "Kesehatan Finansial" (Dashboard Analytics, Fase 3) — dipakai
    dashboard/cabang.blade.php dan dashboard/pusat.blade.php. Murni tampilan,
    data dari DashboardAnalyticsService::getSnapshot() (yang sendiri murni
    komposisi NeracaService+LabaRugiFormalService+BepOtomatisService, lihat
    Rule bisnis #47).

    Props:
        analytics – array hasil DashboardAnalyticsService::getSnapshot()
--}}
@props(['analytics'])
@php
    $bep = $analytics['bep'];
    $pctBep = $bep['persentase_tercapai'];
    $chartId = 'chartAnalyticsTrend' . substr(md5(json_encode($analytics['trend']['labels'])), 0, 6);
@endphp
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header py-3 px-4">
                <span><i class="bi bi-heart-pulse me-2 text-danger"></i>Kesehatan Finansial</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    {{-- Card 1: Profitabilitas Bulan Ini --}}
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted mb-2" style="font-size:0.75rem">Profitabilitas Bulan Ini</div>
                            <div class="fw-bold" style="font-size:0.95rem">Rp {{ number_format($analytics['pendapatan_bulan_ini'], 0, ',', '.') }}</div>
                            <div class="text-muted" style="font-size:0.7rem">Pendapatan</div>
                            <div class="fw-bold mt-2 {{ $analytics['laba_bersih_bulan_ini'] >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:1rem">
                                Rp {{ number_format($analytics['laba_bersih_bulan_ini'], 0, ',', '.') }}
                            </div>
                            <div class="text-muted" style="font-size:0.7rem">
                                Laba Bersih
                                @if($analytics['margin_persen'] !== null)
                                    ({{ number_format($analytics['margin_persen'], 1, ',', '.') }}% margin)
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Card 2: BEP Status --}}
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted mb-2" style="font-size:0.75rem">BEP Status Bulan Ini</div>
                            @if($bep['bisa_bep'])
                                <div class="fw-bold" style="font-size:0.95rem">{{ number_format($bep['bep_unit'], 1, ',', '.') }} kg</div>
                                <div class="text-muted" style="font-size:0.7rem">BEP Unit vs {{ number_format($bep['volume_aktual'], 1, ',', '.') }} kg aktual</div>
                                <div class="progress mt-2" style="height:8px">
                                    <div class="progress-bar {{ $pctBep !== null && $pctBep >= 100 ? 'bg-success' : 'bg-warning' }}" style="width: {{ $pctBep !== null ? min(100, max(0,$pctBep)) : 0 }}%"></div>
                                </div>
                                <div class="fw-bold mt-1 {{ $pctBep !== null && $pctBep >= 100 ? 'text-success' : 'text-warning' }}" style="font-size:0.85rem">
                                    {{ $pctBep !== null ? number_format($pctBep, 1, ',', '.') . '%' : '-' }} tercapai
                                </div>
                            @else
                                <div class="text-danger fw-bold" style="font-size:0.85rem"><i class="bi bi-exclamation-triangle"></i> Margin kontribusi negatif</div>
                                <div class="text-muted" style="font-size:0.7rem">Harga jual &le; biaya variabel per kg</div>
                            @endif
                            <a href="{{ route('laporan.bep-otomatis.index') }}" class="d-block mt-2" style="font-size:0.72rem">Lihat Detail &raquo;</a>
                        </div>
                    </div>

                    {{-- Card 3: Rasio Keuangan Sederhana --}}
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted mb-2" style="font-size:0.75rem">Rasio Keuangan</div>
                            <div class="fw-bold {{ $analytics['rasio_lancar'] === null || $analytics['rasio_lancar'] >= 1 ? 'text-success' : 'text-danger' }}" style="font-size:0.95rem">
                                {{ $analytics['rasio_lancar'] !== null ? number_format($analytics['rasio_lancar'], 2, ',', '.') . 'x' : 'Aman (tanpa hutang)' }}
                            </div>
                            <div class="text-muted" style="font-size:0.7rem">Rasio Lancar (Aset Lancar / Kewajiban Jk. Pendek)</div>
                            <div class="fw-bold mt-2 {{ $analytics['roi_persen'] !== null && $analytics['roi_persen'] >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:0.95rem">
                                {{ $analytics['roi_persen'] !== null ? number_format($analytics['roi_persen'], 2, ',', '.') . '%' : '-' }}
                            </div>
                            <div class="text-muted" style="font-size:0.7rem">ROI Sederhana (Laba Bersih / Total Modal)</div>
                        </div>
                    </div>

                    {{-- Card 4: Trend 6 Bulan --}}
                    <div class="col-12 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted mb-2" style="font-size:0.75rem">Trend 6 Bulan</div>
                            <div style="position:relative;height:110px">
                                <canvas id="{{ $chartId }}"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    new Chart(document.getElementById('{{ $chartId }}'), {
        type: 'line',
        data: {
            labels: @json($analytics['trend']['labels']),
            datasets: [
                { label: 'Pendapatan', data: @json($analytics['trend']['pendapatan']), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 2 },
                { label: 'Beban', data: @json($analytics['trend']['beban']), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.05)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 2 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: true, labels: { font: { size: 9 }, boxWidth: 8 } },
                tooltip: { callbacks: { label: c => c.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) } }
            },
            scales: {
                x: { ticks: { font: { size: 8 } } },
                y: { ticks: { display: false } }
            }
        }
    });
})();
</script>
