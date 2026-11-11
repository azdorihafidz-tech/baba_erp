@props([
    'action'      => '',
    'dari'        => null,
    'sampai'      => null,
    'sessionKey'  => 'daterange_default',
    'extraPresets' => [], // opsional: ['key' => 'Label'] — tombol preset tambahan, tidak mengubah 8 preset default
])
@php
    // Resolve dari/sampai: prop → session → default bulan ini
    $dariVal   = $dari   ?? session($sessionKey . '_dari',   now()->startOfMonth()->toDateString());
    $sampaiVal = $sampai ?? session($sessionKey . '_sampai', now()->toDateString());
@endphp
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body pb-2 pt-3">
        <form method="GET" action="{{ $action }}" id="dateRangeForm_{{ $sessionKey }}">
            {{-- Preserve other GET params passed as slot --}}
            {{ $slot ?? '' }}

            {{-- Date Inputs --}}
            <div class="row g-2 align-items-end mb-2">
                <div class="col-12 col-sm-5 col-md-3">
                    <label class="form-label form-label-sm fw-semibold mb-1">Dari Tanggal</label>
                    <input type="date" name="dari" id="inputDari_{{ $sessionKey }}"
                           class="form-control form-control-sm"
                           value="{{ $dariVal }}">
                </div>
                <div class="col-12 col-sm-5 col-md-3">
                    <label class="form-label form-label-sm fw-semibold mb-1">Sampai Tanggal</label>
                    <input type="date" name="sampai" id="inputSampai_{{ $sessionKey }}"
                           class="form-control form-control-sm"
                           value="{{ $sampaiVal }}">
                </div>
                <div class="col-12 col-sm-auto d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ $action }}" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-x-lg me-1"></i>Reset
                    </a>
                </div>
            </div>

            {{-- Quick Filter Buttons --}}
            <div class="quick-filter-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap;padding-bottom:4px">
                <div class="d-inline-flex gap-1 flex-nowrap">
                    <button type="button" class="btn btn-xs btn-outline-secondary qf-btn" style="font-size:.75rem;padding:.2rem .55rem"
                            data-key="{{ $sessionKey }}" data-range="today">Hari Ini</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary qf-btn" style="font-size:.75rem;padding:.2rem .55rem"
                            data-key="{{ $sessionKey }}" data-range="week">Minggu Ini</button>
                    <button type="button" class="btn btn-xs btn-outline-primary qf-btn" style="font-size:.75rem;padding:.2rem .55rem"
                            data-key="{{ $sessionKey }}" data-range="month">Bulan Ini</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary qf-btn" style="font-size:.75rem;padding:.2rem .55rem"
                            data-key="{{ $sessionKey }}" data-range="3month">3 Bulan</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary qf-btn" style="font-size:.75rem;padding:.2rem .55rem"
                            data-key="{{ $sessionKey }}" data-range="6month">6 Bulan</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary qf-btn" style="font-size:.75rem;padding:.2rem .55rem"
                            data-key="{{ $sessionKey }}" data-range="year">Tahun Ini</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary qf-btn" style="font-size:.75rem;padding:.2rem .55rem"
                            data-key="{{ $sessionKey }}" data-range="1year">1 Thn Terakhir</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary qf-btn" style="font-size:.75rem;padding:.2rem .55rem"
                            data-key="{{ $sessionKey }}" data-range="all">Semua Data</button>
                    @foreach($extraPresets as $rangeKey => $rangeLabel)
                    <button type="button" class="btn btn-xs btn-outline-secondary qf-btn" style="font-size:.75rem;padding:.2rem .55rem"
                            data-key="{{ $sessionKey }}" data-range="{{ $rangeKey }}">{{ $rangeLabel }}</button>
                    @endforeach
                </div>
            </div>
        </form>
    </div>
</div>

@once
@push('scripts')
<script>
(function() {
    function pad(n) { return String(n).padStart(2, '0'); }
    function toDate(d) { return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()); }

    function setRange(key, dari, sampai) {
        var form = document.getElementById('dateRangeForm_' + key);
        if (!form) return;
        form.querySelector('#inputDari_' + key).value   = dari;
        form.querySelector('#inputSampai_' + key).value = sampai;
        form.submit();
    }

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.qf-btn');
        if (!btn) return;
        var key   = btn.dataset.key;
        var range = btn.dataset.range;
        var now   = new Date();
        var y     = now.getFullYear();
        var m     = now.getMonth();
        var d     = now.getDate();

        switch (range) {
            case 'today':
                setRange(key, toDate(now), toDate(now));
                break;
            case 'week': {
                var day = now.getDay() || 7; // Mon=1, Sun=7
                var mon = new Date(now); mon.setDate(d - day + 1);
                var sun = new Date(mon);  sun.setDate(mon.getDate() + 6);
                setRange(key, toDate(mon), toDate(sun));
                break;
            }
            case 'month':
                setRange(key, toDate(new Date(y, m, 1)), toDate(new Date(y, m+1, 0)));
                break;
            case '3month':
                setRange(key, toDate(new Date(y, m-2, 1)), toDate(new Date(y, m+1, 0)));
                break;
            case '6month':
                setRange(key, toDate(new Date(y, m-5, 1)), toDate(new Date(y, m+1, 0)));
                break;
            case 'year':
                setRange(key, toDate(new Date(y, 0, 1)), toDate(new Date(y, 11, 31)));
                break;
            case '1year':
                setRange(key, toDate(new Date(y-1, m, d+1)), toDate(now));
                break;
            case 'all':
                setRange(key, '2020-01-01', toDate(now));
                break;
            case 'yesterday': {
                var yst = new Date(now); yst.setDate(d - 1);
                setRange(key, toDate(yst), toDate(yst));
                break;
            }
            case 'lastmonth':
                setRange(key, toDate(new Date(y, m-1, 1)), toDate(new Date(y, m, 0)));
                break;
        }
    });
})();
</script>
@endpush
@endonce
