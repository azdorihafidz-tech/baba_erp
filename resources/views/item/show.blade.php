@extends('layouts.app')

@section('title', 'Detail Barang — ' . $item->nama_item)

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0">
            <i class="bi bi-card-list me-2 text-primary"></i>{{ $item->nama_item }}
        </h5>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('item.index') }}" class="text-decoration-none">Master Barang</a></li>
                <li class="breadcrumb-item active">{{ $item->kode_item }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('item.edit')
        <a href="{{ route('item.edit', $item) }}" class="btn btn-warning btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endcan
        <a href="{{ route('item.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="row g-3">

    {{-- INFO UTAMA --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-info-circle me-1"></i>Informasi Barang
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="ps-3" style="width:40%">Kode</th>
                            <td><code>{{ $item->kode_item }}</code></td>
                        </tr>
                        <tr>
                            <th class="ps-3">Nama</th>
                            <td class="fw-semibold">{{ $item->nama_item }}</td>
                        </tr>
                        <tr>
                            <th class="ps-3">Kategori</th>
                            <td>{{ $item->category?->nama_kategori ?? '<span class="text-muted">—</span>' }}</td>
                        </tr>
                        <tr>
                            <th class="ps-3">Tipe</th>
                            <td>
                                @php
                                    $tipeLabel = [
                                        'bahan_baku'  => ['Bahan Baku', 'secondary'],
                                        'produk_jadi' => ['Produk Jadi', 'primary'],
                                        'kemasan'     => ['Kemasan', 'info'],
                                        'lainnya'     => ['Lainnya', 'light'],
                                    ];
                                    [$lbl, $cls] = $tipeLabel[$item->tipe] ?? [$item->tipe, 'secondary'];
                                @endphp
                                <span class="badge bg-{{ $cls }} text-{{ $cls === 'light' ? 'dark' : 'white' }}">{{ $lbl }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th class="ps-3">Jenis</th>
                            <td>
                                <span class="badge {{ $item->jenis?->value === 'perlengkapan' ? 'bg-info' : 'bg-secondary' }}">
                                    {{ $item->jenis?->label() ?? 'Bahan Baku' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th class="ps-3">Lacak Stok</th>
                            <td>
                                @if($item->track_stok)
                                    <span class="badge bg-success">Ya</span>
                                @else
                                    <span class="badge bg-secondary">Tidak</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="ps-3">Satuan</th>
                            <td>{{ $item->satuan }}</td>
                        </tr>
                        <tr>
                            <th class="ps-3">Harga Jual</th>
                            <td>Rp {{ number_format($item->harga_jual ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <th class="ps-3">Harga Standar</th>
                            <td>Rp {{ number_format($item->harga_beli_terakhir ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <th class="ps-3">Harga PO Terakhir</th>
                            <td>@if($item->harga_po_terakhir !== null)Rp {{ number_format((float) $item->harga_po_terakhir, 0, ',', '.') }} <small class="text-muted">({{ $item->harga_po_terakhir_at?->format('d/m/Y') }})</small>@else <span class="text-muted">—</span> @endif</td>
                        </tr>
                        <tr>
                            <th class="ps-3">Stok Min (Global)</th>
                            <td>{{ number_format($item->qty_minimum, 3) }} {{ $item->satuan }}</td>
                        </tr>
                        <tr>
                            <th class="ps-3">Status</th>
                            <td>
                                @if($item->is_active)
                                    <span class="badge bg-success">Aktif</span>
                                @else
                                    <span class="badge bg-secondary">Nonaktif</span>
                                @endif
                            </td>
                        </tr>
                        @if($item->deskripsi)
                        <tr>
                            <th class="ps-3">Deskripsi</th>
                            <td>{{ $item->deskripsi }}</td>
                        </tr>
                        @endif
                        <tr>
                            <th class="ps-3">Dibuat</th>
                            <td class="text-muted small">{{ $item->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- STOK PER LOKASI --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-geo-alt me-1"></i>Stok per Lokasi
            </div>
            <div class="card-body p-0">
                @if($item->stocks->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">Belum ada data stok.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Lokasi</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end pe-3">Min</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($item->stocks as $stok)
                            <tr class="{{ $stok->qty <= $stok->qty_minimum && $stok->qty_minimum > 0 ? 'table-warning' : '' }}">
                                <td class="ps-3">{{ $stok->lokasi?->nama_cabang ?? '—' }}</td>
                                <td class="text-end fw-semibold {{ $stok->qty <= 0 ? 'text-danger' : '' }}">
                                    {{ number_format($stok->qty, 3) }} {{ $item->satuan }}
                                </td>
                                <td class="text-end pe-3 text-muted small">
                                    {{ number_format($stok->qty_minimum, 3) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- RIWAYAT PERGERAKAN --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-clock-history me-1"></i>50 Pergerakan Stok Terakhir</span>
                <a href="{{ route('stok.kartu') }}?item_id={{ $item->id }}" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-journal-text me-1"></i>Kartu Stok Lengkap
                </a>
            </div>
            <div class="card-body p-0">
                @if($recentMovements->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">Belum ada pergerakan stok.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Waktu</th>
                                <th>Tipe</th>
                                <th class="text-end">Qty</th>
                                <th>Asal → Tujuan</th>
                                <th>Referensi</th>
                                <th class="d-none d-md-table-cell">User</th>
                                <th class="d-none d-lg-table-cell pe-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentMovements as $mv)
                            @php
                                $tipeColor = [
                                    'masuk'      => 'success',
                                    'keluar'     => 'danger',
                                    'transfer'   => 'info',
                                    'adjustment' => 'warning',
                                ];
                                $tipeValue = $mv->tipe instanceof \App\Enums\TipeStockMovement
                                    ? $mv->tipe->value
                                    : (string) $mv->tipe;
                                $color = $tipeColor[$tipeValue] ?? 'secondary';
                            @endphp
                            <tr>
                                <td class="ps-3 text-nowrap small">
                                    {{ $mv->created_at ? \Carbon\Carbon::parse($mv->created_at)->format('d/m/Y H:i') : '—' }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ $color }}">{{ strtoupper($tipeValue) }}</span>
                                </td>
                                <td class="text-end fw-semibold">
                                    {{ number_format($mv->qty, 3) }}
                                </td>
                                <td class="small">
                                    <span class="text-muted">{{ $mv->lokasiAsal?->nama_cabang ?? '—' }}</span>
                                    @if($mv->lokasiTujuan)
                                        <i class="bi bi-arrow-right text-muted mx-1"></i>
                                        {{ $mv->lokasiTujuan->nama_cabang }}
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    {{ $mv->referensi_type ?? '—' }}
                                    @if($mv->referensi_id) #{{ $mv->referensi_id }} @endif
                                </td>
                                <td class="d-none d-md-table-cell small text-muted">
                                    {{ $mv->user?->name ?? '—' }}
                                </td>
                                <td class="d-none d-lg-table-cell pe-3 small text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    {{ $mv->catatan ?? '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
