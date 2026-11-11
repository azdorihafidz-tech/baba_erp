{{--
    Widget "Jam Ramai Hari Ini" — dipakai dashboard/cabang.blade.php dan
    dashboard/pusat.blade.php. Murni tampilan, data dari
    JamRamaiService::getAnalisaJamRamai() (Orders-only, SAMA persis dengan
    yang dipakai Laporan Analisa Jam Ramai — reuse penuh, nol duplikasi
    logic hitung jam puncak/sepi).

    Props:
        jamRamai – array hasil JamRamaiService::getAnalisaJamRamai()
--}}
@props(['jamRamai'])
@php
    $chartId = 'chartJamRamaiWidget' . substr(md5(json_encode($jamRamai['per_jam'])), 0, 6);
    $jamPuncak = $jamRamai['jam_puncak'];
@endphp
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
                <span><i class="bi bi-clock-history me-2 text-primary"></i>Jam Ramai Hari Ini</span>
                <a href="{{ route('laporan.jam-ramai.index') }}" style="font-size:0.75rem">Lihat Laporan Lengkap &raquo;</a>
            </div>
            <div class="card-body">
                @if($jamRamai['total_transaksi'] === 0)
                <div class="text-muted text-center py-3" style="font-size:0.85rem">
                    <i class="bi bi-inbox d-block mb-1" style="font-size:1.3rem"></i>
                    Belum ada order hari ini.
                </div>
                @else
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted mb-1" style="font-size:0.75rem">Jam Paling Ramai</div>
                            <div class="fw-bold text-success" style="font-size:1.3rem">{{ $jamPuncak['label'] ?? '-' }}</div>
                            <div class="text-muted" style="font-size:0.72rem">{{ $jamPuncak['jumlah_transaksi'] ?? 0 }} order &bull; Rp {{ number_format($jamPuncak['total_nominal'] ?? 0, 0, ',', '.') }}</div>
                            <div class="text-muted mt-2" style="font-size:0.72rem">Total hari ini: <strong>{{ $jamRamai['total_transaksi'] }} order</strong></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div style="position:relative;height:130px">
                            <canvas id="{{ $chartId }}"></canvas>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($jamRamai['total_transaksi'] > 0)
<script>
(function() {
    var d = @json($jamRamai['per_jam']);
    var jamPuncak = @json($jamPuncak['jam'] ?? null);
    // Cuma render jam yang punya aktivitas (mini widget, hindari 24 bar kosong)
    var aktif = d.filter(function(j) { return j.jumlah_transaksi > 0; });
    new Chart(document.getElementById('{{ $chartId }}'), {
        type: 'bar',
        data: {
            labels: aktif.map(function(j) { return j.label; }),
            datasets: [{
                label: 'Order',
                data: aktif.map(function(j) { return j.jumlah_transaksi; }),
                backgroundColor: aktif.map(function(j) { return (jamPuncak !== null && j.jam === jamPuncak) ? '#22c55e' : '#94a3b8'; })
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { font: { size: 9 } } },
                y: { beginAtZero: true, ticks: { precision: 0, font: { size: 9 } } }
            }
        }
    });
})();
</script>
@endif
