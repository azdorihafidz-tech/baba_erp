@extends('layouts.app')

@section('title', 'Detail Transaksi Keuangan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-receipt me-2 text-primary"></i>Detail Transaksi Keuangan
    </h5>
    <a href="{{ $referensiPo ? route('pembelian.show', $referensiPo) : route('keuangan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali{{ $referensiPo ? ' ke PO' : '' }}
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Transaksi — <strong>{{ $transaksi->nomor_transaksi }}</strong></span>
                <span class="badge bg-{{ $transaksi->tipe->value === 'pemasukan' ? 'success' : 'danger' }}">
                    {{ $transaksi->tipe->label() }}
                </span>
            </div>
            <div class="card-body">
                @if($referensiPo)
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Transaksi ini ter-link ke Purchase Order
                    <a href="{{ route('pembelian.show', $referensiPo) }}" class="fw-semibold">{{ $referensiPo->nomor_po }}</a>
                    — pembayaran PO tercatat lewat transaksi ini.
                </div>
                @endif

                <dl class="row mb-0">
                    <dt class="col-5 col-md-4 text-muted fw-normal">Tanggal</dt>
                    <dd class="col-7 col-md-8">{{ $transaksi->tanggal_transaksi->format('d M Y') }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Kategori</dt>
                    <dd class="col-7 col-md-8">{{ $transaksi->kategoriDinamis?->nama ?? $transaksi->kategori?->label() ?? '-' }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Jumlah</dt>
                    <dd class="col-7 col-md-8 fw-semibold">Rp {{ number_format($transaksi->jumlah, 0, ',', '.') }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Kas</dt>
                    <dd class="col-7 col-md-8">{{ $transaksi->kas?->nama_kas ?? '-' }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Cabang</dt>
                    <dd class="col-7 col-md-8">{{ $transaksi->cabang?->nama_cabang ?? '-' }}</dd>

                    <dt class="col-5 col-md-4 text-muted fw-normal">Keterangan</dt>
                    <dd class="col-7 col-md-8">{{ $transaksi->keterangan }}</dd>

                    @if($transaksi->catatan)
                    <dt class="col-5 col-md-4 text-muted fw-normal">Catatan</dt>
                    <dd class="col-7 col-md-8">{{ $transaksi->catatan }}</dd>
                    @endif

                    <dt class="col-5 col-md-4 text-muted fw-normal">Dicatat oleh</dt>
                    <dd class="col-7 col-md-8">{{ $transaksi->createdBy?->name ?? '-' }}</dd>

                    @if($transaksi->bukti_path)
                    <dt class="col-5 col-md-4 text-muted fw-normal">Bukti</dt>
                    <dd class="col-7 col-md-8">
                        <a href="{{ '/img/' . $transaksi->bukti_path }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-paperclip me-1"></i>Lihat Bukti
                        </a>
                    </dd>
                    @endif
                </dl>

                <hr>
                <p class="text-muted small mb-0">
                    <i class="bi bi-lock me-1"></i>Transaksi ini sudah ter-link ke sumber lain ({{ $transaksi->referensi_type }}) — tidak bisa diedit langsung dari sini. Edit dari sumber aslinya.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
