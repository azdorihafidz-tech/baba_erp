@extends('layouts.app')

@section('title', 'Kas & Keuangan')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.3rem">Kas & Keuangan</h2>
        <p class="text-muted mb-0" style="font-size:0.85rem">
            Kelola transaksi keuangan harian
            @if($isSemua)
                — <span class="badge bg-info text-dark">Semua Cabang</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        @can('keuangan.kas_view')
        <a href="{{ route('keuangan.kas') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-wallet2 me-1"></i><span class="d-none d-sm-inline">Kelola Kas</span>
        </a>
        @endcan
        @can('keuangan.view')
        <a href="{{ route('keuangan.export', request()->query()) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </a>
        @endcan
        @can('laporan.keuangan.view')
        <a href="{{ route('keuangan.laporan') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i><span class="d-none d-sm-inline">Laporan</span>
        </a>
        @endcan
        @can('keuangan.create')
        <a href="{{ route('keuangan.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Tambah Transaksi</span>
        </a>
        @endcan
        <x-panduan-button slug="kas-transaksi" />
        @canany(['transaksi.link_po.action', 'transaksi.assign_kas.action', 'kas.sinkron_saldo.action'])
        <x-panduan-button slug="cleanup-tool" />
        @endcanany
    </div>
</div>

{{-- Date Range Filter --}}
<x-date-range-filter
    action="{{ route('keuangan.index') }}"
    :dari="$dari->toDateString()"
    :sampai="$sampai->toDateString()"
    session-key="keuangan">
    {{-- Extra filters --}}
    <div class="row g-2 mb-2">
        <x-search-box placeholder="Nomor / keterangan / kas / kategori..." col="col-12 col-md-4" label="Cari" />
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Tipe</label>
            <select name="tipe" class="form-select form-select-sm">
                <option value="">Semua Tipe</option>
                @foreach($tipes as $tipe)
                    <option value="{{ $tipe->value }}" {{ request('tipe') === $tipe->value ? 'selected' : '' }}>
                        {{ $tipe->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Kategori</label>
            <select name="kategori_id" class="form-select form-select-sm">
                <option value="">Semua Kategori</option>
                @foreach($kategoris as $kat)
                    <option value="{{ $kat->id }}" {{ request('kategori_id') == $kat->id ? 'selected' : '' }}>
                        {{ $kat->nama }}
                    </option>
                @endforeach
            </select>
        </div>
        @if($cabangs->count())
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Cabang</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $cabang)
                    <option value="{{ $cabang->id }}" {{ $filterCabangId == $cabang->id ? 'selected' : '' }}>
                        {{ $cabang->nama_cabang }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
    </div>
</x-date-range-filter>

{{-- Stats Cards --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-arrow-down-circle"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:0.78rem">Total Pemasukan</div>
                    <div class="fw-bold" style="font-size:1.15rem">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</div>
                    <div class="text-muted" style="font-size:0.75rem">
                        {{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-arrow-up-circle"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:0.78rem">Total Pengeluaran</div>
                    <div class="fw-bold" style="font-size:1.15rem">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</div>
                    <div class="text-muted" style="font-size:0.75rem">
                        {{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon {{ $saldo >= 0 ? 'bg-primary' : 'bg-danger' }} bg-opacity-10 {{ $saldo >= 0 ? 'text-primary' : 'text-danger' }}">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:0.78rem">Selisih (Saldo)</div>
                    <div class="fw-bold {{ $saldo >= 0 ? 'text-primary' : 'text-danger' }}" style="font-size:1.15rem">
                        {{ $saldo < 0 ? '-' : '' }}Rp {{ number_format(abs($saldo), 0, ',', '.') }}
                        @if($saldo < 0)<small class="fs-6">(Defisit)</small>@endif
                    </div>
                    <div class="text-muted" style="font-size:0.75rem">Pemasukan - Pengeluaran</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Rincian Saldo per Kas — TAMBAHAN murni, tidak mengubah 3 kartu di atas --}}
<div class="card mb-4">
    <div class="card-header py-2">
        <span class="fw-semibold"><i class="bi bi-wallet2 me-1"></i>Rincian Saldo per Kas</span>
        @php
            $cabangAktifNama = $filterCabangId ? $cabangs->firstWhere('id', $filterCabangId)?->nama_cabang : null;
        @endphp
        @if($cabangAktifNama)
            <small class="text-muted ms-1">({{ $cabangAktifNama }})</small>
        @endif
    </div>
    <div class="card-body">
        @if($kasBreakdown->isEmpty())
        <div class="text-muted text-center py-3">
            <i class="bi bi-inbox me-1"></i>Tidak ada kas aktif di cabang ini
        </div>
        @else
        <div class="row g-3">
            @foreach($kasBreakdown as $kas)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="border rounded p-2 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <i class="bi {{ $kas->tipe_kas === 'tunai' ? 'bi-cash-stack text-success' : 'bi-bank text-primary' }} me-1"></i>
                            <strong style="font-size:0.9rem">{{ $kas->nama_kas }}</strong>
                        </div>
                        <div class="text-end fw-bold {{ $kas->saldo_sekarang >= 0 ? 'text-primary' : 'text-danger' }}" style="font-size:0.9rem; white-space:nowrap">
                            {{ $kas->saldo_sekarang < 0 ? '-' : '' }}Rp {{ number_format(abs($kas->saldo_sekarang), 0, ',', '.') }}
                        </div>
                    </div>
                    <small class="text-muted">
                        {{ $totalSemuaKas > 0 ? number_format(((float) $kas->saldo_sekarang / $totalSemuaKas) * 100, 1) : 0 }}% dari total
                    </small>
                </div>
            </div>
            @endforeach
        </div>

        <hr>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
            <strong>Total Semua Kas:</strong>
            <strong class="{{ $totalSemuaKas >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $totalSemuaKas < 0 ? '-' : '' }}Rp {{ number_format(abs($totalSemuaKas), 0, ',', '.') }}
            </strong>
        </div>
        <small class="text-muted d-block mt-1">
            <i class="bi bi-info-circle me-1"></i>Saldo real-time saat ini — tidak terikat filter tanggal di atas, beda dari kartu "Selisih (Saldo)" yang menghitung arus kas periode terpilih.
        </small>
        @endif
    </div>
</div>

{{-- Ringkasan Per Cabang (hanya mode Semua Cabang) --}}
@if($isSemua && $ringkasanCabang->count() > 0)
<div class="card mb-4 border-info border-opacity-25">
    <div class="card-header bg-info bg-opacity-10 py-2">
        <span class="fw-semibold text-info"><i class="bi bi-building me-1"></i>Ringkasan Per Cabang</span>
        <small class="text-muted ms-2">{{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}</small>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Cabang</th>
                    <th class="text-end text-success">Pemasukan</th>
                    <th class="text-end text-danger">Pengeluaran</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ringkasanCabang->sortByDesc('saldo') as $rc)
                <tr>
                    <td>
                        <a href="{{ route('keuangan.index', ['cabang_id' => $rc->cabang_id, 'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]) }}"
                           class="text-decoration-none fw-semibold">
                            {{ $rc->cabang?->nama_cabang ?? 'Cabang #' . $rc->cabang_id }}
                        </a>
                    </td>
                    <td class="text-end text-success">Rp {{ number_format($rc->total_masuk, 0, ',', '.') }}</td>
                    <td class="text-end text-danger">Rp {{ number_format($rc->total_keluar, 0, ',', '.') }}</td>
                    <td class="text-end fw-semibold {{ $rc->saldo >= 0 ? 'text-primary' : 'text-danger' }}">
                        {{ $rc->saldo < 0 ? '-' : '' }}Rp {{ number_format(abs($rc->saldo), 0, ',', '.') }}
                    </td>
                </tr>
                @endforeach
                <tr class="table-secondary fw-bold">
                    <td>Total</td>
                    <td class="text-end text-success">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</td>
                    <td class="text-end text-danger">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
                    <td class="text-end {{ $saldo >= 0 ? 'text-primary' : 'text-danger' }}">
                        {{ $saldo < 0 ? '-' : '' }}Rp {{ number_format(abs($saldo), 0, ',', '.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Cleanup Tool B2: Transaksi Tanpa Kas Sumber (Owner-only) --}}
@can('transaksi.assign_kas.action')
@if($tanpaKasSumber->count() > 0)
<div class="card mb-4 border-warning border-opacity-50">
    <div class="card-header bg-warning bg-opacity-10 py-2">
        <span class="fw-semibold text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Transaksi Tanpa Kas Sumber</span>
        <small class="text-muted ms-2">{{ $tanpaKasSumber->count() }} transaksi tidak mengurangi/menambah saldo kas manapun</small>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Nomor</th>
                    <th>Keterangan</th>
                    <th>Cabang</th>
                    <th>Tipe</th>
                    <th class="text-end">Jumlah</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tanpaKasSumber as $trx)
                <tr>
                    <td>{{ $trx->tanggal_transaksi->format('d/m/Y') }}</td>
                    <td><code style="font-size:0.78rem">{{ $trx->nomor_transaksi }}</code></td>
                    <td>{{ $trx->keterangan }}</td>
                    <td><small class="badge bg-light text-dark border">{{ $trx->cabang?->nama_cabang ?? '-' }}</small></td>
                    <td>
                        @if($trx->tipe === \App\Enums\TipeTransaksiKeuangan::Pemasukan)
                            <span class="badge bg-success">Pemasukan</span>
                        @else
                            <span class="badge bg-danger">Pengeluaran</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">Rp {{ number_format($trx->jumlah, 0, ',', '.') }}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalAssignKas{{ $trx->id }}">
                            <i class="bi bi-wallet2 me-1"></i>Assign Kas
                        </button>
                    </td>
                </tr>

                {{-- Modal Assign Kas per baris --}}
                <div class="modal fade" id="modalAssignKas{{ $trx->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="POST" action="{{ route('keuangan.assign-kas', $trx) }}">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title fw-bold">Assign Kas — {{ $trx->nomor_transaksi }}</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="small text-muted mb-2">
                                        {{ $trx->keterangan }} — Rp {{ number_format($trx->jumlah, 0, ',', '.') }}
                                        ({{ $trx->tipe === \App\Enums\TipeTransaksiKeuangan::Pemasukan ? 'Pemasukan' : 'Pengeluaran' }})
                                    </p>
                                    <label class="form-label fw-semibold">Kas Sumber <span class="text-danger">*</span></label>
                                    <select name="kas_id" class="form-select" required>
                                        <option value="">-- Pilih Kas --</option>
                                        @foreach($kasPerCabang->get($trx->cabang_id, collect()) as $kas)
                                        <option value="{{ $kas->id }}">{{ $kas->nama_kas }} (Saldo: Rp {{ number_format($kas->saldo_sekarang, 0, ',', '.') }})</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">
                                        Saldo kas yang dipilih akan otomatis
                                        {{ $trx->tipe === \App\Enums\TipeTransaksiKeuangan::Pemasukan ? 'bertambah' : 'berkurang' }}
                                        Rp {{ number_format($trx->jumlah, 0, ',', '.') }}.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-warning">Simpan</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endcan

{{-- Tabel Desktop --}}
<div class="card d-none d-md-block">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Transaksi</span>
        <small class="text-muted">{{ $transaksis->total() }} transaksi</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th width="40">#</th>
                    <th>Tanggal</th>
                    <th>
                        Jam
                        <i class="bi bi-info-circle text-muted" style="font-size:0.7rem" title="Jam entri dicatat di sistem, BUKAN selalu jam kejadian bisnis — banyak transaksi diinput belakangan (beda tanggal dari Tanggal Transaksi) atau auto-generated (mis. Depresiasi Aset)."></i>
                    </th>
                    <th>Nomor</th>
                    <th>Keterangan</th>
                    <th class="d-none d-lg-table-cell">Kategori</th>
                    <th class="d-none d-lg-table-cell">Kas</th>
                    @if($isSemua)<th>Cabang</th>@endif
                    <th>Tipe</th>
                    <th class="text-end">Jumlah</th>
                    <th class="text-center d-none d-lg-table-cell" width="50">Bukti</th>
                    <th width="80" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transaksis as $i => $trx)
                <tr>
                    <td class="text-muted">{{ $transaksis->firstItem() + $i }}</td>
                    <td>{{ $trx->tanggal_transaksi->format('d/m/Y') }}</td>
                    <td>
                        <span class="text-muted small" title="Jam entri dicatat, bukan jam kejadian bisnis">{{ $trx->created_at?->format('H:i') ?? '-' }}</span>
                        @if($trx->created_at && $trx->created_at->toDateString() !== $trx->tanggal_transaksi->toDateString())
                        <i class="bi bi-clock-history text-warning" style="font-size:0.68rem" title="Dicatat belakangan — beda tanggal dari Tanggal Transaksi ({{ $trx->tanggal_transaksi->format('d/m/Y') }})"></i>
                        @endif
                    </td>
                    <td><code style="font-size:0.78rem">{{ $trx->nomor_transaksi }}</code></td>
                    <td>
                        <div>{{ $trx->keterangan }}</div>
                        @if($trx->catatan)
                            <small class="text-muted">{{ Str::limit($trx->catatan, 50) }}</small>
                        @endif
                    </td>
                    <td class="d-none d-lg-table-cell">
                        <span class="badge bg-secondary bg-opacity-15 text-dark" style="font-size:0.75rem">
                            {{ $trx->kategoriDinamis?->nama ?? $trx->kategori?->label() ?? '-' }}
                        </span>
                    </td>
                    <td class="d-none d-lg-table-cell">
                        @if($trx->kas)
                            <small>{{ $trx->kas->nama_kas }}</small>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    @if($isSemua)
                    <td>
                        <small class="badge bg-light text-dark border">{{ $trx->cabang?->nama_cabang ?? '-' }}</small>
                    </td>
                    @endif
                    <td>
                        @if($trx->tipe === \App\Enums\TipeTransaksiKeuangan::Pemasukan)
                            <span class="badge bg-success">Pemasukan</span>
                        @else
                            <span class="badge bg-danger">Pengeluaran</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold {{ $trx->tipe === \App\Enums\TipeTransaksiKeuangan::Pemasukan ? 'text-success' : 'text-danger' }}">
                        {{ $trx->tipe === \App\Enums\TipeTransaksiKeuangan::Pemasukan ? '+' : '-' }}Rp {{ number_format($trx->jumlah, 0, ',', '.') }}
                    </td>
                    <td class="text-center d-none d-lg-table-cell">
                        @if($trx->bukti_path)
                        <a href="/img/{{ $trx->bukti_path }}" target="_blank" class="btn btn-xs btn-outline-info py-0 px-1" title="Lihat Bukti" style="font-size:0.72rem">
                            <i class="bi bi-paperclip"></i>
                        </a>
                        @else
                        <span class="text-muted" style="font-size:0.72rem">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($trx->referensi_type === 'order')
                            <a href="{{ route('penjualan.show', $trx->referensi_id) }}"
                               class="btn btn-xs btn-outline-info py-0 px-1"
                               title="Auto-generated dari Order. Edit/hapus dari halaman Order."
                               style="font-size:0.72rem">
                                <i class="bi bi-receipt"></i> Order
                            </a>
                        @elseif($trx->referensi_type === 'purchase_order')
                            <a href="{{ route('pembelian.show', $trx->referensi_id) }}"
                               class="btn btn-xs btn-outline-secondary py-0 px-1"
                               title="Auto-generated dari Purchase Order. Edit/hapus dari halaman PO."
                               style="font-size:0.72rem">
                                <i class="bi bi-bag-plus"></i> PO
                            </a>
                        @elseif($trx->referensi_type === 'stock_movement' || str_contains($trx->referensi_type ?? '', 'StockMovement'))
                            <a href="{{ route('stok.index') }}"
                               class="btn btn-xs btn-outline-warning py-0 px-1"
                               title="Auto-generated dari Adjustment Stok."
                               style="font-size:0.72rem">
                                <i class="bi bi-sliders"></i> Adj.Stok
                            </a>
                        @elseif($trx->referensi_type)
                            <span class="text-muted" style="font-size:0.72rem"
                                  title="Dari {{ $trx->referensi_type }}. Edit dari sumber aslinya.">
                                <i class="bi bi-link-45deg"></i> {{ Str::title(str_replace('_', ' ', $trx->referensi_type)) }}
                            </span>
                        @else
                            @can('keuangan.edit')
                            <a href="{{ route('keuangan.edit', $trx) }}"
                               class="btn btn-xs btn-outline-warning py-0 px-1 me-1" title="Edit transaksi manual" style="font-size:0.72rem">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                            @can('keuangan.delete')
                            <button type="button"
                                onclick="confirmHapusTransaksi({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}')"
                                class="btn btn-xs btn-outline-danger py-0 px-1" title="Hapus transaksi manual" style="font-size:0.72rem">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isSemua ? 12 : 11 }}" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>Belum ada transaksi untuk periode ini
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transaksis->hasPages())
    <div class="card-footer">
        {{ $transaksis->links() }}
    </div>
    @endif
</div>

{{-- Card View Mobile --}}
<div class="d-md-none">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <small class="text-muted">{{ $transaksis->total() }} transaksi</small>
    </div>
    @forelse($transaksis as $trx)
    <div class="card mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1 me-2">
                    <div class="fw-semibold" style="font-size:0.9rem">{{ $trx->keterangan }}</div>
                    <div class="text-muted" style="font-size:0.78rem">
                        {{ $trx->tanggal_transaksi->format('d M Y') }}
                        &bull; <span title="Jam entri dicatat, bukan jam kejadian bisnis">{{ $trx->created_at?->format('H:i') ?? '-' }}</span>
                        &bull; {{ $trx->kategoriDinamis?->nama ?? $trx->kategori?->label() ?? '-' }}
                        @if($trx->kas) &bull; {{ $trx->kas->nama_kas }} @endif
                        @if($isSemua && $trx->cabang) &bull; <span class="badge bg-light text-dark border" style="font-size:.65rem">{{ $trx->cabang->nama_cabang }}</span> @endif
                    </div>
                </div>
                <div class="text-end">
                    @if($trx->tipe === \App\Enums\TipeTransaksiKeuangan::Pemasukan)
                        <div class="fw-bold text-success">+Rp {{ number_format($trx->jumlah, 0, ',', '.') }}</div>
                        <span class="badge bg-success" style="font-size:0.7rem">Pemasukan</span>
                    @else
                        <div class="fw-bold text-danger">-Rp {{ number_format($trx->jumlah, 0, ',', '.') }}</div>
                        <span class="badge bg-danger" style="font-size:0.7rem">Pengeluaran</span>
                    @endif
                </div>
            </div>
            {{-- Aksi mobile --}}
            <div class="mt-2 pt-2 border-top d-flex gap-2 flex-wrap align-items-center">
                @if($trx->referensi_type === 'order')
                    <a href="{{ route('penjualan.show', $trx->referensi_id) }}" class="btn btn-sm btn-outline-info py-1" style="font-size:0.78rem">
                        <i class="bi bi-receipt me-1"></i>Lihat Order
                    </a>
                    <span class="text-muted" style="font-size:0.72rem"><i class="bi bi-lock me-1"></i>Edit dari Order</span>
                @elseif($trx->referensi_type === 'purchase_order')
                    <a href="{{ route('pembelian.show', $trx->referensi_id) }}" class="btn btn-sm btn-outline-secondary py-1" style="font-size:0.78rem">
                        <i class="bi bi-bag-plus me-1"></i>Lihat PO
                    </a>
                    <span class="text-muted" style="font-size:0.72rem"><i class="bi bi-lock me-1"></i>Edit dari PO</span>
                @elseif($trx->referensi_type === 'stock_movement' || str_contains($trx->referensi_type ?? '', 'StockMovement'))
                    <a href="{{ route('stok.index') }}" class="btn btn-sm btn-outline-warning py-1" style="font-size:0.78rem">
                        <i class="bi bi-sliders me-1"></i>Adjustment Stok
                    </a>
                    <span class="text-muted" style="font-size:0.72rem"><i class="bi bi-lock me-1"></i>Dari Adj.Stok</span>
                @elseif(!$trx->referensi_type)
                    @can('keuangan.edit')
                    <a href="{{ route('keuangan.edit', $trx) }}" class="btn btn-sm btn-outline-warning py-1" style="font-size:0.78rem">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    @endcan
                    @can('keuangan.delete')
                    <button type="button"
                        onclick="confirmHapusTransaksi({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}')"
                        class="btn btn-sm btn-outline-danger py-1" style="font-size:0.78rem">
                        <i class="bi bi-trash me-1"></i>Hapus
                    </button>
                    @endcan
                @else
                    <span class="text-muted" style="font-size:0.72rem"><i class="bi bi-link-45deg me-1"></i>{{ Str::title(str_replace('_', ' ', $trx->referensi_type)) }}</span>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada transaksi
    </div>
    @endforelse
    @if($transaksis->hasPages())
    <div class="mt-3">{{ $transaksis->links() }}</div>
    @endif
</div>
{{-- Modal hapus transaksi manual --}}
<div class="modal fade" id="hapusTransaksiModal" tabindex="-1" aria-labelledby="hapusTransaksiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold" id="hapusTransaksiLabel">
                    <i class="bi bi-trash me-2"></i>Hapus Transaksi
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Apakah Anda yakin ingin menghapus transaksi <strong id="hapusTransaksiNomor"></strong>?</p>
                <p class="text-muted small mb-0">Data bisa dipulihkan kembali dari menu <strong>Data Terhapus</strong>. Saldo kas akan dikembalikan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="hapusTransaksiForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmHapusTransaksi(id, nomor) {
    document.getElementById('hapusTransaksiNomor').textContent = nomor;
    document.getElementById('hapusTransaksiForm').action = '/keuangan/' + id;
    const modal = new bootstrap.Modal(document.getElementById('hapusTransaksiModal'));
    modal.show();
}
</script>
@endpush
