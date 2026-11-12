@extends('layouts.app')

@section('title', 'Laporan Belanja Online')

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $q = fn (array $ubah = []) => route('toko-online.laporan', array_merge(['jenis' => $jenis, 'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(), 'level' => $level, 'gudang' => $gudangId], $ubah));
    $pakaiTanggal = $jenis !== 'tagihan';
    $pakaiGudang = ! in_array($jenis, ['ro', 'ro_tim'], true);
    $pakaiLevel = in_array($jenis, ['tim', 'ro_tim'], true);
@endphp

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Laporan Belanja Online</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="laporan-belanja-online" />
        @unless($galat)
        <a href="{{ $q(['ekspor' => 'excel']) }}" class="btn btn-outline-success btn-sm" data-uji="ekspor-excel"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        @endunless
    </div>
</div>

<div class="d-flex flex-wrap gap-1 mb-2" data-uji="jenis-laporan">
    @foreach(\App\Http\Controllers\LaporanOnlineController::JENIS as $k => $label)
    <a href="{{ $q(['jenis' => $k]) }}" class="btn btn-sm {{ $jenis === $k ? 'btn-dark' : 'btn-outline-secondary' }}" data-uji="jenis-{{ $k }}">{{ $label }}</a>
    @endforeach
</div>

<form method="GET" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="jenis" value="{{ $jenis }}">
    @if($pakaiTanggal)
    <div class="col-6 col-md-auto"><label class="form-label small mb-0">Dari</label><input type="date" name="dari" value="{{ $dari->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-auto"><label class="form-label small mb-0">Sampai</label><input type="date" name="sampai" value="{{ $sampai->toDateString() }}" class="form-control form-control-sm"></div>
    @endif
    @if($pakaiLevel)
    <div class="col-12 col-md-auto"><label class="form-label small mb-0">Tim menurut <x-tooltip key="laporan_online.tim" /></label>
        <select name="level" class="form-select form-select-sm" data-uji="level-tim">
            @foreach(\App\Services\LaporanOnlineService::LEVEL_TIM as $k => $label)<option value="{{ $k }}" @selected($level === $k)>{{ $label }}</option>@endforeach
        </select></div>
    @endif
    @if($pakaiGudang && $gudangList->count() > 1)
    <div class="col-12 col-md-auto"><label class="form-label small mb-0">Gudang</label>
        <select name="gudang" class="form-select form-select-sm"><option value="">Semua gudang</option>
            @foreach($gudangList as $g)<option value="{{ $g->id }}" @selected($gudangId === $g->id)>{{ $g->nama_cabang }}</option>@endforeach
        </select></div>
    @endif
    <div class="col-12 col-md-auto"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Tampilkan</button></div>
</form>

@if($galat)
<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>{{ $galat }}</div>
@endif

@if($ringkas)
<div class="row g-2 mb-3" data-uji="ringkas">
    @foreach([['Omzet produk', $rp($ringkas['omzet'])], ['HPP', $rp($ringkas['hpp'])], ['Laba kotor', $rp($ringkas['laba'])], ['Botol terjual', $ringkas['botol']], ['Pesanan', $ringkas['pesanan']], ['Ongkir', $rp($ringkas['ongkir'])]] as [$l, $v])
    <div class="col-6 col-md-2"><div class="border rounded p-2 h-100"><div class="text-muted small">{{ $l }}</div><div class="fw-bold">{{ $v }}</div></div></div>
    @endforeach
</div>
@endif

<div class="alert alert-light border small py-2"><i class="bi bi-info-circle me-1"></i>
@switch($jenis)
    @case('ro') @case('ro_tim') RO = jumlah botol dari belanja online (gudang & upline) sesuai Mode RO; dikurangi retur / batal. Menurut tanggal catatan RO. @break
    @case('tagihan') Pesanan gudang yang dibayar dengan tagihan dan belum lunas. Merah = lewat jatuh tempo. @break
    @default Penjualan gudang yang barangnya sudah keluar (dikirim / siap diambil) dalam periode, bersih setelah retur. HPP = modal FIFO saat barang keluar.
@endswitch
@if($pakaiLevel) Tim = upline ber-jabatan {{ \App\Services\LaporanOnlineService::LEVEL_TIM[$level] }} menurut struktur aplikasi member saat ini. @endif
</div>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0 small" data-uji="tabel-laporan">
@if(in_array($jenis, ['produk', 'cabang', 'jabatan', 'tim'], true))
    <thead><tr><th>{{ ['produk' => 'Produk', 'cabang' => 'Cabang member', 'jabatan' => 'Jabatan pembeli', 'tim' => 'Tim'][$jenis] }}</th>
        <th class="text-end">Botol</th><th class="text-end d-none d-md-table-cell">Pesanan</th><th class="text-end">Omzet</th>
        <th class="text-end d-none d-md-table-cell">HPP</th><th class="text-end">Laba</th><th class="text-end d-none d-md-table-cell">Margin</th></tr></thead>
    <tbody>
    @forelse($baris as $r)
    <tr data-uji="baris"><td>{{ $r['nama'] }}<div class="text-muted d-md-none">{{ $r['pesanan'] }} pesanan · HPP {{ $rp($r['hpp']) }}</div></td>
        <td class="text-end">{{ $r['botol'] }}</td><td class="text-end d-none d-md-table-cell">{{ $r['pesanan'] }}</td><td class="text-end text-nowrap">{{ $rp($r['omzet']) }}</td>
        <td class="text-end d-none d-md-table-cell text-nowrap">{{ $rp($r['hpp']) }}</td><td class="text-end text-nowrap fw-semibold">{{ $rp($r['laba']) }}</td>
        <td class="text-end d-none d-md-table-cell">{{ $r['margin'] !== null ? $r['margin'] . '%' : '—' }}</td></tr>
    @empty
    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada penjualan online pada periode ini.</td></tr>
    @endforelse
    </tbody>
@elseif($jenis === 'ro')
    <thead><tr><th>Member</th><th class="d-none d-md-table-cell">Jabatan</th><th class="d-none d-md-table-cell">Cabang</th><th class="text-end">RO +</th><th class="text-end">RO −</th><th class="text-end">Bersih</th></tr></thead>
    <tbody>
    @forelse($baris as $r)
    <tr data-uji="baris"><td>{{ $r['nama'] }}<div class="text-muted">{{ trim(($r['no_id'] ?? '') . ' · ' . ($r['no_member'] ?? ''), ' ·') }}<span class="d-md-none"> · {{ $r['jabatan'] }}</span></div>
        @if($r['tertunda'])<span class="badge bg-warning text-dark">{{ $r['tertunda'] }} belum terkirim</span>@endif</td>
        <td class="d-none d-md-table-cell">{{ $r['jabatan'] }}</td><td class="d-none d-md-table-cell">{{ $r['cabang'] }}</td>
        <td class="text-end text-success">{{ $r['tambah'] }}</td><td class="text-end text-danger">{{ $r['kurang'] }}</td><td class="text-end fw-bold">{{ $r['bersih'] }}</td></tr>
    @empty
    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada RO dari belanja online pada periode ini.</td></tr>
    @endforelse
    </tbody>
@elseif($jenis === 'ro_tim')
    <thead><tr><th>Tim</th><th class="text-end">Anggota</th><th class="text-end d-none d-md-table-cell">RO +</th><th class="text-end d-none d-md-table-cell">RO −</th><th class="text-end">RO bersih</th></tr></thead>
    <tbody>
    @forelse($baris as $r)
    <tr data-uji="baris"><td>{{ $r['nama'] }}</td><td class="text-end">{{ $r['anggota'] }}</td><td class="text-end d-none d-md-table-cell">{{ $r['tambah'] }}</td>
        <td class="text-end d-none d-md-table-cell">{{ $r['kurang'] }}</td><td class="text-end fw-bold">{{ $r['bersih'] }}</td></tr>
    @empty
    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada RO dari belanja online pada periode ini.</td></tr>
    @endforelse
    </tbody>
@else
    <thead><tr><th>Pesanan</th><th>Member</th><th class="d-none d-md-table-cell">Jatuh tempo</th><th class="text-end">Sisa</th></tr></thead>
    <tbody>
    @forelse($baris as $r)
    <tr data-uji="baris" class="{{ $r['telat'] ? 'table-danger' : '' }}">
        <td><a href="{{ route('toko-online.pesanan.show', $r['id']) }}">{{ $r['nomor'] }}</a><div class="text-muted">{{ $r['status'] }}{{ $r['barang_keluar'] ? '' : ' · barang belum keluar' }}</div></td>
        <td>{{ $r['nama'] }}<div class="text-muted">{{ $r['no_id'] }}{{ $r['jabatan'] ? ' · ' . $r['jabatan'] : '' }}</div></td>
        <td class="d-none d-md-table-cell">{{ $r['jatuh_tempo']?->format('d/m/Y') ?? '—' }}</td>
        <td class="text-end text-nowrap fw-semibold">{{ $rp($r['sisa']) }}
            <div class="small {{ $r['telat'] ? 'text-danger fw-bold' : 'text-muted' }}">{{ $r['telat'] ? 'Terlambat ' . $r['telat'] . ' hari' : ($r['sisa_hari'] !== null ? ($r['sisa_hari'] === 0 ? 'Jatuh tempo hari ini' : $r['sisa_hari'] . ' hari lagi') : '') }}</div></td>
    </tr>
    @empty
    <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada tagihan member yang belum lunas.</td></tr>
    @endforelse
    @if($baris->count())
    <tr class="table-light fw-bold"><td colspan="2">Total tagihan</td><td class="d-none d-md-table-cell"></td><td class="text-end text-nowrap" data-uji="total-tagihan">{{ $rp($baris->sum('sisa')) }}</td></tr>
    @endif
    </tbody>
@endif
</table>
</div></div>
@endsection
