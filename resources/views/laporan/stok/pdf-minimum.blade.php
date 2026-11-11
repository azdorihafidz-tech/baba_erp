@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th>Kategori</th>
            <th class="num">Stok Sekarang</th>
            <th class="num">Stok Minimum</th>
            <th class="num">Selisih</th>
            <th class="num">Rekomendasi Beli</th>
        </tr>
    </thead>
    <tbody>
        @forelse($stocks as $s)
        @php $selisih = max(0, (float) $s->qty_minimum - (float) $s->qty); @endphp
        <tr class="{{ $s->qty <= 0 ? 'highlight-danger' : 'highlight-warning' }}">
            <td>{{ $s->item?->kode_item ?? '-' }}</td>
            <td>{{ $s->item?->nama_item ?? '-' }}</td>
            <td>{{ $s->item?->category?->nama_kategori ?? '-' }}</td>
            <td class="num">{{ number_format($s->qty, 2, ',', '.') }}</td>
            <td class="num">{{ number_format($s->qty_minimum, 2, ',', '.') }}</td>
            <td class="num">{{ number_format($selisih, 2, ',', '.') }}</td>
            <td class="num">{{ number_format(ceil($selisih * 1.2), 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="empty-note">Tidak ada data untuk filter yang dipilih.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
