@extends('laporan.pdf.layout')

@section('content')
<table class="data" style="margin-bottom:16px;">
    <tbody>
        <tr class="section-head"><td colspan="4">RINGKASAN</td></tr>
        <tr>
            <td>Total Order: {{ number_format($ringkasan['total_order'], 0, ',', '.') }}</td>
            <td>Omzet: Rp {{ number_format($ringkasan['total_omzet'], 0, ',', '.') }}</td>
            <td>HPP: Rp {{ number_format($ringkasan['total_hpp'], 0, ',', '.') }}</td>
            <td>Untung: Rp {{ number_format($ringkasan['total_untung'], 0, ',', '.') }} ({{ $ringkasan['margin'] !== null ? $ringkasan['margin'] . '%' : '-' }})</td>
        </tr>
    </tbody>
</table>

<div class="section-head" style="font-weight:bold;margin-bottom:4px;">BREAKDOWN PER {{ strtoupper($labelLevel) }}</div>
<table class="data">
    <thead>
        @if($level === 'order')
        <tr><th>No Order</th><th>Tanggal</th><th>Pelanggan</th><th>Kasir</th><th class="num">Omzet</th><th class="num">HPP</th><th class="num">Untung</th><th class="num">Margin</th></tr>
        @elseif($level === 'kategori')
        <tr><th>Kategori</th><th class="num">Omzet</th><th class="num">HPP</th><th class="num">Untung</th><th class="num">Margin</th></tr>
        @elseif($level === 'jenis_olahan')
        <tr><th>Jenis Menu</th><th class="num">Omzet</th><th class="num">HPP</th><th class="num">Untung</th><th class="num">Margin</th></tr>
        @else
        <tr><th>Item</th><th>Tipe</th><th class="num">Qty</th><th>Satuan</th><th class="num">Omzet</th><th class="num">HPP</th><th class="num">Untung</th><th class="num">Margin</th></tr>
        @endif
    </thead>
    <tbody>
        @forelse($breakdownPenuh as $b)
        @php $margin = $b->margin !== null ? $b->margin . '%' : '-'; @endphp
        <tr>
            @if($level === 'order')
            <td>{{ $b->nomor_order }}</td>
            <td>{{ \Carbon\Carbon::parse($b->tanggal_order)->format('d/m/Y') }}</td>
            <td>{{ $b->nama_pelanggan ?? 'Umum' }}</td>
            <td>{{ $b->kasir_nama ?? '-' }}</td>
            @elseif($level === 'kategori')
            <td>{{ ucfirst($b->kategori) }}</td>
            @elseif($level === 'jenis_olahan')
            <td>{{ ucfirst($b->jenis_olahan) }}</td>
            @else
            <td>{{ $b->nama_item }}</td>
            <td>{{ $b->tipe }}</td>
            <td class="num">{{ number_format($b->total_qty, 2, ',', '.') }}</td>
            <td>{{ $b->satuan }}</td>
            @endif
            <td class="num">Rp {{ number_format($b->total_omzet, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($b->total_hpp, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($b->total_untung, 0, ',', '.') }}</td>
            <td class="num">{{ $margin }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8" class="empty-note">Tidak ada data untuk filter yang dipilih.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="section-head" style="font-weight:bold;margin:10px 0 4px;">DETAIL TRANSAKSI</div>
<table class="data">
    <thead>
        <tr>
            <th>Tanggal</th><th>Waktu</th><th>No Order</th><th>Kategori</th><th>Item</th>
            <th class="num">Qty</th><th>Satuan</th><th class="num">Omzet</th><th class="num">HPP</th><th class="num">Untung</th><th>Kasir</th>
        </tr>
    </thead>
    <tbody>
        @forelse($detail as $tanggal => $baris)
            @foreach($baris as $r)
            <tr>
                <td>{{ $tanggal }}</td>
                <td>{{ \Carbon\Carbon::parse($r->order_created_at)->format('H:i') }}</td>
                <td>{{ $r->nomor_order }}</td>
                <td>{{ ucfirst($r->kategori) }}</td>
                <td>{{ $r->nama_item }}</td>
                <td class="num">{{ number_format($r->qty, 2, ',', '.') }}</td>
                <td>{{ $r->satuan }}</td>
                <td class="num">Rp {{ number_format($r->omzet, 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format($r->hpp, 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format($r->untung, 0, ',', '.') }}</td>
                <td>{{ $r->kasir_nama ?? '-' }}</td>
            </tr>
            @endforeach
        @empty
        <tr>
            <td colspan="11" class="empty-note">Tidak ada transaksi untuk filter yang dipilih.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
