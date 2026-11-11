@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Jam</th>
            <th class="text-end">Jumlah Transaksi</th>
            <th class="text-end">Total Nominal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($analisa['per_jam'] as $row)
        <tr class="{{ ($analisa['jam_puncak']['jam'] ?? null) === $row['jam'] ? 'highlight-warning' : '' }}">
            <td>{{ $row['label'] }}</td>
            <td class="text-end">{{ number_format($row['jumlah_transaksi'], 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($row['total_nominal'], 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
