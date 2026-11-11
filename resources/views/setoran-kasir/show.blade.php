@extends('layouts.app')

@section('title', 'Detail Setoran Kasir')

@section('content')

<div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-cash-coin me-2"></i>Setoran {{ $setoran->cabang->nama_cabang }} — {{ $setoran->tanggal->format('d/m/Y') }}
        <span class="badge {{ $setoran->status->badgeClass() }} ms-2">{{ $setoran->status->label() }}</span>
    </h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('setoran-kasir.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <x-panduan-button slug="setoran-kasir" />
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Breakdown per Metode</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Metode</th><th class="text-end">Sistem</th><th class="text-end">Fisik</th></tr></thead>
                    <tbody>
                        @foreach($setoran->details as $d)
                        <tr>
                            <td class="text-capitalize">{{ $d->metode->value }}</td>
                            <td class="text-end">Rp {{ number_format($d->jumlah_sistem, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($d->jumlah_fisik ?? $d->jumlah_sistem, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Ringkasan</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-4"><div class="text-muted small">Total Sistem</div><div class="fw-bold">Rp {{ number_format($setoran->total_penjualan_sistem, 0, ',', '.') }}</div></div>
                    <div class="col-4"><div class="text-muted small">Total Disetor (Tunai)</div><div class="fw-bold">Rp {{ number_format($setoran->total_disetor, 0, ',', '.') }}</div></div>
                    <div class="col-4"><div class="text-muted small">Selisih</div><div class="fw-bold {{ $setoran->selisih < 0 ? 'text-danger' : ($setoran->selisih > 0 ? 'text-warning' : 'text-success') }}">Rp {{ number_format($setoran->selisih, 0, ',', '.') }}</div></div>
                </div>
                @if($setoran->catatan_kasir)
                <div class="mt-3"><div class="text-muted small">Catatan Kasir</div>{{ $setoran->catatan_kasir }}</div>
                @endif
                @if($setoran->bukti_foto)
                <div class="mt-3">
                    <div class="text-muted small mb-1">Bukti Foto</div>
                    <img src="{{ asset('storage/'.$setoran->bukti_foto) }}" style="max-width:250px" class="rounded border">
                </div>
                @endif
                @if($setoran->status->value === 'rejected')
                <div class="alert alert-danger mt-3 mb-0">Alasan Ditolak: {{ $setoran->ditolak_alasan }}</div>
                @endif
                @if($setoran->status->value === 'approved')
                <div class="alert alert-success mt-3 mb-0">
                    Disetujui oleh {{ $setoran->disetujuiOleh->name ?? '-' }} pada {{ $setoran->disetujui_pada?->format('d/m/Y H:i') }}
                    @if($setoran->catatan_ho) — {{ $setoran->catatan_ho }} @endif
                </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Riwayat</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($setoran->approvals as $a)
                        <tr>
                            <td>{{ $a->dilakukan_pada->format('d/m/Y H:i') }}</td>
                            <td class="text-capitalize">{{ $a->action }}</td>
                            <td>{{ $a->user->name ?? '-' }}</td>
                            <td>{{ $a->catatan }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('setoran_kasir.approve')
    @if($setoran->status->value === 'menunggu')
    <div class="col-12 col-lg-5">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Aksi HO</div>
            <div class="card-body">
                <form method="POST" action="{{ route('setoran-kasir.approve', $setoran) }}" class="mb-3">
                    @csrf
                    <label class="form-label small mb-1">Catatan HO <x-tooltip key="setoran_kasir.catatan_ho" /></label>
                    <textarea name="catatan_ho" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan (opsional)"></textarea>
                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('Approve setoran ini? Saldo Kas HO akan bertambah.')">
                        <i class="bi bi-check-lg me-1"></i>Approve
                    </button>
                </form>
                <form method="POST" action="{{ route('setoran-kasir.reject', $setoran) }}">
                    @csrf
                    <label class="form-label small mb-1">Alasan Penolakan <x-tooltip key="setoran_kasir.alasan_reject" /></label>
                    <textarea name="alasan" class="form-control form-control-sm mb-2" rows="2" placeholder="Alasan penolakan (wajib)" required></textarea>
                    <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-x-lg me-1"></i>Reject</button>
                </form>
            </div>
        </div>
    </div>
    @endif
    @endcan
</div>

@endsection
