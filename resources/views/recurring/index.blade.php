@extends('layouts.app')
@section('title', 'Transaksi Berulang')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-arrow-repeat text-primary me-2"></i>Transaksi Berulang</h4>
    <div class="d-flex gap-2 align-items-center">
        @can('recurring.create')
        <a href="{{ route('recurring.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Tambah Template</span>
        </a>
        @endcan
        <x-panduan-button slug="recurring" />
    </div>
</div>

<form method="GET" action="{{ route('recurring.index') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Tipe</label>
                <select name="tipe" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    <option value="pemasukan"   @selected(request('tipe')==='pemasukan')>Pemasukan</option>
                    <option value="pengeluaran" @selected(request('tipe')==='pengeluaran')>Pengeluaran</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="aktif" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    <option value="1" @selected(request('aktif')==='1')>Aktif</option>
                    <option value="0" @selected(request('aktif')==='0')>Nonaktif</option>
                </select>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nama Template</th>
                    <th class="d-none d-md-table-cell">Tipe</th>
                    <th class="text-end">Jumlah</th>
                    <th class="d-none d-md-table-cell">Frekuensi</th>
                    <th class="text-center">Tgl. Jatuh Tempo</th>
                    <th class="d-none d-lg-table-cell">Generate Terakhir</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($recurrings as $r)
            <tr class="{{ !$r->is_active ? 'text-muted' : '' }}">
                <td>
                    <div class="fw-semibold small">{{ $r->nama_template }}</div>
                    <div class="text-muted" style="font-size:0.75rem">{{ Str::limit($r->keterangan, 35) }}</div>
                </td>
                <td class="d-none d-md-table-cell">
                    <span class="badge bg-{{ $r->tipe === 'pemasukan' ? 'success' : 'danger' }} bg-opacity-75">{{ ucfirst($r->tipe) }}</span>
                </td>
                <td class="text-end fw-semibold small">Rp {{ number_format($r->jumlah,0,',','.') }}</td>
                <td class="small d-none d-md-table-cell">{{ ucfirst($r->frekuensi) }}</td>
                <td class="text-center small">Tgl {{ $r->tanggal_jatuh_tempo }}</td>
                <td class="small d-none d-lg-table-cell text-muted">
                    {{ $r->tanggal_terakhir_generate ? \Carbon\Carbon::parse($r->tanggal_terakhir_generate)->format('d/m/Y') : '-' }}
                </td>
                <td class="text-center">
                    @if($r->is_active)
                        <span class="badge bg-success">Aktif</span>
                    @else
                        <span class="badge bg-secondary">Nonaktif</span>
                    @endif
                </td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                        @can('recurring.create')
                        <form action="{{ route('recurring.generate', $r) }}" method="POST" onsubmit="return confirm('Generate transaksi hari ini untuk template ini?')">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-outline-primary py-0 px-1" title="Generate sekarang" {{ !$r->is_active?'disabled':'' }}>
                                <i class="bi bi-play-fill"></i>
                            </button>
                        </form>
                        <form action="{{ route('recurring.toggle-active', $r) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-xs {{ $r->is_active?'btn-outline-warning':'btn-outline-success' }} py-0 px-1" title="{{ $r->is_active?'Nonaktifkan':'Aktifkan' }}">
                                <i class="bi bi-{{ $r->is_active?'pause-fill':'play-circle' }}"></i>
                            </button>
                        </form>
                        @endcan
                        @can('recurring.edit')
                        <a href="{{ route('recurring.edit', $r) }}" class="btn btn-xs btn-outline-secondary py-0 px-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @endcan
                        @can('recurring.delete')
                        <form action="{{ route('recurring.destroy', $r) }}" method="POST" onsubmit="return confirm('Hapus template ini?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-arrow-repeat fs-2 d-block mb-2 opacity-25"></i>
                Belum ada template transaksi berulang.
                @can('recurring.create')<br><a href="{{ route('recurring.create') }}" class="btn btn-primary btn-sm mt-2">Buat Template Pertama</a>@endcan
            </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($recurrings->hasPages())
    <div class="card-footer py-2">{{ $recurrings->links() }}</div>
    @endif
</div>
@endsection
