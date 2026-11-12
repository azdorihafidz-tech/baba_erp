@extends('laporan.pdf.layout')

@push('pdf-styles')
<style>
    .info td { padding: 3px 6px; font-size: 11px; }
    .info td.l { color: #64748b; width: 150px; }
    .ttd td { width: 50%; text-align: center; font-size: 10px; padding-top: 6px; }
    .ttd .garis { margin: 50px 30px 0; border-top: 1px solid #334155; padding-top: 3px; }
</style>
@endpush

@section('content')
@php $r = $t->stokRusak; $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); $ang = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ','); @endphp
<p style="font-size:11px;margin-bottom:8px">Pada tanggal {{ $t->created_at?->translatedFormat('d F Y') }} telah dimusnahkan barang rusak berikut:</p>
<table class="layout info">
    <tr><td class="l">Barang</td><td><strong>{{ $r->barang?->nama_item }}</strong> ({{ $r->barang?->kode_item }})</td></tr>
    <tr><td class="l">Lokasi</td><td>{{ $r->cabang?->nama_cabang }}</td></tr>
    <tr><td class="l">Jumlah dimusnahkan</td><td>{{ $ang($t->qty) }} {{ $r->satuan }}</td></tr>
    <tr><td class="l">Nilai pokok</td><td>{{ $rp($t->nilai_pokok) }} (kerugian sudah dicatat saat stok opname disetujui)</td></tr>
    <tr><td class="l">Asal</td><td>Stok opname {{ $r->opname?->nomor ?? '-' }}</td></tr>
    <tr><td class="l">Keterangan</td><td>{{ $t->catatan ?: '-' }}</td></tr>
    <tr><td class="l">Dicatat oleh</td><td>{{ $t->user?->name }}</td></tr>
</table>
@if($fotoData)
<div style="margin:10px 0;text-align:center"><img src="{{ $fotoData }}" style="max-width:320px;max-height:240px"><div style="font-size:9px;color:#64748b">Foto bukti pemusnahan</div></div>
@endif
<table class="layout ttd" style="margin-top:16px">
    <tr><td>Yang memusnahkan</td><td>Saksi / Penanggung jawab lokasi</td></tr>
    <tr><td><div class="garis">{{ $t->user?->name }}</div></td><td><div class="garis">(....................)</div></td></tr>
</table>
@endsection
