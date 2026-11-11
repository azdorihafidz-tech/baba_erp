@extends('layouts.app')

@section('title', 'Cek Antrian')

@push('styles')
<style>
.status-badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700; letter-spacing: 0.03em;
}
.status-menunggu   { background: #e2e8f0; color: #475569; }
.status-dikerjakan { background: #fef3c7; color: #92400e; }
.status-selesai    { background: #dcfce7; color: #166534; }
.status-disimpan   { background: #dbeafe; color: #1e40af; }
.status-diambil    { background: #f1f5f9; color: #64748b; }
.antrian-num-lg { font-size: 2rem; font-weight: 900; line-height: 1; min-width: 56px; text-align: center; }
.result-card { border-left: 4px solid #e2e8f0; transition: border-color 0.15s; }
.result-card.status-menunggu   { border-left-color: #94a3b8; }
.result-card.status-dikerjakan { border-left-color: #f59e0b; }
.result-card.status-selesai    { border-left-color: #22c55e; }
.result-card.status-disimpan   { border-left-color: #3b82f6; }
.result-card.status-diambil    { border-left-color: #cbd5e1; opacity: 0.75; }
#searchResults .spinner-wrapper { padding: 2rem; text-align: center; color: #94a3b8; }
#noResult { display: none; }
</style>
@endpush

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-search me-2 text-info"></i>Cek Antrian</h5>
        <small class="text-muted">Cari status antrian pelanggan hari ini</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('antrian.kelola')
        <a href="{{ route('antrian.operator') }}" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-list-ol me-1"></i>Kelola Antrian
        </a>
        @endcan
        <x-panduan-button slug="antrian-cek" />
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

{{-- Form Pencarian --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-5 col-md-4">
                <label class="form-label small fw-medium mb-1">Cari Nomor / Nama / Order</label>
                <input type="text" id="searchInput" class="form-control"
                    placeholder="Nomor antrian, nama, atau #order..."
                    autocomplete="off">
            </div>
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label small fw-medium mb-1">Tanggal</label>
                <input type="date" id="searchDate" class="form-control"
                    value="{{ today()->toDateString() }}">
            </div>
            <div class="col-12 col-sm-3 col-md-2">
                <button type="button" id="btnCari" class="btn btn-primary w-100" onclick="doSearch()">
                    <i class="bi bi-search me-1"></i>Cari
                </button>
            </div>
            <div class="col-12 col-md-3">
                <button type="button" class="btn btn-outline-secondary w-100 w-md-auto" onclick="resetSearch()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Tampilkan Semua Hari Ini
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Hasil Pencarian --}}
<div id="searchResults">
    {{-- Default: tampil semua order aktif hari ini --}}
    @if($orders->isEmpty())
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            Belum ada antrian hari ini.
        </div>
    </div>
    @else
    <div class="row g-2" id="resultContainer">
        @foreach($orders as $order)
        @php $st = $order->status_produksi?->value ?? 'diambil'; @endphp
        <div class="col-12">
            <div class="card result-card status-{{ $st }}">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="antrian-num-lg
                            @if($st==='menunggu') text-secondary
                            @elseif($st==='dikerjakan') text-warning
                            @elseif($st==='selesai') text-success
                            @elseif($st==='disimpan') text-info
                            @else text-muted @endif">
                            {{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}
                        </div>
                        <div class="flex-fill">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-bold">{{ $order->nama_pelanggan ?: 'Walk-in' }}</span>
                                <span class="status-badge status-{{ $st }}">{{ $order->status_produksi?->label() ?? 'Diambil' }}</span>
                            </div>
                            <div class="small text-muted d-flex flex-wrap gap-2">
                                <span><i class="bi bi-receipt me-1"></i>{{ $order->nomor_order }}</span>
                                @if($order->berat_daging_kg)
                                <span><i class="bi bi-boxes me-1"></i>{{ number_format($order->berat_daging_kg, 2) }} kg</span>
                                @endif
                                @if($order->lokasi_rak)
                                <span><i class="bi bi-archive me-1"></i>Rak: <strong>{{ $order->lokasi_rak }}</strong></span>
                                @endif
                                @if($order->dikerjakanOleh)
                                <span><i class="bi bi-person me-1"></i>{{ $order->dikerjakanOleh->nama_lengkap }}</span>
                                @endif
                                @if($order->waktu_mulai_kerja)
                                <span><i class="bi bi-clock me-1"></i>Mulai: {{ $order->waktu_mulai_kerja->setTimezone('Asia/Jakarta')->format('H:i') }}</span>
                                @endif
                                @if($order->waktu_selesai_kerja)
                                <span><i class="bi bi-check-circle me-1"></i>Selesai: {{ $order->waktu_selesai_kerja->setTimezone('Asia/Jakarta')->format('H:i') }}</span>
                                @endif
                                @if($order->waktu_disimpan)
                                <span><i class="bi bi-archive me-1"></i>Disimpan: {{ $order->waktu_disimpan->setTimezone('Asia/Jakarta')->format('H:i') }}</span>
                                @endif
                                @if($order->waktu_diambil)
                                <span><i class="bi bi-bag-check me-1"></i>Diambil: {{ $order->waktu_diambil->setTimezone('Asia/Jakarta')->format('H:i') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0 flex-wrap">
                            @if(in_array($st, ['selesai','disimpan']))
                            @can('antrian.lihat')
                            <form method="POST" action="{{ route('antrian.cek.diambil', $order) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm"
                                    onclick="return confirm('Konfirmasi pelanggan sudah ambil?')">
                                    <i class="bi bi-bag-check me-1"></i>
                                    <span class="d-none d-sm-inline">Diambil</span>
                                </button>
                            </form>
                            @endcan
                            @endif
                            <a href="{{ route('penjualan.struk', $order) }}"
                               class="btn btn-outline-secondary btn-sm" target="_blank">
                                <i class="bi bi-printer me-1"></i>
                                <span class="d-none d-sm-inline">Cetak</span>
                            </a>
                            <a href="{{ route('penjualan.show', $order) }}"
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>
                                <span class="d-none d-sm-inline">Detail</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

<div id="noResult" class="card" style="display:none">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-search fs-1 d-block mb-2"></i>
        Tidak ditemukan antrian yang cocok.
    </div>
</div>

@endsection

@push('scripts')
<script>
const SEARCH_URL = '{{ route('antrian.cek.search') }}';
const CSRF       = '{{ csrf_token() }}';

let searchTimer = null;

document.getElementById('searchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') doSearch();
});
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(doSearch, 500);
});
document.getElementById('searchDate').addEventListener('change', doSearch);

function resetSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchDate').value  = '{{ today()->toDateString() }}';
    doSearch();
}

async function doSearch() {
    const q       = document.getElementById('searchInput').value.trim();
    const tanggal = document.getElementById('searchDate').value;
    const btn     = document.getElementById('btnCari');

    btn.disabled = true;
    document.getElementById('searchResults').innerHTML =
        '<div class="spinner-wrapper"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Mencari...</div>';
    document.getElementById('noResult').style.display = 'none';

    try {
        const url = SEARCH_URL + '?q=' + encodeURIComponent(q) + '&tanggal=' + encodeURIComponent(tanggal);
        const resp = await fetch(url, {
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const data = await resp.json();
        renderResults(data);
    } catch (e) {
        document.getElementById('searchResults').innerHTML =
            '<div class="alert alert-danger">Gagal memuat data. Coba refresh halaman.</div>';
    } finally {
        btn.disabled = false;
    }
}

function statusBadge(status, label) {
    const cls = { menunggu:'status-menunggu', dikerjakan:'status-dikerjakan',
                  selesai:'status-selesai', disimpan:'status-disimpan', diambil:'status-diambil' };
    return `<span class="status-badge ${cls[status] || ''}">${escHtml(label || status)}</span>`;
}

function numColor(status) {
    return { menunggu:'text-secondary', dikerjakan:'text-warning',
             selesai:'text-success', disimpan:'text-info' }[status] || 'text-muted';
}

function renderResults(data) {
    const container = document.getElementById('searchResults');
    const noResult  = document.getElementById('noResult');

    if (!data || data.length === 0) {
        container.innerHTML = '';
        noResult.style.display = 'block';
        return;
    }

    noResult.style.display = 'none';

    const html = data.map(o => {
        const metaParts = [];
        if (o.nomor_order)   metaParts.push(`<span><i class="bi bi-receipt me-1"></i>${escHtml(o.nomor_order)}</span>`);
        if (o.berat)         metaParts.push(`<span><i class="bi bi-boxes me-1"></i>${escHtml(o.berat)}</span>`);
        if (o.lokasi_rak)    metaParts.push(`<span><i class="bi bi-archive me-1"></i>Rak: <strong>${escHtml(o.lokasi_rak)}</strong></span>`);
        if (o.operator)      metaParts.push(`<span><i class="bi bi-person me-1"></i>${escHtml(o.operator)}</span>`);
        if (o.mulai)         metaParts.push(`<span><i class="bi bi-clock me-1"></i>Mulai: ${escHtml(o.mulai)}</span>`);
        if (o.selesai)       metaParts.push(`<span><i class="bi bi-check-circle me-1"></i>Selesai: ${escHtml(o.selesai)}</span>`);
        if (o.disimpan_jam)  metaParts.push(`<span><i class="bi bi-archive me-1"></i>Disimpan: ${escHtml(o.disimpan_jam)}</span>`);
        if (o.diambil_jam)   metaParts.push(`<span><i class="bi bi-bag-check me-1"></i>Diambil: ${escHtml(o.diambil_jam)}</span>`);

        const diambilBtn = o.can_diambil
            ? `<form method="POST" action="${escHtml(o.url_diambil)}">
                 <input type="hidden" name="_token" value="${CSRF}">
                 <button type="submit" class="btn btn-success btn-sm"
                     onclick="return confirm('Konfirmasi pelanggan sudah ambil?')">
                     <i class="bi bi-bag-check me-1"></i><span class="d-none d-sm-inline">Diambil</span>
                 </button>
               </form>`
            : '';

        return `<div class="col-12">
            <div class="card result-card status-${escHtml(o.status)}">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="antrian-num-lg ${numColor(o.status)}">${escHtml(o.nomor_antrian)}</div>
                        <div class="flex-fill">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-bold">${escHtml(o.nama_pelanggan)}</span>
                                ${statusBadge(o.status, o.status_label)}
                            </div>
                            <div class="small text-muted d-flex flex-wrap gap-2">
                                ${metaParts.join('')}
                            </div>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0 flex-wrap">
                            ${diambilBtn}
                            <a href="${escHtml(o.url_struk)}" class="btn btn-outline-secondary btn-sm" target="_blank">
                                <i class="bi bi-printer me-1"></i><span class="d-none d-sm-inline">Cetak</span>
                            </a>
                            <a href="${escHtml(o.url_detail)}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i><span class="d-none d-sm-inline">Detail</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }).join('');

    container.innerHTML = `<div class="row g-2">${html}</div>`;
}

function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
@endpush
