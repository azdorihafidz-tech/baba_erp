@extends('layouts.app')

@section('title', 'Ambil Produk dari Cabang Lain')

@section('content')

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-arrow-in-down me-2 text-success"></i>Ambil Produk dari Cabang Lain</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.produk-jual.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <x-panduan-button slug="ambil-produk-cabang" />
    </div>
</div>

<div class="alert alert-info small py-2">
    <i class="bi bi-info-circle me-1"></i>
    Produk yang diambil <strong>disalin jadi milik cabang ini</strong> (nama, kode, kategori, foto, harga, varian, komposisi).
    Komposisi memakai <strong>bahan milik cabang ini sendiri</strong> — stok yang terpotong saat dijual adalah stok cabang ini.
    Setelah disalin, produk bisa diubah bebas tanpa memengaruhi cabang sumber.
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            @if(\App\Support\CabangAktif::id())
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-medium mb-1">Ke cabang</label>
                    <div class="form-control form-control-sm bg-light">{{ $tujuan?->nama_cabang }}</div>
                </div>
            @else
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-medium mb-1" for="tujuanSelect">Ke cabang <x-tooltip key="ambil_produk.tujuan" /></label>
                    <select name="tujuan" id="tujuanSelect" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Pilih cabang tujuan --</option>
                        @foreach($pilihanTujuan as $c)
                            <option value="{{ $c->id }}" @selected($tujuan?->id === $c->id)>{{ $c->nama_cabang }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-4">
                <label class="form-label small fw-medium mb-1" for="sumberSelect">Ambil dari cabang <x-tooltip key="ambil_produk.sumber" /></label>
                <select name="sumber" id="sumberSelect" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Pilih cabang sumber --</option>
                    @foreach($cabangSumber as $c)
                        <option value="{{ $c->id }}" @selected($sumber?->id === $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            <x-search-box placeholder="Cari nama / kode produk..." col="col-8 col-md-3" />
            <div class="col-4 col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

@if(! $tujuan)
    <div class="text-center text-muted py-5"><i class="bi bi-geo-alt fs-1 d-block mb-2"></i>Pilih cabang tujuan dulu.</div>
@elseif(! $sumber)
    <div class="text-center text-muted py-5"><i class="bi bi-shop fs-1 d-block mb-2"></i>Pilih cabang sumber untuk melihat produknya.</div>
@else
<form method="GET" action="{{ route('master.produk-jual.ambil.periksa') }}" id="formPilihProduk">
    <input type="hidden" name="sumber" value="{{ $sumber->id }}">
    <input type="hidden" name="tujuan[]" value="{{ $tujuan->id }}">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Produk Jual di <strong>{{ $sumber->nama_cabang }}</strong> ({{ $produk->count() }})</span>
            @if($produk->isNotEmpty())
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="pilihSemua">
                <label class="form-check-label small" for="pilihSemua">Pilih semua</label>
            </div>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px"></th>
                        <th>Produk</th>
                        <th class="d-none d-md-table-cell">Kategori</th>
                        <th class="text-end">Harga</th>
                        <th class="d-none d-sm-table-cell">Komposisi</th>
                        <th>Di {{ $tujuan->nama_cabang }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($produk as $p)
                    @php $sudahAda = isset($kodeTujuan[app(\App\Services\AmbilProdukCabangService::class)->kunci($p->kode_item)]); @endphp
                    <tr class="{{ $sudahAda ? 'text-muted' : '' }}">
                        <td>
                            <input class="form-check-input pilih-produk" type="checkbox" name="produk[]" value="{{ $p->id }}" id="p{{ $p->id }}" @disabled($sudahAda)>
                        </td>
                        <td><label for="p{{ $p->id }}" class="mb-0">
                            <div class="fw-semibold small">{{ $p->nama_item }}</div>
                            <div class="text-muted" style="font-size:0.75rem">{{ $p->kode_item }}@if($p->punya_varian) · <span class="badge bg-info-subtle text-info">Varian</span>@endif</div>
                        </label></td>
                        <td class="d-none d-md-table-cell small">{{ $p->category?->nama_kategori ?? '-' }}</td>
                        <td class="text-end small">Rp {{ number_format($p->harga_jual ?? 0, 0, ',', '.') }}</td>
                        <td class="d-none d-sm-table-cell small">{{ $p->resep ? $p->resep->items_count . ' bahan' : 'Tanpa resep' }}</td>
                        <td>
                            @if($sudahAda)
                                <span class="badge bg-secondary">Sudah ada</span>
                            @else
                                <span class="badge bg-success-subtle text-success">Belum ada</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Cabang ini belum punya produk jual.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($produk->isNotEmpty())
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted"><span id="jumlahDipilih">0</span> produk dipilih. Bahan komposisi diperiksa di langkah berikutnya.</span>
            <button type="submit" class="btn btn-primary btn-sm" id="btnLanjut" disabled>
                Lanjut ke Pemeriksaan <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
        @endif
    </div>
</form>
@endif

@endsection

@push('scripts')
<script>
(function () {
    const semua = document.getElementById('pilihSemua');
    const kotak = () => Array.from(document.querySelectorAll('.pilih-produk:not(:disabled)'));
    function segarkan() {
        const n = kotak().filter(c => c.checked).length;
        const label = document.getElementById('jumlahDipilih');
        if (label) label.textContent = n;
        const btn = document.getElementById('btnLanjut');
        if (btn) btn.disabled = n === 0;
    }
    semua?.addEventListener('change', () => { kotak().forEach(c => c.checked = semua.checked); segarkan(); });
    document.querySelectorAll('.pilih-produk').forEach(c => c.addEventListener('change', segarkan));
    segarkan();
})();
</script>
@endpush
