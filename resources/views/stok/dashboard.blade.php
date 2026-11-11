@extends('layouts.app')

@section('title', 'Dashboard Stok')

@push('styles')
<style>
    .stat-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 1px 6px rgba(0,0,0,0.07);
        transition: box-shadow 0.15s;
    }
    .stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.10); }
    .stat-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .badge-alert { font-size: 0.7rem; }
    .item-row-expand td { border-bottom: none; }
    .batch-panel { background: #f8fafc; border-top: 1px solid #e2e8f0; }
    .tipe-bahan   { background:#fefce8; color:#854d0e; }
    .tipe-produk  { background:#f0fdf4; color:#166534; }
    .tipe-kemasan { background:#eff6ff; color:#1e40af; }
    .tipe-lain    { background:#f1f5f9; color:#475569; }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-graph-up me-2 text-primary"></i>Dashboard Stok
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('stok.index') }}" class="text-decoration-none">Stok</a></li>
                <li class="breadcrumb-item active">Dashboard Stok</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        {{-- Filter Cabang (Owner only) + Filter Tipe (semua role) --}}
        <form method="GET" action="{{ route('stok.dashboard') }}" class="d-flex gap-2 align-items-center flex-wrap">
            @if($cabangList->isNotEmpty())
            <select name="cabang_id" class="form-select form-select-sm" style="min-width:180px" onchange="this.form.submit()">
                <option value="">Semua Cabang</option>
                @foreach($cabangList as $cabang)
                <option value="{{ $cabang->id }}" {{ $cabangId == $cabang->id ? 'selected' : '' }}>
                    {{ $cabang->nama_cabang }}
                </option>
                @endforeach
            </select>
            @endif
            <select name="tipe" class="form-select form-select-sm" style="min-width:160px" onchange="this.form.submit()">
                <option value="">Semua Tipe</option>
                <option value="bahan_baku" {{ $tipe === 'bahan_baku' ? 'selected' : '' }}>Bahan Baku</option>
                <option value="kemasan" {{ $tipe === 'kemasan' ? 'selected' : '' }}>Kemasan</option>
                <option value="tambahan_gratis" {{ $tipe === 'tambahan_gratis' ? 'selected' : '' }}>Tambahan Gratis</option>
            </select>
            @if($cabangId || $tipe)
            <a href="{{ route('stok.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-lg"></i>
            </a>
            @endif
        </form>
        <x-panduan-button slug="dashboard-stok" />
    </div>
</div>

{{-- STAT CARDS --}}
<div class="row g-3 mb-4">
    {{-- Total Item --}}
    <div class="col-6 col-md-3">
        <div class="card stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:#eff6ff">
                    <i class="bi bi-boxes text-primary" style="font-size:1.3rem"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4 lh-1">{{ $stokItems->count() }}</div>
                    <div class="text-muted" style="font-size:0.75rem">Total Item</div>
                </div>
            </div>
        </div>
    </div>
    {{-- Stok Kritis --}}
    <div class="col-6 col-md-3">
        <div class="card stat-card p-3 {{ $statKritis > 0 ? 'border-danger border-2' : '' }}">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:#fef2f2">
                    <i class="bi bi-exclamation-triangle{{ $statKritis > 0 ? '-fill' : '' }} text-danger" style="font-size:1.3rem"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4 lh-1 {{ $statKritis > 0 ? 'text-danger' : '' }}">{{ $statKritis }}</div>
                    <div class="text-muted" style="font-size:0.75rem">Item Kritis / Habis</div>
                    @if($statMenipis > 0)
                    <div style="font-size:0.7rem" class="text-warning">+{{ $statMenipis }} menipis</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    {{-- Nilai Stok FIFO --}}
    <div class="col-12 col-md-6">
        <div class="card stat-card p-3 border-primary border-2">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:#eff6ff">
                    <i class="bi bi-currency-dollar text-primary" style="font-size:1.3rem"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold fs-5 lh-1 text-primary">
                        Rp {{ number_format($totalNilai, 0, ',', '.') }}
                    </div>
                    <div class="text-muted" style="font-size:0.75rem">Total Nilai Stok (FIFO)</div>
                    @if($nilaiStokPerCabang->count() > 1)
                    <div style="font-size:0.7rem" class="text-muted">
                        {{ $nilaiStokPerCabang->count() }} lokasi
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ALERT SECTION --}}
@if($habis->count() > 0 || $kritis->count() > 0)
<div class="card mb-4" style="border:2px solid #dc2626">
    <div class="card-header d-flex align-items-center gap-2 py-2 px-3" style="background:#fef2f2;border-bottom:1px solid #fecaca">
        <i class="bi bi-exclamation-triangle-fill text-danger"></i>
        <span class="fw-semibold text-danger" style="font-size:0.9rem">
            Stok Memerlukan Perhatian
        </span>
        <span class="badge bg-danger ms-auto">{{ $habis->count() + $kritis->count() }} item</span>
    </div>
    <div class="card-body py-3 px-3">
        @foreach($habis as $alertItem)
        <div class="d-flex align-items-center gap-2 py-2 border-bottom" style="font-size:0.85rem">
            <span class="badge bg-danger flex-shrink-0" style="font-size:0.68rem">HABIS</span>
            <span class="fw-semibold">{{ $alertItem->nama_item }}</span>
            @if(!empty($alertItem->nama_cabang))
            <span class="text-muted" style="font-size:0.78rem">— {{ $alertItem->nama_cabang }}</span>
            @endif
            <span class="ms-auto text-muted" style="font-size:0.78rem">min: {{ $alertItem->qty_minimum }} {{ $alertItem->satuan }}</span>
        </div>
        @endforeach
        @foreach($kritis as $alertItem)
        <div class="d-flex align-items-center gap-2 py-2 border-bottom" style="font-size:0.85rem">
            <span class="badge bg-warning text-dark flex-shrink-0" style="font-size:0.68rem">KRITIS</span>
            <span class="fw-semibold">{{ $alertItem->nama_item }}</span>
            @if(!empty($alertItem->nama_cabang))
            <span class="text-muted" style="font-size:0.78rem">— {{ $alertItem->nama_cabang }}</span>
            @endif
            <span class="ms-auto" style="font-size:0.78rem">
                <strong>{{ number_format($alertItem->qty, 0, ',', '.') }}</strong>
                <span class="text-muted">/ min {{ number_format($alertItem->qty_minimum, 0, ',', '.') }} {{ $alertItem->satuan }}</span>
            </span>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- NILAI STOK PER CABANG --}}
@if($nilaiStokPerCabang->count() > 0)
<div class="card mb-4">
    <div class="card-header py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-building text-primary"></i>
        <span class="fw-semibold" style="font-size:0.9rem">Nilai Stok per Cabang (FIFO)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="px-3">Cabang / Lokasi</th>
                    <th class="text-end px-3">Nilai Stok</th>
                    <th class="text-end px-3 d-none d-md-table-cell">% dari Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($nilaiStokPerCabang as $row)
                <tr>
                    <td class="px-3" style="font-size:0.88rem">
                        <i class="bi bi-shop me-1 text-muted"></i>{{ $row->nama_cabang }}
                    </td>
                    <td class="text-end px-3 fw-semibold" style="font-size:0.88rem">
                        Rp {{ number_format($row->nilai_stok, 0, ',', '.') }}
                    </td>
                    <td class="text-end px-3 d-none d-md-table-cell text-muted" style="font-size:0.82rem">
                        @if($totalNilai > 0)
                            {{ number_format(($row->nilai_stok / $totalNilai) * 100, 1) }}%
                            <div class="progress mt-1" style="height:4px">
                                <div class="progress-bar bg-primary"
                                     style="width:{{ ($row->nilai_stok / $totalNilai) * 100 }}%"></div>
                            </div>
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td class="px-3 fw-bold" style="font-size:0.88rem">TOTAL</td>
                    <td class="text-end px-3 fw-bold text-primary" style="font-size:0.88rem">
                        Rp {{ number_format($totalNilai, 0, ',', '.') }}
                    </td>
                    <td class="d-none d-md-table-cell"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

{{-- LIST ITEM DENGAN EXPAND BATCH --}}
<div class="card">
    <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="d-flex align-items-center gap-2">
            <i class="bi bi-list-ul text-primary"></i>
            <span class="fw-semibold" style="font-size:0.9rem">Daftar Stok Bahan</span>
        </span>
        <div class="d-flex gap-2 align-items-center">
            <input type="text" id="searchItem" class="form-control form-control-sm" placeholder="Cari item..." style="max-width:200px">
            <select id="filterAlert" class="form-select form-select-sm" style="max-width:130px">
                <option value="">Semua Status</option>
                <option value="habis">Habis</option>
                <option value="kritis">Kritis</option>
                <option value="menipis">Menipis</option>
                <option value="aman">Aman</option>
            </select>
        </div>
    </div>

    {{-- Desktop Table --}}
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle mb-0" id="itemTable">
            <thead class="table-light">
                <tr>
                    <th class="px-3" style="width:40px"></th>
                    <th class="px-3">Nama Item</th>
                    <th>Tipe</th>
                    <th class="text-end">Total Stok</th>
                    <th class="text-end d-none d-lg-table-cell">HPP Avg (FIFO)</th>
                    <th class="text-end">Nilai Stok</th>
                    <th class="text-center">Batch</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stokItems as $item)
                <tr class="item-row-expand" data-alert="{{ $item->alert }}" data-nama="{{ strtolower($item->nama_item) }}">
                    <td class="px-3">
                        <button class="btn btn-sm btn-link p-0 toggle-batch"
                                type="button"
                                data-item-id="{{ $item->id }}"
                                data-cabang-id="{{ $cabangId ?? '' }}"
                                aria-expanded="false"
                                title="Lihat Batch FIFO">
                            <i class="bi bi-chevron-right" style="font-size:0.8rem"></i>
                        </button>
                    </td>
                    <td class="px-3">
                        <div class="fw-semibold" style="font-size:0.88rem;color:#1e293b">{{ $item->nama_item }}</div>
                        <div style="font-size:0.73rem;color:#94a3b8">{{ $item->kode_item }}</div>
                    </td>
                    <td>
                        @php
                            $tipeCls = match($item->tipe) {
                                'bahan_baku'      => 'tipe-bahan',
                                'kemasan'         => 'tipe-kemasan',
                                'tambahan_gratis' => 'tipe-bahan',
                                default           => 'tipe-lain',
                            };
                            $tipeLabel = match($item->tipe) {
                                'bahan_baku'      => 'Bahan Baku',
                                'kemasan'         => 'Kemasan',
                                'tambahan_gratis' => 'Tambahan Gratis',
                                default           => 'Lainnya',
                            };
                        @endphp
                        <span class="badge {{ $tipeCls }}" style="font-size:0.7rem">{{ $tipeLabel }}</span>
                    </td>
                    <td class="text-end">
                        <span class="fw-semibold" style="font-size:0.88rem">
                            {{ number_format($item->total_qty, 2, ',', '.') }}
                        </span>
                        <span class="text-muted" style="font-size:0.75rem"> {{ $item->satuan }}</span>
                    </td>
                    <td class="text-end d-none d-lg-table-cell">
                        @if($item->hpp_avg > 0)
                        <span style="font-size:0.85rem">Rp {{ number_format($item->hpp_avg, 0, ',', '.') }}</span>
                        @else
                        <span class="text-muted" style="font-size:0.82rem">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($item->nilai_stok > 0)
                        <span class="fw-semibold" style="font-size:0.85rem">
                            Rp {{ number_format($item->nilai_stok, 0, ',', '.') }}
                        </span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem">
                            {{ $item->jumlah_batch }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if($item->alert === 'habis')
                            <span class="badge bg-danger badge-alert">HABIS</span>
                        @elseif($item->alert === 'kritis')
                            <span class="badge bg-warning text-dark badge-alert">KRITIS</span>
                        @elseif($item->alert === 'menipis')
                            <span class="badge bg-info text-dark badge-alert">MENIPIS</span>
                        @else
                            <span class="badge bg-success-subtle text-success badge-alert">AMAN</span>
                        @endif
                    </td>
                </tr>
                {{-- Batch expand row --}}
                <tr class="batch-expand-row d-none" id="batch-row-{{ $item->id }}">
                    <td colspan="8" class="p-0">
                        <div class="batch-panel px-4 py-3" id="batch-content-{{ $item->id }}">
                            <div class="text-muted" style="font-size:0.82rem">
                                <i class="bi bi-hourglass-split me-1"></i>Memuat data batch...
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
                        <p class="mt-2 mb-0">Tidak ada item stok ditemukan.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="d-md-none p-3">
        @forelse($stokItems as $item)
        <div class="card mb-2 border item-card-mobile"
             data-alert="{{ $item->alert }}"
             data-nama="{{ strtolower($item->nama_item) }}">
            <div class="card-body p-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <div class="fw-semibold" style="font-size:0.88rem">{{ $item->nama_item }}</div>
                        <div style="font-size:0.73rem;color:#94a3b8">{{ $item->kode_item }}</div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        @if($item->alert === 'habis')
                            <span class="badge bg-danger badge-alert">HABIS</span>
                        @elseif($item->alert === 'kritis')
                            <span class="badge bg-warning text-dark badge-alert">KRITIS</span>
                        @elseif($item->alert === 'menipis')
                            <span class="badge bg-info text-dark badge-alert">MENIPIS</span>
                        @else
                            <span class="badge bg-success-subtle text-success badge-alert">AMAN</span>
                        @endif
                    </div>
                </div>
                <div class="row g-2" style="font-size:0.82rem">
                    <div class="col-6">
                        <div class="text-muted">Total Stok</div>
                        <div class="fw-semibold">{{ number_format($item->total_qty, 2, ',', '.') }} {{ $item->satuan }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Nilai Stok (FIFO)</div>
                        <div class="fw-semibold">
                            @if($item->nilai_stok > 0)
                                Rp {{ number_format($item->nilai_stok, 0, ',', '.') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">HPP Avg</div>
                        <div>
                            @if($item->hpp_avg > 0)
                                Rp {{ number_format($item->hpp_avg, 0, ',', '.') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Aktif Batch</div>
                        <div>{{ $item->jumlah_batch }} batch</div>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-secondary mt-2 w-100 toggle-batch-mobile"
                        type="button"
                        data-item-id="{{ $item->id }}"
                        data-cabang-id="{{ $cabangId ?? '' }}">
                    <i class="bi bi-layers me-1"></i>Lihat Batch FIFO
                </button>
                <div class="batch-mobile-panel mt-2 d-none" id="batch-mobile-{{ $item->id }}">
                    <div class="text-muted" style="font-size:0.8rem">Memuat...</div>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
            <p class="mt-2">Tidak ada item stok.</p>
        </div>
        @endforelse
    </div>
</div>

{{-- ═══════════════ SMART INSIGHTS ═══════════════ --}}
<div class="mt-4 mb-2">
    <h6 class="fw-bold d-flex align-items-center gap-2" style="color:#1e293b">
        <i class="bi bi-lightbulb text-warning"></i>Smart Insights
        <span class="badge bg-warning-subtle text-warning fw-normal" style="font-size:0.7rem">Phase C</span>
    </h6>
</div>
<div class="row g-3">

    {{-- Widget 1: Aging Stok --}}
    <div class="col-12 col-md-6">
        <div class="card h-100" style="border:1.5px solid #f59e0b">
            <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
                 style="background:#fffbeb;border-bottom:1px solid #fde68a">
                <i class="bi bi-hourglass-split text-warning"></i>
                <span class="fw-semibold" style="font-size:0.88rem">Stok Lama (&gt;30 hari)</span>
                <span class="badge ms-auto {{ $agingStok->count() > 0 ? 'bg-warning text-dark' : 'bg-secondary-subtle text-secondary' }}"
                      style="font-size:0.7rem">{{ $agingStok->count() }}</span>
            </div>
            <div class="card-body p-3">
                @if($agingStok->isEmpty())
                    <div class="text-center py-3 text-muted" style="font-size:0.85rem">
                        <i class="bi bi-check-circle text-success" style="font-size:1.3rem"></i>
                        <p class="mb-0 mt-1">Tidak ada batch stok lama</p>
                    </div>
                @else
                    @foreach($agingStok as $batch)
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2 p-2 rounded"
                         style="{{ $batch->severity === 'danger' ? 'background:#fef2f2' : 'background:#fffbeb' }}">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate" style="font-size:0.82rem">{{ $batch->nama_item }}</div>
                            <div style="font-size:0.73rem;color:#94a3b8">
                                #{{ $batch->id }} &bull; {{ $batch->lokasi }} &bull;
                                <span class="{{ $batch->severity === 'danger' ? 'text-danger fw-semibold' : 'text-warning fw-semibold' }}">
                                    {{ $batch->umur_hari }} hari
                                </span>
                                &bull; {{ $batch->tanggal_masuk }}
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="fw-semibold" style="font-size:0.82rem">
                                {{ number_format($batch->qty_sisa, 2, ',', '.') }} {{ $batch->satuan }}
                            </div>
                            <div style="font-size:0.73rem;color:#94a3b8">
                                Rp {{ number_format($batch->nilai, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                    <div class="mt-2 pt-2 border-top" style="font-size:0.74rem;color:#64748b">
                        <i class="bi bi-lightbulb me-1 text-warning"></i>
                        Pertimbangkan: cek kualitas, promo, atau prioritas jual
                    </div>
                @endif
                <div class="text-center mt-2 pt-2 border-top">
                    <a href="{{ route('stok.aging', $cabangId ? ['cabang_id' => $cabangId] : []) }}"
                       class="btn btn-sm btn-warning px-3 py-1" style="font-size:0.78rem">
                        <i class="bi bi-list-ul me-1"></i>Lihat Semua
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Widget 2: Top Movement --}}
    <div class="col-12 col-md-6">
        <div class="card h-100" style="border:1.5px solid #22c55e">
            <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
                 style="background:#f0fdf4;border-bottom:1px solid #bbf7d0">
                <i class="bi bi-trophy text-success"></i>
                <span class="fw-semibold" style="font-size:0.88rem">Top 5 Paling Sering Dipakai</span>
                <span class="badge ms-auto bg-secondary-subtle text-secondary" style="font-size:0.68rem">90 hari terakhir</span>
            </div>
            <div class="card-body p-3">
                @if($topMovement->isEmpty())
                    <div class="text-center py-3 text-muted" style="font-size:0.85rem">
                        <i class="bi bi-bar-chart text-secondary" style="font-size:1.3rem"></i>
                        <p class="mb-0 mt-1">Tidak ada movement dalam 90 hari terakhir</p>
                    </div>
                @else
                    @php $maxQty = max($topMovement->max('total_keluar'), 1); @endphp
                    @foreach($topMovement as $idx => $item)
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span style="font-size:0.82rem">
                                <span class="fw-bold text-success me-1">#{{ $idx + 1 }}</span>
                                {{ $item->nama_item }}
                            </span>
                            <span style="font-size:0.75rem;color:#64748b">
                                {{ number_format($item->total_keluar, 2, ',', '.') }} {{ $item->satuan }}
                                <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:0.65rem">
                                    {{ $item->jumlah_transaksi }}x
                                </span>
                            </span>
                        </div>
                        <div class="progress" style="height:6px;border-radius:4px">
                            <div class="progress-bar bg-success" style="width:{{ ($item->total_keluar / $maxQty) * 100 }}%;border-radius:4px"></div>
                        </div>
                        @if($item->nilai > 0)
                        <div style="font-size:0.72rem;color:#94a3b8" class="mt-1">
                            Nilai: Rp {{ number_format($item->nilai, 0, ',', '.') }}
                        </div>
                        @endif
                    </div>
                    @endforeach
                @endif
                <div class="text-center mt-2 pt-2 border-top">
                    <a href="{{ route('stok.top-movement', $cabangId ? ['cabang_id' => $cabangId] : []) }}"
                       class="btn btn-sm btn-success px-3 py-1" style="font-size:0.78rem">
                        <i class="bi bi-list-ul me-1"></i>Lihat Semua
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Widget 3: Trend Harga --}}
    <div class="col-12 col-md-6">
        <div class="card h-100" style="border:1.5px solid #3b82f6">
            <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
                 style="background:#eff6ff;border-bottom:1px solid #bfdbfe">
                <i class="bi bi-graph-up-arrow text-primary"></i>
                <span class="fw-semibold" style="font-size:0.88rem">Trend Harga Beli</span>
                <span class="badge ms-auto bg-secondary-subtle text-secondary" style="font-size:0.68rem">30 hari terakhir</span>
            </div>
            <div class="card-body p-3">
                @if($trendHarga->isEmpty())
                    <div class="text-center py-3 text-muted" style="font-size:0.85rem">
                        <i class="bi bi-check-circle text-success" style="font-size:1.3rem"></i>
                        <p class="mb-0 mt-1">Harga stabil, tidak ada perubahan &gt;5%</p>
                    </div>
                @else
                    @foreach($trendHarga as $trend)
                    <div class="mb-2 p-2 rounded"
                         style="{{ $trend->severity === 'danger' ? 'background:#fef2f2' : 'background:#fffbeb' }}">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-semibold" style="font-size:0.82rem">{{ $trend->nama_item }}</span>
                            <span class="fw-bold {{ $trend->arah === 'naik' ? 'text-danger' : 'text-success' }}"
                                  style="font-size:0.85rem">
                                {{ $trend->arah === 'naik' ? '↑' : '↓' }}{{ number_format(abs($trend->persen), 1) }}%
                            </span>
                        </div>
                        <div style="font-size:0.73rem;color:#64748b" class="mt-1">
                            Rp {{ number_format($trend->harga_lama, 0, ',', '.') }}
                            <i class="bi bi-arrow-right mx-1"></i>
                            <span class="{{ $trend->arah === 'naik' ? 'text-danger' : 'text-success' }} fw-semibold">
                                Rp {{ number_format($trend->harga_baru, 0, ',', '.') }}
                            </span>
                            <span class="ms-1 text-muted">/ {{ $trend->satuan }}</span>
                        </div>
                    </div>
                    @endforeach
                    <div class="mt-2 pt-2 border-top" style="font-size:0.74rem;color:#64748b">
                        <i class="bi bi-lightbulb me-1 text-primary"></i>
                        Pertimbangkan adjustment harga jual produk
                    </div>
                @endif
                <div class="text-center mt-2 pt-2 border-top">
                    <a href="{{ route('stok.trend-harga', $cabangId ? ['cabang_id' => $cabangId] : []) }}"
                       class="btn btn-sm btn-primary px-3 py-1" style="font-size:0.78rem">
                        <i class="bi bi-list-ul me-1"></i>Lihat Semua
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Widget 4: Stok Mati --}}
    <div class="col-12 col-md-6">
        <div class="card h-100" style="border:1.5px solid #94a3b8">
            <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
                 style="background:#f8fafc;border-bottom:1px solid #e2e8f0">
                <i class="bi bi-moon-stars text-secondary"></i>
                <span class="fw-semibold" style="font-size:0.88rem">Stok Tidak Bergerak</span>
                <span class="badge ms-auto {{ $stokMati->count() > 0 ? 'bg-secondary' : 'bg-secondary-subtle text-secondary' }}"
                      style="font-size:0.7rem">
                    {{ $stokMati->count() > 0 ? $stokMati->count() . ' item' : '0' }}
                </span>
            </div>
            <div class="card-body p-3">
                @if($stokMati->isEmpty())
                    <div class="text-center py-3 text-muted" style="font-size:0.85rem">
                        <i class="bi bi-check-circle text-success" style="font-size:1.3rem"></i>
                        <p class="mb-0 mt-1">Semua stok bergerak baik</p>
                    </div>
                @else
                    @foreach($stokMati as $item)
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2 pb-2 border-bottom">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate" style="font-size:0.82rem">{{ $item->nama_item }}</div>
                            <div style="font-size:0.73rem;color:#94a3b8">
                                @if($item->pernah_keluar)
                                    <i class="bi bi-clock me-1"></i>{{ $item->hari_sejak_keluar }} hari tanpa transaksi
                                @else
                                    <span class="badge bg-info-subtle text-info" style="font-size:0.65rem">Belum pernah dipakai</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="fw-semibold" style="font-size:0.82rem">
                                {{ number_format($item->qty, 2, ',', '.') }} {{ $item->satuan }}
                            </div>
                            @if($item->nilai > 0)
                            <div style="font-size:0.73rem;color:#94a3b8">
                                Rp {{ number_format($item->nilai, 0, ',', '.') }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                    <div class="mt-1 pt-1" style="font-size:0.74rem;color:#64748b">
                        <i class="bi bi-lightbulb me-1 text-secondary"></i>
                        Review: promo, diskon, atau investigasi penyebab tidak bergerak
                    </div>
                @endif
                <div class="text-center mt-2 pt-2 border-top">
                    <a href="{{ route('stok.stok-mati', $cabangId ? ['cabang_id' => $cabangId] : []) }}"
                       class="btn btn-sm btn-secondary px-3 py-1" style="font-size:0.78rem">
                        <i class="bi bi-list-ul me-1"></i>Lihat Semua
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
{{-- ═══════════════ END SMART INSIGHTS ═══════════════ --}}

@push('scripts')
<script>
// ── Filter & Search ──────────────────────────────────────────────
document.getElementById('searchItem')?.addEventListener('input', function () {
    filterTable();
});
document.getElementById('filterAlert')?.addEventListener('change', function () {
    filterTable();
});

function filterTable() {
    const search = document.getElementById('searchItem').value.toLowerCase();
    const alertFilter = document.getElementById('filterAlert').value;

    // Desktop
    document.querySelectorAll('#itemTable tbody tr.item-row-expand').forEach(row => {
        const matchNama  = (row.dataset.nama || '').includes(search);
        const matchAlert = !alertFilter || row.dataset.alert === alertFilter;
        const show = matchNama && matchAlert;

        row.classList.toggle('d-none', !show);

        // Saat item disembunyikan, sembunyikan juga batch expand row-nya.
        // Saat item ditampilkan kembali, biarkan batch row dalam state sebelumnya.
        const id = row.querySelector('.toggle-batch')?.dataset.itemId;
        if (id && !show) {
            const expandRow = document.getElementById('batch-row-' + id);
            if (expandRow) expandRow.classList.add('d-none');
        }
    });

    // Mobile
    document.querySelectorAll('.item-card-mobile').forEach(card => {
        const matchNama  = (card.dataset.nama || '').includes(search);
        const matchAlert = !alertFilter || card.dataset.alert === alertFilter;
        card.classList.toggle('d-none', !(matchNama && matchAlert));
    });
}

// ── Expand Batch (Desktop) ───────────────────────────────────────
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.toggle-batch');
    if (!btn) return;

    const itemId   = btn.dataset.itemId;
    const cabangId = btn.dataset.cabangId;
    const icon     = btn.querySelector('i');
    const batchRow = document.getElementById('batch-row-' + itemId);
    const content  = document.getElementById('batch-content-' + itemId);

    const isOpen = !batchRow.classList.contains('d-none');

    if (isOpen) {
        batchRow.classList.add('d-none');
        icon.className = 'bi bi-chevron-right';
        btn.setAttribute('aria-expanded', 'false');
    } else {
        batchRow.classList.remove('d-none');
        icon.className = 'bi bi-chevron-down';
        btn.setAttribute('aria-expanded', 'true');

        if (content.dataset.loaded !== '1') {
            loadBatchData(itemId, cabangId, content, false);
        }
    }
});

// ── Expand Batch (Mobile) ────────────────────────────────────────
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.toggle-batch-mobile');
    if (!btn) return;

    const itemId   = btn.dataset.itemId;
    const cabangId = btn.dataset.cabangId;
    const panel    = document.getElementById('batch-mobile-' + itemId);

    const isOpen = !panel.classList.contains('d-none');

    if (isOpen) {
        panel.classList.add('d-none');
        btn.innerHTML = '<i class="bi bi-layers me-1"></i>Lihat Batch FIFO';
    } else {
        panel.classList.remove('d-none');
        btn.innerHTML = '<i class="bi bi-layers-half me-1"></i>Sembunyikan Batch';

        if (panel.dataset.loaded !== '1') {
            loadBatchData(itemId, cabangId, panel, true);
        }
    }
});

// ── Fetch & Render Batch ─────────────────────────────────────────
function loadBatchData(itemId, cabangId, container, isMobile) {
    let url = `/stok/dashboard/item/${itemId}/batches`;
    if (cabangId) url += `?cabang_id=${cabangId}`;

    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        container.dataset.loaded = '1';
        if (!data.batches || data.batches.length === 0) {
            container.innerHTML = '<em class="text-muted" style="font-size:0.82rem">Tidak ada batch aktif untuk item ini.</em>';
            return;
        }

        if (isMobile) {
            let html = '';
            data.batches.forEach(b => {
                const nilai = parseFloat(b.nilai);
                html += `
                <div class="border rounded p-2 mb-2 bg-white" style="font-size:0.78rem">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Batch #${b.id}</span>
                        <span class="text-muted">${b.umur_hari} hari lalu</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Masuk: <strong>${b.tanggal_masuk}</strong></span>
                        <span>${b.lokasi || '—'}</span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span>Sisa: <strong>${parseFloat(b.qty_sisa).toLocaleString('id-ID', {minimumFractionDigits:2})}</strong> / awal ${parseFloat(b.qty_awal).toLocaleString('id-ID', {minimumFractionDigits:2})}</span>
                        <span>Rp ${parseFloat(b.harga_beli_per_unit).toLocaleString('id-ID')} / unit</span>
                    </div>
                    <div class="text-end mt-1 fw-semibold">Nilai: Rp ${nilai.toLocaleString('id-ID')}</div>
                </div>`;
            });
            container.innerHTML = html;
        } else {
            let html = `
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-stack text-primary"></i>
                <span class="fw-semibold text-primary" style="font-size:0.82rem">FIFO Batch Detail</span>
                <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:0.68rem">${data.batches.length} batch</span>
                <span class="text-muted ms-2" style="font-size:0.75rem">Urutan konsumsi: tertua → terbaru</span>
            </div>
            <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem">
                <thead class="table-primary">
                    <tr>
                        <th>Batch</th>
                        <th>Tanggal Masuk</th>
                        <th>Umur</th>
                        <th class="text-end">Qty Awal</th>
                        <th class="text-end">Qty Sisa</th>
                        <th class="text-end">Harga/Unit</th>
                        <th class="text-end">Nilai</th>
                        <th>Lokasi</th>
                        <th>Referensi</th>
                    </tr>
                </thead>
                <tbody>`;

            data.batches.forEach((b, i) => {
                const isOldest = i === 0;
                const nilai = parseFloat(b.nilai);
                const persen = parseFloat(b.qty_sisa) > 0 && parseFloat(b.qty_awal) > 0
                    ? ((parseFloat(b.qty_sisa) / parseFloat(b.qty_awal)) * 100).toFixed(0)
                    : 0;

                html += `<tr class="${isOldest ? 'table-warning' : ''}">
                    <td><span class="badge bg-secondary" style="font-size:0.65rem">#${b.id}</span>
                        ${isOldest ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.6rem">↑ NEXT</span>' : ''}</td>
                    <td>${b.tanggal_masuk}</td>
                    <td><span class="text-muted">${b.umur_hari}h</span></td>
                    <td class="text-end">${parseFloat(b.qty_awal).toLocaleString('id-ID', {minimumFractionDigits:2})}</td>
                    <td class="text-end fw-semibold">${parseFloat(b.qty_sisa).toLocaleString('id-ID', {minimumFractionDigits:2})}
                        <div class="progress mt-1" style="height:3px">
                            <div class="progress-bar bg-success" style="width:${persen}%"></div>
                        </div>
                    </td>
                    <td class="text-end">Rp ${parseFloat(b.harga_beli_per_unit).toLocaleString('id-ID')}</td>
                    <td class="text-end fw-semibold">Rp ${nilai.toLocaleString('id-ID')}</td>
                    <td>${b.lokasi || '—'}</td>
                    <td><span class="text-muted" style="font-size:0.72rem">${b.referensi_type || '—'}</span></td>
                </tr>`;
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        }
    })
    .catch(() => {
        container.innerHTML = '<span class="text-danger" style="font-size:0.82rem"><i class="bi bi-x-circle me-1"></i>Gagal memuat data batch.</span>';
    });
}
</script>
@endpush

@endsection
