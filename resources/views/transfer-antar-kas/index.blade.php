@extends('layouts.app')

@section('title', 'Transfer Antar Kas')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-arrow-down-up me-2 text-primary"></i>Transfer Antar Kas</h5>
        <p class="text-muted mb-0 small">Mutasi dana antar Kas dalam 1 cabang (mis. Tunai &harr; Bank)</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('transfer_antar_kas.create')
        <a href="{{ route('transfer-antar-kas.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Transfer Antar Kas
        </a>
        @endcan
        <x-panduan-button slug="transfer-antar-kas" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    {!! session('success') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    {!! session('error') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="GET" class="mb-3 d-flex flex-wrap gap-2 align-items-end">
    <x-search-box placeholder="Nomor / keterangan / nama kas..." col="" />
    @if($cabangOptions->count())
    <div>
        <label class="form-label form-label-sm mb-1">Cabang</label>
        <select name="cabang_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Semua Cabang</option>
            @foreach($cabangOptions as $c)
            <option value="{{ $c->id }}" @selected(request('cabang_id') == $c->id)>{{ $c->nama_cabang }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
    @if(request('cabang_id') || request('search'))
    <a href="{{ route('transfer-antar-kas.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-x"></i> Reset
    </a>
    @endif
</form>

{{-- Desktop Table --}}
<div class="card d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>No. Transaksi</th>
                    <th>Kas Asal &rarr; Kas Tujuan</th>
                    <th class="text-end">Jumlah</th>
                    @if($cabangOptions->count())
                    <th>Cabang</th>
                    @endif
                    <th>Dibuat Oleh</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $trx)
                @php $pasangan = \App\Models\TransaksiKeuangan::withTrashed()->find($trx->referensi_id); @endphp
                <tr>
                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($trx->tanggal_transaksi)->format('d M Y') }}</td>
                    <td><code class="small">{{ $trx->nomor_transaksi }}</code></td>
                    <td>
                        <span class="fw-semibold">{{ $trx->kas?->nama_kas ?? '-' }}</span>
                        <i class="bi bi-arrow-right text-primary mx-1"></i>
                        <span class="fw-semibold">{{ $pasangan?->kas?->nama_kas ?? '-' }}</span>
                    </td>
                    <td class="text-end fw-bold text-nowrap">Rp {{ number_format($trx->jumlah, 0, ',', '.') }}</td>
                    @if($cabangOptions->count())
                    <td>{{ $trx->cabang?->nama_cabang ?? '-' }}</td>
                    @endif
                    <td class="small text-muted">{{ $trx->createdBy?->name ?? '-' }}</td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                            <a href="{{ route('transfer-antar-kas.show', $trx) }}"
                               class="btn btn-xs btn-outline-primary py-0 px-1" title="Detail" style="font-size:0.72rem">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('transfer_antar_kas.delete')
                            <button type="button"
                                onclick="confirmHapusTransfer({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}')"
                                class="btn btn-xs btn-outline-danger py-0 px-1" title="Hapus" style="font-size:0.72rem">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-arrow-down-up fs-2 d-block mb-2"></i>
                        Belum ada Transfer Antar Kas
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transfers->hasPages())
    <div class="card-footer">{{ $transfers->links() }}</div>
    @endif
</div>

{{-- Mobile Cards --}}
<div class="d-md-none">
    @forelse($transfers as $trx)
    @php $pasangan = \App\Models\TransaksiKeuangan::withTrashed()->find($trx->referensi_id); @endphp
    <div class="card mb-2">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <code class="small">{{ $trx->nomor_transaksi }}</code>
                    <div class="small text-muted">{{ \Carbon\Carbon::parse($trx->tanggal_transaksi)->format('d M Y') }}</div>
                </div>
                <span class="fw-bold text-nowrap">Rp {{ number_format($trx->jumlah, 0, ',', '.') }}</span>
            </div>
            <div class="mt-1">
                <span class="fw-semibold">{{ $trx->kas?->nama_kas ?? '-' }}</span>
                <i class="bi bi-arrow-right text-primary mx-1"></i>
                <span class="fw-semibold">{{ $pasangan?->kas?->nama_kas ?? '-' }}</span>
            </div>
            @if($cabangOptions->count())
            <div class="small text-muted">{{ $trx->cabang?->nama_cabang ?? '-' }}</div>
            @endif
            <div class="d-flex gap-2 mt-2">
                <a href="{{ route('transfer-antar-kas.show', $trx) }}" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-eye me-1"></i>Detail
                </a>
                @can('transfer_antar_kas.delete')
                <button type="button" onclick="confirmHapusTransfer({{ $trx->id }}, '{{ addslashes($trx->nomor_transaksi) }}')"
                    class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                </button>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-arrow-down-up fs-2 d-block mb-2"></i>
        Belum ada Transfer Antar Kas
    </div>
    @endforelse
    @if($transfers->hasPages())
    <div class="mt-2">{{ $transfers->links() }}</div>
    @endif
</div>

<form id="hapusTransferForm" method="POST" style="display:none">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
function confirmHapusTransfer(id, nomor) {
    window.showConfirm(
        'Hapus Transfer Antar Kas?',
        'Transfer <strong>' + nomor + '</strong> akan dihapus dan saldo kedua Kas dikembalikan ke kondisi semula. Aksi ini bisa dipulihkan lewat menu Data Terhapus.',
        { icon: 'warning', confirmText: 'Ya, Hapus', cancelText: 'Batal' }
    ).then(function (result) {
        if (result.isConfirmed) {
            const form = document.getElementById('hapusTransferForm');
            form.action = '/keuangan/transfer-antar-kas/' + id;
            form.submit();
        }
    });
}
</script>
@endpush
