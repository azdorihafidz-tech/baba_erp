@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Produk</th>
            <th class="text-end">Harga Jual/Unit</th>
            <th class="text-end">Biaya Variabel/Unit</th>
            <th class="text-end">BEP (Unit)</th>
            <th class="text-end">BEP (Rupiah)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($products as $p)
        <tr>
            <td>{{ $p->nama_produk }}</td>
            <td class="text-end">Rp {{ number_format($p->harga_jual_per_unit, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($p->biaya_variabel_per_unit, 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($p->bep_unit, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($p->bep_rupiah, 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="5" class="empty-note">Belum ada setting BEP untuk periode ini.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
