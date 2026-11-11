@extends('layouts.app')

@section('title', 'Setoran Kasir')

@section('content')

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2 text-success"></i>Setoran Kasir</h5>
    <div class="d-flex gap-2 align-items-center">
        @can('setoran_kasir.create')
        <a href="{{ route('setoran-kasir.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Buat Setoran Hari Ini
        </a>
        @endcan
        <x-panduan-button slug="setoran-kasir" />
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            @if($cabangOptions->isNotEmpty())
            <div class="col-6 col-md-3">
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangOptions as $cab)
                    <option value="{{ $cab->id }}" @selected(request('cabang_id') == $cab->id)>{{ $cab->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="menunggu" @selected(request('status')=='menunggu')>Menunggu Approval</option>
                    <option value="approved" @selected(request('status')=='approved')>Disetujui</option>
                    <option value="rejected" @selected(request('status')=='rejected')>Ditolak</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Cabang</th><th class="text-end">Sistem</th>
                        <th class="text-end">Disetor</th><th class="text-end">Selisih</th>
                        <th class="text-center">Status</th><th>Disubmit Oleh</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($setorans as $s)
                    <tr>
                        <td>{{ $s->tanggal->format('d/m/Y') }}</td>
                        <td>{{ $s->cabang->nama_cabang }}</td>
                        <td class="text-end">Rp {{ number_format($s->total_penjualan_sistem, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($s->total_disetor, 0, ',', '.') }}</td>
                        <td class="text-end {{ $s->selisih < 0 ? 'text-danger' : ($s->selisih > 0 ? 'text-warning' : '') }}">
                            Rp {{ number_format($s->selisih, 0, ',', '.') }}
                        </td>
                        <td class="text-center"><span class="badge {{ $s->status->badgeClass() }}">{{ $s->status->label() }}</span></td>
                        <td>{{ $s->disubmitOleh->name ?? '-' }}</td>
                        <td class="text-end">
                            <a href="{{ route('setoran-kasir.show', $s) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada setoran</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $setorans->links() }}</div>

@endsection
