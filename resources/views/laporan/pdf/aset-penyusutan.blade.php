@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Kode Aset</th>
            <th>Nama Aset</th>
            <th>Kategori</th>
            <th>Lokasi</th>
            <th class="text-end">Nilai Buku Awal</th>
            <th class="text-end">Penyusutan</th>
            <th class="text-end">Akumulasi</th>
            <th class="text-end">Nilai Buku Akhir</th>
        </tr>
    </thead>
    <tbody>
        @forelse($depreciations as $d)
        <tr>
            <td>{{ $d->asset?->kode_aset ?? '-' }}</td>
            <td>{{ $d->asset?->nama_aset ?? '-' }}</td>
            <td>{{ $d->asset?->kategori?->nama_kategori ?? '-' }}</td>
            <td>{{ $d->asset?->lokasi?->nama_cabang ?? '-' }}</td>
            <td class="text-end">Rp {{ number_format($d->nilai_buku_awal, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($d->jumlah_penyusutan, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($d->akumulasi_penyusutan, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($d->nilai_buku_akhir, 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8" class="empty-note">Tidak ada data penyusutan periode ini.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
