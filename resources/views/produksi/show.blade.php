@extends('layouts.app')

@section('title', $batch->kode_batch)

@section('content')
@php $angka = fn ($n) => \App\Services\ProduksiService::angka((float) $n); $satuan = $batch->item?->satuan; @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-fire me-2 text-success"></i>{{ $batch->kode_batch }}
        <span class="badge bg-{{ $batch->status === 'selesai' ? 'success' : 'danger' }} ms-2">{{ ucfirst($batch->status) }}</span></h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="produksi" />
        <a href="{{ route('produksi.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

@include('produksi._tabs')

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card"><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><th style="width:200px">Tanggal</th><td>{{ $batch->tanggal->format('d/m/Y') }}</td></tr>
                <tr><th>Gudang</th><td>{{ $batch->cabang?->nama_cabang }}</td></tr>
                <tr><th>Barang hasil</th><td class="fw-semibold">{{ $batch->item?->nama_item }}</td></tr>
                <tr><th>Rencana</th><td>{{ $angka($batch->qty_rencana) }} {{ $satuan }}</td></tr>
                <tr><th>Hasil nyata</th><td>{{ $angka($batch->qty_hasil) }} {{ $satuan }}
                    @php $selisih = (float) $batch->qty_hasil - (float) $batch->qty_rencana; @endphp
                    @if(abs($selisih) >= 0.001)<span class="text-{{ $selisih < 0 ? 'danger' : 'success' }} small">({{ $selisih > 0 ? '+' : '' }}{{ $angka($selisih) }})</span>@endif
                </td></tr>
                <tr><th>Biaya bahan</th><td>Rp {{ number_format($batch->biaya_bahan, 0, ',', '.') }}</td></tr>
                <tr><th>Modal otomatis <x-tooltip key="produksi.modal" /></th><td>Rp {{ number_format($batch->modal_otomatis, 0, ',', '.') }} / {{ $satuan }}</td></tr>
                <tr><th>Modal dipakai</th><td class="fw-semibold">Rp {{ number_format($batch->modal_per_unit, 0, ',', '.') }} / {{ $satuan }}
                    @if($batch->modal_manual)<span class="badge bg-warning text-dark ms-1">diisi manual</span>@endif
                </td></tr>
                <tr><th>Nilai stok hasil</th><td>Rp {{ number_format($batch->nilai_hasil, 0, ',', '.') }}</td></tr>
                @if($batch->catatan)<tr><th>Catatan</th><td>{{ $batch->catatan }}</td></tr>@endif
                <tr><th>Dicatat oleh</th><td>{{ $batch->user?->name }} · {{ $batch->created_at->format('d/m/Y H:i') }}</td></tr>
                @if($batch->status === 'dibatalkan')
                <tr class="table-danger"><th>Dibatalkan</th><td>{{ $batch->dibatalkanOleh?->name }} · {{ $batch->dibatalkan_at?->format('d/m/Y H:i') }}<br><span class="small">Alasan: {{ $batch->alasan_batal }}</span></td></tr>
                @endif
            </table>
        </div></div>

        <div class="card mt-3">
            <div class="card-header bg-white small fw-semibold">Bahan terpakai</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Bahan</th><th class="text-end">Qty</th><th class="text-end">Modal (FIFO)</th></tr></thead>
                    <tbody>
                    @foreach($batch->bahan as $b)
                    <tr><td>{{ $b->item?->nama_item }}</td><td class="text-end">{{ $angka($b->qty) }} {{ $b->item?->satuan }}</td><td class="text-end">Rp {{ number_format($b->biaya, 0, ',', '.') }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr class="fw-semibold"><td colspan="2">Total</td><td class="text-end">Rp {{ number_format($batch->biaya_bahan, 0, ',', '.') }}</td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card"><div class="card-body">
            @if($batch->status === 'selesai')
                <div class="text-success small mb-3"><i class="bi bi-check-circle-fill me-1"></i>Stok {{ $batch->item?->nama_item }} di {{ $batch->cabang?->nama_cabang }} sudah bertambah {{ $angka($batch->qty_hasil) }} {{ $satuan }}.</div>
                @can('produksi.create')
                <a href="{{ route('produksi.create', ['resep' => $batch->resep_produksi_id]) }}" class="btn btn-outline-primary btn-sm w-100 mb-3">Produksi lagi</a>
                @endcan
                @can('produksi.batalkan')
                <form method="POST" action="{{ route('produksi.batalkan', $batch) }}" onsubmit="return confirm('Batalkan produksi ini? Stok hasil ditarik dan bahan dikembalikan.')">
                    @csrf
                    <label class="form-label small fw-semibold">Batalkan produksi <x-tooltip key="produksi.batalkan" /></label>
                    <textarea name="alasan" rows="2" maxlength="500" class="form-control form-control-sm mb-2 @error('alasan') is-invalid @enderror" placeholder="Alasan, mis. salah input jumlah" required>{{ old('alasan') }}</textarea>
                    @error('alasan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-outline-danger btn-sm w-100">Batalkan Produksi</button>
                </form>
                @endcan
            @else
                <div class="text-muted small">Produksi ini sudah dibatalkan — stok hasil sudah ditarik dan bahan sudah dikembalikan ke gudang.</div>
            @endif
        </div></div>
    </div>
</div>
@endsection
