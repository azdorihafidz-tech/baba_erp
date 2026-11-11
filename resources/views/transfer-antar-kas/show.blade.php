@extends('layouts.app')

@section('title', 'Detail Transfer Antar Kas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-arrow-down-up me-2 text-primary"></i>Detail Transfer Antar Kas
    </h5>
    <a href="{{ route('transfer-antar-kas.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row g-3 justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-body text-center">
                <div class="small text-muted mb-1">Jumlah Transfer</div>
                <div class="fs-3 fw-bold text-primary">Rp {{ number_format($trxKeluar->jumlah, 0, ',', '.') }}</div>
                <div class="small text-muted mt-1">{{ \Carbon\Carbon::parse($trxKeluar->tanggal_transaksi)->format('d M Y') }}</div>
                @if($trxKeluar->keterangan)
                <div class="mt-2">{{ Str::after($trxKeluar->keterangan, ': ') }}</div>
                @endif
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="card border-danger h-100">
                    <div class="card-header bg-danger bg-opacity-10 text-danger fw-semibold">
                        <i class="bi bi-arrow-up-right me-1"></i>Kas Asal (Keluar)
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-5">No. Transaksi</dt>
                            <dd class="col-7"><code>{{ $trxKeluar->nomor_transaksi }}</code></dd>
                            <dt class="col-5">Kas</dt>
                            <dd class="col-7">{{ $trxKeluar->kas?->nama_kas ?? '-' }}</dd>
                            <dt class="col-5">Cabang</dt>
                            <dd class="col-7">{{ $trxKeluar->cabang?->nama_cabang ?? '-' }}</dd>
                            <dt class="col-5">Jumlah</dt>
                            <dd class="col-7 text-danger fw-bold">-Rp {{ number_format($trxKeluar->jumlah, 0, ',', '.') }}</dd>
                        </dl>
                        <a href="{{ route('keuangan.index', ['search' => $trxKeluar->nomor_transaksi]) }}"
                           class="btn btn-sm btn-outline-secondary w-100 mt-2">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Lihat di Kas & Transaksi
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="card border-success h-100">
                    <div class="card-header bg-success bg-opacity-10 text-success fw-semibold">
                        <i class="bi bi-arrow-down-left me-1"></i>Kas Tujuan (Masuk)
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-5">No. Transaksi</dt>
                            <dd class="col-7"><code>{{ $trxMasuk->nomor_transaksi }}</code></dd>
                            <dt class="col-5">Kas</dt>
                            <dd class="col-7">{{ $trxMasuk->kas?->nama_kas ?? '-' }}</dd>
                            <dt class="col-5">Cabang</dt>
                            <dd class="col-7">{{ $trxMasuk->cabang?->nama_cabang ?? '-' }}</dd>
                            <dt class="col-5">Jumlah</dt>
                            <dd class="col-7 text-success fw-bold">+Rp {{ number_format($trxMasuk->jumlah, 0, ',', '.') }}</dd>
                        </dl>
                        <a href="{{ route('keuangan.index', ['search' => $trxMasuk->nomor_transaksi]) }}"
                           class="btn btn-sm btn-outline-secondary w-100 mt-2">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Lihat di Kas & Transaksi
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="small text-muted mt-3">
            Dibuat oleh <strong>{{ $trxKeluar->createdBy?->name ?? '-' }}</strong>
            pada {{ $trxKeluar->created_at?->format('d M Y H:i') }}
        </div>

        @can('transfer_antar_kas.delete')
        <div class="mt-3">
            <button type="button" onclick="confirmHapusTransferDetail()" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Hapus Transfer Ini
            </button>
        </div>
        <form id="hapusTransferDetailForm" method="POST" action="{{ route('transfer-antar-kas.destroy', $trxKeluar) }}" style="display:none">
            @csrf
            @method('DELETE')
        </form>
        @endcan
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmHapusTransferDetail() {
    window.showConfirm(
        'Hapus Transfer Antar Kas?',
        'Transfer <strong>{{ $trxKeluar->nomor_transaksi }}</strong> akan dihapus dan saldo kedua Kas dikembalikan ke kondisi semula. Aksi ini bisa dipulihkan lewat menu Data Terhapus.',
        { icon: 'warning', confirmText: 'Ya, Hapus', cancelText: 'Batal' }
    ).then(function (result) {
        if (result.isConfirmed) {
            document.getElementById('hapusTransferDetailForm').submit();
        }
    });
}
</script>
@endpush
