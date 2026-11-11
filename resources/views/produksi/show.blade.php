@extends('layouts.app')

@section('title', $batch->kode_batch)

@section('content')
@php $angka = fn ($n) => \App\Services\ProduksiService::angka((float) $n); $satuan = $batch->item?->satuan; @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-droplet me-2 text-success"></i>{{ $batch->kode_batch }}
        <span class="ms-2">@include('produksi._status', ['b' => $batch])</span></h5>
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
                <tr><th>Rencana / diracik</th><td>{{ $angka($batch->qty_rencana) }} {{ $satuan }}</td></tr>
                @if($batch->tanggal_siap)
                <tr><th>Pemeraman <x-tooltip key="produksi.pemeraman" /></th><td>{{ $batch->tanggal->format('d/m/Y') }} → siap {{ $batch->tanggal_siap->format('d/m/Y') }}
                    ({{ (int) $batch->tanggal->diffInDays($batch->tanggal_siap) }} hari)</td></tr>
                @endif
                @if($batch->status !== 'diperam')
                <tr><th>Hasil nyata</th><td>{{ $angka($batch->qty_hasil) }} {{ $satuan }}
                    @php $selisih = (float) $batch->qty_hasil - (float) $batch->qty_rencana; @endphp
                    @if(abs($selisih) >= 0.001)<span class="text-{{ $selisih < 0 ? 'danger' : 'success' }} small">({{ $selisih < 0 ? 'susut ' : '+' }}{{ $angka(abs($selisih)) }}{{ $selisih < 0 && (float) $batch->qty_rencana > 0 ? ' · ' . $angka(abs($selisih) / (float) $batch->qty_rencana * 100) . '%' : '' }})</span>@endif
                </td></tr>
                @endif
                <tr><th>Biaya bahan</th><td>Rp {{ number_format($batch->biaya_bahan, 0, ',', '.') }}</td></tr>
                @if($batch->status !== 'diperam')
                <tr><th>Modal otomatis <x-tooltip key="produksi.modal" /></th><td>Rp {{ number_format($batch->modal_otomatis, 0, ',', '.') }} / {{ $satuan }}</td></tr>
                <tr><th>Modal dipakai</th><td class="fw-semibold">Rp {{ number_format($batch->modal_per_unit, 0, ',', '.') }} / {{ $satuan }}
                    @if($batch->modal_manual)<span class="badge bg-warning text-dark ms-1">diisi manual</span>@endif
                </td></tr>
                <tr><th>Nilai stok hasil</th><td>Rp {{ number_format($batch->nilai_hasil, 0, ',', '.') }}</td></tr>
                @endif
                @if($batch->catatan)<tr><th>Catatan</th><td>{{ $batch->catatan }}</td></tr>@endif
                <tr><th>Dicatat oleh</th><td>{{ $batch->user?->name }} · {{ $batch->created_at->format('d/m/Y H:i') }}</td></tr>
                @if($batch->diselesaikan_at)<tr><th>Selesai peram oleh</th><td>{{ $batch->diselesaikanOleh?->name }} · {{ $batch->diselesaikan_at->format('d/m/Y H:i') }}</td></tr>@endif
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
            @if($batch->status === 'diperam')
                <div class="alert alert-warning small mb-3"><i class="bi bi-hourglass-split me-1"></i>
                    Sedang diperam. Bahan sudah dipotong dari stok; <strong>{{ $batch->item?->nama_item }}</strong> masuk stok setelah <strong>Selesai Peram &amp; Saring</strong>@if($batch->tanggal_siap), bisa mulai tanggal <strong>{{ $batch->tanggal_siap->format('d/m/Y') }}</strong>@endif.
                </div>
                @can('produksi.create')
                <form method="POST" action="{{ route('produksi.selesai-peram', $batch) }}" class="mb-3">
                    @csrf
                    <label class="form-label small fw-semibold">Hasil akhir setelah disaring * <x-tooltip key="produksi.hasil_akhir" /></label>
                    <div class="input-group input-group-sm mb-1">
                        <input type="number" step="0.001" min="0.001" max="{{ (float) $batch->qty_rencana }}" name="qty_hasil" id="hasilAkhir" class="form-control @error('qty_hasil') is-invalid @enderror" value="{{ old('qty_hasil', (float) $batch->qty_rencana) }}" required {{ $batch->siap_diselesaikan ? '' : 'disabled' }}>
                        <span class="input-group-text">{{ $satuan }}</span>
                    </div>
                    @error('qty_hasil')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text mb-2">Diracik {{ $angka($batch->qty_rencana) }} {{ $satuan }}. Selisihnya tercatat sebagai susut (penguapan/ampas).</div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="pakai_modal_manual" value="1" id="pakaiManualSelesai" @checked(old('pakai_modal_manual')) {{ $batch->siap_diselesaikan ? '' : 'disabled' }}>
                        <label class="form-check-label small" for="pakaiManualSelesai">Isi modal per unit secara manual</label>
                    </div>
                    <div class="input-group input-group-sm mb-2 {{ old('pakai_modal_manual') ? '' : 'd-none' }}" id="boxManualSelesai">
                        <span class="input-group-text">Rp</span>
                        <input type="number" step="1" min="0" name="modal_manual" class="form-control" value="{{ old('modal_manual') }}">
                        <span class="input-group-text">/ {{ $satuan }}</span>
                    </div>
                    <button class="btn btn-success btn-sm w-100" {{ $batch->siap_diselesaikan ? '' : 'disabled' }}>
                        <i class="bi bi-check2-circle me-1"></i>Selesai Peram &amp; Saring
                    </button>
                    @unless($batch->siap_diselesaikan)<div class="small text-muted mt-1">Tombol aktif mulai {{ $batch->tanggal_siap?->format('d/m/Y') }}.</div>@endunless
                </form>
                <script>document.getElementById('pakaiManualSelesai')?.addEventListener('change', e => document.getElementById('boxManualSelesai').classList.toggle('d-none', !e.target.checked));</script>
                @endcan
                @can('produksi.batalkan')
                <form method="POST" action="{{ route('produksi.batalkan', $batch) }}" onsubmit="return confirm('Batalkan produksi ini? Bahan dikembalikan ke stok gudang.')">
                    @csrf
                    <label class="form-label small fw-semibold">Batalkan produksi <x-tooltip key="produksi.batalkan" /></label>
                    <textarea name="alasan" rows="2" maxlength="500" class="form-control form-control-sm mb-2 @error('alasan') is-invalid @enderror" placeholder="Alasan, mis. salah racik" required>{{ old('alasan') }}</textarea>
                    @error('alasan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-outline-danger btn-sm w-100">Batalkan Produksi</button>
                </form>
                @endcan
            @elseif($batch->status === 'selesai')
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
