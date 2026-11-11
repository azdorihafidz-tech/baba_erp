@extends('layouts.app')

@section('title', 'Buat Setoran Kasir')

@section('content')

<div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Buat Setoran Kasir — {{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }}</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('setoran-kasir.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <x-panduan-button slug="setoran-kasir" />
    </div>
</div>

@if($sudahAda && $sudahAda->status->value !== 'rejected')
<div class="alert alert-warning">
    Setoran untuk hari ini sudah pernah disubmit dengan status <strong>{{ $sudahAda->status->label() }}</strong>.
    <a href="{{ route('setoran-kasir.show', $sudahAda) }}">Lihat detail</a>.
</div>
@else

@if($sudahAda && $sudahAda->status->value === 'rejected')
<div class="alert alert-info">
    Setoran hari ini sebelumnya <strong>ditolak</strong>: {{ $sudahAda->ditolak_alasan }}. Silakan revisi &amp; submit ulang di bawah.
</div>
@endif

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Ringkasan Penjualan Sistem (Auto-hitung)</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td>Tunai</td><td class="text-end">Rp {{ number_format($hitung['per_metode']['tunai'], 0, ',', '.') }}</td></tr>
                        <tr><td>Transfer</td><td class="text-end">Rp {{ number_format($hitung['per_metode']['transfer'], 0, ',', '.') }}</td></tr>
                        <tr><td>QRIS</td><td class="text-end">Rp {{ number_format($hitung['per_metode']['qris'], 0, ',', '.') }}</td></tr>
                        <tr class="fw-bold border-top"><td>Total</td><td class="text-end">Rp {{ number_format($hitung['total'], 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
                <div class="form-text mt-2">
                    Hanya <strong>Tunai</strong> yang disetorkan fisik ke HO — metode non-tunai sudah otomatis masuk kas non-tunai cabang saat transaksi dibuat.
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <form method="POST" action="{{ route('setoran-kasir.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="tanggal" value="{{ $tanggal }}">
            <div class="card">
                <div class="card-header fw-semibold">Setor Uang Tunai</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Jumlah Uang Tunai yang Diserahkan * <x-tooltip key="setoran_kasir.total_disetor" /></label>
                        <input type="number" name="total_disetor" class="form-control @error('total_disetor') is-invalid @enderror"
                            step="0.01" min="0" value="{{ old('total_disetor', $hitung['per_metode']['tunai']) }}" required>
                        @error('total_disetor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Default terisi sesuai sistem (Rp {{ number_format($hitung['per_metode']['tunai'], 0, ',', '.') }}) — ubah kalau ada selisih uang fisik.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bukti Foto (opsional) <x-tooltip key="setoran_kasir.bukti_foto" /></label>
                        <input type="file" name="bukti_foto" accept="image/*" class="form-control form-control-sm">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan <x-tooltip key="setoran_kasir.catatan_kasir" /></label>
                        <textarea name="catatan_kasir" rows="2" class="form-control">{{ old('catatan_kasir') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Submit Setoran</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
