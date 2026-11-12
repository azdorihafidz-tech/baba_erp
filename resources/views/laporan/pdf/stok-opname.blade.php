@extends('laporan.pdf.layout')

@push('pdf-styles')
<style>
    .info td { padding: 2px 6px; font-size: 10px; }
    .info td.l { color: #64748b; width: 110px; }
    .cap { position: fixed; top: 280px; left: 0; right: 0; text-align: center; font-size: 60px; color: rgba(220, 38, 38, .12); transform: rotate(-20deg); font-weight: bold; }
    .ttd td { width: 33%; text-align: center; vertical-align: top; font-size: 10px; padding-top: 6px; }
    .ttd .garis { margin: 46px 18px 0; border-top: 1px solid #334155; padding-top: 3px; }
    .foto td { width: 33%; text-align: center; vertical-align: top; padding: 4px; font-size: 9px; }
    .foto img { max-width: 170px; max-height: 130px; }
    .neg { color: #b91c1c; } .pos { color: #1d4ed8; }
    table.data th.text-end { text-align: right; }
    h4 { font-size: 11px; margin: 8px 0 4px; }
</style>
@endpush

@section('content')
@php
    $o = $ba['o']; $rg = $ba['ringkasan'];
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $ang = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
@endphp
@if(! $ba['final'])<div class="cap">SEMENTARA</div>@endif

<table class="layout info" style="margin-bottom:8px">
    <tr><td class="l">Nomor</td><td><strong>{{ $o->nomor }}</strong> — {{ $o->status_label }}</td><td class="l">Lokasi</td><td>{{ $o->cabang?->nama_cabang }}</td></tr>
    <tr><td class="l">Cakupan</td><td>{{ \App\Models\StokOpname::CAKUPAN[$o->cakupan] ?? $o->cakupan }}@if($o->cakupan_ket) — {{ \Illuminate\Support\Str::limit($o->cakupan_ket, 90) }}@endif</td>
        <td class="l">Hitung buta</td><td>{{ $o->hitung_buta ? 'Ya' : 'Tidak' }}</td></tr>
    <tr><td class="l">Dimulai</td><td>{{ $o->created_at?->format('d/m/Y H:i') }} · {{ $o->pembuat?->name }}</td>
        <td class="l">Disetujui</td><td>{{ $o->disetujui_at ? $o->disetujui_at->format('d/m/Y H:i') . ' · ' . $o->penyetuju?->name : 'belum' }}</td></tr>
    <tr><td class="l">Penghitung</td><td colspan="3">{{ implode(', ', $ba['penghitung']) ?: '-' }}</td></tr>
</table>

<table class="data">
    <thead><tr><th>Barang</th><th>Satuan</th><th class="text-end">Sistem</th><th class="text-end">Fisik</th><th class="text-end">Selisih</th><th class="text-end">Harga pokok</th><th class="text-end">Nilai</th><th>Penyebab</th></tr></thead>
    <tbody>
    @foreach($ba['baris'] as $b)
        <tr @class(['highlight-danger' => ($b['selisih'] ?? 0) < 0, 'highlight-warning' => $b['selisih'] === null])>
            <td>{{ $b['nama'] }}<br><span style="color:#94a3b8">{{ $b['kode'] }}</span></td>
            <td>{{ $b['satuan'] }}</td>
            <td class="num">{{ $ang($b['sistem']) }}</td>
            <td class="num">{{ $ang($b['fisik']) }}</td>
            <td class="num {{ ($b['selisih'] ?? 0) < 0 ? 'neg' : (($b['selisih'] ?? 0) > 0 ? 'pos' : '') }}">{{ $b['selisih'] === null ? 'belum dihitung' : (($b['selisih'] > 0 ? '+' : '') . $ang($b['selisih'])) }}</td>
            <td class="num">{{ $rp($b['harga']) }}</td>
            <td class="num">{{ $b['nilai'] === null ? '–' : $rp($b['nilai']) }}</td>
            <td>{{ $b['penyebab'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="layout"><tr>
<td style="width:48%;vertical-align:top">
    <h4>Ringkasan</h4>
    <table class="data">
        <tr><td>Barang dihitung</td><td class="num">{{ $rg['dihitung'] }}@if($rg['belum']) (+{{ $rg['belum'] }} belum)@endif</td></tr>
        <tr><td>Cocok / kurang / lebih</td><td class="num">{{ $rg['cocok'] }} / {{ $rg['kurang'] }} / {{ $rg['lebih'] }}</td></tr>
        <tr><td>Nilai kurang</td><td class="num neg">{{ $rp($rg['nilai_kurang']) }}</td></tr>
        <tr><td>Nilai lebih</td><td class="num pos">{{ $rp($rg['nilai_lebih']) }}</td></tr>
        <tr class="subtotal"><td>Selisih bersih</td><td class="num">{{ $rp($rg['nilai_bersih']) }}</td></tr>
        <tr class="grand"><td>Akurasi stok</td><td class="num">{{ $rg['akurasi'] }}%</td></tr>
    </table>
</td>
<td style="width:4%"></td>
<td style="width:48%;vertical-align:top">
    <h4>Per penyebab (dicatat di keuangan, tanpa kas)</h4>
    <table class="data">
        @forelse($ba['per_penyebab'] as $k => $p)
        <tr><td>{{ \App\Services\StokOpnameService::PENYEBAB[$k]['label'] ?? $k }}<br><span style="color:#94a3b8">{{ \App\Services\StokOpnameService::PENYEBAB[$k]['coa'] ?? '' }} {{ \App\Services\StokOpnameService::PENYEBAB[$k]['nama'] ?? '' }}</span></td><td class="num">{{ $rp($p['nilai']) }}</td></tr>
        @empty
        <tr><td class="empty-note">Tidak ada selisih.</td></tr>
        @endforelse
    </table>
    <div style="font-size:9px;color:#64748b">Nilai = harga pokok (harga beli FIFO), bukan harga jual.@if(! $ba['final']) Angka sementara memakai harga pokok rata-rata saat ini.@endif</div>
</td>
</tr></table>

<table class="layout ttd" style="margin-top:14px">
    <tr><td>Penghitung</td><td>Pemeriksa / Pengaju</td><td>Disetujui</td></tr>
    <tr><td><div class="garis">{{ implode(', ', $ba['penghitung']) ?: '(....................)' }}</div></td>
        <td><div class="garis">{{ $o->pengaju?->name ?? '(....................)' }}</div></td>
        <td><div class="garis">{{ $o->penyetuju?->name ?? '(....................)' }}</div></td></tr>
</table>

@if($ba['foto'])
<div style="page-break-before: always"></div>
<h4>Lampiran foto bukti</h4>
<table class="layout foto">
@foreach(array_chunk(array_slice($ba['foto'], 0, 24), 3) as $tiga)
    <tr>
    @foreach($tiga as $f)
        @php $isi = \Illuminate\Support\Facades\Storage::disk('local')->exists($f['path']) ? \Illuminate\Support\Facades\Storage::disk('local')->get($f['path']) : null; @endphp
        <td>@if($isi)<img src="data:{{ (new \finfo(FILEINFO_MIME_TYPE))->buffer($isi) }};base64,{{ base64_encode($isi) }}">@else<span class="empty-note">foto tidak ada</span>@endif
            <div>{{ $f['barang'] }} — {{ $f['penyebab'] }} {{ $f['qty'] }}</div></td>
    @endforeach
    </tr>
@endforeach
</table>
@endif
@endsection
