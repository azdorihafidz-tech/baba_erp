@extends('laporan.pdf.layout')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $pj = $data['penjualan']; $lr = $data['laba_rugi']; $ak = $data['arus_kas'];
    $sk = $data['saldo_kas']; $mt = $data['mutasi']; $st = $data['stok'];
@endphp

@if(!empty($statusInfo))
<p style="font-size:9px;color:#64748b;margin-bottom:8px">{{ $statusInfo }}</p>
@endif

<table class="data">
    <thead><tr><th colspan="2">PENJUALAN</th><th colspan="2">LABA RUGI</th></tr></thead>
    <tbody>
        <tr><td>Jumlah transaksi</td><td class="num">{{ number_format($pj['jumlah_order'], 0, ',', '.') }}</td>
            <td>Omzet bersih</td><td class="num">{{ $rp($lr['omzet']) }}</td></tr>
        <tr><td>Omzet kotor</td><td class="num">{{ $rp($pj['omzet_kotor']) }}</td>
            <td>HPP</td><td class="num">− {{ $rp($lr['hpp']) }}</td></tr>
        @if(((float) ($lr['eliminasi_omzet'] ?? 0)) > 0)
        <tr><td colspan="2"></td><td colspan="2" style="font-size:8pt;color:#666">Kiriman Gudang → cabang tidak dihitung (omzet {{ $rp($lr['eliminasi_omzet']) }}, HPP {{ $rp($lr['eliminasi_hpp']) }})</td></tr>
        @endif
        <tr><td>Diskon</td><td class="num">− {{ $rp($pj['diskon']) }}</td>
            <td><strong>Laba kotor</strong></td><td class="num"><strong>{{ $rp($lr['laba_kotor']) }}</strong></td></tr>
        <tr><td>Service charge + take away fee</td><td class="num">{{ $rp($pj['service_charge'] + $pj['take_away_fee']) }}</td>
            <td>Pendapatan lain</td><td class="num">{{ $rp($lr['total_pendapatan_lain']) }}</td></tr>
        <tr><td><strong>Omzet bersih</strong></td><td class="num"><strong>{{ $rp($pj['omzet_bersih']) }}</strong></td>
            <td>Beban operasional</td><td class="num">− {{ $rp($lr['total_beban']) }}</td></tr>
        <tr class="grand"><td colspan="2"></td><td>LABA BERSIH</td><td class="num">{{ $rp($lr['laba_bersih']) }}</td></tr>
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Metode Bayar</th><th style="text-align:right">Total</th><th>Tipe Transaksi</th><th style="text-align:right">Total</th></tr></thead>
    <tbody>
        @php $n = max(count($pj['per_metode']), count($pj['per_tipe']), 1); @endphp
        @for($i = 0; $i < $n; $i++)
        <tr>
            <td>{{ $pj['per_metode'][$i]['label'] ?? '' }}</td>
            <td class="num">{{ isset($pj['per_metode'][$i]) ? $rp($pj['per_metode'][$i]['total']) : '' }}</td>
            <td>{{ isset($pj['per_tipe'][$i]) ? $pj['per_tipe'][$i]['label'] . ' (' . $pj['per_tipe'][$i]['jumlah_order'] . ')' : '' }}</td>
            <td class="num">{{ isset($pj['per_tipe'][$i]) ? $rp($pj['per_tipe'][$i]['total']) : '' }}</td>
        </tr>
        @endfor
    </tbody>
</table>

@if(!empty($lr['beban']) || !empty($lr['pendapatan_lain']))
<table class="data">
    <thead><tr><th>Rincian Pendapatan Lain & Beban</th><th style="text-align:right">Jumlah</th></tr></thead>
    <tbody>
        @foreach($lr['pendapatan_lain'] as $r)<tr><td>+ {{ $r['label'] }}</td><td class="num">{{ $rp($r['total']) }}</td></tr>@endforeach
        @foreach($lr['beban'] as $r)<tr><td>− {{ $r['label'] }}</td><td class="num">{{ $rp($r['total']) }}</td></tr>@endforeach
    </tbody>
</table>
@endif

<table class="data">
    <thead><tr><th>ARUS KAS — Pemasukan</th><th style="text-align:right">Jumlah</th><th>Pengeluaran</th><th style="text-align:right">Jumlah</th></tr></thead>
    <tbody>
        @php $n = max(count($ak['pemasukan']), count($ak['pengeluaran']), 1); @endphp
        @for($i = 0; $i < $n; $i++)
        <tr>
            <td>{{ $ak['pemasukan'][$i]['label'] ?? '' }}</td>
            <td class="num">{{ isset($ak['pemasukan'][$i]) ? $rp($ak['pemasukan'][$i]['total']) : '' }}</td>
            <td>{{ $ak['pengeluaran'][$i]['label'] ?? '' }}</td>
            <td class="num">{{ isset($ak['pengeluaran'][$i]) ? $rp($ak['pengeluaran'][$i]['total']) : '' }}</td>
        </tr>
        @endfor
        <tr class="subtotal"><td>Total pemasukan</td><td class="num">{{ $rp($ak['total_pemasukan']) }}</td><td>Total pengeluaran</td><td class="num">{{ $rp($ak['total_pengeluaran']) }}</td></tr>
        <tr class="grand"><td colspan="3">Arus kas bersih</td><td class="num">{{ $rp($ak['net']) }}</td></tr>
    </tbody>
</table>

<table class="data">
    <thead>
        <tr><th>Saldo Kas</th><th style="text-align:right">Awal</th><th style="text-align:right">Masuk</th><th style="text-align:right">Keluar</th>
            <th style="text-align:right">Mutasi +</th><th style="text-align:right">Mutasi −</th><th style="text-align:right">Akhir</th>
            @if(!empty($kasFisik))<th style="text-align:right">Fisik</th><th style="text-align:right">Selisih</th>@endif</tr>
    </thead>
    <tbody>
        @foreach($sk['rows'] as $r)
        @php $f = $kasFisik[$r['kas_id']] ?? null; @endphp
        <tr class="{{ $f && (float) $f['selisih'] != 0 ? 'highlight-warning' : '' }}">
            <td>{{ $r['nama_kas'] }}@if($cabangId === null) <span style="color:#64748b">({{ $r['cabang'] }})</span>@endif</td>
            <td class="num">{{ $rp($r['saldo_awal']) }}</td><td class="num">{{ $rp($r['masuk']) }}</td><td class="num">{{ $rp($r['keluar']) }}</td>
            <td class="num">{{ $rp($r['mutasi_masuk']) }}</td><td class="num">{{ $rp($r['mutasi_keluar']) }}</td><td class="num">{{ $rp($r['saldo_akhir']) }}</td>
            @if(!empty($kasFisik))
            <td class="num">{{ $f ? $rp($f['saldo_fisik']) : '' }}</td><td class="num">{{ $f ? $rp($f['selisih']) : '' }}</td>
            @endif
        </tr>
        @endforeach
        <tr class="grand">
            <td>Total (Tunai {{ $rp($sk['total_tunai']['saldo_akhir']) }} · Bank {{ $rp($sk['total_bank']['saldo_akhir']) }})</td>
            <td class="num">{{ $rp($sk['total']['saldo_awal']) }}</td><td class="num">{{ $rp($sk['total']['masuk']) }}</td><td class="num">{{ $rp($sk['total']['keluar']) }}</td>
            <td class="num">{{ $rp($sk['total']['mutasi_masuk']) }}</td><td class="num">{{ $rp($sk['total']['mutasi_keluar']) }}</td><td class="num">{{ $rp($sk['total']['saldo_akhir']) }}</td>
            @if(!empty($kasFisik))<td colspan="2"></td>@endif
        </tr>
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Mutasi Internal (bukan omzet/pengeluaran)</th><th style="text-align:right">Keluar</th><th style="text-align:right">Masuk</th><th style="text-align:right">Belum Diterima</th></tr></thead>
    <tbody>
        @foreach($mt['ringkasan'] as $g)
        <tr><td>{{ $g['label'] }}</td><td class="num">{{ $rp($g['keluar']) }}</td><td class="num">{{ $rp($g['masuk']) }}</td><td class="num">{{ $rp($g['dalam_perjalanan']) }}</td></tr>
        @endforeach
    </tbody>
</table>

@if(!empty($mt['detail']))
<table class="data">
    <thead><tr><th>Tanggal</th><th>Jenis</th><th>Dari</th><th>Ke</th><th style="text-align:right">Jumlah</th><th>Status</th></tr></thead>
    <tbody>
        @foreach($mt['detail'] as $x)
        <tr><td>{{ \Carbon\Carbon::parse($x['tanggal'])->format('d/m/Y') }}</td><td>{{ $x['jenis'] }}</td><td>{{ $x['dari'] }}</td><td>{{ $x['ke'] }}</td>
            <td class="num">{{ $rp($x['jumlah']) }}</td><td>{{ $x['status'] }}</td></tr>
        @endforeach
    </tbody>
</table>
@endif

<table class="data">
    <thead><tr><th>Nilai Persediaan Stok</th><th style="text-align:right">Nilai</th></tr></thead>
    <tbody>
        <tr><td>Bahan baku & tambahan</td><td class="num">{{ $rp($st['bahan_baku']) }}</td></tr>
        <tr><td>Kemasan</td><td class="num">{{ $rp($st['kemasan']) }}</td></tr>
        <tr><td>Barang jadi</td><td class="num">{{ $rp($st['barang_jadi']) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="num">{{ $rp($st['total']) }}</td></tr>
    </tbody>
</table>

@if(!empty($data['per_cabang']))
<table class="data">
    <thead><tr><th>Cabang</th><th style="text-align:right">Trx</th><th style="text-align:right">Omzet</th><th style="text-align:right">HPP</th>
        <th style="text-align:right">Laba Kotor</th><th style="text-align:right">Beban</th><th style="text-align:right">Laba Bersih</th></tr></thead>
    <tbody>
        @foreach($data['per_cabang'] as $c)
        <tr class="{{ $c['laba_bersih'] < 0 ? 'highlight-danger' : '' }}">
            <td>{{ $c['nama_cabang'] }}</td><td class="num">{{ $c['jumlah_order'] }}</td><td class="num">{{ $rp($c['omzet']) }}</td>
            <td class="num">{{ $rp($c['hpp']) }}</td><td class="num">{{ $rp($c['laba_kotor']) }}</td><td class="num">{{ $rp($c['beban']) }}</td>
            <td class="num">{{ $rp($c['laba_bersih']) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
@endsection
