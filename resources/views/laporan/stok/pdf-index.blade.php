@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th>Kategori</th>
            <th>Satuan</th>
            <th class="num">Stok</th>
            <th class="num">Stok Minimum</th>
            <th class="center">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($stocks as $s)
        @php
            $status = $s->qty <= 0 ? 'Habis' : ($s->isBelowMinimum() ? 'Minim' : 'Aman');
            $rowClass = $status === 'Habis' ? 'highlight-danger' : ($status === 'Minim' ? 'highlight-warning' : '');
        @endphp
        <tr class="{{ $rowClass }}">
            <td>{{ $s->item?->kode_item ?? '-' }}</td>
            <td>{{ $s->item?->nama_item ?? '-' }}</td>
            <td>{{ $s->item?->category?->nama_kategori ?? '-' }}</td>
            <td>{{ $s->item?->satuan ?? '-' }}</td>
            <td class="num">{{ number_format($s->qty, 2, ',', '.') }}</td>
            <td class="num">{{ number_format($s->qty_minimum, 2, ',', '.') }}</td>
            <td class="center">{{ $status }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="empty-note">Tidak ada data untuk filter yang dipilih.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
