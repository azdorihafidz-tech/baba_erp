@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Cabang</th>
            <th class="num">Total Sistem</th>
            <th class="num">Total Disetor</th>
            <th class="num">Selisih</th>
            <th class="center">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($setorans as $s)
        <tr class="{{ (float) $s->selisih !== 0.0 ? ((float) $s->selisih < 0 ? 'highlight-danger' : 'highlight-warning') : '' }}">
            <td>{{ $s->tanggal->format('d/m/Y') }}</td>
            <td>{{ $s->cabang?->nama_cabang ?? '-' }}</td>
            <td class="num">Rp {{ number_format($s->total_penjualan_sistem, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($s->total_disetor, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($s->selisih, 0, ',', '.') }}</td>
            <td class="center">{{ ucfirst($s->status->value) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" class="empty-note">Tidak ada data pada periode ini.</td>
        </tr>
        @endforelse
    </tbody>
    @if($setorans->isNotEmpty())
    <tfoot>
        <tr class="grand">
            <td colspan="2">Total {{ $setorans->count() }} setoran</td>
            <td class="num">Rp {{ number_format($stats['total_sistem'], 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($stats['total_disetor'], 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($stats['total_selisih'], 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
