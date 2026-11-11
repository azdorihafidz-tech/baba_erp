{{--
    Fase 5 — Modul Perlengkapan (Rule #66). Widget SELF-CONTAINED (query
    inline di sini) — sengaja BUKAN <x-component> dengan data dari
    DashboardController seperti widget lain, supaya DashboardController
    TIDAK PERLU disentuh sama sekali. Dipanggil via @include, bukan tag
    komponen. Reuse qty_minimum existing (Rule #27) — bukan kolom baru.
--}}
@php
    $menipisPerlengkapan = \App\Models\Stock::whereHas('item', function ($q) {
            $q->where('jenis', 'perlengkapan')->where('track_stok', true);
        })
        ->whereColumn('qty', '<=', 'qty_minimum')
        ->where('qty_minimum', '>', 0)
        ->with(['item', 'lokasi'])
        ->orderBy('qty')
        ->take(10)
        ->get();
@endphp

@can('pemakaian_perlengkapan.view')
@if($menipisPerlengkapan->count() > 0)
<div class="card mt-3">
    <div class="card-header bg-warning-subtle d-flex align-items-center justify-content-between">
        <span>
            <i class="bi bi-exclamation-triangle me-1"></i>
            Perlengkapan Menipis ({{ $menipisPerlengkapan->count() }})
        </span>
        <a href="{{ route('pemakaian-perlengkapan.index') }}" class="btn btn-sm btn-outline-secondary">Lihat Semua</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Item</th>
                        <th>Cabang</th>
                        <th class="text-end pe-3">Sisa / Minimum</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($menipisPerlengkapan as $stok)
                    <tr>
                        <td class="ps-3">{{ $stok->item?->nama_item ?? '-' }}</td>
                        <td>{{ $stok->lokasi?->nama_cabang ?? '-' }}</td>
                        <td class="text-end pe-3 {{ $stok->qty <= 0 ? 'text-danger fw-semibold' : 'text-warning-emphasis' }}">
                            {{ number_format($stok->qty, 3) }} / {{ number_format($stok->qty_minimum, 3) }} {{ $stok->item?->satuan }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endcan
