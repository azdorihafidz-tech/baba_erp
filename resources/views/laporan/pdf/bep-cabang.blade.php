@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Cabang</th>
            <th class="text-end">Pendapatan</th>
            <th class="text-end">Biaya Tetap</th>
            <th class="text-end">BEP (Rupiah)</th>
            <th class="text-end">% Capaian</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($dataCabang as $row)
        <tr class="{{ $row['tercapai'] ? '' : 'highlight-warning' }}">
            <td>{{ $row['cabang']->nama_cabang }}</td>
            <td class="text-end">Rp {{ number_format($row['pendapatan'], 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($row['biaya_tetap'], 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($row['bep_rupiah'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['pct_bep'], 1, ',', '.') }}%</td>
            <td>{{ $row['tercapai'] ? 'Tercapai' : 'Belum Tercapai' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" class="empty-note">Tidak ada data.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
