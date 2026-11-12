@extends('layouts.app')

@section('title', 'Laporan Kerugian Stok')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $ang = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
    $t = $data['total'];
    $q = fn ($extra = []) => array_merge(['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(), 'lokasi' => $pilih, 'tab' => $tab], $extra);
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-graph-down-arrow me-2 text-danger"></i>Laporan Kerugian Stok</h5>
    <div class="d-flex gap-2 flex-wrap">
        <x-panduan-button slug="laporan-kerugian-stok" />
        <a href="{{ route('stok-opname.laporan', $q(['excel' => 1])) }}" class="btn btn-sm btn-outline-success" data-uji="excel"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        <a href="{{ route('stok-opname.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-check"></i> Stok Opname</a>
    </div>
</div>

<form method="GET" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="col-6 col-md-3"><label class="form-label small mb-0" for="dari">Dari</label><input type="date" name="dari" id="dari" class="form-control form-control-sm" value="{{ $dari->toDateString() }}"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-0" for="sampai">Sampai</label><input type="date" name="sampai" id="sampai" class="form-control form-control-sm" value="{{ $sampai->toDateString() }}"></div>
    <div class="col-12 col-md-4"><label class="form-label small mb-0" for="lokasi">Lokasi</label>
        <select name="lokasi" id="lokasi" class="form-select form-select-sm" data-uji="lokasi">
            @php
                $adaMitra = $terlihat->contains(fn ($l) => \App\Services\StokOpnameService::lokasiMitra($l));
                $adaBaba = $terlihat->contains(fn ($l) => ! \App\Services\StokOpnameService::lokasiMitra($l));
            @endphp
            @if($adaBaba)<option value="">Semua lokasi BABA</option>@endif
            @foreach($terlihat as $l)<option value="{{ $l->id }}" @selected($pilih === (string) $l->id || (! $adaBaba && $terlihat->count() === 1))>{{ $l->nama_cabang }}@if(\App\Services\StokOpnameService::lokasiMitra($l)) (toko mitra)@endif</option>@endforeach
            @if($adaMitra && $adaBaba)<option value="semua" @selected($pilih === 'semua')>Semua lokasi + toko mitra</option>@endif
        </select></div>
    <div class="col-12 col-md-2"><button class="btn btn-sm btn-primary w-100">Tampilkan</button></div>
</form>

<div class="row g-2 mb-3">
    @foreach([['Opname disetujui', $t['opname'], ''], ['Akurasi rata-rata', $t['akurasi'] . '%', 'fw-bold'], ['Nilai kurang', $rp($t['nilai_kurang']), 'text-danger'], ['Nilai lebih', $rp($t['nilai_lebih']), 'text-primary'],
        ['Selisih bersih opname', $rp($t['bersih']), $t['bersih'] < 0 ? 'text-danger' : ''], ['Kerugian penyesuaian manual', $rp($t['manual_rugi']), 'text-danger'], ['Dipulihkan dari stok rusak', $rp($t['dipulihkan']), 'text-success']] as [$l, $v, $c])
    <div class="col-6 col-md-4 col-xl-2"><div class="card h-100"><div class="card-body p-2"><div class="small text-muted">{{ $l }}</div><div class="fw-semibold {{ $c }}" data-uji="total-{{ \Illuminate\Support\Str::slug($l) }}">{{ $v }}</div></div></div></div>
    @endforeach
</div>

<div class="d-flex flex-wrap gap-1 mb-2">
    @foreach(\App\Http\Controllers\StokOpnameController::TAB_LAPORAN as $k => $label)
    <a href="{{ route('stok-opname.laporan', $q(['tab' => $k])) }}" class="btn btn-sm {{ $tab === $k ? 'btn-dark' : 'btn-outline-secondary' }}" data-uji="tab-{{ $k }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card"><div class="card-body p-2 table-responsive">
@if($tab === 'ringkasan')
    <div class="fw-semibold small mb-1">Per penyebab</div>
    <table class="table table-sm small mb-3" data-uji="per-penyebab">
        <thead><tr><th>Penyebab</th><th>Pos keuangan</th><th class="text-end">Barang</th><th>Jumlah</th><th class="text-end">Nilai</th></tr></thead>
        <tbody>
        @foreach($data['per_penyebab'] as $k => $p)
            <tr class="{{ $p['nilai'] > 0 ? '' : 'text-muted' }}"><td>{{ $p['label'] }}</td><td>{{ $p['akun'] }}</td><td class="text-end">{{ $p['barang'] }}</td>
                <td>{{ collect($p['qty'])->map(fn ($v, $s) => $ang($v) . ' ' . $s)->implode(', ') ?: '-' }}</td><td class="text-end {{ $k === 'lebih' ? 'text-primary' : ($p['nilai'] > 0 ? 'text-danger' : '') }}">{{ $rp($p['nilai']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="fw-semibold small mb-1">Per lokasi</div>
    <table class="table table-sm small mb-0" data-uji="per-lokasi">
        <thead><tr><th>Lokasi</th><th class="text-end">Opname</th><th class="text-end">Akurasi</th><th class="text-end">Kurang</th><th class="text-end">Lebih</th><th class="text-end">Bersih</th></tr></thead>
        <tbody>
        @forelse($data['per_lokasi'] as $l)
            <tr><td>{{ $l['lokasi'] }}</td><td class="text-end">{{ $l['opname'] }}</td><td class="text-end">{{ $l['akurasi'] }}%</td><td class="text-end text-danger">{{ $rp($l['nilai_kurang']) }}</td>
                <td class="text-end text-primary">{{ $rp($l['nilai_lebih']) }}</td><td class="text-end fw-semibold">{{ $rp($l['bersih']) }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada opname yang disetujui pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
@elseif($tab === 'barang')
    <table class="table table-sm small mb-0" data-uji="per-barang">
        <thead><tr><th>Barang</th><th>Lokasi</th><th class="text-end">Kurang</th><th class="text-end">Nilai</th><th class="text-end">Kali</th><th>Penyebab</th></tr></thead>
        <tbody>
        @forelse($data['per_barang'] as $b)
            <tr><td>{{ $b['nama'] }} <span class="text-muted">· {{ $b['kode'] }}</span></td><td>{{ $b['lokasi'] }}</td><td class="text-end">{{ $ang($b['qty']) }} {{ $b['satuan'] }}</td>
                <td class="text-end text-danger">{{ $rp($b['nilai']) }}</td><td class="text-end">{{ $b['kali'] }}</td><td>{{ $b['penyebab'] }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada barang yang kurang pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
@elseif($tab === 'riwayat')
    <table class="table table-sm small mb-0" data-uji="riwayat">
        <thead><tr><th>Nomor</th><th>Lokasi</th><th>Status</th><th>Dimulai</th><th class="text-end">Barang</th><th class="text-end">Akurasi</th><th class="text-end">Kurang</th><th class="text-end">Lebih</th></tr></thead>
        <tbody>
        @forelse($data['riwayat'] as $o)
            <tr><td><a href="{{ route('stok-opname.show', $o) }}">{{ $o->nomor }}</a></td><td>{{ $o->cabang?->nama_cabang }}</td><td><span class="badge bg-{{ $o->status_warna }}">{{ $o->status_label }}</span></td>
                <td>{{ $o->created_at->format('d/m/Y') }}</td><td class="text-end">{{ $o->item_count }}</td>
                <td class="text-end">{{ isset($o->ringkasan['akurasi']) ? $o->ringkasan['akurasi'] . '%' : '–' }}</td>
                <td class="text-end text-danger">{{ isset($o->ringkasan['nilai_kurang']) ? $rp($o->ringkasan['nilai_kurang']) : '–' }}</td>
                <td class="text-end text-primary">{{ isset($o->ringkasan['nilai_lebih']) ? $rp($o->ringkasan['nilai_lebih']) : '–' }}</td></tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada opname pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
@elseif($tab === 'rusak')
    <div class="small mb-1"><span class="fw-semibold">Masih ditahan sekarang</span> · nilai pokok <span class="text-danger" data-uji="rusak-ditahan">{{ $rp($data['rusak']['nilai_ditahan']) }}</span></div>
    <table class="table table-sm small mb-3" data-uji="rusak">
        <thead><tr><th>Barang</th><th>Lokasi</th><th class="text-end">Sisa</th><th class="text-end">Nilai</th><th class="text-end">Hari</th><th>Asal</th></tr></thead>
        <tbody>
        @forelse($data['rusak']['ditahan'] as $r)
            <tr><td>{{ $r['nama'] }}</td><td>{{ $r['lokasi'] }}</td><td class="text-end">{{ $ang($r['qty']) }} {{ $r['satuan'] }}</td><td class="text-end">{{ $rp($r['nilai']) }}</td>
                <td class="text-end {{ $r['umur'] > 30 ? 'text-danger fw-semibold' : '' }}">{{ $r['umur'] }}</td><td>{{ $r['opname'] }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada stok rusak yang masih ditahan.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="fw-semibold small mb-1">Tindak lanjut dalam periode</div>
    <table class="table table-sm small mb-0" data-uji="rusak-jenis">
        <thead><tr><th>Tindak lanjut</th><th class="text-end">Kali</th><th class="text-end">Nilai pokok</th><th class="text-end">Dipulihkan</th></tr></thead>
        <tbody>
        @foreach($data['rusak']['per_jenis'] as $j)
            <tr class="{{ $j['kali'] ? '' : 'text-muted' }}"><td>{{ $j['label'] }}</td><td class="text-end">{{ $j['kali'] }}</td><td class="text-end">{{ $rp($j['nilai_pokok']) }}</td><td class="text-end text-success">{{ $rp($j['nilai_pulih']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
@else
    <div class="small text-muted mb-2">Dari menu <strong>Stok Barang › Penyesuaian Stok</strong> (per barang, di luar opname).</div>
    <table class="table table-sm small mb-0" data-uji="manual">
        <thead><tr><th>Lokasi</th><th>Pos</th><th class="text-end">Transaksi</th><th class="text-end">Nilai</th></tr></thead>
        <tbody>
        @forelse($data['manual'] as $m)
            <tr><td>{{ $m['lokasi'] }}</td><td>{{ $m['nama'] }}</td><td class="text-end">{{ $m['jumlah'] }}</td><td class="text-end {{ $m['kode'] === 'KOR-STOK' ? 'text-primary' : 'text-danger' }}">{{ $rp($m['nilai']) }}</td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada penyesuaian manual pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
@endif
</div></div>
@endsection
