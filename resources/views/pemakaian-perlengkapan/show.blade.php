@extends('layouts.app')

@section('title', 'Detail Pemakaian Perlengkapan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-box-arrow-up me-2 text-primary"></i>Detail Pemakaian Perlengkapan
    </h5>
    <a href="{{ route('pemakaian-perlengkapan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header">
                Pemakaian #{{ $pemakaian->id }} — {{ $pemakaian->tanggal_pemakaian->format('d M Y') }}
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 col-md-4 text-muted fw-normal">Item</dt>
                    <dd class="col-7 col-md-8">{{ $pemakaian->item?->nama_item ?? '-' }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Cabang</dt>
                    <dd class="col-7 col-md-8">{{ $pemakaian->cabang?->nama_cabang ?? '-' }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Qty</dt>
                    <dd class="col-7 col-md-8 fw-semibold">{{ number_format($pemakaian->qty, 3) }} {{ $pemakaian->item?->satuan }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Nilai</dt>
                    <dd class="col-7 col-md-8">Rp {{ number_format($pemakaian->nilai, 0, ',', '.') }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Keterangan</dt>
                    <dd class="col-7 col-md-8">{{ $pemakaian->keterangan ?: '-' }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Dicatat oleh</dt>
                    <dd class="col-7 col-md-8">{{ $pemakaian->createdBy?->name ?? '-' }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Dicatat pada</dt>
                    <dd class="col-7 col-md-8">{{ $pemakaian->created_at?->format('d/m/Y H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
