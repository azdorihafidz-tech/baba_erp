@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Kode Aset</th>
            <th>Nama Aset</th>
            <th>Kategori</th>
            <th>Lokasi</th>
            <th>Tgl Perolehan</th>
            <th class="text-end">Harga Perolehan</th>
            <th class="text-end">Nilai Buku</th>
            <th>Kondisi</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($assets as $a)
        <tr>
            <td>{{ $a->kode_aset }}</td>
            <td>{{ $a->nama_aset }}</td>
            <td>{{ $a->kategori?->nama_kategori ?? '-' }}</td>
            <td>{{ $a->lokasi?->nama_cabang ?? '-' }}</td>
            <td>{{ $a->tanggal_perolehan?->format('d/m/Y') ?? '-' }}</td>
            <td class="text-end">Rp {{ number_format($a->harga_perolehan, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($a->nilai_buku, 0, ',', '.') }}</td>
            <td>{{ $a->kondisi?->value ?? '-' }}</td>
            <td>{{ $a->status?->value ?? '-' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="9" class="empty-note">Tidak ada data aset.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
